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

        /*
         * Credited against money that was actually recorded, never against the seat decision.
         *
         * Keying the middle branch on `status === 'confirmed'` meant accepting a seat credited
         * `payable_now` — which on a trip with no deposit is the entire price, so confirming
         * made a booking fully paid on its own and the two host actions collapsed into one.
         * A verified payment submission is the thing that says money arrived; the fully-paid tag
         * is the host saying the rest of it did too.
         */
        $paid = match (true) {
            $this->isFullyPaid() => $total,
            $this->hasVerifiedPayment() => $trip->payable_now * $this->party_size,
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
