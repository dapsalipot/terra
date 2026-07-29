<?php

use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\TripDeparture;

it('generates a public token on create', function () {
    $reservation = Reservation::factory()->create();

    expect($reservation->public_token)->not->toBeNull()
        ->and(strlen($reservation->public_token))->toBe(36);
});

it('builds a full name and skips a missing middle name', function () {
    $with = new ReservationParticipant(['first_name' => 'Ada', 'middle_name' => 'Luz', 'last_name' => 'Reyes']);
    $without = new ReservationParticipant(['first_name' => 'Ada', 'last_name' => 'Reyes']);

    expect($with->fullName())->toBe('Ada Luz Reyes')
        ->and($without->fullName())->toBe('Ada Reyes');
});

it('is complete only with first name, last name and birthday', function () {
    $complete = new ReservationParticipant(['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02']);
    $noBirthday = new ReservationParticipant(['first_name' => 'Ada', 'last_name' => 'Reyes']);
    $blank = new ReservationParticipant([]);

    expect($complete->isComplete())->toBeTrue()
        ->and($noBirthday->isComplete())->toBeFalse()
        ->and($blank->isComplete())->toBeFalse();
});

it('measures minority against the departure date, not today', function () {
    // Turns 18 on 2026-09-01. Departure is after that, so they are NOT a minor on the trip.
    $participant = new ReservationParticipant(['birthday' => '2008-09-01']);

    expect($participant->isMinorAt(\Carbon\Carbon::parse('2026-10-01')))->toBeFalse()
        ->and($participant->isMinorAt(\Carbon\Carbon::parse('2026-08-01')))->toBeTrue();
});

it('treats a participant with no birthday as not a minor', function () {
    expect((new ReservationParticipant([]))->isMinorAt(\Carbon\Carbon::parse('2026-10-01')))->toBeFalse();
});

it('separates the booker from the travelers', function () {
    $departure = TripDeparture::factory()->create(['capacity' => 10]);
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $departure->id,
        'party_size' => 3,
        'contact_first_name' => 'Ada',
        'contact_last_name' => 'Reyes',
        'contact_email' => 'ada@example.com',
        'contact_phone' => '09171234567',
    ]);

    // Coordinator case: three travelers, none of whom is the booker.
    ReservationParticipant::factory()->count(3)->sequence(
        ['position' => 0], ['position' => 1], ['position' => 2],
    )->create(['reservation_id' => $reservation->id, 'is_booker' => false]);

    expect($reservation->contactName())->toBe('Ada Reyes')
        ->and($reservation->bookerIsTraveling())->toBeFalse()
        ->and($reservation->bookerParticipant)->toBeNull()
        ->and($reservation->participants)->toHaveCount(3);
});

it('flags the booker when they travel too', function () {
    $reservation = Reservation::factory()->create(['party_size' => 2]);
    ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0, 'is_booker' => true]);
    ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 1, 'is_booker' => false]);

    expect($reservation->bookerIsTraveling())->toBeTrue()
        ->and($reservation->bookerParticipant->position)->toBe(0);
});

it('reports which travelers still owe details', function () {
    $reservation = Reservation::factory()->create(['party_size' => 3]);
    ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 1]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 2]);

    expect($reservation->incompleteParticipants())->toHaveCount(2);
});
