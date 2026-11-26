# Discover Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A page of full-screen host photos that scrolls endlessly, favours places the traveler has not been to, and puts a Book button on every card.

**Architecture:** Host wall posts gain an `in_discover` flag and a trip tag set from the composer. A `DiscoverFeed` support class selects and orders the qualifying posts; a Livewire page `Discover` renders them as a snap-scrolling column and loads the next batch when a late card scrolls into view. The phone tab bar swaps "Hosts" for "Discover".

**Tech Stack:** Laravel 12, Livewire 3.8 (Alpine's intersect plugin is bundled), Tailwind 3.4, Pest 3, Dusk (MySQL `terra_dusk`). No new dependency.

**Spec:** `docs/superpowers/specs/2026-11-26-discover-feed-design.md`

**Depends on:** the Your Philippines plan having landed (`VisitedProvinces`, `places.province`).

## Global Constraints

- A post qualifies for Discover only when it has at least one image, `in_discover` is true, its `trip_id` points at a trip that is listed to the viewer (`Trip::listedFor`) and has an open future departure (`openDepartures`). Recap posts never qualify. A suspended host never appears (already inside `published()`).
- Reuse posts, post images and the listing rules; no new table, no new upload flow, no video, no likes, comments or saves.
- Ordering: for a signed-in traveler, posts whose trip is new to them come first (a trip is "familiar" when every itinerary place is in a province they have visited, or is a place they have already booked); each group newest first; no host twice in a row while another host's post is available. Guests: newest first with the same host variety.
- A batch is 8 cards. The candidate set is capped at 200 posts.
- "Hosts" leaves the phone tab bar and "Discover" takes its place; Hosts stays in the desktop nav, on Home and in Explore. The page is open to guests.
- Book goes to `trips.join`; the trip line goes to `trips.show`; a host viewing sees "View trip" instead of Book.
- Existing posts stay out of Discover; the tag and the switch are set when a post is created.
- Accessibility: alt text on every image (the caption, else "{trip title} photo"), Tab never trapped by the snap column, reduced motion turns off smooth scrolling, text no lighter than `text-ink/65` on the card's ground, touch targets 44px, works at 320px.
- Dev mode: run only the Pest files and Dusk classes a task names, never the full suites. Dusk swaps `.env` while it runs, so look at terra.test in a browser before or after a Dusk run, not during.
- Commits: Conventional Commit prefix, lowercase subject, no AI attribution line, on `master`, no branches or worktrees. Push after the last task.

## Review Focus

1. A link-only trip's photo never appears for anyone, and a followers-first trip's photo appears only to its host's followers until its window ends. (Task 2)
2. A post tagged with a trip that has no open future departure (sold out, ended) drops out. (Task 2)
3. Passing the shown ids back never repeats or skips a card, and the last batch ends cleanly with no spinner left. (Tasks 2, 3)
4. Tagging another host's trip, or switching Discover on without a photo, is rejected or ignored. (Task 1)
5. A host with no handle, a post with several photos, and a very long caption all render without breaking the card; a host viewing sees View trip, not Book. (Task 3)

---

### Task 1: The in_discover flag and the composer

**Files:**
- Create: `database/migrations/2026_11_27_110000_add_in_discover_to_posts_table.php`
- Modify: `app/Livewire/Hosts/Show.php`, `resources/views/livewire/hosts/show.blade.php` (the post composer)
- Test: `tests/Feature/DiscoverComposerTest.php`

**Interfaces:**
- Produces: `posts.in_discover` (boolean, default false) with an index on `(in_discover, created_at)`; on `Hosts\Show`: `public ?int $postTripId = null`, `public bool $postInDiscover = true`, `getTaggableTripsProperty(): Collection` (the host's published trips that have an open future departure, soonest first); `publishPost()` saves `trip_id` and sets `in_discover` to true only when a trip is tagged, at least one image is attached and the switch is on.

- [ ] **Step 1: Write the failing tests.**

```php
it('saves a tagged photo post into Discover when the switch is on', function () {
    Livewire::actingAs($host)->test(Show::class, ['user' => $host])
        ->set('postBody', 'Sea of clouds')->set('postImages', [UploadedFile::fake()->image('a.jpg')])
        ->set('postTripId', $trip->id)->set('postInDiscover', true)->call('publishPost');
    expect(Post::latest('id')->first())->trip_id->toBe($trip->id)->in_discover->toBeTrue();
});

it('keeps a post out of Discover when the switch is off, there is no photo, or no trip is tagged', function (array $set) {
    // dataset: switch off | no image | no trip
    expect(Post::latest('id')->first()->in_discover)->toBeFalse();
});

it('rejects another host\'s trip and a trip with nothing open', function () {
    Livewire::actingAs($host)->test(Show::class, ['user' => $host])->set('postBody', 'x')->set('postTripId', $otherHostsTrip->id)->call('publishPost')->assertHasErrors('postTripId');
});

it('offers only the host\'s own open trips to tag', function () {
    Livewire::actingAs($host)->test(Show::class, ['user' => $host])->assertViewHas('taggableTrips', fn ($t) => $t->pluck('id')->all() === [$open->id]);
});

it('leaves old posts and recaps out of Discover', function () {
    expect(Post::factory()->create()->in_discover)->toBeFalse();
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/DiscoverComposerTest.php`
- [ ] **Step 3: Implement the migration, the properties and `publishPost()` changes** (validate `postTripId` as a trip of this host that is published with an open future departure; ignore the switch without a trip or a photo). In the composer view add, below the textarea, a "Tag a trip" select (placeholder "No trip", options "{title} · {next date}") and a "Show in Discover" switch that appears when a trip is chosen and a photo is attached, on by default; reset both fields after posting. Existing composer behaviour is unchanged when neither is used.
- [ ] **Step 4: Run the file plus `tests/Feature/HostPageTest.php tests/Feature/HostProfilePageTest.php tests/Feature/ListingVisibilityTest.php`.** All pass.
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: let a host tag a trip and show a photo post in discover"`

---

### Task 2: The feed query and its ordering

**Files:**
- Create: `app/Support/DiscoverFeed.php`
- Test: `tests/Feature/DiscoverFeedTest.php`

**Interfaces:**
- Consumes: `Trip::listedFor()`, `Trip::openDepartures()`, `VisitedProvinces::for()`.
- Produces `App\Support\DiscoverFeed::next(?User $viewer, array $seen = [], int $size = 8): Collection` returning `Post` models with `images`, `host.hostProfile`, `trip.place`, `trip.destinations.place` and the trip's open future departures eager-loaded, in display order, excluding ids in `$seen`.

- [ ] **Step 1: Write the failing tests** (helper `discoverPost(array $trip = [], array $post = [])` builds a host, a published trip with an open departure and a Discover-flagged post with an image).

```php
it('includes a qualifying post and nothing else', function (string $why) {
    // dataset: no image | flag off | no trip | link-only trip | trip with no open departure | suspended host | recap post
    expect(DiscoverFeed::next(null)->pluck('id'))->not->toContain($post->id);
})->with([...]);

it('shows a followers-first trip only to followers until its window ends', function () {
    expect(DiscoverFeed::next($follower)->pluck('id'))->toContain($early->id)
        ->and(DiscoverFeed::next($stranger)->pluck('id'))->not->toContain($early->id)
        ->and(DiscoverFeed::next(null)->pluck('id'))->not->toContain($early->id);
});

it('puts places the viewer has not been to first', function () {
    // viewer has a finished palawan booking; a palawan post is newer than a batanes post
    expect(DiscoverFeed::next($viewer)->pluck('id')->all())->toBe([$batanes->id, $palawan->id]);
});

it('does not let one host fill the screen', function () {
    // hosts A (3 posts, newest) and B (2 posts) -> A B A B A, never A A
});

it('pages without repeating or skipping', function () {
    $first = DiscoverFeed::next($viewer, [], 3);
    $second = DiscoverFeed::next($viewer, $first->pluck('id')->all(), 3);
    expect($first->pluck('id')->merge($second->pluck('id'))->unique())->toHaveCount(5)
        ->and(DiscoverFeed::next($viewer, $all->pluck('id')->all(), 3))->toBeEmpty();
});

it('caps the candidate set and runs a fixed number of queries', function () {
    // 30 qualifying posts: DB::getQueryLog() count stays under a small constant
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/DiscoverFeedTest.php`
- [ ] **Step 3: Implement `next()`.** Select qualifying posts newest first (`whereHas('images')`, `whereHas('trip', fn ($t) => $t->listedFor($viewer)->whereHas('openDepartures'))`, limit 200) with the eager loads above; drop `$seen`; for a signed-in viewer mark each post familiar when every Philippine itinerary place's province is in `VisitedProvinces::for($viewer)` or its place id is one the viewer has already booked (a finished confirmed booking), unfamiliar first; then interleave hosts greedily (take the first remaining post whose host differs from the previous one, else the first); return the first `$size`.
- [ ] **Step 4: Run the file.** All pass.
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: select and order the discover feed"`

---

### Task 3: The Discover page

**Files:**
- Create: `app/Livewire/Discover.php`, `resources/views/livewire/discover.blade.php`
- Modify: `routes/web.php`, `app/Support/Handles.php` (reserve `discover`), `resources/views/components/layouts/site.blade.php` (a `footer` layout flag, and the Discover link in the desktop nav)
- Test: `tests/Feature/DiscoverPageTest.php`

**Interfaces:**
- Consumes: `DiscoverFeed::next()`.
- Produces: route `discover` (`GET /discover`, open to guests); `Discover` with `public array $shown = []`, `public bool $hasMore = true`, `loadMore(): void` (appends the next 8 ids from `DiscoverFeed::next(Auth::user(), $this->shown)` and sets `$hasMore` to whether it returned a full batch), and `mount()` calling `loadMore()` once. The layout accepts `'footer' => false`.

- [ ] **Step 1: Write the failing tests.**

```php
it('shows the first eight cards, newest places first, to a guest', function () {
    $this->get(route('discover'))->assertOk()->assertSee($post->host->name)->assertSee($post->trip->title);
});

it('loads the next batch and stops cleanly', function () {
    Livewire::test(Discover::class)->assertCount('shown', 8)->call('loadMore')->assertCount('shown', 10)->assertSet('hasMore', false);
});

it('sends Book to the join page and the trip line to the trip page', function () {
    $this->get(route('discover'))->assertSee(route('trips.join', $trip), false)->assertSee(route('trips.show', $trip), false);
});

it('shows a host View trip instead of Book', function () {
    $this->actingAs($host)->get(route('discover'))->assertDontSee(route('trips.join', $trip), false)->assertSeeText('View trip');
});

it('renders a card for a host with no handle, several photos and a long caption', function () { /* no exception, first image eager, others lazy, dots present, caption clamped */ });

it('shows a friendly empty state with a way to Explore', function () {
    $this->get(route('discover'))->assertSeeText('Nothing to discover yet')->assertSee(route('trips.index'), false);
});

it('reserves the route name and hides the footer', function () {
    expect(Handles::RESERVED)->toContain('discover');
    $this->get(route('discover'))->assertDontSee('Tarana comes from', false);
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/DiscoverPageTest.php`
- [ ] **Step 3: Implement.** `render()` loads the shown posts by id (in `$shown` order) with the eager loads and passes them to the view. View: a `snap-y snap-mandatory overflow-y-scroll` column whose cards are the height of the visible screen above the tab bar and below the nav; the first card's image `loading="eager"`, the rest `lazy`; each card as the spec describes (photo, gradient, avatar and name, clamped caption, trip line with title, place and province, next date and price, a primary Book button, dots for several photos); `x-intersect="$wire.loadMore()"` on the third-from-last card while `$hasMore`; on desktop a centred phone-width strip on a dark ground with arrow keys stepping between cards; reduced-motion turns off smooth scrolling. Add the route above the catch-all handle route, reserve the handle, add the `footer` flag to the site layout, and a "Discover" link to the desktop nav.
- [ ] **Step 4: Run the file.** All pass. Build assets and look at `/discover` at desktop and 320px with seeded posts once.
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: add discover, a vertical feed of host photos"`

---

### Task 4: Discover in the tab bar

**Files:**
- Modify: `resources/views/components/layouts/site.blade.php` (the tabs), `resources/views/components/tabbar.blade.php` (a new icon), `tests/Feature/TabBarTest.php`, `tests/Browser/AdminConsoleFlowTest.php` (the `@tab-hosts` click), `tests/Browser/MobileNavTest.php` if it names the Hosts tab
- Test: `tests/Feature/TabBarTest.php`

**Interfaces:**
- Produces: a tab `['label' => 'Discover', 'href' => route('discover'), 'icon' => 'spark', 'active' => request()->routeIs('discover'), 'dusk' => 'tab-discover']` replacing the Hosts tab for guests and signed-in joiners; the tab bar icon `spark` (a four-point star in the same stroke style as the others).

- [ ] **Step 1: Update the tests first.** In `TabBarTest` assert the guest bar is Home, Explore, Discover, Log in and the joiner bar is Home, Explore, Discover, Trips, Account, each with exactly five or fewer tabs, and that no tab links to `hosts.index`; change the Dusk steps that clicked `@tab-hosts` to open the directory through its URL or the desktop nav link.
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/TabBarTest.php`
- [ ] **Step 3: Implement the tab and the icon.**
- [ ] **Step 4: Run `tests/Feature/TabBarTest.php tests/Feature/BrandTest.php tests/Feature/HomePageTest.php`.** All pass.
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: put discover in the phone tab bar"`

---

### Task 5: Demo data, Dusk and the final targeted run

**Files:**
- Modify: `database/seeders/FakeDataSeeder.php`, `tests/Browser/ContrastAuditTest.php`
- Create: `tests/Browser/DiscoverFlowTest.php`

- [ ] **Step 1: Seed demo data.** Give two seeded hosts a few photo posts tagged to their open trips with the switch on, using the stock photos, keeping `tests/Feature/FakeDataSeederTest.php` green.
- [ ] **Step 2: Write the Dusk test** `DiscoverFlowTest::test_a_traveler_scrolls_and_books`: open `/discover` on a phone-sized window, scroll the column until a second batch loads (the card count grows), tap Book on a card and land on that trip's join page; repeat the first check at 320px.
- [ ] **Step 3: Add `/discover` to the contrast audit** (guest and joiner passes) so the card text over photos is audited.
- [ ] **Step 4: Final run.** Pest: `DiscoverComposerTest DiscoverFeedTest DiscoverPageTest TabBarTest FakeDataSeederTest HostPageTest HomePageTest`. Dusk, one run as in `docs/DUSK.md`: `--filter 'DiscoverFlowTest|ContrastAuditTest|MobileNavTest|AdminConsoleFlowTest'`. Afterwards look at `/discover` on terra.test at desktop and 320px.
- [ ] **Step 5: Commit and push.** `git add -A && git commit -m "chore: cover discover end to end" && git push origin master`
