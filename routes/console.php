<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Warn before the axe falls, then swing it. Both hourly, the reminder first so a hold is
// never released in the same pass that would have warned about it.
Schedule::command('reservations:remind')->hourly();
Schedule::command('reservations:expire')->hourly();

Schedule::command('departures:complete')->daily();

// Monthly, so next year's holidays appear on their own once the proclamations are
// published, and a late correction upstream is picked up without anyone remembering to run it.
Schedule::command('holidays:import')->monthlyOn(1, '03:00');
