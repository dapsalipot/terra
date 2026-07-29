<?php

use App\Models\Place;
use App\Models\Trip;

it('stores destinations in order and syncs place_id to the first', function () {
    $trip = Trip::factory()->published()->create();
    $a = Place::factory()->create(['name' => 'Ambangeg']);
    $b = Place::factory()->create(['name' => 'Mount Pulag']);

    $trip->syncDestinations([
        ['place_id' => $a->id, 'days' => ['Trek to camp']],
        ['place_id' => $b->id, 'days' => ['Summit push']],
    ]);

    $trip->refresh()->load('destinations');

    expect($trip->destinations->pluck('place_id')->all())->toBe([$a->id, $b->id])
        ->and($trip->destinations->pluck('position')->all())->toBe([0, 1])
        ->and($trip->destinations->first()->days)->toBe(['Trek to camp'])
        // legacy read sites still work
        ->and($trip->place_id)->toBe($a->id);
});

it('replaces destinations on resync rather than appending', function () {
    $trip = Trip::factory()->published()->create();
    $a = Place::factory()->create();
    $b = Place::factory()->create();

    $trip->syncDestinations([['place_id' => $a->id, 'days' => ['']]]);
    $trip->syncDestinations([['place_id' => $b->id, 'days' => ['']]]);

    expect($trip->refresh()->destinations)->toHaveCount(1)
        ->and($trip->destinations->first()->place_id)->toBe($b->id)
        ->and($trip->place_id)->toBe($b->id);
});

it('inherits difficulty from the first destination when not overridden', function () {
    $trip = Trip::factory()->published()->create(['difficulty' => null]);
    $place = Place::factory()->create(['difficulty' => 'hard']);
    $trip->syncDestinations([['place_id' => $place->id, 'days' => ['']]]);

    expect($trip->refresh()->effective_difficulty)->toBe('hard');
});

it('lets an explicit trip difficulty override the destination', function () {
    $trip = Trip::factory()->published()->create(['difficulty' => 'easy']);
    $place = Place::factory()->create(['difficulty' => 'hard']);
    $trip->syncDestinations([['place_id' => $place->id, 'days' => ['']]]);

    expect($trip->refresh()->effective_difficulty)->toBe('easy');
});
