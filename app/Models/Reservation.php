<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function displayName(): string
    {
        return $this->joiner?->name ?? $this->guest_name ?? 'Guest';
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
