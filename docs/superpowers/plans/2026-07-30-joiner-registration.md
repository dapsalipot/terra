# Joiner Registration & Identity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let anyone — signed in or not — register themselves and/or other people for a trip in
one pass, capturing the identity details a Philippine trip host needs for permits, with account
creation as a one-tap default rather than a gate.

**Architecture:** A new `RegistrationService` orchestrates a booking by delegating seat-taking to
the existing `ReservationService` (whose row lock is the only oversell protection), writing a
`contact_*` snapshot of the booker onto the reservation, and creating exactly `party_size`
`reservation_participants` rows for the travelers. Booker and traveler are separate concepts: the
booker may take no seat. A hashed-token `magic_links` table backs both passwordless sign-in and
booking-attachment, and every reservation carries a `public_token` so guests reach their booking
without authentication.

**Tech Stack:** Laravel 12, Livewire 3, Tailwind 3.4, Pest 3, Laravel Dusk, MySQL (dev) / SQLite (tests).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-30-joiner-registration-design.md`. Read it before Task 1.
- **Never run `git commit`, `git push`, `git merge`, or create branches.** This project's owner
  gates all git write operations on an explicit request. Commit steps below are written for
  completeness; **do not execute them unless the user asks in that same request.**
- `reservations.party_size` stays authoritative for seat count. Never derive it from
  `participants()->count()` — `ReservationService::reserve()` sums it under `lockForUpdate()` to
  prevent oversell and `Reservation::money()` multiplies by it to price a booking.
- `ReservationService::reserve()` is the only code permitted to create a `Reservation` row on the
  joiner path. Never reimplement its capacity check.
- Magic-link tokens are stored as `hash('sha256', $raw)`. The raw value appears only in URLs and
  email bodies and is returned only to the request that created it.
- Only links emailed to an address may create a session. Host-shared links (Spec 2) never authenticate.
- Registration must work end-to-end with `MAIL_MAILER=log`. Email is an addition, never a dependency.
- Dev server runs on port 8020 (`php artisan serve --port=8020`); Dusk on 8030.
- Run `php artisan test` (Pest) and `php artisan dusk` from `~/Herd/terra`.

## File Structure

**Migrations** (`database/migrations/`)
- `2026_07_30_100000_create_reservation_participants_table.php` — traveler rows
- `2026_07_30_100100_create_magic_links_table.php` — hashed tokens
- `2026_07_30_100200_add_profile_fields_to_users_table.php` — name parts, birthday, gender, nullable password
- `2026_07_30_100300_add_contact_and_token_to_reservations_table.php` — booker snapshot + public token

**Models** (`app/Models/`)
- `ReservationParticipant.php` (new) — one traveler; completeness and minor checks
- `MagicLink.php` (new) — token validity and consumption
- `Reservation.php` (modify) — participant relations, contact accessors, token generation
- `User.php` (modify) — new fillables and full-name accessor

**Support** (`app/Support/`) — matches the existing `App\Support\Handles` convention
- `AccountOutcome.php` (new) — enum of the four account resolutions
- `RegistrationResult.php` (new) — DTO returned by `RegistrationService`

**Services** (`app/Services/`)
- `MagicLinkService.php` (new) — issue and consume tokens
- `RegistrationService.php` (new) — the orchestrator

**Mail** (`app/Mail/`, `resources/views/emails/`)
- `MagicLinkMail.php` + `magic-link.blade.php` — sign-in and attach links
- `BookingConfirmationMail.php` + `booking-confirmation.blade.php` — carries the `/r/{token}` URL

**Controllers** (`app/Http/Controllers/`)
- `MagicLinkController.php` (new) — consumes a link
- `SendMagicLinkController.php` (new) — "email me a sign-in link"

**Livewire** (`app/Livewire/Reservations/`, `resources/views/livewire/reservations/`)
- `Register.php` + `register.blade.php` (new) — the registration form
- `Show.php` + `show.blade.php` (new) — token-authorized booking page, replaces `Pay`
- `Pay.php` + `pay.blade.php` (delete in Task 5)

**Modified elsewhere**
- `routes/web.php` — new routes, legacy redirect
- `app/Livewire/Trips/Show.php` — `reserve()` becomes a redirect to the join page
- `resources/views/livewire/trips/show.blade.php` — button copy
- `app/Livewire/Host/Roster.php` + `roster.blade.php` — manifest surfacing
- `resources/views/auth/login.blade.php` — sign-in-link option
- `database/factories/ReservationParticipantFactory.php` (new)

---

### Task 1: Participant schema and models

**Files:**
- Create: `database/migrations/2026_07_30_100000_create_reservation_participants_table.php`
- Create: `database/migrations/2026_07_30_100300_add_contact_and_token_to_reservations_table.php`
- Create: `app/Models/ReservationParticipant.php`
- Create: `database/factories/ReservationParticipantFactory.php`
- Modify: `app/Models/Reservation.php`
- Test: `tests/Feature/ReservationParticipantTest.php`

**Interfaces:**
- Consumes: existing `Reservation`, `TripDeparture` models and their factories.
- Produces: `ReservationParticipant` with `fullName(): string`, `isComplete(): bool`,
  `isMinorAt(\Carbon\CarbonInterface $date): bool`. `Reservation` gains
  `participants(): HasMany`, `bookerParticipant(): HasOne`, `contactName(): string`,
  `bookerIsTraveling(): bool`, `minors(): Collection`, `incompleteParticipants(): Collection`,
  and auto-generated `public_token`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ReservationParticipantTest.php`:

```php
<?php

use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\TripDeparture;

it('generates a public token on create', function () {
    $reservation = Reservation::factory()->create();

    expect($reservation->public_token)->not->toBeNull()
        ->and(strlen($reservation->public_token))->toBe(36);
});

it('builds a full name and skips a missing middle name', function () {
    $with = new ReservationParticipant(['first_name' => 'Ada', 'middle_name' => 'Luz', 'last_name' => 'Reyes']);
    $without = new ReservationParticipant(['first_name' => 'Ada', 'last_name' => 'Reyes']);

    expect($with->fullName())->toBe('Ada Luz Reyes')
        ->and($without->fullName())->toBe('Ada Reyes');
});

it('is complete only with first name, last name and birthday', function () {
    $complete = new ReservationParticipant(['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02']);
    $noBirthday = new ReservationParticipant(['first_name' => 'Ada', 'last_name' => 'Reyes']);
    $blank = new ReservationParticipant([]);

    expect($complete->isComplete())->toBeTrue()
        ->and($noBirthday->isComplete())->toBeFalse()
        ->and($blank->isComplete())->toBeFalse();
});

it('measures minority against the departure date, not today', function () {
    // Turns 18 on 2026-09-01. Departure is after that, so they are NOT a minor on the trip.
    $participant = new ReservationParticipant(['birthday' => '2008-09-01']);

    expect($participant->isMinorAt(\Carbon\Carbon::parse('2026-10-01')))->toBeFalse()
        ->and($participant->isMinorAt(\Carbon\Carbon::parse('2026-08-01')))->toBeTrue();
});

it('treats a participant with no birthday as not a minor', function () {
    expect((new ReservationParticipant([]))->isMinorAt(\Carbon\Carbon::parse('2026-10-01')))->toBeFalse();
});

it('separates the booker from the travelers', function () {
    $departure = TripDeparture::factory()->create(['capacity' => 10]);
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $departure->id,
        'party_size' => 3,
        'contact_first_name' => 'Ada',
        'contact_last_name' => 'Reyes',
        'contact_email' => 'ada@example.com',
        'contact_phone' => '09171234567',
    ]);

    // Coordinator case: three travelers, none of whom is the booker.
    ReservationParticipant::factory()->count(3)->sequence(
        ['position' => 0], ['position' => 1], ['position' => 2],
    )->create(['reservation_id' => $reservation->id, 'is_booker' => false]);

    expect($reservation->contactName())->toBe('Ada Reyes')
        ->and($reservation->bookerIsTraveling())->toBeFalse()
        ->and($reservation->bookerParticipant)->toBeNull()
        ->and($reservation->participants)->toHaveCount(3);
});

it('flags the booker when they travel too', function () {
    $reservation = Reservation::factory()->create(['party_size' => 2]);
    ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0, 'is_booker' => true]);
    ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 1, 'is_booker' => false]);

    expect($reservation->bookerIsTraveling())->toBeTrue()
        ->and($reservation->bookerParticipant->position)->toBe(0);
});

it('reports which travelers still owe details', function () {
    $reservation = Reservation::factory()->create(['party_size' => 3]);
    ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 1]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 2]);

    expect($reservation->incompleteParticipants())->toHaveCount(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ReservationParticipantTest`
Expected: FAIL — `Class "App\Models\ReservationParticipant" not found`.

- [ ] **Step 3: Write the participants migration**

Create `database/migrations/2026_07_30_100000_create_reservation_participants_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('position')->default(0);
            $t->boolean('is_booker')->default(false);
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('last_name')->nullable();
            $t->date('birthday')->nullable();
            $t->string('gender', 24)->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 32)->nullable();
            $t->timestamps();

            $t->index(['reservation_id', 'position']);
        });
    }

    public function down(): void { Schema::dropIfExists('reservation_participants'); }
};
```

- [ ] **Step 4: Write the reservations alter migration**

Create `database/migrations/2026_07_30_100300_add_contact_and_token_to_reservations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->uuid('public_token')->nullable()->unique()->after('id');
            $t->string('contact_first_name')->nullable()->after('guest_phone');
            $t->string('contact_middle_name')->nullable()->after('contact_first_name');
            $t->string('contact_last_name')->nullable()->after('contact_middle_name');
            $t->string('contact_email')->nullable()->after('contact_last_name');
            $t->string('contact_phone', 32)->nullable()->after('contact_email');
        });

        // Existing rows predate the token. Backfill so every booking is reachable by URL.
        // Every row is null right after the ALTER above, so no WHERE filter is needed — and
        // none should be added: chunking with offset-based pagination (Builder::each()) while
        // writing to the very column being filtered on would skip whole pages of rows as the
        // matching set shrinks underneath the offset. chunkById() paginates by primary key
        // instead, which is unaffected by that mutation.
        DB::table('reservations')->orderBy('id')->chunkById(1000, function ($rows) {
            foreach ($rows as $row) {
                DB::table('reservations')->where('id', $row->id)
                    ->update(['public_token' => (string) Str::uuid()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->dropColumn([
                'public_token', 'contact_first_name', 'contact_middle_name',
                'contact_last_name', 'contact_email', 'contact_phone',
            ]);
        });
    }
};
```

- [ ] **Step 5: Write the ReservationParticipant model**

Create `app/Models/ReservationParticipant.php`:

```php
<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationParticipant extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['birthday' => 'date', 'is_booker' => 'boolean'];
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /** First/middle/last with blanks collapsed, so "Ada  Reyes" never renders. */
    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name,
        ])));
    }

    /**
     * Enough detail for a permit or manifest.
     *
     * Gender is deliberately excluded: it is nudged in the form but never forced, and a
     * booking must not be blocked on it.
     */
    public function isComplete(): bool
    {
        return filled($this->first_name) && filled($this->last_name) && filled($this->birthday);
    }

    /**
     * Under 18 on the given date — normally the departure date, not today.
     *
     * A 17-year-old who turns 18 before the trip is not a minor for permit purposes, so
     * asking "is this person a minor?" without a date is the wrong question.
     */
    public function isMinorAt(CarbonInterface $date): bool
    {
        return $this->birthday !== null && $this->birthday->diffInYears($date) < 18;
    }
}
```

