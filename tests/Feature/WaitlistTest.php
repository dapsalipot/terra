<?php

use App\Livewire\Reservations\Register;
use App\Mail\SeatOpenedMail;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\ReservationService;
use App\Services\WaitlistService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function fullDeparture(int $capacity = 1): TripDeparture
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Pulag hike', 'price' => 2000]);
    $departure = TripDeparture::factory()->for($trip)->create(['capacity' => $capacity, 'starts_at' => now()->addWeek()]);

    Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => null, 'status' => 'confirmed', 'party_size' => $capacity,
        'contact_email' => 'taken@example.com',
    ]);

    return $departure->fresh();
}

it('offers a queue instead of a dead end when a departure is full', function () {
    $departure = fullDeparture();

    Livewire::test(Register::class, ['trip' => $departure->trip])
        ->assertSet('soldOut', true)
        ->assertSee('This departure is full')
        ->assertSeeHtml('dusk="join-waitlist"')
        ->set('waitlistName', 'Ada Reyes')
        ->set('waitlistEmail', 'ada@example.com')
        ->call('joinWaitlist')
        ->assertHasNoErrors()
        ->assertSet('waitlisted', true)
        ->assertSee("You're on the list", false);

    expect(WaitlistEntry::where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('treats a second sign-up from the same address as the same person', function () {
    $departure = fullDeparture();
    $service = app(WaitlistService::class);

    $service->join($departure, ['name' => 'Ada', 'email' => 'ada@example.com']);
    $service->join($departure, ['name' => 'Ada Reyes', 'email' => 'ADA@example.com']);

    expect(WaitlistEntry::count())->toBe(1)
        ->and(WaitlistEntry::first()->name)->toBe('Ada Reyes');
});

it('emails the front of the queue when a seat is released', function () {
    Mail::fake();
    $departure = fullDeparture();
    $service = app(WaitlistService::class);

    $first = $service->join($departure, ['name' => 'First', 'email' => 'first@example.com']);
    $second = $service->join($departure, ['name' => 'Second', 'email' => 'second@example.com']);

    app(ReservationService::class)->cancel($departure->reservations()->first(), 'Cancelled by your host');

    // One seat came back, so one person is told — not the whole queue racing for it.
    Mail::assertSent(SeatOpenedMail::class, 1);
    Mail::assertSent(SeatOpenedMail::class, fn ($m) => $m->hasTo('first@example.com'));

    expect($first->fresh()->notified_at)->not->toBeNull()
        ->and($second->fresh()->notified_at)->toBeNull();
});

it('does not tell the same person twice as seats come and go', function () {
    Mail::fake();
    $departure = fullDeparture(2);
    $service = app(WaitlistService::class);
    $service->join($departure, ['name' => 'Ada', 'email' => 'ada@example.com']);

    $service->notifyAvailable($departure);   // still full, nothing to say
    Mail::assertNothingSent();

    app(ReservationService::class)->cancel($departure->reservations()->first(), 'Cancelled by your host');
    Mail::assertSent(SeatOpenedMail::class, 1);

    // another opening later must not re-alert someone who already had their chance
    expect($service->notifyAvailable($departure->fresh()))->toBe(0);
    Mail::assertSent(SeatOpenedMail::class, 1);
});

it('skips a party that will not fit the seats that freed', function () {
    Mail::fake();
    $departure = fullDeparture(3);
    $service = app(WaitlistService::class);

    $big = $service->join($departure, ['name' => 'Four of us', 'email' => 'four@example.com', 'party_size' => 4]);
    $solo = $service->join($departure, ['name' => 'Just me', 'email' => 'one@example.com', 'party_size' => 1]);

    // three seats come back; the party of four still does not fit
    app(ReservationService::class)->cancel($departure->reservations()->first(), 'Cancelled by your host');

    expect($big->fresh()->notified_at)->toBeNull()
        ->and($solo->fresh()->notified_at)->not->toBeNull();
});

it('says nothing when the departure itself was called off', function () {
    Mail::fake();
    $departure = fullDeparture();
    app(WaitlistService::class)->join($departure, ['name' => 'Ada', 'email' => 'ada@example.com']);

    app(ReservationService::class)->cancelDeparture($departure, 'Typhoon');

    // seats are technically free, but there is no trip to offer them on
    Mail::assertNotSent(SeatOpenedMail::class);
});
