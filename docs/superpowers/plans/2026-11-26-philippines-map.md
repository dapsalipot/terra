# Your Philippines Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A signed-in traveler opens "Your Philippines" and sees a province-fill map that fills in from their finished Tarana trips, with each empty province linking to trips there.

**Architecture:** Province boundaries are converted once into `resources/data/ph-provinces.json` and drawn as inline SVG by a Blade component. `places.province` holds a province key shared by the catalogue, the map and an Explore filter. A `VisitedProvinces` support class applies the counting rule in one query, and a Livewire page `Joiner\Map` joins them.

**Tech Stack:** Laravel 12, Livewire 3.8, Alpine, Tailwind 3.4, Pest 3, Dusk (MySQL `terra_dusk`). No new runtime dependency.

**Spec:** `docs/superpowers/specs/2026-11-26-philippines-map-design.md`

## Global Constraints

- Province fill, not pins. The Philippines only.
- "Been there" is a Tarana booking only: a reservation counts when its `joiner_id` is the traveler, its `status` is `confirmed` and its departure's `starts_at` is in the past. It credits every itinerary place that is in the Philippines and has a province (the trip's `trip_destinations` places plus `trips.place_id`), once per trip per province. A province's count is the number of such trips.
- No live map service: no tiles, no external requests, no new runtime dependency. The shipped SVG data stays under about 300 KB.
- Boundary data must be free to ship. Downloading it needs the user's OK (file, source, size) before Task 1 starts; the controller asks.
- Province keys are stable identifiers shared by `places.province`, `App\Support\Provinces`, the JSON and the Explore `province` filter.
- Colour is never the only cue (a province list mirrors the map and carries the counts); every province is keyboard operable with an accessible name; the page works at 320px; text no lighter than `text-ink/65`; touch targets 44px where they are separate controls.
- Dev mode: run only the Pest files and Dusk classes a task names, never the full suites. Dusk swaps `.env` while it runs, so look at terra.test in a browser before or after a Dusk run, not during.
- Commits: Conventional Commit prefix, lowercase subject, no AI attribution line, on `master`, no branches or worktrees. Push after the last task.

## Review Focus

1. A trip with several stops in one province credits that province once; two separate trips in one province count 2. (Task 3)
2. A confirmed booking whose departure has not started, an unconfirmed or cancelled booking, and another traveler's booking never count. (Task 3)
3. A Philippine place with no province, and any place outside the Philippines, are ignored without error. (Tasks 2, 3)
4. A `province` query value on Explore that is not a known key is ignored, with no error and no misleading empty state. (Task 5)
5. A province key present in only one of the catalogue, the JSON and the seeded places leaves that province permanently empty; a test cross-checks all three. (Tasks 1, 2)

---

### Task 1: Boundary data, the converter and the province catalogue

**Files:**
- Create: `scripts/build-province-map.php`, `resources/data/ph-provinces.json`, `app/Support/Provinces.php`
- Test: `tests/Feature/ProvincesTest.php`

