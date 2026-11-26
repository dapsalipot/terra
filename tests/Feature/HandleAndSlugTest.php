<?php

use App\Livewire\Host\Trips\Create;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Support\Handles;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Livewire\Volt\Volt;

/** Pass a handle, or `null` to create a host that has none yet. */
function handleHost(?string $handle = 'auto'): User
{
    $host = User::factory()->create([
        'role' => 'host',
        'handle' => $handle === 'auto' ? 'host'.Str::random(8) : $handle,
    ]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

it('opens a host page at the root of the site', function () {
    $host = handleHost('trailco');

    test()->get('/trailco')->assertOk()->assertSee($host->name);
});

it('keeps the @handle form working', function () {
    $host = handleHost('trailco');

    test()->get('/@trailco')->assertOk()->assertSee($host->name);
});

it('does not let a handle shadow a real route', function () {
    // these must reach their own pages, not a "host not found"
    test()->get('/trips')->assertOk();
    test()->get('/hosts')->assertOk();
    test()->get('/login')->assertOk();
});

it('404s an unknown handle rather than erroring', function () {
    test()->get('/nobody-by-that-name')->assertNotFound();
});

it('lets a host set their handle from account settings', function () {
    $host = handleHost(null);

    Volt::actingAs($host)->test('profile.update-profile-information-form')
        ->set('handle', 'ridgeline')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($host->fresh()->handle)->toBe('ridgeline');
    test()->get('/ridgeline')->assertOk();
});

it('refuses a reserved handle', function () {
    $host = handleHost(null);

    foreach (['host', 'trips', 'login', 'settings'] as $reserved) {
        Volt::actingAs($host)->test('profile.update-profile-information-form')
            ->set('handle', $reserved)
            ->call('updateProfileInformation')
            ->assertHasErrors(['handle']);
    }

    expect($host->fresh()->handle)->toBeNull();
});

it('refuses a handle someone already has', function () {
    handleHost('taken');
    $host = handleHost(null);

    Volt::actingAs($host)->test('profile.update-profile-information-form')
        ->set('handle', 'taken')
        ->call('updateProfileInformation')
        ->assertHasErrors(['handle']);
});

it('refuses handles with spaces, capitals or symbols', function () {
    $host = handleHost(null);

    foreach (['Trail Co', 'TrailCo', 'trail.co', 'tr', 'trail@co'] as $bad) {
        Volt::actingAs($host)->test('profile.update-profile-information-form')
            ->set('handle', $bad)
            ->call('updateProfileInformation')
            ->assertHasErrors(['handle']);
    }
});

it('lets a host clear their handle to go unlisted', function () {
    $host = handleHost('goingaway');

    Volt::actingAs($host)->test('profile.update-profile-information-form')
        ->set('handle', '')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    // null, not '' — two blank handles would otherwise collide on the unique index
    expect($host->fresh()->handle)->toBeNull();
});

it('names the date in a trip slug instead of a bare number', function () {
    $host = handleHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', 'Mount Pulag 2 Day Hike')
        ->set('price', '2000')
        ->call('next')->call('next')
        ->set('departures', [
            ['starts_at' => '2026-08-02', 'ends_at' => '', 'capacity' => 10],
            ['starts_at' => '2026-11-29', 'ends_at' => '', 'capacity' => 10],
        ])
        ->call('next')
        ->call('publish');

    $slugs = Trip::where('title', 'Mount Pulag 2 Day Hike')->pluck('slug')->sort()->values();

    expect($slugs->all())->toBe([
        'mount-pulag-2-day-hike-aug-2026',
        'mount-pulag-2-day-hike-nov-2026',
    ]);
});

it('still resolves a trip by its slug', function () {
    $host = handleHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')->set('title', 'Findable Trip')->set('price', '900')
        ->call('next')->call('next')
        ->set('departures', [['starts_at' => '2026-08-02', 'ends_at' => '', 'capacity' => 10]])
        ->call('next')->call('publish');

    $trip = Trip::where('title', 'Findable Trip')->sole();

    expect($trip->slug)->toBe('findable-trip-aug-2026');
    test()->get('/t/'.$trip->slug)->assertOk()->assertSee('Findable Trip');
});

it('falls back to a numbered slug only when two schedules share a month', function () {
    $host = handleHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')->set('title', 'Twice In August')->set('price', '900')
        ->call('next')->call('next')
        ->set('departures', [
            ['starts_at' => '2026-08-02', 'ends_at' => '', 'capacity' => 10],
            ['starts_at' => '2026-08-20', 'ends_at' => '', 'capacity' => 10],
        ])
        ->call('next')->call('publish');

    expect(Trip::where('title', 'Twice In August')->pluck('slug')->sort()->values()->all())
        ->toBe(['twice-in-august-aug-2026', 'twice-in-august-aug-2026-2']);
});

it('keeps a published trip’s page and join links working after its title and date are edited', function () {
    $this->travelTo('2027-01-10 09:00:00');
    $host = handleHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')->set('title', 'Sea Of Clouds')->set('price', '900')
        ->call('next')->call('next')
        ->set('departures', [['starts_at' => '2027-03-14', 'ends_at' => '', 'capacity' => 10]])
        ->call('next')->call('publish');

    $trip = Trip::where('title', 'Sea Of Clouds')->sole();
    $shared = $trip->slug;   // what is in the chat message and on the printed QR

    // The wizard locks the date on an edit, so it is moved underneath: a new month must not move the address either.
    $trip->departure->update(['starts_at' => '2027-05-09']);

    Livewire::actingAs($host)->test(Create::class, ['trip' => $trip->fresh()])
        ->set('title', 'Sea Of Clouds via Ambangeg')
        ->call('publish')
        ->assertHasNoErrors();

    test()->actingAsGuest();   // a shared link is opened by someone else, and a host is bounced off the join page

    test()->get('/t/'.$shared)->assertOk()->assertSee('Sea Of Clouds via Ambangeg');
    test()->get('/t/'.$shared.'/join')->assertOk()->assertSee('Sea Of Clouds via Ambangeg');
});

it('rewrites the slug on a wizard save only for a trip that was never published', function (string $status, string $slug) {
    $host = handleHost();
    $place = Place::factory()->create();
    $trip = Trip::factory()->for($host, 'host')->create(['title' => 'Ridge Walk', 'status' => $status]);

    Livewire::actingAs($host)->test(Create::class, ['trip' => $trip])
        ->call('addDestination', $place->id)
        ->set('departures', [['starts_at' => '2027-03-14', 'ends_at' => '', 'capacity' => 10]])
        ->call('publish')
        ->assertHasNoErrors();

    expect($trip->fresh()->slug)->toBe($slug);
})->with([
    'a draft gets the descriptive form at first publish' => ['draft', 'ridge-walk-mar-2027'],
    // archived is an admin takedown, so the address was already out in the world
    'a taken-down trip keeps the one already shared' => ['archived', 'ridge-walk'],
]);

it('lists every reserved word as unusable', function () {
    // the route file registers these at the root, so they can never be handles
    expect(Handles::RESERVED)->toContain('host', 'trips', 'login', 'settings', 't', 'livewire');
});
