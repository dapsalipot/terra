<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'refunded_at' => 'datetime',
            'refund_due' => 'decimal:2',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'fully_paid_at' => 'datetime',
            'downpayment_due_at' => 'datetime',
            'full_payment_due_at' => 'datetime',
        ];
    }

    /** States that occupy a seat against departure capacity. */
    public const HOLDING = ['pending', 'awaiting_payment', 'payment_submitted', 'confirmed'];

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            $reservation->public_token ??= (string) Str::uuid();
        });
    }

    public function departure()
    {
        return $this->belongsTo(TripDeparture::class, 'trip_departure_id');
    }

    public function joiner()
    {
        return $this->belongsTo(User::class, 'joiner_id');
    }

    public function paymentSubmissions()
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function latestPayment()
    {
        return $this->hasOne(PaymentSubmission::class)->latestOfMany();
    }

    public function participants()
    {
        return $this->hasMany(ReservationParticipant::class)->orderBy('position');
    }

    /**
     * The traveler who is also the booker — null when the booker took no seat.
     *
     * There is deliberately no `lead()` relation: "lead" fused who pays with who travels,
     * and separating those is the point of this table.
     */
    public function bookerParticipant()
    {
        return $this->hasOne(ReservationParticipant::class)->where('is_booker', true);
    }

    public function contactName(): string
    {
        return trim(implode(' ', array_filter([
            $this->contact_first_name, $this->contact_middle_name, $this->contact_last_name,
        ])));
    }

    public function bookerIsTraveling(): bool
    {
        return $this->participants->contains('is_booker', true);
    }

    /** Travelers under 18 on the departure date. Drives the host's guardian-consent flag. */
    public function minors()
    {
        $date = $this->departure?->starts_at;

        return $date === null
            ? collect()
            : $this->participants->filter(fn ($p) => $p->isMinorAt($date))->values();
    }

    /** Travelers still owing permit details. Drives "3 of 5 details pending". */
    public function incompleteParticipants()
    {
        return $this->participants->reject->isComplete()->values();
    }

    public function displayName(): string
    {
        return $this->contactName()
            ?: ($this->joiner?->name ?? $this->guest_name ?? 'Guest');
    }

    /**
     * What this booking is worth, and what has actually been collected.
     *
     * A submission records what the host says arrived, so `paid` is a sum of real figures rather
     * than a guess from the booking's state. Submissions predating that carry no amount and fall
     * back to the old derivation.
     *
     * @return array{total: float, paid: float, due: float}
     */
    public function money(): array
    {
        $trip = $this->departure?->trip;

        if (! $trip) {
            return ['total' => 0.0, 'paid' => 0.0, 'due' => 0.0];
        }

        $total = $trip->current_price * $this->party_size;

        /*
         * Recorded money first, derived money only as a fallback.
         *
         * Never credited against the seat decision: confirming a booking used to credit
         * `payable_now`, which on a no-deposit trip is the whole price, so accepting somebody
         * onto a trip silently settled their bill.
         *
         * A host now types what arrived when they accept a proof, so `paid` is a sum of real
         * figures. Submissions made before that existed carry no amount, and for those the old
         * derivation still applies — reading them as zero would make historical bookings look
         * unpaid, and reading them as the full price would invent money.
         */
        $recorded = (float) $this->paymentSubmissions
            ->filter(fn ($p) => $p->confirmed_at && $p->amount !== null)
            ->sum('amount');

        $legacy = $this->paymentSubmissions
            ->contains(fn ($p) => $p->confirmed_at && $p->amount === null);

        $paid = match (true) {
            $this->isFullyPaid() => $total,
            $recorded > 0 => min($recorded, $total),
            $legacy => $trip->payable_now * $this->party_size,
            default => 0.0,
        };

        return ['total' => $total, 'paid' => $paid, 'due' => max(0, $total - $paid)];
    }

    /**
     * A payment the joiner submitted and the host verified.
     *
     * Reads the already-loaded latestPayment rather than querying the submissions, so a roster
     * of fifty bookings does not become fifty extra queries.
     */
    public function hasVerifiedPayment(): bool
    {
        return $this->latestPayment?->confirmed_at !== null;
    }

    /**
     * Whether this traveller's share has been settled.
     *
     * A payment that named participants covers exactly those; one that named none covers the
     * whole booking, which is what a host means when they accept without narrowing it and what
     * every pre-existing submission means.
     */
    public function participantIsPaid(ReservationParticipant $participant): bool
    {
        if ($this->isFullyPaid()) {
            return true;
        }

        return $this->paymentSubmissions
            ->filter(fn ($p) => $p->confirmed_at)
            ->contains(fn ($p) => $p->participants->isEmpty()
                || $p->participants->contains('id', $participant->id));
    }

    /** Money the joiner paid that has not been sent back yet. */
    public function owesRefund(): bool
    {
        return $this->refund_due > 0 && $this->refunded_at === null;
    }

    /** The host has said the full amount is in, over and above confirming the seat. */
    public function isFullyPaid(): bool
    {
        return $this->fully_paid_at !== null && $this->status !== 'cancelled';
    }

    /** Confirmed but still owing the balance of a deposit booking. */
    public function owesBalance(): bool
    {
        $money = $this->money();

        return $this->status === 'confirmed' && $money['due'] > 0;
    }

    /**
     * The seat is granted, so money can be sent.
     *
     * A single place for the rule, because three surfaces ask it — the checkout page deciding
     * whether to show a form, the joiner dashboard deciding whether to nag, and the service
     * refusing the write. Two of them disagreeing is how a "Pay now" button that throws gets
     * shipped.
     */
    public function canSubmitPayment(): bool
    {
        return $this->status === 'confirmed' && ! $this->isFullyPaid();
    }

    /**
     * The next deadline this booking is working towards, or null when the trip sets none.
     *
     * The downpayment comes first and stops mattering the moment it is credited; after that the
     * full settlement is the only date left. Returning one date rather than both keeps the
     * surfaces honest — a joiner needs to know what to do next, not the whole schedule.
     */
    public function nextPaymentDue(): ?Carbon
    {
        if ($this->isFullyPaid()) {
            return null;
        }

        if (! $this->hasVerifiedPayment() && $this->downpayment_due_at !== null) {
            return $this->downpayment_due_at;
        }

        return $this->full_payment_due_at;
    }

    /**
     * Past a deadline with money still owed.
     *
     * Deliberately only a flag. The host decided to let this person on the trip, so the system
     * does not get to take that back on a timer — it says who is late and leaves the judgement
     * where it belongs.
     */
    public function paymentOverdue(): bool
    {
        if ($this->status !== 'confirmed' || $this->isFullyPaid()) {
            return false;
        }

        $due = $this->nextPaymentDue();

        return $due !== null && $due->isPast();
    }

    /** What the overdue booking was supposed to have sent — the wording differs for each. */
    public function overdueLabel(): string
    {
        return ! $this->hasVerifiedPayment() && $this->downpayment_due_at !== null
            ? 'Downpayment overdue'
            : 'Payment overdue';
    }

    public function isOffline(): bool
    {
        return $this->source === 'manual';
    }
}
