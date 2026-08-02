<?php

use App\Livewire\Reservations\Show;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
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
    $owner = User::factory()->create(['role' => 'joiner']);
    $stranger = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $owner->id]);

    $this->actingAs($stranger)
        ->get("/reservations/{$reservation->id}/pay")
        ->assertForbidden();

    $guestBooking = Reservation::factory()->create(['joiner_id' => null]);

    $this->actingAs($stranger)
        ->get("/reservations/{$guestBooking->id}/pay")
        ->assertForbidden();
});

it('lets the joiner edit participant details', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $user->id, 'party_size' => 1]);
    $participant = ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id,
        'position' => 0,
        'first_name' => 'Ada',
        'last_name' => 'Reyes',
        'birthday' => '1998-04-02',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('editParticipant', $participant->id)
        ->set('edit_first_name', 'Maria')
        ->set('edit_last_name', 'Santos')
        ->call('updateParticipant');

    expect($participant->fresh()->first_name)->toBe('Maria')
        ->and($participant->fresh()->last_name)->toBe('Santos');
});

it('lets a guest edit participant details', function () {
    $reservation = Reservation::factory()->create(['joiner_id' => null, 'party_size' => 1]);
    $participant = ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id,
        'position' => 0,
        'first_name' => 'Ada',
        'last_name' => 'Reyes',
        'birthday' => '1998-04-02',
    ]);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('editParticipant', $participant->id)
        ->set('edit_first_name', 'Maria')
        ->set('edit_last_name', 'Santos')
        ->call('updateParticipant');

    expect($participant->fresh()->first_name)->toBe('Maria')
        ->and($participant->fresh()->last_name)->toBe('Santos');
});

it('refuses an unrelated user from editing participant details', function () {
    $owner = User::factory()->create(['role' => 'joiner']);
    $stranger = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $owner->id, 'party_size' => 1]);
    $participant = ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id,
        'position' => 0,
        'first_name' => 'Ada',
        'last_name' => 'Reyes',
        'birthday' => '1998-04-02',
    ]);

    $this->actingAs($stranger);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('editParticipant', $participant->id)
        ->assertStatus(403);
});

it('validates required fields when updating a participant', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $user->id, 'party_size' => 1]);
    $participant = ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id,
        'position' => 0,
        'first_name' => 'Ada',
        'last_name' => 'Reyes',
        'birthday' => '1998-04-02',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('editParticipant', $participant->id)
        ->set('edit_first_name', '')
        ->set('edit_last_name', '')
        ->set('edit_birthday', '')
        ->call('updateParticipant')
        ->assertHasErrors(['edit_first_name', 'edit_last_name', 'edit_birthday']);
});

it('lets the joiner remove a participant', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $user->id, 'party_size' => 2]);
    $p1 = ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02']);
    $p2 = ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 1, 'first_name' => 'Ben', 'last_name' => 'Reyes', 'birthday' => '2000-01-01']);

    $this->actingAs($user);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('confirmRemove', $p2->id)
        ->call('removeParticipant', $p2->id);

    expect($reservation->fresh()->participants->count())->toBe(1)
        ->and($reservation->fresh()->party_size)->toBe(1)
        ->and(ReservationParticipant::find($p2->id))->toBeNull();
});

it('cancels the reservation when the last participant is removed', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $user->id, 'party_size' => 1]);
    $p1 = ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02']);

    $this->actingAs($user);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('confirmRemove', $p1->id)
        ->call('removeParticipant', $p1->id);

    expect($reservation->fresh()->status)->toBe('cancelled')
        ->and(ReservationParticipant::find($p1->id))->toBeNull();
});

it('refuses an unrelated user from removing a participant', function () {
    $owner = User::factory()->create(['role' => 'joiner']);
    $stranger = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $owner->id, 'party_size' => 2]);
    $p1 = ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0]);
    $p2 = ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 1]);

    $this->actingAs($stranger);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('removeParticipant', $p1->id)
        ->assertStatus(403);
});
