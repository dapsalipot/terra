<?php

use App\Models\Place;

it('creates a place with a slug and difficulty', function () {
    $p = Place::factory()->create(['name' => 'Mount Rinjani', 'difficulty' => 'challenging']);
    expect($p->slug)->toBe('mount-rinjani')
        ->and($p->difficulty)->toBe('challenging');
});

it('seeds at least 8 international mountains', function () {
    $this->seed(\Database\Seeders\PlaceSeeder::class);
    expect(Place::count())->toBeGreaterThanOrEqual(8)
        ->and(Place::where('country', 'Indonesia')->exists())->toBeTrue();
});
