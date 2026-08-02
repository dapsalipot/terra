# Joiner Dashboard Refinement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix eight measured defects in `/my-trips`, cut the feature surface back to what a joiner with a handful of bookings needs, and move Philippine holidays into a table we own.

**Architecture:** Three independent strands. The backend strand adds a missing eager load and stops computing a query nobody sees, guarded by a permanent query-budget test. The simplification strand deletes the hidden filter panel and all sorting — removing the silent-filter bug by removing the filter — and collapses six duplicated card templates into one Blade component. The holiday strand replaces a runtime HTTP call with a `holidays` table populated by a scheduled import command, so a Presidential Proclamation can be corrected by one manual row.

**Tech Stack:** Laravel 12, Livewire 3, Tailwind 3.4, Pest 3, Laravel Dusk, MySQL (dev) / SQLite (tests).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-02-joiner-dashboard-refinement-design.md`. Read it before Task 1.
- **Never run `git commit`, `git push`, `git merge`, or create branches.** This project's owner gates all git writes on an explicit request in that same message. Commit steps below are written for completeness; **do not execute them unless the user asks.**
- The working tree contains uncommitted work from another session in `app/Livewire/Joiner/Dashboard.php`, `resources/views/livewire/joiner/dashboard.blade.php`, `app/Livewire/Reservations/Show.php` and several test files. **Build on it; never revert or overwrite it.**
- Blade changes that introduce new Tailwind utility classes require `npm run build`. `php artisan view:clear` alone is not enough — the built CSS in `public/build` will not contain the new classes.
- Tests run on SQLite, dev runs MySQL. `tests/Pest.php` calls `Http::preventStrayRequests()` for all Feature tests; no test may reach the network.
- Run `php artisan test` and `php artisan dusk` from `/Users/danielsalipot/Herd/terra`.
- Baseline before this work: **370 Pest passing, 28 Dusk passing, SystemAuditTest reports 0 findings.** All three must hold at the end.

## File Structure

**Modified**
- `app/Livewire/Joiner/Dashboard.php` — eager load, `$recommended` guard, remove sort/filter surface, session-backed dismissals, comment fix
- `resources/views/livewire/joiner/dashboard.blade.php` — visible tabs, one card component, one empty state
- `app/Services/PhilippineHolidays.php` — reads the table, no HTTP
- `routes/console.php` — schedule the import
- `tests/Feature/JoinerDashboardTest.php` — adjust for removed filters/sorts
- `tests/Feature/PhilippineHolidaysTest.php` — rewrite against the table

**Created**
- `tests/Feature/DashboardQueryBudgetTest.php` — permanent N+1 guard
- `resources/views/components/booking-card.blade.php` — the one booking card
- `resources/views/components/no-trips.blade.php` — the one empty state
- `database/migrations/2026_08_02_100000_create_holidays_table.php`
- `app/Models/Holiday.php`
- `app/Console/Commands/ImportHolidays.php`
- `tests/Feature/ImportHolidaysTest.php`

---

### Task 1: Query budget, eager load, and the discarded query

**Files:**
- Create: `tests/Feature/DashboardQueryBudgetTest.php`
- Modify: `app/Livewire/Joiner/Dashboard.php`

**Interfaces:**
- Consumes: existing `Dashboard::render()`, `Reservation`, `Trip`, `TripDeparture` factories.
- Produces: no new public API. `render()` keeps the same view data keys; `$recommended` becomes an empty collection when the joiner has bookings.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DashboardQueryBudgetTest.php`:

