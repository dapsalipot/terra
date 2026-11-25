<?php

use App\Livewire\Joiner\Dashboard;
use App\Mail\MagicLinkMail;
use App\Models\Holiday;
use App\Models\MagicLink;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\MagicLinkService;
use App\Support\PlaceCategories;
use App\Support\TrailStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
    $booking = bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)],
        ['trip' => ['title' => 'Mount Pulag']]);

    // An unanswered request sits in the For-confirmation panel with its hold clock. It is not a
    // For-payment card any more: there is nothing to pay for until the host grants the seat.
    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'confirm')
        ->assertSee('Mount Pulag')
        ->assertSeeHtml('dusk="for-confirmation"')
        ->assertSeeHtml('dusk="confirm-row-'.$booking->id.'"')
        ->assertSee('held for another');
});

it('asks for the traveller details the host needs for permits', function () {
    $user = joiner();
    $r = bookingFor($user, ['status' => 'confirmed', 'party_size' => 2]);
    $r->participants()->create(['position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
        'emergency_contact_name' => 'Rosa Reyes', 'emergency_contact_phone' => '09171112222']);
    $r->participants()->create(['position' => 1]);   // blank

    // Traveller details ride in the For-payment panel: both are your move on a seat you hold.
    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'pay')
        ->assertSee('1 of 2 travellers still need details')
        ->assertSeeHtml('dusk="attention-details-'.$r->id.'"');
});

it('does not nag when every traveller is accounted for', function () {
    $user = joiner();
    $r = bookingFor($user, ['status' => 'confirmed', 'party_size' => 1]);
    $r->participants()->create(['position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
        'emergency_contact_name' => 'Rosa Reyes', 'emergency_contact_phone' => '09171112222']);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertDontSeeHtml('dusk="attention-details"');
});

it('invites a review once the trip has actually run', function () {
    $user = joiner();
    $r = bookingFor($user, ['status' => 'confirmed'], [
        'starts_at' => now()->subWeeks(2), 'status' => 'completed',
        'trip' => ['title' => 'Taal Sunrise'],
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'review')
        ->assertSee('How was Taal Sunrise?')
        ->assertSeeHtml('dusk="attention-review-'.$r->id.'"');
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

    /*
     * Asserted inside each section, not by where a title first appears on the page.
     *
     * The old version compared the first position of each title in the whole document. That
     * depended on the calendar grid, which prints a title for every trip in the month on screen:
     * when the past trip fell in the current month and the upcoming one fell in the next, the grid
     * printed the past title first and the test failed without anything being wrong. It also
     * claimed upcoming renders first in source order, which stopped being true on purpose when
     * the calendar moved above the side panels so a phone opens on it.
     *
     * What actually matters is which bucket each trip lands in.
     */
    // betweenFirst, not between: between() runs to the LAST closing tag in the page, which would
    // quietly stretch each "section" to the end of the document and make every not-contain
    // assertion depend on which section happens to come later in the source.
    $nextUp = Str::betweenFirst($html, 'dusk="next-up"', '</section>');
    $past = Str::betweenFirst($html, 'dusk="past"', '</section>');

    expect($nextUp)->toContain('Still Coming')
        ->and($nextUp)->not->toContain('Already Ran')
        ->and($past)->toContain('Already Ran')
        ->and($past)->not->toContain('Still Coming');
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
        ->call('setPanel', 'confirm')
        ->assertSee('held for another 5 hours')
        // diffForHumans() on its own yields "held until 5 hours from now".
        ->assertDontSee('from now');
});

it('rounds the remaining hold down rather than up', function () {
    // Understating how long a seat is held is safe; overstating it strands someone who waited.
    $user = joiner();
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(5)->subMinute()]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'confirm')
        ->assertSee('held for another 4 hours')
        ->assertDontSee('5 hours');
});

it('says a lapsed hold has lapsed rather than held until the past', function () {
    // The sweep that cancels expired holds runs on a schedule, so a hold can be past its time
    // and still listed.
    $user = joiner();
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->subHour()]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'confirm')
        ->assertSee('This hold has lapsed')
        ->assertDontSee('held for another');
});

// ─────────────────────────────────────────────────────────────── calendar

it('opens on the calendar', function () {
    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->assertSet('view', 'calendar')
        ->assertSeeHtml('dusk="calendar"');
});

it('puts a booking on its own day, with the trip photo as the cell', function () {
    $user = joiner();
    $r = bookingFor($user, [], [
        'starts_at' => now()->addMonth()->startOfMonth()->addDays(9),
        'trip' => ['title' => 'Pulag Sunrise'],
    ]);
    $day = $r->departure->starts_at;

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $day->format('Y-m'))
        ->assertSeeHtml('dusk="cal-day-'.$day->toDateString().'"')
        ->assertSee('Pulag Sunrise')
        ->assertSeeHtml($r->departure->trip->cover_url);
});

