# Terra 01 — Foundation & Public Browsing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the Terra Laravel + Livewire web app with the Trail Earth design system, the read-side data model (places, hosts, trips, departures), and the public browsing surfaces (home, trip browse/search, trip detail, host page) — a working, seeded, testable site with no auth-gated actions yet.

**Architecture:** Server-rendered Laravel 11 + Livewire 3 (class-based components) with Tailwind v3. This plan builds only the read side: no reservations, payments, chat, or reviews (those are later plans). It ends with a browsable, seeded marketplace at `terra.test`.

**Tech Stack:** Laravel 11, Livewire 3, Tailwind CSS v3, Laravel Breeze (Livewire stack — installed here but auth flows customized in Plan 02), Pest 3, MySQL, `spatie/laravel-sluggable`.

## Global Constraints

Every task's requirements implicitly include these — copied verbatim from `docs/specs/2026-07-18-terra-mvp-design.md`:

- **Framework:** Laravel `^11.0`, Livewire `^3.4`, PHP 8.2, MySQL. Tests in **Pest**. No Bootstrap.
- **Location:** app lives at `~/Herd/terra` (served at `terra.test` by Herd); `design/` and `docs/` folders coexist alongside the Laravel app and must not be deleted.
- **Account model:** role-locked — `users.role` is `joiner` or `host`, chosen at signup, never switched.
- **Money model:** Terra never touches money. Payment methods are host-configured; prices render in the host's currency. (No payment code in this plan, but no schema may assume Terra holds funds.)
- **Trip model:** a `trip` is a reusable offering (`draft → published → archived`) with 1..n `trip_departures` (`open → full → completed → cancelled`); capacity and rosters are per-departure.
- **Reservation states (later plans):** `pending → awaiting_payment → payment_submitted → confirmed → cancelled`. Do not build these here, but leave the departures/trip schema ready for them.
- **International:** places are global mountains, English copy, host currency codes (ISO 4217).
- **Design palette (exact):** Sand `#F1EBDD`, off-white bg `#FBF9F4`, surface `#FFFFFF`, Pine `#2C3826`, Moss `#5E6B44`, ink `#1F1C15`, Terracotta `#BC4B26`, Amber `#A8761F`, cream `#F7F4EC`. **Buttons are only Pine or Moss.** Terracotta = saved/summit/destructive only. Amber = waiting states only.
- **Type:** Fraunces (variable webfont) restricted to trip names and prices; all other UI text is system sans, headings at weight 700. Monospace for references/permit numbers.
- **Icons:** one drawn SVG set, 24px viewBox, 1.7px stroke, `currentColor`, as Blade components. **No emoji in UI chrome.**
- **Brand signatures (only these three):** dashed trail line for progress/sequence; elevation/mountain graphic language; Fraunces on names & prices.

---

### Task 1: Scaffold the Laravel + Livewire + Tailwind + Pest app

**Files:**
- Create: entire Laravel skeleton under `~/Herd/terra/` (composer create-project output)
- Modify: `~/Herd/terra/.env` (DB + app URL)
- Create: `~/Herd/terra/database/database.sqlite` is NOT used — we use MySQL `terra`

**Interfaces:**
- Produces: a booting app at `https://terra.test`, `php artisan test` green, Livewire + Tailwind + Pest installed.

- [ ] **Step 1: Create the project without clobbering `design/` and `docs/`**

```bash
cd ~/Herd
composer create-project laravel/laravel:^11.0 terra_tmp
# move app files into terra/ (which already holds design/ and docs/)
shopt -s dotglob
mv terra_tmp/* terra/
rm -rf terra_tmp
cd ~/Herd/terra
git init            # composer create-project ships no VCS history; design/ and docs/ get committed too
```

> **Note on commits:** this plan commits after every task (TDD discipline). That's part of executing the plan you're approving — not auto-committing outside it. If you'd rather stage without committing, say so and I'll drop the commit steps.

- [ ] **Step 2: Create the MySQL database and point `.env` at it**

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS terra CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Edit `.env`:
```
APP_NAME=Terra
APP_URL=https://terra.test
DB_CONNECTION=mysql
DB_DATABASE=terra
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
```

- [ ] **Step 3: Install Livewire, Breeze (Livewire stack), Pest, sluggable**

```bash
composer require livewire/livewire:^3.4 spatie/laravel-sluggable
composer require laravel/breeze --dev
php artisan breeze:install livewire --pest
npm install
```

- [ ] **Step 4: Run the default test suite to confirm a green baseline**

Run: `php artisan test`
Expected: PASS (Breeze ships auth tests; all green).

- [ ] **Step 5: Build assets and smoke-test the site boots**