```php
<?php

use App\Livewire\Joiner\Dashboard;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** A joiner with real bookings, each carrying travellers. */
function joinerWithBookings(int $count): User
{
    $user = User::factory()->create(['role' => 'joiner']);

    foreach (range(1, $count) as $i) {
        $trip = Trip::factory()->create(['status' => 'published']);
        $departure = TripDeparture::factory()->create([
            'trip_id' => $trip->id,
            'capacity' => 10,
            'starts_at' => now()->addWeeks($i),
        ]);
        $reservation = Reservation::factory()->create([
            'trip_departure_id' => $departure->id,
            'joiner_id' => $user->id,
            'status' => 'confirmed',
            'party_size' => 3,
        ]);
        foreach (range(0, 2) as $position) {
            $reservation->participants()->create([
                'position' => $position,
                'first_name' => 'Ada',
                'last_name' => 'Reyes',
                'birthday' => '1995-01-01',
            ]);
        }
    }

    return $user;
}

/**
 * Count the queries one render issues.
 *
 * The closure MUST use `use (&$queries)`. An arrow function binds by value, so `$queries[]`
 * would append to a copy and the count would come back zero — which looks like a passing
 * result rather than a broken probe.
 */
function queriesForRender(User $user): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    Livewire::actingAs($user)->test(Dashboard::class);

    return $queries;
}

it('renders within its query budget', function () {
    $queries = queriesForRender(joinerWithBookings(6));

    // Measured before this work: 18, of which 6 were a per-booking price-tier lookup.
    // The ceiling is 10 rather than 8 so an unrelated session or framework query does not
    // break the test, while still failing loudly if the eager load is dropped.
    expect(count($queries))->toBeLessThanOrEqual(10);
});

it('does not issue more queries as bookings grow', function () {
    // A ceiling alone can be satisfied by a fixed-size N+1. Only the comparison proves flatness.
    $few = count(queriesForRender(joinerWithBookings(3)));
    $many = count(queriesForRender(joinerWithBookings(12)));

    expect($many)->toBe($few);
});

it('never looks up price tiers one booking at a time', function () {
    $queries = queriesForRender(joinerWithBookings(6));

    $tierQueries = collect($queries)->filter(fn ($sql) => str_contains($sql, 'trip_price_tiers'));

    // One eager-loaded lookup is correct. Six is the N+1.
    expect($tierQueries->count())->toBeLessThanOrEqual(1);
});

it('does not build recommendations for a joiner who has bookings', function () {
    // The recommendations query is a four-join correlated subquery rendered only in the empty
    // state, so computing it for someone with bookings is pure waste.
    $queries = queriesForRender(joinerWithBookings(3));

    $recommendationQueries = collect($queries)
        ->filter(fn ($sql) => str_contains($sql, 'trip_destinations'));

    expect($recommendationQueries)->toBeEmpty();
});

it('still recommends trips to a joiner with nothing booked', function () {
    $user = User::factory()->create(['role' => 'joiner']);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertViewHas('recommended');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DashboardQueryBudgetTest`
Expected: FAIL. The budget test reports roughly 18 queries against a ceiling of 10, and the price-tier test reports 6 against a ceiling of 1.

- [ ] **Step 3: Add the missing eager load**

In `app/Livewire/Joiner/Dashboard.php`, find the reservations query in `render()` and extend its `with()`:

```php
        $reservations = Reservation::where('joiner_id', $user->id)
            ->where('status', '!=', 'cancelled')
            // priceTiers is load-bearing: owesBalance() -> money() -> current_price ->
            // currentTier() reads $trip->priceTiers, so without it every booking costs one
            // extra query and the page degrades linearly with how much someone travels.
            ->with(['departure.trip.host', 'departure.trip.priceTiers', 'participants'])
            ->get()
            ->filter(fn (Reservation $r) => $r->departure !== null);
```

- [ ] **Step 4: Only build recommendations when they will be shown**

In the same `render()`, replace the `$recommended = Trip::published()...` assignment with a guarded version. Keep the existing query body exactly as it is; only wrap it:

```php
        /*
         * Only computed for an empty dashboard, which is the only place it renders. This is a
         * four-join correlated subquery; running it for every joiner who already has bookings
         * costs a real query and shows nobody anything.
         */
        $recommended = $reservations->isEmpty()
            ? Trip::published()
                ->whereHas('place', fn ($p) => $p->whereIn('category', function ($q) use ($user) {
                    $q->select('category')->from('places')
                        ->join('trip_destinations', 'places.id', '=', 'trip_destinations.place_id')
                        ->join('trips', 'trip_destinations.trip_id', '=', 'trips.id')
                        ->join('trip_departures', 'trips.id', '=', 'trip_departures.trip_id')
                        ->join('reservations', 'trip_departures.id', '=', 'reservations.trip_departure_id')
                        ->where('reservations.joiner_id', $user->id)
                        ->where('reservations.status', '!=', 'cancelled');
                }))
                ->with('place', 'host.hostProfile')
                ->latest()
                ->take(3)
                ->get()
            : collect();
```

- [ ] **Step 5: Fix the comment that still claims a Monday week start**

In the same file, find the calendar comment block and correct the stale line:

```php
        /*
         * Calendar data.
         *
         * Built from every booking, not the filtered set: a month grid that hid trips because a
         * filter chip was set would be lying about where you are going. The grid renders whole
         * weeks from Sunday, so it spills into the neighbouring months.
         */
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=DashboardQueryBudgetTest`
Expected: PASS, 5 tests.

Then: `php artisan test`
Expected: all pass. Baseline was 370; this task adds 5, so expect 375.

- [ ] **Step 7: Commit** *(gated — see Global Constraints)*

```bash
git add tests/Feature/DashboardQueryBudgetTest.php app/Livewire/Joiner/Dashboard.php
git commit -m "perf: eager-load price tiers and stop building unseen recommendations"
```