it('moves between months and back to today', function () {
    $user = joiner();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSet('month', now()->format('Y-m'))
        ->call('shiftMonth', 1)
        ->assertSet('month', now()->addMonthNoOverflow()->format('Y-m'))
        ->call('shiftMonth', -1)
        ->assertSet('month', now()->format('Y-m'))
        ->call('shiftMonth', 3)
        ->call('today')
        ->assertSet('month', now()->format('Y-m'));
});

it('shows the right month even when today is the 31st', function () {
    // Carbon fills unparsed fields from today, so a bare 'Y-m' parse of "2026-06" on a 31st
    // yields 31 June and overflows into July. The host calendar shipped exactly that bug.
    $this->travelTo(Carbon::parse('2026-07-31 09:00:00'));

    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->set('month', '2026-06')
        ->assertSee('June 2026')
        ->assertDontSee('July 2026');

    // February from a 31st is the harshest case: three days of overflow.
    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->set('month', '2026-02')
        ->assertSee('February 2026');
});

it('survives a malformed month in the URL', function () {
    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->set('month', 'not-a-month')
        ->assertSee(now()->format('F Y'));
});

it('counts only the trips that fall in the month on screen', function () {
    $user = joiner();
    $month = now()->addMonth()->startOfMonth();

    bookingFor($user, [], ['starts_at' => $month->copy()->addDays(3)]);
    bookingFor($user, [], ['starts_at' => $month->copy()->addDays(10)]);
    bookingFor($user, [], ['starts_at' => $month->copy()->addMonthNoOverflow()->addDays(2)]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $month->format('Y-m'))
        ->assertViewHas('monthCount', 2);
});

it('shows the calendar whatever the filter chip says', function () {
    // A month grid that hid trips because a filter was set would be lying about where you go.
    $user = joiner();
    $r = bookingFor($user, [], ['starts_at' => now()->addWeeks(2), 'trip' => ['title' => 'Still Listed']]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('filter', 'past')
        ->set('month', $r->departure->starts_at->format('Y-m'))
        ->assertSee('Still Listed');
});

it('rejects a made-up view rather than rendering nothing', function () {
    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->call('setView', 'hologram')
        ->assertSet('view', 'calendar');
});

it('groups the card view by month', function () {
    /*
     * Relative to today, never hardcoded.
     *
     * This used to name September and October 2026 outright, and rotted the day those months
     * became the past: a past booking is filtered out of the upcoming card view, so the headings
     * the test looks for stopped existing and it failed with no change to the code under test.
     * Starting from the first of next month also keeps both dates clear of "today" and of the
     * year boundary, which addMonthsNoOverflow handles.
     */
    $first = now()->startOfMonth()->addMonthsNoOverflow(1);
    $second = now()->startOfMonth()->addMonthsNoOverflow(2);

    $user = joiner();
    bookingFor($user, [], ['starts_at' => $first->copy()->addDays(3), 'trip' => ['title' => 'First Month Trip']]);
    bookingFor($user, [], ['starts_at' => $second->copy()->addDays(11), 'trip' => ['title' => 'Second Month Trip']]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)
        ->set('view', 'grid')
        ->html();

    // Measured from the first heading onwards. The right-hand column also names the next trip,
    // so the page's *first* mention of "First Month Trip" is no longer the grid's copy of it —
    // comparing raw first positions would be testing column order, not month grouping.
    $at = strpos($html, $first->format('F Y'));

    expect($html)->toContain($first->format('F Y'))
        ->and($html)->toContain($second->format('F Y'))
        // The first booking falls under the first heading and before the second one.
        ->and(strpos($html, 'First Month Trip', $at))->toBeLessThan(strpos($html, $second->format('F Y'), $at));
});

// ──────────────────────────────────────────── clickable cells & planning

it('sends a booked day straight to that booking', function () {
    $user = joiner();
    $r = bookingFor($user, [], ['starts_at' => now()->addMonth()->startOfMonth()->addDays(9)]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $r->departure->starts_at->format('Y-m'))
        ->assertSeeHtml(route('reservations.show', ['token' => $r->public_token]));
});

it('answers an empty day with what leaves that day', function () {
    $user = joiner();
    $free = now()->addMonth()->startOfMonth()->addDays(14);

    $trip = Trip::factory()->create(['status' => 'published', 'title' => 'Siargao Surf']);
    TripDeparture::factory()->create([
        'trip_id' => $trip->id, 'starts_at' => $free, 'capacity' => 8, 'status' => 'open',
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $free->format('Y-m'))
        ->call('selectDate', $free->toDateString())
        ->assertSee('Siargao Surf')
        ->assertSeeHtml('dusk="cal-free-panel"');
});

/*
 * The sheet must stay shut until someone asks for it.
 *
 * render() seeds selectedDate with the next booked day so the grid has something highlighted, so
 * a sheet keyed off the date alone would fly open on page load. dayOpen is the separate flag that
 * stops that, and this is the test that would catch it being collapsed back into one.
 */
it('does not open the day sheet on page load', function () {
    $user = joiner();
    $free = now()->addMonth()->startOfMonth()->addDays(14);

    $trip = Trip::factory()->create(['status' => 'published', 'title' => 'Siargao Surf']);
    TripDeparture::factory()->create([
        'trip_id' => $trip->id, 'starts_at' => $free, 'capacity' => 8, 'status' => 'open',
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $free->format('Y-m'))
        ->assertSet('dayOpen', false)
        ->assertDontSeeHtml('dusk="cal-free-panel"');
});

it('closes the day sheet on request', function () {
    $user = joiner();
    $free = now()->addMonth()->startOfMonth()->addDays(14);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $free->format('Y-m'))
        ->call('selectDate', $free->toDateString())
        ->assertSeeHtml('dusk="cal-free-panel"')
        ->call('closeDay')
        ->assertSet('dayOpen', false)
        ->assertDontSeeHtml('dusk="cal-free-panel"');
});

/*
 * Paging the month while the sheet is open would leave it describing a date that is no longer on
 * screen — the calendar behind it would have moved on without it.
 */
it('closes the day sheet when the month changes', function () {
    $user = joiner();
    $free = now()->addMonth()->startOfMonth()->addDays(14);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $free->format('Y-m'))
        ->call('selectDate', $free->toDateString())
        ->assertSet('dayOpen', true)
        ->call('shiftMonth', 1)
        ->assertSet('dayOpen', false);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('selectDate', $free->toDateString())
        ->call('today')
        ->assertSet('dayOpen', false);
});

it('keeps a free day selected instead of snapping back to a booked one', function () {
    // render() used to reset the selection whenever the chosen day held no booking, which made
    // picking a free square impossible — a free day is by definition absent from that lookup.
    $user = joiner();
    $month = now()->addMonth()->startOfMonth();
    bookingFor($user, [], ['starts_at' => $month->copy()->addDays(9)]);
    $free = $month->copy()->addDays(20)->toDateString();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $month->format('Y-m'))
        ->call('selectDate', $free)
        ->assertSet('selectedDate', $free);
});

