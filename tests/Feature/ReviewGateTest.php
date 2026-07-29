<?php

use App\Models\Place;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReviewGate;

beforeEach(function () {
    $this->gate = app(ReviewGate::class);
    $this->host = User::factory()->create(['role' => 'host']);
    $this->place = Place::factory()->create();
    $this->trip = Trip::factory()->published()->create(['host_id' => $this->host->id, 'place_id' => $this->place->id]);
    $this->joiner = User::factory()->create(['role' => 'joiner']);
});

it('allows review only after a confirmed reservation on a completed departure', function () {
    $departure = TripDeparture::factory()->completed()->for($this->trip)->create();
    Reservation::factory()->confirmed()->create([
        'trip_departure_id' => $departure->id, 'joiner_id' => $this->joiner->id,
    ]);

    expect($this->gate->allows($this->joiner, $this->trip))->toBeTrue()
        ->and($this->gate->allows($this->joiner, $this->host))->toBeTrue()   // the host
        ->and($this->gate->allows($this->joiner, $this->place))->toBeTrue(); // the mountain
});

it('denies review when the departure is not completed', function () {
    $departure = TripDeparture::factory()->for($this->trip)->create(['status' => 'open']);
    Reservation::factory()->confirmed()->create([
        'trip_departure_id' => $departure->id, 'joiner_id' => $this->joiner->id,
    ]);

    expect($this->gate->allows($this->joiner, $this->trip))->toBeFalse();
});

it('denies review when the reservation is not confirmed', function () {
    $departure = TripDeparture::factory()->completed()->for($this->trip)->create();
    Reservation::factory()->create([
        'trip_departure_id' => $departure->id, 'joiner_id' => $this->joiner->id, 'status' => 'cancelled',
    ]);

    expect($this->gate->allows($this->joiner, $this->trip))->toBeFalse();
});

it('denies review from a joiner who never booked', function () {
    TripDeparture::factory()->completed()->for($this->trip)->create();
    expect($this->gate->allows($this->joiner, $this->trip))->toBeFalse();
});
