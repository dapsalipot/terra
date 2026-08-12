<?php

use App\Livewire\Home;
use App\Livewire\Host\Trips\Create;
use App\Livewire\Host\Trips\Index;
use App\Livewire\Hosts\Show;
use App\Models\HostMeetupPreset;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Livewire\Livewire;

function scheduleHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'host'.Str::random(8)]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

/** Publish with N dates and get the resulting trips back in date order. */
function publishSchedules(User $host, Place $place, array $dates, string $title = 'Pulag Sunrise')
{
    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', $title)
        ->set('price', '2000')
        ->call('next')->call('next')
        ->set('departures', collect($dates)->map(fn ($d) => [
            'starts_at' => $d, 'ends_at' => '', 'capacity' => 10,
        ])->all())
        ->call('next')
        ->call('publish');

    return Trip::where('title', $title)->orderBy('id')->get();
}

it('publishes one trip per date, sharing a group', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    $trips = publishSchedules($host, $place, [
        now()->addWeek()->toDateString(),
        now()->addMonth()->toDateString(),
        now()->addMonths(2)->toDateString(),
    ]);

    expect($trips)->toHaveCount(3)
        ->and($trips->pluck('trip_group_id')->unique())->toHaveCount(1)
        ->and($trips->pluck('slug')->unique())->toHaveCount(3);

    // and each one owns exactly one schedule
    $trips->each(fn ($t) => expect($t->departures)->toHaveCount(1));
});

it('gives every schedule its own inclusions and pickups to customise', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    $trips = publishSchedules($host, $place, [now()->addWeek()->toDateString(), now()->addMonth()->toDateString()]);

    // the content is duplicated, not shared — editing one cannot touch the other
    expect($trips[0]->inclusionItems->pluck('id')->intersect($trips[1]->inclusionItems->pluck('id')))->toBeEmpty()
        ->and($trips[0]->destinations->pluck('id')->intersect($trips[1]->destinations->pluck('id')))->toBeEmpty();
});

it('edits one schedule without disturbing its siblings', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();
    $other = Place::factory()->create(['name' => 'Mount Ulap']);

    $trips = publishSchedules($host, $place, [now()->addWeek()->toDateString(), now()->addMonth()->toDateString()]);
    [$first, $second] = [$trips[0], $trips[1]];

    Livewire::actingAs($host)->test(Create::class, ['trip' => $first])
        ->assertSet('editingId', $first->id)
        ->assertSet('title', 'Pulag Sunrise')          // no "(copy)" when editing
        ->call('addDestination', $other->id)
        ->call('next')
        ->set('title', 'Pulag Sunrise · VIP')
        ->set('price', '4500')
        ->call('next')->call('next')->call('next')
        ->call('publish');

    expect($first->fresh()->title)->toBe('Pulag Sunrise · VIP')
        ->and((float) $first->fresh()->price)->toBe(4500.0)
        ->and($first->fresh()->destinations)->toHaveCount(2)
        // the sibling is untouched
        ->and($second->fresh()->title)->toBe('Pulag Sunrise')
        ->and((float) $second->fresh()->price)->toBe(2000.0)
        ->and($second->fresh()->destinations)->toHaveCount(1)
        // still one offering
        ->and($first->fresh()->trip_group_id)->toBe($second->fresh()->trip_group_id);
});

it('keeps reservations alive across an edit', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();
    $joiner = User::factory()->create();

    $trip = publishSchedules($host, $place, [now()->addWeek()->toDateString()])->sole();
    $departure = $trip->departures->sole();

    $reservation = Reservation::create([
        'trip_departure_id' => $departure->id,
        'joiner_id' => $joiner->id,
        'party_size' => 2,
        'status' => 'confirmed',
    ]);

    Livewire::actingAs($host)->test(Create::class, ['trip' => $trip])
        ->call('next')->set('price', '3000')
        ->call('next')->call('next')->call('next')
        ->call('publish');

    // the departure row must survive: deleting and recreating it would orphan the booking
    expect($reservation->fresh())->not->toBeNull()
        ->and($reservation->fresh()->trip_departure_id)->toBe($departure->id)
        ->and($departure->fresh())->not->toBeNull()
        ->and((float) $trip->fresh()->price)->toBe(3000.0);
});

it('refuses to edit another host’s schedule', function () {
    $mine = scheduleHost();
    $theirs = scheduleHost();
    $place = Place::factory()->create();

    $notMine = publishSchedules($theirs, $place, [now()->addWeek()->toDateString()], 'Not Mine')->sole();

    test()->actingAs($mine)
        ->get(route('host.trips.edit', $notMine))
        ->assertForbidden();
});

it('lists a host’s schedules grouped under one offering', function () {
    $host = scheduleHost();
    $place = Place::factory()->create(['name' => 'Mount Pulag']);

    publishSchedules($host, $place, [now()->addWeek()->toDateString(), now()->addMonth()->toDateString()]);

    Livewire::actingAs($host)->test(Index::class)
        ->assertSee('Mount Pulag')
        ->assertSee('2 schedules')
        ->assertSee('Pulag Sunrise');
});

it('separates upcoming schedules from past ones', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    $trips = publishSchedules($host, $place, [now()->addWeek()->toDateString(), now()->addMonth()->toDateString()]);
    $trips[0]->departures->sole()->update(['starts_at' => now()->subMonth()]);

    Livewire::actingAs($host)->test(Index::class)
        ->assertSet('filter', 'upcoming')
        ->assertSee('1 schedule')
        ->set('filter', 'past')
        ->assertSee('1 schedule')
        ->set('filter', 'all')
        ->assertSee('2 schedules');
});