```bash
npm run build
php artisan migrate
curl -skI https://terra.test | head -1
```
Expected: `HTTP/2 200`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 11 + Livewire + Breeze + Pest + Tailwind"
```

---

### Task 2: Trail Earth design tokens, fonts, and base layout

**Files:**
- Modify: `tailwind.config.js` (colors, fontFamily)
- Modify: `resources/css/app.css` (font-face, base tokens, skeleton util)
- Create: `resources/views/components/layouts/site.blade.php` (public shell: nav + footer)
- Create: `resources/views/components/wordmark.blade.php`
- Create: `resources/views/components/icon/` — `mountain.blade.php`, `search.blade.php`, `heart.blade.php`, `star.blade.php`, `check.blade.php`, `user.blade.php`, `pin.blade.php`, `calendar.blade.php` (24px viewBox, 1.7 stroke, currentColor)
- Download: Fraunces variable woff2 into `public/fonts/fraunces.woff2`
- Test: `tests/Feature/DesignSystemTest.php`

**Interfaces:**
- Produces: `<x-layouts.site>` slot layout; `<x-wordmark />`; `<x-icon.mountain />` etc.; Tailwind classes `bg-pine text-moss bg-sand` resolve to spec hex.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/DesignSystemTest.php
it('renders the site layout with the Terra wordmark and no emoji in chrome', function () {
    $html = Blade::render('<x-layouts.site><p>hello</p></x-layouts.site>');
    expect($html)->toContain('Terra')          // wordmark present
        ->toContain('hello')                    // slot renders
        ->not->toMatch('/[\x{1F300}-\x{1FAFF}]/u'); // no emoji in chrome
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DesignSystemTest`
Expected: FAIL (`x-layouts.site` view not found).

- [ ] **Step 3: Add tokens to `tailwind.config.js`**

```js
// tailwind.config.js — extend theme
theme: { extend: {
  colors: {
    sand: '#F1EBDD', canvas: '#FBF9F4', surface: '#FFFFFF',
    pine: '#2C3826', moss: '#5E6B44', ink: '#1F1C15',
    terra: '#BC4B26', amber: '#A8761F', cream: '#F7F4EC',
  },
  fontFamily: {
    serif: ['Fraunces', 'Georgia', 'serif'],
    sans: ['"Avenir Next"', 'Avenir', '"Helvetica Neue"', 'Arial', 'sans-serif'],
    mono: ['ui-monospace', 'Menlo', 'monospace'],
  },
}},
```

- [ ] **Step 4: Add the font-face and download Fraunces**

```bash
curl -s "https://fonts.gstatic.com/s/fraunces/v38/6NU78FyLNQOQZAnv9bYEvDiIdE9Ea92uemAk_WBq8U_9v0c2Wa0K9C1Zf9-V.woff2" -o public/fonts/fraunces.woff2
```
In `resources/css/app.css` (top):
```css
@font-face { font-family:'Fraunces'; src:url('/fonts/fraunces.woff2') format('woff2'); font-weight:100 900; font-display:swap; }
body { background:#FBF9F4; color:#1F1C15; }
.skeleton { background:linear-gradient(90deg,#F1EBDD,#F7F4EC,#F1EBDD); background-size:200% 100%; animation:sk 1.4s infinite; }
@keyframes sk { to { background-position:-200% 0; } }
```

- [ ] **Step 5: Create the wordmark, icon components, and site layout**

`resources/views/components/wordmark.blade.php`:
```blade
<span class="inline-flex items-center gap-2">
  <span class="grid place-items-center w-7 h-7 rounded-full bg-pine">
    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 fill-cream"><path d="M3 19.5l6.4-9.7 3.7 5.1 3.2-4.7 6.3 9.3z"/></svg>
  </span>
  <span class="font-serif font-semibold text-xl">Terra</span>
</span>
```

Example icon `resources/views/components/icon/mountain.blade.php` (repeat pattern per icon with its own path):
```blade
@props(['class' => 'w-5 h-5'])
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
  <path d="M2.5 19.5l6.2-9.4 3.6 5 3.1-4.6 6.1 9z"/><path d="M8.7 10.1l2.3 3.4"/>
</svg>
```

`resources/views/components/layouts/site.blade.php`:
```blade
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title ?? 'Terra' }}</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased">
  <nav class="h-[68px] sticky top-0 z-20 flex items-center gap-7 px-10 bg-canvas/90 backdrop-blur border-b border-ink/5">
    <a href="/"><x-wordmark /></a>
    <div class="flex gap-6 ml-3 text-sm font-semibold text-ink/60">
      <a href="{{ route('trips.index') }}">Explore</a>
      <a href="{{ route('hosts.index') }}">Hosts</a>
    </div>
    <div class="ml-auto flex items-center gap-4">
      <a href="{{ route('login') }}" class="text-sm font-semibold">Log in</a>
      <a href="{{ route('register') }}" class="text-sm font-semibold text-cream bg-pine rounded-full px-4 py-2.5">Sign up</a>
    </div>
  </nav>
  <main>{{ $slot }}</main>
  <footer class="mt-24 border-t border-ink/5 px-10 py-10 text-sm text-ink/50"><x-wordmark /> · trips worth joining</footer>
  @livewireScripts
</body>
</html>
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=DesignSystemTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: Trail Earth design tokens, wordmark, icons, site layout"
```

---

### Task 3: `places` table, model, factory, and seeder