---

### Task 2: Delete sorting, collapse filters to two visible tabs

**Files:**
- Modify: `app/Livewire/Joiner/Dashboard.php`
- Modify: `resources/views/livewire/joiner/dashboard.blade.php`
- Modify: `tests/Feature/JoinerDashboardTest.php`

**Interfaces:**
- Consumes: `Dashboard::$view` (`calendar` | `grid` | `list`) and `Dashboard::$filter` from Task 1's file.
- Produces: `$filter` accepts only `upcoming` | `past`. `$sort`, `setSort()`, `$showFilters` and `toggleFilters()` no longer exist. View data key `sorted` is gone; `filtered` remains and is always date-ordered.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/JoinerDashboardTest.php`:

```php

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="offers only upcoming and past|has no sorting surface|shows the tabs in cards"`
Expected: FAIL — `setFilter('action')` still sets `action`, and `setSort` still exists.

- [ ] **Step 3: Strip the sort and filter surface from the component**

In `app/Livewire/Joiner/Dashboard.php`:

Delete the `$showFilters` property, the `$sort` property and its `#[Url]` attribute, the `toggleFilters()` method and the `setSort()` method.

Replace `setFilter()` with:

```php
    public function setFilter(string $filter): void
    {
        // Two options only. A hidden four-way filter that persisted in the URL is how a joiner
        // lost sight of every upcoming trip with nothing on screen to explain it.
        $this->filter = $filter === 'past' ? 'past' : 'upcoming';
    }
```

In `render()`, replace the `$filtered` match and the whole `$sorted` match with:

```php
        $filtered = $this->filter === 'past' ? $past : $upcoming;

        // Always by date. A trip is an event; nothing else is a meaningful order for six rows.
        $sorted = $filtered->sortBy(fn (Reservation $r) => $r->departure->starts_at);
```

- [ ] **Step 4: Replace the filter panel with two visible tabs**

In `resources/views/livewire/joiner/dashboard.blade.php`, delete the funnel `<button type="button" wire:click="toggleFilters" …>` and the entire `@if($showFilters) … @endif` block (the `dusk="filter-bar"` panel).

Immediately above the bookings section, add:

```blade
    {{-- Two tabs, always visible. Cards and List only: in Calendar the month is the scope, and
         the grid deliberately shows every booking, so a filter tab there would contradict it. --}}
    @if($view !== 'calendar')
        <div class="flex items-center gap-2 mb-4" role="group" aria-label="Filter trips">
            @foreach(['upcoming' => 'Upcoming', 'past' => 'Past'] as $key => $label)
                <button type="button" wire:click="setFilter('{{ $key }}')" dusk="tab-{{ $key }}"
                        aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                        class="rounded-full px-4 min-h-[40px] text-sm font-semibold transition-colors
                               {{ $filter === $key ? 'bg-pine text-cream' : 'border border-ink/15 hover:border-ink/35' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif
```

- [ ] **Step 5: Simplify the section heading**

Find the heading block that branches on `$filter` for four values and replace its contents with:

```blade
                <h2 class="marker text-[11px] font-semibold text-ink/60">
                    {{ $view === 'calendar' ? 'Your calendar' : ($filter === 'past' ? 'Past' : 'Upcoming') }}
                </h2>
```

- [ ] **Step 6: Remove the now-dead tests**

Run: `grep -n "setSort\|toggleFilters\|showFilters\|'action'\|filter-bar" tests/Feature/JoinerDashboardTest.php`

Delete or rewrite each hit. Any test asserting a four-way filter or a sort order is asserting behaviour this task deliberately removes — rewrite it against Upcoming/Past, do not weaken the new behaviour to keep an old test green.

- [ ] **Step 7: Rebuild and run**

Run:
```bash
npm run build && php artisan view:clear && php artisan test
```
Expected: all pass.

- [ ] **Step 8: Commit** *(gated)*

```bash
git add app/Livewire/Joiner/Dashboard.php resources/views/livewire/joiner/dashboard.blade.php tests/Feature/JoinerDashboardTest.php
git commit -m "refactor: two visible filter tabs, no sorting"
```

---

### Task 3: Urgent items cannot be dismissed; soft nudges stay dismissed

**Files:**
- Modify: `app/Livewire/Joiner/Dashboard.php`
- Modify: `resources/views/livewire/joiner/dashboard.blade.php`
- Modify: `tests/Feature/JoinerDashboardTest.php`

