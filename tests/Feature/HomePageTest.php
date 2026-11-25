<?php

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Str;

function homeHost(string $handle = 'ridgeline', string $name = 'Ridgeline Club'): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => $handle, 'name' => $name]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

function homeTrips(User $host, string $category, int $count): void
{
    $place = Place::factory()->create(['category' => $category]);

    Trip::factory()->published()->count($count)->create(['host_id' => $host->id, 'place_id' => $place->id]);
}

it('opens on a search and the trips that are leaving soon', function () {
    Trip::factory()->published()->create(['host_id' => homeHost()->id, 'title' => 'Mount Rinjani Traverse']);

    $this->get('/')->assertOk()
        ->assertSeeText('Find your next group trip')
        ->assertSee('role="search"', false)
        ->assertSeeText('Departing soon')
        ->assertSeeText('Mount Rinjani Traverse');
});

it('runs the trail pattern behind the whole page', function () {
    // On the root element, so it spans the page from the nav to the footer rather than one band of it.
    $this->get('/')->assertOk()->assertSee('class="trail-bg"', false);
});

it('searches the catalogue rather than a page of its own', function () {
    $this->get('/')->assertOk()
        ->assertSee('action="'.route('trips.index').'"', false)
        ->assertSee('name="search"', false);
});

it('lists each kind of trip that exists as a way into the catalogue', function () {
    $host = homeHost();
    homeTrips($host, 'hike', 1);
    homeTrips($host, 'island', 1);

    // The footer lists every kind there is; the strip under the search lists only the ones with trips.
    $strip = Str::betweenFirst($this->get('/')->assertOk()->getContent(), 'aria-label="Trip types"', '</nav>');

    expect($strip)
        ->toContain('category=hike')
        ->toContain('category=island')
        ->not->toContain('category=surf');
});

it('gives a kind of trip its own row only when it has enough trips to fill one', function () {
    $host = homeHost();
    homeTrips($host, 'hike', 4);
    homeTrips($host, 'island', 3);

    // Each row is a labelled region, which is a firmer thing to look for than a word that the (random)
    // trip titles or the footer might happen to contain too.
    $this->get('/')->assertOk()
        ->assertSee('aria-label="Hikes"', false)
        ->assertDontSee('aria-label="Islands"', false);
});

it('puts the hosts who have trips to join in front of the ones who do not', function () {
    $busy = homeHost('ridgeline', 'Ridgeline Club');
    homeTrips($busy, 'hike', 1);
    homeHost('quietco', 'Quiet Collective');

    $this->get('/')->assertOk()
        ->assertSeeText('Top hosts')
        ->assertSeeText('Ridgeline Club')
        ->assertDontSeeText('Quiet Collective');
});

it('tells organisers they can host for free, shows how, and sends them to host registration', function () {
    $this->get('/')->assertOk()
        ->assertSeeText('Host your Trips on Terra')
        ->assertSeeText('Terra takes no cut')
        ->assertSeeText('Create your host account')
        ->assertSeeText('Post your trip')
        ->assertSeeText('Welcome your guests')
        ->assertSee(route('register', ['role' => 'host']), false);
});

it('keeps the hosting pitch away from people who already chose a side', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role, 'handle' => $role === 'host' ? 'ridgeline' : null]))
        ->get('/')
        ->assertOk()
        ->assertDontSeeText('Host your Trips on Terra')
        ->assertDontSee('id="host"', false);
})->with(['joiner', 'host']);

it('has no em-dashes anywhere in the page copy', function () {
    // The page is meant to read as plain sentences; a dash standing in for a comma is the habit to keep out.
    homeTrips(homeHost(), 'hike', 2);

    expect(strip_tags($this->get('/')->getContent()))->not->toContain('—');
});