- [ ] **Step 6: Write the factory**

Create `database/factories/ReservationParticipantFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'position' => 0,
            'is_booker' => false,
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'birthday' => fake()->dateTimeBetween('-45 years', '-19 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
        ];
    }

    /** A seat whose details the booker deferred with "I'll add this later". */
    public function blank(): static
    {
        return $this->state([
            'first_name' => null, 'middle_name' => null, 'last_name' => null,
            'birthday' => null, 'gender' => null,
        ]);
    }
}
```

- [ ] **Step 7: Extend the Reservation model**

In `app/Models/Reservation.php`, add `use Illuminate\Support\Str;` to the imports, then add these
members. Replace the existing `displayName()` with the version below.

```php
    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            $reservation->public_token ??= (string) Str::uuid();
        });
    }

    public function participants()
    {
        return $this->hasMany(ReservationParticipant::class)->orderBy('position');
    }

    /**
     * The traveler who is also the booker — null when the booker took no seat.
     *
     * There is deliberately no `lead()` relation: "lead" fused who pays with who travels,
     * and separating those is the point of this table.
     */
    public function bookerParticipant()
    {
        return $this->hasOne(ReservationParticipant::class)->where('is_booker', true);
    }

    public function contactName(): string
    {
        return trim(implode(' ', array_filter([
            $this->contact_first_name, $this->contact_middle_name, $this->contact_last_name,
        ])));
    }

    public function bookerIsTraveling(): bool
    {
        return $this->participants->contains('is_booker', true);
    }

    /** Travelers under 18 on the departure date. Drives the host's guardian-consent flag. */
    public function minors()
    {
        $date = $this->departure?->starts_at;

        return $date === null
            ? collect()
            : $this->participants->filter(fn ($p) => $p->isMinorAt($date))->values();
    }

    /** Travelers still owing permit details. Drives "3 of 5 details pending". */
    public function incompleteParticipants()
    {
        return $this->participants->reject->isComplete()->values();
    }

    public function displayName(): string
    {
        return $this->contactName()
            ?: ($this->joiner?->name ?? $this->guest_name ?? 'Guest');
    }
```

- [ ] **Step 8: Run the migrations and the tests**

Run:
```bash
php artisan migrate && php artisan test --filter=ReservationParticipantTest
```
Expected: PASS, 8 tests.

- [ ] **Step 9: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: all previously passing tests still pass. `displayName()` changed, so pay attention to
`HostManagementTest` and `ReserveFlowTest`; a reservation with no contact fields still falls back
to `joiner->name`, so they should be unaffected.

- [ ] **Step 10: Commit** *(do not run unless the user asked in this request — see Global Constraints)*

```bash
git add database/migrations/2026_07_30_1000*.php database/factories/ReservationParticipantFactory.php app/Models/ReservationParticipant.php app/Models/Reservation.php tests/Feature/ReservationParticipantTest.php
git commit -m "feat: separate booker from travelers with reservation_participants"
```

---

### Task 2: Magic link infrastructure

**Files:**
- Create: `database/migrations/2026_07_30_100100_create_magic_links_table.php`
- Create: `app/Models/MagicLink.php`
- Create: `app/Services/MagicLinkService.php`
- Test: `tests/Feature/MagicLinkTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `MagicLinkService::issue(string $purpose, array $attrs, int $ttlMinutes = 30): array`
  returning `['link' => MagicLink, 'url' => string]`, and
  `MagicLinkService::consume(string $rawToken): ?MagicLink`. `MagicLink` has `isValid(): bool`.
  Redemption itself is a single atomic conditional `UPDATE ... WHERE used_at IS NULL AND
  expires_at > now()` inside the service — there is no separate `MagicLink::consume()` write
  method, so there is nowhere for an unconditional write to creep back in. Purposes used in
  this plan: `'login'`, `'attach'`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MagicLinkTest.php`:

```php
<?php

use App\Models\MagicLink;
use App\Models\User;
use App\Services\MagicLinkService;

beforeEach(function () {
    $this->svc = app(MagicLinkService::class);
});

it('never stores the raw token', function () {
    $user = User::factory()->create();
    ['link' => $link, 'url' => $url] = $this->svc->issue('login', ['user_id' => $user->id]);

    $raw = str($url)->afterLast('/')->toString();

    expect($raw)->not->toBe($link->token)
        ->and($link->token)->toBe(hash('sha256', $raw))
        ->and(strlen($link->token))->toBe(64);
});

it('consumes a valid token once', function () {
    $user = User::factory()->create();
    ['url' => $url] = $this->svc->issue('login', ['user_id' => $user->id]);
    $raw = str($url)->afterLast('/')->toString();

    $first = $this->svc->consume($raw);

    expect($first)->not->toBeNull()
        ->and($first->user_id)->toBe($user->id)
        ->and($first->used_at)->not->toBeNull()
        ->and($this->svc->consume($raw))->toBeNull();   // second use rejected
});

it('rejects an expired token', function () {
    ['url' => $url] = $this->svc->issue('login', ['email' => 'ada@example.com'], ttlMinutes: 30);
    $raw = str($url)->afterLast('/')->toString();

    $this->travel(31)->minutes();

    expect($this->svc->consume($raw))->toBeNull();
});

it('rejects a token that never existed', function () {
    expect($this->svc->consume('not-a-real-token'))->toBeNull();
});

it('records the purpose and target so a consumer can act on it', function () {
    ['link' => $link] = $this->svc->issue('attach', [
        'email' => 'ada@example.com',
        'reservation_id' => null,
    ]);

    expect($link->purpose)->toBe('attach')
        ->and($link->email)->toBe('ada@example.com')
        ->and($link->isValid())->toBeTrue();
});

it('will not re-stamp a token that was already used', function () {
    $user = User::factory()->create();
    ['link' => $link, 'url' => $url] = $this->svc->issue('login', ['user_id' => $user->id]);
    $raw = str($url)->afterLast('/')->toString();

    $firstUse = now()->subMinutes(5);
    $link->forceFill(['used_at' => $firstUse])->save();

    expect($this->svc->consume($raw))->toBeNull()
        // The old unconditional update would move used_at to now().
        ->and($link->fresh()->used_at->timestamp)->toBe($firstUse->timestamp);
});

it('lets only one of two concurrent redemptions of the same token win', function () {
    $user = User::factory()->create();
    ['url' => $url] = $this->svc->issue('login', ['user_id' => $user->id]);
    $raw = str($url)->afterLast('/')->toString();
    $hash = hash('sha256', $raw);

    // Simulate a second request racing this one for the same raw token by re-entering
    // consume() from a query listener, right as this call's first database statement
    // completes. A read-then-write implementation issues a SELECT before it decides
    // anything, leaving a window where a second, interleaved caller can also read
    // "unused" and also go on to redeem it. An atomic conditional UPDATE has no such
    // window: its first (and decisive) statement is the guarded write itself, so a
    // caller that interleaves at that same point is already too late.
    $reentered = false;
    $innerResult = null;

    DB::listen(function ($query) use (&$reentered, &$innerResult, $hash, $raw) {
        if ($reentered || ! str_contains($query->sql, 'select') || ! in_array($hash, $query->bindings, true)) {
            return;
        }

        $reentered = true;
        $innerResult = $this->svc->consume($raw);
    });

    $outerResult = $this->svc->consume($raw);

    $winners = collect([$outerResult, $innerResult])->filter(fn ($r) => $r instanceof MagicLink)->count();

    expect($reentered)->toBeTrue()
        ->and($winners)->toBe(1);
});
```

Note: `DB::listen()` requires `use Illuminate\Support\Facades\DB;` at the top of the test
file, alongside the existing `App\Models\MagicLink`, `App\Models\User`, and
`App\Services\MagicLinkService` imports.

A tempting simpler regression test is to pre-mark a link used (`$link->forceFill(['used_at'
=> ...])->save()`) and then assert `consume()` returns null and doesn't move the
timestamp — the first test above. It's worth keeping, but on its own it will **pass against
the old, unguarded read-then-write implementation too**: `isValid()` re-reads the row inside
`consume()` and already rejects an already-used token when nothing is racing it. It only
distinguishes the two implementations when a second caller's read and write are
genuinely interleaved with this one's, which is what the second test forces via query-listener
reentrancy. Verify any race regression test actually fails against the old implementation —
don't trust a test like the first one to prove it on its own.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MagicLinkTest`
Expected: FAIL — `Class "App\Services\MagicLinkService" not found`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_30_100100_create_magic_links_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_links', function (Blueprint $t) {
            $t->id();
            // sha256 of the raw token. The raw value lives only in the URL and the email body.
            $t->string('token', 64)->unique();
            $t->string('purpose', 24);          // 'login' | 'attach'  ('invite' arrives in Spec 2)
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('email')->nullable();
            $t->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('expires_at');
            $t->dateTime('used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('magic_links'); }
};
```

- [ ] **Step 4: Write the model**

Create `app/Models/MagicLink.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MagicLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
```

Note: there is deliberately no `consume(): void` method here. Redemption is a
check-and-write in one step, and the only place that step may happen is the guarded
`UPDATE` inside `MagicLinkService::consume()` below. A model method that does
`$this->update(['used_at' => now()])` unconditionally would be a second, unguarded way to
stamp a link as used — exactly the shape of the race condition this task exists to avoid —
so don't add one back.

- [ ] **Step 5: Write the service**

Create `app/Services/MagicLinkService.php`:

```php
<?php

namespace App\Services;

use App\Models\MagicLink;
use Illuminate\Support\Str;

