<?php

use App\Livewire\Reservations\Register;
use App\Livewire\Reservations\Show;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

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

it('is complete only with a name, a birthday and someone to call', function () {
    $ec = ['emergency_contact_name' => 'Rosa Reyes', 'emergency_contact_phone' => '09171112222'];

    $complete = new ReservationParticipant(['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02'] + $ec);
    $noBirthday = new ReservationParticipant(['first_name' => 'Ada', 'last_name' => 'Reyes'] + $ec);
    $blank = new ReservationParticipant([]);
    // Half a contact is not a contact: a name with no number cannot be rung.
    $halfContact = new ReservationParticipant([
        'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
        'emergency_contact_name' => 'Rosa Reyes',
    ]);

    expect($complete->isComplete())->toBeTrue()
        ->and($halfContact->isComplete())->toBeFalse()
        ->and($noBirthday->isComplete())->toBeFalse()
        ->and($blank->isComplete())->toBeFalse();
});

it('measures minority against the departure date, not today', function () {
    // Turns 18 on 2026-09-01. Departure is after that, so they are NOT a minor on the trip.
    $participant = new ReservationParticipant(['birthday' => '2008-09-01']);

    expect($participant->isMinorAt(Carbon::parse('2026-10-01')))->toBeFalse()
        ->and($participant->isMinorAt(Carbon::parse('2026-08-01')))->toBeTrue();
});

it('treats a participant with no birthday as not a minor', function () {
    expect((new ReservationParticipant([]))->isMinorAt(Carbon::parse('2026-10-01')))->toBeFalse();
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

it('backfills a public token for every pre-existing row, past the first chunk', function () {
    $departure = TripDeparture::factory()->create(['capacity' => 5000]);

    // Insert straight to the table so the model's creating hook does not set tokens.
    $rows = collect(range(1, 1200))->map(fn ($i) => [
        'trip_departure_id' => $departure->id,
        'party_size' => 1,
        'status' => 'pending',
        'public_token' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ])->all();

    DB::table('reservations')->insert($rows);
    expect(DB::table('reservations')->whereNull('public_token')->count())->toBe(1200);

    // Same statement the migration runs.
    DB::table('reservations')->orderBy('id')->chunkById(1000, function ($chunk) {
        foreach ($chunk as $row) {
            DB::table('reservations')->where('id', $row->id)
                ->update(['public_token' => (string) Str::uuid()]);
        }
    });

    expect(DB::table('reservations')->whereNull('public_token')->count())->toBe(0)
        ->and(DB::table('reservations')->distinct()->count('public_token'))->toBe(1200);
});

// ─────────────────────────────────────────────────── emergency contact

it('will not take a booking without someone to call', function () {
    $trip = Trip::factory()->published()->create(['price' => 1000]);
    TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

    Livewire::test(Register::class, ['trip' => $trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->call('submit')
        // The one per-traveller field worth blocking a booking over: a trip up a mountain with
        // nobody to ring.
        ->assertHasErrors([
            'participants.0.emergency_contact_name' => 'required',
            'participants.0.emergency_contact_phone' => 'required',
        ]);

    expect(Reservation::count())->toBe(0);
});

it('carries the emergency contact all the way to the row', function () {
    // The form validated it and the service used to drop it on the floor — collected, then lost.
    $trip = Trip::factory()->published()->create(['price' => 1000]);
    TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

    Livewire::test(Register::class, ['trip' => $trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '0917 111 2222')
        ->set('createAccount', false)
        ->call('submit')
        ->assertHasNoErrors();

    $p = Reservation::first()->participants->first();

    expect($p->emergency_contact_name)->toBe('Rosa Reyes')
        // Normalised the same way every other mobile number on the booking is.
        ->and($p->emergency_contact_phone)->toBe('09171112222')
        ->and($p->hasEmergencyContact())->toBeTrue();
});

it('asks a legacy traveller for one through the edit modal', function () {
    // Every seat booked before this field existed has none. This is where that gets closed,
    // rather than discovered at a trailhead.
    $r = Reservation::factory()->create([
        'joiner_id' => null, 'status' => 'confirmed', 'confirmed_at' => now(),
    ]);
    $p = ReservationParticipant::factory()->for($r)->withoutEmergencyContact()->create([
        'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
    ]);

    expect($p->isComplete())->toBeFalse();

    Livewire::test(Show::class, ['token' => $r->public_token])
        ->call('editParticipant', $p->id)
        // Saving without one is refused here too, not just at registration — otherwise the
        // requirement is a thing new joiners carry and existing ones never have to.
        ->call('updateParticipant')
        ->assertHasErrors(['edit_emergency_contact_name' => 'required'])
        ->set('edit_emergency_contact_name', 'Rosa Reyes')
        ->set('edit_emergency_contact_phone', '09171112222')
        ->call('updateParticipant')
        ->assertHasNoErrors();

    expect($p->fresh()->hasEmergencyContact())->toBeTrue()
        ->and($p->fresh()->isComplete())->toBeTrue();
});
