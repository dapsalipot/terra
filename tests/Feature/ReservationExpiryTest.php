<?php

use App\Models\Reservation;
use App\Models\TripDeparture;
use App\Services\ReservationService;

it('expires unpaid holds past their expiry and frees the seat', function () {
    $svc = app(ReservationService::class);
    $departure = TripDeparture::factory()->create(['capacity' => 10]);

    $stale = $svc->reserve($departure, ['party_size' => 2]);
    $stale->update(['expires_at' => now()->subHour()]);           // overdue

    $fresh = $svc->reserve($departure, ['party_size' => 1]);       // still within its 24h

    $released = $svc->expireStaleHolds();

    expect($released)->toBe(1)
        ->and($stale->fresh()->status)->toBe('cancelled')
        ->and($fresh->fresh()->status)->toBe('pending')
        ->and($departure->fresh()->heldCount())->toBe(1);
});

it('never expires a confirmed reservation', function () {
    $svc = app(ReservationService::class);
    $r = Reservation::factory()->confirmed()->create(['expires_at' => now()->subDay()]);

    $svc->expireStaleHolds();

    expect($r->fresh()->status)->toBe('confirmed');
});
