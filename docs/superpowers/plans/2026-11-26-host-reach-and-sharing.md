# Host Reach and Sharing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A host chooses how widely each trip is listed, shares their page and trips with real previews, and keeps a joiner on their own trips with "More from this host".

**Architecture:** `trips` gains `visibility` and `public_at`. Two scopes, `listed()` and `listedFor($viewer)`, sit on top of the existing `published()` lifecycle scope, and every discovery query switches to one of them; direct pages keep keying off `status`. Sharing is one Blade component plus an Open Graph component fed by a small `PageMeta` builder.

**Tech Stack:** Laravel 12, Livewire 3, Alpine, Tailwind 3.4, Pest 3, Dusk (MySQL `terra_dusk`), Endroid QR (already installed).

**Spec:** `docs/superpowers/specs/2026-11-26-host-reach-and-sharing-design.md`

## Global Constraints

- `Trip::published()` stays the lifecycle scope (status plus suspension). Visibility is orthogonal to it.
- A published trip's page, join page, QR and booking page open by link whatever its visibility. Listing control never changes access.
- Default visibility is `public`, so the roughly 54 test files that lean on the published listing stay green untouched.
- Followers-first windows are presets only: `1d`, `3d`, `1w`.
- Link only is unlisted everywhere, the host's own page included. A host sees their own unlisted trips on their own page and in "More from this host" on their own trip pages, never in Explore or Home.
- Nobody's exploring is restricted: no own-link mode, no hidden nav or footer, no `?via` tag. Booking and payment pages keep their nav.
- No new dependencies; Livewire and Alpine only. Previews use the cover photo plus one default card, `public/images/og-card.png`, 1200×630.
- "More from this host" shows at most six trips.
- Dev mode: run only the Pest files and Dusk classes a task names, never the full suites. Dusk swaps `.env` while it runs, so look at terra.test in a browser before or after a Dusk run, not during.
- Commits: Conventional Commit prefix, lowercase subject, no AI attribution line, on `master`, no branches or worktrees. Push after each slice (Tasks 1-4, 5-6, 7-8).

## Review Focus

Failure modes the spec implies that are most likely to bite; each has a test in the named task.

1. An offering whose schedules have mixed visibility lists only its listed schedules, and its tile counts only those. (Task 2)
2. A host whose every trip is unlisted is missing from Home's top hosts and counts zero in the directory; a host with no handle shows no host-page share panel. (Tasks 2, 6)
3. `public_at` exactly now already counts as open, and a suspended host's trips are never listed, followers or not. (Task 1)
4. Editing one schedule leaves its sibling schedules untouched, "keep" keeps the opening date, and saving a window-passed trip as everyone clears `public_at`. (Task 3)
5. A title with quotes and an ampersand is escaped in preview tags, and the preview image URL is absolute for an uploaded cover and a stock cover alike. (Task 5)

---

### Task 1: Visibility data, support class and scopes

**Files:**
- Create: `database/migrations/2026_11_26_120000_add_visibility_to_trips_table.php`
- Create: `app/Support/Visibility.php`
- Modify: `app/Models/Trip.php` (cast, three methods after `scopePublished`), `database/factories/TripFactory.php`
- Test: `tests/Feature/TripVisibilityTest.php`

**Interfaces:**
- Produces `App\Support\Visibility`: constants `PUBLIC='public'`, `FOLLOWERS='followers'`, `LINK='link'`; `CATALOG` (`public` => 'Everyone', `followers` => 'Followers first', `link` => 'Link only'); `WINDOWS` (`'1d' => ['P1D','1 day']`, `'3d' => ['P3D','3 days']`, `'1w' => ['P1W','1 week']`); `static keys(): array`; `static opensAt(string $window): Carbon` (now plus the window, throws `InvalidArgumentException` for an unknown key); `static chip(Trip $trip): ?string` (null when effectively public, else "Link only" or "Followers first until 5 Dec").
- Produces on `Trip`: `effectiveVisibility(): string`, `scopeListed($q)`, `scopeListedFor($q, ?User $viewer)`, cast `public_at` => `datetime`.
- Produces on `TripFactory`: `linkOnly(): static`, `followersFirst(?DateTimeInterface $opensAt = null): static` (default `now()->addDays(3)`).

- [ ] **Step 1: Write the failing tests** in `tests/Feature/TripVisibilityTest.php`