**Interfaces:**
- Consumes: the `attention()` item shape from Task 1's file — each item is an array with keys `kind`, `urgent`, `title`, `detail`, `cta`, `href`, `reservation_id`.
- Produces: `dismissAttention(string $kind)` accepts only non-urgent kinds and persists to the session. `$dismissedAttention` is populated from the session on mount.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/JoinerDashboardTest.php`:

```php

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
    $user = joiner();
    $booking = bookingFor($user, ['status' => 'pending', 'expires_at' => now()->addHours(6)]);

    $html = Livewire::actingAs($user)->test(Dashboard::class)->html();

    $card = substr($html, strpos($html, 'dusk="attention-pay-'.$booking->id.'"'));
    $card = substr($card, 0, strpos($card, 'attention-') ?: 2000);

    expect($card)->not->toContain('dismissAttention');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="keeps a soft nudge dismissed|refuses to dismiss an expiring hold|offers no dismiss control"`
Expected: FAIL — the dismissal does not survive a second component instance, and `dismissAttention('pay')` currently hides the payment item.

- [ ] **Step 3: Persist dismissals and refuse urgent kinds**

In `app/Livewire/Joiner/Dashboard.php`, add a constant beside the other class constants:

```php
    /** Kinds a joiner may hide. Anything time-sensitive is deliberately absent. */
    private const DISMISSIBLE = ['review'];
```

Replace `dismissAttention()` with:

```php
    public function dismissAttention(string $kind): void
    {
        /*
         * Urgent items are not dismissible. An unpaid hold expires and releases the seat, so a
         * control that hides it is an affordance that costs someone their place.
         */
        if (! in_array($kind, self::DISMISSIBLE, true)) {
            return;
        }

        $this->dismissedAttention = array_values(array_unique([...$this->dismissedAttention, $kind]));

        session()->put($this->dismissKey(), $this->dismissedAttention);
    }

    /** Per-user, so one account's dismissal never hides another's nudge on a shared browser. */
    private function dismissKey(): string
    {
        return 'joiner-dismissed.'.Auth::id();
    }
```

Extend `mount()` so a dismissal survives a reload:

```php
    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');

        // Session rather than a column: a dismissal is a per-visit preference, not a fact about
        // the booking, and the review nudge disappears for good once the review is written.
        $this->dismissedAttention = session($this->dismissKey(), []);
    }
```

- [ ] **Step 4: Hide the dismiss button on urgent items**

In `resources/views/livewire/joiner/dashboard.blade.php`, wrap the dismiss button inside the attention card:

```blade
                            @unless($item['urgent'])
                                <button type="button" wire:click="dismissAttention('{{ $item['kind'] }}')"
                                        class="shrink-0 -mt-1 -mr-1 text-ink/35 hover:text-ink/70 p-2 rounded-lg hover:bg-ink/5 transition-colors"
                                        aria-label="Dismiss">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            @endunless
```

- [ ] **Step 5: Run the tests**

Run:
```bash
npm run build && php artisan view:clear && php artisan test
```
Expected: all pass.

- [ ] **Step 6: Commit** *(gated)*

```bash
git add app/Livewire/Joiner/Dashboard.php resources/views/livewire/joiner/dashboard.blade.php tests/Feature/JoinerDashboardTest.php
git commit -m "fix: persist soft dismissals, refuse to hide an expiring hold"
```

---

### Task 4: One booking card, one empty state

**Files:**
- Create: `resources/views/components/booking-card.blade.php`
- Create: `resources/views/components/no-trips.blade.php`
- Modify: `resources/views/livewire/joiner/dashboard.blade.php`

**Interfaces:**
- Consumes: `App\Models\Reservation` with `departure.trip.host` and `participants` eager-loaded by Task 1.
- Produces: `<x-booking-card :reservation="$r" variant="card|row" :actions="true|false" />` and `<x-no-trips />`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/JoinerDashboardTest.php`:

```php

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

it('shows a single empty state', function () {
    $html = Livewire::actingAs(joiner())->test(Dashboard::class)->html();

    // Two hand-maintained copies of the same empty state is two places to forget a fix.
    expect(substr_count($html, 'No trips booked yet'))->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="renders one booking card component|shows a single empty state"`
Expected: FAIL — no `dusk="booking-card"` exists, and the empty state currently appears twice.

- [ ] **Step 3: Create the booking card component**

Create `resources/views/components/booking-card.blade.php`:

