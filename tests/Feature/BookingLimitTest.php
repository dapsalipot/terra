<?php

use App\Livewire\Reservations\Register;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * A booking holds its seats for a day before anything is paid, so how many one source can make
 * has to be bounded. The throttle on the join route only covers loading the page: the booking is
 * a Livewire action, sent to Livewire's own update route.
 */

beforeEach(function () {
    $this->trip = Trip::factory()->published()->create();
    // Roomy, so that what stops a booking here is never the departure filling up.
    $this->departure = TripDeparture::factory()->create(['trip_id' => $this->trip->id, 'capacity' => 100]);
});

/** A join form filled in well enough to book. */
function filledJoinForm(Trip $trip, string $email, int $seats = 1): Testable
{
    $travellers = array_map(fn (int $seat) => array_merge(Register::BLANK_PARTICIPANT, [
        'first_name' => 'Guest',
        'last_name' => 'Number'.$seat,
        'email' => "guest{$seat}@example.com",
        'emergency_contact_name' => 'Rosa Reyes',
        'emergency_contact_phone' => '09171112222',
    ]), range(1, $seats));

    return Livewire::test(Register::class, ['trip' => $trip])
        ->set('partySize', $seats)
        ->set('contact', [
            'first_name' => 'Ada', 'middle_name' => '', 'last_name' => 'Reyes',
            'email' => $email, 'phone' => '09171234567',
        ])
        ->set('participants', $travellers)
        ->set('createAccount', false);
}

/** Every request from here on arrives from this address. */
function comingFrom(string $ip): void
{
    app()->rebinding('request', fn ($app, $request) => $request->server->set('REMOTE_ADDR', $ip));
}

it('stops one address after ten bookings, and keeps what was typed', function () {
    foreach (range(1, 10) as $n) {
        filledJoinForm($this->trip, "booker{$n}@example.com")->call('submit')->assertRedirect();
    }

    filledJoinForm($this->trip, 'booker11@example.com')
        ->call('review')
        ->call('submit')
        ->assertNoRedirect()
        ->assertSee('Nothing was booked')
        // In the review sheet, where the button was pressed: the sheet covers the rest of the page.
        ->assertSeeHtml('dusk="review-error"')
        ->assertSet('showReview', true)
        ->assertSet('contact.email', 'booker11@example.com')
        ->assertSet('participants.0.emergency_contact_name', 'Rosa Reyes');

    expect(Reservation::count())->toBe(10);

    // The limit belongs to the address that used it up, not to the departure.
    comingFrom('203.0.113.7');

    filledJoinForm($this->trip, 'neighbour@example.com')->call('submit')->assertRedirect();
});

it('stops one email after three bookings, from any address and in any case', function () {
    foreach (range(1, 3) as $n) {
        comingFrom("203.0.113.{$n}");
        filledJoinForm($this->trip, 'ada@example.com')->call('submit')->assertRedirect();
    }

    comingFrom('203.0.113.4');

    filledJoinForm($this->trip, 'ADA@Example.com')->call('submit')
        ->assertNoRedirect()
        ->assertSee('Nothing was booked');

    expect(Reservation::count())->toBe(3);
});

it('books a full group of twenty in one go', function () {
    filledJoinForm($this->trip, 'organiser@example.com', 20)->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Reservation::sole()->party_size)->toBe(20);
});

it('does not count attempts that book nothing', function () {
    // A form with a mistake in it, sent more often than either limit allows.
    $form = filledJoinForm($this->trip, 'ada@example.com')->set('contact.phone', '');

    foreach (range(1, 12) as $n) {
        $form->call('submit')->assertHasErrors('contact.phone');
    }

    // Seats that go while the form is open, also more often than the email limit allows.
    $form = filledJoinForm($this->trip, 'ada@example.com');
    $rival = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 100, 'status' => 'confirmed',
    ]);

    foreach (range(1, 4) as $n) {
        $form->call('submit')->assertNoRedirect()->assertDontSee('Nothing was booked');
    }

    $rival->delete();

    foreach (range(1, 3) as $n) {
        filledJoinForm($this->trip, 'ada@example.com')->call('submit')->assertRedirect();
    }
});

it('lets the same booker back in a day later', function () {
    foreach (range(1, 3) as $n) {
        filledJoinForm($this->trip, 'ada@example.com')->call('submit')->assertRedirect();
    }

    $this->travel(23)->hours();

    filledJoinForm($this->trip, 'ada@example.com')->call('submit')
        ->assertNoRedirect()
        ->assertSee('Try again in 1 hour');

    $this->travel(61)->minutes();

    filledJoinForm($this->trip, 'ada@example.com')->call('submit')->assertRedirect();
});

/*
 * Reading the count, booking, then counting would let a burst of requests sent together all find
 * room. So a booking is counted before it is made, which is what this looks at.
 */
it('counts a booking before making it', function () {
    $countedWhileBooking = null;

    Reservation::creating(function () use (&$countedWhileBooking) {
        $countedWhileBooking = RateLimiter::attempts('booking:email:'.sha1('ada@example.com'));
    });

    filledJoinForm($this->trip, 'ada@example.com')->call('submit')->assertRedirect();

    expect($countedWhileBooking)->toBe(1);
});