```php
it('lists a public trip to everyone, guests included', function () {
    $t = Trip::factory()->published()->create();
    expect(Trip::listed()->pluck('id'))->toContain($t->id)
        ->and(Trip::listedFor(null)->pluck('id'))->toContain($t->id);
});

it('lists a followers-first trip to the host\'s followers only until it opens', function () {
    $t = Trip::factory()->published()->followersFirst()->create();
    $follower = User::factory()->create(); $follower->follow($t->host);
    expect(Trip::listedFor($follower)->pluck('id'))->toContain($t->id)
        ->and(Trip::listedFor(User::factory()->create())->pluck('id'))->not->toContain($t->id)
        ->and(Trip::listedFor(null)->pluck('id'))->not->toContain($t->id)
        ->and(Trip::listed()->pluck('id'))->not->toContain($t->id);
});

it('counts public_at exactly now as open', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $t = Trip::factory()->published()->followersFirst(now())->create();
    expect(Trip::listed()->pluck('id'))->toContain($t->id)
        ->and($t->effectiveVisibility())->toBe('public');
});

it('never lists a link-only trip', function () {
    $t = Trip::factory()->published()->linkOnly()->create();
    $follower = User::factory()->create(); $follower->follow($t->host);
    expect(Trip::listedFor($follower)->pluck('id'))->not->toContain($t->id)
        ->and($t->effectiveVisibility())->toBe('link');
});

it('never lists a suspended host\'s trips, followers or not', function () {
    $t = Trip::factory()->published()->followersFirst()->create();
    $t->host->forceFill(['suspended_at' => now()])->save();
    $follower = User::factory()->create(); $follower->follow($t->host);
    expect(Trip::listedFor($follower)->pluck('id'))->not->toContain($t->id);
});

it('words the chip for the host', function () {
    $this->travelTo('2026-11-26 09:00:00');
    expect(Visibility::chip(Trip::factory()->create()))->toBeNull()
        ->and(Visibility::chip(Trip::factory()->linkOnly()->create()))->toBe('Link only')
        ->and(Visibility::chip(Trip::factory()->followersFirst(Carbon::parse('2026-12-05 18:00'))->create()))->toBe('Followers first until 5 Dec');
});
```

- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/TripVisibilityTest.php` fails with `Call to undefined method ... linkOnly()`.
- [ ] **Step 3: Migration.** On `trips`: `string('visibility', 12)->default('public')->after('status')`, `timestamp('public_at')->nullable()->after('visibility')`, `index(['status','visibility','public_at'], 'trips_listing_index')`; `down()` drops the index then both columns.
- [ ] **Step 4: Implement `Visibility`, the `Trip` additions and the factory states.** Qualify every column with `trips.` (Home joins `places`). `scopeListed($q)` is `return $this->scopeListedFor($q, null)`. The scope's SQL shape is the one non-obvious part:

```php
return $q->published()->where(fn ($v) => $v
    ->where('trips.visibility', 'public')
    ->orWhere(fn ($f) => $f->where('trips.visibility', 'followers')->where(fn ($w) => $w
        ->where('trips.public_at', '<=', now())
        ->when($viewer, fn ($w) => $w->orWhereIn('trips.host_id',
            DB::table('follows')->select('host_id')->where('follower_id', $viewer->id))))));
```

`effectiveVisibility()` returns `public` when stored `public`, or when stored `followers` with `public_at` non-null and not in the future; otherwise the stored value.
- [ ] **Step 5: Run the new file, then the guard files.** `./vendor/bin/pest tests/Feature/TripVisibilityTest.php tests/Feature/TripBrowseTest.php tests/Feature/HomePageTest.php` all pass. Then `php artisan migrate` for the dev database.
- [ ] **Step 6: Commit.** `git add -A && git commit -m "feat: add visibility and listing scopes to trips"`

---

### Task 2: Apply listing rules to every discovery surface

**Files:**
- Modify: `app/Livewire/Home.php`, `app/Livewire/Trips/Index.php`, `app/Livewire/Hosts/Index.php`, `app/Support/HostCards.php`, `app/Livewire/Hosts/Show.php`, `app/Livewire/Joiner/Dashboard.php`
- Test: `tests/Feature/ListingVisibilityTest.php`

**Interfaces:**
- Consumes: `Trip::listed()`, `Trip::listedFor(?User)`.
- Rule for every edit: a discovery list uses `listedFor(Auth::user())`; Home's headline `trips` count uses `listed()`; a host's own page uses `published()` when the viewer is that host. Direct-access code (`Trips\Show::mount`, `Register::mount`, `TripQrController`, admin counts, the joiner's own bookings) keeps `status === 'published'`.

- [ ] **Step 1: Write the failing tests** in `tests/Feature/ListingVisibilityTest.php`. Helper `visibilityHost(): User` creates a host with a `HostProfile` and a handle; each test builds an `$open` (public), `$early` (`followersFirst()`) and `$link` (`linkOnly()`) trip for one host, plus a `$follower` of that host.

```php
it('keeps link-only trips out of Explore and shows followers-first ones to followers only', function () {
    $this->get('/trips')->assertSee($open->title)->assertDontSee($early->title)->assertDontSee($link->title);
    $this->actingAs($follower)->get('/trips')->assertSee($early->title)->assertDontSee($link->title);
});