```blade
@props([
    'reservation',
    'variant' => 'card',   // card = cover on top; row = thumbnail beside the text
    'actions' => true,
])

@php($trip = $reservation->departure->trip)
@php($link = route('reservations.show', ['token' => $reservation->public_token]))
@php($status = [
    'pending' => ['Awaiting payment', 'amber'],
    'awaiting_payment' => ['Awaiting payment', 'amber'],
    'payment_submitted' => ['Waiting on your host', 'amber'],
    'confirmed' => ['Confirmed', 'moss'],
][$reservation->status] ?? [ucfirst(str_replace('_', ' ', $reservation->status)), 'ink'])
@php($pill = $status[1] === 'moss' ? 'bg-moss/90 text-cream'
    : ($status[1] === 'amber' ? 'bg-amber/90 text-cream' : 'bg-ink/70 text-cream/90'))
@php($past = $reservation->departure->starts_at->isPast())

@if($variant === 'card')
    <div dusk="booking-card" class="flex flex-col rounded-2xl border border-ink/10 overflow-hidden hover:border-ink/25 transition-all duration-300">
        <a href="{{ $link }}" wire:navigate class="block relative group">
            <img src="{{ $trip->cover_url }}" alt=""
                 class="w-full h-36 object-cover transition-transform duration-[900ms] ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.04] {{ $past ? 'grayscale' : '' }}">
            <div class="absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-ink/60 to-transparent" aria-hidden="true"></div>
            {{-- The date reads before the title: a trip is an event first. --}}
            <span class="absolute top-3 left-3 grid place-items-center rounded-xl bg-cream/95 px-2.5 py-1 text-center shadow-sm">
                <span class="block text-[10px] font-bold uppercase tracking-wide text-terra leading-none">{{ $reservation->departure->starts_at->format('M') }}</span>
                <span class="block font-serif font-bold text-lg text-ink leading-none mt-0.5">{{ $reservation->departure->starts_at->format('j') }}</span>
            </span>
            <span class="absolute top-3 right-3 rounded-full px-3 py-1 text-xs font-bold {{ $pill }}">{{ $status[0] }}</span>
        </a>
        <div class="p-4 flex-1 flex flex-col">
            <a href="{{ $link }}" wire:navigate class="block min-w-0 mb-1">
                <p class="font-semibold truncate">{{ $trip->title }}</p>
            </a>
            <p class="text-sm text-ink/65">
                {{ $reservation->departure->starts_at->format('D j M Y') }}
                <span class="text-ink/40">·</span> {{ $reservation->party_size }} {{ \Illuminate\Support\Str::plural('seat', $reservation->party_size) }}
                <span class="text-ink/40">·</span> {{ $trip->host->name }}
            </p>
            @if($actions)
                <div class="flex items-center justify-end gap-1 mt-auto pt-3 border-t border-ink/8">
                    <a href="{{ $link }}" wire:navigate
                       class="inline-flex items-center rounded-full border border-ink/20 text-sm font-semibold text-pine hover:border-ink/30 min-h-[40px] px-4 transition-colors">View</a>
                </div>
            @endif
        </div>
    </div>
@else
    <a href="{{ $link }}" wire:navigate dusk="booking-card"
       class="flex items-center gap-4 rounded-2xl border border-ink/10 p-4 hover:border-ink/25 transition-all {{ $past ? 'opacity-75 hover:opacity-100' : '' }}">
        <img src="{{ $trip->cover_url }}" alt="" class="w-16 h-16 rounded-xl object-cover shrink-0 {{ $past ? 'grayscale' : '' }}">
        <span class="min-w-0 flex-1">
            <span class="block font-semibold truncate">{{ $trip->title }}</span>
            <span class="block text-sm text-ink/65">
                {{ $reservation->departure->starts_at->format('D j M Y') }}
                <span class="text-ink/40">·</span> {{ $reservation->party_size }} {{ \Illuminate\Support\Str::plural('seat', $reservation->party_size) }}
            </span>
        </span>
        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $pill }}">{{ $status[0] }}</span>
    </a>
@endif
```

- [ ] **Step 4: Create the empty state component**

Create `resources/views/components/no-trips.blade.php`:

```blade
@props(['compact' => false])

{{-- compact is for the calendar, where this sits *under* a rendered grid rather than in place
     of one, so it needs less vertical weight and no illustration. --}}
<div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 text-center bg-ink/[.01] {{ $compact ? 'py-10 mt-4' : 'py-12' }}">
    @unless($compact)
        <div class="w-16 h-16 rounded-full bg-ink/[.04] grid place-items-center mx-auto mb-4">
            <svg viewBox="0 0 24 24" class="w-8 h-8 text-ink/30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </div>
    @endunless
    <p class="font-semibold mb-1">No trips booked yet</p>
    <p class="text-ink/65 text-sm mb-5">Find a group heading somewhere you want to go.</p>
    <a href="{{ route('trips.index') }}" wire:navigate
       class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors shadow-sm">Explore trips</a>
</div>
```

- [ ] **Step 5: Replace the duplicated markup**

In `resources/views/livewire/joiner/dashboard.blade.php`:

