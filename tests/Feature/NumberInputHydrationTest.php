<?php

/*
 * Guards one bug class across every number input in the app.
 *
 * A `public int` property cannot hold the empty string Livewire sends when a number field is
 * cleared. PHP leaves the typed property uninitialized and routes the next read to __get(),
 * where Livewire throws PropertyNotFoundException — a 500 for the ordinary act of backspacing
 * over a number. Reservations\Register hit exactly this in production.
 *
 * The other components below survive only because nothing reads the property while it is in that
 * state. These tests pin that: adding an updated*() hook that touches the property, or any early
 * read, would break them here rather than for a user.
 */

use App\Livewire\Host\Roster;
use App\Livewire\Trips\Show;
use App\Models\HostProfile;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Livewire\Livewire;

it('the trip page survives its guests box being cleared', function () {
    $trip = Trip::factory()->create(['status' => 'published']);
    TripDeparture::factory()->create(['trip_id' => $trip->id, 'status' => 'open']);

    Livewire::test(Show::class, ['trip' => $trip])
        ->set('party', '')
        ->assertOk();
});

it('the roster survives its party box being cleared', function () {
    $host = User::factory()->create(['role' => 'host']);
    HostProfile::factory()->for($host)->create();
    $trip = Trip::factory()->create(['host_id' => $host->id]);
    $departure = TripDeparture::factory()->create(['trip_id' => $trip->id, 'capacity' => 10]);

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $departure])
        ->set('guest_name', 'Walk In')
        ->set('guest_party', '')
        ->call('addOffline')
        ->assertOk();
});
