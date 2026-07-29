<?php

use App\Models\Post;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\DepartureCompletionService;

it('auto-publishes a recap post when a departure completes', function () {
    $host = User::factory()->create(['role' => 'host']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Mount Rinjani Traverse']);
    $departure = TripDeparture::factory()->for($trip)->create(['status' => 'open']);
    Reservation::factory()->confirmed()->create(['trip_departure_id' => $departure->id, 'party_size' => 3]);

    app(DepartureCompletionService::class)->complete($departure);

    $post = Post::where('host_id', $host->id)->first();
    expect($departure->fresh()->status)->toBe('completed')
        ->and($post)->not->toBeNull()
        ->and($post->type)->toBe('recap')
        ->and($post->body)->toContain('Mount Rinjani Traverse')
        ->and($post->body)->toContain('3 joiners');
});

it('the departures:complete command finishes past trips', function () {
    $trip = Trip::factory()->published()->create();
    TripDeparture::factory()->for($trip)->create(['status' => 'open', 'ends_at' => now()->subDay()]);
    TripDeparture::factory()->for($trip)->create(['status' => 'open', 'ends_at' => now()->addWeek()]);

    $this->artisan('departures:complete')->expectsOutputToContain('Completed 1 departure(s).');

    expect(Post::where('type', 'recap')->count())->toBe(1);
});
