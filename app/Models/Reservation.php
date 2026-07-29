<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
        return $this->participants()->where('is_booker', true)->exists();
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
     * Payments are not itemised with amounts anywhere — a submission records the method
     * and a reference, not a figure — so money is derived from the trip's live price and
     * the reservation's state. A deposit trip collects `payable_now`; the rest is due to
     * the host on the day.
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
        $paid = $this->status === 'confirmed'
            ? $trip->payable_now * $this->party_size
            : 0.0;

        return ['total' => $total, 'paid' => $paid, 'due' => max(0, $total - $paid)];
    }

    /** Confirmed but still owing the balance of a deposit booking. */
    public function owesBalance(): bool
    {
        $money = $this->money();

        return $this->status === 'confirmed' && $money['due'] > 0;
    }

    public function isOffline(): bool
    {
        return $this->source === 'manual';
    }
}