class MagicLinkService
{
    /**
     * Mint a single-use link.
     *
     * The raw token is returned here and never again — only its hash is stored, so a leaked
     * database yields no usable links. Callers that need to show or email the URL must do it
     * with this return value.
     *
     * @return array{link: MagicLink, url: string}
     */
    public function issue(string $purpose, array $attrs, int $ttlMinutes = 30): array
    {
        $raw = Str::random(64);

        $link = MagicLink::create([
            'token' => hash('sha256', $raw),
            'purpose' => $purpose,
            'user_id' => $attrs['user_id'] ?? null,
            'email' => $attrs['email'] ?? null,
            'reservation_id' => $attrs['reservation_id'] ?? null,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return ['link' => $link, 'url' => route('magic.consume', ['token' => $raw])];
    }

    /**
     * Redeem a raw token, marking it used.
     *
     * Expired, already-used and never-existed all return null so a caller cannot probe which
     * case it hit — that would leak whether a token was ever real.
     *
     * The redemption is a single conditional UPDATE — `WHERE used_at IS NULL AND
     * expires_at > now()` — not a read-then-write. Do NOT rewrite this as a `first()` /
     * `isValid()` check followed by an unconditional save: that shape has a window between
     * the read and the write where two concurrent requests for the same raw token (an email
     * prefetcher plus the user's own click, a double-submit, a client retry) can both read
     * "unused" and both go on to redeem it. The atomic UPDATE closes that window — the
     * database applies the write to at most one of two racing callers, so only one can ever
     * get a non-null result back. Also do NOT reach for `lockForUpdate()` here instead: it is
     * a no-op on SQLite, which is what the test suite runs on, so a test built to catch this
     * regression would pass even with the race still open.
     */
    public function consume(string $rawToken): ?MagicLink
    {
        $hash = hash('sha256', $rawToken);

        $claimed = MagicLink::where('token', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        if ($claimed === 0) {
            return null;
        }

        return MagicLink::where('token', $hash)->first();
    }
}
```

- [ ] **Step 6: Add the route the service references**

`MagicLinkService::issue()` calls `route('magic.consume')`, which does not exist yet. Add a
placeholder to `routes/web.php` immediately after the `Route::view('profile', ...)` block. Task 7
replaces the closure with the real controller.

```php
// Magic links. The controller arrives in Task 7; this reserves the route name so
// MagicLinkService::issue() can build URLs.
Route::get('/magic/{token}', fn (string $token) => abort(404))->name('magic.consume');
```

- [ ] **Step 7: Run migrations and tests**

Run:
```bash
php artisan migrate && php artisan test --filter=MagicLinkTest
```
Expected: PASS, 7 tests.

- [ ] **Step 8: Commit** *(gated — see Global Constraints)*

```bash
git add database/migrations/2026_07_30_100100_create_magic_links_table.php app/Models/MagicLink.php app/Services/MagicLinkService.php routes/web.php tests/Feature/MagicLinkTest.php
git commit -m "feat: hashed single-use magic links"
```

---

### Task 3: User profile fields and passwordless accounts

**Files:**
- Create: `database/migrations/2026_07_30_100200_add_profile_fields_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/PasswordlessUserTest.php`

**Interfaces:**
- Consumes: nothing from Tasks 1–2.
- Produces: `users` columns `first_name, middle_name, last_name, birthday, gender`, all nullable,
  all fillable; `users.password` becomes nullable. `User::getFullNameAttribute(): string`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PasswordlessUserTest.php`:

```php
<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('stores profile fields used to pre-fill a later booking', function () {
    $user = User::factory()->create([
        'first_name' => 'Ada', 'middle_name' => 'Luz', 'last_name' => 'Reyes',
        'birthday' => '1998-04-02', 'gender' => 'female',
    ]);

    expect($user->fresh()->full_name)->toBe('Ada Luz Reyes')
        ->and($user->fresh()->birthday->format('Y-m-d'))->toBe('1998-04-02');
});

it('creates an account with no password at all', function () {
    $user = User::factory()->create(['password' => null]);

    expect($user->fresh()->password)->toBeNull();
});

it('refuses to authenticate a passwordless account with any password', function () {
    $user = User::factory()->create(['password' => null]);

    // Hash::check returns false for a null hash, so no submitted value can ever match.
    expect(Hash::check('password', $user->password))->toBeFalse()
        ->and(Hash::check('', $user->password))->toBeFalse();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PasswordlessUserTest`
Expected: FAIL — unknown column `first_name`, and a NOT NULL violation on `password`.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_07_30_100200_add_profile_fields_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('first_name')->nullable()->after('name');
            $t->string('middle_name')->nullable()->after('first_name');
            $t->string('last_name')->nullable()->after('middle_name');
            $t->date('birthday')->nullable()->after('phone');
            $t->string('gender', 24)->nullable()->after('birthday');

            // A passwordless account is encoded honestly rather than with a random-hash decoy,
            // so `password IS NULL` can drive the "email me a sign-in link" affordance.
            // Safe: AbstractHasher::check() returns false when the stored hash is null.
            $t->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['first_name', 'middle_name', 'last_name', 'birthday', 'gender']);
            $t->string('password')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 4: Update the User model**

In `app/Models/User.php`, extend `$fillable` and `casts()`, and add the accessor:

```php
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'role',
        'handle',
        'avatar',
        'phone',
        'birthday',
        'gender',
    ];
```

In `casts()`, add `'birthday' => 'date',` alongside the existing entries.

Add the accessor next to `getInitialsAttribute()`:

```php
    /** Name parts when we have them, falling back to the display name. */
    public function getFullNameAttribute(): string
    {
        $parts = trim(implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name,
        ])));

        return $parts ?: (string) $this->name;
    }
```

- [ ] **Step 5: Run migrations and tests**

Run:
```bash
php artisan migrate && php artisan test --filter=PasswordlessUserTest
```
Expected: PASS, 3 tests.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: all pass. Nothing yet writes a null password outside this test.

- [ ] **Step 7: Commit** *(gated)*

```bash
git add database/migrations/2026_07_30_100200_add_profile_fields_to_users_table.php app/Models/User.php tests/Feature/PasswordlessUserTest.php
git commit -m "feat: user profile fields and nullable password"
```

---

### Task 4: RegistrationService

**Files:**
- Create: `app/Support/AccountOutcome.php`
- Create: `app/Support/RegistrationResult.php`
- Create: `app/Services/RegistrationService.php`
- Test: `tests/Feature/RegistrationServiceTest.php`

**Interfaces:**
- Consumes: `ReservationService::reserve(TripDeparture $departure, array $attrs): Reservation`
  from `app/Services/ReservationService.php`; `MagicLinkService::issue()` from Task 2;
  `ReservationParticipant` from Task 1; `User` profile fields from Task 3.
- Produces: `RegistrationService::register(TripDeparture $departure, array $data): RegistrationResult`.
  `$data` keys: `party_size` (int), `contact` (array with `first_name`, `middle_name`,
  `last_name`, `email`, `phone`), `booker_is_traveling` (bool), `participants` (list of arrays
  with `first_name`, `middle_name`, `last_name`, `birthday`, `gender`, `email`, `phone`),
  `create_account` (bool), `actor` (`?User`). `RegistrationResult` exposes public
  `$reservation`, `$outcome` (`AccountOutcome`), `$magicUrl` (`?string`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RegistrationServiceTest.php`:

```php
<?php

use App\Exceptions\TripFullException;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\RegistrationService;
use App\Support\AccountOutcome;

beforeEach(function () {
    $this->svc = app(RegistrationService::class);
    $this->departure = TripDeparture::factory()->create(['capacity' => 10]);
});

function regData(array $overrides = []): array
{
    return array_merge([
        'party_size' => 1,
        'contact' => [
            'first_name' => 'Ada', 'middle_name' => null, 'last_name' => 'Reyes',
            'email' => 'ada@example.com', 'phone' => '09171234567',
        ],
        'booker_is_traveling' => true,
        'participants' => [
            ['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02', 'gender' => 'female'],
        ],
        'create_account' => false,
        'actor' => null,
    ], $overrides);
}

it('registers a guest for one seat', function () {
    $result = $this->svc->register($this->departure, regData());
    $reservation = $result->reservation;

    expect($result->outcome)->toBe(AccountOutcome::Guest)
        ->and($reservation->joiner_id)->toBeNull()
        ->and($reservation->contact_email)->toBe('ada@example.com')
        ->and($reservation->participants)->toHaveCount(1)
        ->and($reservation->participants->first()->is_booker)->toBeTrue();
});

it('creates one participant row per seat even when details are deferred', function () {
    $result = $this->svc->register($this->departure, regData([
        'party_size' => 3,
        'participants' => [
            ['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02', 'gender' => 'female'],
            ['first_name' => 'Ben', 'last_name' => 'Cruz', 'birthday' => '1995-01-10', 'gender' => 'male'],
            [],   // "I'll add this later"
        ],
    ]));

    expect($result->reservation->participants)->toHaveCount(3)
        ->and($result->reservation->incompleteParticipants())->toHaveCount(1)
        ->and($result->reservation->party_size)->toBe(3);
});

it('lets a booker pay without taking a seat', function () {
    $result = $this->svc->register($this->departure, regData([
        'party_size' => 2,
        'booker_is_traveling' => false,
        'participants' => [
            ['first_name' => 'Ben', 'last_name' => 'Cruz', 'birthday' => '1995-01-10', 'gender' => 'male'],
            ['first_name' => 'Cara', 'last_name' => 'Lim', 'birthday' => '1997-06-20', 'gender' => 'female'],
        ],
    ]));
    $reservation = $result->reservation;

    expect($reservation->participants)->toHaveCount(2)
        ->and($reservation->bookerIsTraveling())->toBeFalse()
        ->and($reservation->bookerParticipant)->toBeNull()
        ->and($reservation->contactName())->toBe('Ada Reyes')
        // the booker's name appears in no seat
        ->and($reservation->participants->pluck('first_name')->all())->not->toContain('Ada');
});

it('stores optional per-participant contact details', function () {
    $result = $this->svc->register($this->departure, regData([
        'party_size' => 2,
        'participants' => [
            ['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02', 'gender' => 'female'],
            ['first_name' => 'Ben', 'last_name' => 'Cruz', 'birthday' => '1995-01-10', 'gender' => 'male', 'phone' => '09998887777'],
        ],
    ]));

    expect($result->reservation->participants[1]->phone)->toBe('09998887777');
});

it('creates a passwordless account from the booker, not from a traveler', function () {
    $result = $this->svc->register($this->departure, regData([
        'party_size' => 1,
        'booker_is_traveling' => false,
        'participants' => [['first_name' => 'Ben', 'last_name' => 'Cruz', 'birthday' => '1995-01-10']],
        'create_account' => true,
    ]));

    $user = User::where('email', 'ada@example.com')->first();

    expect($result->outcome)->toBe(AccountOutcome::AccountCreated)
        ->and($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Ada')      // the booker
        ->and($user->password)->toBeNull()
        ->and($user->role)->toBe('joiner')
        ->and($result->reservation->joiner_id)->toBe($user->id)
        ->and($result->magicUrl)->toContain('/magic/');
});

it('never attaches a booking to an existing account by email alone', function () {
    $existing = User::factory()->create(['email' => 'ada@example.com', 'role' => 'joiner']);

    $result = $this->svc->register($this->departure, regData(['create_account' => true]));

    expect($result->outcome)->toBe(AccountOutcome::EmailTaken)
        ->and($result->reservation->joiner_id)->toBeNull()   // stays a guest booking
        ->and($result->magicUrl)->toContain('/magic/')
        ->and(auth()->check())->toBeFalse();

    expect(\App\Models\MagicLink::where('purpose', 'attach')->count())->toBe(1);
});

it('attaches directly for a signed-in joiner and fills only their blank profile fields', function () {
    $actor = User::factory()->create([
        'role' => 'joiner', 'first_name' => 'Adelaida', 'birthday' => null,
    ]);

    $result = $this->svc->register($this->departure, regData(['actor' => $actor]));

    expect($result->outcome)->toBe(AccountOutcome::AlreadySignedIn)
        ->and($result->reservation->joiner_id)->toBe($actor->id);

    $actor->refresh();

    expect($actor->birthday->format('Y-m-d'))->toBe('1998-04-02')  // was blank, now filled
        ->and($actor->first_name)->toBe('Adelaida');               // already set, not overwritten
});

it('refuses a host account', function () {
    $host = User::factory()->create(['role' => 'host']);

    $this->svc->register($this->departure, regData(['actor' => $host]));
})->throws(\App\Exceptions\HostCannotJoinException::class);

it('still cannot oversell through the new path', function () {
    $small = TripDeparture::factory()->create(['capacity' => 2]);

    $this->svc->register($small, regData(['party_size' => 2, 'participants' => [[], []]]));
    $this->svc->register($small, regData(['party_size' => 1, 'participants' => [[]]]));
})->throws(TripFullException::class);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RegistrationServiceTest`
Expected: FAIL — `Class "App\Services\RegistrationService" not found`.

- [ ] **Step 3: Write the enum, the exception and the result DTO**

Create `app/Support/AccountOutcome.php`:

```php
<?php

namespace App\Support;

enum AccountOutcome: string
{
    case Guest = 'guest';                // booked without an account, by choice
    case AccountCreated = 'created';     // passwordless account made and attached
    case AlreadySignedIn = 'signed_in';  // existing joiner, attached directly
    case EmailTaken = 'email_taken';     // guest booking + attach link issued
}
```

Create `app/Support/RegistrationResult.php`:

```php
<?php

namespace App\Support;

use App\Models\Reservation;

final class RegistrationResult
{
    public function __construct(
        public Reservation $reservation,
        public AccountOutcome $outcome,
        /** Raw magic-link URL. Available only on the request that created it. */
        public ?string $magicUrl = null,
    ) {}
}
```

Create `app/Exceptions/HostCannotJoinException.php`, matching the existing exception style in
`app/Exceptions/TripFullException.php`:

```php
<?php

namespace App\Exceptions;

use Exception;

class HostCannotJoinException extends Exception
{
    protected $message = 'Host accounts cannot join trips. Sign up as a joiner to reserve.';
}
```

- [ ] **Step 4: Write the service**

Create `app/Services/RegistrationService.php`:

```php
<?php

namespace App\Services;

use App\Exceptions\HostCannotJoinException;
use App\Models\Reservation;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\AccountOutcome;
use App\Support\RegistrationResult;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    public function __construct(
        private ReservationService $reservations,
        private MagicLinkService $magicLinks,
    ) {}

    /**
     * Book seats and record who is travelling.
     *
     * The booker and the travellers are separate: the booker always supplies contact details
     * and may or may not occupy a seat.
     *
     * @param  array{party_size:int, contact:array, booker_is_traveling:bool, participants:array, create_account:bool, actor:?User}  $data
     */
    public function register(TripDeparture $departure, array $data): RegistrationResult
    {
        $actor = $data['actor'] ?? null;

        if ($actor?->isHost()) {
            throw new HostCannotJoinException();
        }

        return DB::transaction(function () use ($departure, $data, $actor) {
            // Seats first: reserve() holds a row lock on the departure and is the only thing
            // preventing an oversell. Nesting transactions is safe — Laravel uses savepoints.
            $reservation = $this->reservations->reserve($departure, [
                'party_size' => $data['party_size'],
                'source' => 'app',
            ]);

            $contact = $data['contact'];

            $reservation->update([
                'contact_first_name' => $contact['first_name'] ?? null,
                'contact_middle_name' => $contact['middle_name'] ?? null,
                'contact_last_name' => $contact['last_name'] ?? null,
                'contact_email' => $contact['email'] ?? null,
                'contact_phone' => $contact['phone'] ?? null,
            ]);

            $this->writeParticipants($reservation, $data);

            [$outcome, $magicUrl] = $this->resolveAccount($reservation, $data, $actor);

            return new RegistrationResult($reservation->fresh(['participants']), $outcome, $magicUrl);
        });
    }

    /**
     * Exactly party_size rows, blanks included, so the host can see who is still missing.
     */
    private function writeParticipants(Reservation $reservation, array $data): void
    {
        for ($i = 0; $i < $data['party_size']; $i++) {
            $row = $data['participants'][$i] ?? [];

            $reservation->participants()->create([
                'position' => $i,
                'is_booker' => $i === 0 && ($data['booker_is_traveling'] ?? false),
                'first_name' => $row['first_name'] ?? null,
                'middle_name' => $row['middle_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'birthday' => $row['birthday'] ?? null,
                'gender' => $row['gender'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
            ]);
        }
    }

    /** @return array{0: AccountOutcome, 1: ?string} */
    private function resolveAccount(Reservation $reservation, array $data, ?User $actor): array
    {
        if ($actor) {
            $reservation->update(['joiner_id' => $actor->id]);
            $this->backfillProfile($actor, $data);

            return [AccountOutcome::AlreadySignedIn, null];
        }

        if (! ($data['create_account'] ?? false)) {
            return [AccountOutcome::Guest, null];
        }

        $contact = $data['contact'];
        $existing = User::where('email', $contact['email'])->first();

        if ($existing) {
            // Never auto-attach: that would let anyone create a booking that shows up inside
            // a stranger's account. Send a link the real owner must click.
            ['url' => $url] = $this->magicLinks->issue('attach', [
                'user_id' => $existing->id,
                'email' => $existing->email,
                'reservation_id' => $reservation->id,
            ]);

            return [AccountOutcome::EmailTaken, $url];
        }

        // The account is always the booker — they supplied the email and will pay.
        $user = User::create([
            'name' => trim(($contact['first_name'] ?? '').' '.($contact['last_name'] ?? '')),
            'first_name' => $contact['first_name'] ?? null,
            'middle_name' => $contact['middle_name'] ?? null,
            'last_name' => $contact['last_name'] ?? null,
            'email' => $contact['email'],
            'phone' => $contact['phone'] ?? null,
            'password' => null,
            'role' => 'joiner',
        ]);

        $this->backfillProfile($user, $data);
        $reservation->update(['joiner_id' => $user->id]);

        ['url' => $url] = $this->magicLinks->issue('login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reservation_id' => $reservation->id,
        ]);

        return [AccountOutcome::AccountCreated, $url];
    }

    /**
     * Fill blank profile fields so the next booking pre-fills.
     *
     * Only blanks — a value the user already set is theirs, and a form submission must never
     * silently overwrite it.
     */
    private function backfillProfile(User $user, array $data): void
    {
        // Birthday and gender belong to a traveller, so take them from the booker's own seat
        // if they have one. A booker who is not travelling supplies neither.
        $ownSeat = ($data['booker_is_traveling'] ?? false) ? ($data['participants'][0] ?? []) : [];

        $candidates = [
            'first_name' => $data['contact']['first_name'] ?? null,
            'middle_name' => $data['contact']['middle_name'] ?? null,
            'last_name' => $data['contact']['last_name'] ?? null,
            'phone' => $data['contact']['phone'] ?? null,
            'birthday' => $ownSeat['birthday'] ?? null,
            'gender' => $ownSeat['gender'] ?? null,
        ];

        $fill = collect($candidates)
            ->filter(fn ($value, $key) => filled($value) && blank($user->{$key}))
            ->all();

        if ($fill !== []) {
            $user->update($fill);
        }
    }
}
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=RegistrationServiceTest`
Expected: PASS, 9 tests.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: all pass.

- [ ] **Step 7: Commit** *(gated)*

```bash
git add app/Support/AccountOutcome.php app/Support/RegistrationResult.php app/Exceptions/HostCannotJoinException.php app/Services/RegistrationService.php tests/Feature/RegistrationServiceTest.php
git commit -m "feat: RegistrationService orchestrating booker, travellers and accounts"
```

---

### Task 5: Token-authorized booking page

**Files:**
- Create: `app/Livewire/Reservations/Show.php`
- Create: `resources/views/livewire/reservations/show.blade.php`
- Delete: `app/Livewire/Reservations/Pay.php`, `resources/views/livewire/reservations/pay.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/GuestBookingAccessTest.php`

**Interfaces:**
- Consumes: `Reservation::$public_token` from Task 1;
  `ReservationService::submitPayment()` and `cancel()` from the existing service.
- Produces: route `reservations.show` at `/r/{token}`; `reservations.pay` kept as a redirect.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/GuestBookingAccessTest.php`:

```php
<?php

use App\Models\Reservation;
use App\Models\User;

it('lets a guest open their booking with the token and no login', function () {
    $reservation = Reservation::factory()->create([
        'joiner_id' => null,
        'contact_first_name' => 'Ada',
        'contact_last_name' => 'Reyes',
    ]);

    $this->get(route('reservations.show', ['token' => $reservation->public_token]))
        ->assertOk()
        ->assertSee('Ada Reyes');
});

it('404s on an unknown token rather than revealing anything', function () {
    $this->get(route('reservations.show', ['token' => '00000000-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

it('redirects the legacy pay URL to the token URL', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => $user->id]);

    $this->actingAs($user)
        ->get("/reservations/{$reservation->id}/pay")
        ->assertRedirect(route('reservations.show', ['token' => $reservation->public_token]));
});

it('lets a guest submit payment details', function () {
    $reservation = Reservation::factory()->create(['joiner_id' => null, 'status' => 'pending']);

    Livewire::test(\App\Livewire\Reservations\Show::class, ['token' => $reservation->public_token])
        ->set('reference_no', 'GC-12345')
        ->set('method', 'gcash')
        ->call('submit');

    expect($reservation->fresh()->status)->toBe('payment_submitted');
});
```

Add `use Livewire\Livewire;` at the top of the file.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GuestBookingAccessTest`
Expected: FAIL — `Route [reservations.show] not defined`.

- [ ] **Step 3: Create the Show component**

Create `app/Livewire/Reservations/Show.php`:

```php
<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use App\Services\ReservationService;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public Reservation $reservation;
    public $proof = null;
    public string $method = '';
    public string $reference_no = '';

    /**
     * Authorized by token possession, not by session.
     *
     * A guest booking has no joiner_id, so ownership alone cannot gate this page. The token is
     * a long random value scoped to exactly one booking, which is the standard guest
     * order-status pattern.
     */
    public function mount(string $token): void
    {
        $this->reservation = Reservation::where('public_token', $token)
            ->with('departure.trip.host.hostProfile', 'latestPayment', 'participants')
            ->firstOrFail();
    }

    public function submit(ReservationService $service): void
    {
        $this->validate([
            'reference_no' => ['required', 'string', 'max:120'],
            'method' => ['nullable', 'string', 'max:60'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $service->submitPayment($this->reservation, [
            'method' => $this->method ?: null,
            'reference_no' => $this->reference_no,
            'proof_image' => $this->proof ? $this->proof->store('proofs', 'local') : null,
        ]);

        $this->reservation->refresh();
    }

    public function cancel(ReservationService $service)
    {
        $service->cancel($this->reservation, 'Cancelled by joiner');

        return $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.reservations.show')->layout('components.layouts.site');
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/livewire/reservations/show.blade.php` by copying the existing
`pay.blade.php` and making three changes:

```bash
cp resources/views/livewire/reservations/pay.blade.php resources/views/livewire/reservations/show.blade.php
```

Then in the new file:

1. Add this block directly below the opening `<div>` so the booking names its travellers:

```blade
    {{-- Leads the page when heads are unnamed: the host cannot file a permit without them. --}}
    @php($pending = $reservation->incompleteParticipants())
    @if($pending->isNotEmpty())
        <div class="rounded-2xl bg-amber/12 p-5 mb-5" dusk="pending-details">
            <p class="font-semibold mb-1">
                {{ $pending->count() }} of {{ $reservation->participants->count() }}
                {{ \Illuminate\Support\Str::plural('traveller', $reservation->participants->count()) }} still need details
            </p>
            <p class="text-sm text-ink/70">
                Your host needs each traveller's full name and birthday for permits. Send them this
                page's link, or add the details before the trip.
            </p>
        </div>
    @endif

    <div class="rounded-2xl border border-ink/10 p-5 mb-5">
        <h2 class="font-serif font-semibold text-lg mb-1">{{ $reservation->contactName() }}</h2>
        <p class="text-sm text-ink/65 mb-3">
            {{ $reservation->party_size }} {{ \Illuminate\Support\Str::plural('seat', $reservation->party_size) }}
            @unless($reservation->bookerIsTraveling()) · you are booking for others @endunless
        </p>

        <ul class="space-y-1 text-sm">
            @foreach($reservation->participants as $p)
                <li class="flex items-center gap-2">
                    <span class="text-ink/45 tabular-nums">{{ $loop->iteration }}.</span>
                    @if($p->isComplete())
                        <span>{{ $p->fullName() }}</span>
                        @if($p->is_booker)<span class="text-xs text-moss font-semibold">you</span>@endif
                    @else
                        <span class="text-ink/50 italic">Details still needed</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    {{-- The booking's own address. With no mail provider configured this is the only way back. --}}
    <div class="rounded-2xl bg-moss/10 p-5 mb-5">
        <p class="text-sm font-semibold mb-1.5">Save this link</p>
        <p class="text-sm text-ink/70 mb-3">It's how you get back to this booking.</p>
        <input readonly value="{{ route('reservations.show', ['token' => $reservation->public_token]) }}"
               dusk="booking-link"
               class="w-full rounded-lg border border-ink/15 px-3 py-2 text-sm font-mono bg-cream"
               onclick="this.select()">
    </div>
```

2. Replace every `$this->reservation` / `$reservation->joiner` reference that assumed an
   authenticated joiner with `$reservation->contactName()`.
3. Leave the payment form markup unchanged — the component's public properties are identical.

- [ ] **Step 5: Update the routes**

In `routes/web.php`, remove the `reservations.pay` line from the `auth` group and add the two
routes below it, outside any `auth` middleware:

```php
// Booking status and payment. Deliberately outside `auth`: a guest booking has no user to
// authenticate, so possession of the token is the credential.
Route::get('/r/{token}', \App\Livewire\Reservations\Show::class)->name('reservations.show');

// Legacy URL kept alive so links already sent out keep working.
Route::get('/reservations/{reservation}/pay', function (\App\Models\Reservation $reservation) {
    return redirect()->route('reservations.show', ['token' => $reservation->public_token]);
})->name('reservations.pay');
```

- [ ] **Step 6: Delete the superseded component**

```bash
rm app/Livewire/Reservations/Pay.php resources/views/livewire/reservations/pay.blade.php
```

- [ ] **Step 7: Update references to the deleted component**

Run: `grep -rn "Reservations\\\\Pay\|reservations.pay" app/ resources/ tests/`

Update every hit to use `reservations.show` with the token. Expect hits in
`app/Livewire/Trips/Show.php` (the `reserve()` redirect, which Task 6 rewrites anyway) and in
`tests/Feature/ReserveFlowTest.php`.

- [ ] **Step 8: Run the tests**

Run: `php artisan test --filter=GuestBookingAccessTest`
Expected: PASS, 4 tests.

Then: `php artisan test`
Expected: all pass.

- [ ] **Step 9: Commit** *(gated)*

```bash
git add app/Livewire/Reservations resources/views/livewire/reservations routes/web.php tests/Feature/GuestBookingAccessTest.php
git commit -m "feat: token-authorized booking page for guests"
```

---

### Task 6: The registration form

**Files:**
- Create: `app/Livewire/Reservations/Register.php`
- Create: `resources/views/livewire/reservations/register.blade.php`
- Modify: `app/Livewire/Trips/Show.php:36-60`
- Modify: `resources/views/livewire/trips/show.blade.php:393-394`
- Modify: `routes/web.php`
- Test: `tests/Feature/RegisterComponentTest.php`

**Interfaces:**
- Consumes: `RegistrationService::register()` and `RegistrationResult` from Task 4;
  route `reservations.show` from Task 5.
- Produces: route `trips.join` at `/t/{trip:slug}/join`; component
  `App\Livewire\Reservations\Register` with public `$partySize`, `$contact`,
  `$bookerIsTraveling`, `$participants`, `$createAccount`, and methods `submit()`,
  `updatedPartySize()`, `updatedBookerIsTraveling()`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RegisterComponentTest.php`:

```php
<?php

use App\Livewire\Reservations\Register;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->trip = Trip::factory()->create(['status' => 'published', 'price' => 3500]);
    $this->departure = TripDeparture::factory()->create([
        'trip_id' => $this->trip->id, 'capacity' => 10, 'status' => 'open',
    ]);
});

it('starts with one seat, one participant and the booker travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSet('partySize', 1)
        ->assertSet('bookerIsTraveling', true)
        ->assertCount('participants', 1);
});

it('grows and shrinks the participant list with the seat count', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', 3)
        ->assertCount('participants', 3)
        ->set('partySize', 2)
        ->assertCount('participants', 2);
});

it('mirrors the booker name into seat one while they are travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->assertSet('participants.0.first_name', 'Ada')
        ->assertSet('participants.0.last_name', 'Reyes');
});

it('clears an untouched mirrored seat when the booker stops travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('bookerIsTraveling', false)
        ->assertSet('participants.0.first_name', '')
        ->assertSet('participants.0.last_name', '');
});

it('preserves a seat the booker edited when they stop travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('participants.0.first_name', 'Ben')   // deliberately someone else
        ->set('bookerIsTraveling', false)
        ->assertSet('participants.0.first_name', 'Ben');
});

it('requires the booker contact block', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->call('submit')
        ->assertHasErrors([
            'contact.first_name' => 'required',
            'contact.last_name' => 'required',
            'contact.email' => 'required',
            'contact.phone' => 'required',
        ]);
});

it('registers a guest and sends them to their booking', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('participants.0.birthday', '1998-04-02')
        ->set('participants.0.gender', 'female')
        ->set('createAccount', false)
        ->call('submit')
        ->assertRedirect();

    $reservation = \App\Models\Reservation::first();

    expect($reservation->joiner_id)->toBeNull()
        ->and($reservation->contact_email)->toBe('ada@example.com')
        ->and($reservation->participants)->toHaveCount(1);
});

it('turns a host away', function () {
    $host = User::factory()->create(['role' => 'host']);

    Livewire::actingAs($host)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertRedirect(route('trips.show', $this->trip));
});

it('refuses a departure that has already left', function () {
    $this->departure->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()]);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSet('closed', true)
        ->assertSee('no longer taking registrations');
});

it('refuses a cancelled departure', function () {
    $this->departure->update(['status' => 'cancelled']);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSet('closed', true);
});

it('sends a joiner who already booked to their existing booking', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);
    $existing = \App\Models\Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id,
        'joiner_id' => $joiner->id,
        'status' => 'pending',
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertRedirect(route('reservations.show', ['token' => $existing->public_token]));
});

it('does not divert a joiner whose previous booking was cancelled', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);
    \App\Models\Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id,
        'joiner_id' => $joiner->id,
        'status' => 'cancelled',
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertNoRedirect();
});

it('pre-fills a signed-in joiner from their profile', function () {
    $joiner = User::factory()->create([
        'role' => 'joiner', 'first_name' => 'Ada', 'last_name' => 'Reyes',
        'email' => 'ada@example.com', 'phone' => '09171234567', 'birthday' => '1998-04-02',
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertSet('contact.first_name', 'Ada')
        ->assertSet('contact.email', 'ada@example.com')
        ->assertSet('participants.0.birthday', '1998-04-02');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RegisterComponentTest`
Expected: FAIL — `Class "App\Livewire\Reservations\Register" not found`.

- [ ] **Step 3: Write the component**

Create `app/Livewire/Reservations/Register.php`:

```php
<?php

namespace App\Livewire\Reservations;

use App\Exceptions\TripFullException;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Services\RegistrationService;
use App\Support\AccountOutcome;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Register extends Component
{
    public Trip $trip;
    public TripDeparture $departure;

    public int $partySize = 1;
    public array $contact = [
        'first_name' => '', 'middle_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
    ];
    public bool $bookerIsTraveling = true;
    public array $participants = [];
    public bool $createAccount = true;
    public ?string $error = null;

    /** True when this departure can no longer be joined. Renders a dead-end notice, not a form. */
    public bool $closed = false;
    public string $closedReason = '';

    public const BLANK_PARTICIPANT = [
        'first_name' => '', 'middle_name' => '', 'last_name' => '',
        'birthday' => '', 'gender' => '', 'email' => '', 'phone' => '',
        'show_contact' => false,
    ];

    public function mount(Trip $trip)
    {
        abort_unless($trip->status === 'published', 404);

        if (Auth::user()?->isHost()) {
            return $this->redirectRoute('trips.show', ['trip' => $trip], navigate: true);
        }

        $this->trip = $trip;
        $this->departure = $trip->departures()->firstOrFail();
        $this->participants = [self::BLANK_PARTICIPANT];

        // A trip can be published while its one departure has sailed or been called off.
        // Show a dead end rather than a form that can only fail on submit.
        if (in_array($this->departure->status, ['cancelled', 'completed'], true)) {
            $this->closed = true;
            $this->closedReason = 'This departure has been cancelled.';

            return;
        }

        if ($this->departure->starts_at->isPast()) {
            $this->closed = true;
            $this->closedReason = 'This trip has already left.';

            return;
        }

        // Someone who already holds a live booking here wants that booking, not a second one.
        if ($existing = $this->existingReservation()) {
            return $this->redirectRoute(
                'reservations.show',
                ['token' => $existing->public_token],
                navigate: true,
            );
        }

        if ($user = Auth::user()) {
            $this->contact = [
                'first_name' => $user->first_name ?? '',
                'middle_name' => $user->middle_name ?? '',
                'last_name' => $user->last_name ?? '',
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ];
            $this->participants[0] = array_merge(self::BLANK_PARTICIPANT, [
                'first_name' => $user->first_name ?? '',
                'middle_name' => $user->middle_name ?? '',
                'last_name' => $user->last_name ?? '',
                'birthday' => $user->birthday?->format('Y-m-d') ?? '',
                'gender' => $user->gender ?? '',
            ]);
        }

        $this->restoreDraft();
    }

    public function getIsSignedInProperty(): bool
    {
        return Auth::check();
    }

    /**
     * A live booking this visitor already holds on this departure.
     *
     * Only identifiable for a signed-in joiner. A guest is deliberately not matched by email:
     * that would let anyone probe whether an address has booked a given trip.
     */
    private function existingReservation(): ?\App\Models\Reservation
    {
        if (! Auth::check()) {
            return null;
        }

        return $this->departure->reservations()
            ->where('joiner_id', Auth::id())
            ->whereIn('status', \App\Models\Reservation::HOLDING)
            ->first();
    }

    /** Seats drive the traveller list; one row per seat, always. */
    public function updatedPartySize(): void
    {
        $this->partySize = max(1, min(20, (int) $this->partySize));

        while (count($this->participants) < $this->partySize) {
            $this->participants[] = self::BLANK_PARTICIPANT;
        }
        // Trim from the end so filled earlier seats survive.
        $this->participants = array_slice($this->participants, 0, $this->partySize);

        $this->saveDraft();
    }

    public function updatedContact(): void
    {
        $this->mirrorBookerSeat();
        $this->saveDraft();
    }

    public function updatedBookerIsTraveling(bool $value): void
    {
        if ($value) {
            $this->mirrorBookerSeat();
            $this->saveDraft();

            return;
        }

        // Clear seat 1 only if it still mirrors the contact block untouched. If the booker
        // typed a different name there it is theirs, and wiping it would destroy their work.
        if ($this->seatMirrorsContact(0)) {
            $this->participants[0] = self::BLANK_PARTICIPANT;
        }

        $this->saveDraft();
    }

    public function toggleContactFor(int $i): void
    {
        $this->participants[$i]['show_contact'] = ! ($this->participants[$i]['show_contact'] ?? false);
    }

    /** Copy the booker's name into their own seat; never their birthday or gender. */
    private function mirrorBookerSeat(): void
    {
        if (! $this->bookerIsTraveling || $this->participants === []) {
            return;
        }

        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $this->participants[0][$field] = $this->contact[$field] ?? '';
        }
    }

    private function seatMirrorsContact(int $i): bool
    {
        $same = fn (string $f) => strcasecmp(
            trim((string) ($this->participants[$i][$f] ?? '')),
            trim((string) ($this->contact[$f] ?? '')),
        ) === 0;

        return $same('first_name') && $same('middle_name') && $same('last_name');
    }

    protected function rules(): array
    {
        return [
            'partySize' => ['required', 'integer', 'min:1', 'max:20'],
            'contact.first_name' => ['required', 'string', 'max:80'],
            'contact.middle_name' => ['nullable', 'string', 'max:80'],
            'contact.last_name' => ['required', 'string', 'max:80'],
            'contact.email' => ['required', 'email', 'max:180'],
            'contact.phone' => ['required', 'string', 'max:32'],
            'participants.*.first_name' => ['nullable', 'string', 'max:80'],
            'participants.*.middle_name' => ['nullable', 'string', 'max:80'],
            'participants.*.last_name' => ['nullable', 'string', 'max:80'],
            'participants.*.birthday' => ['nullable', 'date', 'before:today'],
            'participants.*.gender' => ['nullable', 'string', 'max:24'],
            'participants.*.email' => ['nullable', 'email', 'max:180'],
            'participants.*.phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function submit(RegistrationService $service)
    {
        $this->validate();

        try {
            $result = $service->register($this->departure, [
                'party_size' => $this->partySize,
                'contact' => $this->normalisedContact(),
                'booker_is_traveling' => $this->bookerIsTraveling,
                'participants' => $this->normalisedParticipants(),
                'create_account' => $this->isSignedIn ? false : $this->createAccount,
                'actor' => Auth::user(),
            ]);
        } catch (TripFullException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        session()->forget($this->draftKey());

        // Carried in the session because the raw magic URL exists only on this request.
        session()->flash('registration_outcome', $result->outcome->value);
        session()->flash('registration_magic_url', $result->magicUrl);

        return $this->redirectRoute(
            'reservations.show',
            ['token' => $result->reservation->public_token],
            navigate: true,
        );
    }

    private function normalisedContact(): array
    {
        return [
            'first_name' => trim($this->contact['first_name']),
            'middle_name' => trim($this->contact['middle_name']) ?: null,
            'last_name' => trim($this->contact['last_name']),
            'email' => strtolower(trim($this->contact['email'])),
            'phone' => $this->normalisePhone($this->contact['phone']),
        ];
    }

    private function normalisedParticipants(): array
    {
        return collect($this->participants)->map(fn (array $p) => [
            'first_name' => trim($p['first_name'] ?? '') ?: null,
            'middle_name' => trim($p['middle_name'] ?? '') ?: null,
            'last_name' => trim($p['last_name'] ?? '') ?: null,
            'birthday' => ($p['birthday'] ?? '') ?: null,
            'gender' => ($p['gender'] ?? '') ?: null,
            'email' => trim($p['email'] ?? '') ?: null,
            'phone' => ($p['phone'] ?? '') ? $this->normalisePhone($p['phone']) : null,
        ])->all();
    }

    /** Store one shape: 09xxxxxxxxx and +639xxxxxxxxx both become 09xxxxxxxxx. */
    private function normalisePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }

    private function draftKey(): string
    {
        return 'reg-draft.'.$this->departure->id;
    }

    /** Session-only, so a refresh or a stray back-navigation does not wipe a filled form. */
    private function saveDraft(): void
    {
        session()->put($this->draftKey(), [
            'partySize' => $this->partySize,
            'contact' => $this->contact,
            'bookerIsTraveling' => $this->bookerIsTraveling,
            'participants' => $this->participants,
        ]);
    }

    private function restoreDraft(): void
    {
        $draft = session($this->draftKey());

        if (! is_array($draft)) {
            return;
        }

        $this->partySize = $draft['partySize'] ?? $this->partySize;
        $this->contact = array_merge($this->contact, $draft['contact'] ?? []);
        $this->bookerIsTraveling = $draft['bookerIsTraveling'] ?? $this->bookerIsTraveling;
        $this->participants = collect($draft['participants'] ?? $this->participants)
            ->map(fn ($p) => array_merge(self::BLANK_PARTICIPANT, is_array($p) ? $p : []))
            ->all();
    }

    public function render()
    {
        return view('livewire.reservations.register', [
            'spotsLeft' => $this->departure->spotsLeft(),
            'total' => $this->trip->current_price * $this->partySize,
            'payableNow' => $this->trip->payable_now * $this->partySize,
        ])->layout('components.layouts.site');
    }
}
```

- [ ] **Step 4: Write the view**

Create `resources/views/livewire/reservations/register.blade.php`:

```blade
@php($cur = $trip->host->hostProfile?->currency ?? 'PHP')
@php($money = fn ($n) => $cur.' '.number_format($n))

<div class="max-w-2xl mx-auto px-5 py-8">
    <a href="{{ route('trips.show', $trip) }}" wire:navigate class="text-sm text-ink/65 hover:text-ink">← {{ $trip->title }}</a>
    <h1 class="font-serif font-semibold text-3xl mt-2 mb-6">Join this trip</h1>

    @if($closed)
        <div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 py-12 text-center">
            <p class="font-semibold mb-1">{{ $closedReason }}</p>
            <p class="text-ink/65 text-sm mb-5">This departure is no longer taking registrations.</p>
            <a href="{{ route('trips.index') }}" wire:navigate
               class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors">Find another trip</a>
        </div>
    @else

    @if($error)
        <div class="rounded-xl bg-terra/10 text-terra px-4 py-3 mb-5 text-sm font-semibold" dusk="reg-error">{{ $error }}</div>
    @endif

    {{-- ═══ 1 · SEATS ═══ --}}
    <section class="rounded-2xl border border-ink/10 p-5 mb-4">
        <h2 class="font-serif font-semibold text-lg mb-3">How many seats?</h2>

        <div class="flex items-center gap-4">
            <input type="number" min="1" max="20" wire:model.live="partySize" dusk="party-size"
                   aria-label="Number of seats" class="field-narrow w-24 text-center">
            <div class="text-sm text-ink/70">
                <div>{{ $money($total) }} total</div>
                <div class="text-ink/50">{{ $spotsLeft }} {{ \Illuminate\Support\Str::plural('seat', $spotsLeft) }} left</div>
            </div>
        </div>

        @if($trip->takesDeposit())
            <p class="text-sm text-ink/70 mt-3">{{ $money($payableNow) }} to reserve, balance on the day.</p>
        @endif
    </section>

    {{-- ═══ 2 · BOOKER ═══ --}}
    <section class="rounded-2xl border border-ink/10 p-5 mb-4">
        <h2 class="font-serif font-semibold text-lg">Your details</h2>
        <p class="text-sm text-ink/65 mb-4">Who we contact about this booking.</p>

        <div class="grid sm:grid-cols-3 gap-3">
            <label class="block"><span class="field-label">First name</span>
                <input wire:model.blur="contact.first_name" dusk="contact-first" class="field">
                @error('contact.first_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <label class="block"><span class="field-label">Middle name <span class="text-ink/45">optional</span></span>
                <input wire:model.blur="contact.middle_name" class="field">
            </label>
            <label class="block"><span class="field-label">Last name</span>
                <input wire:model.blur="contact.last_name" dusk="contact-last" class="field">
                @error('contact.last_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
        </div>

        <div class="grid sm:grid-cols-2 gap-3 mt-3">
            <label class="block"><span class="field-label">Email</span>
                <input type="email" wire:model.blur="contact.email" dusk="contact-email" class="field">
                @error('contact.email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <label class="block"><span class="field-label">Mobile</span>
                <input type="tel" wire:model.blur="contact.phone" dusk="contact-phone" placeholder="09xx xxx xxxx" class="field">
                @error('contact.phone')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
        </div>
    </section>

    {{-- ═══ 3 · TRAVELLERS ═══ --}}
    <section class="rounded-2xl border border-ink/10 p-5 mb-4">
        <h2 class="font-serif font-semibold text-lg mb-3">Who's going?</h2>

        <label class="flex items-start gap-3 rounded-xl bg-moss/8 p-4 mb-4 cursor-pointer">
            <input type="checkbox" wire:model.live="bookerIsTraveling" dusk="booker-traveling"
                   class="mt-0.5 rounded border-ink/25 text-moss focus:ring-moss">
            <span class="text-sm">
                <b class="font-semibold">I'm joining this trip too</b>
                @unless($bookerIsTraveling)
                    <span class="block text-ink/70 mt-1">
                        You book and pay; {{ $partySize }} {{ \Illuminate\Support\Str::plural('person', $partySize) }} travel.
                        Your details are used for contact only and take no seat.
                    </span>
                @endunless
            </span>
        </label>

        @foreach($participants as $i => $p)
            <div class="rounded-xl border border-ink/10 p-4 mb-3" dusk="seat-{{ $i }}">
                <p class="font-semibold text-sm mb-3">
                    {{ $bookerIsTraveling && $i === 0 ? 'You' : 'Seat '.($i + 1) }}
                </p>

                <div class="grid sm:grid-cols-3 gap-3">
                    <label class="block"><span class="field-label">First name</span>
                        <input wire:model.blur="participants.{{ $i }}.first_name" dusk="seat-{{ $i }}-first" class="field">
                    </label>
                    <label class="block"><span class="field-label">Middle <span class="text-ink/45">optional</span></span>
                        <input wire:model.blur="participants.{{ $i }}.middle_name" class="field">
                    </label>
                    <label class="block"><span class="field-label">Last name</span>
                        <input wire:model.blur="participants.{{ $i }}.last_name" dusk="seat-{{ $i }}-last" class="field">
                    </label>
                </div>

                <div class="grid sm:grid-cols-2 gap-3 mt-3">
                    <label class="block"><span class="field-label">Birthday</span>
                        <input type="date" max="{{ now()->toDateString() }}"
                               wire:model.blur="participants.{{ $i }}.birthday" dusk="seat-{{ $i }}-birthday" class="field">
                        @error('participants.'.$i.'.birthday')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                    </label>

                    <div x-data="{ more: false }">
                        <span class="field-label">Gender</span>
                        <div class="flex gap-2">
                            @foreach(['male' => 'Male', 'female' => 'Female'] as $value => $label)
                                <button type="button" wire:click="$set('participants.{{ $i }}.gender', '{{ $value }}')"
                                        class="flex-1 rounded-xl border px-3 min-h-[44px] text-sm font-semibold transition-colors
                                               {{ ($p['gender'] ?? '') === $value ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">{{ $label }}</button>
                            @endforeach
                            <button type="button" @click="more = !more"
                                    class="rounded-xl border border-ink/15 px-3 min-h-[44px] text-sm hover:border-ink/30">…</button>
                        </div>
                        <div x-show="more" x-cloak class="flex gap-2 mt-2">
                            @foreach(['other' => 'Other', 'undisclosed' => 'Prefer not to say'] as $value => $label)
                                <button type="button" wire:click="$set('participants.{{ $i }}.gender', '{{ $value }}')"
                                        class="flex-1 rounded-xl border px-3 min-h-[40px] text-xs font-semibold transition-colors
                                               {{ ($p['gender'] ?? '') === $value ? 'bg-moss text-cream border-moss' : 'border-ink/15' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if($p['show_contact'] ?? false)
                    <div class="grid sm:grid-cols-2 gap-3 mt-3">
                        <label class="block"><span class="field-label">Their email <span class="text-ink/45">optional</span></span>
                            <input type="email" wire:model.blur="participants.{{ $i }}.email" class="field">
                        </label>
                        <label class="block"><span class="field-label">Their mobile <span class="text-ink/45">optional</span></span>
                            <input type="tel" wire:model.blur="participants.{{ $i }}.phone" class="field">
                        </label>
                    </div>
                @else
                    <button type="button" wire:click="toggleContactFor({{ $i }})"
                            class="text-sm font-semibold text-moss hover:underline mt-3">+ add contact</button>
                @endif
            </div>
        @endforeach

        <p class="text-sm text-ink/60">Don't have everyone's details yet? Leave a seat blank and add them later.</p>
    </section>

    {{-- ═══ 4 · CONFIRM ═══ --}}
    <section class="rounded-2xl border border-ink/10 p-5">
        @unless($this->isSignedIn)
            <label class="flex items-start gap-3 rounded-xl bg-moss/8 p-4 mb-4 cursor-pointer">
                <input type="checkbox" wire:model="createAccount" dusk="create-account"
                       class="mt-0.5 rounded border-ink/25 text-moss focus:ring-moss">
                <span class="text-sm">
                    <b class="font-semibold">Create my Peeps account</b>
                    <span class="block text-ink/70 mt-0.5">Track this trip, chat with your group, and rebook in one tap. No password needed.</span>
                </span>
            </label>
        @endunless

        <button wire:click="submit" wire:loading.attr="disabled" dusk="submit-registration"
                class="w-full rounded-full bg-pine text-cream py-3.5 font-semibold hover:bg-pine/90 transition-colors">
            Confirm registration · {{ $money($payableNow) }}
        </button>

        @unless($this->isSignedIn)
            <p class="text-xs text-ink/55 text-center mt-2">Booking as a guest? Untick the box above.</p>
        @endunless
    </section>

    @endif
</div>
```

Note the `@else` / `@endif` wrapping the four sections: when `$closed` is true the form must not
render at all, or a visitor could still submit against a departed trip.

- [ ] **Step 5: Add the route**

In `routes/web.php`, directly below the `trips.show` line:

```php
Route::get('/t/{trip:slug}/join', \App\Livewire\Reservations\Register::class)->name('trips.join');
```

- [ ] **Step 6: Point the trip page at the new flow**

In `app/Livewire/Trips/Show.php`, replace the whole `reserve()` method (lines 36–60) with:

```php
    /** The trip page no longer books directly — registration collects who is travelling. */
    public function reserve()
    {
        return $this->redirectRoute('trips.join', ['trip' => $this->trip], navigate: true);
    }
```

Remove the now-unused imports `App\Exceptions\TripFullException`, `App\Models\TripDeparture` and
`App\Services\ReservationService` from that file, and delete the `$party` and `$departureId`
properties only if `grep -n "party\|departureId" resources/views/livewire/trips/show.blade.php`
returns nothing. If the blade still references them, leave them in place.

In `resources/views/livewire/trips/show.blade.php`, change the button label on line 394 from
`Reserve` to `Join this trip`.

- [ ] **Step 7: Run the tests**

Run: `php artisan test --filter=RegisterComponentTest`
Expected: PASS, 13 tests.

Then: `php artisan test`
Expected: all pass. `ReserveFlowTest` exercises the old direct-reserve path and will need its
assertions repointed at `trips.join`; update it rather than deleting it.

- [ ] **Step 8: Verify in a browser**

Run:
```bash
php artisan serve --port=8020
```
Open `http://127.0.0.1:8020/t/<any-published-slug>/join`. Confirm: seat stepper updates the
total, seat cards appear and disappear with the count, unticking "I'm joining this trip too"
reveals the explanatory line, and "+ add contact" reveals two more fields.

- [ ] **Step 9: Commit** *(gated)*

```bash
git add app/Livewire/Reservations/Register.php resources/views/livewire/reservations/register.blade.php app/Livewire/Trips/Show.php resources/views/livewire/trips/show.blade.php routes/web.php tests/Feature/RegisterComponentTest.php tests/Feature/ReserveFlowTest.php
git commit -m "feat: joiner registration form with booker/traveller split"
```

---

### Task 7: Magic-link consumption, email, and the login affordance

**Files:**
- Create: `app/Http/Controllers/MagicLinkController.php`
- Create: `app/Http/Controllers/SendMagicLinkController.php`
- Create: `app/Mail/MagicLinkMail.php`
- Create: `resources/views/emails/magic-link.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/auth/login.blade.php`
- Modify: `app/Services/RegistrationService.php`
- Test: `tests/Feature/MagicLinkFlowTest.php`

**Interfaces:**
- Consumes: `MagicLinkService::consume()` from Task 2; `AccountOutcome` from Task 4;
  route `reservations.show` from Task 5.
- Produces: working `magic.consume` route (replacing Task 2's placeholder) and `magic.send`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MagicLinkFlowTest.php`:

```php
<?php

use App\Models\Reservation;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Mail;

it('signs a user in through a login link', function () {
    $user = User::factory()->create(['role' => 'joiner', 'password' => null]);
    ['url' => $url] = app(MagicLinkService::class)->issue('login', ['user_id' => $user->id]);

    $this->get($url)->assertRedirect();

    expect(auth()->id())->toBe($user->id);
});

it('refuses a consumed login link the second time', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    ['url' => $url] = app(MagicLinkService::class)->issue('login', ['user_id' => $user->id]);

    $this->get($url);
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

it('attaches a booking to its owner through an attach link', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => null]);

    ['url' => $url] = app(MagicLinkService::class)->issue('attach', [
        'user_id' => $user->id,
        'reservation_id' => $reservation->id,
    ]);

    $this->get($url)->assertRedirect(route('reservations.show', ['token' => $reservation->public_token]));

    expect($reservation->fresh()->joiner_id)->toBe($user->id)
        ->and(auth()->id())->toBe($user->id);
});

it('emails a sign-in link on request', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => 'joiner']);

    $this->post(route('magic.send'), ['email' => $user->email])->assertRedirect();

    Mail::assertSent(\App\Mail\MagicLinkMail::class);
});

it('does not reveal whether an address is registered', function () {
    Mail::fake();

    $this->post(route('magic.send'), ['email' => 'nobody@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status');

    Mail::assertNothingSent();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MagicLinkFlowTest`
Expected: FAIL — the placeholder route aborts 404.

- [ ] **Step 3: Write the consume controller**

Create `app/Http/Controllers/MagicLinkController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Auth;

class MagicLinkController extends Controller
{
    public function __invoke(string $token, MagicLinkService $links)
    {
        $link = $links->consume($token);

        if (! $link) {
            return redirect()->route('login')
                ->with('status', 'That link has expired or was already used. Ask for a new one below.');
        }

        // Only links we emailed to an address may create a session. Both purposes here are
        // emailed to the address itself; Spec 2's host-shared invite links must not reach this.
        if ($link->user) {
            Auth::login($link->user, remember: true);
        }

        if ($link->purpose === 'attach' && $link->reservation && $link->user) {
            $link->reservation->update(['joiner_id' => $link->user->id]);
        }

        if ($link->reservation) {
            return redirect()->route('reservations.show', ['token' => $link->reservation->public_token]);
        }

        return redirect()->route('home');
    }
}
```

- [ ] **Step 4: Write the send controller and the mailable**

Create `app/Http/Controllers/SendMagicLinkController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Mail\MagicLinkMail;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SendMagicLinkController extends Controller
{
    public function __invoke(Request $request, MagicLinkService $links)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            ['url' => $url] = $links->issue('login', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            Mail::to($user->email)->send(new MagicLinkMail($url, $user->full_name));
        }

        // The response is identical either way: telling a stranger whether an address is
        // registered turns this endpoint into an account-enumeration oracle.
        return back()->with('status', 'If that address has a Peeps account, a sign-in link is on its way.');
    }
}
```

Create `app/Mail/MagicLinkMail.php`:

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url, public string $name) {}

    public function build(): self
    {
        return $this->subject('Your Peeps sign-in link')
            ->view('emails.magic-link');
    }
}
```

Create `resources/views/emails/magic-link.blade.php`:

```blade
<p>Hi {{ $name }},</p>

<p>Here's your sign-in link. It works once and expires in 30 minutes.</p>

<p><a href="{{ $url }}">Sign in to Peeps</a></p>

<p>If you didn't ask for this, you can ignore this email — nobody can get into your account without the link.</p>
```

- [ ] **Step 5: Replace the placeholder route and add the send route**

In `routes/web.php`, replace the placeholder added in Task 2 with:

```php
Route::get('/magic/{token}', \App\Http\Controllers\MagicLinkController::class)->name('magic.consume');
Route::post('/magic', \App\Http\Controllers\SendMagicLinkController::class)
    ->middleware('throttle:5,1')->name('magic.send');
```

- [ ] **Step 6: Send the account email from RegistrationService**

In `app/Services/RegistrationService.php`, add these imports:

```php
use App\Mail\MagicLinkMail;
use Illuminate\Support\Facades\Mail;
```

Then, in `resolveAccount()`, after each `$this->magicLinks->issue(...)` call, send the mail.
For the `EmailTaken` branch, insert before `return`:

```php
            Mail::to($existing->email)->send(new MagicLinkMail($url, $existing->full_name));
```

For the `AccountCreated` branch, insert before `return`:

```php
        Mail::to($user->email)->send(new MagicLinkMail($url, $user->full_name));
```

With `MAIL_MAILER=log` this writes to `storage/logs/laravel.log` and never fails the booking —
the on-screen link from Task 5 remains the guaranteed path.

- [ ] **Step 7: Add the sign-in-link option to the login page**

In `resources/views/auth/login.blade.php`, immediately before the closing `</form>` tag, add:

```blade
    </form>

    {{-- A passwordless account has no password to type. Without this, its owner would be told
         their credentials are wrong with no way forward. --}}
    <form method="POST" action="{{ route('magic.send') }}" class="mt-6 pt-6 border-t border-ink/10">
        @csrf
        <p class="text-sm text-ink/70 mb-2">No password? We'll email you a sign-in link.</p>
        <input type="email" name="email" required placeholder="you@example.com" dusk="magic-email"
               class="w-full rounded-xl border-ink/15 px-3.5 py-2 text-sm focus:border-moss focus:ring-moss">
        <button type="submit" dusk="send-magic"
                class="mt-2 w-full rounded-full border border-ink/15 py-2.5 text-sm font-semibold hover:border-moss transition-colors">
            Email me a sign-in link
        </button>
        @if(session('status'))
            <p class="text-sm text-moss mt-2">{{ session('status') }}</p>
        @endif
    </form>
```

Delete the original `</form>` that this block replaced, so the file has exactly two forms.

- [ ] **Step 8: Run the tests**

Run: `php artisan test --filter=MagicLinkFlowTest`
Expected: PASS, 5 tests.

Then: `php artisan test`
Expected: all pass.

- [ ] **Step 9: Commit** *(gated)*

```bash
git add app/Http/Controllers/MagicLinkController.php app/Http/Controllers/SendMagicLinkController.php app/Mail/MagicLinkMail.php resources/views/emails/magic-link.blade.php app/Services/RegistrationService.php resources/views/auth/login.blade.php routes/web.php tests/Feature/MagicLinkFlowTest.php
git commit -m "feat: passwordless sign-in and booking attachment by magic link"
```

---

### Task 8: Surface the manifest on the host roster

**Files:**
- Modify: `app/Livewire/Host/Roster.php`
- Modify: `resources/views/livewire/host/roster.blade.php`
- Test: `tests/Feature/RosterManifestTest.php`

**Interfaces:**
- Consumes: `Reservation::participants()`, `incompleteParticipants()`, `minors()`,
  `contactName()`, `bookerIsTraveling()` from Task 1.
- Produces: no new public API. The roster renders traveller names, a pending-details count and
  a minor flag.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/RosterManifestTest.php`:

```php
<?php

use App\Livewire\Host\Roster;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'host']);
    $trip = Trip::factory()->create(['host_id' => $this->host->id]);
    $this->departure = TripDeparture::factory()->create([
        'trip_id' => $trip->id, 'starts_at' => now()->addMonth(), 'capacity' => 10,
    ]);
});

it('lists every traveller by name', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 2, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0,
        'first_name' => 'Ada', 'last_name' => 'Reyes',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 1,
        'first_name' => 'Ben', 'last_name' => 'Cruz',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->assertSee('Ada Reyes')
        ->assertSee('Ben Cruz');
});

it('counts travellers still owing details', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 3, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 1]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 2]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->assertSee('2 of 3 still need details');
});

it('flags a traveller who is a minor on the departure date', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0,
        'first_name' => 'Kid', 'last_name' => 'Cruz',
        'birthday' => now()->subYears(15)->format('Y-m-d'),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->assertSee('Minor');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RosterManifestTest`
Expected: FAIL — the roster does not render traveller names.

- [ ] **Step 3: Eager-load participants in the component**

In `app/Livewire/Host/Roster.php`, find the `render()` method's reservation query and add
`participants` to its `with(...)` list. If the query has no `with()`, add
`->with('participants', 'joiner', 'latestPayment')`.

- [ ] **Step 4: Render the manifest in the view**

In `resources/views/livewire/host/roster.blade.php`, inside the reservation card, immediately
after the closing `</div>` of the contact-details block (the `flex flex-wrap gap-x-4` div ending
around line 127), insert:

```blade
                        {{-- Who is actually travelling on this booking. --}}
                        @if($r->participants->isNotEmpty())
                            @php($pending = $r->incompleteParticipants())
                            @php($minors = $r->minors())

                            <div class="mt-3 pt-3 border-t border-ink/8">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="marker text-[10px] font-semibold text-ink/65">Travelling</span>
                                    @if($pending->isNotEmpty())
                                        <span class="rounded-full bg-amber/15 text-amber px-2.5 py-0.5 text-xs font-bold">
                                            {{ $pending->count() }} of {{ $r->participants->count() }} still need details
                                        </span>
                                    @endif
                                    @if($minors->isNotEmpty())
                                        <span class="rounded-full bg-terra/15 text-terra px-2.5 py-0.5 text-xs font-bold">
                                            {{ $minors->count() }} {{ \Illuminate\Support\Str::plural('Minor', $minors->count()) }} · guardian consent
                                        </span>
                                    @endif
                                    @unless($r->bookerIsTraveling())
                                        <span class="rounded-full bg-ink/[.06] text-ink/65 px-2.5 py-0.5 text-xs font-bold">Booker not travelling</span>
                                    @endunless
                                </div>

                                <ol class="text-sm space-y-1">
                                    @foreach($r->participants as $p)
                                        <li class="flex flex-wrap items-center gap-2">
                                            <span class="text-ink/45 tabular-nums w-4">{{ $loop->iteration }}</span>
                                            @if($p->isComplete())
                                                <span>{{ $p->fullName() }}</span>
                                                <span class="text-ink/55 text-xs">{{ $p->birthday->age }}y</span>
                                                @if($p->gender)<span class="text-ink/55 text-xs capitalize">{{ $p->gender }}</span>@endif
                                                @if($p->is_booker)<span class="text-xs font-semibold text-moss">booker</span>@endif
                                                @if($p->phone)<a href="tel:{{ $p->phone }}" class="text-xs font-semibold text-moss hover:underline">{{ $p->phone }}</a>@endif
                                            @else
                                                <span class="text-ink/45 italic">Details still needed</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=RosterManifestTest`
Expected: PASS, 3 tests.

Then: `php artisan test`
Expected: all pass.

- [ ] **Step 6: Commit** *(gated)*

```bash
git add app/Livewire/Host/Roster.php resources/views/livewire/host/roster.blade.php tests/Feature/RosterManifestTest.php
git commit -m "feat: traveller manifest with pending-details and minor flags on the roster"
```

---

### Task 9: End-to-end browser coverage

**Files:**
- Create: `tests/Browser/JoinerRegistrationTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–8. Uses the `dusk` hooks added in Task 6
  (`party-size`, `contact-first`, `contact-last`, `contact-email`, `contact-phone`,
  `seat-0-birthday`, `create-account`, `submit-registration`) and Task 5 (`booking-link`).
- Produces: nothing consumed elsewhere.

- [ ] **Step 1: Write the browser test**

Create `tests/Browser/JoinerRegistrationTest.php`:

```php
<?php

use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Laravel\Dusk\Browser;

it('lets a stranger register as a guest and reach their booking', function () {
    $trip = Trip::factory()->create(['status' => 'published', 'price' => 3500]);
    TripDeparture::factory()->create(['trip_id' => $trip->id, 'capacity' => 10, 'status' => 'open']);

    $this->browse(function (Browser $browser) use ($trip) {
        $browser->visit("/t/{$trip->slug}/join")
            ->waitForText('Join this trip')
            ->type('@contact-first', 'Ada')
            ->type('@contact-last', 'Reyes')
            ->type('@contact-email', 'ada@example.com')
            ->type('@contact-phone', '09171234567')
            ->type('@seat-0-birthday', '1998-04-02')
            ->uncheck('@create-account')
            ->click('@submit-registration')
            ->waitForText('Save this link')
            ->assertVisible('@booking-link');
    });

    $reservation = \App\Models\Reservation::first();

    expect($reservation->joiner_id)->toBeNull()
        ->and($reservation->contact_email)->toBe('ada@example.com')
        ->and($reservation->participants)->toHaveCount(1);
});

it('creates an account when the toggle is left on', function () {
    $trip = Trip::factory()->create(['status' => 'published', 'price' => 3500]);
    TripDeparture::factory()->create(['trip_id' => $trip->id, 'capacity' => 10, 'status' => 'open']);

    $this->browse(function (Browser $browser) use ($trip) {
        $browser->visit("/t/{$trip->slug}/join")
            ->waitForText('Join this trip')
            ->type('@contact-first', 'Ben')
            ->type('@contact-last', 'Cruz')
            ->type('@contact-email', 'ben@example.com')
            ->type('@contact-phone', '09998887777')
            ->type('@seat-0-birthday', '1995-01-10')
            ->click('@submit-registration')          // toggle left checked
            ->waitForText('Save this link');
    });

    $user = User::where('email', 'ben@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->role)->toBe('joiner');
});

it('books for others without taking a seat', function () {
    $trip = Trip::factory()->create(['status' => 'published', 'price' => 3500]);
    TripDeparture::factory()->create(['trip_id' => $trip->id, 'capacity' => 10, 'status' => 'open']);

    $this->browse(function (Browser $browser) use ($trip) {
        $browser->visit("/t/{$trip->slug}/join")
            ->waitForText('Join this trip')
            ->type('@contact-first', 'Cara')
            ->type('@contact-last', 'Lim')
            ->type('@contact-email', 'cara@example.com')
            ->type('@contact-phone', '09171112222')
            ->uncheck('@booker-traveling')
            ->waitForText('take no seat')
            ->type('@seat-0-first', 'Dee')
            ->type('@seat-0-last', 'Santos')
            ->type('@seat-0-birthday', '2000-03-15')
            ->uncheck('@create-account')
            ->click('@submit-registration')
            ->waitForText('Save this link');
    });

    $reservation = \App\Models\Reservation::first();

    expect($reservation->contactName())->toBe('Cara Lim')
        ->and($reservation->bookerIsTraveling())->toBeFalse()
        ->and($reservation->participants->first()->fullName())->toBe('Dee Santos');
});
```

- [ ] **Step 2: Run the browser tests**

Run:
```bash
php artisan dusk --filter=JoinerRegistrationTest
```
Expected: PASS, 3 tests. If Chromedriver is stale, run `php artisan dusk:chrome-driver --detect`
first — see `docs/DUSK.md`.

- [ ] **Step 3: Run both suites**

Run:
```bash
php artisan test && php artisan dusk
```
Expected: both green.

- [ ] **Step 4: Commit** *(gated)*

```bash
git add tests/Browser/JoinerRegistrationTest.php
git commit -m "test: end-to-end joiner registration coverage"
```

---

## Verification checklist

Before declaring the feature done, confirm each by running the command and reading the output —
not by assuming:

- [ ] `php artisan test` — all green
- [ ] `php artisan dusk` — all green
- [ ] `php artisan migrate:fresh --seed` succeeds, proving the four new migrations compose with
      the 9 pre-existing pending ones
- [ ] A guest booking is reachable at `/r/{token}` in an incognito window
- [ ] `storage/logs/laravel.log` contains a magic-link URL after opting into an account
- [ ] Pasting that URL into a fresh browser signs the account in
- [ ] The host roster shows traveller names, the pending-details count, and a minor flag
