<?php

namespace App\Services;

use App\Mail\SeatOpenedMail;
use App\Models\TripDeparture;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Mail;

/**
 * Catching the people a sold-out departure would otherwise turn away.
 *
 * Seats free up constantly here — an unpaid hold lapses every few hours — so "sold out" is very
 * often temporary, and the only thing missing was somewhere to put the interest in the meantime.
 */
class WaitlistService
{
    public function join(TripDeparture $departure, array $attrs): WaitlistEntry
    {
        // updateOrCreate on the email: asking twice is a person checking whether it worked, not
        // a second person, and a unique violation is a poor answer to that.
        return WaitlistEntry::updateOrCreate(
            ['trip_departure_id' => $departure->id, 'email' => strtolower(trim($attrs['email']))],
            [
                'user_id' => $attrs['user_id'] ?? null,
                'name' => trim($attrs['name']),
                'phone' => $attrs['phone'] ?? null,
                'party_size' => max(1, (int) ($attrs['party_size'] ?? 1)),
            ],
        );
    }

    /**
     * Tell the front of the queue that a seat opened.
     *
     * First come, and only as many as the free seats can actually hold — telling twenty people
     * about one seat is a race that nineteen of them lose after being invited to try.
     *
     * Notified entries are not told again. They had their chance at an open seat; re-alerting on
     * every subsequent expiry is how a useful email becomes one people filter away.
     */
    public function notifyAvailable(TripDeparture $departure): int
    {
        $free = $departure->spotsLeft();

        // The mail sends people to the join page, so say nothing when that page would turn them away: it is a 404
        // for a trip that is not published, and closed for a date that was called off, has run or has left.
        if ($free < 1
            || $departure->trip->status !== 'published'
            || in_array($departure->status, ['cancelled', 'completed'], true)
            || $departure->starts_at->isPast()) {
            return 0;
        }

        $told = 0;

        $waiting = $departure->waitlistEntries()->waiting()->oldest()->get();

        foreach ($waiting as $entry) {
            if ($entry->party_size > $free) {
                continue;   // a party of four does not fit one seat; leave them in the queue
            }

            try {
                Mail::to($entry->email)->send(new SeatOpenedMail($entry));
            } catch (\Throwable $e) {
                report($e);

                continue;   // a bounce must not consume their place in the queue
            }

            $entry->update(['notified_at' => now()]);
            $free -= $entry->party_size;
            $told++;

            if ($free < 1) {
                break;
            }
        }

        return $told;
    }
}
