<?php

use App\Models\Trip;

it('renders the community homepage with departing-soon trips', function () {
    Trip::factory()->published()->create(['title' => 'Mount Rinjani Traverse']);

    $this->get('/')->assertOk()
        ->assertSee('Better trips with')
        ->assertSee('Mount Rinjani Traverse');
});
