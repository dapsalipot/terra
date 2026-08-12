<?php

use App\Livewire\Host\Dashboard;
use App\Livewire\Host\Roster;
use App\Livewire\Host\Trips\Create;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Pin the clock. These tests seed departures at now()+N days and assert they appear in the
 * current month's grid, which only holds when the month has room left — the grid renders whole
 * weeks, so late in a month now()+3 can fall past its final cell. Mid-month keeps the fixtures
 * meaningful; individual tests travel elsewhere when the date is the point.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-07-15 09:00:00'));
});

function dashHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'host'.Str::random(8)]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

/** Publish one schedule on a given date, with optional price settings. */
function schedule(User $host, string $date, array $set = [], string $title = 'Pulag Sunrise'): Trip
{
    $place = Place::factory()->create();

    $test = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', $title)
        ->set('price', '2000');

    foreach ($set as $key => $value) {
        $test->set($key, $value);
    }

    $test->call('next')->call('next')
        ->set('departures', [['starts_at' => $date, 'ends_at' => '', 'capacity' => 10]])
        ->call('next')
        ->call('publish');

    return Trip::where('title', $title)->latest('id')->first();
}

it('shows this month by default and moves between months', function () {
    $host = dashHost();

    Livewire::actingAs($host)->test(Dashboard::class)
        ->assertSet('month', now()->format('Y-m'))
        ->assertSee(now()->format('F Y'))
        ->call('shiftMonth', 1)
        ->assertSet('month', now()->addMonthNoOverflow()->format('Y-m'))
        ->assertSee(now()->addMonthNoOverflow()->format('F Y'))
        ->call('shiftMonth', -2)
        ->assertSee(now()->subMonthNoOverflow()->format('F Y'))
        ->call('today')
        ->assertSet('month', now()->format('Y-m'));
});

it('puts each schedule on its own day in the calendar', function () {
    $host = dashHost();
    $soon = now()->addDays(3);

    $trip = schedule($host, $soon->toDateString(), [], 'On The Grid');
    $departure = $trip->departures->sole();

    Livewire::actingAs($host)->test(Dashboard::class)
        ->assertSee('On The Grid')
        // the card links straight to that schedule's dashboard
        ->assertSee(route('host.roster', $departure), false);
});

it('puts only the viewed month in the calendar', function () {
    $host = dashHost();

    schedule($host, now()->addDays(2)->toDateString(), [], 'This Month');
    schedule($host, now()->addMonthsNoOverflow(3)->toDateString(), [], 'Far Future');

    // "Next up" is deliberately month-independent, so assert on the grid's own data
    Livewire::actingAs($host)->test(Dashboard::class)
        ->assertViewHas('monthDepartures', fn ($d) => $d->pluck('trip.title')->all() === ['This Month'])
        ->call('shiftMonth', 3)
        ->assertViewHas('monthDepartures', fn ($d) => $d->pluck('trip.title')->all() === ['Far Future']);
});

it('survives a malformed month in the URL', function () {
    $host = dashHost();

    Livewire::actingAs($host)->test(Dashboard::class, ['month' => 'not-a-month'])
        ->assertOk()
        ->assertSee(now()->format('F Y'));
});

it('never shows another host’s schedules', function () {
    $mine = dashHost();
    $theirs = dashHost();

    schedule($mine, now()->addDays(4)->toDateString(), [], 'Mine');
    schedule($theirs, now()->addDays(5)->toDateString(), [], 'Theirs');

    Livewire::actingAs($mine)->test(Dashboard::class)
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});

it('counts payments waiting on the host', function () {
    $host = dashHost();
    $trip = schedule($host, now()->addDays(6)->toDateString());
    $departure = $trip->departures->sole();

    Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 2, 'status' => 'payment_submitted']);
    Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 1, 'status' => 'awaiting_payment']);
    Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 3, 'status' => 'confirmed']);

    Livewire::actingAs($host)->test(Dashboard::class)
        ->assertViewHas('toConfirm', 1)
        ->assertViewHas('awaitingPayment', 1)
        ->assertViewHas('confirmedGuests', 3);
});

// ── the per-schedule dashboard ────────────────────────────────────────────────

/**
 * A booking whose payment the host has verified — the thing that credits money now that
 * confirming a seat no longer does.
 */
function withVerifiedPayment(Reservation $r, User $host): Reservation
{
    $r->paymentSubmissions()->create([
        'reference_no' => '9021', 'submitted_at' => now(),
        'confirmed_at' => now(), 'confirmed_by' => $host->id,
    ]);

    return $r->fresh();
}

