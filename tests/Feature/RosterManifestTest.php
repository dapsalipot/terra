<?php

use App\Livewire\Host\Roster;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'host']);
    $trip = Trip::factory()->create(['host_id' => $this->host->id]);
    $this->departure = TripDeparture::factory()->create([
        'trip_id' => $trip->id, 'starts_at' => now()->addMonth(), 'capacity' => 10,
    ]);
});

it('lists every traveller by name', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 2, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0,
        'first_name' => 'Ada', 'last_name' => 'Reyes',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 1,
        'first_name' => 'Ben', 'last_name' => 'Cruz',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->assertSee('Ada Reyes')
        ->assertSee('Ben Cruz');
});

it('counts travellers still owing details', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 3, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 1]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 2]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->assertSee('2 of 3 still need details');
});

it('flags a traveller who is a minor on the departure date', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0,
        'first_name' => 'Kid', 'last_name' => 'Cruz',
        'birthday' => now()->subYears(15)->format('Y-m-d'),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->assertSee('guardian consent');
});

it('does not flag someone who turns 18 before the trip', function () {
    // Departure is a month out, so a 17-year-old whose birthday falls next week travels as an
    // adult. Measuring against today instead of the departure date would wrongly flag them.
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0,
        'first_name' => 'Nearly', 'last_name' => 'Adult',
        'birthday' => now()->subYears(18)->addWeek()->format('Y-m-d'),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->assertDontSee('guardian consent');
});

it('marks a booking whose booker is not travelling', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0, 'is_booker' => false,
        'first_name' => 'Dee', 'last_name' => 'Santos',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->assertSee('Booker not travelling');
});
