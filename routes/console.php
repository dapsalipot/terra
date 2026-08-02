<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:expire')->hourly();

Schedule::command('departures:complete')->daily();

// Monthly, so next year's holidays appear on their own once the proclamations are
// published, and a late correction upstream is picked up without anyone remembering to run it.
Schedule::command('holidays:import')->monthlyOn(1, '03:00');