it('adds up what is expected, collected and outstanding', function () {
    $host = dashHost();
    $trip = schedule($host, now()->addWeek()->toDateString());   // 2000 per person, pay in full
    $departure = $trip->departures->sole();

    withVerifiedPayment(Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 2, 'status' => 'confirmed']), $host);   // 4000 in
    Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 1, 'status' => 'payment_submitted']);  // 2000 expected, not yet verified

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $departure])
        ->assertViewHas('money', fn ($m) => $m['expected'] === 6000.0
            && $m['collected'] === 4000.0
            && $m['outstanding'] === 2000.0);
});

it('leaves a cancelled booking out of the money', function () {
    $host = dashHost();
    $trip = schedule($host, now()->addWeek()->toDateString());
    $departure = $trip->departures->sole();

    withVerifiedPayment(Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 2, 'status' => 'confirmed']), $host);
    Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 5, 'status' => 'cancelled']);

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $departure])
        ->assertViewHas('money', fn ($m) => $m['expected'] === 4000.0 && $m['outstanding'] === 0.0);
});

it('counts only the deposit as collected and flags the balance', function () {
    $host = dashHost();
    $trip = schedule($host, now()->addWeek()->toDateString(), ['deposit' => '500']);
    $departure = $trip->departures->sole();

    $r = withVerifiedPayment(Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 2, 'status' => 'confirmed']), $host);

    // 2 × 2000 expected, 2 × 500 taken up front, 3000 still owed on the day
    expect($r->fresh()->money())->toBe(['total' => 4000.0, 'paid' => 1000.0, 'due' => 3000.0])
        ->and($r->fresh()->owesBalance())->toBeTrue();

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $departure])
        ->assertViewHas('money', fn ($m) => $m['collected'] === 1000.0 && $m['outstanding'] === 3000.0)
        // Same guarantee, sharper wording: the chip now names the amount still owed rather
        // than saying "Balance due" and leaving the host to work it out.
        // The money column states the numbers; the chip used to narrate them in words.
        ->assertSee('3,000 due');
});

it('does not flag a balance when the trip is paid in full', function () {
    $host = dashHost();
    $trip = schedule($host, now()->addWeek()->toDateString());
    $departure = $trip->departures->sole();

    $r = withVerifiedPayment(Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 1, 'status' => 'confirmed']), $host);

    expect($r->fresh()->owesBalance())->toBeFalse();

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $departure])
        ->assertDontSee('Balance due');
});

it('prices the roster from the live tier, not the flat price', function () {
    $host = dashHost();
    $trip = schedule($host, now()->addMonths(2)->toDateString(), [
        'priceTiers' => [['label' => 'Early bird', 'amount' => '1500', 'starts_at' => '', 'ends_at' => now()->addWeek()->toDateString()]],
    ]);
    $departure = $trip->departures->sole();

    Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => User::factory()->create()->id,
        'party_size' => 2, 'status' => 'confirmed']);

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $departure])
        ->assertViewHas('money', fn ($m) => $m['expected'] === 3000.0);   // 2 × 1500, not 2 × 2000
});

it('shows who booked and how to reach them', function () {
    $host = dashHost();
    $trip = schedule($host, now()->addWeek()->toDateString());
    $departure = $trip->departures->sole();

    $joiner = User::factory()->create(['name' => 'Ana Cruz', 'email' => 'ana@example.test', 'phone' => '09171234567']);
    Reservation::create(['trip_departure_id' => $departure->id, 'joiner_id' => $joiner->id,
        'party_size' => 2, 'status' => 'confirmed']);

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $departure])
        ->call('openBooking', $departure->reservations()->value('id'))
        ->assertSee('Ana Cruz')
        ->assertSee('ana@example.test')
        ->assertSee('09171234567');
    // No "Booking" column any more: who pays and how to reach them sits under the traveller
    // it belongs to, rather than in a greyed column of its own.
});

it('refuses to open another host’s schedule dashboard', function () {
    $mine = dashHost();
    $theirs = dashHost();

    $trip = schedule($theirs, now()->addWeek()->toDateString(), [], 'Not Mine');

    test()->actingAs($mine)
        ->get(route('host.roster', $trip->departures->sole()))
        ->assertForbidden();
});

