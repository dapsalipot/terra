<?php

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReviewService;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('records trip, host and place reviews and recomputes aggregates', function () {
    $host = User::factory()->create(['role' => 'host']);
    HostProfile::factory()->for($host)->create();
    $place = Place::factory()->create();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'place_id' => $place->id]);
    $departure = TripDeparture::factory()->completed()->for($trip)->create();
    $joiner = User::factory()->create(['role' => 'joiner']);
    Reservation::factory()->confirmed()->create(['trip_departure_id' => $departure->id, 'joiner_id' => $joiner->id]);

    app(ReviewService::class)->record($joiner, $trip, ['trip' => 5, 'host' => 4, 'place' => 5], 'Unreal sunrise.');

    expect(Review::count())->toBe(3)
        ->and($trip->fresh()->aggregate_rating)->toEqual(5.0)
        ->and($trip->fresh()->reviews_count)->toBe(1)
        ->and($place->fresh()->aggregate_rating)->toEqual(5.0)
        ->and($host->fresh()->hostProfile->rating)->toEqual(4.0);
});

it('refuses a review from a non-attendee', function () {
    $trip = Trip::factory()->published()->create();
    $stranger = User::factory()->create(['role' => 'joiner']);

    app(ReviewService::class)->record($stranger, $trip, ['trip' => 5]);
})->throws(HttpException::class);