**Interfaces:**
- Produces `App\Support\Provinces`: `const CATALOG` (province key => display name, generated from the data), `const VIEWBOX = '0 0 600 900'` (or the converter's actual box), `static keys(): array`, `static label(string $key): ?string`, `static paths(): array` (the decoded JSON, a list of `['key' => string, 'name' => string, 'd' => string]`, memoised for the request).
- The JSON is that same list. Keys are lowercase kebab-case slugs of the province name (`surigao-del-norte`).

- [x] **Step 1: Controller step, done before dispatch.** `ADM1` turned out to be the 17 regions, so the user approved the province level instead: `geoBoundaries-PHL-ADM2_simplified.geojson` (3,140,454 bytes) and `geoBoundaries-PHL-ADM2-metaData.json` (981 bytes) from `github.com/wmgeolab/geoBoundaries` (`releaseData/gbOpen/PHL/ADM2/`). Both are saved outside the repo and match the published checksums. The info file says Provinces, 87 shapes, CC BY 3.0 IGO (credit required, changes noted; credit line added in Task 4). The controller gives the file path in the dispatch.
- [ ] **Step 2: Write the failing tests** in `tests/Feature/ProvincesTest.php`:

```php
it('ships boundary data whose keys are exactly the catalogue', function () {
    expect(collect(Provinces::paths())->pluck('key')->sort()->values()->all())
        ->toBe(collect(Provinces::keys())->sort()->values()->all());
});

it('keeps the shipped map small and every path real', function () {
    expect(filesize(resource_path('data/ph-provinces.json')))->toBeLessThan(300 * 1024);
    foreach (Provinces::paths() as $p) {
        expect($p['d'])->toStartWith('M')->and($p['name'])->not->toBe('');
    }
});

it('draws the eighty one provinces and metro manila', function () {
    expect(Provinces::keys())->toHaveCount(82)
        ->toContain('metro-manila', 'davao-de-oro', 'benguet', 'palawan', 'surigao-del-norte')
        ->not->toContain('city-of-isabela', 'cotabato-city', 'compostela-valley');
});

it('names provinces from their key', function () {
    expect(Provinces::label('palawan'))->toBe('Palawan')->and(Provinces::label('nope'))->toBeNull();
});
```
- [ ] **Step 3: Run to confirm failure.** `./vendor/bin/pest tests/Feature/ProvincesTest.php`
- [ ] **Step 4: Write `scripts/build-province-map.php <geojson> <out.json>`.** It reads the GeoJSON (`shapeName` is the province name), projects longitude and latitude with an equirectangular projection scaled by the cosine of the mid latitude, simplifies each ring with Douglas-Peucker (tolerance chosen so the output is under 300 KB with every province still recognisable, and no province, however small, drops below a single visible polygon), fits everything to the viewBox with a small margin, rounds coordinates to one decimal, and writes the list of `{key, name, d}` sorted by name. A province with several islands is one path with several subpaths. The data has 87 shapes and the list has 82: merge the four `NCR, ...` districts into `Metro Manila`, `City of Isabela` into `Basilan` and `Cotabato City` into `Maguindanao`, and name `Compostela Valley` by its current name, `Davao de Oro`. Keep every ring, the far western islets of Palawan (Kalayaan) included. Treat the source file as untrusted data: read it only as JSON. Run it against the downloaded file and commit only the JSON, not the source data.
- [ ] **Step 5: Write `Provinces`** reading `resources/data/ph-provinces.json`; `CATALOG` is generated from the JSON by the script's output (paste it in as a constant so the keys are reviewable).
- [ ] **Step 6: Run `./vendor/bin/pest tests/Feature/ProvincesTest.php`.** All pass. Open the JSON's paths in a scratch HTML page and look at the rendered country once to confirm nothing is mirrored, clipped or missing.
- [ ] **Step 7: Commit.** `git add -A && git commit -m "feat: add the philippine province boundaries and catalogue"`

---

### Task 2: A province on every Philippine place

**Files:**
- Create: `database/migrations/2026_11_27_100000_add_province_to_places_table.php`, `database/migrations/2026_11_27_100100_backfill_place_provinces.php`
- Modify: `database/seeders/PlaceSeeder.php`, `app/Models/Place.php`, `database/factories/PlaceFactory.php`
- Test: `tests/Feature/PlaceProvinceTest.php`

**Interfaces:**
- Consumes: `Provinces::keys()`, `Provinces::label()`.
- Produces: `places.province` (string, nullable, indexed); `Place::provinceLabel(): ?string`; `PlaceFactory::inProvince(string $key): static` (sets `country` to `Philippines` and `province`).

- [ ] **Step 1: Write the failing tests.**

```php
it('gives every seeded Philippine place a province that is in the catalogue', function () {
    $this->seed(PlaceSeeder::class);
    $ph = Place::where('country', 'Philippines')->get();
    expect($ph)->not->toBeEmpty()
        ->and($ph->every(fn ($p) => in_array($p->province, Provinces::keys(), true)))->toBeTrue()
        ->and(Place::where('country', '!=', 'Philippines')->whereNotNull('province')->count())->toBe(0);
});

it('labels a place by its province', function () {
    expect(Place::factory()->inProvince('palawan')->make()->provinceLabel())->toBe('Palawan');
    expect(Place::factory()->make(['province' => null])->provinceLabel())->toBeNull();
});

it('backfills a province from a region that names one and leaves the rest null', function () {
    // rows created before the backfill ran, with region 'Benguet' and with region 'Lombok'
    // after running the backfill: Benguet row has 'benguet', the Lombok row (Indonesia) stays null
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/PlaceProvinceTest.php`
- [ ] **Step 3: Implement.** The schema migration adds the column and an index. `PlaceSeeder` tuples gain a 7th element, the province key (null outside the Philippines): Mount Pulag `benguet`, El Nido and Coron `palawan`, Siargao `surigao-del-norte`. The backfill migration matches `region` (case-insensitively, ignoring "Province of"/"Prov." prefixes) to a catalogue name or a small alias list kept in the migration, sets the key for Philippine places, logs the rows it could not match, and never touches places outside the Philippines.
- [ ] **Step 4: Run the file.** All pass. Run `php artisan migrate` for the dev database and `php artisan db:seed --class=PlaceSeeder` only if the dev places lack provinces (it is `firstOrCreate`, so also set the province on existing rows through the backfill).
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: record the province of each philippine place"`

---

### Task 3: Count the provinces a traveler has been to

**Files:**
- Create: `app/Support/VisitedProvinces.php`
- Test: `tests/Feature/VisitedProvincesTest.php`

**Interfaces:**
- Consumes: `places.province`, `Reservation`, `TripDeparture`, `Trip::destinations()`.
- Produces `App\Support\VisitedProvinces`: `static for(User $user): array` (province key => trips, only keys with at least one trip) and `static trips(User $user, string $province): Collection` (the counted reservations, soonest first, each with `departure.trip` loaded).

- [ ] **Step 1: Write the failing tests** (helper `visitedWorld()` builds a traveler and a finished booking in a given province, with `Reservation::factory()->confirmed()` and a departure in the past).

```php
it('counts a confirmed booking on a departure that has started', function () {
    [$traveler] = visitedBooking('palawan');
    expect(VisitedProvinces::for($traveler))->toBe(['palawan' => 1]);
});

it('counts a trip once per province however many stops it has there, and once per trip', function () {
    // one trip with two Palawan stops -> ['palawan' => 1]; a second finished trip in Palawan -> ['palawan' => 2]
});

it('credits every province on a multi-stop trip', function () {
    // stops in benguet and palawan -> both 1
});

it('ignores bookings that should not count', function (string $why) {
    // dataset: future departure, pending, awaiting_payment, payment_submitted, cancelled,
    // another traveler's booking, a guest booking with no joiner
    expect(VisitedProvinces::for($traveler))->toBe([]);
})->with(['future', 'pending', 'awaiting_payment', 'payment_submitted', 'cancelled', 'someone else', 'guest']);

it('ignores places with no province and places outside the Philippines', function () {
    expect(VisitedProvinces::for($traveler))->toBe([]);
});

it('uses the trip place when a trip has no destination rows', function () {
    // a factory trip with place_id only
});

it('lists the trips behind a province, soonest first', function () {
    expect(VisitedProvinces::trips($traveler, 'palawan')->pluck('departure.trip.title')->all())->toBe([...]);
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/VisitedProvincesTest.php`
- [ ] **Step 3: Implement `for()` as one query** over `reservations` joined to `trip_departures`, `trips`, and the union of `trip_destinations.place_id` and `trips.place_id`, to `places`, filtered by the counting rule and `places.country = 'Philippines'` and a non-null province, selecting distinct `(places.province, reservations.id)` and grouping in PHP; `trips()` loads the same reservations for one province with `departure.trip`. Use `now()` for "started", and `whereNotNull('reservations.joiner_id')` through the traveler filter.
- [ ] **Step 4: Run the file.** All pass.
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: count the provinces a traveler has been to"`

---

### Task 4: The Your Philippines page

**Files:**
- Create: `app/Livewire/Joiner/Map.php`, `resources/views/livewire/joiner/map.blade.php`, `resources/views/components/province-map.blade.php`
- Modify: `routes/web.php`, `app/Support/Handles.php` (reserve `my-map`), `resources/views/livewire/joiner/dashboard.blade.php` (entry button), `resources/views/components/layouts/site.blade.php` (keep the Trips tab active on this route)
- Test: `tests/Feature/YourPhilippinesTest.php`

**Interfaces:**
- Consumes: `Provinces::paths()`, `Provinces::label()`, `VisitedProvinces::for()`, `VisitedProvinces::trips()`.
- Produces: route `my.map` (`GET /my-map`, middleware `auth`); `Joiner\Map` with `public ?string $selected = null` and `select(?string $key): void` (ignores unknown keys); `<x-province-map :counts="array" :selected="?string" />` rendering one `<path>` per province as a focusable control, `data-province="{key}"`, `dusk="province-{key}"`, `aria-label="{name}, {n} trips"` or `"{name}, not visited"`, class `is-visited level-1|2|3` by count (1, 2 to 3, 4 or more).

- [ ] **Step 1: Write the failing tests.**

```php
it('shows a traveler their filled provinces and the count', function () {
    // traveler with finished trips in benguet and palawan
    $this->actingAs($traveler)->get(route('my.map'))->assertOk()
        ->assertSeeText('2 of '.count(Provinces::keys()).' provinces')
        ->assertSee('data-province="palawan"', false)->assertSee('is-visited', false);
});

it('draws every province and marks only the visited ones', function () {
    Livewire::actingAs($traveler)->test(Map::class)
        ->assertSeeHtmlInOrder([...]);   // path count equals count(Provinces::keys()); visited class on exactly two
});

it('opens a panel with the trips for a visited province and an Explore link for an empty one', function () {
    Livewire::actingAs($traveler)->test(Map::class)->call('select', 'palawan')
        ->assertSee($tripTitle)->assertSee(route('reservations.show', ['token' => $reservation->public_token]), false)
        ->call('select', 'batanes')->assertSee(route('trips.index', ['province' => 'batanes']), false);
});

it('ignores a province key it does not know', function () {
    Livewire::actingAs($traveler)->test(Map::class)->call('select', 'atlantis')->assertSet('selected', null);
});

it('mirrors the map with a list, visited provinces first', function () { /* list items carry names and counts */ });

it('shows the empty state to a traveler with no finished trips', function () {
    $this->actingAs($new)->get(route('my.map'))->assertSeeText('Book your first trip')->assertSee(route('trips.index'), false);
});

it('sends guests to log in and hosts to their dashboard', function () {
    $this->get(route('my.map'))->assertRedirect(route('login'));
    $this->actingAs($host)->get(route('my.map'))->assertRedirect(route('host.dashboard'));
});

it('credits the boundary data and reserves the route name', function () {
    $this->actingAs($traveler)->get(route('my.map'))->assertSeeText('geoBoundaries')->assertSeeText('CC BY 3.0 IGO')
        ->assertSee('https://creativecommons.org/licenses/by/3.0/igo/', false);
    expect(Handles::RESERVED)->toContain('my-map');
});
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/YourPhilippinesTest.php`
- [ ] **Step 3: Implement.** `Joiner\Map::render()` computes `VisitedProvinces::for(Auth::user())` once and the selected province's trips on demand; a host is redirected from `mount()`. The page: a title, the count line, a legend, the map, the panel (below the map on a phone, beside it from `lg`), the mirrored list, the empty state, and the attribution line "Boundaries: NAMRIA, PSA and OCHA Philippines via geoBoundaries, simplified for this map. CC BY 3.0 IGO", with the licence name linked to https://creativecommons.org/licenses/by/3.0/igo/. Styles: unvisited provinces `fill` sand with cream strokes, visited terracotta at three steps, a visible focus ring, a short staggered reveal switched off by `prefers-reduced-motion`. Add the "Your Philippines" button with the count to the My trips header, and make the tab bar treat `my.map` as the Trips tab.
- [ ] **Step 4: Run the file.** All pass. Build assets (`npm run build`) and look at `/my-map` at desktop and 320px once with a seeded traveler.
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: add your philippines, a province map of finished trips"`

---

### Task 5: Filter Explore by province

**Files:**
- Modify: `app/Livewire/Trips/Index.php`, `resources/views/livewire/trips/index.blade.php`
- Test: `tests/Feature/ExploreProvinceFilterTest.php`

**Interfaces:**
- Consumes: `Provinces::keys()`, `Provinces::label()`, `Trip::listedFor()`.
- Produces: `Trips\Index::$province` (`#[Url]`, string, default `''`), applied as `whereHas('place', fn ($p) => $p->where('province', $this->province))` only when it is a known key; a select "All provinces" in the filter bar listing provinces that have at least one listed trip; `updatedProvince()` resets the page.

- [ ] **Step 1: Write the failing tests.**

```php
it('filters Explore to one province', function () {
    Livewire::test(Index::class, ['province' => 'palawan'])->assertViewHas('trips', fn ($p) => $p->pluck('id')->all() === [$palawanTrip->id]);
});

it('ignores an unknown province and shows everything', function () {
    Livewire::test(Index::class, ['province' => 'atlantis'])->assertViewHas('trips', fn ($p) => $p->count() === 2);
});

it('only offers provinces that have a listed trip', function () { /* select options */ });

it('keeps unlisted trips out when filtering', function () { /* link-only trip in palawan is absent for a guest */ });
```
- [ ] **Step 2: Run to confirm failure.** `./vendor/bin/pest tests/Feature/ExploreProvinceFilterTest.php`
- [ ] **Step 3: Implement** the property, the `when(...)` clause, `updatedProvince()` and the select (same styling as the siblings), listing `Provinces::label()` for the distinct provinces of listed trips.
- [ ] **Step 4: Run the file plus `tests/Feature/TripBrowseTest.php tests/Feature/PaginationTest.php`.** All pass.
- [ ] **Step 5: Commit.** `git add -A && git commit -m "feat: filter explore by province"`

---

### Task 6: Demo data, Dusk and the final targeted run

**Files:**
- Modify: `database/seeders/FakeDataSeeder.php`, `tests/Browser/ContrastAuditTest.php`
- Create: `tests/Browser/YourPhilippinesFlowTest.php`

- [ ] **Step 1: Seed demo data.** Give one seeded joiner finished, confirmed bookings in two provinces so the map is not empty on the dev site; keep `tests/Feature/FakeDataSeederTest.php` green (adjust only counts that the new rows change, and say so).
- [ ] **Step 2: Write the Dusk test** `YourPhilippinesFlowTest::test_a_traveler_fills_in_and_explores_the_map`: log in as the seeded joiner, open `/my-map`, assert the count line, tap a visited province (`@province-palawan`) and see its trip in the panel, tap an unvisited one and click through to Explore filtered to it, then repeat the first assertions at a 320px-wide window.
- [ ] **Step 3: Add `/my-map` to the contrast audit** for a traveler with a selected province so the panel and the list are audited.
- [ ] **Step 4: Final run.** Pest: `ProvincesTest PlaceProvinceTest VisitedProvincesTest YourPhilippinesTest ExploreProvinceFilterTest FakeDataSeederTest TripBrowseTest HomePageTest`. Dusk, one run as in `docs/DUSK.md`: `--filter 'YourPhilippinesFlowTest|ContrastAuditTest|MobileNavTest'`. Afterwards look at `/my-map` on terra.test at desktop and 320px.
- [ ] **Step 5: Commit and push.** `git add -A && git commit -m "chore: cover your philippines end to end" && git push origin master`