it('does not offer a trip the joiner has already booked', function () {
    $user = joiner();
    $r = bookingFor($user, [], ['starts_at' => now()->addMonth()->startOfMonth()->addDays(9)]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $r->departure->starts_at->format('Y-m'))
        ->call('selectDate', $r->departure->starts_at->toDateString())
        // A booked day shows the booking, never the "what's on offer" panel.
        ->assertDontSeeHtml('dusk="cal-free-panel"');
});

it('leaves a full departure off the offers', function () {
    $user = joiner();
    $free = now()->addMonth()->startOfMonth()->addDays(14);

    $trip = Trip::factory()->create(['status' => 'published', 'title' => 'Sold Out Trip']);
    $dep = TripDeparture::factory()->create([
        'trip_id' => $trip->id, 'starts_at' => $free, 'capacity' => 2, 'status' => 'open',
    ]);
    Reservation::factory()->create([
        'trip_departure_id' => $dep->id, 'party_size' => 2, 'status' => 'confirmed',
        'joiner_id' => joiner()->id,
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $free->format('Y-m'))
        ->call('selectDate', $free->toDateString())
        ->assertDontSee('Sold Out Trip')
        ->assertSee('Nothing leaves on this day yet');
});

it('marks weekends and Philippine holidays on the grid', function () {
    // Seeded, not faked: holidays now come from our own table, so the dashboard makes no
    // HTTP call for them at all.
    Holiday::create(['date' => '2026-08-21', 'name' => 'Ninoy Aquino Day']);

    $page = Livewire::actingAs(joiner())->test(Dashboard::class)->set('month', '2026-08');

    $weeks = $page->viewData('weeks')->flatten(1);
    $sat15 = $weeks->firstWhere(fn ($d) => $d['date']->toDateString() === '2026-08-15');
    $mon17 = $weeks->firstWhere(fn ($d) => $d['date']->toDateString() === '2026-08-17');
    $hol = $weeks->firstWhere(fn ($d) => $d['date']->toDateString() === '2026-08-21');

    expect($sat15['isWeekend'])->toBeTrue()
        ->and($mon17['isWeekend'])->toBeFalse()
        ->and($hol['holiday'])->toBe('Ninoy Aquino Day');

    $page->assertSee('Ninoy Aquino Day');
});

it('still renders the calendar when no holidays have been imported', function () {
    // Previously this asserted the calendar survived an unreachable API. There is no API call
    // on this path any more, so the real question is whether an empty table breaks the grid.
    Http::preventStrayRequests();

    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->assertOk()
        ->assertSeeHtml('dusk="calendar"');

    Http::assertNothingSent();
});

it('starts each week on Sunday, with every date under its own column', function () {
    // The grid's start day and the column headings are set in two different files, so they can
    // drift apart silently — every date would then render one column off.
    $page = Livewire::actingAs(joiner())->test(Dashboard::class)->set('month', '2026-08');
    $weeks = $page->viewData('weeks');

    $headings = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    foreach ($weeks as $week) {
        expect($week)->toHaveCount(7);

        foreach ($week->values() as $column => $day) {
            expect($day['date']->format('D'))->toBe($headings[$column]);
        }
    }

    // First cell of the grid is a Sunday, last is a Saturday.
    expect($weeks->first()->first()['date']->dayOfWeek)->toBe(Carbon::SUNDAY)
        ->and($weeks->last()->last()['date']->dayOfWeek)->toBe(Carbon::SATURDAY);
});

it('keeps the weekend columns at the outer edges', function () {
    $weeks = Livewire::actingAs(joiner())->test(Dashboard::class)
        ->set('month', '2026-08')
        ->viewData('weeks');

    $first = $weeks->first()->values();

    // Sunday-first puts the weekend at column 0 and column 6, not 5 and 6.
    expect($first[0]['isWeekend'])->toBeTrue()
        ->and($first[6]['isWeekend'])->toBeTrue()
        ->and($first[1]['isWeekend'])->toBeFalse();
});

// ─────────────────────────────────────────────────── simplified controls

it('offers only upcoming and past', function () {
    // Four filters behind a funnel icon let a joiner hide every upcoming trip and never see why.
    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->call('setFilter', 'action')
        ->assertSet('filter', 'upcoming')
        ->call('setFilter', 'all')
        ->assertSet('filter', 'upcoming')
        ->call('setFilter', 'past')
        ->assertSet('filter', 'past');
});

it('has no sorting surface at all', function () {
    $component = Livewire::actingAs(joiner())->test(Dashboard::class);

    expect(method_exists($component->instance(), 'setSort'))->toBeFalse()
        ->and(property_exists($component->instance(), 'sort'))->toBeFalse()
        ->and(property_exists($component->instance(), 'showFilters'))->toBeFalse();
});

it('always orders bookings by date', function () {
    $user = joiner();
    bookingFor($user, [], ['starts_at' => now()->addWeeks(6), 'trip' => ['title' => 'Later Trip']]);
    bookingFor($user, [], ['starts_at' => now()->addWeeks(2), 'trip' => ['title' => 'Sooner Trip']]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->set('view', 'grid')->html();

    expect(strpos($html, 'Sooner Trip'))->toBeLessThan(strpos($html, 'Later Trip'));
});

it('shows the tabs in cards view and hides them in calendar', function () {
    // In calendar the month is the scope, so a filter tab there would contradict the grid,
    // which deliberately shows every booking regardless of filter.
    $user = joiner();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('view', 'grid')
        ->assertSeeHtml('dusk="tab-upcoming"');

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('view', 'calendar')
        ->assertDontSeeHtml('dusk="tab-upcoming"');
});

// ──────────────────────────────────────────────────────── #[Url] binding

it('picks up filter, view and month from the query string on load', function () {
    // #[Url] only works with `use Livewire\Attributes\Url;` imported. Setting the property
    // directly with ->set() would pass even without that import — this has to arrive via
    // an actual query string, the same way a shared link or a reload would deliver it.
    Livewire::actingAs(joiner())
        ->withQueryParams(['filter' => 'past', 'view' => 'grid', 'month' => '2026-03'])
        ->test(Dashboard::class)
        ->assertSet('filter', 'past')
        ->assertSet('view', 'grid')
        ->assertSet('month', '2026-03');
});

it('reflects a filter arriving from the query string in the visible tabs', function () {
    // The whole point of this task: the filter can no longer be applied invisibly. If it
    // arrives via a shared/reloaded URL, the Past tab must visibly show as selected rather
    // than silently hiding upcoming trips with no on-screen indicator.
    $html = Livewire::actingAs(joiner())
        ->withQueryParams(['filter' => 'past', 'view' => 'grid'])
        ->test(Dashboard::class)
        ->html();

    expect($html)->toMatch('/dusk="tab-past"\s+aria-pressed="true"/')
        ->and($html)->toMatch('/dusk="tab-upcoming"\s+aria-pressed="false"/');
});

it('normalises an invalid filter arriving from the query string, tabs included', function () {
    // #[Url] hydration writes the raw query value straight to the property, bypassing
    // setFilter() entirely — there is no enum type to coerce it. A stray ?filter=action left
    // over from before this task (or a hand-edited link) must not leave both tabs unselected,
    // which is the invisible filter state this whole task exists to remove. Asserting the tab
    // matters more than asserting the property: the property alone would already look safe.
    $html = Livewire::actingAs(joiner())
        ->withQueryParams(['filter' => 'action', 'view' => 'grid'])
        ->test(Dashboard::class)
        ->assertSet('filter', 'upcoming')
        ->html();

    expect($html)->toMatch('/dusk="tab-upcoming"\s+aria-pressed="true"/');
});

it('normalises an invalid view arriving from the query string, view control included', function () {
    // Same defect class as the filter, in the view switcher: #[Url] hydration bypasses
    // setView() too, and it has no enum type either. Content degrades safely into the list
    // branch, but every Calendar/Cards/List button would render aria-pressed="false" — none
    // highlighted — misrepresenting which view is active. Asserting the button, not just the
    // property, is what actually catches that: the property alone was already safe.
    $html = Livewire::actingAs(joiner())
        ->withQueryParams(['view' => 'xyz'])
        ->test(Dashboard::class)
        ->assertSet('view', 'calendar')
        ->html();

    expect($html)->toMatch('/dusk="view-calendar"\s+aria-pressed="true"/');
});

// ───────────────────────────────────────────────────────── dismissals

it('keeps a soft nudge dismissed across a re-render', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed'], [
        'starts_at' => now()->subWeeks(2), 'status' => 'completed',
        'trip' => ['title' => 'Taal Sunrise'],
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'review')
        ->assertSee('How was Taal Sunrise?')
        ->call('dismissAttention', 'review')
        ->assertDontSee('How was Taal Sunrise?');

    // A dismiss button that forgets on reload is a button that does nothing.
    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'review')
        ->assertDontSee('How was Taal Sunrise?');
});

