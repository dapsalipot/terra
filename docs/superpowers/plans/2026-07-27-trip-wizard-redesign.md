# Trip Wizard Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the host trip-creation wizard as a three-step, live-previewing composer backed by ordered multi-destination trips, a reusable host meetup-preset library, a custom drag-drop upload component, and human-readable validation.

**Architecture:** Three new tables (`trip_destinations`, `host_meetup_presets`, `trip_photos`) plus a nullable `trips.difficulty` column. `trips.place_id` is retained and synced to the first destination so ~20 existing read sites keep working. Three new Blade+Alpine components (`x-upload`, `x-place-picker`, `x-money-input`) are consumed by a rewritten `App\Livewire\Host\Trips\Create` and a new `App\Livewire\Host\MeetupPresets` CRUD page.

**Tech Stack:** Laravel 12, Livewire 3 (with bundled Alpine), Tailwind CSS v3, Pest 3, Laravel Dusk, MySQL.

## Global Constraints

- **Never run `git commit`, `git push`, or create branches.** The user's global CLAUDE.md forbids it unless explicitly requested in the current message. This plan contains no commit steps by design; report changes and stop.
- Design system is "Trail Earth": buttons are **only** `bg-pine` or `bg-moss`. `terra` (`#BC4B26`) and `amber` (`#A8761F`) are accents only — never button fills.
- The Tailwind token `terra` is **terracotta the colour**, not the old brand name. Never rename `text-terra` / `bg-terra`.
- No emoji in UI chrome. Fraunces (`font-serif`) is for names, titles and prices only.
- Body/secondary text must be `text-ink/65` or darker — `text-ink/45`–`/60` fail WCAG AA on `canvas`.
- Dev server runs on `http://127.0.0.1:8020` (`php artisan serve --port=8020`). Dusk runs against `:8030`.
- Tests run on SQLite; dev/prod run MySQL. Never use MySQL-only SQL (e.g. `FIELD()`) in queries the test suite touches.
- Run `npm run build` after any Blade/CSS change before browser verification.
- Uploads go to the `public` disk. Its URL is host-relative (`/storage`) — do not reintroduce `APP_URL`.

---

## File Structure

**Migrations (create):**
- `database/migrations/2026_07_28_100000_create_trip_destinations_table.php` — ordered itinerary + backfill from `trips.place_id`
- `database/migrations/2026_07_28_100100_create_host_meetup_presets_table.php` — host-owned reusable spots
- `database/migrations/2026_07_28_100200_create_trip_photos_table.php` — carousel photos
- `database/migrations/2026_07_28_100300_add_difficulty_to_trips_table.php` — nullable override

**Models (create):** `app/Models/TripDestination.php`, `app/Models/HostMeetupPreset.php`, `app/Models/TripPhoto.php`
**Models (modify):** `app/Models/Trip.php` (relations + `effective_difficulty`), `app/Models/User.php` (`meetupPresets()`)

**Blade components (create):**
- `resources/views/components/upload.blade.php` — drag-drop uploader
- `resources/views/components/place-picker.blade.php` — destination combobox + chips
- `resources/views/components/money-input.blade.php` — currency-aware price field

**Livewire (create):** `app/Livewire/Host/MeetupPresets.php` + `resources/views/livewire/host/meetup-presets.blade.php`
**Livewire (rewrite):** `app/Livewire/Host/Trips/Create.php` + `resources/views/livewire/host/trips/create.blade.php`

**Routes/nav (modify):** `routes/web.php` (add `host.meetup-presets`), `resources/views/components/layouts/host.blade.php:25-29` (nav array)

**Tests (create):** `tests/Feature/TripDestinationTest.php`, `tests/Feature/MeetupPresetTest.php`, `tests/Feature/TripWizardTest.php`, `tests/Browser/TripWizardFlowTest.php`

---

## Task 1: Ordered destinations schema

**Files:**
- Create: `database/migrations/2026_07_28_100000_create_trip_destinations_table.php`
- Create: `app/Models/TripDestination.php`
- Modify: `app/Models/Trip.php`
- Test: `tests/Feature/TripDestinationTest.php`

**Interfaces:**
- Consumes: existing `Trip`, `Place` models.
- Produces: `Trip::destinations()` HasMany ordered by `position`; `Trip::syncDestinations(array $rows): void` where each row is `['place_id' => int, 'day_label' => ?string]`; `TripDestination` with `$fillable`-free `$guarded = []`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TripDestinationTest.php`:

```php
<?php

use App\Models\Place;
use App\Models\Trip;

it('stores destinations in order and syncs place_id to the first', function () {
    $trip = Trip::factory()->published()->create();
    $a = Place::factory()->create(['name' => 'Ambangeg']);
    $b = Place::factory()->create(['name' => 'Mount Pulag']);

    $trip->syncDestinations([
        ['place_id' => $a->id, 'day_label' => 'Day 1'],
        ['place_id' => $b->id, 'day_label' => 'Day 2'],
    ]);

    $trip->refresh()->load('destinations');

    expect($trip->destinations->pluck('place_id')->all())->toBe([$a->id, $b->id])
        ->and($trip->destinations->pluck('position')->all())->toBe([0, 1])
        ->and($trip->destinations->first()->day_label)->toBe('Day 1')
        // legacy read sites still work
        ->and($trip->place_id)->toBe($a->id);
});

it('replaces destinations on resync rather than appending', function () {
    $trip = Trip::factory()->published()->create();
    $a = Place::factory()->create();
    $b = Place::factory()->create();

    $trip->syncDestinations([['place_id' => $a->id, 'day_label' => null]]);
    $trip->syncDestinations([['place_id' => $b->id, 'day_label' => null]]);

    expect($trip->refresh()->destinations)->toHaveCount(1)
        ->and($trip->destinations->first()->place_id)->toBe($b->id)
        ->and($trip->place_id)->toBe($b->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/Herd/terra && php artisan test tests/Feature/TripDestinationTest.php`
Expected: FAIL — `Call to undefined method App\Models\Trip::syncDestinations()`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_28_100000_create_trip_destinations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('day_label')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'position']);
        });

        // Backfill: every existing trip keeps its destination as stop 0.
        foreach (DB::table('trips')->whereNotNull('place_id')->get(['id', 'place_id']) as $trip) {
            DB::table('trip_destinations')->insert([
                'trip_id' => $trip->id,
                'place_id' => $trip->place_id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_destinations');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/TripDestination.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDestination extends Model
{
    protected $guarded = [];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }
}
```

- [ ] **Step 5: Add the relation and sync method to Trip**

In `app/Models/Trip.php`, add after the existing `meetups()` method:

```php
    public function destinations()
    {
        return $this->hasMany(TripDestination::class)->orderBy('position');
    }

    /**
     * Replace this trip's itinerary. `trips.place_id` is kept in sync with the first stop
     * so the ~20 existing read sites that still query place_id keep working unchanged.
     *
     * @param  array<int, array{place_id: int, day_label: ?string}>  $rows
     */
    public function syncDestinations(array $rows): void
    {
        $this->destinations()->delete();

        foreach (array_values($rows) as $i => $row) {
            $this->destinations()->create([
                'place_id' => $row['place_id'],
                'day_label' => $row['day_label'] ?? null,
                'position' => $i,
            ]);
        }

        if ($first = $rows[0]['place_id'] ?? null) {
            $this->forceFill(['place_id' => $first])->save();
        }
    }
```

- [ ] **Step 6: Migrate and run the test**

Run: `cd ~/Herd/terra && php artisan migrate && php artisan test tests/Feature/TripDestinationTest.php`
Expected: PASS — 2 passed

- [ ] **Step 7: Verify the backfill and full suite**

Run: `cd ~/Herd/terra && php artisan tinker --execute="echo DB::table('trip_destinations')->count();" && php artisan test`
Expected: destination count equals the number of trips with a `place_id` (10 in the seeded dev DB); full suite still green.

---

## Task 2: Difficulty override

**Files:**
- Create: `database/migrations/2026_07_28_100300_add_difficulty_to_trips_table.php`
- Modify: `app/Models/Trip.php`
- Test: `tests/Feature/TripDestinationTest.php` (append)

**Interfaces:**
- Consumes: `Trip::destinations()` from Task 1.
- Produces: `Trip::getEffectiveDifficultyAttribute(): ?string` — returns `trips.difficulty` when set, otherwise the first destination's `place.difficulty`, otherwise null.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/TripDestinationTest.php`:

```php
it('inherits difficulty from the first destination when not overridden', function () {
    $trip = Trip::factory()->published()->create(['difficulty' => null]);
    $place = Place::factory()->create(['difficulty' => 'hard']);
    $trip->syncDestinations([['place_id' => $place->id, 'day_label' => null]]);

    expect($trip->refresh()->effective_difficulty)->toBe('hard');
});

it('lets an explicit trip difficulty override the destination', function () {
    $trip = Trip::factory()->published()->create(['difficulty' => 'easy']);
    $place = Place::factory()->create(['difficulty' => 'hard']);
    $trip->syncDestinations([['place_id' => $place->id, 'day_label' => null]]);

    expect($trip->refresh()->effective_difficulty)->toBe('easy');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/Herd/terra && php artisan test tests/Feature/TripDestinationTest.php`
Expected: FAIL — `Unknown column 'difficulty' in 'field list'`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_28_100300_add_difficulty_to_trips_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Null = inherit from the first destination's place.
            $table->string('difficulty', 20)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('difficulty');
        });
    }
};
```

- [ ] **Step 4: Add the accessor**

In `app/Models/Trip.php`, add after `syncDestinations()`:

```php
    /** Trip-level difficulty when set, otherwise the first destination's. */
    public function getEffectiveDifficultyAttribute(): ?string
    {
        return $this->difficulty ?: $this->destinations->first()?->place?->difficulty;
    }
