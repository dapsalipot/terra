<?php

use App\Models\HostProfile;
use App\Models\Trip;
use App\Models\User;

it('shows a host page with upcoming trips', function () {
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['bio' => 'Beginner-friendly climbs']);
    Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Rinjani Traverse']);

    $this->get('/@ridgeline')->assertOk()
        ->assertSee('Ridgeline')
        ->assertSee('Beginner-friendly climbs')
        ->assertSee('Rinjani Traverse');
});

it('404s for a joiner handle', function () {
    User::factory()->create(['role' => 'joiner', 'handle' => 'nope']);
    $this->get('/@nope')->assertNotFound();
});
