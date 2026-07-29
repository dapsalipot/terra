<?php

use App\Models\Trip;
use App\Models\TripDeparture;

it('shows a published trip with its departures and price', function () {
    $trip = Trip::factory()->published()->create(['title' => 'Mount Rinjani Traverse', 'price' => 180]);
    TripDeparture::factory()->for($trip)->create(['capacity' => 14, 'status' => 'open', 'starts_at' => now()->addWeek()]);

    $this->get("/t/{$trip->slug}")->assertOk()
        ->assertSee('Mount Rinjani Traverse')
        ->assertSee('180')
        ->assertSee('14 spots');
});

it('404s on a draft trip', function () {
    $trip = Trip::factory()->create(['status' => 'draft']);
    $this->get("/t/{$trip->slug}")->assertNotFound();
});