it('refuses to dismiss an expiring hold', function () {
    // Hiding an unpaid hold is how someone loses the seat while believing it is held.
    $user = joiner();
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)],
        ['trip' => ['title' => 'Mount Pulag']]);

    // The request still shows, hold clock and all, however hard the browser asks to hide it.
    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'confirm')
        ->call('dismissAttention', 'pay')
        ->assertSeeHtml('dusk="for-confirmation"')
        ->assertSee('Mount Pulag');
});

it('offers no dismiss control on an urgent item', function () {
    // Substring-windowing the card proved fragile: the window collapsed to six characters and
    // the assertion passed regardless of the markup. Counting across a page that holds exactly
    // one kind of item is blunt but cannot lie.
    $user = joiner();
    // Urgency now comes from an unpaid confirmed seat against its deadline, not from a pending
    // hold — a request the host has not answered yet is not something the joiner can act on.
    bookingFor($user, [
        'status' => 'confirmed',
        'confirmed_at' => now()->subDays(4),
        'full_payment_due_at' => now()->subDay(),
    ]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->call('setPanel', 'pay')->html();

    expect(substr_count($html, 'dismissAttention'))->toBe(0);
});

it('does offer a dismiss control on a soft nudge', function () {
    // The other half of the pair. Without this, the assertion above would also pass if the
    // dismiss control were deleted everywhere.
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed'], [
        'starts_at' => now()->subWeeks(2), 'status' => 'completed',
    ]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->call('setPanel', 'review')->html();

    expect(substr_count($html, 'dismissAttention'))->toBeGreaterThan(0);
});

