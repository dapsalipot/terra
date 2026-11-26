<?php

use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\User;
use App\Support\Visibility;
use Database\Seeders\FakeDataSeeder;
use Database\Seeders\PlaceSeeder;

/**
 * A seeder is code that only ever runs by hand, which is exactly the code that rots quietly.
 * These run a small world and check the shape of what comes out.
 */
function seedSmallWorld(): void
{
    (new PlaceSeeder)->run();

    $seeder = new FakeDataSeeder;
    $seeder->hosts = 3;
    $seeder->joiners = 12;
    $seeder->tripsPerHost = [2, 2];
    $seeder->departuresPerTrip = [3, 3];
    $seeder->run();
}

it('creates hosts, joiners and an admin that can each reach their own area', function () {
    seedSmallWorld();

    expect(User::where('role', 'host')->count())->toBe(3)
        ->and(User::where('role', 'joiner')->count())->toBe(12)
        ->and(User::where('role', 'admin')->count())->toBe(1);

    // Every host is reachable at a handle: a host without one is invisible in the directory,
    // which filters on `handle is not null`.
    expect(User::where('role', 'host')->whereNull('handle')->count())->toBe(0);

    // And each has a profile, or their public page is a blank shell.
    User::where('role', 'host')->get()->each(
        fn (User $h) => expect($h->hostProfile)->not->toBeNull()
    );
});

it('puts a booking in every state the app can be in', function () {
    seedSmallWorld();

    $statuses = Reservation::query()->distinct()->pluck('status');

    // Not an exact set — the distribution is random — but the states that drive genuinely
    // different screens all have to be represented or the seed is not worth running.
    expect($statuses)->toContain('confirmed')
        ->and($statuses)->toContain('cancelled')
        ->and(Reservation::whereNotNull('fully_paid_at')->count())->toBeGreaterThan(0);
});

it('never leaves a live paid booking without a boarding pass', function () {
    seedSmallWorld();

    /*
     * The invariant the seeder exists to respect. markFullyPaid() mints a pass per traveller,
     * so a paid booking with a passless traveller means something wrote the row directly and
     * skipped the service — the pass page would then be blank for no visible reason.
     *
     * Cancelled bookings are excluded on purpose: cancelling revokes the pass but keeps
     * `fully_paid_at` as the record of money that came in and is now owed back.
     */
    $passless = Reservation::whereNotNull('fully_paid_at')
        ->where('status', '!=', 'cancelled')
        ->whereHas('participants', fn ($q) => $q->whereNull('pass_code'))
        ->count();

    expect($passless)->toBe(0);
});

it('snapshots a refund on every booking cancelled after money arrived', function () {
    seedSmallWorld();

    // cancel() reads what was paid before it moves the status. If that ordering were ever
    // reversed, money() would already read zero and these would all be null.
    $cancelledAfterPaying = Reservation::where('status', 'cancelled')->whereNotNull('fully_paid_at')->get();

    expect($cancelledAfterPaying)->not->toBeEmpty();
    $cancelledAfterPaying->each(fn (Reservation $r) => expect((float) $r->refund_due)->toBeGreaterThan(0));
});

it('gives every seat a traveller', function () {
    seedSmallWorld();

    // party_size is what capacity is measured in, so a booking with fewer travellers than seats
    // is a roster row that cannot be checked in.
    $short = Reservation::withCount('participants')
        ->get()
        ->filter(fn (Reservation $r) => $r->participants_count < $r->party_size);

    expect($short)->toBeEmpty();
});

it('produces trips a person would actually want to click', function () {
    seedSmallWorld();

    expect(Trip::where('status', 'published')->count())->toBe(6)
        ->and(Trip::whereNull('place_id')->count())->toBe(0);

    // Each trip gets one past departure so ratings and the wall are not empty everywhere.
    Trip::all()->each(
        fn (Trip $t) => expect($t->departures()->where('starts_at', '<', now())->count())->toBeGreaterThan(0)
    );
});

it('marks two of the first host\'s trips for the listing choices, and no one else\'s', function () {
    seedSmallWorld();

    // Without these a demo has no "Link only" or "Followers first" chip to look at, and nothing unlisted to click through to.
    $trips = User::where('role', 'host')->orderBy('id')->first()->trips()->orderBy('id')->get();

    expect($trips[0]->visibility)->toBe(Visibility::LINK)
        ->and($trips[1]->visibility)->toBe(Visibility::FOLLOWERS)
        ->and($trips[1]->public_at->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()))->toBeTrue()
        ->and(Trip::where('visibility', '!=', Visibility::PUBLIC)->count())->toBe(2);
});

it('checks in only people who were actually on a trip that already ran', function () {
    seedSmallWorld();

    $earlyCheckIns = ReservationParticipant::whereNotNull('checked_in_at')
        ->whereHas('reservation.departure', fn ($q) => $q->where('starts_at', '>', now()))
        ->count();

    expect($earlyCheckIns)->toBe(0);
});

it('adds to the world on a second run instead of colliding with the first', function () {
    seedSmallWorld();
    seedSmallWorld();

    /*
     * Accounts are numbered predictably so you can type host3@terra.test from memory, which
     * means a naive second run tries to create host1 again. The second run has to pick up the
     * count where the first left off.
     */
    expect(User::where('role', 'host')->count())->toBe(6)
        ->and(User::where('role', 'joiner')->count())->toBe(24);

    // The moderator is the exception: there is one of them, however many times this runs.
    expect(User::where('email', 'admin@terra.test')->count())->toBe(1);
});