Replace the grid-view card loop body with `<x-booking-card :reservation="$r" variant="card" />`.

Replace the list-view row body with `<x-booking-card :reservation="$r" variant="row" />`.

Replace both past-section loops with `<x-booking-card :reservation="$r" variant="row" :actions="false" />`.

Replace **all three** copies of the "No trips booked yet" block with the component: the two
full-size ones with `<x-no-trips />`, and the calendar's shorter nudge with
`<x-no-trips compact />`. Leaving the calendar copy hand-written would keep a third copy of the
same sentence alive and break the "exactly one occurrence" test below, since Calendar is the
default view.

Verify nothing was missed:

```bash
grep -c "No trips booked yet" resources/views/livewire/joiner/dashboard.blade.php
```
Expected: `0` (the string now lives only in the component).

- [ ] **Step 6: Run the tests**

Run:
```bash
npm run build && php artisan view:clear && php artisan test
```
Expected: all pass.

- [ ] **Step 7: Verify in a browser**

Run `php artisan serve --port=8020`, sign in as a joiner with bookings, and check Cards, List and the past section render the card at each width. Confirm no horizontal scroll at 375px.

- [ ] **Step 8: Commit** *(gated)*

```bash
git add resources/views/components/booking-card.blade.php resources/views/components/no-trips.blade.php resources/views/livewire/joiner/dashboard.blade.php tests/Feature/JoinerDashboardTest.php
git commit -m "refactor: one booking card component, one empty state"
```

---

### Task 5: Holidays in our own table, imported by command

**Files:**
- Create: `database/migrations/2026_08_02_100000_create_holidays_table.php`
- Create: `app/Models/Holiday.php`
- Create: `app/Console/Commands/ImportHolidays.php`
- Create: `tests/Feature/ImportHolidaysTest.php`
- Modify: `app/Services/PhilippineHolidays.php`
- Modify: `tests/Feature/PhilippineHolidaysTest.php`
- Modify: `routes/console.php`

**Interfaces:**
- Consumes: nothing from Tasks 1–4.
- Produces: `Holiday` model over a `holidays` table. `PhilippineHolidays::forYear(int $year): array` and `::between(DateTimeInterface $from, DateTimeInterface $to): array` keep their existing signatures and `date => name` return shape, so the dashboard needs no change. New command `holidays:import {year?}`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ImportHolidaysTest.php`:

```php
<?php

use App\Models\Holiday;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Pest.php installs a catch-all fake so no test reaches the network, and Laravel evaluates
    // stubs in registration order — so that catch-all would win over the stubs below. A fresh
    // factory gives each test a clean slate.
    Http::swap(new Illuminate\Http\Client\Factory());
});

function nagerResponse(array $rows): void
{
    Http::fake(['date.nager.at/*' => Http::response($rows, 200)]);
}

it('imports holidays into the table', function () {
    nagerResponse([
        ['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day'],
        ['date' => '2026-12-25', 'localName' => 'Pasko', 'name' => 'Christmas Day'],
    ]);

    $this->artisan('holidays:import', ['year' => 2026])->assertSuccessful();

    expect(Holiday::count())->toBe(2)
        ->and(Holiday::where('date', '2026-06-12')->first()->name)->toBe('Araw ng Kalayaan')
        ->and(Holiday::where('date', '2026-06-12')->first()->source)->toBe('import');
});

it('is safe to run twice', function () {
    nagerResponse([['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day']]);

    $this->artisan('holidays:import', ['year' => 2026]);
    $this->artisan('holidays:import', ['year' => 2026]);

    expect(Holiday::count())->toBe(1);
});

it('never overwrites a manual correction', function () {
    // A Presidential Proclamation moves a holiday; someone fixes the row by hand. The next
    // scheduled import must not undo that.
    Holiday::create([
        'date' => '2026-06-12',
        'name' => 'Moved by proclamation',
        'source' => 'manual',
    ]);

    nagerResponse([['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day']]);

    $this->artisan('holidays:import', ['year' => 2026])->assertSuccessful();

    expect(Holiday::where('date', '2026-06-12')->first()->name)->toBe('Moved by proclamation')
        ->and(Holiday::where('date', '2026-06-12')->first()->source)->toBe('manual');
});

it('updates an imported row while leaving a manual one beside it alone', function () {
    Holiday::create(['date' => '2026-06-12', 'name' => 'Hand fixed', 'source' => 'manual']);
    Holiday::create(['date' => '2026-12-25', 'name' => 'Stale name', 'source' => 'import']);

    nagerResponse([
        ['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day'],
        ['date' => '2026-12-25', 'localName' => 'Pasko', 'name' => 'Christmas Day'],
    ]);

    $this->artisan('holidays:import', ['year' => 2026]);

    expect(Holiday::where('date', '2026-06-12')->first()->name)->toBe('Hand fixed')
        ->and(Holiday::where('date', '2026-12-25')->first()->name)->toBe('Pasko');
});

