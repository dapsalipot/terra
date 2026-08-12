<?php

use App\Livewire\Host\Trips\Create;
use App\Livewire\Trips\Show;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\HikeGrades;
use Livewire\Livewire;

function hikeHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

/**
 * Drive the wizard to a published trip.
 *
 * @param  array<int, array<string, mixed>>  $hikes  trail detail keyed by place id
 * @param  list<Place>  $places  itinerary order
 */
function publishTrip(User $host, array $places, array $hikes = [], string $title = 'Pulag Traverse'): Trip
{
    $test = Livewire::actingAs($host)->test(Create::class);

    foreach ($places as $place) {
        $test->call('addDestination', $place->id);
    }

    $test->set('title', $title);

    foreach ($hikes as $placeId => $fields) {
        foreach ($fields as $key => $value) {
            $test->set("hikes.$placeId.$key", $value);
        }
    }

    $test->call('next')     // the trip & trail -> what's included
        ->call('next')      // -> logistics
        ->set('price', '2000')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 12]])
        ->call('next')      // -> publish
        ->call('publish');

    return Trip::where('title', $title)->sole();
}

/** Trail detail for one stop of a published trip. */
function trailFor(Trip $trip, Place $place)
{
    return $trip->destinations->firstWhere('place_id', $place->id)?->hikeDetail;
}

it('records the trail numbers, grades and features for a hike', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);

    $trip = publishTrip($host, [$place], [$place->id => [
        'trail_name' => 'Akiki–Ambangeg traverse',
        'jump_off' => 'Brgy. Ambangeg, Bokod',
        'masl' => '2922',
        'elevation_gain_m' => '1450',
        'distance_km' => '14.5',
        'duration_hours' => '9',
        'difficulty' => 5,
        'trail_class' => 2,
        'trail_type' => 'traverse',
        'features' => ['assault', 'mossy_forest', 'sea_of_clouds'],
        'pulag_factors' => ['low_temperature', 'gusts'],
    ]]);

    $hike = trailFor($trip, $place);

    expect($hike)->not->toBeNull()
        ->and($hike->masl)->toBe(2922)
        ->and($hike->elevation_gain_m)->toBe(1450)
        ->and($hike->distance_km)->toBe(14.5)
        ->and($hike->duration_hours)->toBe(9.0)
        ->and($hike->trail_type)->toBe('traverse')
        ->and($hike->features)->toBe(['assault', 'mossy_forest', 'sea_of_clouds'])
        ->and($hike->pulag_factors)->toBe(['low_temperature', 'gusts']);
});

it('keeps a separate trail for every mountain on a multi-stop trip', function () {
    $host = hikeHost();
    $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike', 'elevation_m' => null]);
    $ulap = Place::factory()->create(['name' => 'Mount Ulap', 'category' => 'hike', 'elevation_m' => null]);

    $trip = publishTrip($host, [$pulag, $ulap], [
        $pulag->id => ['masl' => '2922', 'distance_km' => '14.5', 'difficulty' => 5, 'features' => ['mossy_forest']],
        $ulap->id => ['masl' => '1846', 'distance_km' => '9', 'difficulty' => 3, 'features' => ['grassland']],
    ], 'Two Summits');

    expect(trailFor($trip, $pulag)->masl)->toBe(2922)
        ->and(trailFor($trip, $pulag)->features)->toBe(['mossy_forest'])
        ->and(trailFor($trip, $ulap)->masl)->toBe(1846)
        ->and(trailFor($trip, $ulap)->features)->toBe(['grassland'])
        ->and($trip->hikeDetails()->count())->toBe(2);
});

it('nests a trail section inside each hiking stop and none inside a beach', function () {
    $host = hikeHost();
    $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);
    $ulap = Place::factory()->create(['name' => 'Mount Ulap', 'category' => 'hike']);
    $beach = Place::factory()->create(['name' => 'Nacpan Beach', 'category' => 'beach']);

    $html = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $pulag->id)
        ->call('addDestination', $ulap->id)
        ->call('addDestination', $beach->id)
        ->assertSee('Mount Pulag')
        ->assertSee('Mount Ulap')
        ->assertSee('Nacpan Beach')
        ->html();

    // one trail section per hike, and the beach stop carries none
    expect(substr_count($html, 'The trail'))->toBe(2)
        ->and(substr_count($html, 'What hikers want to know about Mount Pulag'))->toBe(1)
        ->and(substr_count($html, 'What hikers want to know about Mount Ulap'))->toBe(1)
        ->and($html)->not->toContain('What hikers want to know about Nacpan Beach');
});

