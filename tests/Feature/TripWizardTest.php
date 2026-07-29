<?php

use App\Livewire\Host\Trips\Create;
use App\Models\HostMeetupPreset;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function wizardHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

it('publishes a trip with an ordered itinerary', function () {
    $host = wizardHost();
    $a = Place::factory()->create(['name' => 'Ambangeg']);
    $b = Place::factory()->create(['name' => 'Mount Pulag']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $a->id)
        ->call('addDestination', $b->id)
        ->set('title', 'Pulag Traverse')
        ->call('next')->call('next')
        ->set('price', '2000')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 12]])
        ->call('next')
        ->call('publish');

    $trip = Trip::where('title', 'Pulag Traverse')->sole();

    expect($trip->destinations->pluck('place_id')->all())->toBe([$a->id, $b->id])
        ->and($trip->place_id)->toBe($a->id);
});

it('copies a preset into the meetup rather than linking to it', function () {
    $host = wizardHost();
    $place = Place::factory()->create();
    $preset = HostMeetupPreset::create([
        'host_id' => $host->id, 'label' => 'Cubao terminal',
        'address' => 'EDSA', 'lat' => 14.6220, 'lng' => 121.0530, 'note' => 'Blue van',
    ]);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Preset Trip')
        ->call('next')->call('next')
        ->call('applyPreset', 0, $preset->id)
        ->set('price', '900')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 8]])
        ->call('next')
        ->call('publish');

    $trip = Trip::where('title', 'Preset Trip')->sole();
    expect($trip->meetups->first()->label)->toBe('Cubao terminal');

    // Editing the preset afterwards must NOT rewrite history on a published trip.
    $preset->update(['label' => 'Renamed terminal']);

    expect($trip->refresh()->meetups->first()->label)->toBe('Cubao terminal');
});

it('saves a one-off meetup to the library when asked', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Library Trip')
        ->call('next')->call('next')
        ->set('meetups', [[
            'label' => 'New trailhead', 'address' => '', 'lat' => '', 'lng' => '',
            'note' => '', 'image' => null, 'save_to_library' => true,
        ]])
        ->set('price', '500')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 6]])
        ->call('next')
        ->call('publish');

    expect($host->meetupPresets()->where('label', 'New trailhead')->exists())->toBeTrue();
});

it('requires a destination before leaving step 1', function () {
    $host = wizardHost();

    Livewire::actingAs($host)->test(Create::class)
        ->set('title', 'No Destination')
        ->call('next')
        ->assertHasErrors(['destinations'])
        ->assertSet('step', 1);
});

it('walks where -> trip -> included -> logistics -> publish', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->assertSet('step', 2)          // the trip: title, photos, price
        ->set('title', 'Stepped Trip')
        ->set('price', '600')
        ->call('next')
        ->assertSet('step', 3)          // what's included
        ->call('next')
        ->assertSet('step', 4)          // logistics
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 9]])
        ->call('next')
        ->assertSet('step', 5);         // publish
});

it('does not advance past the trip step while the price is missing', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')                  // -> the trip
        ->set('title', 'No Price')
        ->set('price', '')
        ->call('next')
        ->assertHasErrors(['price'])
        ->assertSet('step', 2);
});

it('reports friendly validation text instead of raw attribute paths', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    $component = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', 'Bad Pin')
        ->set('price', '500')
        ->call('next')->call('next')    // -> included -> logistics
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 8]])
        ->set('meetups', [[
            'label' => 'Nowhere', 'address' => '', 'lat' => '999', 'lng' => '',
            'time' => '', 'note' => '', 'image' => null, 'save_to_library' => false,
        ]])
        ->call('next');

    $errors = $component->errors()->get('meetups.0.lat');

    expect($errors[0])->toContain('valid map pin')
        ->and($errors[0])->not->toContain('meetups.0.lat');
});

it('stores the cover and carousel photos in order', function () {
    Storage::fake('public');
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Photo Trip')
        ->set('cover', UploadedFile::fake()->image('cover.jpg'))
        ->set('photos', [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ])
        ->call('next')->call('next')
        ->set('price', '700')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 10]])
        ->call('next')
        ->call('publish');

    $trip = Trip::where('title', 'Photo Trip')->sole();

    expect($trip->cover_photo)->not->toBeNull()
        ->and($trip->photos->pluck('position')->all())->toBe([0, 1]);

    Storage::disk('public')->assertExists($trip->cover_photo);
    $trip->photos->each(fn ($p) => Storage::disk('public')->assertExists($p->path));
});

it('shows every destination on the public trip page', function () {
    $host = wizardHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Route Trip']);
    $a = Place::factory()->create(['name' => 'Ambangeg']);
    $b = Place::factory()->create(['name' => 'Akiki Trail']);

    $trip->syncDestinations([
        ['place_id' => $a->id, 'days' => ['Trek to camp']],
        ['place_id' => $b->id, 'days' => ['Summit push']],
    ]);

    $this->get('/t/'.$trip->slug)->assertOk()
        ->assertSee('Ambangeg')
        ->assertSee('Akiki Trail')
        ->assertSee('Summit push');   // the second stop's day description
});

