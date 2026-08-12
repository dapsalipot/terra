<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

/**
 * Philippine public holidays, for planning a trip around a long weekend.
 *
 * Reads our own table only. Nothing here touches the network: the data arrives through
 * `php artisan holidays:import`, scheduled monthly, so a page render never waits on a third
 * party and never fails because one is down.
 *
 * Holidays are declared each year by Presidential Proclamation and dates are routinely moved to
 * build long weekends. When that happens, a row with `source = 'manual'` overrides the import
 * permanently — see App\Console\Commands\ImportHolidays.
 */
class PhilippineHolidays
{
    /**
     * @return array<string, string> date (Y-m-d) => name
     */
    public function forYear(int $year): array
    {
        return $this->between(
            Carbon::create($year, 1, 1),
            Carbon::create($year, 12, 31),
        );
    }

    /**
     * Holidays covering a date range, which routinely spans a year boundary because the calendar
     * grid renders whole weeks and spills into the neighbouring months.
     *
     * @return array<string, string>
     */
    public function between(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return Holiday::between($from->format('Y-m-d'), $to->format('Y-m-d'))
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn (Holiday $h) => [$h->date->format('Y-m-d') => $h->name])
            ->all();
    }
}