it('gives every stop its own list of described days', function () {
    $host = hikeHost();
    $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);
    $ulap = Place::factory()->create(['name' => 'Mount Ulap', 'category' => 'hike']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $pulag->id)
        ->call('addDestination', $ulap->id)
        // each stop opens with one day; add two more to Pulag
        ->call('addDay', 0)
        ->call('addDay', 0)
        ->set('destinations.0.days.0', 'Trek to Camp 2')
        ->set('destinations.0.days.2', 'Descend to jump-off')
        ->assertCount('destinations.0.days', 3)
        ->assertCount('destinations.1.days', 1)
        ->assertSet('destinations.0.days.0', 'Trek to Camp 2')
        ->assertSet('destinations.0.days.2', 'Descend to jump-off')
        ->assertSee('4 days')       // 3 at Pulag + 1 at Ulap
        ->assertSee('Day 3');       // the third row exists to type into
});

it('keeps at least one day on every stop', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['category' => 'hike']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('removeDay', 0, 0)
        ->assertCount('destinations.0.days', 1)
        ->call('addDay', 0)
        ->call('removeDay', 0, 1)
        ->assertCount('destinations.0.days', 1);
});

it('keeps each stop’s numbers with its own mountain when the order changes', function () {
    $host = hikeHost();
    $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike', 'elevation_m' => null]);
    $ulap = Place::factory()->create(['name' => 'Mount Ulap', 'category' => 'hike', 'elevation_m' => null]);

    $page = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $pulag->id)
        ->call('addDestination', $ulap->id)
        ->set("hikes.{$pulag->id}.masl", '2922')
        ->set("hikes.{$ulap->id}.masl", '1846')
        ->call('moveDestination', 0, 1);   // Ulap now leads

    $page->assertSet("hikes.{$pulag->id}.masl", '2922')
        ->assertSet("hikes.{$ulap->id}.masl", '1846');
});

it('reads a PinoyMountaineer grade back as a band and a label', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['category' => 'hike']);

    $hike = trailFor(
        publishTrip($host, [$place], [$place->id => ['difficulty' => 5, 'trail_class' => 3, 'trail_type' => 'loop']]),
        $place,
    );

    expect($hike->band)->toBe('Major climb')
        ->and($hike->difficulty_label)->toBe('Moderate')
        ->and($hike->trail_class_label)->toBe('Class 3')
        ->and($hike->trail_type_label)->toBe('Loop');
});

it('bands a grade of 4 as a minor climb and 9 as hardcore', function () {
    expect(HikeGrades::band(1))->toBe('Minor climb')
        ->and(HikeGrades::band(4))->toBe('Minor climb')
        ->and(HikeGrades::band(5))->toBe('Major climb')
        ->and(HikeGrades::band(8))->toBe('Major climb')
        ->and(HikeGrades::band(9))->toBe('Hardcore')
        ->and(HikeGrades::band(null))->toBeNull();
});

it('opens the trail section on step 1 as soon as a hike destination is picked', function () {
    $host = hikeHost();
    $hike = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);

    Livewire::actingAs($host)->test(Create::class)
        ->assertDontSee('The trail')
        ->call('addDestination', $hike->id)
        ->assertSet('step', 1)
        ->assertSee('The trail')
        ->assertSee('Trail grade')
        ->assertSee('Monkey trail')
        ->assertSee('Where &amp; the trail', false);
});

it('names the grade in words as soon as one is picked', function () {
    $host = hikeHost();
    $hike = Place::factory()->create(['category' => 'hike', 'elevation_m' => null]);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $hike->id)
        ->set("hikes.{$hike->id}.difficulty", 5)
        ->assertSee('Major climb')
        ->assertSee('Moderate')
        ->assertSee('5–9 hours with class 3+ parts');
});

it('says nothing about trails when the destination is not a hike', function () {
    $host = hikeHost();
    $beach = Place::factory()->create(['name' => 'Nacpan Beach', 'category' => 'beach']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $beach->id)
        ->assertDontSee('Trail grade')
        ->assertDontSee('Where &amp; the trail', false);
});

it('refuses a trail feature that is not in the catalogue', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['category' => 'hike']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Made Up Trail')
        ->set("hikes.{$place->id}.features", ['assault', 'lava_surfing'])
        ->call('next')
        ->assertHasErrors(["hikes.{$place->id}.features.1"])
        ->assertSet('step', 1);
});

