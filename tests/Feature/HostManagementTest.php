<?php

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Livewire\Livewire;

function makeHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridge']);
    HostProfile::factory()->for($host)->create();

    return $host;
}

it('blocks joiners from the host dashboard', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);
    $this->actingAs($joiner)->get('/host')->assertForbidden();
});

it('lets a host see their dashboard', function () {
    $this->actingAs(makeHost())->get('/host')->assertOk()->assertSee('Host dashboard');
});

it('publishes a trip with departures through the wizard', function () {
    $host = makeHost();
    $place = Place::factory()->create(['name' => 'Mount Rinjani']);

    Livewire::actingAs($host)
        ->test(\App\Livewire\Host\Trips\Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', 'Mount Rinjani Traverse')
        ->set('price', 180)
        ->call('next')->call('next')
        ->set('departures', [
            ['starts_at' => now()->addWeeks(2)->toDateString(), 'ends_at' => now()->addWeeks(2)->addDays(2)->toDateString(), 'capacity' => 14],
            ['starts_at' => now()->addWeeks(5)->toDateString(), 'ends_at' => '', 'capacity' => 12],
        ])
        ->call('publish')
        ->assertRedirect();

    // two dates now publish two trips — one schedule each, sharing a group
    $trips = Trip::orderBy('id')->get();

    expect($trips)->toHaveCount(2)
        ->and($trips->pluck('title')->unique()->all())->toBe(['Mount Rinjani Traverse'])
        ->and($trips->pluck('status')->unique()->all())->toBe(['published'])
        ->and($trips->pluck('host_id')->unique()->all())->toBe([$host->id])
        ->and($trips->pluck('trip_group_id')->unique())->toHaveCount(1)
        ->and($trips->pluck('slug')->unique())->toHaveCount(2)
        ->and($trips[0]->departures)->toHaveCount(1)
        ->and($trips[1]->departures)->toHaveCount(1)
        ->and($trips[0]->departures->first()->capacity)->toBe(14)
        ->and($trips[1]->departures->first()->capacity)->toBe(12);
});

it('requires a destination before advancing, and a title one step later', function () {
    $host = makeHost();
    $place = \App\Models\Place::factory()->create();

    // step 1 is where you're going, so only the destination is required here
    Livewire::actingAs($host)
        ->test(\App\Livewire\Host\Trips\Create::class)
        ->call('next')
        ->assertHasErrors(['destinations'])
        ->assertSet('step', 1);

    // the title belongs to step 2, with the rest of the trip's presentation
    Livewire::actingAs($host)
        ->test(\App\Livewire\Host\Trips\Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', '')
        ->call('next')
        ->assertHasErrors(['title'])
        ->assertSet('step', 2);
});

it('adds an offline participant that consumes capacity', function () {
    $host = makeHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);
    $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10]);

    Livewire::actingAs($host)
        ->test(\App\Livewire\Host\Roster::class, ['departure' => $departure])
        ->set('guest_name', 'Ramil Cruz')
        ->set('guest_party', 2)
        ->call('addOffline');

    $r = $departure->reservations()->first();
    expect($r->source)->toBe('manual')
        ->and($r->status)->toBe('confirmed')
        ->and($r->guest_name)->toBe('Ramil Cruz')
        ->and($departure->fresh()->heldCount())->toBe(2);
});

it('another host cannot open my roster', function () {
    $mine = Trip::factory()->published()->create(['host_id' => makeHost()->id]);
    $departure = TripDeparture::factory()->for($mine)->create();
    $other = User::factory()->create(['role' => 'host']);

    Livewire::actingAs($other)
        ->test(\App\Livewire\Host\Roster::class, ['departure' => $departure])
        ->assertForbidden();
});

it('serves a PNG QR code for a published trip', function () {
    $trip = Trip::factory()->published()->create(['host_id' => makeHost()->id]);

    $this->actingAs($trip->host)
        ->get(route('host.trips.qr', $trip))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});
