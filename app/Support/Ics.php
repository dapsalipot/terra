<?php

namespace App\Support;

use App\Models\Reservation;
use Illuminate\Support\Collection;

/**
 * iCalendar text, by hand.
 *
 * One VEVENT per booking. No dependency: a calendar file is a short text format, and the only
 * genuinely fiddly parts are line folding and escaping, both of which are a few lines and both
 * of which are covered by a test.
 */
class Ics
{
    /**
     * A whole feed a calendar app can subscribe to.
     *
     * @param  Collection<int, Reservation>  $reservations
     */
    public static function feed(Collection $reservations, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Terra//Trips//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($name),
            // Hint to clients about how often to re-poll. Google largely ignores it and refreshes
            // on its own schedule — see the ceiling noted on the route.
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ];

        foreach ($reservations as $reservation) {
            $lines = [...$lines, ...self::event($reservation)];
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /** One booking on its own, for a download rather than a subscription. */
    public static function single(Reservation $reservation, string $name): string
    {
        return self::feed(collect([$reservation]), $name);
    }

    /** @return array<int, string> */
    private static function event(Reservation $reservation): array
    {
        $departure = $reservation->departure;
        $trip = $departure->trip;

        /*
         * Floating local time — no Z, no TZID.
         *
         * The app stores and displays these without conversion, so a trip the roster calls 5am is
         * 5am. Stamping them UTC would show a Manila joiner 1pm for the same row. Floating means
         * "whatever local time the reader is in", which is right for a group that travels together
         * and wrong only for someone reading from another timezone — who has the same problem
         * everywhere else in the app.
         */
        $start = $departure->starts_at->format('Ymd\THis');
        $end = ($departure->ends_at ?: $departure->starts_at->copy()->addHours(8))->format('Ymd\THis');

        $where = collect([$trip->meetup_point, $trip->place?->name, $trip->place?->country])
            ->filter()->implode(', ');

        $description = collect([
            $trip->description,
            'Host: '.$trip->host->name,
            'Your booking: '.route('reservations.show', ['token' => $reservation->public_token]),
        ])->filter()->implode("\n\n");

        return array_filter([
            'BEGIN:VEVENT',
            // Stable per booking, so a re-poll updates the event instead of duplicating it.
            'UID:booking-'.$reservation->public_token.'@terra',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            // Bumped whenever the booking changes, which is what tells a client to replace it.
            'SEQUENCE:'.$reservation->updated_at->timestamp,
            'DTSTART:'.$start,
            'DTEND:'.$end,
            'SUMMARY:'.self::escape($trip->title),
            $where ? 'LOCATION:'.self::escape($where) : null,
            'DESCRIPTION:'.self::escape($description),
            'URL:'.route('trips.show', $trip),
            // A cancelled booking has to stay in the feed saying CANCELLED. Dropping it just
            // leaves the old event sitting in somebody's calendar forever.
            'STATUS:'.($reservation->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
        ]);
    }

    /** Escape the four characters that mean something structural in an ICS TEXT value. */
    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            trim($value),
        );
    }

    /**
     * Fold to 75 octets, continuing with a leading space.
     *
     * Octets, not characters: RFC 5545 counts bytes, and splitting mid-sequence would corrupt any
     * accented place name. mb_strcut cuts on a character boundary at or below the byte limit.
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out = mb_strcut($line, 0, 75);
        $rest = substr($line, strlen($out));

        while ($rest !== '') {
            // 74 to leave room for the leading space that marks a continuation.
            $chunk = mb_strcut($rest, 0, 74);
            $out .= "\r\n ".$chunk;
            $rest = substr($rest, strlen($chunk));
        }

        return $out;
    }
}
