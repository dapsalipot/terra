<?php

namespace App\Services;

use App\Exceptions\InvalidTransitionException;
use App\Exceptions\TripFullException;
use App\Models\PaymentSubmission;
use App\Models\Reservation;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\PaymentTerms;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(
        private BookingNotifier $notifier = new BookingNotifier,
        private CheckInService $passes = new CheckInService,
        private WaitlistService $waitlist = new WaitlistService,
    ) {}

    public int $holdHours = 24;

    /**
     * Atomically reserve seats on a departure. Locks the departure row so two
     * concurrent requests can never oversell — the cardinal failure per the spec.
     *
     * @throws TripFullException
     */
    public function reserve(TripDeparture $departure, array $attrs): Reservation
    {
        $party = (int) ($attrs['party_size'] ?? 1);

        return DB::transaction(function () use ($departure, $attrs, $party) {
            $locked = TripDeparture::whereKey($departure->getKey())->lockForUpdate()->firstOrFail();

            $held = (int) $locked->reservations()
                ->whereIn('status', Reservation::HOLDING)
                ->sum('party_size');

            if ($held + $party > $locked->capacity) {
                throw new TripFullException;
            }

            $reservation = $locked->reservations()->create([
                'joiner_id' => $attrs['joiner_id'] ?? null,
                'guest_name' => $attrs['guest_name'] ?? null,
                'guest_phone' => $attrs['guest_phone'] ?? null,
                'source' => $attrs['source'] ?? 'app',
                'party_size' => $party,
                'status' => 'pending',
                'expires_at' => now()->addHours($this->holdHours),
            ]);

            $this->syncDepartureFullness($locked);

            return $reservation;
        });
    }

    /** Host adds a walk-in with no account. Consumes capacity, confirmed immediately. */
    public function addManualParticipant(TripDeparture $departure, array $attrs): Reservation
    {
        $party = max(1, (int) ($attrs['party_size'] ?? 1));
        $reservation = $this->reserve($departure, array_merge($attrs, ['source' => 'manual', 'joiner_id' => null]));

        $reservation->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'expires_at' => null,
        ]);

        /*
         * A walk-in is a person, not just a seat count.
         *
         * Without a participant row they had no name on the manifest, could never hold a pass and
         * could not be checked in at all — invisible to the feature built for exactly this
         * moment. The first row carries the name the host typed; any extra seats are unnamed
         * placeholders the host can fill in from the roster.
         */
        $name = trim((string) ($attrs['guest_name'] ?? ''));
        $parts = preg_split('/\s+/', $name, 2) ?: [];

        for ($i = 0; $i < $party; $i++) {
            $reservation->participants()->create([
                'position' => $i,
                'is_booker' => $i === 0,
                'first_name' => $i === 0 ? ($parts[0] ?? null) : null,
                'last_name' => $i === 0 ? ($parts[1] ?? null) : null,
            ]);
        }

        return $reservation;
    }

    /** Joiner uploads proof of payment. */
    public function submitPayment(Reservation $reservation, array $payment): PaymentSubmission
    {
        /*
         * You pay for a seat you have, not for one you are asking for.
         *
         * The order used to run the other way — pay, then the host confirms on the strength of
         * the proof — which meant strangers sending money for places that might not exist. A seat
         * is a scarce thing the host allocates; confirming is the allocation, and only then is
         * there something to pay for. Everything before confirmation is a request.
         *
         * A confirmed booking stays able to submit for as long as it owes anything, because a
         * deposit trip is settled in two goes and a rejected proof has to be replaceable.
         */
        $this->assertStatus($reservation, ['confirmed'], 'submit payment for');

        $submission = $reservation->paymentSubmissions()->create([
            'method' => $payment['method'] ?? null,
            'reference_no' => $payment['reference_no'] ?? null,
            'proof_image' => $payment['proof_image'] ?? null,
            'submitted_at' => now(),
        ]);

        // The seat is already theirs, so the status does not move. Sending money is not a fresh
        // request for a place, and knocking it back to `payment_submitted` would un-accept
        // somebody the host already let on the trip.

        // The one notification that goes the other way. Without it a host had to keep
        // refreshing the roster to find out that anything had arrived.
        $this->notifier->notifyHost($reservation);

        return $submission;
    }

    /**
     * The host grants the seat, and starts the payment clock.
     *
     * This is the allocation decision and nothing else — it accepts a person onto the trip. What
     * they then owe, and by when, is stamped onto the booking here from the trip's terms, because
     * a deadline a joiner was told about must not move when the host later edits the trip.
     */
    public function confirm(Reservation $reservation, User $host): Reservation
    {
        $this->assertStatus($reservation, ['pending', 'awaiting_payment', 'payment_submitted'], 'confirm');

        /*
         * Confirming accepts the seat. It is not a payment event, and now it cannot be one.
         *
         * It used to verify whatever submission was already sitting there, because under the old
         * order the joiner had paid first. Now nobody can pay before this moment, so there is
         * never anything here to verify — the host does that afterwards, deliberately, through
         * verifyPayment(). A legacy booking that still carries an unverified submission from the
         * old flow is left alone rather than silently credited: crediting money as a side effect
         * of a seat decision is exactly the collapse this codebase already had to undo once.
         */
        $confirmedAt = now();
        $trip = $reservation->departure?->trip;

        $reservation->update([
            'status' => 'confirmed',
            'confirmed_at' => $confirmedAt,
            // The hold clock stops; the payment clock starts. Both live in different columns
            // because a missed deadline no longer releases the seat — it only flags the booking.
            'expires_at' => null,
            'downpayment_due_at' => $trip ? PaymentTerms::downpaymentDue($trip, $confirmedAt) : null,
            'full_payment_due_at' => $trip ? PaymentTerms::fullPaymentDue($trip, $confirmedAt) : null,
        ]);

        $this->notifier->send($reservation, 'confirmed');

        return $reservation;
    }

    /**
     * The host has looked at a proof and says the money is really there.
     *
     * confirm() used to do this as a side effect, back when the joiner paid before the seat was
     * granted. Reversing that order left the credit with nowhere to happen: a deposit would land,
     * sit as an unverified submission, and money() would go on reporting nothing paid. This is
     * that decision, made on its own.
     *
     * It credits `payable_now` — the deposit on a trip that takes one, the whole price otherwise.
     * Settling the remaining balance is markFullyPaid(), still a separate act.
     */
    /**
     * The host has looked at a proof and says the money is really there.
     *
     * Takes the figure and, optionally, which travellers it settles. A booking is often a group
     * of friends where two have sent their share and two have not, and a host who can only accept
     * the whole booking has to either lie or wait.
     *
     * `$participantIds` empty means the payment covers everybody on the booking, which is what a
     * host means when they accept without narrowing it.
     *
     * @param  array<int, int>  $participantIds
     */
    public function verifyPayment(
        Reservation $reservation,
        User $host,
        ?float $amount = null,
        array $participantIds = [],
    ): Reservation {
        $this->assertStatus($reservation, ['confirmed', 'payment_submitted'], 'verify payment for');

        $payment = $reservation->latestPayment;

        if ($payment === null) {
            // Nothing was sent. Saying "verified" here would credit money against no record at
            // all — the host wants markFullyPaid(), which writes one.
            throw InvalidTransitionException::from($reservation->status, 'verify payment for');
        }

        /*
         * Default to what this joiner owes *next*, not to everything they will ever owe.
         *
         * On a deposit trip with nothing paid that is the deposit — which is what the joiner has
         * actually just sent — and once it is in, it becomes the balance. On a trip with no
         * deposit the two are the same number. Making the host retype a figure the app already
         * knows is how a typo gets recorded as fact.
         */
        $money = $reservation->money();
        $trip = $reservation->departure?->trip;
        $depositOutstanding = $trip
            ? max(0, ($trip->payable_now * $reservation->party_size) - $money['paid'])
            : 0;

        $amount ??= $depositOutstanding > 0 ? $depositOutstanding : $money['due'];

        $payment->update([
            'amount' => max(0, $amount),
            'confirmed_at' => now(),
            'confirmed_by' => $host->id,
            // A proof cannot be both refused and accepted. Verifying supersedes an earlier no.
            'rejected_reason' => null,
        ]);

        // Only ids that actually belong to this booking — the list comes from a form.
        $payment->participants()->sync(
            $reservation->participants->whereIn('id', $participantIds)->pluck('id')->all()
        );

        // A traveller whose share is settled gets their pass, whether or not the rest of the
        // group has paid. Waiting for the whole booking would hold four people's passes hostage
        // to a fifth who has not sent theirs.
        $this->passes->issueFor($reservation->fresh());

        return $reservation->fresh();
    }

    /**
     * The host says the whole amount is in.
     *
     * Separate from confirm(): confirming accepts the booking and credits the deposit, this says
     * the balance has landed too. A host can reach it from any live state, because someone can
     * pay in full up front just as easily as they can settle later.
     */
    public function markFullyPaid(Reservation $reservation, User $host): Reservation
    {
        $this->assertStatus($reservation, ['pending', 'awaiting_payment', 'payment_submitted', 'confirmed'], 'mark as fully paid');

        $reservation->update([
            'fully_paid_at' => now(),
            'fully_paid_by' => $host->id,
        ]);

        // Issued here because this is the moment the seat stops being provisional. Existing
        // codes are left alone, so re-tagging a booking does not invalidate a pass already
        // saved to somebody's phone.
        $this->passes->issueFor($reservation->fresh());

        return $reservation;
    }

    /**
     * Take it back.
     *
     * Marking paid is a human judgement about money that arrived somewhere this app cannot see,
     * so it is exactly the kind of thing that gets tapped by mistake. Without an undo the only
     * remedy was editing the database. Confirmation is deliberately left untouched — un-paying a
     * booking should not also throw away the host's decision to accept the seat.
     */
    public function unmarkFullyPaid(Reservation $reservation): Reservation
    {
        $reservation->update(['fully_paid_at' => null, 'fully_paid_by' => null]);

        $this->passes->revokeFor($reservation);

        return $reservation;
    }

    /** Host rejects a submission; the seat is held while the joiner re-submits. */
    public function reject(Reservation $reservation, User $host, string $reason): Reservation
    {
        // `payment_submitted` is only reachable on bookings made under the old pay-first order.
        // Kept accepted so those can still be worked, but proof now arrives on a confirmed seat.
        $this->assertStatus($reservation, ['confirmed', 'payment_submitted'], 'reject');

        $reservation->latestPayment?->update(['rejected_reason' => $reason]);

        /*
         * Turning down a payment does not turn down the person.
         *
         * This used to drop the booking to `awaiting_payment` and re-arm `expires_at`, which
         * under the old order was harmless — an unconfirmed hold expiring is the system working.
         * Now the seat has already been granted, and `expireStaleHolds()` would cancel a
         * confirmed traveller because their bank reference was mistyped. The seat stays; only the
         * proof was refused, and the joiner sends another.
         */
        if ($reservation->status === 'payment_submitted') {
            $reservation->update(['status' => 'awaiting_payment', 'expires_at' => now()->addHours($this->holdHours)]);
        }

        $this->notifier->send($reservation, 'rejected', $reason);

        return $reservation;
    }

    /** Cancel a reservation, releasing its seat. */
    public function cancel(Reservation $reservation, ?string $reason = null): Reservation
    {
        $this->passes->revokeFor($reservation);

        if ($reservation->status === 'cancelled') {
            throw InvalidTransitionException::from($reservation->status, 'cancel');
        }

        /*
         * Read the money before the status moves.
         *
         * money() cannot answer this afterwards — isFullyPaid() excludes cancelled bookings and
         * the paid branch keys off a live status, so a cancelled booking reports paid: 0. Whatever
         * the joiner is owed has to be captured here or it is gone.
         */
        $paid = $reservation->money()['paid'];

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'expires_at' => null,
            'refund_due' => $paid > 0 ? $paid : null,
        ]);

        if ($reservation->departure) {
            $departure = $reservation->departure->fresh();
            $this->syncDepartureFullness($departure);

            // The moment the waitlist exists for. Cancelling is the commonest way a seat comes
            // back — an expired hold routes through here too.
            $this->waitlist->notifyAvailable($departure->fresh());
        }

        // Not for a joiner cancelling their own booking — they were just told by the page they
        // did it on. This is for the ones done to them: an expired hold, a host removing them,
        // a departure called off.
        //
        // The exception is a seat they had already been granted. The confirmation put an event in
        // their calendar, and the page cannot take it out; only a cancellation delivered to their
        // mailbox can. No reason goes with it — "Cancelled by joiner" would be the joiner being
        // told what they just did.
        if ($reason !== 'Cancelled by joiner') {
            $this->notifier->send($reservation, 'cancelled', $reason);
        } elseif ($reservation->confirmed_at !== null) {
            $this->notifier->send($reservation, 'cancelled');
        }

        return $reservation;
    }

    /**
     * The host says they have sent the money back.
     *
     * Tarana never held it, so this records a fact about a transfer that happened elsewhere —
     * the same shape as marking a booking paid, and for the same reason.
     */
    public function markRefunded(Reservation $reservation, User $host, ?string $note = null): Reservation
    {
        $reservation->update([
            'refunded_at' => now(),
            'refunded_by' => $host->id,
            'refund_note' => $note,
        ]);

        return $reservation;
    }

    /** Release unpaid holds past their expiry. Returns the number released. */
    /**
     * The host calls the whole departure off.
     *
     * One decision, not a loop of individual cancellations: every live booking is released and
     * every joiner told once, and the departure itself is marked so the join page turns people
     * away instead of selling seats on a trip that is not running. `cancelled` was in the enum
     * from the first migration and nothing ever wrote it — the joiner-facing half of this
     * feature has been shipped and unreachable this whole time.
     */
    public function cancelDeparture(TripDeparture $departure, ?string $reason = null): int
    {
        $live = $departure->reservations()->whereIn('status', Reservation::HOLDING)->get();

        foreach ($live as $reservation) {
            // Same snapshot as cancel(): read the money before the status makes it unrecoverable.
            // This path wrote its own update and so skipped it — two cancellation routes, one of
            // which quietly lost every refund it owed.
            $paid = $reservation->money()['paid'];

            $this->passes->revokeFor($reservation);

            $reservation->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'expires_at' => null,
                'refund_due' => $paid > 0 ? $paid : null,
            ]);

            $this->notifier->send($reservation, 'departure_cancelled', $reason);
        }

        $departure->update(['status' => 'cancelled']);

        return $live->count();
    }

    /**
     * Warn holds that are about to lapse.
     *
     * Once each, hence the stamp: an hourly command with no memory would email the same person
     * every hour of the window, which trains them to ignore the one that matters. The warning is
     * only worth sending while there is still time to act on it, so anything already past its
     * expiry is left to expireStaleHolds().
     */
    public function remindExpiringHolds(int $withinHours = 3): int
    {
        $soon = Reservation::whereIn('status', ['pending', 'awaiting_payment'])
            ->whereNull('expiry_reminded_at')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addHours($withinHours)])
            ->get();

        foreach ($soon as $reservation) {
            $this->notifier->send($reservation, 'expiring');
            $reservation->update(['expiry_reminded_at' => now()]);
        }

        return $soon->count();
    }

    /**
     * The host removes someone: a no-show, a duplicate, a booking they know is not real.
     *
     * Distinct from the joiner cancelling themselves, which is why it carries its own reason —
     * cancel() only stays silent for "Cancelled by joiner", so this one does email them.
     */
    public function cancelAsHost(Reservation $reservation, ?string $reason = null): Reservation
    {
        return $this->cancel($reservation, $reason ?: 'Cancelled by your host');
    }

    public function expireStaleHolds(): int
    {
        $stale = Reservation::whereIn('status', ['pending', 'awaiting_payment'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($stale as $reservation) {
            $this->cancel($reservation, 'Hold expired');
        }

        return $stale->count();
    }

    private function assertStatus(Reservation $reservation, array $allowed, string $action): void
    {
        if (! in_array($reservation->status, $allowed, true)) {
            throw InvalidTransitionException::from($reservation->status, $action);
        }
    }

    /** Open or full, from the seats held. Cancelled and completed are decisions about the date, so they stay. */
    public function syncDepartureFullness(TripDeparture $departure): void
    {
        if ($departure->status === 'cancelled' || $departure->status === 'completed') {
            return;
        }
        $departure->update(['status' => $departure->spotsLeft() <= 0 ? 'full' : 'open']);
    }
}
