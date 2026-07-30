<?php

use App\Livewire\Reservations\Show;
use App\Models\Reservation;
use App\Models\User;
use Livewire\Livewire;

it('lets a guest open their booking with the token and no login', function () {
    $reservation = Reservation::factory()->create([
        'joiner_id' => null,
        'contact_first_name' => 'Ada',
        'contact_last_name' => 'Reyes',
    ]);

    $this->get(route('reservations.show', ['token' => $reservation->public_token]))
        ->assertOk()
        ->assertSee('Ada Reyes');
});

it('404s on an unknown token rather than revealing anything', function () {
    $this->get(route('reservations.show', ['token' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

it('redirects the legacy pay URL to the token URL', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $user->id]);

    $this->actingAs($user)
        ->get("/reservations/{$reservation->id}/pay")
        ->assertRedirect(route('reservations.show', ['token' => $reservation->public_token]));
});

it('lets a guest submit payment details', function () {
    $reservation = Reservation::factory()->create(['joiner_id' => null, 'status' => 'pending']);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->set('reference_no', 'GC-12345')
        ->set('method', 'gcash')
        ->call('submit');

    expect($reservation->fresh()->status)->toBe('payment_submitted');
});

it('shows the booking link so a guest can get back without email', function () {
    $reservation = Reservation::factory()->create(['joiner_id' => null]);

    $this->get(route('reservations.show', ['token' => $reservation->public_token]))
        ->assertOk()
        ->assertSee(route('reservations.show', ['token' => $reservation->public_token]));
});

it('leads with the outstanding-details prompt when travellers are unnamed', function () {
    $reservation = Reservation::factory()->create(['joiner_id' => null, 'party_size' => 2]);
    $reservation->participants()->create(['position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02']);
    $reservation->participants()->create(['position' => 1]);

    $this->get(route('reservations.show', ['token' => $reservation->public_token]))
        ->assertOk()
        ->assertSee('1 of 2');
});

it('will not trade a guessable reservation id for the booking token', function () {
    // /r/{token} is authorized by possession of an unguessable value. The legacy integer-id URL
    // must therefore never hand that token to someone who merely counted upwards.
    $owner = User::factory()->create(['role' => 'joiner']);
    $stranger = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $owner->id]);

    $this->actingAs($stranger)
        ->get("/reservations/{$reservation->id}/pay")
        ->assertForbidden();

    // A guest booking (joiner_id null) must not match an unauthenticated null id either.
    $guestBooking = Reservation::factory()->create(['joiner_id' => null]);

    $this->actingAs($stranger)
        ->get("/reservations/{$guestBooking->id}/pay")
        ->assertForbidden();
});