// ─────────────────────────────────────────────────────── shared markup

it('renders one booking card component in both card and list views', function () {
    $user = joiner();
    bookingFor($user, [], ['trip' => ['title' => 'Shared Card Trip']]);

    foreach (['grid', 'list'] as $view) {
        Livewire::actingAs($user)->test(Dashboard::class)
            ->set('view', $view)
            ->assertSee('Shared Card Trip')
            ->assertSeeHtml('dusk="booking-card"');
    }
});

it('keeps the empty state in one place', function () {
    // Counting rendered output alone cannot see this: the default calendar view renders only one
    // of the three copies, so the assertion passed while all three still existed in the source.
    // The duplication is the defect, so the source is what has to be asserted on.
    $blade = file_get_contents(resource_path('views/livewire/joiner/dashboard.blade.php'));

    expect(substr_count($blade, 'No trips booked yet'))->toBe(0)
        ->and(file_exists(resource_path('views/components/no-trips.blade.php')))->toBeTrue();
});

it('shows exactly one empty state in each view', function () {
    $user = joiner();

    foreach (['calendar', 'grid', 'list'] as $view) {
        $html = Livewire::actingAs($user)->test(Dashboard::class)->set('view', $view)->html();

        expect(substr_count($html, 'No trips booked yet'))->toBe(1);
    }
});

// ────────────────────────────────────── availability indicator on the grid

it('counts joinable trips on each free day', function () {
    $user = joiner();
    $day = now()->addMonth()->startOfMonth()->addDays(14);

    foreach (['Surf Trip', 'Ridge Hike'] as $title) {
        $trip = Trip::factory()->create(['status' => 'published', 'title' => $title]);
        TripDeparture::factory()->create([
            'trip_id' => $trip->id, 'starts_at' => $day, 'capacity' => 8, 'status' => 'open',
        ]);
    }

    $weeks = Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $day->format('Y-m'))
        ->viewData('weeks')->flatten(1);

    $cell = $weeks->firstWhere(fn ($d) => $d['date']->toDateString() === $day->toDateString());

    expect($cell['available'])->toBe(2);
});

it('does not count a trip the joiner has already booked', function () {
    $user = joiner();
    $r = bookingFor($user, [], ['starts_at' => now()->addMonth()->startOfMonth()->addDays(9)]);
    $day = $r->departure->starts_at;

    $weeks = Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $day->format('Y-m'))
        ->viewData('weeks')->flatten(1);

    $cell = $weeks->firstWhere(fn ($d) => $d['date']->toDateString() === $day->toDateString());

    expect($cell['available'])->toBe(0);
});

