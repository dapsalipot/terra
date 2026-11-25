<?php

use App\Livewire\Host\Roster;
use App\Mail\BookingMail;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Mail;

function notifiableBooking(array $overrides = []): Reservation
{
    $host = User::factory()->create(['role' => 'host', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Pulag hike', 'price' => 2000]);
    $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

    return Reservation::factory()->for($departure, 'departure')->create(array_merge([
        'joiner_id' => null, 'status' => 'pending', 'party_size' => 1,
        'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes',
        'contact_email' => 'ada@example.com',
    ], $overrides));
}

it('tells a joiner when the host confirms their seat', function () {
    Mail::fake();
    $r = notifiableBooking();

    app(ReservationService::class)->confirm($r, $r->departure->trip->host);

    Mail::assertSent(BookingMail::class, fn ($m) => $m->kind === 'confirmed'
        && $m->hasTo('ada@example.com'));
});

it('tells a joiner why a payment was rejected', function () {
    Mail::fake();
    $r = notifiableBooking(['status' => 'payment_submitted']);
    $r->paymentSubmissions()->create(['reference_no' => '9021', 'submitted_at' => now()]);

    app(ReservationService::class)->reject($r->fresh(), $r->departure->trip->host, 'Reference not found');

    Mail::assertSent(BookingMail::class, fn ($m) => $m->kind === 'rejected'
        && $m->reason === 'Reference not found');
});

it('does not email a joiner who cancelled a request the host had not confirmed', function () {
    // They are looking at the page that did it. An email would be telling them what they just did.
    Mail::fake();
    $r = notifiableBooking();

    app(ReservationService::class)->cancel($r, 'Cancelled by joiner');

    Mail::assertNothingSent();
});

it('does email a joiner who cancels a seat they were given, so the calendar can let go of it', function () {
    // The page says it is cancelled, but the confirmation put an event in their calendar and only a
    // cancellation delivered to their mailbox takes it out again.
    Mail::fake();
    $r = notifiableBooking(['status' => 'confirmed', 'confirmed_at' => now()]);

    app(ReservationService::class)->cancel($r, 'Cancelled by joiner');

    // No reason attached: "Cancelled by joiner" would be the joiner being told what they just did.
    Mail::assertSent(BookingMail::class, fn ($m) => $m->kind === 'cancelled'
        && $m->hasTo('ada@example.com')
        && $m->reason === null);
});

it('emails a joiner whose hold expired out from under them', function () {
    Mail::fake();
    $r = notifiableBooking(['expires_at' => now()->subHour()]);

    app(ReservationService::class)->expireStaleHolds();

    Mail::assertSent(BookingMail::class, fn ($m) => $m->kind === 'cancelled');
});

it('cancels a whole departure, releasing every live seat and telling everyone once', function () {
    Mail::fake();
    $r = notifiableBooking(['status' => 'confirmed']);
    $departure = $r->departure;

    $other = Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => null, 'status' => 'pending', 'party_size' => 2,
        'contact_email' => 'ben@example.com',
    ]);
    $alreadyGone = Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => null, 'status' => 'cancelled', 'contact_email' => 'old@example.com',
    ]);

    $released = app(ReservationService::class)->cancelDeparture($departure, 'Typhoon signal 3');

    expect($released)->toBe(2)
        ->and($departure->fresh()->status)->toBe('cancelled')
        ->and($r->fresh()->status)->toBe('cancelled')
        ->and($r->fresh()->cancel_reason)->toBe('Typhoon signal 3')
        ->and($other->fresh()->status)->toBe('cancelled');

    // Two live bookings, two emails — the already-cancelled one is not told twice.
    Mail::assertSent(BookingMail::class, 2);
    Mail::assertNotSent(BookingMail::class, fn ($m) => $m->hasTo('old@example.com'));
});

it('does not let a mail failure undo the decision it announces', function () {
    // SMTP being down must not roll a host's confirmation back out of the database.
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

    $r = notifiableBooking();
    app(ReservationService::class)->confirm($r, $r->departure->trip->host);

    expect($r->fresh()->status)->toBe('confirmed');
});

it('offers the host a way to reach the cancellation, and runs it', function () {
    Mail::fake();
    $r = notifiableBooking(['status' => 'confirmed']);
    $host = $r->departure->trip->host;

    Livewire\Livewire::actingAs($host)
        ->test(Roster::class, ['departure' => $r->departure])
        ->assertSeeHtml('dusk="cancel-departure"')
        ->set('cancelReason', 'Typhoon signal 3')
        ->call('cancelDeparture')
        ->assertHasNoErrors();

    expect($r->fresh()->status)->toBe('cancelled')
        ->and($r->departure->fresh()->status)->toBe('cancelled');
});

it('stops offering the cancellation once the departure is already cancelled', function () {
    $r = notifiableBooking(['status' => 'confirmed']);
    $r->departure->update(['status' => 'cancelled']);

    Livewire\Livewire::actingAs($r->departure->trip->host)
        ->test(Roster::class, ['departure' => $r->departure])
        ->assertDontSeeHtml('dusk="cancel-departure"');
});

it('warns a hold once, not every hour until it lapses', function () {
    Mail::fake();
    $soon = notifiableBooking(['expires_at' => now()->addHour()]);
    notifiableBooking(['expires_at' => now()->addDays(2)]);      // not close enough yet
    notifiableBooking(['expires_at' => now()->subHour()]);       // already gone, expiry handles it

    $service = app(ReservationService::class);

    expect($service->remindExpiringHolds())->toBe(1)
        ->and($soon->fresh()->expiry_reminded_at)->not->toBeNull()
        // a second pass finds nothing, because the stamp is the memory
        ->and($service->remindExpiringHolds())->toBe(0);

    Mail::assertSent(BookingMail::class, 1);
    Mail::assertSent(BookingMail::class, fn ($m) => $m->kind === 'expiring');
});

it('lets a host remove a booking and tells the joiner', function () {
    Mail::fake();
    $r = notifiableBooking(['status' => 'confirmed']);

    Livewire\Livewire::actingAs($r->departure->trip->host)
        ->test(Roster::class, ['departure' => $r->departure])
        ->call('openBooking', $r->id)
        ->assertSeeHtml('dusk="cancel-booking-'.$r->id.'"')
        ->call('cancelBooking', $r->id);

    expect($r->fresh()->status)->toBe('cancelled');
    // Unlike a joiner cancelling themselves, this one is done *to* them, so they hear about it.
    Mail::assertSent(BookingMail::class, fn ($m) => $m->kind === 'cancelled');
});

it('renders every booking email through the Terra shell', function () {
    /*
     * `<x-mail::layout>` is Laravel's own markdown-mail component, not ours — so every one of
     * these templates was silently rendering the framework's default wrapper instead of the
     * Terra one, and nothing said so until a test called render().
     */
    $r = notifiableBooking();

    foreach (['confirmed', 'rejected', 'expiring', 'departure_cancelled', 'cancelled'] as $kind) {
        $html = (new BookingMail($r, $kind, 'A reason'))->render();

        expect($html)->toContain('Terra')
            ->and($html)->toContain('you pay your host directly');
    }
});
