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

it('lists every reserved word as unusable', function () {
    // the route file registers these at the root, so they can never be handles
    expect(Handles::RESERVED)->toContain('host', 'trips', 'login', 'settings', 't', 'livewire');
});