it('shows a host only their own trips', function () {
    $mine = scheduleHost();
    $theirs = scheduleHost();
    $place = Place::factory()->create();

    publishSchedules($mine, $place, [now()->addWeek()->toDateString()], 'Mine');
    publishSchedules($theirs, $place, [now()->addWeek()->toDateString()], 'Theirs');

    Livewire::actingAs($mine)->test(Index::class)
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});

it('ignores a forged departure date on edit', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    $trip = publishSchedules($host, $place, [now()->addWeek()->toDateString()])->sole();
    $original = $trip->departures->sole()->starts_at->toDateString();

    Livewire::actingAs($host)->test(Create::class, ['trip' => $trip])
        ->call('goToStep', 4)
        ->assertSee('This date is fixed')
        ->assertDontSee('Add a departure')
        // the input is not rendered, so this can only come from a tampered payload
        ->set('departures.0.starts_at', now()->addYear()->toDateString())
        ->call('publish');

    expect($trip->fresh()->departures->sole()->starts_at->toDateString())->toBe($original);
});

it('opens a card that already holds content', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    $trip = publishSchedules($host, $place, [now()->addWeek()->toDateString()])->sole();
    $trip->meetups()->create(['kind' => 'pickup', 'label' => 'Cubao terminal', 'time' => '22:00', 'position' => 0]);

    $html = Livewire::actingAs($host)->test(Create::class, ['trip' => $trip->fresh()])
        ->call('goToStep', 4)
        ->html();

    // x-data="{ open: true }" is what makes the saved pickup visible without a click
    expect($html)->toContain('Cubao terminal');
    expect(substr_count($html, 'x-data="{ open: true }"'))->toBeGreaterThan(0);
});

it('adds a spot blank or from the library', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    $preset = HostMeetupPreset::create([
        'host_id' => $host->id, 'label' => 'Cubao terminal',
        'address' => 'EDSA', 'lat' => 14.62, 'lng' => 121.05, 'note' => 'Blue van',
    ]);

    $page = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('goToStep', 4);

    // blank
    $page->call('addSpot', 'pickup')
        ->assertCount('meetups', 2)
        ->assertSet('meetups.1.label', '');

    // from the library, into the row it just made
    $page->call('addSpot', 'pickup', $preset->id)
        ->assertCount('meetups', 3)
        ->assertSet('meetups.2.label', 'Cubao terminal')
        ->assertSet('meetups.2.address', 'EDSA')
        ->assertSet('meetups.2.lat', '14.62');

    // and drop-offs use the same library
    $page->call('addSpot', 'dropoff', $preset->id)
        ->assertCount('dropoffs', 2)
        ->assertSet('dropoffs.1.label', 'Cubao terminal');
});

it('will not seed a spot from another host’s library', function () {
    $mine = scheduleHost();
    $theirs = scheduleHost();
    $place = Place::factory()->create();

    $notMine = HostMeetupPreset::create([
        'host_id' => $theirs->id, 'label' => 'Their spot',
    ]);

    $blocked = false;
    try {
        Livewire::actingAs($mine)->test(Create::class)
            ->call('addDestination', $place->id)
            ->call('addSpot', 'pickup', $notMine->id);
    } catch (ModelNotFoundException) {
        $blocked = true;
    }

    expect($blocked)->toBeTrue();
});

it('shows one card per offering, not one per date', function () {
    $host = scheduleHost();
    $place = Place::factory()->create(['name' => 'Mount Pulag']);

    publishSchedules($host, $place, [
        now()->addWeek()->toDateString(),
        now()->addMonth()->toDateString(),
        now()->addMonths(2)->toDateString(),
    ], 'Mount Pulag 2 Day Hike');

    foreach ([Home::class, App\Livewire\Trips\Index::class] as $component) {
        $html = Livewire::test($component)->html();

        // a card prints the title in its img alt as well as its heading, so count headings
        expect(substr_count($html, '>Mount Pulag 2 Day Hike<'))->toBe(1, $component.' repeated the offering');
    }
});

it('says how many other dates are behind the card', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    publishSchedules($host, $place, [
        now()->addWeek()->toDateString(),
        now()->addMonth()->toDateString(),
        now()->addMonths(2)->toDateString(),
    ], 'Thrice');

    Livewire::test(App\Livewire\Trips\Index::class)
        ->assertSee('Thrice')
        ->assertSee('+2 more dates');
});

it('leads with the soonest bookable date', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    $trips = publishSchedules($host, $place, [
        now()->addMonths(6)->toDateString(),
        now()->addWeek()->toDateString(),      // published second, but sooner
    ], 'Soonest');

    $lead = Trip::collapseSchedules(Trip::where('title', 'Soonest')->with('departures')->get())->sole();

    expect($lead->id)->toBe($trips[1]->id)
        ->and($lead->schedule_count)->toBe(2);
});

it('does not repeat an offering on the host’s public page', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    publishSchedules($host, $place, [now()->addWeek()->toDateString(), now()->addMonth()->toDateString()], 'Once Only');

    $html = Livewire::test(Show::class, ['user' => $host])->html();

    expect(substr_count($html, '>Once Only<'))->toBe(1);
});

it('offers each past offering once when starting a new trip', function () {
    $host = scheduleHost();
    $place = Place::factory()->create();

    publishSchedules($host, $place, [
        now()->addWeek()->toDateString(),
        now()->addMonth()->toDateString(),
        now()->addMonths(2)->toDateString(),
    ], 'Repeatable');

    TripDraft::query()->delete();

    $html = Livewire::actingAs($host)->test(Create::class)->html();

    // the chip row used to list the same trip once per schedule — count the chips
    expect(substr_count($html, 'dusk="reuse-'))->toBe(1)
        ->and($html)->toContain('Repeatable');
});