it('drops blank inclusion and meetup rows', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Sparse Trip')
        ->set('inclusions', [
            ['label' => 'Guide', 'note' => '', 'image' => null],
            ['label' => '', 'note' => '', 'image' => null],
        ])
        ->call('next')->call('next')
        ->set('meetups', [
            ['label' => 'Real spot', 'address' => '', 'lat' => '', 'lng' => '', 'note' => '', 'image' => null, 'save_to_library' => false],
            ['label' => '', 'address' => '', 'lat' => '', 'lng' => '', 'note' => '', 'image' => null, 'save_to_library' => false],
        ])
        ->set('price', '400')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 5]])
        ->call('next')
        ->call('publish');

    $trip = Trip::where('title', 'Sparse Trip')->sole();

    expect($trip->inclusionItems)->toHaveCount(1)
        ->and($trip->meetups)->toHaveCount(1);
});

it('summarises every step on the review screen', function () {
    $host = wizardHost();
    $place = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike', 'elevation_m' => null]);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('destinations.0.days.0', 'Trek to Camp 2')
        ->set("hikes.{$place->id}.masl", '2922')
        ->set("hikes.{$place->id}.difficulty", 5)
        ->call('next')
        ->set('title', 'Pulag Sunrise')
        ->set('description', 'A cold, beautiful dawn.')
        ->set('price', '2500')
        ->call('next')->call('next')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 9]])
        ->set('meetups', [['label' => 'Cubao terminal', 'address' => '', 'lat' => '14.62', 'lng' => '121.05',
                           'time' => '22:00', 'note' => '', 'image' => null, 'save_to_library' => false]])
        ->set('dropoffs', [['label' => 'Cubao terminal', 'address' => '', 'lat' => '', 'lng' => '',
                            'time' => '18:00', 'note' => '', 'image' => null, 'save_to_library' => false]])
        ->call('next')
        ->assertSet('step', 5)
        // every step's content is on the review screen, not just a tick
        ->assertSee('Mount Pulag')
        ->assertSee('Trek to Camp 2')
        ->assertSee('Major climb')
        ->assertSee('Pulag Sunrise')
        ->assertSee('A cold, beautiful dawn.')
        ->assertSee('2,500')
        ->assertSee('Cubao terminal')
        ->assertSee('22:00')
        ->assertSee('18:00')
        ->assertSee('up to 9 joiners')
        ->assertSee('pinned');
});

it('jumps back to the step behind each review section', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    $page = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', 'Jumper')
        ->set('price', '900')
        ->call('next')->call('next')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 9]])
        ->call('next')
        ->assertSet('step', 5);

    foreach ([1, 2, 3, 4] as $step) {
        $page->call('goToStep', $step)->assertSet('step', $step);
    }

    // and it clamps rather than trusting the caller
    $page->call('goToStep', 99)->assertSet('step', 5)
        ->call('goToStep', -3)->assertSet('step', 1);
});

it('stores a drop-off pin, photo and library copy just like a pickup', function () {
    Storage::fake('public');
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', 'Round Trip')
        ->set('price', '1200')
        ->call('next')->call('next')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 9]])
        ->set('dropoffs', [['label' => 'Cubao terminal', 'address' => 'EDSA', 'lat' => '14.6220', 'lng' => '121.0530',
                            'time' => '18:30', 'note' => 'By the jeepney bay', 'image' => null, 'save_to_library' => true]])
        ->set('dropoffs.0.image', UploadedFile::fake()->image('bay.jpg'))
        ->call('publish');

    $trip = Trip::where('title', 'Round Trip')->sole();
    $drop = $trip->dropoffs->sole();

    expect($drop->label)->toBe('Cubao terminal')
        ->and($drop->time_label)->toBe('18:30')
        ->and($drop->lat)->toBe(14.622)
        ->and($drop->lng)->toBe(121.053)
        ->and($drop->image)->not->toBeNull()
        ->and($drop->map_embed_url)->toContain('14.622,121.053')
        ->and($trip->meetups)->toHaveCount(0)
        ->and($host->meetupPresets()->where('label', 'Cubao terminal')->exists())->toBeTrue();
});

it('rejects a drop-off pin outside the valid range', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', 'Bad Drop')
        ->set('price', '900')
        ->call('next')->call('next')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 9]])
        ->set('dropoffs', [['label' => 'Nowhere', 'address' => '', 'lat' => '999', 'lng' => '0',
                            'time' => '', 'note' => '', 'image' => null, 'save_to_library' => false]])
        ->call('next')
        ->assertHasErrors(['dropoffs.0.lat'])
        ->assertSet('step', 4);
});

it('embeds a live map only once both coordinates are usable', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    $page = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')->set('title', 'Mapped')->set('price', '900')
        ->call('next')->call('next');

    // nothing typed yet
    $page->assertDontSee('output=embed', false);

    // half a pin is not a pin — a lone latitude must not drop a marker in the ocean
    $page->set('meetups.0.lat', '14.6220')->assertDontSee('output=embed', false);

    $page->set('meetups.0.lng', '121.0530')
        ->assertSee('maps.google.com/maps?q=14.6220,121.0530', false)
        ->assertSee('output=embed', false);

    // and out-of-range values are refused an embed rather than rendering a broken map
    $page->set('meetups.0.lat', '999')->assertDontSee('output=embed', false);
});

it('embeds a map for a drop-off pin too', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')->set('title', 'Mapped Drop')->set('price', '900')
        ->call('next')->call('next')
        ->set('dropoffs.0.lat', '14.6220')
        ->set('dropoffs.0.lng', '121.0530')
        ->assertSee('maps.google.com/maps?q=14.6220,121.0530', false);
});
