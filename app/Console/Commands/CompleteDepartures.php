<?php

namespace App\Console\Commands;

use App\Services\DepartureCompletionService;
use Illuminate\Console\Command;

class CompleteDepartures extends Command
{
    protected $signature = 'departures:complete';

    protected $description = 'Complete past departures and publish trip recaps to host walls.';

    public function handle(DepartureCompletionService $service): int
    {
        $count = $service->completePastDepartures();
        $this->info("Completed {$count} departure(s).");

        return self::SUCCESS;
    }
}