```

- [ ] **Step 5: Migrate and run tests**

Run: `cd ~/Herd/terra && php artisan migrate && php artisan test tests/Feature/TripDestinationTest.php`
Expected: PASS — 4 passed

---

## Task 3: Host meetup presets (model + CRUD page)

**Files:**
- Create: `database/migrations/2026_07_28_100100_create_host_meetup_presets_table.php`
- Create: `app/Models/HostMeetupPreset.php`
- Create: `app/Livewire/Host/MeetupPresets.php`
- Create: `resources/views/livewire/host/meetup-presets.blade.php`
- Modify: `app/Models/User.php`, `routes/web.php`, `resources/views/components/layouts/host.blade.php:25-29`
- Test: `tests/Feature/MeetupPresetTest.php`

**Interfaces:**
- Consumes: existing `User` model, `EnsureUserIsHost` middleware (alias `host`).
- Produces: `User::meetupPresets()` HasMany; route name `host.meetup-presets` at `/host/meetup-points`; `HostMeetupPreset` columns `host_id, label, address, lat, lng, image, note`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/MeetupPresetTest.php`:

```php
<?php

use App\Livewire\Host\MeetupPresets;
use App\Models\HostMeetupPreset;
use App\Models\HostProfile;
use App\Models\User;
use Livewire\Livewire;

function presetHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
    HostProfile::factory()->for($host)->create();

    return $host;
}

it('blocks joiners from the meetup preset page', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);

    $this->actingAs($joiner)->get('/host/meetup-points')->assertForbidden();
});

it('lets a host save a meetup preset', function () {
    $host = presetHost();

    Livewire::actingAs($host)->test(MeetupPresets::class)
        ->set('label', 'Cubao terminal')
        ->set('address', 'EDSA cor. Aurora Blvd')
        ->set('lat', '14.6220')
        ->set('lng', '121.0530')
        ->set('note', 'Look for the blue van')
        ->call('save')
        ->assertHasNoErrors();

    $preset = $host->meetupPresets()->sole();

    expect($preset->label)->toBe('Cubao terminal')
        ->and((float) $preset->lat)->toBe(14.6220);
});

it('only shows a host their own presets', function () {
    $mine = presetHost();
    $other = User::factory()->create(['role' => 'host', 'handle' => 'sunchaser']);
    HostProfile::factory()->for($other)->create();

    HostMeetupPreset::create(['host_id' => $mine->id, 'label' => 'Mine']);
    HostMeetupPreset::create(['host_id' => $other->id, 'label' => 'Theirs']);

    Livewire::actingAs($mine)->test(MeetupPresets::class)
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});

it('refuses to delete another hosts preset', function () {
    $mine = presetHost();
    $other = User::factory()->create(['role' => 'host', 'handle' => 'sunchaser']);
    $theirs = HostMeetupPreset::create(['host_id' => $other->id, 'label' => 'Theirs']);

    Livewire::actingAs($mine)->test(MeetupPresets::class)
        ->call('delete', $theirs->id)
        ->assertNotFound();

    expect(HostMeetupPreset::find($theirs->id))->not->toBeNull();
});

it('rejects coordinates outside the valid range with a friendly message', function () {
    $host = presetHost();

    Livewire::actingAs($host)->test(MeetupPresets::class)
        ->set('label', 'Nowhere')
        ->set('lat', '999')
        ->call('save')
        ->assertHasErrors(['lat']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/Herd/terra && php artisan test tests/Feature/MeetupPresetTest.php`
Expected: FAIL — `Class "App\Livewire\Host\MeetupPresets" not found`

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_28_100100_create_host_meetup_presets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_meetup_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('label');
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('image')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('host_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_meetup_presets');
    }
};
```

- [ ] **Step 4: Create the model and User relation**

Create `app/Models/HostMeetupPreset.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HostMeetupPreset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
```

In `app/Models/User.php`, add after `reviewsReceived()`:

```php
    public function meetupPresets()
    {
        return $this->hasMany(HostMeetupPreset::class, 'host_id')->orderBy('label');
    }
```

- [ ] **Step 5: Create the Livewire component**

Create `app/Livewire/Host/MeetupPresets.php`:

```php
<?php

namespace App\Livewire\Host;

