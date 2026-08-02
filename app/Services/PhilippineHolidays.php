<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Philippine public holidays, for planning a trip around a long weekend.
 *
 * Sourced from Nager.Date, which is free and needs no key. Two caveats worth knowing:
 *
 * 1. Philippine holidays are declared each year by Presidential Proclamation, and dates are
 *    routinely moved to create long weekends. Any third-party list is a best-effort mirror,
 *    not an authority — it can lag a late proclamation and may omit special non-working days.
 *    The UI therefore presents these as a planning aid, never as a guarantee.
 * 2. This is an outbound call on a page render, so it is cached hard and fails silently. A
 *    joiner's own bookings must never fail to load because a holiday API is down.
 */
class PhilippineHolidays
{
    private const ENDPOINT = 'https://date.nager.at/api/v3/PublicHolidays';

    /** Long enough to be cheap, short enough to pick up a mid-year proclamation. */
    private const TTL_DAYS = 7;

    /**
     * Holidays for a year, keyed by Y-m-d.
     *
     * @return array<string, string>  date => local name
     */
    public function forYear(int $year): array
    {
        return Cache::remember("ph-holidays.{$year}", now()->addDays(self::TTL_DAYS), function () use ($year) {
            try {
                $response = Http::timeout(4)->retry(1, 200)->get(self::ENDPOINT."/{$year}/PH");

                if (! $response->successful()) {
                    return [];
                }

                return collect($response->json())
                    ->filter(fn ($h) => filled($h['date'] ?? null))
                    ->mapWithKeys(fn ($h) => [
                        $h['date'] => $h['localName'] ?: ($h['name'] ?? 'Holiday'),
                    ])
                    ->all();
            } catch (\Throwable $e) {
                // Silent by design. A holiday hint is a nicety; the dashboard is not.
                Log::warning('Holiday lookup failed', ['year' => $year, 'error' => $e->getMessage()]);

                return [];
            }
        });
    }

    /**
     * Holidays covering a date range, which routinely spans a year boundary because the
     * calendar grid renders whole weeks and spills into the neighbouring months.
     *
     * @return array<string, string>
     */
    public function between(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $years = range((int) $from->format('Y'), (int) $to->format('Y'));

        return collect($years)
            ->flatMap(fn (int $year) => $this->forYear($year))
            ->filter(fn ($_, string $date) => $date >= $from->format('Y-m-d') && $date <= $to->format('Y-m-d'))
            ->all();
    }
}
