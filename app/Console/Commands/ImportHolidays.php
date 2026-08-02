<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportHolidays extends Command
{
    protected $signature = 'holidays:import {year? : Defaults to this year and next}';

    protected $description = 'Import Philippine public holidays from Nager.Date into the holidays table.';

    private const ENDPOINT = 'https://date.nager.at/api/v3/PublicHolidays';

    public function handle(): int
    {
        $years = $this->argument('year')
            ? [(int) $this->argument('year')]
            : [now()->year, now()->year + 1];

        $failed = false;

        foreach ($years as $year) {
            $rows = $this->fetch($year);

            if ($rows === null) {
                $this->error("Could not fetch {$year}.");
                $failed = true;

                continue;
            }

            $written = 0;

            foreach ($rows as $row) {
                if (blank($row['date'] ?? null)) {
                    continue;
                }

                $existing = Holiday::onDate($row['date'])->first();

                // A hand-corrected row is the authority. Skipping it here is the whole reason
                // the source column exists.
                if ($existing && $existing->source === 'manual') {
                    continue;
                }

                // updateOrCreate's own lookup has the same blind spot, so resolve the row first
                // and update it explicitly when it exists.
                $attributes = [
                    'name' => $row['localName'] ?: ($row['name'] ?? 'Holiday'),
                    'source' => 'import',
                ];

                $existing
                    ? $existing->update($attributes)
                    : Holiday::create($attributes + ['date' => $row['date']]);

                $written++;
            }

            $this->info("{$year}: {$written} holiday(s) written.");
        }

        // A failed fetch must leave existing rows alone. Last year's good data beats none.
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, array>|null  null means the fetch failed */
    private function fetch(int $year): ?array
    {
        try {
            $response = Http::timeout(10)->retry(2, 300)->get(self::ENDPOINT."/{$year}/PH");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