use App\Models\HostMeetupPreset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class MeetupPresets extends Component
{
    use WithFileUploads;

    public ?int $editingId = null;
    public string $label = '';
    public string $address = '';
    public string $lat = '';
    public string $lng = '';
    public string $note = '';
    public $image = null;

    public string $saved = '';

    protected function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:160'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'label' => 'meetup name',
            'lat' => 'latitude',
            'lng' => 'longitude',
            'image' => 'photo',
        ];
    }

    protected function messages(): array
    {
        return [
            'lat.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'lng.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'lat.numeric' => 'The latitude should be a number like 14.6220.',
            'lng.numeric' => 'The longitude should be a number like 121.0530.',
        ];
    }

    public function edit(int $id): void
    {
        $preset = Auth::user()->meetupPresets()->findOrFail($id);

        $this->editingId = $preset->id;
        $this->label = $preset->label;
        $this->address = (string) $preset->address;
        $this->lat = (string) ($preset->lat ?? '');
        $this->lng = (string) ($preset->lng ?? '');
        $this->note = (string) $preset->note;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'label', 'address', 'lat', 'lng', 'note', 'image', 'saved']);
    }

    public function save(): void
    {
        $this->validate();

        $preset = $this->editingId
            ? Auth::user()->meetupPresets()->findOrFail($this->editingId)
            : new HostMeetupPreset(['host_id' => Auth::id()]);

        if ($this->image) {
            if ($preset->image && Storage::disk('public')->exists($preset->image)) {
                Storage::disk('public')->delete($preset->image);
            }
            $preset->image = $this->image->store('meetups', 'public');
        }

        $preset->fill([
            'host_id' => Auth::id(),
            'label' => $this->label,
            'address' => $this->address ?: null,
            'note' => $this->note ?: null,
            'lat' => $this->lat !== '' ? $this->lat : null,
            'lng' => $this->lng !== '' ? $this->lng : null,
        ])->save();

        $this->cancel();
        $this->saved = 'Meetup point saved.';
    }

    public function delete(int $id): void
    {
        $preset = Auth::user()->meetupPresets()->findOrFail($id);

        if ($preset->image && Storage::disk('public')->exists($preset->image)) {
            Storage::disk('public')->delete($preset->image);
        }

        $preset->delete();
    }

    public function render()
    {
        return view('livewire.host.meetup-presets', [
            'presets' => Auth::user()->meetupPresets()->get(),
        ])->layout('components.layouts.host', ['title' => 'Terra · Meetup points']);
    }
}
```

- [ ] **Step 6: Create the view**

Create `resources/views/livewire/host/meetup-presets.blade.php`:

```blade
<div class="max-w-3xl">
    <div class="flex items-end justify-between mb-6">
        <div>
            <h1 class="font-serif font-semibold text-3xl">Meetup points</h1>
            <p class="text-ink/65 mt-1 text-sm">Save the spots you use often, then pick them when you post a trip.</p>
        </div>
        @if($saved)
            <span class="rounded-full bg-moss/12 text-moss px-4 py-2 text-sm font-semibold">{{ $saved }}</span>
        @endif
    </div>

    <form wire:submit="save" class="rounded-2xl border border-ink/10 bg-surface p-6 mb-8">
        <h2 class="font-serif font-semibold text-lg mb-4">{{ $editingId ? 'Edit spot' : 'Add a spot' }}</h2>

        <label class="block mb-4">
            <span class="block text-sm font-semibold text-ink/70 mb-1.5">Name</span>
            <input wire:model="label" type="text" placeholder="Cubao terminal · 10:00 PM"
                   class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
            @error('label')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </label>

        <label class="block mb-4">
            <span class="block text-sm font-semibold text-ink/70 mb-1.5">Address</span>
            <input wire:model="address" type="text" placeholder="EDSA cor. Aurora Blvd, Quezon City"
                   class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
            @error('address')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </label>

        <label class="block mb-4">
            <span class="block text-sm font-semibold text-ink/70 mb-1.5">Note</span>
            <input wire:model="note" type="text" placeholder="Look for the blue van by Gate 2"
                   class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
            @error('note')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </label>

        <div class="grid grid-cols-2 gap-4">
            <label class="block">
                <span class="block text-sm font-semibold text-ink/70 mb-1.5">Latitude</span>
                <input wire:model="lat" type="text" inputmode="decimal" placeholder="14.6220"
                       class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                @error('lat')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <label class="block">
                <span class="block text-sm font-semibold text-ink/70 mb-1.5">Longitude</span>
                <input wire:model="lng" type="text" inputmode="decimal" placeholder="121.0530"
                       class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                @error('lng')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
        </div>
        <p class="text-[11px] text-ink/60 mt-2">In Google Maps, right-click the spot → click the coordinates to copy, then paste them here.</p>

        <div class="mt-4">
            <x-upload wire:model="image" label="Photo of the spot" :multiple="false" :max="1" />
            @error('image')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-3 mt-6">
            <button type="submit" class="rounded-full bg-pine text-cream px-6 py-3 font-semibold text-sm hover:bg-pine/90 transition-colors">
                {{ $editingId ? 'Save changes' : 'Add spot' }}
            </button>
            @if($editingId)
                <button type="button" wire:click="cancel" class="text-sm font-semibold text-ink/65 hover:text-ink">Cancel</button>
            @endif
        </div>
    </form>

    <div class="space-y-3" dusk="preset-list">
        @forelse($presets as $preset)
            <div class="flex items-start gap-4 rounded-2xl border border-ink/10 bg-surface p-4" wire:key="preset-{{ $preset->id }}">
                @if($preset->image_url)
                    <img src="{{ $preset->image_url }}" alt="" class="w-20 h-20 rounded-xl object-cover shrink-0">
                @endif
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">{{ $preset->label }}</div>
                    @if($preset->address)<p class="text-sm text-ink/65 mt-0.5">{{ $preset->address }}</p>@endif
                    @if($preset->note)<p class="text-sm text-ink/70 mt-1 italic">{{ $preset->note }}</p>@endif
                    @if($preset->lat && $preset->lng)
                        <p class="text-xs text-ink/60 mt-1.5">Pinned · {{ $preset->lat }}, {{ $preset->lng }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button wire:click="edit({{ $preset->id }})" class="text-sm font-semibold text-ink/65 hover:text-ink">Edit</button>
                    <button wire:click="delete({{ $preset->id }})" wire:confirm="Delete this meetup point?"
                            class="text-sm font-semibold text-terra">Delete</button>
                </div>
            </div>
        @empty
            <div class="text-center py-12 rounded-2xl border border-dashed border-ink/15">
                <p class="font-serif text-lg text-ink/70">No saved spots yet.</p>
                <p class="text-ink/60 text-sm mt-1">Add the terminal or trailhead you use most.</p>
            </div>
        @endforelse
    </div>
</div>
```

- [ ] **Step 7: Add the route and sidebar link**

In `routes/web.php`, inside the `Route::middleware(['auth', 'host'])->prefix('host')` group, after the `host.profile` line:

```php
    Route::get('/meetup-points', \App\Livewire\Host\MeetupPresets::class)->name('host.meetup-presets');
```

In `resources/views/components/layouts/host.blade.php`, replace the `$nav` array (lines 25-29) with:

```blade
        @php($nav = [
            ['host.dashboard', 'Dashboard', 'M4 11l8-6.6 8 6.6v8a1.6 1.6 0 0 1-1.6 1.6H5.6A1.6 1.6 0 0 1 4 19z'],
            ['host.trips.create', 'New trip', 'M12 5.5v13M5.5 12h13'],
            ['host.meetup-presets', 'Meetup points', 'M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11z'],
            ['host.profile', 'Your page', 'M12 12.6a3.7 3.7 0 1 0 0-7.4 3.7 3.7 0 0 0 0 7.4zM4.6 20.2c0-4.1 3.4-6.2 7.4-6.2s7.4 2.1 7.4 6.2'],
        ])
```

- [ ] **Step 8: Migrate and run the test**

Run: `cd ~/Herd/terra && php artisan migrate && php artisan test tests/Feature/MeetupPresetTest.php`
Expected: PASS — 5 passed

> Note: Step 6 uses `<x-upload>`, built in Task 4. Run Task 4 before browser-verifying this page; the Pest tests in this task do not render the upload component's JS and pass regardless.

---

## Task 4: `x-upload` drag-drop component

**Files:**
- Create: `resources/views/components/upload.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: Livewire's `wire:model` file upload contract.
- Produces: `<x-upload wire:model="prop" label="..." :multiple="bool" :max="int" />`. For `:multiple="true"` the bound property must be an array of `TemporaryUploadedFile`; for false, a single file. Emits no events — Livewire handles state.

- [ ] **Step 1: Create the component**

Create `resources/views/components/upload.blade.php`:

```blade
@props(['label' => 'Photos', 'multiple' => false, 'max' => 10, 'hint' => null])
@php($model = $attributes->wire('model')->value())

{{-- Drag-drop uploader. Falls back to a plain file input when JS is unavailable. --}}
<div x-data="{
        over: false,
        get files() { return $wire.get('{{ $model }}') ?? [] },
        pick(e) { this.over = false; this.$refs.input.files = e.dataTransfer.files; this.$refs.input.dispatchEvent(new Event('change')) }
     }">
    @if($label)
        <span class="block text-sm font-semibold text-ink/70 mb-1.5">{{ $label }}</span>
    @endif

    <label @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="pick($event)"
           :class="over ? 'border-moss bg-moss/5' : 'border-ink/20 hover:border-ink/35'"
           class="flex flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed px-6 py-8 cursor-pointer transition-colors">
        <svg viewBox="0 0 24 24" class="w-6 h-6 text-ink/45" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 16V4M7.5 8.5L12 4l4.5 4.5M4 16v2.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V16"/>
        </svg>
        <span class="text-sm font-semibold">Drag {{ $multiple ? 'photos' : 'a photo' }} here</span>
        <span class="text-xs text-ink/60">or click to browse{{ $multiple ? ' · up to '.$max : '' }}</span>
        <input x-ref="input" type="file" accept="image/*" {{ $multiple ? 'multiple' : '' }}
               {{ $attributes->only('wire:model') }} class="hidden">
    </label>

    @if($hint)<p class="text-[11px] text-ink/60 mt-1.5">{{ $hint }}</p>@endif

    <p wire:loading wire:target="{{ $model }}" class="text-xs text-ink/65 mt-2">Uploading…</p>

    {{-- Thumbnails. Reorder by dragging; removal is delegated to the parent component. --}}
    <div class="grid grid-cols-4 gap-2 mt-3">
        @if($multiple)
            @foreach(($this->{$model} ?? []) as $i => $file)
                @if($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                    <div class="relative group" wire:key="up-{{ $model }}-{{ $i }}">
                        <img src="{{ $file->temporaryUrl() }}" alt="" class="aspect-square w-full object-cover rounded-lg">
                        <span class="absolute bottom-1 left-1 rounded bg-ink/60 px-1.5 text-[10px] font-bold text-cream">{{ $i + 1 }}</span>
                    </div>
                @endif
            @endforeach
        @else
            @if(($this->{$model} ?? null) instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                <div class="col-span-4">
                    <img src="{{ $this->{$model}->temporaryUrl() }}" alt="" class="h-40 w-full object-cover rounded-xl">
                </div>
            @endif
        @endif
    </div>
</div>
```

- [ ] **Step 2: Verify it renders on the preset page**

Run: `cd ~/Herd/terra && npm run build && curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8020/host/meetup-points`
Expected: `302` (redirect to login when unauthenticated) — confirms the Blade compiles without error. A `500` means the component has a syntax error.

- [ ] **Step 3: Run the full suite**

Run: `cd ~/Herd/terra && php artisan test`
Expected: all green — the component is presentational and adds no rules.

---

## Task 5: `x-place-picker` and `x-money-input`

**Files:**
- Create: `resources/views/components/place-picker.blade.php`
- Create: `resources/views/components/money-input.blade.php`

**Interfaces:**
- Consumes: parent Livewire component exposing `$places` (Collection of `Place`) and a `destinations` array property of `['place_id' => int, 'day_label' => ?string]`, plus methods `addDestination(int $placeId)`, `removeDestination(int $index)`, `moveDestination(int $index, int $direction)`.
- Produces: `<x-place-picker :places="$places" :destinations="$destinations" />` and `<x-money-input wire:model="price" currency="PHP" />`.

- [ ] **Step 1: Create the place picker**

Create `resources/views/components/place-picker.blade.php`:

```blade
@props(['places', 'destinations'])
@php($chosen = collect($destinations)->pluck('place_id')->filter()->all())

<div x-data="{ q: '', open: false }" class="relative">
    <span class="block text-sm font-semibold text-ink/70 mb-1.5">Destinations</span>

    {{-- chosen stops, in order --}}
    @if(count($destinations))
        <div class="space-y-2 mb-3">
            @foreach($destinations as $i => $row)
                @php($place = $places->firstWhere('id', $row['place_id']))
                <div class="flex items-center gap-3 rounded-xl border border-ink/12 bg-surface px-4 py-3" wire:key="dest-{{ $i }}">
                    <span class="grid place-items-center w-6 h-6 rounded-full bg-moss text-cream text-[11px] font-bold shrink-0">{{ $i + 1 }}</span>
                    <span class="font-semibold min-w-0 truncate">{{ $place?->name }}<span class="text-ink/45 font-normal"> · {{ $place?->country }}</span></span>
                    <input wire:model.blur="destinations.{{ $i }}.day_label" type="text" placeholder="Day {{ $i + 1 }}"
                           class="ml-auto w-28 rounded-lg border-ink/15 px-2.5 py-1.5 text-xs focus:border-moss focus:ring-moss">
                    <span class="flex items-center gap-1 shrink-0">
                        <button type="button" wire:click="moveDestination({{ $i }}, -1)" @disabled($i === 0)
                                aria-label="Move up" class="p-1 text-ink/45 hover:text-ink disabled:opacity-30">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 14l6-6 6 6"/></svg>
                        </button>
                        <button type="button" wire:click="moveDestination({{ $i }}, 1)" @disabled($i === count($destinations) - 1)
                                aria-label="Move down" class="p-1 text-ink/45 hover:text-ink disabled:opacity-30">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 10l6 6 6-6"/></svg>
                        </button>
                        <button type="button" wire:click="removeDestination({{ $i }})" aria-label="Remove destination"
                                class="p-1 text-ink/45 hover:text-terra">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </button>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- search-as-you-type, never a <select> --}}
    <input x-model="q" @focus="open = true" @click.outside="open = false" type="text"
           placeholder="Search a destination — try “pulag”"
           class="w-full rounded-xl border-ink/15 px-4 py-3 focus:border-moss focus:ring-moss">

    <div x-show="open && q.length > 0" x-transition.opacity
         class="absolute z-20 inset-x-0 mt-1 max-h-64 overflow-y-auto rounded-xl border border-ink/12 bg-surface shadow-lg">
        @foreach($places as $place)
            @continue(in_array($place->id, $chosen, true))
            <button type="button" wire:click="addDestination({{ $place->id }})" @click="q = ''; open = false"
                    dusk="place-option-{{ $place->id }}"
                    x-show="'{{ Str::lower($place->name.' '.$place->country) }}'.includes(q.toLowerCase())"
                    class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-ink/[.03]">
                <span class="font-semibold">{{ $place->name }}</span>
                <span class="text-sm text-ink/60">{{ $place->country }}</span>
                <span class="ml-auto text-[11px] font-bold uppercase tracking-wide text-ink/45">{{ $place->category }}</span>
            </button>
        @endforeach
    </div>
</div>
```

- [ ] **Step 2: Create the money input**

Create `resources/views/components/money-input.blade.php`:

```blade
@props(['currency' => 'PHP', 'suffix' => 'per person'])

<div>
    <span class="block text-sm font-semibold text-ink/70 mb-1.5">Price</span>
    <div class="flex items-stretch rounded-xl border border-ink/15 overflow-hidden focus-within:border-moss focus-within:ring-1 focus-within:ring-moss">
        <span class="grid place-items-center px-4 bg-ink/[.04] text-sm font-bold text-ink/70 border-r border-ink/10">{{ $currency }}</span>
        <input {{ $attributes->merge(['class' => 'flex-1 border-0 focus:ring-0 font-serif text-xl px-4 py-3']) }}
               type="text" inputmode="decimal" placeholder="2,000">
        <span class="grid place-items-center px-4 text-sm text-ink/60 border-l border-ink/10">{{ $suffix }}</span>
    </div>
</div>
```

- [ ] **Step 3: Build and confirm no compile errors**

Run: `cd ~/Herd/terra && npm run build && php artisan view:clear && php artisan test`
Expected: build succeeds, full suite green.

---

## Task 6: Rewrite the wizard component

**Files:**
- Modify: `app/Livewire/Host/Trips/Create.php` (full rewrite)
- Test: `tests/Feature/TripWizardTest.php`

**Interfaces:**
- Consumes: `Trip::syncDestinations()` (Task 1), `Trip::effective_difficulty` (Task 2), `User::meetupPresets()` (Task 3).
- Produces: public properties `$destinations`, `$inclusions`, `$meetups`, `$departures`, `$cover`, `$photos`, `$price`, `$difficulty`, `$title`, `$description`; methods `addDestination(int)`, `removeDestination(int)`, `moveDestination(int,int)`, `addInclusion()`, `removeInclusion(int)`, `addMeetup()`, `removeMeetup(int)`, `applyPreset(int $index, int $presetId)`, `addDeparture()`, `removeDeparture(int)`, `toStep2()`, `toStep3()`, `back()`, `publish()`; computed `readiness(): array`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TripWizardTest.php`:

```php
<?php

use App\Livewire\Host\Trips\Create;
use App\Models\HostMeetupPreset;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function wizardHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

it('publishes a trip with an ordered itinerary', function () {
    $host = wizardHost();
    $a = Place::factory()->create(['name' => 'Ambangeg']);
    $b = Place::factory()->create(['name' => 'Mount Pulag']);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $a->id)
        ->call('addDestination', $b->id)
        ->set('title', 'Pulag Traverse')
        ->call('toStep2')
        ->set('price', '2000')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 12]])
        ->call('toStep3')
        ->call('publish');

    $trip = Trip::where('title', 'Pulag Traverse')->sole();

    expect($trip->destinations->pluck('place_id')->all())->toBe([$a->id, $b->id])
        ->and($trip->place_id)->toBe($a->id);
});

