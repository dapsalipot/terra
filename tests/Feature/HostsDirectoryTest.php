<?php

use App\Livewire\Hosts\Index;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Livewire\Livewire;

function directoryHost(string $name, string $handle, array $profile = [], ?string $category = null): User
{
    $host = User::factory()->create(['role' => 'host', 'name' => $name, 'handle' => $handle]);
    HostProfile::factory()->for($host)->create(array_merge(['currency' => 'PHP'], $profile));

    if ($category) {
        $place = Place::factory()->create(['category' => $category]);
        Trip::factory()->published()->create(['host_id' => $host->id, 'place_id' => $place->id]);
    }

    return $host;
}

it('lists hosts who have a handle', function () {
    directoryHost('Ridgeline', 'ridgeline');
    User::factory()->create(['role' => 'host', 'handle' => null]);   // no page to link to
    User::factory()->create(['role' => 'joiner', 'handle' => 'nope']);

    Livewire::test(Index::class)
        ->assertSee('Ridgeline')
        ->assertDontSee('nope');
});

it('keeps a host who has not filled in a profile yet', function () {
    // The sort reads host_profiles, so this is joined — an inner join would delete them.
    User::factory()->create(['role' => 'host', 'name' => 'Bare Bones', 'handle' => 'bare']);

    Livewire::test(Index::class)->assertSee('Bare Bones');
});

it('searches name, handle, tagline, location and bio', function () {
    directoryHost('Ridgeline', 'ridgeline', ['tagline' => 'Cordillera specialists']);
    directoryHost('Sunchaser', 'sunchaser', ['location' => 'Siargao', 'bio' => 'Reef breaks only']);

    $cases = [
        'ridge' => 'Ridgeline',        // name
        'sunchaser' => 'Sunchaser',    // handle
        'Cordillera' => 'Ridgeline',   // tagline
        'Siargao' => 'Sunchaser',      // location
        'Reef' => 'Sunchaser',         // bio
    ];

    foreach ($cases as $term => $expected) {
        $other = $expected === 'Ridgeline' ? 'Sunchaser' : 'Ridgeline';

        Livewire::test(Index::class)->set('search', $term)
            ->assertSee($expected)
            ->assertDontSee($other);
    }
});

it('filters to hosts who actually run that kind of trip', function () {
    directoryHost('Ridgeline', 'ridgeline', [], 'hike');
    directoryHost('Sunchaser', 'sunchaser', [], 'surf');

    Livewire::test(Index::class)->call('setCategory', 'hike')
        ->assertSee('Ridgeline')
        ->assertDontSee('Sunchaser');
});

it('clears the category when the active chip is tapped again', function () {
    // A filter with no way off is a trap — there is no other "show everyone" control.
    Livewire::test(Index::class)
        ->call('setCategory', 'hike')->assertSet('category', 'hike')
        ->call('setCategory', 'hike')->assertSet('category', '');
});

it('ignores a category that is not in the catalog', function () {
    Livewire::test(Index::class)->call('setCategory', 'volcano')->assertSet('category', '');
});

it('sorts unrated hosts last rather than treating them as zero or as top', function () {
    // MySQL and SQLite disagree on where NULL sorts, so this is pinned explicitly.
    directoryHost('Rated Low', 'low', ['rating' => 3.0]);
    directoryHost('Unrated', 'unrated', ['rating' => null]);
    directoryHost('Rated High', 'high', ['rating' => 4.8]);

    $order = Livewire::test(Index::class)->set('sort', 'rating')
        ->viewData('hosts')->pluck('name')->all();

    expect($order)->toBe(['Rated High', 'Rated Low', 'Unrated']);
});

it('sorts by published trip count', function () {
    $busy = directoryHost('Busy', 'busy', [], 'hike');
    Trip::factory()->published()->create(['host_id' => $busy->id]);
    directoryHost('Quiet', 'quiet', [], 'surf');

    $order = Livewire::test(Index::class)->set('sort', 'trips')
        ->viewData('hosts')->pluck('name')->all();

    expect($order[0])->toBe('Busy');
});

it('falls back to featured on an unknown sort', function () {
    Livewire::test(Index::class)->call('setSort', 'whatever')->assertSet('sort', 'featured');
});

it("shows each host's next open departure, not a past or a cancelled one", function () {
    $host = directoryHost('Ridgeline', 'ridgeline', [], 'hike');
    $trip = $host->trips()->first();

    $soonest = TripDeparture::factory()->for($trip)->create(['status' => 'open', 'starts_at' => now()->addWeek()]);
    TripDeparture::factory()->for($trip)->create(['status' => 'open', 'starts_at' => now()->addWeeks(3)]);
    TripDeparture::factory()->for($trip)->create(['status' => 'open', 'starts_at' => now()->subWeek()]);
    TripDeparture::factory()->for($trip)->create(['status' => 'cancelled', 'starts_at' => now()->addDays(2)]);

    $next = Livewire::test(Index::class)->viewData('hostExtras')['next'];

    expect($next[$host->id]->id)->toBe($soonest->id);
});

it('shows what each host runs, deduped', function () {
    $host = directoryHost('Ridgeline', 'ridgeline', [], 'hike');
    $second = Place::factory()->create(['category' => 'hike']);
    Trip::factory()->published()->create(['host_id' => $host->id, 'place_id' => $second->id]);

    $kinds = Livewire::test(Index::class)->viewData('categoriesByHost');

    expect($kinds[$host->id])->toHaveCount(1)
        ->and($kinds[$host->id][0]['label'])->toBe('Hike');
});

it('offers a way out when a filter empties the page', function () {
    directoryHost('Ridgeline', 'ridgeline', [], 'hike');

    Livewire::test(Index::class)->set('search', 'nobody here')
        ->assertSee('No hosts match that')
        ->assertSeeHtml('dusk="clear-filters"')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSee('Ridgeline');
});

it('tells an empty directory apart from an over-filtered one', function () {
    Livewire::test(Index::class)
        ->assertSee('No hosts yet')
        ->assertDontSee('No hosts match that');
});