it('does not count a sold-out departure', function () {
    $user = joiner();
    $day = now()->addMonth()->startOfMonth()->addDays(14);

    $trip = Trip::factory()->create(['status' => 'published', 'title' => 'Sold Out']);
    $dep = TripDeparture::factory()->create([
        'trip_id' => $trip->id, 'starts_at' => $day, 'capacity' => 2, 'status' => 'open',
    ]);
    Reservation::factory()->create([
        'trip_departure_id' => $dep->id, 'party_size' => 2, 'status' => 'confirmed',
        'joiner_id' => joiner()->id,
    ]);

    $weeks = Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $day->format('Y-m'))
        ->viewData('weeks')->flatten(1);

    $cell = $weeks->firstWhere(fn ($d) => $d['date']->toDateString() === $day->toDateString());

    expect($cell['available'])->toBe(0);
});

it('shows the count on the day square and in its accessible name', function () {
    $user = joiner();
    $day = now()->addMonth()->startOfMonth()->addDays(14);

    $trip = Trip::factory()->create(['status' => 'published']);
    TripDeparture::factory()->create([
        'trip_id' => $trip->id, 'starts_at' => $day, 'capacity' => 8, 'status' => 'open',
    ]);

    // The badge is aria-hidden, so the count has to reach a screen reader through the label.
    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $day->format('Y-m'))
        ->assertSeeHtml('1 trip leaving this day');
});

it('says so when nothing leaves that day', function () {
    $user = joiner();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSeeHtml('no trips leaving this day');
});

/*
 * ── Trip type badges ─────────────────────────────────────────────────────────────────────
 */

it('names the single kind of trip leaving on a day', function () {
    $user = joiner();
    $free = now()->addMonth()->startOfMonth()->addDays(12);
    $place = Place::factory()->create(['category' => 'hike']);

    $trip = Trip::factory()->create(['status' => 'published', 'place_id' => $place->id]);
    TripDeparture::factory()->create([
        'trip_id' => $trip->id, 'starts_at' => $free, 'capacity' => 8, 'status' => 'open',
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $free->format('Y-m'))
        // Spelled out, because one word fits and a word cannot be misread.
        ->assertSee('Hike')
        ->assertSee('leaving this day — Hike');
});

/*
 * Catalog order, not creation order: the same mix of trips has to draw the same row of badges
 * every time, or the calendar reshuffles itself between renders for no reason a user can see.
 */
it('lists mixed trip kinds in catalog order', function () {
    $user = joiner();
    $free = now()->addMonth()->startOfMonth()->addDays(12);

    // created surf-first, but 'hike' comes first in the catalog
    foreach (['surf', 'hike'] as $category) {
        $place = Place::factory()->create(['category' => $category]);
        $trip = Trip::factory()->create(['status' => 'published', 'place_id' => $place->id]);
        TripDeparture::factory()->create([
            'trip_id' => $trip->id, 'starts_at' => $free, 'capacity' => 8, 'status' => 'open',
        ]);
    }

    Livewire::actingAs($user)->test(Dashboard::class)
        ->set('month', $free->format('Y-m'))
        ->assertSee('leaving this day — Hike, Surf');
});

it('does not repeat a badge when several trips share a kind', function () {
    $place = Place::factory()->create(['category' => 'island']);

    expect(PlaceCategories::resolve(['island', 'island', 'island']))
        ->toHaveCount(1);
});

it('draws no badge for a category the catalog does not know', function () {
    expect(PlaceCategories::resolve(['spelunking', null]))->toBe([])
        ->and(PlaceCategories::label('spelunking'))->toBeNull();
});

// ──────────────────────────────────────── next-up panel & what to bring

it('leads the right column with the soonest trip and its packing list', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed'], [
        'starts_at' => now()->addWeeks(1),
        'trip' => ['title' => 'Soonest Trip', 'packing_items' => ['headlamp', 'water']],
    ]);
    bookingFor($user, ['status' => 'confirmed'], [
        'starts_at' => now()->addWeeks(6),
        'trip' => ['title' => 'Later Trip'],
    ]);

    $page = Livewire::actingAs($user)->test(Dashboard::class)->call('setPanel', 'next');

    $page->assertSeeHtml('dusk="next-up"')
        ->assertSeeHtml('dusk="what-to-bring"')
        ->assertSee('Soonest Trip')
        ->assertSee('Headlamp')
        ->assertSee('Water')
        // The rest of the diary sits underneath, not competing with it.
        ->assertSeeHtml('dusk="later-trips"')
        ->assertSee('Later Trip');

    expect($page->viewData('nextUp')->departure->trip->title)->toBe('Soonest Trip');
});

it('says so plainly when the host has listed nothing to bring', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed'], ['trip' => ['packing_items' => null]]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'next')
        ->assertSeeHtml('dusk="what-to-bring"')
        ->assertSee('has not listed a packing list');
});

