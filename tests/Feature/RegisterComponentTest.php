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

it('pre-fills name parts from a legacy account that only has a display name', function () {
    // Every account predating structured name columns has only `name`. Without a fallback a
    // long-standing member would meet an emptier form than a first-time guest.
    $joiner = User::factory()->create([
        'role' => 'joiner', 'name' => 'Mika Ramos',
        'first_name' => null, 'last_name' => null,
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertSet('contact.first_name', 'Mika')
        ->assertSet('contact.last_name', 'Ramos');
});

it('rejects a birthday with an implausible year', function () {
    // `before:today` alone accepts the year 0514, which a date input can produce from a typo.
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('participants.0.birthday', '0514-09-12')
        ->call('submit')
        ->assertHasErrors(['participants.0.birthday']);
});

it('names fields in plain language when validation fails', function () {
    // Laravel would otherwise render "The contact.first name field is required."
    $component = Livewire::test(Register::class, ['trip' => $this->trip])->call('submit');

    $messages = collect($component->errors()->all())->implode(' ');

    expect($messages)->not->toContain('contact.')
        ->and($messages)->toContain('first name');
});

it('survives the seat field being cleared mid-edit', function () {
    // A `public int` cannot hold the "" Livewire sends when the box is emptied; PHP leaves the
    // typed property uninitialized and routes the next read to __get(), which threw a 500 for
    // the ordinary act of backspacing over a number.
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', '')
        ->assertOk()
        // The box stays empty rather than snapping to 1, which would rewrite it under the cursor.
        ->assertSet('partySize', '')
        // Totals still render, falling back to one seat rather than showing a price of zero.
        ->assertSee('3,500');
});

it('lets a two-digit seat count be typed a digit at a time', function () {
    // Snapping to a clamped value on every keystroke makes "12" impossible: clearing rewrites the
    // box to 1, so the next digit produces 11, not 12.
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', '')
        ->set('partySize', '1')
        ->set('partySize', '12')
        ->assertSet('partySize', 12)
        ->assertCount('participants', 12);
});

it('clamps a seat count beyond the allowed range', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', '99')
        ->assertSet('partySize', 20)
        ->set('partySize', '0')
        ->assertSet('partySize', 1);
});

it('refuses to submit with an empty seat field', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', '')
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->call('submit')
        ->assertHasErrors(['partySize'])
        ->assertNoRedirect();

    expect(Reservation::count())->toBe(0);
});
