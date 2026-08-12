<?php

namespace App\Support;

use App\Models\Reservation;
use Illuminate\Support\Collection;

/**
 * What a run of trips adds up to.
 *
 * A list of past trips says what you did; it cannot say how much. Twelve rows of names are twelve
 * rows of names — "4 trips · 6,240 m climbed · 71 km on foot" is the sentence somebody actually
 * wants out of their own history, and the one they screenshot.
 *
 * Everything here is deliberately tolerant of missing data. Trail figures are optional on a trip,
 * plenty of hosts fill in none of them, and a summary that renders "0 m" for a month of island
 * hops is worse than one that stays quiet about elevation.
 */
class TrailStats
{
    /**
     * Totals for a set of bookings.
     *
     * Counts the trip, not the seat: a party of four on one departure climbed one mountain
     * between them, and multiplying the elevation by party size would be a lie the joiner can
     * spot at a glance.
     *
     * @param  Collection<int, Reservation>  $reservations
     * @return array{trips:int, days:int, ascent:int|null, distance:float|null, hours:float|null, peak:int|null, inclusions:Collection<int, string>}
     */
    public static function summarise(Collection $reservations): array
    {
        $ascent = 0;
        $distance = 0.0;
        $hours = 0.0;
        $peak = 0;
        $days = 0;
        $inclusions = collect();

        // Any figure at all, from any trip in the set. Without this a month where one host filled
        // in distances and three did not would total to a number that looks authoritative and is
        // quietly wrong — better to show what is known and say nothing about the rest.
        $sawAscent = $sawDistance = $sawHours = $sawPeak = false;

        foreach ($reservations as $r) {
            $trip = $r->departure?->trip;

            if (! $trip) {
                continue;
            }

            $days += $r->departure->spanDays();

            foreach ($trip->hikeDetails as $hike) {
                if (filled($hike->elevation_gain_m)) {
                    $ascent += (int) $hike->elevation_gain_m;
                    $sawAscent = true;
                }

                if (filled($hike->distance_km)) {
                    $distance += (float) $hike->distance_km;
                    $sawDistance = true;
                }

                if (filled($hike->duration_hours)) {
                    $hours += (float) $hike->duration_hours;
                    $sawHours = true;
                }

                // The highest point stood on, not a sum: adding two summits together produces a
                // number that corresponds to nothing on earth.
                if (filled($hike->masl)) {
                    $peak = max($peak, (int) $hike->masl);
                    $sawPeak = true;
                }
            }

            $inclusions = $inclusions->merge($trip->inclusions ?? []);
        }

        return [
            'trips' => $reservations->count(),
            'days' => $days,
            'ascent' => $sawAscent ? $ascent : null,
            'distance' => $sawDistance ? round($distance, 1) : null,
            'hours' => $sawHours ? round($hours, 1) : null,
            'peak' => $sawPeak ? $peak : null,
            // Distinct and capped: this is a texture line under the numbers, not a manifest.
            'inclusions' => $inclusions->filter()->unique()->values(),
        ];
    }

    /**
     * The summary as a row of tiles, already formatted and already filtered to what is known.
     *
     * Returning display-ready rows rather than raw numbers keeps the "is this worth showing?"
     * decision in one place instead of repeated as a Blade conditional per tile.
     *
     * @return array<int, array{label:string, value:string, sub:string|null}>
     */
    public static function tiles(array $stats): array
    {
        $tiles = [[
            'label' => 'Trips',
            'value' => (string) $stats['trips'],
            'sub' => $stats['days'] > 1 ? $stats['days'].' days out' : null,
        ]];

        if ($stats['ascent']) {
            $tiles[] = [
                'label' => 'Climbed',
                'value' => number_format($stats['ascent']).' m',
                // Roughly Everest-from-sea-level, which is the only unit of ascent anyone has an
                // intuition for. Only once it is actually meaningful.
                'sub' => $stats['ascent'] >= 4000
                    ? round($stats['ascent'] / 8849, 2).'× Everest'
                    : null,
            ];
        }

        if ($stats['distance']) {
            $tiles[] = [
                'label' => 'Distance',
                'value' => rtrim(rtrim(number_format($stats['distance'], 1), '0'), '.').' km',
                'sub' => null,
            ];
        }

        if ($stats['peak']) {
            $tiles[] = [
                'label' => 'Highest',
                'value' => number_format($stats['peak']).' m',
                'sub' => 'above sea level',
            ];
        }

        if ($stats['hours']) {
            $tiles[] = [
                'label' => 'On the trail',
                'value' => rtrim(rtrim(number_format($stats['hours'], 1), '0'), '.').' hrs',
                'sub' => null,
            ];
        }

        return $tiles;
    }
}
