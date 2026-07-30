<?php

use App\Livewire\Reservations\Register;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->trip = Trip::factory()->create(['status' => 'published', 'price' => 3500]);
    $this->departure = TripDeparture::factory()->create([
        'trip_id' => $this->trip->id, 'capacity' => 10, 'status' => 'open',
    ]);
});

it('starts with one seat, one participant and the booker travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSet('partySize', 1)
        ->assertSet('bookerIsTraveling', true)
        ->assertCount('participants', 1);
});

it('grows and shrinks the participant list with the seat count', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', 3)
        ->assertCount('participants', 3)
        ->set('partySize', 2)
        ->assertCount('participants', 2);
});

it('mirrors the booker name into seat one while they are travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->assertSet('participants.0.first_name', 'Ada')
        ->assertSet('participants.0.last_name', 'Reyes');
});

it('clears an untouched mirrored seat when the booker stops travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('bookerIsTraveling', false)
        ->assertSet('participants.0.first_name', '')
        ->assertSet('participants.0.last_name', '');
});

it('preserves a seat the booker edited when they stop travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('participants.0.first_name', 'Ben')   // deliberately someone else
        ->set('bookerIsTraveling', false)
        ->assertSet('participants.0.first_name', 'Ben');
});

it('requires the booker contact block', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->call('submit')
        ->assertHasErrors([
            'contact.first_name' => 'required',
            'contact.last_name' => 'required',
            'contact.email' => 'required',
            'contact.phone' => 'required',
        ]);
});

it('registers a guest and sends them to their booking', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('participants.0.birthday', '1998-04-02')
        ->set('participants.0.gender', 'female')
        ->set('createAccount', false)
        ->call('submit')
        ->assertRedirect();

    $reservation = Reservation::first();

    expect($reservation->joiner_id)->toBeNull()
        ->and($reservation->contact_email)->toBe('ada@example.com')
        ->and($reservation->participants)->toHaveCount(1);
});

it('normalises a +63 mobile number to the 09 form', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '+63 917 123 4567')
        ->set('participants.0.birthday', '1998-04-02')
        ->set('createAccount', false)
        ->call('submit');

    expect(Reservation::first()->contact_phone)->toBe('09171234567');
});

it('turns a host away', function () {
    $host = User::factory()->create(['role' => 'host']);

    Livewire::actingAs($host)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertRedirect(route('trips.show', $this->trip));
});

it('pre-fills a signed-in joiner from their profile', function () {
    $joiner = User::factory()->create([
        'role' => 'joiner', 'first_name' => 'Ada', 'last_name' => 'Reyes',
        'email' => 'ada@example.com', 'phone' => '09171234567', 'birthday' => '1998-04-02',
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertSet('contact.first_name', 'Ada')
        ->assertSet('contact.email', 'ada@example.com')
        ->assertSet('participants.0.birthday', '1998-04-02');
});

it('refuses a departure that has already left', function () {
    $this->departure->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()]);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSet('closed', true)
        ->assertSee('no longer taking registrations');
});

it('refuses a cancelled departure', function () {
    $this->departure->update(['status' => 'cancelled']);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSet('closed', true);
});

it('sends a joiner who already booked to their existing booking', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);
    $existing = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id,
        'joiner_id' => $joiner->id,
        'status' => 'pending',
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertRedirect(route('reservations.show', ['token' => $existing->public_token]));
});

it('does not divert a joiner whose previous booking was cancelled', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);
    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id,
        'joiner_id' => $joiner->id,
        'status' => 'cancelled',
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertNoRedirect();
});

it('refuses to oversell and says how many seats are left', function () {
    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 9, 'status' => 'confirmed',
    ]);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', 5)
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('createAccount', false)
        ->call('submit')
        ->assertSet('closed', false)
        ->assertNoRedirect();

    expect(Reservation::where('contact_email', 'ada@example.com')->count())->toBe(0);
});
