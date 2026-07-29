<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Release unpaid reservation holds that have passed their expiry.';

    public function handle(ReservationService $service): int
    {
        $released = $service->expireStaleHolds();
        $this->info("Released {$released} stale hold(s).");

        return self::SUCCESS;
    }
}
