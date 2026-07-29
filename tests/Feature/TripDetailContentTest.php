<?php

use App\Livewire\Host\Trips\Create;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function contentHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

it('publishes a trip with several meetup points and photographed inclusions', function () {
    Storage::fake('public');
    $host = contentHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Pulag Traverse')
        ->call('next')->call('next')
        ->set('price', 2000)
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 12]])
        ->set('meetups', [
            ['label' => 'Cubao terminal', 'address' => 'EDSA', 'lat' => '14.6220', 'lng' => '121.0530', 'note' => 'Blue van', 'image' => null],
            ['label' => 'Baguio drop-off', 'address' => '', 'lat' => '', 'lng' => '', 'note' => '', 'image' => null],
        ])
        ->set('inclusions', [
            ['label' => 'Transport', 'note' => 'Aircon van', 'image' => null],
            ['label' => 'Permits', 'note' => '', 'image' => null],
        ])
        // set uploads on their own keys so Livewire wraps them as temporary files
        ->set('meetups.0.image', UploadedFile::fake()->image('spot.jpg'))
        ->set('inclusions.0.image', UploadedFile::fake()->image('van.jpg'))
        ->call('publish');

    $trip = Trip::where('title', 'Pulag Traverse')->sole();

    expect($trip->meetups)->toHaveCount(2)
        ->and($trip->inclusionItems)->toHaveCount(2);

    $first = $trip->meetups->first();
    expect($first->label)->toBe('Cubao terminal')
        ->and($first->hasPin())->toBeTrue()
        ->and($first->map_embed_url)->toContain('14.622')
        ->and($first->image)->not->toBeNull();

    // a spot without coordinates still renders, just without a map
    expect($trip->meetups->last()->hasPin())->toBeFalse()
        ->and($trip->meetups->last()->map_embed_url)->toBeNull();

    Storage::disk('public')->assertExists($first->image);
    Storage::disk('public')->assertExists($trip->inclusionItems->first()->image);
});

it('skips meetups and inclusions the host left unnamed', function () {
    $host = contentHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Sparse Trip')
        ->call('next')->call('next')
        ->set('price', 500)
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 8]])
        ->set('meetups', [
            ['label' => 'Real spot', 'address' => '', 'lat' => '', 'lng' => '', 'note' => '', 'image' => null],
            ['label' => '', 'address' => '', 'lat' => '', 'lng' => '', 'note' => '', 'image' => null],
        ])
        ->set('inclusions', [
            ['label' => 'Guide', 'note' => '', 'image' => null],
            ['label' => '', 'note' => '', 'image' => null],
        ])
        ->call('publish');

    $trip = Trip::where('title', 'Sparse Trip')->sole();

    expect($trip->meetups)->toHaveCount(1)
        ->and($trip->inclusionItems)->toHaveCount(1)
        // the legacy single-string column tracks the first meetup
        ->and($trip->meetup_point)->toBe('Real spot');
});

it('rejects coordinates outside the valid range', function () {
    $host = contentHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')                              // -> the trip
        ->set('title', 'Bad Pin')
        ->set('price', 500)
        ->call('next')->call('next')                // -> included -> logistics
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 8]])
        ->set('meetups', [['label' => 'Nowhere', 'address' => '', 'lat' => '999', 'lng' => '0', 'time' => '', 'note' => '', 'image' => null]])
        ->call('next')
        ->assertHasErrors(['meetups.0.lat']);
});

it('shows meetup points and the map on the public trip page', function () {
    $host = contentHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Mapped Trip']);

    $trip->meetups()->create(['label' => 'Cubao terminal', 'address' => 'EDSA', 'lat' => 14.622, 'lng' => 121.053, 'position' => 0]);
    $trip->inclusionItems()->create(['label' => 'Transport', 'note' => 'Aircon van', 'position' => 0]);

    $this->get('/t/'.$trip->slug)->assertOk()
        ->assertSee('Cubao terminal')
        ->assertSee('Transport')
        ->assertSee('output=embed', escape: false);   // the embedded Google map
});