**Files:**
- Create: `database/migrations/xxxx_create_places_table.php`
- Create: `app/Models/Place.php`
- Create: `database/factories/PlaceFactory.php`
- Create: `database/seeders/PlaceSeeder.php`
- Test: `tests/Feature/PlaceTest.php`

**Interfaces:**
- Produces: `Place` model with `name, country, region, elevation_m, difficulty (enum easy|challenging|hard), slug, aggregate_rating (decimal, nullable), reviews_count (int)`. `Place::factory()`. `PlaceSeeder` inserts ≥8 real international mountains.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/PlaceTest.php
use App\Models\Place;
it('creates a place with a slug and difficulty', function () {
    $p = Place::factory()->create(['name' => 'Mount Rinjani', 'difficulty' => 'challenging']);
    expect($p->slug)->toBe('mount-rinjani')->and($p->difficulty)->toBe('challenging');
});
it('seeds at least 8 international mountains', function () {
    $this->seed(\Database\Seeders\PlaceSeeder::class);
    expect(Place::count())->toBeGreaterThanOrEqual(8)
        ->and(Place::where('country','Indonesia')->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PlaceTest`
Expected: FAIL (`App\Models\Place` not found).

- [ ] **Step 3: Migration**

```php
Schema::create('places', function (Blueprint $t) {
    $t->id();
    $t->string('name'); $t->string('slug')->unique();
    $t->string('country'); $t->string('region')->nullable();
    $t->unsignedInteger('elevation_m')->nullable();
    $t->enum('difficulty', ['easy','challenging','hard'])->default('challenging');
    $t->decimal('aggregate_rating', 2, 1)->nullable();
    $t->unsignedInteger('reviews_count')->default(0);
    $t->timestamps();
});
```

- [ ] **Step 4: Model with sluggable**

```php
class Place extends Model {
    use HasFactory, \Spatie\Sluggable\HasSlug;
    protected $guarded = [];
    public function getSlugOptions(): \Spatie\Sluggable\SlugOptions {
        return \Spatie\Sluggable\SlugOptions::create()->generateSlugsFrom('name')->saveSlugsTo('slug');
    }
    public function trips() { return $this->hasMany(Trip::class); }
}
```

- [ ] **Step 5: Factory + seeder**

```php
// PlaceFactory
public function definition(): array {
    return ['name'=>fake()->unique()->city().' Mountain','country'=>fake()->country(),
            'elevation_m'=>fake()->numberBetween(800,4000),'difficulty'=>'challenging'];
}
// PlaceSeeder — real mountains
$rows = [
  ['Mount Rinjani','Indonesia','Lombok',3726,'hard'],
  ['Mount Batur','Indonesia','Bali',1717,'easy'],
  ['Mount Fuji','Japan','Shizuoka',3776,'challenging'],
  ['Mount Kinabalu','Malaysia','Sabah',4095,'hard'],
  ['Mount Batulao','Philippines','Batangas',811,'easy'],
  ['Mount Pulag','Philippines','Benguet',2926,'challenging'],
  ['Ijen','Indonesia','Java',2799,'challenging'],
  ['Mount Merbabu','Indonesia','Java',3145,'challenging'],
];
foreach ($rows as [$n,$c,$r,$e,$d]) Place::create(['name'=>$n,'country'=>$c,'region'=>$r,'elevation_m'=>$e,'difficulty'=>$d]);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=PlaceTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: places table, model, factory, international seeder"
```

---

### Task 4: `users.role` + `host_profiles` table, models, factories

**Files:**
- Create: `database/migrations/xxxx_add_role_to_users_table.php`
- Create: `database/migrations/xxxx_create_host_profiles_table.php`
- Modify: `app/Models/User.php`
- Create: `app/Models/HostProfile.php`
- Create: `database/factories/HostProfileFactory.php`
- Test: `tests/Feature/HostProfileTest.php`

**Interfaces:**
- Produces: `User` gains `role` (`joiner|host`), `handle` (unique, nullable), `isHost(): bool`, `hostProfile()` hasOne. `HostProfile` with `user_id, bio, cover_photo, currency (ISO code, default 'USD'), payment_methods (json: array of {label,detail}), trips_count, guests_count, followers_count, rating`. `HostProfile::factory()`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/HostProfileTest.php
use App\Models\{User, HostProfile};
it('flags a host and exposes json payment methods', function () {
    $host = User::factory()->create(['role'=>'host','handle'=>'ridgeline']);
    $profile = HostProfile::factory()->for($host)->create([
        'currency'=>'USD',
        'payment_methods'=>[['label'=>'Bank transfer','detail'=>'•••• 4821'],['label'=>'Wise','detail'=>'@ridgeline']],
    ]);
    expect($host->isHost())->toBeTrue()
        ->and($host->hostProfile->payment_methods)->toHaveCount(2)
        ->and($host->hostProfile->currency)->toBe('USD');
});
it('treats a joiner as not a host', function () {
    expect(User::factory()->create(['role'=>'joiner'])->isHost())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HostProfileTest`
Expected: FAIL (`role` column / `HostProfile` missing).

- [ ] **Step 3: Migrations**

```php
// add_role_to_users
Schema::table('users', function (Blueprint $t) {
    $t->enum('role', ['joiner','host'])->default('joiner')->after('email');
    $t->string('handle')->nullable()->unique()->after('role');
    $t->string('avatar')->nullable();
    $t->string('phone')->nullable();
});
// create_host_profiles
Schema::create('host_profiles', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->text('bio')->nullable(); $t->string('cover_photo')->nullable();
    $t->string('currency', 3)->default('USD');
    $t->json('payment_methods')->nullable();
    $t->unsignedInteger('trips_count')->default(0);
    $t->unsignedInteger('guests_count')->default(0);
    $t->unsignedInteger('followers_count')->default(0);
    $t->decimal('rating', 2, 1)->nullable();
    $t->timestamps();
});
```

- [ ] **Step 4: Models**

```php
// User.php additions
protected function casts(): array { return [...parent::casts(), 'role'=>'string']; }
public function isHost(): bool { return $this->role === 'host'; }
public function hostProfile() { return $this->hasOne(HostProfile::class); }

// HostProfile.php
class HostProfile extends Model {
    use HasFactory;
    protected $guarded = [];
    protected function casts(): array { return ['payment_methods'=>'array']; }
    public function user() { return $this->belongsTo(User::class); }
}
```

- [ ] **Step 5: Factory**

```php
// HostProfileFactory
public function definition(): array {
    return ['bio'=>fake()->sentence(12),'currency'=>'USD',
            'payment_methods'=>[['label'=>'Bank transfer','detail'=>'•••• '.fake()->numberBetween(1000,9999)]],
            'trips_count'=>fake()->numberBetween(0,50),'rating'=>fake()->randomFloat(1,4.4,5.0)];
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=HostProfileTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: role-locked users + host_profiles"
```

---

### Task 5: `trips` + `trip_departures` tables, models, factories

**Files:**
- Create: `database/migrations/xxxx_create_trips_table.php`
- Create: `database/migrations/xxxx_create_trip_departures_table.php`
- Create: `app/Models/Trip.php`, `app/Models/TripDeparture.php`
- Create: `database/factories/TripFactory.php`, `database/factories/TripDepartureFactory.php`
- Test: `tests/Feature/TripTest.php`

**Interfaces:**
- Produces: `Trip` (`host_id, place_id, title, slug, description, meetup_point, price (decimal), inclusions (json), cover_photo, status enum draft|published|archived`) with `host()`, `place()`, `departures()`, `openDepartures()`, scope `published()`. `TripDeparture` (`trip_id, starts_at, ends_at, capacity, status enum open|full|completed|cancelled`) with `spotsLeft(): int` (capacity minus confirmed+held reservations — returns capacity for now since reservations don't exist yet, computed via a `reservations()` relation stub returning 0).

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/TripTest.php
use App\Models\{Trip, TripDeparture, User, Place};
it('builds a published trip with departures and a slug', function () {
    $trip = Trip::factory()->published()->create(['title'=>'Mount Rinjani Traverse']);
    TripDeparture::factory()->count(2)->for($trip)->create();
    expect($trip->slug)->toBe('mount-rinjani-traverse')
        ->and($trip->departures)->toHaveCount(2)
        ->and(Trip::published()->count())->toBe(1);
});
it('reports spots left from capacity when there are no reservations yet', function () {
    $d = TripDeparture::factory()->create(['capacity'=>14]);
    expect($d->spotsLeft())->toBe(14);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TripTest`
Expected: FAIL (models missing).

- [ ] **Step 3: Migrations**

```php
// trips
Schema::create('trips', function (Blueprint $t) {
    $t->id();
    $t->foreignId('host_id')->constrained('users')->cascadeOnDelete();
    $t->foreignId('place_id')->constrained();
    $t->string('title'); $t->string('slug')->unique();
    $t->text('description')->nullable(); $t->string('meetup_point')->nullable();
    $t->decimal('price', 10, 2); $t->json('inclusions')->nullable();
    $t->string('cover_photo')->nullable();
    $t->enum('status', ['draft','published','archived'])->default('draft');
    $t->timestamps();
});
// trip_departures
Schema::create('trip_departures', function (Blueprint $t) {
    $t->id();
    $t->foreignId('trip_id')->constrained()->cascadeOnDelete();
    $t->dateTime('starts_at'); $t->dateTime('ends_at')->nullable();
    $t->unsignedSmallInteger('capacity')->default(12);
    $t->enum('status', ['open','full','completed','cancelled'])->default('open');
    $t->timestamps();
});
```

- [ ] **Step 4: Models**

```php
class Trip extends Model {
    use HasFactory, \Spatie\Sluggable\HasSlug;
    protected $guarded = [];
    protected function casts(): array { return ['inclusions'=>'array','price'=>'decimal:2']; }
    public function getSlugOptions(): \Spatie\Sluggable\SlugOptions {
        return \Spatie\Sluggable\SlugOptions::create()->generateSlugsFrom('title')->saveSlugsTo('slug');
    }
    public function host() { return $this->belongsTo(User::class,'host_id'); }
    public function place() { return $this->belongsTo(Place::class); }
    public function departures() { return $this->hasMany(TripDeparture::class); }
    public function openDepartures() { return $this->departures()->where('status','open')->where('starts_at','>',now())->orderBy('starts_at'); }
    public function scopePublished($q) { return $q->where('status','published'); }
    public function getRouteKeyName(): string { return 'slug'; }
}
class TripDeparture extends Model {
    use HasFactory;
    protected $guarded = [];
    protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime']; }
    public function trip() { return $this->belongsTo(Trip::class); }
    // reservations arrive in Plan 02; until then, 0 held/confirmed:
    public function heldCount(): int { return 0; }
    public function spotsLeft(): int { return max(0, $this->capacity - $this->heldCount()); }
}
```

- [ ] **Step 5: Factories**

```php
// TripFactory
public function definition(): array {
    return ['host_id'=>User::factory()->state(['role'=>'host']),
            'place_id'=>Place::factory(),'title'=>fake()->unique()->words(3,true),
            'price'=>fake()->numberBetween(60,220),
            'inclusions'=>['Transport','Permits','Guide','Meals'],'status'=>'draft'];
}
public function published(): static { return $this->state(['status'=>'published']); }
// TripDepartureFactory
public function definition(): array {
    return ['trip_id'=>Trip::factory(),'starts_at'=>now()->addWeeks(fake()->numberBetween(1,8)),
            'ends_at'=>now()->addWeeks(fake()->numberBetween(1,8))->addDays(2),
            'capacity'=>fake()->numberBetween(8,16),'status'=>'open'];
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=TripTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat: trips + trip_departures models and factories"
```

---

### Task 6: Demo seeder — hosts, trips, departures for a browsable site

**Files:**
- Create: `database/seeders/DemoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (call PlaceSeeder then DemoSeeder)
- Test: `tests/Feature/DemoSeederTest.php`

**Interfaces:**
- Produces: `php artisan migrate:fresh --seed` yields ≥3 hosts (with profiles + handles), ≥8 published trips (each tied to a seeded place), each trip 2–3 open departures.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/DemoSeederTest.php
use App\Models\{User, Trip, TripDeparture};
it('seeds a browsable marketplace', function () {
    $this->seed();
    expect(User::where('role','host')->count())->toBeGreaterThanOrEqual(3)
        ->and(Trip::published()->count())->toBeGreaterThanOrEqual(8)
        ->and(TripDeparture::where('status','open')->count())->toBeGreaterThanOrEqual(16)
        ->and(Trip::published()->first()->host->hostProfile)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DemoSeederTest`
Expected: FAIL (DemoSeeder missing / no data).

- [ ] **Step 3: Write `DemoSeeder`**

```php
class DemoSeeder extends Seeder {
    public function run(): void {
        $places = \App\Models\Place::all();
        collect(['Ridgeline'=>'ridgeline','Sunchaser'=>'sunchaser','Highland Co.'=>'highland'])
          ->each(function ($handle, $name) use ($places) {
            $host = User::factory()->create(['name'=>$name,'role'=>'host','handle'=>$handle]);
            \App\Models\HostProfile::factory()->for($host)->create();
            $places->random(3)->each(function ($place) use ($host) {
                $trip = Trip::factory()->published()->create([
                    'host_id'=>$host->id,'place_id'=>$place->id,
                    'title'=>$place->name.' Traverse',
                ]);
                TripDeparture::factory()->count(fake()->numberBetween(2,3))->for($trip)->create();
            });
        });
    }
}
```

`DatabaseSeeder::run()`:
```php
$this->call([PlaceSeeder::class, DemoSeeder::class]);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=DemoSeederTest`
Expected: PASS.

- [ ] **Step 5: Reseed the dev DB and commit**

```bash
php artisan migrate:fresh --seed
git add -A && git commit -m "feat: demo seeder for a browsable marketplace"
```

---

### Task 7: Trip detail page — `GET /t/{trip:slug}` (Livewire)

**Files:**
- Create: `app/Livewire/Trips/Show.php`
- Create: `resources/views/livewire/trips/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TripPageTest.php`

**Interfaces:**
- Consumes: `Trip` (Task 5), `<x-layouts.site>` (Task 2).
- Produces: route named `trips.show`; page shows trip name in Fraunces, host link to `/@handle`, price in host currency, a departure list with spots-left, and a disabled "Reserve" placeholder (wired in Plan 02). Draft/archived trips 404.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/TripPageTest.php
use App\Models\{Trip, TripDeparture};
it('shows a published trip with its departures and price', function () {
    $trip = Trip::factory()->published()->create(['title'=>'Mount Rinjani Traverse','price'=>180]);
    TripDeparture::factory()->for($trip)->create(['capacity'=>14]);
    $this->get("/t/{$trip->slug}")->assertOk()
        ->assertSee('Mount Rinjani Traverse')->assertSee('180')->assertSee('14 spots');
});
it('404s on a draft trip', function () {
    $trip = Trip::factory()->create(['status'=>'draft']);
    $this->get("/t/{$trip->slug}")->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TripPageTest`
Expected: FAIL (route missing).

- [ ] **Step 3: Component + route + view**

`app/Livewire/Trips/Show.php`:
```php
class Show extends Component {
    public Trip $trip;
    public function mount(Trip $trip) {
        abort_unless($trip->status === 'published', 404);
        $this->trip = $trip->load(['host.hostProfile','place','openDepartures']);
    }
    public function render() { return view('livewire.trips.show')->layout('components.layouts.site'); }
}
```
`routes/web.php`:
```php
use App\Livewire\Trips\Show as TripShow;
Route::get('/t/{trip:slug}', TripShow::class)->name('trips.show');
```
`resources/views/livewire/trips/show.blade.php` (essentials):
```blade
<div class="max-w-5xl mx-auto px-6 py-10">
  <h1 class="font-serif font-semibold text-4xl">{{ $trip->title }}</h1>
  <p class="text-ink/60 mt-2">{{ $trip->place->name }} · {{ $trip->place->country }}
     · <a href="{{ route('hosts.show', $trip->host) }}" class="underline">{{ $trip->host->name }}</a></p>
  <div class="mt-8 grid md:grid-cols-[1fr_360px] gap-10">
    <div>{{ $trip->description }}</div>
    <div class="rounded-2xl border border-ink/10 p-6 bg-surface">
      <div class="font-serif font-bold text-2xl">{{ $trip->host->hostProfile->currency }} {{ number_format($trip->price) }}</div>
      <ul class="mt-4 divide-y divide-ink/5">
        @foreach($trip->openDepartures as $d)
          <li class="py-3 flex justify-between">
            <span>{{ $d->starts_at->format('M j') }}–{{ $d->ends_at?->format('j') }}</span>
            <span class="text-ink/60">{{ $d->spotsLeft() }} spots</span>
          </li>
        @endforeach
      </ul>
      <button disabled class="mt-5 w-full rounded-full bg-pine text-cream py-3 font-semibold opacity-60">Reserve (soon)</button>
    </div>
  </div>
</div>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TripPageTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: public trip detail page /t/{slug}"
```

---

### Task 8: Trip browse + search — `GET /trips` (Livewire with filters)

**Files:**
- Create: `app/Livewire/Trips/Index.php`
- Create: `resources/views/livewire/trips/index.blade.php`
- Create: `resources/views/components/trip-card.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TripBrowseTest.php`

**Interfaces:**
- Consumes: `Trip::published()`, `Place`.
- Produces: route `trips.index`; live text search on trip/place name, filter by country + difficulty, results as `<x-trip-card>`. URL query-string bound (`#[Url]`).

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/TripBrowseTest.php
use App\Models\{Trip, Place};
use Livewire\Livewire;
it('filters trips by search term', function () {
    Trip::factory()->published()->create(['title'=>'Mount Rinjani Traverse']);
    Trip::factory()->published()->create(['title'=>'Fuji Sunrise']);
    Livewire::test(\App\Livewire\Trips\Index::class)
        ->set('search','Rinjani')
        ->assertSee('Mount Rinjani Traverse')->assertDontSee('Fuji Sunrise');
});
it('filters by country', function () {
    $jp = Place::factory()->create(['country'=>'Japan']);
    $id = Place::factory()->create(['country'=>'Indonesia']);
    Trip::factory()->published()->create(['place_id'=>$jp->id,'title'=>'Fuji Sunrise']);
    Trip::factory()->published()->create(['place_id'=>$id->id,'title'=>'Rinjani Traverse']);
    Livewire::test(\App\Livewire\Trips\Index::class)
        ->set('country','Japan')
        ->assertSee('Fuji Sunrise')->assertDontSee('Rinjani Traverse');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TripBrowseTest`
Expected: FAIL.

- [ ] **Step 3: Component + view + card + route**

`app/Livewire/Trips/Index.php`:
```php
class Index extends Component {
    #[Url] public string $search = '';
    #[Url] public string $country = '';
    #[Url] public string $difficulty = '';
    public function render() {
        $trips = Trip::published()->with(['place','host.hostProfile'])
            ->when($this->search, fn($q)=>$q->where(fn($w)=>$w
                ->where('title','like',"%{$this->search}%")
                ->orWhereHas('place', fn($p)=>$p->where('name','like',"%{$this->search}%"))))
            ->when($this->country, fn($q)=>$q->whereHas('place', fn($p)=>$p->where('country',$this->country)))
            ->when($this->difficulty, fn($q)=>$q->whereHas('place', fn($p)=>$p->where('difficulty',$this->difficulty)))
            ->latest()->get();
        $countries = Place::query()->distinct()->orderBy('country')->pluck('country');
        return view('livewire.trips.index', compact('trips','countries'))->layout('components.layouts.site');
    }
}
```
`resources/views/components/trip-card.blade.php`:
```blade
@props(['trip'])
<a href="{{ route('trips.show', $trip) }}" class="block rounded-2xl overflow-hidden border border-ink/5 bg-surface">
  <div class="h-40 skeleton"></div>
  <div class="p-4">
    <h3 class="font-serif font-semibold text-lg">{{ $trip->title }}</h3>
    <p class="text-sm text-ink/60">{{ $trip->place->country }} · {{ $trip->host->name }}</p>
    <p class="mt-2 font-semibold">{{ $trip->host->hostProfile?->currency ?? 'USD' }} {{ number_format($trip->price) }}</p>
  </div>
</a>
```
`resources/views/livewire/trips/index.blade.php`:
```blade
<div class="max-w-6xl mx-auto px-6 py-10">
  <input wire:model.live.debounce.300ms="search" placeholder="Search destinations"
         class="w-full rounded-full border border-ink/15 px-5 py-3">
  <div class="flex gap-3 mt-4">
    <select wire:model.live="country" class="rounded-full border border-ink/15 px-4 py-2">
      <option value="">All countries</option>
      @foreach($countries as $c)<option>{{ $c }}</option>@endforeach
    </select>
    <select wire:model.live="difficulty" class="rounded-full border border-ink/15 px-4 py-2">
      <option value="">Any difficulty</option><option>easy</option><option>challenging</option><option>hard</option>
    </select>
  </div>
  <div class="grid md:grid-cols-3 gap-6 mt-8">
    @forelse($trips as $trip) <x-trip-card :$trip /> @empty
      <p class="text-ink/60">No trips match yet.</p> @endforelse
  </div>
</div>
```
`routes/web.php`:
```php
Route::get('/trips', \App\Livewire\Trips\Index::class)->name('trips.index');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TripBrowseTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: trip browse with live search and filters"
```

---

### Task 9: Host public page — `GET /@{user:handle}` (Livewire, read-only)

**Files:**
- Create: `app/Livewire/Hosts/Show.php`, `app/Livewire/Hosts/Index.php`
- Create: `resources/views/livewire/hosts/show.blade.php`, `resources/views/livewire/hosts/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/HostPageTest.php`

**Interfaces:**
- Consumes: `User` (host), `HostProfile`, `Trip::published()`.
- Produces: routes `hosts.show` (`/@handle`) and `hosts.index` (`/hosts`); host page shows name, bio, trips led & rating (from profile), and upcoming published trips. Non-host handles 404.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/HostPageTest.php
use App\Models\{User, HostProfile, Trip};
it('shows a host page with upcoming trips', function () {
    $host = User::factory()->create(['role'=>'host','handle'=>'ridgeline','name'=>'Ridgeline']);
    HostProfile::factory()->for($host)->create(['bio'=>'Beginner-friendly climbs']);
    Trip::factory()->published()->create(['host_id'=>$host->id,'title'=>'Rinjani Traverse']);
    $this->get('/@ridgeline')->assertOk()
        ->assertSee('Ridgeline')->assertSee('Beginner-friendly climbs')->assertSee('Rinjani Traverse');
});
it('404s for a joiner handle', function () {
    $u = User::factory()->create(['role'=>'joiner','handle'=>'nope']);
    $this->get('/@nope')->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HostPageTest`
Expected: FAIL.

- [ ] **Step 3: Components + route + views**

`app/Livewire/Hosts/Show.php`:
```php
class Show extends Component {
    public User $host;
    public function mount(User $host) {
        abort_unless($host->isHost(), 404);
        $this->host = $host->load('hostProfile');
    }
    public function render() {
        $trips = $this->host->hasMany(Trip::class,'host_id')->published()->with('place')->latest()->get();
        return view('livewire.hosts.show', ['trips'=>$trips])->layout('components.layouts.site');
    }
}
```
`app/Livewire/Hosts/Index.php`:
```php
class Index extends Component {
    public function render() {
        $hosts = User::where('role','host')->whereNotNull('handle')->with('hostProfile')->get();
        return view('livewire.hosts.index', compact('hosts'))->layout('components.layouts.site');
    }
}
```
`routes/web.php`:
```php
Route::get('/hosts', \App\Livewire\Hosts\Index::class)->name('hosts.index');
Route::get('/@{user:handle}', \App\Livewire\Hosts\Show::class)->name('hosts.show');
```
`resources/views/livewire/hosts/show.blade.php` (essentials):
```blade
<div class="max-w-5xl mx-auto px-6 py-10">
  <h1 class="font-serif font-semibold text-3xl">{{ $host->name }}</h1>
  <p class="text-ink/60 mt-2">{{ $host->hostProfile->bio }}</p>
  <div class="flex gap-10 mt-6">
    <div><b class="font-serif text-2xl">{{ $host->hostProfile->trips_count }}</b><div class="text-sm text-ink/60">trips</div></div>
    <div><b class="font-serif text-2xl">{{ $host->hostProfile->rating }}</b><div class="text-sm text-ink/60">rating</div></div>
  </div>
  <h2 class="font-serif text-xl mt-10 mb-4">Upcoming trips</h2>
  <div class="grid md:grid-cols-3 gap-6">@foreach($trips as $trip)<x-trip-card :$trip />@endforeach</div>
</div>
```
`resources/views/livewire/hosts/index.blade.php`: a simple grid linking each host to `route('hosts.show',$host)` with name + bio.

- [ ] **Step 4: Note on route ordering**

Ensure `/@{user:handle}` is registered; Laravel matches literal `@` prefix fine. Confirm `hosts.show` link in `trip-card`/trip page resolves.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=HostPageTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat: host public page /@handle and hosts index"
```

---

### Task 10: Community-first homepage — `GET /` (Livewire)

**Files:**
- Create: `app/Livewire/Home.php`
- Create: `resources/views/livewire/home.blade.php`
- Modify: `routes/web.php` (replace default welcome route)
- Test: `tests/Feature/HomePageTest.php`

**Interfaces:**
- Consumes: `Trip::published()`, `User` hosts.
- Produces: route `home`; hero with the community headline, a "Departing soon" strip of ≤4 trip cards, and a featured-hosts strip. No login required.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/HomePageTest.php
use App\Models\Trip;
it('renders the community homepage with departing-soon trips', function () {
    Trip::factory()->published()->create(['title'=>'Mount Rinjani Traverse']);
    $this->get('/')->assertOk()
        ->assertSee('Find your people')          // community hero copy
        ->assertSee('Mount Rinjani Traverse');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=HomePageTest`
Expected: FAIL (default welcome page has no such copy).

- [ ] **Step 3: Component + view + route**

`app/Livewire/Home.php`:
```php
class Home extends Component {
    public function render() {
        $trips = Trip::published()->with(['place','host.hostProfile'])->latest()->take(4)->get();
        $hosts = \App\Models\User::where('role','host')->whereNotNull('handle')->with('hostProfile')->take(4)->get();
        return view('livewire.home', compact('trips','hosts'))->layout('components.layouts.site');
    }
}
```
`resources/views/livewire/home.blade.php` (essentials — port hero copy from `design/terra-web-mockups.html`):
```blade
<div>
  <section class="max-w-6xl mx-auto px-6 pt-14 pb-10 grid md:grid-cols-2 gap-10 items-center">
    <div>
      <h1 class="font-serif font-semibold text-6xl leading-none">Find your people<br>on the <em class="text-terra">trail.</em></h1>
      <p class="mt-5 text-lg text-ink/60 max-w-md">Group trips led by hosts who know the route. Join a departure, meet the crew in the chat, and go.</p>
      <a href="{{ route('trips.index') }}" class="inline-block mt-7 rounded-full bg-pine text-cream px-6 py-3.5 font-semibold">Browse trips</a>
    </div>
    <div class="h-80 rounded-3xl skeleton"></div>
  </section>
  <section class="max-w-6xl mx-auto px-6">
    <h2 class="font-serif text-2xl mb-5">Departing soon</h2>
    <div class="grid md:grid-cols-4 gap-5">@foreach($trips as $trip)<x-trip-card :$trip />@endforeach</div>
  </section>
</div>
```
`routes/web.php`:
```php
Route::get('/', \App\Livewire\Home::class)->name('home');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=HomePageTest`
Expected: PASS.

- [ ] **Step 5: Full suite + build, then commit**

```bash
php artisan test
npm run build
git add -A && git commit -m "feat: community-first homepage"
```

---

## Roadmap — subsequent plans (each written when we reach it)

Each becomes its own `docs/superpowers/plans/` file with full TDD tasks, built on this foundation:

- **Plan 02 — Auth & reservations (the core loop).** Customize Breeze into role-locked signup (joiner/host). `reservations` + `payment_submissions` tables. The reservation **state machine** as a first-class service (`ReservationService` with guarded transitions) — the spec's #1 test priority: every legal/illegal transition, **atomic capacity check under a DB row lock** (parallel-request test proving no oversell), the reserve → pay (upload proof + reference) flow, and the 24-hour expiry scheduled job. Wire the "Reserve" button and the pay screen; `spotsLeft()` now counts held+confirmed.
- **Plan 03 — Host management.** Host dashboard, the 3-step trip-posting wizard (place → details+departures → review/publish), roster per departure (confirm/reject/extend/cancel with reasons), add offline participant, QR poster generation (`endroid/qr-code` or Browsershot).
- **Plan 04 — Reviews & gating.** Polymorphic `reviews` (host|trip|place), the single server-side gating policy (confirmed reservation on a completed trip), place aggregate-rating recompute, the rate-your-trip composer, review lists on trip/host/place pages.
- **Plan 05 — Chat.** Per-departure `chat_rooms`/`messages`, membership = confirmed + host, pinned host messages, Reverb + Echo realtime (polling fallback), read-only 7 days after completion, chat inbox.
- **Plan 06 — Host pages & wall.** Auto-generated trip-recap posts on completion + free-form posts, followers, the editorial hosts showcase and "become a host" surfaces, IG overlay picker.
- **Plan 07 — Media, polish, deploy.** `spatie/laravel-medialibrary` for real photography (per §8b photo rules), empty/loading/error states pass, notifications centre, saved list, production config (Redis queue, S3, Reverb hosting).
