<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Hosts\Index;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('pages the hosts directory instead of loading every host', function () {
    User::factory()->count(30)->create(['role' => 'host'])->each(function ($h) {
        $h->update(['handle' => 'h'.$h->id]);
        HostProfile::factory()->for($h)->create(['currency' => 'PHP']);
    });

    $page = Livewire::test(Index::class);
    $hosts = $page->viewData('hosts');

    expect($hosts->total())->toBe(30)
        ->and($hosts->count())->toBe(24)      // one page, not the lot
        ->and($hosts->hasPages())->toBeTrue();

    // and the header reports the real total rather than the page size
    $page->assertSee('30');
});

it('sends a narrowed search back to the first page', function () {
    User::factory()->count(30)->create(['role' => 'host'])->each(function ($h) {
        $h->update(['handle' => 'h'.$h->id]);
        HostProfile::factory()->for($h)->create(['currency' => 'PHP']);
    });

    Livewire::test(Index::class)
        ->call('setPage', 2)
        ->set('search', 'nothing matches this')
        ->assertSet('paginators.page', 1);
});

it('pages the trip catalogue after collapsing schedules, not before', function () {
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $place = Place::factory()->create();

    // 30 offerings, one of which runs on three dates — the collapsed count is what pages.
    Trip::factory()->count(29)->published()->create(['host_id' => $host->id, 'place_id' => $place->id]);
    $group = (string) Str::uuid();
    Trip::factory()->count(3)->published()->create([
        'host_id' => $host->id, 'place_id' => $place->id, 'trip_group_id' => $group, 'title' => 'Pulag hike',
    ]);

    $trips = Livewire::test(App\Livewire\Trips\Index::class)->viewData('trips');

    // 29 solo + 1 collapsed offering = 30, never 32
    expect($trips->total())->toBe(30)
        ->and($trips->count())->toBe(24);
});

it('pages each admin tab on its own', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline']);
    Trip::factory()->count(30)->published()->create(['host_id' => $host->id]);

    $page = Livewire::actingAs($admin)->test(Dashboard::class);

    expect($page->viewData('trips')->total())->toBe(30)
        ->and($page->viewData('trips')->count())->toBe(25)
        // an unselected tab still hands the view something a pager can be called on
        ->and($page->viewData('hosts')->total())->toBe(0);

    // switching tab starts at the top of the new list
    $page->call('setPage', 2)->call('setTab', 'hosts')->assertSet('paginators.page', 1);
});