it('hides the next-up panel when nothing is booked', function () {
    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->assertDontSeeHtml('dusk="next-up"')
        ->assertDontSeeHtml('dusk="awaiting-payment"');
});

it('lists what still needs paying with the amount due', function () {
    // Owing money requires a granted seat: a confirmed booking with nothing verified against it.
    // A pending one owes nothing yet, which is why it is no longer in this list.
    $user = joiner();
    $unpaid = bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()],
        ['trip' => ['title' => 'Unpaid Trip', 'price' => 2500]]);
    $settled = bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now(), 'fully_paid_at' => now()],
        ['trip' => ['title' => 'Settled Trip']]);

    $page = Livewire::actingAs($user)->test(Dashboard::class)->call('setPanel', 'pay');

    $page->assertSeeHtml('dusk="payment-'.$unpaid->id.'"')
        ->assertSee('Pay now')
        ->assertSee(number_format($unpaid->money()['due']))
        ->assertDontSeeHtml('dusk="payment-'.$settled->id.'"');

    expect($page->viewData('awaitingPayment')->pluck('id')->all())->toBe([$unpaid->id]);
});

it('shows a submitted payment as waiting rather than as still owing', function () {
    // Proof now arrives on a confirmed seat and leaves the status alone, so "waiting" is a fact
    // about the submission rather than about the booking's status.
    $user = joiner();
    $r = bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()], ['trip' => ['title' => 'Sent Trip']]);
    $r->paymentSubmissions()->create(['reference_no' => 'GC-1', 'submitted_at' => now()]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('setPanel', 'pay')
        ->assertSeeHtml('dusk="payment-'.$r->id.'"')
        ->assertSee('Proof sent')
        ->assertSee('See booking');
});

it('prompts for payment once, not twice', function () {
    // The old "Needs you" pay nudge and the For-payment card are the same task.
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()],
        ['trip' => ['title' => 'Mount Pulag']]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->call('setPanel', 'pay')->html();

    expect(substr_count($html, 'Pay now'))->toBe(1)
        ->and($html)->not->toContain('dusk="attention-pay-');
});

// ─────────────────────────────────────────────── the right column, as tabs

it('offers only the panels that have something in them', function () {
    // One upcoming trip, nothing owed, nothing to review. Four tabs here would be three dead
    // ends — an empty tab is a promise of content that is not there.
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now(), 'fully_paid_at' => now()]);

    $page = Livewire::actingAs($user)->test(Dashboard::class);

    expect($page->viewData('panels')->keys()->all())->toBe(['next']);
    $page->assertSeeHtml('dusk="tab-next"')
        ->assertDontSeeHtml('dusk="tab-pay"')
        ->assertDontSeeHtml('dusk="tab-review"');
});

it('separates what you owe from what you are waiting on', function () {
    $user = joiner();
    $owed = bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()],
        ['trip' => ['title' => 'Owes Money']]);
    $waiting = bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)],
        ['trip' => ['title' => 'Awaiting Host']]);

    $page = Livewire::actingAs($user)->test(Dashboard::class);

    /*
     * The distinction the reordered lifecycle created. Before it, both of these were "for
     * payment" — which after the change meant listing a booking with no payment form under a
     * heading that promised one.
     */
    expect($page->viewData('awaitingPayment')->pluck('id')->all())->toBe([$owed->id])
        ->and($page->viewData('forConfirmation')->pluck('id')->all())->toBe([$waiting->id]);
});

it('shows one panel at a time, not all of them stacked', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()],
        ['starts_at' => now()->addWeek(), 'trip' => ['title' => 'Owes Money']]);
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)],
        ['starts_at' => now()->addWeeks(2), 'trip' => ['title' => 'Awaiting Host']]);

    $page = Livewire::actingAs($user)->test(Dashboard::class)->call('setPanel', 'pay');

    $page->assertSeeHtml('dusk="awaiting-payment"')
        ->assertDontSeeHtml('dusk="for-confirmation"');

    $page->call('setPanel', 'confirm')
        ->assertSeeHtml('dusk="for-confirmation"')
        ->assertDontSeeHtml('dusk="awaiting-payment"');
});

it('falls back to a panel that exists when the remembered one empties out', function () {
    // You dismiss the last review while sitting on the review tab. Rendering an empty panel and
    // an unselected strip is the state this guards against.
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now(), 'fully_paid_at' => now()]);

    $page = Livewire::actingAs($user)->test(Dashboard::class)->call('setPanel', 'review');

    expect($page->viewData('activePanel'))->toBe('next');
});

it('refuses a panel name that is not one of the four', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()]);

    $page = Livewire::actingAs($user)->test(Dashboard::class)->call('setPanel', 'javascript:alert(1)');

    expect($page->get('panel'))->toBe('');
});

it('marks exactly one tab selected for assistive tech', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()],
        ['starts_at' => now()->addWeek()]);
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)],
        ['starts_at' => now()->addWeeks(2)]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->html();

    // A tablist where nothing is selected reads as a row of unrelated buttons, and one where
    // two are reads as broken.
    expect(substr_count($html, 'aria-selected="true"'))->toBe(1);
});

