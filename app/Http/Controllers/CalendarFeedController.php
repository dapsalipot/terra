<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\User;
use App\Support\Ics;

/**
 * A joiner's trips, as a calendar anything can subscribe to.
 *
 * No OAuth, no stored tokens for anyone else's account, no Google verification. The alternative —
 * writing events into their Google Calendar through the API — needs a sensitive scope, a security
 * assessment, and a refresh token per user, to do a job a text file already does.
 *
 * Ceiling: Google re-polls subscribed feeds on its own schedule, often hours behind. Fine for a
 * trip weeks out; urgent changes still go through chat and email.
 * ponytail: swap to the Calendar API only if same-hour propagation turns out to matter.
 */
class CalendarFeedController extends Controller
{
    /** Every live booking a joiner holds. Authenticated by the unguessable token alone. */
    public function feed(string $token)
    {
        $user = User::where('calendar_token', $token)->firstOrFail();

        $reservations = Reservation::where('joiner_id', $user->id)
            ->with('departure.trip.host', 'departure.trip.place')
            ->get()
            ->filter(fn (Reservation $r) => $r->departure !== null)
            ->sortBy(fn (Reservation $r) => $r->departure->starts_at)
            ->values();

        return $this->respond(Ics::feed($reservations, 'Terra trips'), 'terra-trips.ics');
    }

    /**
     * One booking, for the people who will never subscribe to anything.
     *
     * Reached with the booking token like every other joiner-facing page, so a guest with no
     * account can still get their trip into a calendar.
     */
    public function booking(string $token)
    {
        $reservation = Reservation::where('public_token', $token)
            ->with('departure.trip.host', 'departure.trip.place')
            ->firstOrFail();

        abort_if($reservation->departure === null, 404);

        return $this->respond(
            Ics::single($reservation, $reservation->departure->trip->title),
            'terra-'.$reservation->departure->trip->slug.'.ics',
        );
    }

    private function respond(string $body, string $filename)
    {
        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            // A calendar server that caches this defeats the point of a subscription.
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
