<?php

use App\Models\Trip;
use App\Models\TripDeparture;

it('builds a published trip with departures and a slug', function () {
    $trip = Trip::factory()->published()->create(['title' => 'Mount Rinjani Traverse']);
    TripDeparture::factory()->count(2)->for($trip)->create();

    expect($trip->slug)->toBe('mount-rinjani-traverse')
        ->and($trip->departures)->toHaveCount(2)
        ->and(Trip::published()->count())->toBe(1);
});

it('reports spots left from capacity when there are no reservations yet', function () {
    $d = TripDeparture::factory()->create(['capacity' => 14]);
    expect($d->spotsLeft())->toBe(14);
});
