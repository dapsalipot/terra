<?php

namespace App\Services;

use App\Exceptions\InvalidTransitionException;
use App\Exceptions\TripFullException;
use App\Models\PaymentSubmission;
use App\Models\Reservation;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReservationService
{
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
                throw new TripFullException();
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
        $reservation = $this->reserve($departure, array_merge($attrs, ['source' => 'manual', 'joiner_id' => null]));

        $reservation->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'expires_at' => null,
        ]);

        return $reservation;
    }

    /** Joiner uploads proof of payment. */
    public function submitPayment(Reservation $reservation, array $payment): PaymentSubmission
    {
        $this->assertStatus($reservation, ['pending', 'awaiting_payment'], 'submit payment for');

        $submission = $reservation->paymentSubmissions()->create([
            'method' => $payment['method'] ?? null,
            'reference_no' => $payment['reference_no'] ?? null,
            'proof_image' => $payment['proof_image'] ?? null,
            'submitted_at' => now(),
        ]);

        $reservation->update(['status' => 'payment_submitted']);

        return $submission;
    }

    /** Host confirms a submitted payment. */
    public function confirm(Reservation $reservation, User $host): Reservation
    {
        $this->assertStatus($reservation, ['payment_submitted'], 'confirm');

        $reservation->latestPayment?->update(['confirmed_at' => now(), 'confirmed_by' => $host->id]);
        $reservation->update(['status' => 'confirmed', 'confirmed_at' => now(), 'expires_at' => null]);

        return $reservation;
    }

    /** Host rejects a submission; the seat is held while the joiner re-submits. */
    public function reject(Reservation $reservation, User $host, string $reason): Reservation
    {
        $this->assertStatus($reservation, ['payment_submitted'], 'reject');

        $reservation->latestPayment?->update(['rejected_reason' => $reason]);
        $reservation->update(['status' => 'awaiting_payment', 'expires_at' => now()->addHours($this->holdHours)]);

        return $reservation;
    }

    /** Cancel a reservation, releasing its seat. */
    public function cancel(Reservation $reservation, ?string $reason = null): Reservation
    {
        if ($reservation->status === 'cancelled') {
            throw InvalidTransitionException::from($reservation->status, 'cancel');
        }

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'expires_at' => null,
        ]);

        if ($reservation->departure) {
            $this->syncDepartureFullness($reservation->departure->fresh());
        }

        return $reservation;
    }

    /** Release unpaid holds past their expiry. Returns the number released. */
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

    private function syncDepartureFullness(TripDeparture $departure): void
    {
        if ($departure->status === 'cancelled' || $departure->status === 'completed') {
            return;
        }
        $departure->update(['status' => $departure->spotsLeft() <= 0 ? 'full' : 'open']);
    }
}