it('reports a failure instead of wiping the table when the API is down', function () {
    Holiday::create(['date' => '2026-06-12', 'name' => 'Araw ng Kalayaan', 'source' => 'import']);

    Http::fake(['date.nager.at/*' => Http::response('', 503)]);

    $this->artisan('holidays:import', ['year' => 2026])->assertFailed();

    // Last year's good data is better than none.
    expect(Holiday::count())->toBe(1);
});

it('defaults to this year and next when no year is given', function () {
    nagerResponse([['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day']]);

    $this->artisan('holidays:import')->assertSuccessful();

    Http::assertSentCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ImportHolidaysTest`
Expected: FAIL — `Class "App\Models\Holiday" not found`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_02_100000_create_holidays_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->unique();
            $t->string('name');
            // 'manual' rows are authoritative and survive every re-import, which is how a late
            // Presidential Proclamation gets corrected without waiting on a third party.
            $t->string('source', 12)->default('import');
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('holidays'); }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/Holiday.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function scopeBetween($query, string $from, string $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
```

- [ ] **Step 5: Create the import command**

Create `app/Console/Commands/ImportHolidays.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportHolidays extends Command
{
    protected $signature = 'holidays:import {year? : Defaults to this year and next}';

    protected $description = 'Import Philippine public holidays from Nager.Date into the holidays table.';

    private const ENDPOINT = 'https://date.nager.at/api/v3/PublicHolidays';

    public function handle(): int
    {
        $years = $this->argument('year')
            ? [(int) $this->argument('year')]
            : [now()->year, now()->year + 1];

        $failed = false;

        foreach ($years as $year) {
            $rows = $this->fetch($year);

            if ($rows === null) {
                $this->error("Could not fetch {$year}.");
                $failed = true;

                continue;
            }

            $written = 0;

            foreach ($rows as $row) {
                if (blank($row['date'] ?? null)) {
                    continue;
                }

                $existing = Holiday::where('date', $row['date'])->first();

                // A hand-corrected row is the authority. Skipping it here is the whole reason
                // the source column exists.
                if ($existing && $existing->source === 'manual') {
                    continue;
                }

                Holiday::updateOrCreate(
                    ['date' => $row['date']],
                    [
                        'name' => $row['localName'] ?: ($row['name'] ?? 'Holiday'),
                        'source' => 'import',
                    ],
                );

                $written++;
            }

            $this->info("{$year}: {$written} holiday(s) written.");
        }

        // A failed fetch must leave existing rows alone. Last year's good data beats none.
        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, array>|null  null means the fetch failed */
    private function fetch(int $year): ?array
    {
        try {
            $response = Http::timeout(10)->retry(2, 300)->get(self::ENDPOINT."/{$year}/PH");

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
```

- [ ] **Step 6: Rewrite the service to read the table**

Replace the whole of `app/Services/PhilippineHolidays.php` with:

```php
<?php

namespace App\Services;

use App\Models\Holiday;

/**
 * Philippine public holidays, for planning a trip around a long weekend.
 *
 * Reads our own table only. Nothing here touches the network: the data arrives through
 * `php artisan holidays:import`, scheduled monthly, so a page render never waits on a third
 * party and never fails because one is down.
 *
 * Holidays are declared each year by Presidential Proclamation and dates are routinely moved
 * to build long weekends. When that happens, a row with `source = 'manual'` overrides the
 * import permanently — see App\Console\Commands\ImportHolidays.
 */
class PhilippineHolidays
{
    /**
     * @return array<string, string>  date (Y-m-d) => name
     */
    public function forYear(int $year): array
    {
        return $this->between(
            \Illuminate\Support\Carbon::create($year, 1, 1),
            \Illuminate\Support\Carbon::create($year, 12, 31),
        );
    }

    /**
     * Holidays covering a date range, which routinely spans a year boundary because the calendar
     * grid renders whole weeks and spills into the neighbouring months.
     *
     * @return array<string, string>
     */
    public function between(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return Holiday::between($from->format('Y-m-d'), $to->format('Y-m-d'))
            ->orderBy('date')
            ->get()
            ->mapWithKeys(fn (Holiday $h) => [$h->date->format('Y-m-d') => $h->name])
            ->all();
    }
}
```

- [ ] **Step 7: Rewrite the service tests against the table**

Replace the whole of `tests/Feature/PhilippineHolidaysTest.php` with:

```php
<?php

use App\Models\Holiday;
use App\Services\PhilippineHolidays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

it('reads holidays from our own table', function () {
    Holiday::create(['date' => '2026-06-12', 'name' => 'Araw ng Kalayaan']);
    Holiday::create(['date' => '2026-08-31', 'name' => 'Araw ng mga Bayani']);

    $holidays = app(PhilippineHolidays::class)->forYear(2026);

    expect($holidays)->toHaveKey('2026-06-12')
        ->and($holidays['2026-08-31'])->toBe('Araw ng mga Bayani');
});

it('makes no network call at all', function () {
    // The whole point of owning the table: a page render must never wait on a third party.
    Http::preventStrayRequests();
    Holiday::create(['date' => '2026-06-12', 'name' => 'Araw ng Kalayaan']);

    expect(app(PhilippineHolidays::class)->forYear(2026))->toHaveCount(1);

    Http::assertNothingSent();
});

it('spans a year boundary, because the grid does', function () {
    Holiday::create(['date' => '2026-12-25', 'name' => 'Pasko']);
    Holiday::create(['date' => '2027-01-01', 'name' => 'Bagong Taon']);

    $range = app(PhilippineHolidays::class)->between(
        Carbon::parse('2026-11-30'),
        Carbon::parse('2027-01-03'),
    );

    expect($range)->toHaveKeys(['2026-12-25', '2027-01-01']);
});

it('leaves out holidays beyond the range asked for', function () {
    Holiday::create(['date' => '2026-06-12', 'name' => 'Araw ng Kalayaan']);
    Holiday::create(['date' => '2026-08-31', 'name' => 'Araw ng mga Bayani']);

    $range = app(PhilippineHolidays::class)->between(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    expect($range)->toHaveKey('2026-08-31')
        ->and($range)->not->toHaveKey('2026-06-12');
});

it('returns nothing for a year nobody has imported', function () {
    expect(app(PhilippineHolidays::class)->forYear(2030))->toBe([]);
});
```

- [ ] **Step 8: Schedule the import**

Append to `routes/console.php`:

```php
// Monthly, so next year's holidays appear on their own once the proclamations are published,
// and a late correction upstream is picked up without anyone remembering to run anything.
Schedule::command('holidays:import')->monthlyOn(1, '03:00');
```

- [ ] **Step 9: Fix the dashboard holiday tests**

Two tests in `tests/Feature/JoinerDashboardTest.php` fake the HTTP API to supply holidays. They must now seed the table instead.

Run: `grep -n "date.nager.at" tests/Feature/JoinerDashboardTest.php`

Replace each `Http::swap(...)` / `Http::fake(['date.nager.at/*' => ...])` pair with a `Holiday::create([...])` for the dates the test needs, and add `use App\Models\Holiday;` to the file's imports. The test named *"still renders the calendar when the holiday API is unreachable"* no longer describes anything real — rewrite it as *"still renders the calendar when no holidays have been imported"*, asserting the calendar renders with an empty `holidays` table.

- [ ] **Step 10: Migrate and run**

Run:
```bash
php artisan migrate && php artisan test
```
Expected: all pass.

- [ ] **Step 11: Import real data into the dev database**

Run: `php artisan holidays:import`
Expected: two lines like `2026: 18 holiday(s) written.` and `2027: N holiday(s) written.`

- [ ] **Step 12: Commit** *(gated)*

```bash
git add database/migrations/2026_08_02_100000_create_holidays_table.php app/Models/Holiday.php app/Console/Commands/ImportHolidays.php app/Services/PhilippineHolidays.php routes/console.php tests/Feature/ImportHolidaysTest.php tests/Feature/PhilippineHolidaysTest.php tests/Feature/JoinerDashboardTest.php
git commit -m "feat: own the holidays table, import from Nager.Date on a schedule"
```

---

## Verification checklist

Run each and read the output — do not assume:

- [ ] `php artisan test` — all green. Baseline 370; this plan adds roughly 21 tests and removes a few obsolete ones, so expect the high 380s.
- [ ] `php artisan dusk` — 28 passing, and **SystemAuditTest still reports 0 findings**.
- [ ] `php artisan migrate:fresh --seed` succeeds, proving the holidays migration composes with the existing 18.
- [ ] The dashboard renders in a browser at 1728px, 1280px and 375px with no horizontal scroll.
- [ ] Calendar contrast has not regressed: cell title ≥ 12:1 worst case, weekend numeral ≥ 10:1, holiday numeral ≥ 13:1. Measure with the compositing probe rather than by eye — a `getComputedStyle` read of a semi-transparent background reports the wrong colour.
- [ ] `php artisan holidays:import 2026` twice in a row leaves the row count unchanged.