it('lists a mixed-visibility offering once, counting only its listed schedules', function () {
    // two rows sharing a trip_group_id: one public, one linkOnly()
    Livewire::test(TripsIndex::class)
        ->assertViewHas('trips', fn ($p) => $p->count() === 1 && $p->first()->schedule_count === 1);
});

it('leaves a host with only unlisted trips out of Home\'s top hosts', function () {
    Livewire::test(Home::class)->assertViewHas('hosts', fn ($h) => $h->doesntContain('id', $unlistedOnlyHost->id));
});

it('shows the same headline trip count to a follower and a guest', function () {
    expect(Livewire::actingAs($follower)->test(Home::class)->viewData('stats')['trips'])
        ->toBe(Livewire::test(Home::class)->viewData('stats')['trips']);
});

it('counts only listed trips on directory cards and filters categories by them', function () {
    // the host has one public trip and one link-only hike, and no other hike
    Livewire::test(HostsIndex::class)->assertViewHas('hosts', fn ($p) => $p->first()->published_trips === 1);
    Livewire::test(HostsIndex::class)->set('category', 'hike')->assertViewHas('hosts', fn ($p) => $p->isEmpty());
});

it('ignores unlisted trips when picking a host card\'s next departure', function () {
    // the host's only trip with an open departure is link-only
    expect(HostCards::extras([$host->id], null)['next'])->toBe([]);
});

it('shows a host page by viewer', function () {
    $url = route('hosts.handle', $host);
    $this->get($url)->assertSee($open->title)->assertDontSee($early->title)->assertDontSee($link->title);
    $this->actingAs($follower)->get($url)->assertSee($early->title)->assertDontSee($link->title);
    $this->actingAs($host)->get($url)->assertSee($early->title)->assertSee($link->title);
});

it('still opens a link-only trip by link', function () {
    $this->get(route('trips.show', $link))->assertOk();
    $this->get(route('trips.join', $link))->assertOk();
    $this->actingAs($host)->get(route('host.trips.qr', $link))->assertOk();
});
```

The dashboard's `$recommended` query is unreachable today (its category subquery reads the same bookings whose absence enables it), so it gets the scope switch and no dedicated test.
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/ListingVisibilityTest.php`
- [ ] **Step 3: Switch the call sites.** `Home`: `$all` and `$tripTypes` use `Trip::listedFor($viewer)`; the top-hosts `whereHas`/`withCount` closure becomes `fn ($q) => $q->listedFor($viewer)`; `stats.trips` uses `Trip::listed()->count()`. `Trips\Index`: `Trip::listedFor(Auth::user())`. `Hosts\Index`: the `published_trips` count and the category `whereExists` become `listedFor($viewer)` (use `whereHas('trips', ...)`), and `$categoriesByHost` starts from `Trip::listedFor($viewer)`. `HostCards::extras`: `$t->listedFor($viewer)->whereIn('trips.host_id', $ids)`. `Hosts\Show`: one closure picks `published()` for the owner and `listedFor(Auth::user())` otherwise, used by `$trips` and `$upcoming`. `Joiner\Dashboard`: `Trip::listedFor($user)`.
- [ ] **Step 4: Run the new file plus the files these surfaces already have.** `./vendor/bin/pest tests/Feature/ListingVisibilityTest.php tests/Feature/HomePageTest.php tests/Feature/TripBrowseTest.php tests/Feature/HostsDirectoryTest.php tests/Feature/HostPageTest.php tests/Feature/HostIdentityTest.php tests/Feature/JoinerDashboardTest.php` all pass.
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: list trips by visibility across discovery"`

---

### Task 3: Wizard control for who can see a trip

**Files:**
- Modify: `app/Livewire/Host/Trips/Create.php`, `resources/views/livewire/host/trips/create.blade.php` (review step, before the Back/Publish buttons)
- Test: `tests/Feature/TripVisibilityWizardTest.php`