it('refuses a trail shape and a grade outside the published scales', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['category' => 'hike']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Out Of Range')
        ->set("hikes.{$place->id}.trail_type", 'teleport')
        ->set("hikes.{$place->id}.difficulty", 12)
        ->set("hikes.{$place->id}.trail_class", 7)
        ->call('next')
        ->assertHasErrors([
            "hikes.{$place->id}.trail_type",
            "hikes.{$place->id}.difficulty",
            "hikes.{$place->id}.trail_class",
        ])
        ->assertSet('step', 1);
});

it('toggles a feature on and back off', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['category' => 'hike']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('toggleFeature', $place->id, 'river_walk')
        ->assertSet("hikes.{$place->id}.features", ['river_walk'])
        ->call('toggleFeature', $place->id, 'monkey_trail')
        ->assertSet("hikes.{$place->id}.features", ['river_walk', 'monkey_trail'])
        ->call('toggleFeature', $place->id, 'river_walk')
        ->assertSet("hikes.{$place->id}.features", ['monkey_trail']);
});

it('leaves no trail row when nothing is known and the host adds nothing', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['category' => 'hike', 'elevation_m' => null]);

    expect(trailFor(publishTrip($host, [$place], [], 'Bare Trip'), $place))->toBeNull();
});

it('publishes the height the app already knew even when the host types nothing', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['category' => 'hike', 'elevation_m' => 2926]);

    expect(trailFor(publishTrip($host, [$place], [], 'Untouched Trip'), $place)?->masl)->toBe(2926);
});

it('never overwrites a height the host typed', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['category' => 'hike', 'elevation_m' => 2926]);

    $page = Livewire::actingAs($host)->test(Create::class)
        ->set("hikes.{$place->id}.masl", '2922')
        ->call('addDestination', $place->id)
        ->assertSet("hikes.{$place->id}.masl", '2922');

    // the blank title was still filled in — only the height the host owned was left alone
    expect($page->get('prefilled'))->toContain('title')->not->toContain("hikes.{$place->id}.masl");
});

it('ignores trail detail when no destination is a hike', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['name' => 'Nacpan Beach', 'category' => 'beach']);

    $trip = publishTrip($host, [$place], [$place->id => ['masl' => '2922', 'difficulty' => 5]], 'Beach Trip');

    expect($trip->hikeDetails()->count())->toBe(0);
});

it('shows the trail panel with its definitions on the public trip page', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);

    $trip = publishTrip($host, [$place], [$place->id => [
        'trail_name' => 'Akiki traverse',
        'jump_off' => 'Brgy. Ambangeg',
        'masl' => '2922',
        'distance_km' => '14.5',
        'difficulty' => 5,
        'trail_class' => 3,
        'trail_type' => 'traverse',
        'features' => ['monkey_trail', 'river_walk'],
        'pulag_factors' => ['low_temperature'],
    ]]);

    TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addMonth()]);

    Livewire::test(Show::class, ['trip' => $trip])
        ->assertSee('The trail')
        ->assertSee('Akiki traverse')
        ->assertSee('2,922 m')
        ->assertSee('14.5 km')
        ->assertSee('Major climb')
        ->assertSee('Class 3')
        ->assertSee('Traverse')
        ->assertSee('Brgy. Ambangeg')
        ->assertSee('Monkey trail')
        ->assertSee('River walk')
        ->assertSee('Low temperature');
});

it('shows both mountains’ trails on the public page of a traverse', function () {
    $host = hikeHost();
    $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike', 'elevation_m' => null]);
    $ulap = Place::factory()->create(['name' => 'Mount Ulap', 'category' => 'hike', 'elevation_m' => null]);

    $trip = publishTrip($host, [$pulag, $ulap], [
        $pulag->id => ['masl' => '2922', 'features' => ['mossy_forest']],
        $ulap->id => ['masl' => '1846', 'features' => ['grassland']],
    ], 'Two Summits');

    TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addMonth()]);

    Livewire::test(Show::class, ['trip' => $trip])
        ->assertSee('Mount Pulag')
        ->assertSee('2,922 m')
        ->assertSee('Mossy forest')
        ->assertSee('Mount Ulap')
        ->assertSee('1,846 m')
        ->assertSee('Grassland');
});

it('keeps the trail panel off a trip with no trail detail', function () {
    $host = hikeHost();
    $place = Place::factory()->create(['category' => 'hike', 'elevation_m' => null]);

    $trip = publishTrip($host, [$place], [], 'Quiet Trip');
    TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addMonth()]);

    Livewire::test(Show::class, ['trip' => $trip])
        ->assertDontSee('The trail');
});
