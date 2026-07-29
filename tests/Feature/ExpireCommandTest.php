<?php

use App\Models\TripDeparture;
use App\Services\ReservationService;

it('the reservations:expire command releases stale holds', function () {
    $departure = TripDeparture::factory()->create(['capacity' => 5]);
    $r = app(ReservationService::class)->reserve($departure, ['party_size' => 1]);
    $r->update(['expires_at' => now()->subHour()]);

    $this->artisan('reservations:expire')
        ->expectsOutputToContain('Released 1 stale hold(s).')
        ->assertSuccessful();

    expect($r->fresh()->status)->toBe('cancelled');
});
