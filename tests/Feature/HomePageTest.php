<?php

use App\Models\Trip;

it('renders the community homepage with departing-soon trips', function () {
    Trip::factory()->published()->create(['title' => 'Mount Rinjani Traverse']);

    $this->get('/')->assertOk()
        // Both halves, in order: the hero splits the tagline across a <br> and an italic span,
        // so a plain assertSee of the whole phrase can never match. Checking only the first half
        // let the second half change without anything failing.
        ->assertSeeInOrder(['Conquering trails', 'together'])
        ->assertSee('Mount Rinjani Traverse');
});
