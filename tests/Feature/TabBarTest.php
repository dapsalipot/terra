<?php

use App\Models\HostProfile;
use App\Models\Trip;
use App\Models\User;
use Livewire\Livewire;
use Livewire\Volt\Volt;

function tabbarHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline']);
    HostProfile::factory()->for($host)->create();

    return $host;
}

it('gives a guest tabs that end in a way to log in', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-tabbar="site"', false)
        ->assertSee('dusk="tab-login"', false)
        ->assertDontSee('dusk="tab-trips"', false);
});

it('gives a joiner their trips and their account instead', function () {
    $this->actingAs(User::factory()->create(['role' => 'joiner']))
        ->get('/')
        ->assertSee('dusk="tab-trips"', false)
        ->assertSee('dusk="tab-account"', false)
        ->assertDontSee('dusk="tab-login"', false);
});

it('sends a host back to the console from the public site', function () {
    $this->actingAs(tabbarHost())
        ->get('/')
        ->assertSee('dusk="tab-dashboard"', false)
        ->assertDontSee('dusk="tab-trips"', false);
});

it('marks the tab for the page you are on', function () {
    // aria-current is what the pill and the bold label hang off, so it has to be on exactly the right tab.
    expect($this->get('/trips')->getContent())
        ->toMatch('/aria-current="page"\s+dusk="tab-explore"/')
        ->not->toMatch('/aria-current="page"\s+dusk="tab-home"/');
});

it('keeps the bar off pages that carry their own bottom bar', function () {
    $host = tabbarHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);

    $this->get(route('trips.show', $trip))
        ->assertOk()
        ->assertDontSee('data-tabbar', false);
});

it('gives the host console its own tabs, with the primary action raised', function () {
    $this->actingAs(tabbarHost())
        ->get('/host')
        ->assertSee('data-tabbar="host"', false)
        ->assertSee('dusk="tab-new"', false)
        ->assertSee('is-action', false)
        ->assertSee('dusk="more-sheet"', false);
});

it('invites organisers to register from every public page', function () {
    $this->get('/trips')->assertSee(route('register', ['role' => 'host']), false);
});

it('preselects hosting when the registration link asks for it', function () {
    Livewire::withQueryParams(['role' => 'host']);

    Volt::test('pages.auth.register')->assertSet('role', 'host');
});

it('ignores any other role in the link', function () {
    Livewire::withQueryParams(['role' => 'admin']);

    Volt::test('pages.auth.register')->assertSet('role', 'joiner');
});
