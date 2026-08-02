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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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
    $booking = bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)],
        ['trip' => ['title' => 'Mount Pulag']]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('Pay for Mount Pulag')
        ->assertSee('Pay now')
        ->assertSeeHtml('dusk="attention-pay-'.$booking->id.'"');
});

it('asks for the traveller details the host needs for permits', function () {
    $user = joiner();
    $r = bookingFor($user, ['status' => 'confirmed', 'party_size' => 2]);
    $r->participants()->create(['position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02']);
    $r->participants()->create(['position' => 1]);   // blank

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertSee('1 of 2 travellers still need details')
        ->assertSeeHtml('dusk="attention-details-'.$r->id.'"');
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
    $r = bookingFor($user, ['status' => 'confirmed'], [
        'starts_at' => now()->subWeeks(2), 'status' => 'completed',
        'trip' => ['title' => 'Taal Sunrise'],
    ]);

    Livewire::actingAs($user)->test(Dashboard::class)
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
    $user = joiner();
    bookingFor($user, [], ['starts_at' => Carbon::parse('2026-09-04'), 'trip' => ['title' => 'September Trip']]);
    bookingFor($user, [], ['starts_at' => Carbon::parse('2026-10-12'), 'trip' => ['title' => 'October Trip']]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)
        ->set('view', 'grid')
        ->html();

    expect($html)->toContain('September 2026')
        ->and($html)->toContain('October 2026')
        // The month heading precedes its bookings.
        ->and(strpos($html, 'September 2026'))->toBeLessThan(strpos($html, 'September Trip'));
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
    Http::swap(new Illuminate\Http\Client\Factory());
    Http::fake(['date.nager.at/*' => Http::response([
        ['date' => '2026-08-21', 'localName' => 'Ninoy Aquino Day', 'name' => 'Ninoy Aquino Day'],
    ], 200)]);

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

it('still renders the calendar when the holiday API is unreachable', function () {
    Http::swap(new Illuminate\Http\Client\Factory());
    Http::fake(fn () => throw new RuntimeException('offline'));

    Livewire::actingAs(joiner())->test(Dashboard::class)
        ->assertOk()
        ->assertSeeHtml('dusk="calendar"');
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
        ->assertSee('How was Taal Sunrise?')
        ->call('dismissAttention', 'review')
        ->assertDontSee('How was Taal Sunrise?');

    // A dismiss button that forgets on reload is a button that does nothing.
    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertDontSee('How was Taal Sunrise?');
});

it('refuses to dismiss an expiring hold', function () {
    // Hiding an unpaid hold is how someone loses the seat while believing it is held.
    $user = joiner();
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)],
        ['trip' => ['title' => 'Mount Pulag']]);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('dismissAttention', 'pay')
        ->assertSee('Pay for Mount Pulag');
});

it('offers no dismiss control on an urgent item', function () {
    // Substring-windowing the card proved fragile: the window collapsed to six characters and
    // the assertion passed regardless of the markup. Counting across a page that holds exactly
    // one kind of item is blunt but cannot lie.
    $user = joiner();
    bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->html();

    expect(substr_count($html, 'dismissAttention'))->toBe(0);
});

it('does offer a dismiss control on a soft nudge', function () {
    // The other half of the pair. Without this, the assertion above would also pass if the
    // dismiss control were deleted everywhere.
    $user = joiner();
    bookingFor($user, ['status' => 'confirmed'], [
        'starts_at' => now()->subWeeks(2), 'status' => 'completed',
    ]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->html();

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
