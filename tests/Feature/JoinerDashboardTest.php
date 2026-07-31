<?php

use App\Livewire\Joiner\Dashboard;
use App\Mail\MagicLinkMail;
use App\Models\MagicLink;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function joiner(array $attrs = []): User
{
    return User::factory()->create(array_merge(['role' => 'joiner'], $attrs));
}

function bookingFor(User $user, array $reservation = [], array $departure = []): Reservation
{
    $trip = Trip::factory()->create(array_merge(['status' => 'published'], $departure['trip'] ?? []));
    unset($departure['trip']);

    $dep = TripDeparture::factory()->create(array_merge([
        'trip_id' => $trip->id, 'capacity' => 10, 'starts_at' => now()->addWeeks(2),
    ], $departure));

    return Reservation::factory()->create(array_merge([
        'trip_departure_id' => $dep->id, 'joiner_id' => $user->id, 'status' => 'confirmed',
    ], $reservation));
}

// ─────────────────────────────────────────────────────────────── routing

it('sends a joiner to their trips and a host to the console', function () {
    $this->actingAs(joiner())->get('/dashboard')->assertRedirect(route('my.trips'));

    $this->actingAs(User::factory()->create(['role' => 'host']))
        ->get('/dashboard')->assertRedirect(route('host.dashboard'));
});

it('is not reachable without signing in', function () {
    $this->get(route('my.trips'))->assertRedirect(route('login'));
});

it('shows a joiner only their own bookings', function () {
    $mine = joiner();
    $theirs = joiner();

    bookingFor($mine, [], ['trip' => ['title' => 'My Trip']]);
    bookingFor($theirs, [], ['trip' => ['title' => 'Their Trip']]);

    Livewire::actingAs($mine)->test(Dashboard::class)
        ->assertSee('My Trip')
        ->assertDontSee('Their Trip');
});

// ───────────────────────────────────────────────────────── needs you

it('leads with an unpaid hold, because the seat is released when it expires', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)],
        ['trip' => ['title' => 'Mount Pulag']]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('Pay for Mount Pulag')
        ->assertSee('Pay now')
        ->assertSeeHtml('dusk="attention-pay"');
});

it('asks for the traveller details the host needs for permits', function () {
    $user = joiner();
    $r = bookingFor($user, ['status' => 'confirmed', 'party_size' => 2]);
    $r->participants()->create(['position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02']);
    $r->participants()->create(['position' => 1]);   // blank

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('1 of 2 travellers still need details')
        ->assertSeeHtml('dusk="attention-details"');
});

it('does not nag when every traveller is accounted for', function () {
    $user = joiner();
    $r = bookingFor($user, ['status' => 'confirmed', 'party_size' => 1]);
    $r->participants()->create(['position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02']);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertDontSeeHtml('dusk="attention-details"');
});

it('invites a review once the trip has actually run', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed'], [
        'starts_at' => now()->subWeeks(2), 'status' => 'completed',
        'trip' => ['title' => 'Taal Sunrise'],
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('How was Taal Sunrise?')
        ->assertSeeHtml('dusk="attention-review"');
});

it('stops asking once the review is written', function () {
    $user = joiner();
    $r = bookingFor($user, ['status' => 'confirmed'], [
        'starts_at' => now()->subWeeks(2), 'status' => 'completed',
    ]);

    Review::create([
        'joiner_id' => $user->id,
        'reviewable_type' => Trip::class,
        'reviewable_id' => $r->departure->trip->id,
        'rating' => 5,
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertDontSeeHtml('dusk="attention-review"');
});

it('does not invite a review for a trip that has not run', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed'], ['starts_at' => now()->addWeeks(2)]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertDontSeeHtml('dusk="attention-review"');
});

// ───────────────────────────────────────────────────── upcoming / past

it('splits trips by whether they have happened', function () {
    $user = joiner();
    bookingFor($user, [], ['starts_at' => now()->addWeeks(2), 'trip' => ['title' => 'Still Coming']]);
    bookingFor($user, [], ['starts_at' => now()->subWeeks(3), 'trip' => ['title' => 'Already Ran']]);

    $page = Livewire::actingAs($user)->test(Dashboard::class);

    $html = $page->html();
    $upcomingAt = strpos($html, 'Still Coming');
    $pastAt = strpos($html, 'Already Ran');

    expect($upcomingAt)->not->toBeFalse()
        ->and($pastAt)->not->toBeFalse()
        // Upcoming is the section a joiner came for, so it renders first.
        ->and($upcomingAt)->toBeLessThan($pastAt);
});

it('leaves cancelled bookings off entirely', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'cancelled'], ['trip' => ['title' => 'Called Off']]);

    Livewire::actingAs($user)->test(Dashboard::class)->assertDontSee('Called Off');
});

it('points a joiner with nothing booked at the trips they could join', function () {
    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->assertSee('No trips booked yet')
        ->assertSee('Explore trips');
});

// ──────────────────────────────────────────────── claiming guest bookings

it('emails a link to claim bookings made as a guest', function () {
    Mail::fake();
    $user = joiner(['email' => 'ada@example.com']);

    $guestBooking = bookingFor($user, ['joiner_id' => null, 'contact_email' => 'ada@example.com']);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('claimGuestBookings')
        ->assertSee('we have emailed a link');

    Mail::assertSent(MagicLinkMail::class);
    expect(MagicLink::where('purpose', 'attach')->count())->toBe(1)
        // Never attached on the button press alone — clicking the emailed link is the proof.
        ->and($guestBooking->fresh()->joiner_id)->toBeNull();
});

it('says the same thing when there is nothing to claim', function () {
    Mail::fake();

    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->call('claimGuestBookings')
        ->assertSee('we have emailed a link');

    // Identical wording either way, so this cannot be used to probe which addresses have bookings.
    Mail::assertNothingSent();
});

it('claims every guest booking on that address from one link', function () {
    $user = joiner(['email' => 'ada@example.com']);

    $first = bookingFor($user, ['joiner_id' => null, 'contact_email' => 'ada@example.com']);
    $second = bookingFor($user, ['joiner_id' => null, 'contact_email' => 'ada@example.com']);
    $other = bookingFor($user, ['joiner_id' => null, 'contact_email' => 'someone@example.com']);

    ['url' => $url] = app(MagicLinkService::class)->issue('attach', [
        'user_id' => $user->id,
        'email' => 'ada@example.com',
        'reservation_id' => $first->id,
    ]);

    $this->get($url);

    expect($first->fresh()->joiner_id)->toBe($user->id)
        ->and($second->fresh()->joiner_id)->toBe($user->id)
        // Someone else's address is untouched.
        ->and($other->fresh()->joiner_id)->toBeNull();
});

it('words the hold as time remaining, not a bare relative date', function () {
    $user = joiner();
    // A minute past the hour so Carbon's flooring lands on a whole 5, not 4h59m.
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(5)->addMinute()]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('held for another 5 hours')
        // diffForHumans() on its own yields "held until 5 hours from now".
        ->assertDontSee('from now');
});

it('rounds the remaining hold down rather than up', function () {
    // Understating how long a seat is held is safe; overstating it strands someone who waited.
    $user = joiner();
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(5)->subMinute()]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('held for another 4 hours')
        ->assertDontSee('5 hours');
});

it('says a lapsed hold has lapsed rather than held until the past', function () {
    // The sweep that cancels expired holds runs on a schedule, so a hold can be past its time
    // and still listed.
    $user = joiner();
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->subHour()]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('This hold has lapsed')
        ->assertDontSee('held for another');
});
