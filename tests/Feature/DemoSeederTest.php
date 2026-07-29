<?php

use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;

it('seeds a browsable marketplace', function () {
    $this->seed();

    expect(User::where('role', 'host')->count())->toBeGreaterThanOrEqual(3)
        ->and(Trip::published()->count())->toBeGreaterThanOrEqual(8)
        ->and(TripDeparture::where('status', 'open')->count())->toBeGreaterThanOrEqual(16)
        ->and(Trip::published()->first()->host->hostProfile)->not->toBeNull();
});
