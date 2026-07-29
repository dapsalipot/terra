<?php

use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ChatGate;

beforeEach(function () {
    $this->gate = app(ChatGate::class);
    $this->host = User::factory()->create(['role' => 'host']);
    $this->trip = Trip::factory()->published()->create(['host_id' => $this->host->id]);
    $this->departure = TripDeparture::factory()->for($this->trip)->create();
});

it('lets the host and confirmed joiners in', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);
    Reservation::factory()->confirmed()->create(['trip_departure_id' => $this->departure->id, 'joiner_id' => $joiner->id]);

    expect($this->gate->canAccess($this->host, $this->departure))->toBeTrue()
        ->and($this->gate->canAccess($joiner, $this->departure))->toBeTrue();
});

it('keeps out unconfirmed and unrelated users', function () {
    $pending = User::factory()->create(['role' => 'joiner']);
    Reservation::factory()->create(['trip_departure_id' => $this->departure->id, 'joiner_id' => $pending->id, 'status' => 'pending']);
    $stranger = User::factory()->create(['role' => 'joiner']);

    expect($this->gate->canAccess($pending, $this->departure))->toBeFalse()
        ->and($this->gate->canAccess($stranger, $this->departure))->toBeFalse();
});

it('closes the room 7 days after the trip ends', function () {
    $old = TripDeparture::factory()->completed()->for($this->trip)->create([
        'ends_at' => now()->subDays(10),
    ]);
    $recent = TripDeparture::factory()->completed()->for($this->trip)->create([
        'ends_at' => now()->subDays(2),
    ]);

    expect($this->gate->isReadOnly($old))->toBeTrue()
        ->and($this->gate->isReadOnly($recent))->toBeFalse()
        ->and($this->gate->isReadOnly($this->departure))->toBeFalse(); // not completed
});