it('toggles between the calendar grid and a list', function () {
    $host = dashHost();
    $trip = schedule($host, now()->addDays(3)->toDateString(), [], 'Toggleable');
    $departure = $trip->departures->sole();

    $page = Livewire::actingAs($host)->test(Dashboard::class)
        ->assertSet('view', 'grid')
        ->assertViewHas('isList', false)
        // the grid renders its own card hook
        ->assertSee('cal-departure-'.$departure->id, false);

    $page->call('setView', 'list')
        ->assertSet('view', 'list')
        ->assertViewHas('isList', true)
        ->assertSee('list-departure-'.$departure->id, false)
        // the list says what the grid cell had no room for
        ->assertSee('left');

    $page->call('setView', 'grid')->assertSet('view', 'grid');
});

it('refuses a made-up view rather than rendering nothing', function () {
    $host = dashHost();

    Livewire::actingAs($host)->test(Dashboard::class)
        ->call('setView', 'spreadsheet')
        ->assertSet('view', 'grid');
});

it('keeps the chosen view in the URL', function () {
    $host = dashHost();

    // arriving with ?view=list must honour it without a click
    Livewire::actingAs($host)->test(Dashboard::class, ['view' => 'list'])
        ->assertSet('view', 'list')
        ->assertViewHas('isList', true);
});

it('lists the trips for the day picked on the phone calendar', function () {
    $host = dashHost();
    $day = now()->startOfMonth()->addDays(9);
    $other = now()->startOfMonth()->addDays(20);

    $a = schedule($host, $day->toDateString(), [], 'Picked Day');
    $b = schedule($host, $other->toDateString(), [], 'Other Day');

    Livewire::actingAs($host)->test(Dashboard::class)
        ->call('selectDate', $day->toDateString())
        ->assertSet('selectedDate', $day->toDateString())
        ->assertViewHas('selectedDepartures', fn ($d) => $d->pluck('trip.title')->all() === ['Picked Day'])
        ->call('selectDate', $other->toDateString())
        ->assertViewHas('selectedDepartures', fn ($d) => $d->pluck('trip.title')->all() === ['Other Day']);
});

it('keeps a day selected when it is tapped again', function () {
    $host = dashHost();
    $day = now()->startOfMonth()->addDays(9);
    schedule($host, $day->toDateString(), [], 'Sticky Day');

    Livewire::actingAs($host)->test(Dashboard::class)
        ->call('selectDate', $day->toDateString())
        ->call('selectDate', $day->toDateString())
        ->assertSet('selectedDate', $day->toDateString())
        ->assertViewHas('selectedDepartures', fn ($d) => $d->pluck('trip.title')->all() === ['Sticky Day']);
});

it('opens on the soonest day that has something on it', function () {
    $host = dashHost();
    $past = now()->startOfMonth();                       // earlier this month
    $soon = now()->addDays(2);

    // only pick dates that stay inside the current month
    if ($soon->month !== now()->month) {
        $soon = now()->endOfMonth();
    }

    schedule($host, $past->toDateString(), [], 'Already Ran');
    schedule($host, $soon->toDateString(), [], 'Coming Up');

    Livewire::actingAs($host)->test(Dashboard::class)
        ->assertViewHas('selectedDepartures', fn ($d) => $d->pluck('trip.title')->contains('Coming Up'));
});

it('drops a selection that is not in the month being viewed', function () {
    $host = dashHost();
    $day = now()->startOfMonth()->addDays(9);
    schedule($host, $day->toDateString());

    Livewire::actingAs($host)->test(Dashboard::class)
        ->call('selectDate', $day->toDateString())
        ->call('shiftMonth', 6)
        // the old date is not in this month, so it must not leak trips into the panel
        ->assertViewHas('selectedDepartures', fn ($d) => $d->isEmpty());
});

it('shows the right month even when today is the 31st', function () {
    // Carbon fills unparsed fields from today, so parsing "2026-06" on a 31st yields 31 June,
    // which overflows into July. Short months were silently unreachable a few days each month.
    $this->travelTo(Carbon::parse('2026-07-31 09:00:00'));

    $host = dashHost();

    Livewire::actingAs($host)->test(Dashboard::class)
        ->set('month', '2026-06')
        ->assertSee('June 2026')
        ->assertDontSee('July 2026');

    // February from a 31st is the harshest case: three days of overflow.
    Livewire::actingAs($host)->test(Dashboard::class)
        ->set('month', '2026-02')
        ->assertSee('February 2026');
});