**Interfaces:**
- Consumes: `Visibility::CATALOG`, `Visibility::WINDOWS`, `Visibility::keys()`, `Visibility::opensAt()`.
- Produces on `Create`: `public string $visibility = 'public'`; `public string $followersWindow = '3d'` (a `WINDOWS` key, or `'keep'` while editing a followers-first schedule); `public ?string $currentOpensAt = null` (display only, "5 Dec, 6:00 pm", set when editing a followers-first schedule); `private function listingAttributes(?Trip $editing): array` returning `['visibility' => string, 'public_at' => ?Carbon]`; both new fields added to `DRAFT_FIELDS`.
- Rules: `listingAttributes` gives `public` or `link` a null `public_at`; `followers` gets `Visibility::opensAt($window)`, except `keep` on an edited trip whose stored visibility is `followers` keeps its stored `public_at`. `publish()` calls it once and every schedule it creates gets the same result. Validation: `visibility` in `Visibility::keys()`, `followersWindow` in `array_keys(Visibility::WINDOWS)` or `keep`.

- [ ] **Step 1: Add shared wizard helpers to `tests/Pest.php`, then write the failing tests.** `wizardAtReview(User $host, int $dates = 1)` returns the `Livewire::actingAs($host)->test(Create::class)` testable at the review step with a valid form (the walk in the first test of `tests/Feature/TripWizardTest.php`, with `$dates` departures); `publishViaWizard(array $set = [], int $dates = 1): Collection` applies `$set` to it, calls `publish`, and returns the created trips oldest first. The tests go in `tests/Feature/TripVisibilityWizardTest.php`.

```php
it('defaults to everyone', fn () => expect(publishViaWizard()->first()->visibility)->toBe('public'));

it('gives every schedule in a group the same choice and the same opening date', function () {
    $trips = publishViaWizard(['visibility' => 'followers', 'followersWindow' => '1w'], dates: 2);
    expect($trips->pluck('visibility')->unique()->all())->toBe(['followers'])
        ->and($trips->pluck('public_at')->unique())->toHaveCount(1);
});

it('clears public_at for link only', fn () => expect(publishViaWizard(['visibility' => 'link'])->first()->public_at)->toBeNull());

it('keeps the opening date on "keep" and restarts it on a preset', function () {
    $trip = Trip::factory()->published()->followersFirst(now()->addDays(2))->create(['host_id' => $host->id]);
    Livewire::actingAs($host)->test(Create::class, ['trip' => $trip])->assertSet('followersWindow', 'keep')->call('publish');
    expect($trip->fresh()->public_at->equalTo($trip->public_at))->toBeTrue();
    Livewire::actingAs($host)->test(Create::class, ['trip' => $trip])->set('followersWindow', '1d')->call('publish');
    expect($trip->fresh()->public_at->between(now()->addHours(23), now()->addHours(25)))->toBeTrue();
});

it('edits one schedule without touching its siblings', function () {
    // two public schedules in one group; edit the first, set visibility 'link', publish
    expect($first->fresh()->visibility)->toBe('link')->and($second->fresh()->visibility)->toBe('public');
});

it('saves an effectively-public trip as everyone with no public_at', function () {
    $trip = Trip::factory()->published()->followersFirst(now()->subDay())->create(['host_id' => $host->id]);
    Livewire::actingAs($host)->test(Create::class, ['trip' => $trip])->assertSet('visibility', 'public')->call('publish');
    expect($trip->fresh()->visibility)->toBe('public')->and($trip->fresh()->public_at)->toBeNull();
});

it('restores the choice from a draft and inherits it on duplicate', function () {
    Livewire::actingAs($host)->test(Create::class)->set('visibility', 'link')->call('saveDraft');
    Livewire::actingAs($host)->test(Create::class)->assertSet('visibility', 'link');
    Livewire::actingAs($host)->test(Create::class, ['from' => $followersTrip])
        ->assertSet('visibility', 'followers')->assertSet('followersWindow', '3d');
});

it('rejects an unknown visibility', function () {
    wizardAtReview($host)->set('visibility', 'everybody')->call('publish')->assertHasErrors(['visibility']);
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/TripVisibilityWizardTest.php`
- [ ] **Step 3: Implement the properties, `listingAttributes`, validation, the `copyFrom` additions (editing loads `$trip->effectiveVisibility()`, `followersWindow = 'keep'` and `currentOpensAt` for a followers-first schedule; a duplicate inherits the choice with window `3d`), and pass the result into `applyTo`'s `fill`.** Add a trailing `array $listing` parameter to `applyTo` rather than recomputing per schedule.
- [ ] **Step 4: Add the review-step card "Who can see this trip".** Three radio cards from `Visibility::CATALOG` bound `wire:model.live="visibility"` with `dusk="visibility-{key}"`; when `followers` is chosen a select `wire:model="followersWindow"` (`dusk="followers-window"`) lists the presets, plus a first option "Keep: opens {currentOpensAt}" while editing a followers-first schedule. One line under each choice says what it does, in the spec's terms.
- [ ] **Step 5: Run the file plus the wizard's neighbours.** `./vendor/bin/pest tests/Feature/TripVisibilityWizardTest.php tests/Feature/TripWizardTest.php tests/Feature/TripDraftTest.php tests/Feature/TripScheduleTest.php` all pass.
- [ ] **Step 6: Commit.** `git add -A && git commit -m "feat: let a host choose who can see a trip in the wizard"`