it('copies a preset into the meetup rather than linking to it', function () {
    $host = wizardHost();
    $place = Place::factory()->create();
    $preset = HostMeetupPreset::create([
        'host_id' => $host->id, 'label' => 'Cubao terminal',
        'address' => 'EDSA', 'lat' => 14.6220, 'lng' => 121.0530, 'note' => 'Blue van',
    ]);

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Preset Trip')
        ->call('toStep2')
        ->call('applyPreset', 0, $preset->id)
        ->set('price', '900')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 8]])
        ->call('toStep3')
        ->call('publish');

    $trip = Trip::where('title', 'Preset Trip')->sole();
    expect($trip->meetups->first()->label)->toBe('Cubao terminal');

    // Editing the preset afterwards must NOT rewrite history on a published trip.
    $preset->update(['label' => 'Renamed terminal']);

    expect($trip->refresh()->meetups->first()->label)->toBe('Cubao terminal');
});

it('saves a one-off meetup to the library when asked', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Library Trip')
        ->call('toStep2')
        ->set('meetups', [[
            'label' => 'New trailhead', 'address' => '', 'lat' => '', 'lng' => '',
            'note' => '', 'image' => null, 'save_to_library' => true,
        ]])
        ->set('price', '500')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 6]])
        ->call('toStep3')
        ->call('publish');

    expect($host->meetupPresets()->where('label', 'New trailhead')->exists())->toBeTrue();
});

it('requires a destination before leaving step 1', function () {
    $host = wizardHost();

    Livewire::actingAs($host)->test(Create::class)
        ->set('title', 'No Destination')
        ->call('toStep2')
        ->assertHasErrors(['destinations']);
});

it('reports friendly validation text instead of raw attribute paths', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    $component = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Bad Pin')
        ->call('toStep2')
        ->set('price', '500')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 8]])
        ->set('meetups', [[
            'label' => 'Nowhere', 'address' => '', 'lat' => '999', 'lng' => '',
            'note' => '', 'image' => null, 'save_to_library' => false,
        ]])
        ->call('toStep3');

    $errors = $component->errors()->get('meetups.0.lat');

    expect($errors[0])->toContain('valid map pin')
        ->and($errors[0])->not->toContain('meetups.0.lat');
});

it('stores the cover and carousel photos in order', function () {
    Storage::fake('public');
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Photo Trip')
        ->set('cover', UploadedFile::fake()->image('cover.jpg'))
        ->set('photos', [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ])
        ->call('toStep2')
        ->set('price', '700')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 10]])
        ->call('toStep3')
        ->call('publish');

    $trip = Trip::where('title', 'Photo Trip')->sole();

    expect($trip->cover_photo)->not->toBeNull()
        ->and($trip->photos->pluck('position')->all())->toBe([0, 1]);

    Storage::disk('public')->assertExists($trip->cover_photo);
    $trip->photos->each(fn ($p) => Storage::disk('public')->assertExists($p->path));
});

