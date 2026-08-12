<?php

use App\Livewire\Trips\Index;
use App\Models\Place;
use App\Models\Trip;
use Livewire\Livewire;

it('filters trips by search term', function () {
    Trip::factory()->published()->create(['title' => 'Mount Rinjani Traverse']);
    Trip::factory()->published()->create(['title' => 'Fuji Sunrise']);

    Livewire::test(Index::class)
        ->set('search', 'Rinjani')
        ->assertSee('Mount Rinjani Traverse')
        ->assertDontSee('Fuji Sunrise');
});

it('filters trips by country', function () {
    $jp = Place::factory()->create(['country' => 'Japan']);
    $id = Place::factory()->create(['country' => 'Indonesia']);
    Trip::factory()->published()->create(['place_id' => $jp->id, 'title' => 'Fuji Sunrise']);
    Trip::factory()->published()->create(['place_id' => $id->id, 'title' => 'Rinjani Traverse']);

    Livewire::test(Index::class)
        ->set('country', 'Japan')
        ->assertSee('Fuji Sunrise')
        ->assertDontSee('Rinjani Traverse');
});
