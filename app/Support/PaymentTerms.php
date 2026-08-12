<?php

namespace App\Support;

use App\Models\Trip;
use Illuminate\Support\Carbon;

/**
 * When money is due, and how to say so in a sentence.
 *
 * A trip expresses each deadline one of three ways: a stretch of hours after the host confirms
 * the seat, a fixed calendar date, or nothing at all. This resolves that policy against a real
 * confirmation moment, which is the only point at which a relative term becomes a date.
 *
 * Kept out of the Trip model because both the wizard (validating what a host typed) and the
 * reservation service (stamping a booking) need it, and neither should have to reach through the
 * other to get at it.
 */
class PaymentTerms
{
    /** Longest deadline a host can set: a year of hours. Beyond that it is a typo, not a policy. */
    public const MAX_HOURS = 8760;

    /**
     * The moment a payment is due, or null when the trip asks for no deadline.
     *
     * A fixed date wins over a relative one. Both should never be set — the form enforces that —
     * but if data ever arrives with both, honouring the explicit calendar date is the reading
     * least likely to surprise the group who were all told the same day.
     */
    public static function resolve(?int $hours, ?Carbon $fixed, Carbon $confirmedAt): ?Carbon
    {
        if ($fixed !== null) {
            return $fixed->copy();
        }

        if ($hours !== null && $hours > 0) {
            return $confirmedAt->copy()->addHours(min($hours, self::MAX_HOURS));
        }

        return null;
    }

    /** The downpayment deadline for a booking confirmed at this moment, if the trip sets one. */
    public static function downpaymentDue(Trip $trip, Carbon $confirmedAt): ?Carbon
    {
        // A trip with no deposit has nothing to pay early, whatever the field says.
        if (! $trip->takesDeposit()) {
            return null;
        }

        return self::resolve(
            $trip->downpayment_due_hours,
            $trip->downpayment_due_at ? Carbon::parse($trip->downpayment_due_at) : null,
            $confirmedAt,
        );
    }

    /** The deadline for settling in full. Applies whether or not a deposit was taken. */
    public static function fullPaymentDue(Trip $trip, Carbon $confirmedAt): ?Carbon
    {
        return self::resolve(
            $trip->full_payment_due_hours,
            $trip->full_payment_due_at ? Carbon::parse($trip->full_payment_due_at) : null,
            $confirmedAt,
        );
    }

    /**
     * "48 hours" / "3 days" — hours divided back into days when they go in evenly.
     *
     * Hosts think in days and the storage is in hours, so 72 has to come back out as "3 days"
     * or the setting reads as though somebody else typed it.
     */
    public static function describeHours(int $hours): string
    {
        if ($hours >= 24 && $hours % 24 === 0) {
            $days = intdiv($hours, 24);

            return $days.' '.str('day')->plural($days);
        }

        return $hours.' '.str('hour')->plural($hours);
    }

    /** One line a joiner can act on: what is owed and by when. */
    public static function sentence(?Carbon $due, string $what): ?string
    {
        if ($due === null) {
            return null;
        }

        if ($due->isPast()) {
            return $what.' was due '.$due->diffForHumans();
        }

        return $what.' due '.$due->format('j M').' at '.$due->format('g:ia');
    }
}
