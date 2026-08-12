<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calendar days an event occupies.
 *
 * Both dashboards used to group by `starts_at` alone, so a three-day traverse appeared on the
 * Friday and vanished on the Saturday and Sunday it was actually running. A host looking at
 * their month saw a free weekend they were not free on. This is the one definition of "which
 * squares does this event belong in", shared so the two grids cannot drift apart.
 */
class DaySpan
{
    /**
     * A guard, not a policy.
     *
     * Nothing here should ever span a year, but a typo in an end date must not be able to make a
     * calendar render tens of thousands of cells and take the page down with it.
     */
    public const MAX_DAYS = 366;

    /**
     * Every ISO date the event touches, optionally clipped to a window.
     *
     * @return array<int, string>
     */
    public static function dates(
        ?DateTimeInterface $start,
        ?DateTimeInterface $end = null,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
    ): array {
        if (! $start) {
            return [];
        }

        $first = Carbon::instance($start)->startOfDay();
        $last = $end ? Carbon::instance($end)->startOfDay() : $first->copy();

        // An end before its start is bad data, not a backwards trip. Treat it as a single day
        // rather than looping to nowhere or, worse, looping forever.
        if ($last->lt($first)) {
            $last = $first->copy();
        }

        if ($from) {
            $first = $first->max(Carbon::instance($from)->startOfDay());
        }

        if ($to) {
            $last = $last->min(Carbon::instance($to)->startOfDay());
        }

        if ($last->lt($first)) {
            return [];   // the event misses the window entirely
        }

        $dates = [];

        for ($day = $first->copy(); $day->lte($last) && count($dates) < self::MAX_DAYS; $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * Group items under every date they run on, rather than only the date they begin.
     *
     * An item spanning three days is present in three buckets — the same instance, not a copy,
     * so anything counting totals off the result has to reduce to unique first.
     *
     * @param  \Closure(mixed): array{0: ?DateTimeInterface, 1: ?DateTimeInterface}  $range
     * @return Collection<string, Collection<int, mixed>>
     */
    public static function group(
        iterable $items,
        \Closure $range,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
    ): Collection {
        $grouped = [];

        foreach ($items as $item) {
            [$start, $end] = $range($item);

            foreach (self::dates($start, $end, $from, $to) as $iso) {
                $grouped[$iso][] = $item;
            }
        }

        ksort($grouped);

        return collect($grouped)->map(fn (array $items) => collect($items));
    }

    /** How many calendar days the event covers, unclipped. Always at least 1. */
    public static function length(?DateTimeInterface $start, ?DateTimeInterface $end = null): int
    {
        return max(1, count(self::dates($start, $end)));
    }
}
