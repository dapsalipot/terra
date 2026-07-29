<?php

use App\Livewire\Host\Trips\Create;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDraft;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

function draftHost(): User
{
    // unique handle: several of these tests need two hosts at once
    $host = User::factory()->create(['role' => 'host', 'handle' => 'host'.Str::random(8)]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

/** Publish a trip for this host, so we have something to copy from. */
function publishFor(User $host, Place $place, array $hike = [], string $title = 'Pulag Sunrise'): Trip
{
    $test = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', $title);

    foreach ($hike as $key => $value) {
        $test->set("hikes.{$place->id}.$key", $value);
    }

    $test->call('next')->call('next')
        ->set('price', '2000')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 10]])
        ->call('next')
        ->call('publish');

    return Trip::where('title', $title)->sole();
}

it('saves what the host has typed without being asked', function () {
    $host = draftHost();
    $place = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Half Finished Trip');

    $draft = TripDraft::where('host_id', $host->id)->first();

    expect($draft)->not->toBeNull()
        ->and($draft->payload['title'])->toBe('Half Finished Trip')
        ->and($draft->payload['destinations'][0]['place_id'])->toBe($place->id);
});

it('gives the work back after the session drops', function () {
    $host = draftHost();
    $place = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Recovered Trip')
        ->set('price', '2500');

    // a brand-new component, as if the browser had been closed and reopened
    Livewire::actingAs($host)->test(Create::class)
        ->assertSet('title', 'Recovered Trip')
        ->assertSet('price', '2500')
        ->assertSet('draftRestored', true)
        ->assertSee('Picked up where you left off');
});

it('keeps one host out of another host’s draft', function () {
    $mine = draftHost();
    $theirs = draftHost();

    Livewire::actingAs($mine)->test(Create::class)->set('title', 'My Secret Trip');

    Livewire::actingAs($theirs)->test(Create::class)
        ->assertSet('title', '')
        ->assertSet('draftRestored', false);
});

it('clears the draft once the trip is published', function () {
    $host = draftHost();
    $place = Place::factory()->create(['category' => 'hike']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Finished Trip')
        ->call('next')->call('next')
        ->set('price', '2000')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 10]])
        ->call('next')
        ->call('publish');

    expect(TripDraft::where('host_id', $host->id)->exists())->toBeFalse();
});

it('throws the draft away when the host asks to start fresh', function () {
    $host = draftHost();

    Livewire::actingAs($host)->test(Create::class)->set('title', 'Abandoned')
        ->call('discardDraft');

    expect(TripDraft::where('host_id', $host->id)->exists())->toBeFalse();
});

it('starts a new trip from one the host has already run', function () {
    $host = draftHost();
    $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike', 'elevation_m' => null]);

    $original = publishFor($host, $pulag, ['masl' => '2922', 'difficulty' => 5, 'features' => ['mossy_forest']]);

    $original->meetups()->create(['label' => 'Cubao terminal', 'position' => 0]);

    $page = Livewire::actingAs($host)->test(Create::class, ['from' => $original->fresh()])
        ->assertSet('title', 'Pulag Sunrise (copy)')
        ->assertSet('destinations.0.place_id', $pulag->id)
        ->assertSet("hikes.{$pulag->id}.masl", '2922')
        ->assertSet("hikes.{$pulag->id}.features", ['mossy_forest'])
        ->assertSet('meetups.0.label', 'Cubao terminal')
        // dates are the one thing that must be new
        ->assertSet('departures.0.starts_at', '');

    expect(collect($page->get('inclusions'))->pluck('label'))
        ->toContain('Transport', 'Permits', 'Guide');
});

it('refuses to copy a trip belonging to another host', function () {
    $mine = draftHost();
    $theirs = draftHost();
    $place = Place::factory()->create(['category' => 'hike']);

    $notMine = publishFor($theirs, $place, [], 'Not Mine');

    // the real attack surface is the URL, so test that rather than the component
    test()->actingAs($mine)
        ->get(route('host.trips.duplicate', $notMine))
        ->assertForbidden();
});

it('offers past trips to reuse only before a destination is chosen', function () {
    $host = draftHost();
    $place = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);
    publishFor($host, $place);

    TripDraft::query()->delete();   // the publish above leaves none, but be explicit

    Livewire::actingAs($host)->test(Create::class)
        ->assertSee('Run one of these again?')
        ->assertSee('Pulag Sunrise')
        ->call('addDestination', $place->id)
        ->assertDontSee('Run one of these again?');
});

