<?php

use App\Livewire\Host\Dashboard;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\DaySpan;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('lists every day an event touches, not just the first', function () {
    expect(DaySpan::dates(Carbon::parse('2026-08-24'), Carbon::parse('2026-08-26')))
        ->toBe(['2026-08-24', '2026-08-25', '2026-08-26']);
});

it('treats a missing end date as a single day', function () {
    expect(DaySpan::dates(Carbon::parse('2026-08-24'), null))->toBe(['2026-08-24']);
});

it('treats an end before its start as a single day rather than looping', function () {
    // Bad data must not be able to hang the calendar.
    expect(DaySpan::dates(Carbon::parse('2026-08-24'), Carbon::parse('2026-08-20')))
        ->toBe(['2026-08-24']);
});

it('clips to the window so a trip crossing a month boundary appears in both', function () {
    $start = Carbon::parse('2026-07-30');
    $end = Carbon::parse('2026-08-02');

    expect(DaySpan::dates($start, $end, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')))
        ->toBe(['2026-08-01', '2026-08-02'])
        ->and(DaySpan::dates($start, $end, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31')))
        ->toBe(['2026-07-30', '2026-07-31']);
});

it('returns nothing when the event misses the window entirely', function () {
    expect(DaySpan::dates(
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-03'),
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    ))->toBe([]);
});

it('caps a runaway span instead of building an unbounded list', function () {
    $dates = DaySpan::dates(Carbon::parse('2020-01-01'), Carbon::parse('2030-01-01'));

    expect($dates)->toHaveCount(DaySpan::MAX_DAYS);
});

it('numbers the days of a departure', function () {
    $departure = new TripDeparture([
        'starts_at' => '2026-08-24 06:00',
        'ends_at' => '2026-08-26 18:00',
    ]);

    expect($departure->spanDays())->toBe(3)
        ->and($departure->isMultiDay())->toBeTrue()
        ->and($departure->dayNumberOn('2026-08-24'))->toBe(1)
        ->and($departure->dayNumberOn('2026-08-26'))->toBe(3)
        ->and($departure->dayNumberOn('2026-08-27'))->toBe(0);   // outside the trip
});

it('counts a same-day departure as one day, never zero', function () {
    $departure = new TripDeparture(['starts_at' => '2026-08-24 06:00', 'ends_at' => null]);

    expect($departure->spanDays())->toBe(1)
        ->and($departure->isMultiDay())->toBeFalse();
});

function multiDayHost(): array
{
    $host = User::factory()->create(['role' => 'host', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Pulag Traverse']);

    $departure = TripDeparture::factory()->for($trip)->create([
        'capacity' => 10,
        'status' => 'open',
        'starts_at' => Carbon::now()->startOfMonth()->addDays(9)->setTime(6, 0),
        'ends_at' => Carbon::now()->startOfMonth()->addDays(11)->setTime(18, 0),
    ]);

    return [$host, $departure];
}

it('shows a multi-day departure on all three of its days in the host calendar', function () {
    [$host, $departure] = multiDayHost();

    $weeks = Livewire::actingAs($host)
        ->test(Dashboard::class)
        ->viewData('weeks');

    $days = collect($weeks)->flatten(1)
        ->filter(fn ($d) => $d['departures']->contains('id', $departure->id))
        ->map(fn ($d) => $d['date']->toDateString())
        ->values()
        ->all();

    expect($days)->toBe([
        $departure->starts_at->toDateString(),
        $departure->starts_at->copy()->addDay()->toDateString(),
        $departure->ends_at->toDateString(),
    ]);
});

it('still counts a multi-day departure once in the host month total', function () {
    [$host, $departure] = multiDayHost();

    $monthDepartures = Livewire::actingAs($host)
        ->test(Dashboard::class)
        ->viewData('monthDepartures');

    expect($monthDepartures)->toHaveCount(1);
});

it('shows a booking on every day of its trip in the joiner calendar', function () {
    [$host, $departure] = multiDayHost();
    $joiner = User::factory()->create(['role' => 'joiner']);

    Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => $joiner->id, 'status' => 'confirmed', 'party_size' => 1,
    ]);

    $component = Livewire::actingAs($joiner)->test(App\Livewire\Joiner\Dashboard::class);

    $booked = collect($component->viewData('weeks'))->flatten(1)
        ->filter(fn ($d) => $d['bookings']->isNotEmpty())
        ->map(fn ($d) => $d['date']->toDateString())
        ->values()
        ->all();

    expect($booked)->toHaveCount(3)
        ->and($booked[0])->toBe($departure->starts_at->toDateString())
        ->and($booked[2])->toBe($departure->ends_at->toDateString())
        // The header counts trips, not nights away.
        ->and($component->viewData('monthCount'))->toBe(1);
});

it('offers a multi-day trip on its middle days too', function () {
    [$host, $departure] = multiDayHost();
    $joiner = User::factory()->create(['role' => 'joiner']);

    $middle = $departure->starts_at->copy()->addDay()->toDateString();

    $available = Livewire::actingAs($joiner)
        ->test(App\Livewire\Joiner\Dashboard::class)
        ->call('selectDate', $middle)
        ->viewData('availableOnDate');

    expect($available->pluck('id')->all())->toContain($departure->id);
});

it('shows a trip that started before the grid and runs into it', function () {
    // The bug the overlapping scope exists for: filtering on starts_at alone hid the days a
    // host was away whenever the trip began in the previous month.
    $host = User::factory()->create(['role' => 'host']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);

    $departure = TripDeparture::factory()->for($trip)->create([
        'capacity' => 10,
        'status' => 'open',
        'starts_at' => Carbon::parse('2026-07-30 06:00'),
        'ends_at' => Carbon::parse('2026-08-02 18:00'),
    ]);

    $weeks = Livewire::actingAs($host)
        ->test(Dashboard::class, ['month' => '2026-08'])
        ->set('month', '2026-08')
        ->viewData('weeks');

    $inAugust = collect($weeks)->flatten(1)
        ->filter(fn ($d) => $d['inMonth'] && $d['departures']->contains('id', $departure->id))
        ->map(fn ($d) => $d['date']->toDateString())
        ->values()
        ->all();

    expect($inAugust)->toBe(['2026-08-01', '2026-08-02']);
});

it('says when a trip starts on the day sheet card', function () {
    [$host, $departure] = multiDayHost();
    $joiner = User::factory()->create(['role' => 'joiner']);

    $start = $departure->starts_at->toDateString();
    $middle = $departure->starts_at->copy()->addDay()->toDateString();

    // On its own departure day the card says it starts, and how long it runs.
    $onStart = Livewire::actingAs($joiner)->test(App\Livewire\Joiner\Dashboard::class)
        ->call('selectDate', $start)->html();

    expect($onStart)->toContain('Starts '.$departure->starts_at->format('D j M'))
        ->and($onStart)->toContain('3 days')
        ->and($onStart)->toContain('Trips leaving');

    // On a middle day it says when it actually left, and which day of the trip this is —
    // otherwise the sheet reads as though everything on it begins today.
    $onMiddle = Livewire::actingAs($joiner)->test(App\Livewire\Joiner\Dashboard::class)
        ->call('selectDate', $middle)->html();

    expect($onMiddle)->toContain('Started '.$departure->starts_at->format('D j M'))
        ->and($onMiddle)->toContain('day 2 of 3')
        // The eyebrow and footer stop claiming a departure that already happened. Scoped to the
        // sheet's own copy — a page-wide check could never pass, because the cell for the day it
        // really does leave says "leaving this day" quite correctly.
        ->and($onMiddle)->toContain('Trips available')
        ->and($onMiddle)->toContain('available this day.')
        ->and($onMiddle)->not->toContain('leaving this day.');
});
