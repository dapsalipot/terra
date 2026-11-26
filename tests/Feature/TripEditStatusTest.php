<?php

use App\Models\Place;
use App\Models\Trip;
use App\Services\DepartureCompletionService;
use App\Services\ModerationService;
use App\Services\ReservationService;

/*
 * The wizard saves a first publish and every later edit through the same method. Neither status is the
 * form's to decide after that first time: moderation takes a trip down, and a departure is called off
 * or completed elsewhere.
 */

it('publishes the trip and opens its departure the first time', function () {
    $trip = publishViaWizard()->sole();

    expect($trip->status)->toBe('published')
        ->and($trip->departure->status)->toBe('open');
});

it('publishes a draft saved through the edit form, with an open departure', function () {
    $trip = Trip::factory()->create(['status' => 'draft']);

    saveTripEdit($trip, [
        'destinations' => [['place_id' => Place::factory()->create()->id, 'days' => ['']]],
        'departures' => [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 10]],
    ]);

    expect($trip->fresh()->status)->toBe('published')
        ->and($trip->fresh()->departure->status)->toBe('open');
});

it('leaves a taken-down trip down when its host saves an edit', function () {
    $trip = publishViaWizard()->sole();
    app(ModerationService::class)->unpublish($trip);

    $save = saveTripEdit($trip, ['title' => 'Ridge Walk, revised']);

    expect($trip->fresh()->status)->toBe('archived')
        ->and($trip->fresh()->title)->toBe('Ridge Walk, revised');   // the edit itself still saves

    // the public page is a 404 while the trip is down, so the save has to land on the host's own list
    $save->assertRedirect(route('host.trips.index'));
});

it('leaves a departure that was called off or has run as it was', function (string $status) {
    $trip = publishViaWizard()->sole();

    $status === 'cancelled'
        ? app(ReservationService::class)->cancelDeparture($trip->departure, 'Typhoon')
        : app(DepartureCompletionService::class)->complete($trip->departure);

    // a new capacity too: free seats must not read as "open for booking" here
    saveTripEdit($trip, ['departures.0.capacity' => 20]);

    expect($trip->fresh()->departure->status)->toBe($status)
        ->and($trip->fresh()->departure->capacity)->toBe(20);
})->with(['cancelled', 'completed']);

it('sets a departure open or full from the seats left after an edit', function (int $capacity, int $newCapacity, string $status) {
    $trip = publishViaWizard(['departures.0.capacity' => $capacity])->sole();
    app(ReservationService::class)->reserve($trip->departure, ['guest_name' => 'Ana Cruz', 'party_size' => 4]);

    saveTripEdit($trip, ['departures.0.capacity' => $newCapacity]);

    expect($trip->fresh()->departure->status)->toBe($status);
})->with([
    'cut to exactly the seats held' => [9, 4, 'full'],
    'cut below the seats held' => [9, 3, 'full'],
    'cut with a seat still free' => [9, 5, 'open'],
    'a full one given more room' => [4, 6, 'open'],
    'a full one saved unchanged' => [4, 4, 'full'],
]);