it('copies a trip’s pickups and drop-offs, times included', function () {
    $host = draftHost();
    $place = Place::factory()->create();

    $original = publishFor($host, $place, [], 'Round Trip');
    $original->meetups()->create(['kind' => 'pickup', 'label' => 'Cubao terminal', 'time' => '22:00', 'position' => 0]);
    $original->meetups()->create(['kind' => 'dropoff', 'label' => 'Cubao return', 'time' => '18:30', 'position' => 0]);

    $page = Livewire::actingAs($host)->test(Create::class, ['from' => $original->fresh()])
        ->assertSet('meetups.0.label', 'Cubao terminal')
        ->assertSet('meetups.0.time', '22:00')
        ->assertSet('dropoffs.0.label', 'Cubao return')
        ->assertSet('dropoffs.0.time', '18:30');

    // the crash was here: the summary read $m['time'] on a hand-built row that lacked it
    $page->call('goToStep', 4)->assertSet('step', 4)->assertSee('Cubao terminal');
});

it('survives a draft written before a field existed', function () {
    $host = draftHost();
    $place = Place::factory()->create();

    // a payload from an older build: no time, no lat/lng, no save_to_library
    TripDraft::create([
        'host_id' => $host->id,
        'step' => 4,
        'payload' => [
            'destinations' => [['place_id' => $place->id]],
            'title' => 'Legacy Draft',
            'meetups' => [['label' => 'Cubao terminal', 'address' => '', 'note' => '']],
            'dropoffs' => [['label' => 'Cubao return']],
            'hikes' => [$place->id => ['masl' => '2922']],
        ],
    ]);

    Livewire::actingAs($host)->test(Create::class)
        ->assertSet('title', 'Legacy Draft')
        ->assertSet('meetups.0.time', '')          // backfilled, not missing
        ->assertSet('dropoffs.0.time', '')
        ->assertSet('destinations.0.days', [''])
        ->assertSet("hikes.{$place->id}.features", [])
        ->assertSee('Cubao terminal')              // step 4 renders without blowing up
        ->call('goToStep', 1)
        ->assertSet("hikes.{$place->id}.masl", '2922')
        ->assertSee('The trail');                  // and so does step 1
});

it('carries a copied trip’s photos across and re-stores them as new files', function () {
    Storage::fake('public');
    $host = draftHost();
    $place = Place::factory()->create();

    // an original with every kind of image filled in
    $original = publishFor($host, $place, [], 'Photo Trip');
    Storage::disk('public')->put('covers/original.jpg', 'x');
    Storage::disk('public')->put('trips/carousel.jpg', 'x');
    Storage::disk('public')->put('inclusions/van.jpg', 'x');
    $original->update(['cover_photo' => 'covers/original.jpg']);
    $original->photos()->create(['path' => 'trips/carousel.jpg', 'position' => 0]);
    $original->inclusionItems()->create(['label' => 'Aircon van', 'image' => 'inclusions/van.jpg', 'position' => 9]);

    $page = Livewire::actingAs($host)->test(Create::class, ['from' => $original->fresh()])
        // the paths are in state, so the upload component can show them
        ->assertSet('cover', 'covers/original.jpg')
        ->assertSet('photos', ['trips/carousel.jpg']);

    // the uploads live on step 2, where the preview must show the carried-over file
    $page->call('next')
        ->assertSee('Kept from the original')
        ->call('next')->call('next')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 8]])
        ->call('next')
        ->call('publish');

    $copy = Trip::where('title', 'Photo Trip (copy)')->sole();

    expect($copy->cover_photo)->not->toBeNull()
        // a *copy* of the file, never the same path — deleting one must not blank the other
        ->and($copy->cover_photo)->not->toBe('covers/original.jpg')
        ->and($copy->photos->first()->path)->not->toBe('trips/carousel.jpg');

    Storage::disk('public')->assertExists($copy->cover_photo);
    Storage::disk('public')->assertExists($copy->photos->first()->path);

    // and the original is untouched
    Storage::disk('public')->assertExists('covers/original.jpg');
});