it('drops blank inclusion and meetup rows', function () {
    $host = wizardHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Sparse Trip')
        ->set('inclusions', [
            ['label' => 'Guide', 'note' => '', 'image' => null],
            ['label' => '', 'note' => '', 'image' => null],
        ])
        ->call('toStep2')
        ->set('meetups', [
            ['label' => 'Real spot', 'address' => '', 'lat' => '', 'lng' => '', 'note' => '', 'image' => null, 'save_to_library' => false],
            ['label' => '', 'address' => '', 'lat' => '', 'lng' => '', 'note' => '', 'image' => null, 'save_to_library' => false],
        ])
        ->set('price', '400')
        ->set('departures', [['starts_at' => now()->addWeek()->toDateString(), 'ends_at' => '', 'capacity' => 5]])
        ->call('toStep3')
        ->call('publish');

    $trip = Trip::where('title', 'Sparse Trip')->sole();

    expect($trip->inclusionItems)->toHaveCount(1)
        ->and($trip->meetups)->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/Herd/terra && php artisan test tests/Feature/TripWizardTest.php`
Expected: FAIL — `Method App\Livewire\Host\Trips\Create::addDestination does not exist.`

- [ ] **Step 3: Add the TripPhoto migration and model**

Create `database/migrations/2026_07_28_100200_create_trip_photos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_photos');
    }
};
```

Create `app/Models/TripPhoto.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TripPhoto extends Model
{
    protected $guarded = [];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
```

In `app/Models/Trip.php`, add after `destinations()`:

```php
    public function photos()
    {
        return $this->hasMany(TripPhoto::class)->orderBy('position');
    }
```

- [ ] **Step 4: Rewrite the wizard component**

Replace the entire contents of `app/Livewire/Host/Trips/Create.php`:

```php
<?php

namespace App\Livewire\Host\Trips;

use App\Models\HostMeetupPreset;
use App\Models\Place;
use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    public int $step = 1;

    // Step 1 — the trip
    /** @var array<int, array{place_id:int, day_label:?string}> */
    public array $destinations = [];
    public string $title = '';
    public string $description = '';
    public string $difficulty = '';          // '' = inherit from the first destination
    /** @var array<int, array{label:string, note:string, image:mixed}> */
    public array $inclusions = [
        ['label' => 'Transport', 'note' => '', 'image' => null],
        ['label' => 'Permits', 'note' => '', 'image' => null],
        ['label' => 'Guide', 'note' => '', 'image' => null],
    ];
    public $cover = null;
    /** @var array uploaded carousel photos */
    public array $photos = [];

    // Step 2 — logistics
    /** @var array<int, array{label:string, address:string, lat:string, lng:string, note:string, image:mixed, save_to_library:bool}> */
    public array $meetups = [
        ['label' => '', 'address' => '', 'lat' => '', 'lng' => '', 'note' => '', 'image' => null, 'save_to_library' => false],
    ];
    public array $departures = [
        ['starts_at' => '', 'ends_at' => '', 'capacity' => 12],
    ];
    public string $price = '';

    // ── destinations ────────────────────────────────────────────────
    public function addDestination(int $placeId): void
    {
        if (collect($this->destinations)->contains('place_id', $placeId)) {
            return;
        }

        $this->destinations[] = ['place_id' => $placeId, 'day_label' => null];
    }

    public function removeDestination(int $i): void
    {
        unset($this->destinations[$i]);
        $this->destinations = array_values($this->destinations);
    }

    public function moveDestination(int $i, int $direction): void
    {
        $target = $i + $direction;

        if (! isset($this->destinations[$i], $this->destinations[$target])) {
            return;
        }

        [$this->destinations[$i], $this->destinations[$target]] = [$this->destinations[$target], $this->destinations[$i]];
    }

    // ── repeaters ───────────────────────────────────────────────────
    public function addInclusion(): void
    {
        $this->inclusions[] = ['label' => '', 'note' => '', 'image' => null];
    }

    public function removeInclusion(int $i): void
    {
        unset($this->inclusions[$i]);
        $this->inclusions = array_values($this->inclusions);
    }

    public function addMeetup(): void
    {
        $this->meetups[] = ['label' => '', 'address' => '', 'lat' => '', 'lng' => '', 'note' => '', 'image' => null, 'save_to_library' => false];
    }

    public function removeMeetup(int $i): void
    {
        unset($this->meetups[$i]);
        $this->meetups = array_values($this->meetups);
    }

    /** Copy a saved preset's values into a meetup row. Values are copied, never linked. */
    public function applyPreset(int $i, int $presetId): void
    {
        $preset = Auth::user()->meetupPresets()->findOrFail($presetId);

        $this->meetups[$i] = [
            'label' => $preset->label,
            'address' => (string) $preset->address,
            'lat' => (string) ($preset->lat ?? ''),
            'lng' => (string) ($preset->lng ?? ''),
            'note' => (string) $preset->note,
            'image' => null,
            'save_to_library' => false,
        ];
    }

    public function addDeparture(): void
    {
        $this->departures[] = ['starts_at' => '', 'ends_at' => '', 'capacity' => 12];
    }

    public function removeDeparture(int $i): void
    {
        unset($this->departures[$i]);
        $this->departures = array_values($this->departures);
    }

    // ── validation ──────────────────────────────────────────────────
    protected function validationAttributes(): array
    {
        return [
            'destinations' => 'destinations',
            'title' => 'trip title',
            'price' => 'price',
            'cover' => 'cover photo',
            'photos.*' => 'photo',
            'inclusions.*.label' => 'inclusion name',
            'inclusions.*.image' => 'inclusion photo',
            'meetups.*.label' => 'meetup name',
            'meetups.*.address' => 'address',
            'meetups.*.lat' => 'latitude',
            'meetups.*.lng' => 'longitude',
            'meetups.*.image' => 'meetup photo',
            'departures.*.starts_at' => 'start date',
            'departures.*.capacity' => 'capacity',
        ];
    }

    protected function messages(): array
    {
        return [
            'destinations.required' => 'Add at least one destination.',
            'destinations.min' => 'Add at least one destination.',
            'departures.*.starts_at.required' => 'Every departure needs a start date.',
            'meetups.*.lat.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'meetups.*.lng.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'meetups.*.lat.numeric' => 'The latitude should be a number like 14.6220.',
            'meetups.*.lng.numeric' => 'The longitude should be a number like 121.0530.',
        ];
    }

    public function toStep2(): void
    {
        $this->validate([
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*.place_id' => ['required', 'exists:places,id'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'difficulty' => ['nullable', 'in:,easy,challenging,hard'],
            'inclusions.*.label' => ['nullable', 'string', 'max:60'],
            'inclusions.*.note' => ['nullable', 'string', 'max:160'],
            'inclusions.*.image' => ['nullable', 'image', 'max:5120'],
            'cover' => ['nullable', 'image', 'max:5120'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:8192'],
        ]);

        $this->step = 2;
    }

    public function toStep3(): void
    {
        $this->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'departures' => ['required', 'array', 'min:1'],
            'departures.*.starts_at' => ['required', 'date'],
            'departures.*.capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'meetups.*.label' => ['nullable', 'string', 'max:80'],
            'meetups.*.address' => ['nullable', 'string', 'max:160'],
            'meetups.*.note' => ['nullable', 'string', 'max:160'],
            'meetups.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'meetups.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'meetups.*.image' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->step = 3;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /** Soft checklist shown on step 3. Never blocks publishing. */
    public function readiness(): array
    {
        return [
            ['Cover photo', (bool) $this->cover],
            ['Carousel photos', count($this->photos) > 0],
            ['Description', filled($this->description)],
            ['Inclusions', collect($this->inclusions)->filter(fn ($c) => filled($c['label']))->isNotEmpty()],
            ['Meetup point', collect($this->meetups)->filter(fn ($m) => filled($m['label']))->isNotEmpty()],
        ];
    }

    public function publish()
    {
        $this->toStep2();
        $this->toStep3();

        $inclusions = array_values(array_filter($this->inclusions, fn ($c) => filled($c['label'] ?? null)));
        $meetups = array_values(array_filter($this->meetups, fn ($m) => filled($m['label'] ?? null)));

        $trip = DB::transaction(function () use ($inclusions, $meetups) {
            $trip = Trip::create([
                'host_id' => Auth::id(),
                'place_id' => $this->destinations[0]['place_id'],
                'title' => $this->title,
                'description' => $this->description ?: null,
                'difficulty' => $this->difficulty ?: null,
                'meetup_point' => $meetups[0]['label'] ?? null,
                'price' => $this->price,
                'cover_photo' => $this->cover ? $this->cover->store('covers', 'public') : null,
                'status' => 'published',
            ]);

            $trip->syncDestinations($this->destinations);

            foreach (array_values($this->photos) as $i => $photo) {
                $trip->photos()->create(['path' => $photo->store('trips', 'public'), 'position' => $i]);
            }

            foreach ($this->departures as $d) {
                $trip->departures()->create([
                    'starts_at' => $d['starts_at'],
                    'ends_at' => $d['ends_at'] ?: null,
                    'capacity' => (int) $d['capacity'],
                    'status' => 'open',
                ]);
            }

            foreach ($inclusions as $i => $c) {
                $trip->inclusionItems()->create([
                    'label' => $c['label'],
                    'note' => $c['note'] ?: null,
                    'image' => $c['image'] ? $c['image']->store('inclusions', 'public') : null,
                    'position' => $i,
                ]);
            }

            foreach ($meetups as $i => $m) {
                $image = $m['image'] ? $m['image']->store('meetups', 'public') : null;

                $trip->meetups()->create([
                    'label' => $m['label'],
                    'address' => $m['address'] ?: null,
                    'lat' => $m['lat'] !== '' ? $m['lat'] : null,
                    'lng' => $m['lng'] !== '' ? $m['lng'] : null,
                    'note' => $m['note'] ?: null,
                    'image' => $image,
                    'position' => $i,
                ]);

                if ($m['save_to_library'] ?? false) {
                    HostMeetupPreset::create([
                        'host_id' => Auth::id(),
                        'label' => $m['label'],
                        'address' => $m['address'] ?: null,
                        'lat' => $m['lat'] !== '' ? $m['lat'] : null,
                        'lng' => $m['lng'] !== '' ? $m['lng'] : null,
                        'note' => $m['note'] ?: null,
                        'image' => $image,
                    ]);
                }
            }

            return $trip;
        });

        return $this->redirectRoute('trips.show', ['trip' => $trip->slug], navigate: true);
    }

    public function render()
    {
        return view('livewire.host.trips.create', [
            'places' => Place::orderBy('name')->get(),
            'presets' => Auth::user()->meetupPresets()->get(),
        ])->layout('components.layouts.host', ['title' => 'Terra · New trip']);
    }
}
```

- [ ] **Step 5: Migrate and run the wizard tests**

Run: `cd ~/Herd/terra && php artisan migrate && php artisan test tests/Feature/TripWizardTest.php`
Expected: PASS — 7 passed

---

## Task 7: Rebuild the wizard view

**Files:**
- Modify: `resources/views/livewire/host/trips/create.blade.php` (full rewrite)

**Interfaces:**
- Consumes: everything produced by Task 6, plus `<x-upload>`, `<x-place-picker>`, `<x-money-input>` from Tasks 4–5, and `<x-trip-card>`.
- Produces: `dusk` hooks `wizard-next`, `wizard-back`, `wizard-publish`, `destination-search`.

- [ ] **Step 1: Replace the view**

Replace the entire contents of `resources/views/livewire/host/trips/create.blade.php`:

```blade
@php($currency = auth()->user()->hostProfile?->currency ?? 'PHP')
@php($firstPlace = count($destinations) ? $places->firstWhere('id', $destinations[0]['place_id']) : null)
@php($steps = [1 => 'The trip', 2 => 'Logistics', 3 => 'Publish'])

<div class="grid lg:grid-cols-[13rem_minmax(0,1fr)_20rem] gap-8 items-start max-w-6xl">

    {{-- ══ RAIL ══ --}}
    <nav class="hidden lg:block sticky top-8" aria-label="Wizard steps">
        <ol class="space-y-1">
            @foreach($steps as $n => $name)
                <li class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ $step === $n ? 'bg-ink/[.05]' : '' }}">
                    <span class="grid place-items-center w-6 h-6 rounded-full text-[11px] font-bold shrink-0
                                 {{ $step > $n ? 'bg-moss text-cream' : ($step === $n ? 'bg-pine text-cream' : 'bg-ink/10 text-ink/50') }}">
                        @if($step > $n)
                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                        @else{{ $n }}@endif
                    </span>
                    <span class="text-sm font-semibold {{ $step === $n ? 'text-ink' : 'text-ink/60' }}">{{ $name }}</span>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- ══ FORM ══ --}}
    <div>
        @if($step === 1)
            <h1 class="font-serif font-semibold text-3xl mb-1">New trip</h1>
            <p class="text-ink/65 text-sm mb-7">Where it goes, what's included, and how it looks.</p>

            <div class="space-y-7">
                <section>
                    <x-place-picker :places="$places" :destinations="$destinations" />
                    @error('destinations')<p class="text-terra text-xs mt-1.5">{{ $message }}</p>@enderror
                </section>

                <label class="block">
                    <span class="block text-sm font-semibold text-ink/70 mb-1.5">Title</span>
                    <input wire:model.live="title" type="text" dusk="trip-title" placeholder="Mount Pulag Sunrise Traverse"
                           class="w-full rounded-xl border-ink/15 px-4 py-3 font-serif text-lg focus:border-moss focus:ring-moss">
                    @error('title')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="block text-sm font-semibold text-ink/70 mb-1.5">Description <span class="font-normal text-ink/50">— optional</span></span>
                    <textarea wire:model.live="description" rows="3" placeholder="What the trip is like, who it suits, what to expect."
                              class="w-full rounded-xl border-ink/15 px-4 py-3 focus:border-moss focus:ring-moss"></textarea>
                    @error('description')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>

                <section>
                    <span class="block text-sm font-semibold text-ink/70 mb-1.5">Difficulty <span class="font-normal text-ink/50">— optional</span></span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="$set('difficulty', '')"
                                class="rounded-full px-4 py-2 text-sm font-semibold border {{ $difficulty === '' ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">
                            Inherit{{ $firstPlace?->difficulty ? ' · '.ucfirst($firstPlace->difficulty) : '' }}
                        </button>
                        @foreach(['easy', 'challenging', 'hard'] as $d)
                            <button type="button" wire:click="$set('difficulty', '{{ $d }}')"
                                    class="rounded-full px-4 py-2 text-sm font-semibold border {{ $difficulty === $d ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">{{ ucfirst($d) }}</button>
                        @endforeach
                    </div>
                </section>

                <section>
                    <span class="block text-sm font-semibold text-ink/70 mb-2">What's included</span>
                    <div class="space-y-3">
                        @foreach($inclusions as $i => $c)
                            <div class="rounded-xl border border-ink/10 p-4" wire:key="inc-{{ $i }}">
                                <div class="flex items-center gap-2">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                                    <input wire:model.live="inclusions.{{ $i }}.label" type="text" placeholder="Transport"
                                           class="flex-1 rounded-lg border-ink/15 px-3 py-2 text-sm focus:border-moss focus:ring-moss">
                                    @if(count($inclusions) > 1)
                                        <button type="button" wire:click="removeInclusion({{ $i }})" aria-label="Remove inclusion" class="text-ink/40 hover:text-terra">
                                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    @endif
                                </div>
                                @error('inclusions.'.$i.'.label')<p class="text-terra text-xs mt-1.5">{{ $message }}</p>@enderror
                                <input wire:model="inclusions.{{ $i }}.note" type="text" placeholder="Air-conditioned van, Manila round trip"
                                       class="w-full rounded-lg border-ink/15 px-3 py-2 text-sm mt-2 focus:border-moss focus:ring-moss">
                                <div class="mt-2">
                                    <x-upload wire:model="inclusions.{{ $i }}.image" label="" :multiple="false" :max="1" />
                                    @error('inclusions.'.$i.'.image')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addInclusion" class="mt-3 text-sm font-semibold text-moss">＋ Add an inclusion</button>
                </section>

                <section>
                    <x-upload wire:model="cover" label="Cover photo" :multiple="false" :max="1"
                              hint="The single image used on cards and the trip hero." />
                    @error('cover')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </section>

                <section>
                    <x-upload wire:model="photos" label="More photos" :multiple="true" :max="10"
                              hint="Shown as a carousel on the trip page." />
                    @error('photos.*')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </section>
            </div>

            <button wire:click="toStep2" dusk="wizard-next"
                    class="mt-9 w-full rounded-full bg-pine text-cream py-3.5 font-semibold hover:bg-pine/90 transition-colors">Continue to logistics</button>

        @elseif($step === 2)
            <h1 class="font-serif font-semibold text-3xl mb-1">Logistics</h1>
            <p class="text-ink/65 text-sm mb-7">Where you meet, when you leave, what it costs.</p>

            <div class="space-y-7">
                <section>
                    <span class="block text-sm font-semibold text-ink/70 mb-2">Meetup points</span>
                    <div class="space-y-4">
                        @foreach($meetups as $i => $m)
                            <div class="rounded-xl border border-ink/10 p-4" wire:key="meet-{{ $i }}">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="grid place-items-center w-6 h-6 rounded-full bg-terra text-cream text-[11px] font-bold shrink-0">{{ $i + 1 }}</span>
                                    <input wire:model.live="meetups.{{ $i }}.label" type="text" placeholder="Cubao terminal · 10:00 PM"
                                           class="flex-1 rounded-lg border-ink/15 px-3 py-2 text-sm focus:border-moss focus:ring-moss">
                                    @if($presets->isNotEmpty())
                                        <select wire:change="applyPreset({{ $i }}, $event.target.value)"
                                                class="rounded-lg border-ink/15 px-2 py-2 text-xs text-ink/70 focus:border-moss focus:ring-moss">
                                            <option value="">Pick saved…</option>
                                            @foreach($presets as $preset)
                                                <option value="{{ $preset->id }}">{{ $preset->label }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if(count($meetups) > 1)
                                        <button type="button" wire:click="removeMeetup({{ $i }})" aria-label="Remove meetup point" class="text-ink/40 hover:text-terra">
                                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    @endif
                                </div>
                                @error('meetups.'.$i.'.label')<p class="text-terra text-xs mb-2">{{ $message }}</p>@enderror

                                <input wire:model="meetups.{{ $i }}.address" type="text" placeholder="Street address"
                                       class="w-full rounded-lg border-ink/15 px-3 py-2 text-sm mb-2 focus:border-moss focus:ring-moss">
                                <input wire:model="meetups.{{ $i }}.note" type="text" placeholder="Look for the blue van"
                                       class="w-full rounded-lg border-ink/15 px-3 py-2 text-sm mb-2 focus:border-moss focus:ring-moss">

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <input wire:model="meetups.{{ $i }}.lat" type="text" inputmode="decimal" placeholder="Latitude 14.6220"
                                               class="w-full rounded-lg border-ink/15 px-3 py-2 text-sm focus:border-moss focus:ring-moss">
                                        @error('meetups.'.$i.'.lat')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <input wire:model="meetups.{{ $i }}.lng" type="text" inputmode="decimal" placeholder="Longitude 121.0530"
                                               class="w-full rounded-lg border-ink/15 px-3 py-2 text-sm focus:border-moss focus:ring-moss">
                                        @error('meetups.'.$i.'.lng')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <p class="text-[11px] text-ink/60 mt-1.5">In Google Maps, right-click the spot → click the coordinates to copy.</p>

                                <div class="mt-3">
                                    <x-upload wire:model="meetups.{{ $i }}.image" label="" :multiple="false" :max="1" />
                                    @error('meetups.'.$i.'.image')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                </div>

                                <label class="flex items-center gap-2 mt-3 text-xs font-semibold text-ink/70">
                                    <input type="checkbox" wire:model="meetups.{{ $i }}.save_to_library" class="rounded border-ink/25 text-moss focus:ring-moss">
                                    Save this spot to my library
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addMeetup" class="mt-3 text-sm font-semibold text-moss">＋ Add another meetup point</button>
                </section>

                <section>
                    <span class="block text-sm font-semibold text-ink/70 mb-2">Departures <span class="font-normal text-ink/50">— one QR covers all</span></span>
                    <div class="space-y-3">
                        @foreach($departures as $i => $d)
                            <div class="flex gap-2 items-end rounded-xl border border-ink/10 p-3" wire:key="dep-{{ $i }}">
                                <div class="flex-1">
                                    <span class="block text-xs text-ink/60 mb-1">Starts</span>
                                    <input type="date" wire:model="departures.{{ $i }}.starts_at" dusk="dep-{{ $i }}-start" class="w-full rounded-lg border-ink/15 px-3 py-2 text-sm focus:border-moss focus:ring-moss">
                                    @error('departures.'.$i.'.starts_at')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div class="flex-1">
                                    <span class="block text-xs text-ink/60 mb-1">Ends</span>
                                    <input type="date" wire:model="departures.{{ $i }}.ends_at" class="w-full rounded-lg border-ink/15 px-3 py-2 text-sm focus:border-moss focus:ring-moss">
                                </div>
                                <div class="w-24">
                                    <span class="block text-xs text-ink/60 mb-1">Spots</span>
                                    <input type="number" wire:model="departures.{{ $i }}.capacity" class="w-full rounded-lg border-ink/15 px-3 py-2 text-sm focus:border-moss focus:ring-moss">
                                    @error('departures.'.$i.'.capacity')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                @if(count($departures) > 1)
                                    <button type="button" wire:click="removeDeparture({{ $i }})" aria-label="Remove departure" class="mb-2 text-ink/40 hover:text-terra">
                                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addDeparture" class="mt-3 text-sm font-semibold text-moss">＋ Add a departure</button>
                    @error('departures')<p class="text-terra text-xs mt-1.5">{{ $message }}</p>@enderror
                </section>

                <section>
                    <x-money-input wire:model.live="price" :currency="$currency" dusk="trip-price" />
                    @error('price')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </section>
            </div>

            <div class="flex gap-3 mt-9">
                <button wire:click="back" dusk="wizard-back" class="rounded-full border border-ink/20 px-7 py-3.5 font-semibold hover:bg-ink/5 transition-colors">Back</button>
                <button wire:click="toStep3" dusk="wizard-next" class="flex-1 rounded-full bg-pine text-cream py-3.5 font-semibold hover:bg-pine/90 transition-colors">Review</button>
            </div>

        @else
            <h1 class="font-serif font-semibold text-3xl mb-1">Review &amp; publish</h1>
            <p class="text-ink/65 text-sm mb-7">This is exactly how joiners will see it.</p>

            <div class="rounded-2xl border border-ink/10 bg-surface p-5 mb-6">
                <h2 class="font-serif font-semibold text-lg mb-3">Ready to go</h2>
                <ul class="space-y-2">
                    @foreach($this->readiness() as [$label, $ok])
                        <li class="flex items-center gap-2.5 text-sm">
                            @if($ok)
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss shrink-0" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                                <span class="text-ink/75">{{ $label }}</span>
                            @else
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-amber shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg>
                                <span class="text-ink/60">{{ $label }} <span class="text-ink/45">— optional, but it helps</span></span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex gap-3">
                <button wire:click="back" dusk="wizard-back" class="rounded-full border border-ink/20 px-7 py-3.5 font-semibold hover:bg-ink/5 transition-colors">Back</button>
                <button wire:click="publish" dusk="wizard-publish" wire:loading.attr="disabled"
                        class="flex-1 rounded-full bg-pine text-cream py-3.5 font-semibold hover:bg-pine/90 transition-colors">
                    <span wire:loading.remove wire:target="publish">Publish trip</span>
                    <span wire:loading wire:target="publish">Publishing…</span>
                </button>
            </div>
        @endif
    </div>

    {{-- ══ LIVE PREVIEW ══ --}}
    <aside class="hidden lg:block sticky top-8">
        <span class="marker text-[11px] font-semibold text-ink/55">Live preview</span>
        <div class="mt-2 rounded-3xl overflow-hidden border border-ink/8 bg-surface shadow-[0_10px_30px_-18px_rgba(31,28,21,.35)]">
            <div class="relative aspect-[16/10] bg-ink/5">
                @if($cover instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                    <img src="{{ $cover->temporaryUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                @endif
                <div class="absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-ink/80 to-transparent"></div>
                <div class="absolute inset-x-0 bottom-0 p-4">
                    <h3 class="font-serif font-semibold text-lg leading-tight text-cream">{{ $title ?: 'Your trip title' }}</h3>
                    @if($firstPlace)
                        <p class="text-sm text-cream/80 mt-1">{{ $firstPlace->name }} · {{ $firstPlace->country }}</p>
                    @endif
                </div>
            </div>
            <div class="p-4">
                @if(count($destinations) > 1)
                    <p class="text-xs font-semibold text-ink/65 mb-2">{{ count($destinations) }} stops</p>
                @endif
                <p class="text-sm text-ink/65 line-clamp-2">{{ $description ?: 'Your description appears here.' }}</p>
                <p class="text-sm font-medium text-ink/60 mt-3 pt-3 border-t border-ink/6">
                    {{ $currency }} {{ $price !== '' ? number_format((float) $price) : '—' }} <span class="text-ink/40">/ person</span>
                </p>
            </div>
        </div>
    </aside>
</div>
```

- [ ] **Step 2: Build and smoke-test**

Run: `cd ~/Herd/terra && npm run build && php artisan view:clear && php artisan test`
Expected: build succeeds, full Pest suite green (99 existing + new tests).

- [ ] **Step 3: Verify in the browser**

Log in as `host@terra.test` / `password`, visit `http://127.0.0.1:8020/host/trips/create`. Confirm: the rail shows 3 steps, typing in the destination search filters places, adding two destinations shows numbered chips with reorder arrows, and the right-hand preview updates as you type the title.

---

## Task 8: Show the itinerary and carousel on the public trip page

**Files:**
- Modify: `resources/views/livewire/trips/show.blade.php`
- Modify: `app/Livewire/Trips/Show.php`
- Test: `tests/Feature/TripWizardTest.php` (append)

**Interfaces:**
- Consumes: `Trip::destinations()`, `Trip::photos()`, `Trip::effective_difficulty`, `<x-carousel>`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/TripWizardTest.php`:

```php
it('shows every destination on the public trip page', function () {
    $host = wizardHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Route Trip']);
    $a = Place::factory()->create(['name' => 'Ambangeg']);
    $b = Place::factory()->create(['name' => 'Akiki Trail']);

    $trip->syncDestinations([
        ['place_id' => $a->id, 'day_label' => 'Day 1'],
        ['place_id' => $b->id, 'day_label' => 'Day 2'],
    ]);

    $this->get('/t/'.$trip->slug)->assertOk()
        ->assertSee('Ambangeg')
        ->assertSee('Akiki Trail')
        ->assertSee('Day 2');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/Herd/terra && php artisan test tests/Feature/TripWizardTest.php --filter="shows every destination"`
Expected: FAIL — "Akiki Trail" not found.

- [ ] **Step 3: Eager-load the new relations**

In `app/Livewire/Trips/Show.php`, change the `mount()` load line to:

```php
        $this->trip = $trip->load(['host.hostProfile', 'place', 'openDepartures', 'meetups', 'inclusionItems', 'destinations.place', 'photos']);
```

- [ ] **Step 4: Render the itinerary and carousel**

In `resources/views/livewire/trips/show.blade.php`, immediately after the closing `</div>` of the host row (the block ending with the host's trips-led line, before `@if($trip->description)`), insert:

```blade
            {{-- ═══ ITINERARY ═══ --}}
            @if($trip->destinations->count() > 1)
                <h2 class="font-serif font-semibold text-xl mt-9 mb-4">The route</h2>
                <ol class="space-y-3">
                    @foreach($trip->destinations as $i => $stop)
                        <li class="flex items-start gap-3">
                            <span class="grid place-items-center w-7 h-7 rounded-full bg-moss text-cream text-xs font-bold shrink-0">{{ $i + 1 }}</span>
                            <div>
                                <div class="font-semibold">{{ $stop->place?->name }}<span class="text-ink/50 font-normal"> · {{ $stop->place?->country }}</span></div>
                                @if($stop->day_label)<p class="text-sm text-ink/65">{{ $stop->day_label }}</p>@endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            {{-- ═══ PHOTO CAROUSEL ═══ --}}
            @if($trip->photos->isNotEmpty())
                <h2 class="font-serif font-semibold text-xl mt-9 mb-4">Photos</h2>
                <x-carousel :images="$trip->photos->map(fn($p) => ['url' => $p->url, 'alt' => $trip->title])" ratio="aspect-[16/10]" rounded="rounded-2xl" />
            @endif
```

- [ ] **Step 5: Use the effective difficulty in the hero**

In the same file, replace the difficulty chip line:

```blade
                <span class="rounded-full bg-ink/40 backdrop-blur px-3 py-1 text-xs font-semibold text-cream capitalize">{{ $trip->place->difficulty }}</span>
```

with:

```blade
                @if($trip->effective_difficulty)
                    <span class="rounded-full bg-ink/40 backdrop-blur px-3 py-1 text-xs font-semibold text-cream capitalize">{{ $trip->effective_difficulty }}</span>
                @endif
```

- [ ] **Step 6: Run the tests**

Run: `cd ~/Herd/terra && npm run build && php artisan test`
Expected: full suite green.

---

## Task 9: Dusk coverage for the wizard flow

**Files:**
- Create: `tests/Browser/TripWizardFlowTest.php`

**Interfaces:**
- Consumes: dusk hooks `destination-search`, `wizard-next`, `wizard-publish` from Task 7.

- [ ] **Step 1: Write the browser test**

Create `tests/Browser/TripWizardFlowTest.php`:

```php
<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TripWizardFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_a_host_publishes_a_multi_destination_trip(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

        $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'country' => 'Philippines']);
        Place::factory()->create(['name' => 'Sagada', 'country' => 'Philippines']);

        $this->browse(function (Browser $browser) use ($host, $pulag) {
            $browser->loginAs($host)
                ->visit('/host/trips/create')
                ->waitForText('New trip')
                // pick a destination from the search combobox — options are <button>s, so
                // click the dusk hook rather than clickLink()
                ->type('@destination-search', 'pulag')
                ->waitFor('@place-option-'.$pulag->id)
                ->click('@place-option-'.$pulag->id)
                ->waitForText('Mount Pulag')
                ->type('@trip-title', 'Dusk Traverse')
                ->click('@wizard-next')
                ->waitForText('Logistics')
                ->type('@trip-price', '1500')
                ->type('@dep-0-start', now()->addWeek()->format('Y-m-d'))
                ->click('@wizard-next')
                ->waitForText('Review & publish')
                ->click('@wizard-publish')
                ->waitForText('Dusk Traverse');
        });

        $this->assertDatabaseHas('trips', ['title' => 'Dusk Traverse', 'place_id' => $pulag->id]);
    }
}
```

- [ ] **Step 2: Add the dusk hook to the search input**

In `resources/views/components/place-picker.blade.php`, add `dusk="destination-search"` to the search input:

```blade
    <input x-model="q" @focus="open = true" @click.outside="open = false" type="text" dusk="destination-search"
           placeholder="Search a destination — try “pulag”"
           class="w-full rounded-xl border-ink/15 px-4 py-3 focus:border-moss focus:ring-moss">
```

The option buttons already carry `dusk="place-option-{{ $place->id }}"` from Task 5, and the money input forwards its `dusk` attribute through `$attributes->merge()`.

- [ ] **Step 3: Run Dusk**

Run: `cd ~/Herd/terra && npm run build && php artisan dusk tests/Browser/TripWizardFlowTest.php`
Expected: PASS — 1 passed.

- [ ] **Step 4: Run the whole suite**

Run: `cd ~/Herd/terra && php artisan test && php artisan dusk`
Expected: all Pest and all Dusk green.

---

## Task 10: Update the spec's out-of-scope note and verify end to end

**Files:**
- Modify: `docs/superpowers/specs/2026-07-27-trip-wizard-redesign-design.md`

- [ ] **Step 1: Manual verification pass**

Run the dev server and check each of the 12 original requirements:

```bash
cd ~/Herd/terra && php artisan serve --port=8020
```

1. Wizard has rail + form + live preview → `/host/trips/create`
2. Drag-drop upload zones replace native inputs
3. Step 1 reads "The trip", no "Mountain" anywhere
4. Difficulty shows an "Inherit" chip and is skippable
5. Inclusions are in step 1
6. Destination is a search combobox, not a `<select>`
7. Two destinations can be added and reordered
8. `/host/meetup-points` saves spots; the wizard offers "Pick saved…"
9. An invalid latitude reads "That doesn't look like a valid map pin", not `meetups.0.lat`
10. Price shows the currency inline
11. Step 3 shows a readiness checklist and preview
12. Cover + multiple photos upload, and the carousel appears on the trip page

- [ ] **Step 2: Record what shipped**

In `docs/superpowers/specs/2026-07-27-trip-wizard-redesign-design.md`, append to §9:

```markdown
### Shipped 2026-07-28
All 12 requirements implemented. Still deferred: migrating the ~20 read sites off
`trips.place_id`, and an edit flow for published trips.
```

- [ ] **Step 3: Final suite**

Run: `cd ~/Herd/terra && php artisan test && php artisan dusk`
Expected: all green. **Do not commit** — report the changes and stop.

---

## Self-Review

**Spec coverage:** All 9 spec sections map to tasks — §3 schema → Tasks 1, 2, 3, 6(step 3); §4 wizard → Tasks 6, 7; §5 components → Tasks 4, 5; §6 validation → Tasks 3, 6; §7 error handling → Task 6 (`DB::transaction`); §8 testing → Tasks 1, 2, 3, 6, 8, 9; §9 out-of-scope → Task 10.

**Placeholder scan:** No TBD/TODO. Every code step contains complete, runnable code.

**Type consistency:** `syncDestinations(array $rows)` is defined in Task 1 and called identically in Tasks 6 and 8. `applyPreset(int $i, int $presetId)` matches between Task 6's component and Task 7's view. `readiness(): array` returns `[label, bool]` pairs, consumed by Task 7's `@foreach ... as [$label, $ok]`. `effective_difficulty` is defined in Task 2 and consumed in Task 8.

**Defect found and fixed during review:** the Dusk test originally used `clickLink('Mount Pulag')` and bare field names (`type('title', …)`). Livewire inputs carry no `name` attribute and the combobox options are `<button>` elements, so both would have failed. Fixed by adding `dusk` hooks (`trip-title`, `trip-price`, `dep-0-start`, `place-option-{id}`) in Tasks 5 and 7 and selecting by hook in Task 9.