---

### Task 4: My trips chip and per-offering control

**Files:**
- Create: `resources/views/components/visibility-chip.blade.php`, `tests/Browser/ListingControlFlowTest.php`
- Modify: `app/Livewire/Host/Trips/Index.php`, `resources/views/livewire/host/trips/index.blade.php`, `resources/views/components/trip-card.blade.php`, `resources/views/components/trip-tile.blade.php`
- Test: `tests/Feature/HostTripVisibilityTest.php`

**Interfaces:**
- Consumes: `Visibility::chip()`, `Visibility::WINDOWS`.
- Produces: `<x-visibility-chip :trip="$trip" />` (renders only when the signed-in user is that trip's host and `Visibility::chip($trip)` is non-null); `Host\Trips\Index::setVisibility(string $group, string $choice): void` where `$group` is the existing group key (`trip_group_id` or `solo-{id}`) and `$choice` is `public`, `link`, or `followers:1d|3d|1w`.

- [ ] **Step 1: Write the failing Feature tests.**

```php
it('sets one visibility on every schedule of an offering', function () {
    Livewire::actingAs($host)->test(HostTripsIndex::class)->call('setVisibility', $group, 'followers:1w');
    expect(Trip::where('trip_group_id', $group)->get()->pluck('visibility')->unique()->all())->toBe(['followers']);
});

it('ignores a group that belongs to another host', function () {
    Livewire::actingAs($other)->test(HostTripsIndex::class)->call('setVisibility', $group, 'link');
    expect(Trip::where('trip_group_id', $group)->value('visibility'))->toBe('public');
});

it('shows the chip to the trip\'s host and to nobody else', function () {
    expect(trim(Blade::render('<x-visibility-chip :trip="$t" />', ['t' => $linkOnlyTrip])))->toBe('');   // guest
    // actingAs($linkOnlyTrip->host): contains 'Link only'
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/HostTripVisibilityTest.php`
- [ ] **Step 3: Implement.** `setVisibility` loads `Trip::where('host_id', Auth::id())` for the group (a `solo-` key means a single id), then writes `visibility` and `public_at` as `Task 3`'s rules do. Each schedule row shows the chip; each offering header gets one `<select dusk="visibility-{group}">` that acts as an action menu (disabled placeholder "Who can see it", options Everyone / Followers first for 1 day, 3 days, 1 week / Link only) firing `wire:change="setVisibility('{group}', $event.target.value)"`. Add the chip to the trip card and tile.
- [ ] **Step 4: Write the Dusk test** `ListingControlFlowTest::test_a_host_can_take_an_offering_off_the_listings`: a host with one published trip logs in, opens `/host/trips`, picks "Link only" in the offering's select, waits for the text "Link only", then logs out and asserts `/trips` does not show the title while `/t/{slug}` still does.
- [ ] **Step 5: Build assets, then run.** `npm run build`, then `./vendor/bin/pest tests/Feature/HostTripVisibilityTest.php tests/Feature/HostManagementTest.php`, then Dusk `ListingControlFlowTest`, `TripWizardFlowTest` and `ContrastAuditTest` in one Dusk run as `docs/DUSK.md` describes (serve on :8030, then `php artisan dusk --filter 'ListingControlFlowTest|TripWizardFlowTest|ContrastAuditTest'`). Look at `/host/trips` on terra.test after it finishes.
- [ ] **Step 6: Commit and push slice 1.** `git add -A && git commit -m "feat: show and change a trip's visibility from my trips" && git push origin master`

---

### Task 5: Share previews and the default card

**Files:**
- Create: `app/Support/PageMeta.php`, `resources/views/components/seo.blade.php`, `public/images/og-card.png`
- Modify: `resources/views/components/layouts/site.blade.php` (head), `app/Livewire/Trips/Show.php`, `app/Livewire/Hosts/Show.php` (layout data)
- Test: `tests/Feature/PageMetaTest.php`

**Interfaces:**
- Produces `App\Support\PageMeta`: `static default(): array`, `static trip(Trip $trip): array`, `static host(User $host): array`, each returning `['title' => string, 'description' => string, 'image' => string, 'url' => string, 'noindex' => bool]` with `image` and `url` absolute. `trip`: title "{trip} · {host}", description with place, dates, "from {currency} {price} per person" and the host, image `cover_url`, url `route('trips.show')`, `noindex` when `effectiveVisibility() !== 'public'`. `host`: title "{name} on Tarana", description from `hostProfile->tagline` else "Group trips led by {name}", image `hostProfile->cover_url` else the default card, url `route('hosts.handle', $host)`. `default`: site title and tagline, `asset('images/og-card.png')`.
- Produces `<x-seo :meta="array|null" />`: description, canonical link, `robots` `noindex` only when set, `og:site_name|type|title|description|url|image`, `twitter:card` `summary_large_image`; `null` renders `PageMeta::default()`. Tags are written exactly like `<meta property="og:title" content="{{ ... }}">`, `<meta name="robots" content="noindex">` and `<link rel="canonical" href="...">` (double quotes, no self-closing slash), which the tests match on.

- [ ] **Step 1: Write the failing tests** in `tests/Feature/PageMetaTest.php`.

```php
it('previews a trip with its title, host, price and an absolute image', function () {
    $this->get(route('trips.show', $trip))
        ->assertSee('<meta property="og:title" content="Pulag Traverse · Ridgeline">', false)
        ->assertSee('<meta property="og:url" content="'.route('trips.show', $trip).'">', false)
        ->assertDontSee('noindex', false);
    expect(PageMeta::trip($trip)['image'])->toStartWith('http');
});

it('escapes quotes and ampersands in preview tags', function () {
    // title: Pulag "Sea of Clouds" & Beyond
    $this->get(route('trips.show', $t))->assertSee('Pulag &quot;Sea of Clouds&quot; &amp; Beyond', false);
});

it('gives an absolute image for an uploaded cover and a stock cover', function () {
    // trip with cover_photo set, and one without: both PageMeta::trip()['image'] start with http
});

it('marks any trip that is not public right now noindex', function () {
    // linkOnly and followersFirst(+3 days): noindex present; followersFirst(now()->subDay()): absent
});

it('previews a host page and falls back to the default card', function () {
    $this->get(route('hosts.handle', $host))->assertSee('og:title" content="Ridgeline on Tarana"', false);
    expect(PageMeta::host($hostWithoutCover)['image'])->toBe(asset('images/og-card.png'));
});

it('gives every other page the default card', function () {
    $this->get('/')->assertSee(asset('images/og-card.png'), false);
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/PageMetaTest.php`
- [ ] **Step 3: Implement `PageMeta` and `x-seo`.** Make relative URLs absolute with `url()`. Put `<x-seo :meta="$meta ?? null" />` in the site layout head. `Trips\Show` and `Hosts\Show` pass `'meta' => $meta, 'title' => $meta['title']` as layout data.
- [ ] **Step 4: Make the default card.** Write a throwaway HTML page in the scratchpad (pine `#2C3826` ground, the signpost mark from `components/wordmark.blade.php`, the lowercase word in Fredoka via `@font-face` on `public/fonts/fredoka.woff2`, "Group trips led by local hosts" in Geist) and render it: `"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --hide-scrollbars --force-device-scale-factor=1 --user-data-dir=<tmp> --window-size=1200,630 --screenshot=public/images/og-card.png file://<page>`. `sips -g pixelWidth -g pixelHeight public/images/og-card.png` must report 1200 and 630; open it to check it looks right.
- [ ] **Step 5: Run.** `./vendor/bin/pest tests/Feature/PageMetaTest.php tests/Feature/TripPageTest.php tests/Feature/HostPageTest.php tests/Feature/BrandTest.php` all pass.
- [ ] **Step 6: Commit.** `git add -A && git commit -m "feat: add share previews and a default card"`

---

### Task 6: Share panel and a host-page QR

**Files:**
- Create: `resources/views/components/share-panel.blade.php`, `app/Support/Qr.php`, `app/Http/Controllers/HostPageQrController.php`
- Modify: `app/Http/Controllers/TripQrController.php`, `routes/web.php` (host group), `app/Livewire/Host/Trips/Create.php` (flash on publish), `resources/views/livewire/host/trips/index.blade.php`, `resources/views/livewire/trips/show.blade.php`, `resources/views/livewire/hosts/show.blade.php`, `resources/views/livewire/host/profile.blade.php`
- Test: `tests/Feature/SharePanelTest.php`

**Interfaces:**
- Produces `<x-share-panel :url :title :qr="null" :open="false" />`: a button that toggles an Alpine panel holding a read-only field with the URL (`dusk="share-url"`), a Copy button using `navigator.clipboard`, a "Share…" button shown only where `navigator.share` exists, a Facebook link `https://www.facebook.com/sharer/sharer.php?u={url-encoded}` opening in a new tab, and, when `qr` is given, a QR download link.
- Produces `App\Support\Qr::png(string $data): Endroid\QrCode\Writer\Result\ResultInterface` (the builder `TripQrController` has today: pine foreground, white ground, size 600, margin 24, high correction), used by `TripQrController` and the new `HostPageQrController`.
- Produces route `host.profile.qr` (`GET /host/profile/qr`, inside the existing `auth` and `host` group) returning a PNG of `route('hosts.handle', Auth::user())`, `abort(404)` when the host has no handle, filename `tarana-{handle}-qr.png`.
- Produces a session flash `just_published` holding the first created trip's id, set in `Create::publish()` before the redirect.

- [ ] **Step 1: Write the failing tests.**

```php
it('renders the link, a Facebook share, the phone share hook and a QR only when given one', function () {
    $html = Blade::render('<x-share-panel url="https://tarana.test/t/x" title="X" qr="https://tarana.test/qr" />');
    expect($html)->toContain('value="https://tarana.test/t/x"')
        ->toContain('https://www.facebook.com/sharer/sharer.php?u='.urlencode('https://tarana.test/t/x'))
        ->toContain('navigator.share')->toContain('https://tarana.test/qr');
    expect(Blade::render('<x-share-panel url="https://tarana.test/t/x" title="X" />'))->not->toContain('download');
});

it('shows the trip page share panel to its host and to nobody else', function () {
    $this->actingAs($trip->host)->get(route('trips.show', $trip))->assertSee('dusk="share-url"', false);
    $this->get(route('trips.show', $trip))->assertDontSee('dusk="share-url"', false);
    $this->actingAs(User::factory()->create(['role' => 'host']))->get(route('trips.show', $trip))->assertDontSee('dusk="share-url"', false);
});

it('opens the panel for the host right after publishing', function () {
    $created = publishViaWizard()->first();
    expect(session('just_published'))->toBe($created->id);
    $this->actingAs($created->host)->withSession(['just_published' => $created->id])
        ->get(route('trips.show', $created))->assertSee('open: true', false);
});

it('shows the host page share panel to its host only', function () {
    $this->actingAs($host)->get(route('hosts.handle', $host))->assertSee('dusk="share-url"', false);
    $this->get(route('hosts.handle', $host))->assertDontSee('dusk="share-url"', false);
});

it('shows no host-page panel or QR for a host without a handle', function () {
    $this->actingAs($noHandleHost)->get(route('host.profile'))->assertDontSee('dusk="share-url"', false);
    $this->actingAs($noHandleHost)->get(route('host.profile.qr'))->assertNotFound();
});

it('serves the host page QR as a PNG to signed-in hosts only', function () {
    $this->actingAs($host)->get(route('host.profile.qr'))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(route('host.profile.qr'))->assertRedirect(route('login'));
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/SharePanelTest.php`
- [ ] **Step 3: Implement.** Extract `Qr::png`, point `TripQrController` at it, add the controller and route, set the `just_published` flash in `Create::publish()`. Place the panel: My trips row beside View and Edit (`:qr="route('host.trips.qr', $trip)"`); the trip page, as a slim bar under the hero shown only when `auth()->id() === $trip->host_id` and opened when `session('just_published') === $trip->id`; the host page's owner action row beside "New trip" (`:qr="route('host.profile.qr')"`); the sentence under "Your page" in the console, only when the host has a handle.
- [ ] **Step 4: Write the Dusk test** `ListingControlFlowTest::test_publishing_a_link_only_trip_opens_the_share_panel`: walk the wizard exactly as `TripWizardFlowTest::test_a_host_publishes_a_multi_destination_trip` does up to the review step, click `@visibility-link`, click `@wizard-publish`, wait until `@wizard-publish` is gone, then assert `@share-url` is visible on the trip page. No clipboard assertions.
- [ ] **Step 5: Build assets and run.** `npm run build`, then `./vendor/bin/pest tests/Feature/SharePanelTest.php tests/Feature/TripPageTest.php tests/Feature/HostPageTest.php tests/Feature/HostProfilePageTest.php tests/Feature/HostManagementTest.php`, then Dusk `--filter ListingControlFlowTest`.
- [ ] **Step 6: Commit and push slice 2.** `git add -A && git commit -m "feat: add a share panel and a host page qr" && git push origin master`

---

### Task 7: More from this host

**Files:**
- Modify: `app/Livewire/Trips/Show.php` (`render()` adds `$more`), `resources/views/livewire/trips/show.blade.php` (after the reviews block, still inside the left column)
- Test: `tests/Feature/MoreFromHostTest.php`

**Interfaces:**
- Consumes: `Trip::listedFor()`, `Trip::published()`, `Trip::collapseSchedules()`, `Trip::groupSiblings()`, `Trip::openDepartures()`, `x-scroller`, `x-trip-tile`.
- Produces: view data `$more` (a Collection of at most six trips): the same host's trips, excluding the viewed trip's offering (`groupSiblings()` ids), that have an open future departure, listed to the viewer (`published()` when the viewer is the host, else `listedFor(Auth::user())`), collapsed to one per offering. The section is headed "More from {host name}", links to the host page when the host has a handle, and is absent when `$more` is empty.

- [ ] **Step 1: Write the failing tests** in `tests/Feature/MoreFromHostTest.php`. Setup: host `$host` with the viewed trip `$a`, its sibling schedule `$sibling` (same `trip_group_id`), one other qualifying trip `$b`, a trip `$pastOnly` whose only departure is in the past, a `$link` link-only trip, and another host's trip `$otherHostTrip`.

```php
it('shows the host\'s other trips and never another host\'s', function () {
    $this->get(route('trips.show', $a))->assertSee('More from '.$host->name)->assertSee($b->title)->assertDontSee($otherHostTrip->title);
});

it('leaves out the offering being viewed, trips with nothing open, and unlisted trips for guests', function () {
    Livewire::test(TripShow::class, ['trip' => $a])
        ->assertViewHas('more', fn ($m) => $m->pluck('id')->diff([$b->id])->isEmpty());   // only $b qualifies
});

it('shows the host their own unlisted trips', function () {
    Livewire::actingAs($host)->test(TripShow::class, ['trip' => $a])
        ->assertViewHas('more', fn ($m) => $m->contains('id', $link->id));
});

it('shows one tile per offering and at most six', function () {
    // eight other offerings, one of them with two schedules
    Livewire::test(TripShow::class, ['trip' => $a])->assertViewHas('more', fn ($m) => $m->count() === 6);
});

it('is absent when the host has nothing else to show', function () {
    $this->get(route('trips.show', $onlyTrip))->assertDontSee('More from');
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/MoreFromHostTest.php`
- [ ] **Step 3: Implement the query and the section** with `<x-scroller :title="'More from '.$trip->host->name" :href="$hostUrl" class="!px-0">` and one `<x-trip-tile>` per trip.
- [ ] **Step 4: Run.** `./vendor/bin/pest tests/Feature/MoreFromHostTest.php tests/Feature/TripPageTest.php tests/Feature/TripDetailContentTest.php tests/Feature/TripScheduleTest.php`
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: show more from this host on the trip page"`

---

### Task 8: Guard test, demo data and the final targeted run

**Files:**
- Create: `tests/Feature/HostOwnPagesTest.php`
- Modify: `database/seeders/FakeDataSeeder.php`
- Test: `tests/Feature/FakeDataSeederTest.php` (existing; counts must not change)

- [ ] **Step 1: Write the guard test.** Helper `ownPagesHost(string $name, string $tripTitle)` builds a host with a handle, a published trip and an open future departure.

```php
it('never shows another host on a host\'s own pages, and never takes Explore away', function () {
    // $mine = Ridgeline / "Pulag Sea of Clouds"; $other = Summit Co / "Rinjani Crater Rim"; a booking on $mine's departure
    foreach ([route('hosts.handle', $mine->host), route('trips.show', $mine->trip), route('trips.join', $mine->trip),
              route('reservations.show', ['token' => $booking->public_token])] as $url) {
        $this->get($url)->assertOk()
            ->assertDontSee('Summit Co')->assertDontSee('Rinjani Crater Rim')
            ->assertSee(route('trips.index'), false);
    }
});
```
- [ ] **Step 2: Run it.** `./vendor/bin/pest tests/Feature/HostOwnPagesTest.php` passes (nothing to implement: it locks today's behaviour plus Task 7's new section).
- [ ] **Step 3: Seed demo visibility.** In `FakeDataSeeder`, mark two of the first host's existing published trips, one `link` and one `followers` with `public_at` three days out. No rows are added, so the seeder's count assertions hold. Run `./vendor/bin/pest tests/Feature/FakeDataSeederTest.php`.
- [ ] **Step 4: Final targeted run.** Pest: `TripVisibilityTest ListingVisibilityTest TripVisibilityWizardTest HostTripVisibilityTest PageMetaTest SharePanelTest MoreFromHostTest HostOwnPagesTest FakeDataSeederTest`. Dusk, one run, as in `docs/DUSK.md`: `--filter 'ListingControlFlowTest|TripWizardFlowTest|TripDuplicateFlowTest|ContrastAuditTest'`. After Dusk finishes, `npm run build` if needed, then check terra.test: My trips chips and select, the share panel on a trip and on the host page, "More from this host", and a pasted trip link's source for `og:` tags.
- [ ] **Step 5: Commit and push slice 3.** `git add -A && git commit -m "test: keep other hosts off a host's own pages" && git push origin master`
