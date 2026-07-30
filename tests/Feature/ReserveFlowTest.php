<?php

use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Livewire\Livewire;

function publishedTripWithDeparture(): TripDeparture
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline']);
    HostProfile::factory()->for($host)->create([
        'currency' => 'PHP',
        'payment_methods' => [['label' => 'Bank transfer', 'detail' => '•••• 4821']],
    ]);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'price' => 180]);

    return TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);
}

it('a guest goes straight to registration without logging in', function () {
    // Was: guests were bounced to login. Joining without an account is now the point.
    $departure = publishedTripWithDeparture();

    Livewire::test(\App\Livewire\Trips\Show::class, ['trip' => $departure->trip])
        ->call('reserve')
        ->assertRedirect(route('trips.join', $departure->trip));
});

it('a joiner is sent to registration and no seat is taken yet', function () {
    // The trip page no longer creates the booking: registration does, once it knows who is
    // travelling. Holding a seat before that would let an abandoned form block a real joiner.
    $departure = publishedTripWithDeparture();
    $joiner = User::factory()->create(['role' => 'joiner']);

    Livewire::actingAs($joiner)
        ->test(\App\Livewire\Trips\Show::class, ['trip' => $departure->trip])
        ->call('reserve')
        ->assertRedirect(route('trips.join', $departure->trip));

    expect(Reservation::count())->toBe(0);
});

it('a host cannot reserve a trip', function () {
    $departure = publishedTripWithDeparture();
    $host = User::factory()->create(['role' => 'host']);

    Livewire::actingAs($host)
        ->test(\App\Livewire\Trips\Show::class, ['trip' => $departure->trip])
        ->call('reserve')
        ->assertSet('error', fn ($e) => str_contains($e, 'cannot join'));

    expect(Reservation::count())->toBe(0);
});

it('joiner submits payment then host confirms', function () {
    $departure = publishedTripWithDeparture();
    $joiner = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => $joiner->id, 'status' => 'pending',
    ]);

    // joiner pays
    Livewire::actingAs($joiner)
        ->test(\App\Livewire\Reservations\Show::class, ['token' => $reservation->public_token])
        ->set('reference_no', '9021 5567 3382')
        ->call('submit');
    expect($reservation->fresh()->status)->toBe('payment_submitted');

    // host confirms via roster
    Livewire::actingAs($departure->trip->host)
        ->test(\App\Livewire\Host\Roster::class, ['departure' => $departure])
        ->call('confirm', $reservation->id);
    expect($reservation->fresh()->status)->toBe('confirmed');
});

it('a joiner cannot open someone else\'s pay page', function () {
    $departure = publishedTripWithDeparture();
    $mine = Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => User::factory()->create(['role' => 'joiner'])->id,
    ]);
    $stranger = User::factory()->create(['role' => 'joiner']);

    // The legacy integer-id URL must not convert a guessed id into the booking's token.
    test()->actingAs($stranger)
        ->get("/reservations/{$mine->id}/pay")
        ->assertForbidden();
});
