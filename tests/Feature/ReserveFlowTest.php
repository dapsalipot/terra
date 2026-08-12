<?php

use App\Livewire\Host\Roster;
use App\Livewire\Trips\Show;
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

    Livewire::test(Show::class, ['trip' => $departure->trip])
        ->call('reserve')
        ->assertRedirect(route('trips.join', $departure->trip));
});

it('a joiner is sent to registration and no seat is taken yet', function () {
    // The trip page no longer creates the booking: registration does, once it knows who is
    // travelling. Holding a seat before that would let an abandoned form block a real joiner.
    $departure = publishedTripWithDeparture();
    $joiner = User::factory()->create(['role' => 'joiner']);

    Livewire::actingAs($joiner)
        ->test(Show::class, ['trip' => $departure->trip])
        ->call('reserve')
        ->assertRedirect(route('trips.join', $departure->trip));

    expect(Reservation::count())->toBe(0);
});

it('a host cannot reserve a trip', function () {
    $departure = publishedTripWithDeparture();
    $host = User::factory()->create(['role' => 'host']);

    Livewire::actingAs($host)
        ->test(Show::class, ['trip' => $departure->trip])
        ->call('reserve')
        ->assertSet('error', fn ($e) => str_contains($e, 'cannot join'));

    expect(Reservation::count())->toBe(0);
});

it('host grants the seat, joiner pays, host verifies it', function () {
    $departure = publishedTripWithDeparture();
    $joiner = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => $joiner->id, 'status' => 'pending',
    ]);

    // The host grants the seat first. Nothing can be paid before this.
    Livewire::actingAs($departure->trip->host)
        ->test(Roster::class, ['departure' => $departure])
        ->call('confirm', $reservation->id);
    expect($reservation->fresh()->status)->toBe('confirmed')
        ->and($reservation->fresh()->money()['paid'])->toBe(0.0);

    // Now the joiner has something to pay for.
    Livewire::actingAs($joiner)
        ->test(App\Livewire\Reservations\Show::class, ['token' => $reservation->public_token])
        ->set('reference_no', '9021 5567 3382')
        ->call('submit');
    // Sending money does not un-accept the seat, and does not credit itself either.
    expect($reservation->fresh()->status)->toBe('confirmed')
        ->and($reservation->fresh()->latestPayment->reference_no)->toBe('9021 5567 3382')
        ->and($reservation->fresh()->money()['paid'])->toBe(0.0);

    // The host looks at the proof and says the money is really there.
    Livewire::actingAs($departure->trip->host)
        ->test(Roster::class, ['departure' => $departure])
        ->call('verifyPayment', $reservation->id);
    expect($reservation->fresh()->money()['paid'])->toBeGreaterThan(0.0);
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