// ──────────────────────────────────── where you have been · month statistics

/** A completed booking whose trail carries real figures. */
function pastHike(User $user, array $hike, array $tripAttrs = [], ?string $when = null): Reservation
{
    $r = bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()], [
        'starts_at' => $when ? Carbon::parse($when) : now()->subMonth(),
        'status' => 'completed',
        'trip' => $tripAttrs,
    ]);

    $destination = $r->departure->trip->destinations()->create([
        'place_id' => Place::factory()->create()->id,
        'position' => 0,
    ]);
    $destination->hikeDetail()->create($hike);

    return $r->fresh();
}

it('adds up what a month of trips came to', function () {
    $user = joiner();
    pastHike($user, ['masl' => 2922, 'elevation_gain_m' => 1400, 'distance_km' => 14.5, 'duration_hours' => 9], [], '2026-06-10');
    pastHike($user, ['masl' => 1170, 'elevation_gain_m' => 800, 'distance_km' => 6.5, 'duration_hours' => 5], [], '2026-06-24');

    $months = Livewire::actingAs($user)->test(Dashboard::class)->viewData('pastByMonth');
    $june = $months['June 2026']['stats'];

    expect($june['trips'])->toBe(2)
        ->and($june['ascent'])->toBe(2200)
        ->and($june['distance'])->toBe(21.0)
        // The highest point stood on, never a sum: adding two summits gives a number that
        // corresponds to nowhere on earth.
        ->and($june['peak'])->toBe(2922);
});

it('keeps each month to its own figures', function () {
    $user = joiner();
    pastHike($user, ['elevation_gain_m' => 1000], [], '2026-06-10');
    pastHike($user, ['elevation_gain_m' => 400], [], '2026-05-04');

    $months = Livewire::actingAs($user)->test(Dashboard::class)->viewData('pastByMonth');

    expect($months['June 2026']['stats']['ascent'])->toBe(1000)
        ->and($months['May 2026']['stats']['ascent'])->toBe(400)
        // Newest first — a history read forwards buries this year under every year before it.
        ->and($months->keys()->first())->toBe('June 2026');
});

it('stays quiet about a figure nobody recorded', function () {
    // An island hop has no ascent. Rendering "0 m climbed" is worse than saying nothing: it
    // reads as a measurement rather than as an absence.
    $user = joiner();
    pastHike($user, ['distance_km' => 3.0], [], '2026-06-10');

    $stats = Livewire::actingAs($user)->test(Dashboard::class)->viewData('pastTotals');

    expect($stats['ascent'])->toBeNull()
        ->and($stats['distance'])->toBe(3.0)
        ->and(collect(TrailStats::tiles($stats))->pluck('label'))->not->toContain('Climbed');
});

it('counts the trip once however many seats were booked', function () {
    // Four friends on one departure climbed one mountain between them.
    $user = joiner();
    $r = pastHike($user, ['elevation_gain_m' => 1000], [], '2026-06-10');
    $r->update(['party_size' => 4]);

    $stats = Livewire::actingAs($user)->test(Dashboard::class)->viewData('pastTotals');

    expect($stats['ascent'])->toBe(1000);
});

it('gathers the inclusions across everything you have been on', function () {
    $user = joiner();
    pastHike($user, ['masl' => 1000], ['inclusions' => ['Transport', 'Guide']], '2026-06-10');
    pastHike($user, ['masl' => 900], ['inclusions' => ['Guide', 'Meals']], '2026-05-10');

    $stats = Livewire::actingAs($user)->test(Dashboard::class)->viewData('pastTotals');

    // Distinct: "Guide" twice is one thing you got, not two. Order is whatever order the trips
    // happened in and carries no meaning, so it is deliberately not asserted.
    expect($stats['inclusions']->sort()->values()->all())->toBe(['Guide', 'Meals', 'Transport']);
});

it('shows the month totals on the page, not just in the payload', function () {
    $user = joiner();
    pastHike($user, ['masl' => 2922, 'elevation_gain_m' => 1400, 'distance_km' => 14.5], [], '2026-06-10');

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSeeHtml('dusk="past-totals"')
        ->assertSeeHtml('dusk="past-month"')
        ->assertSee('June 2026')
        ->assertSee('1,400 m')
        ->assertSee('14.5 km');
});

it('puts the calendar before the side panels in the source order', function () {
    // On a phone there is no grid, so DOM order is reading order. Someone opening this on a
    // phone came to see when they are away — four panels of admin above the calendar makes
    // them scroll past the answer to reach it.
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->html();

    expect(strpos($html, 'dusk="calendar"'))->toBeLessThan(strpos($html, 'dusk="panel-tabs"'));
});

it('keeps the view switcher with the view it switches', function () {
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed', 'confirmed_at' => now()]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->html();

    // Directly above the calendar, not a column away in the page header.
    expect(strpos($html, 'dusk="view-calendar"'))->toBeLessThan(strpos($html, 'dusk="calendar"'));
});
