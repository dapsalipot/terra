<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class RemindExpiringHolds extends Command
{
    protected $signature = 'reservations:remind {--hours=3 : How close to expiry to warn}';

    protected $description = 'Email joiners whose unpaid hold is about to be released';

    public function handle(ReservationService $service): int
    {
        $count = $service->remindExpiringHolds((int) $this->option('hours'));

        $this->info($count.' hold(s) warned.');

        return self::SUCCESS;
    }
}
