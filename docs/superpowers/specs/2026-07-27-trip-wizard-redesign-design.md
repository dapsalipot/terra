# Trip Wizard Redesign — Design

**Date:** 2026-07-27
**Status:** Approved
**Scope:** The host trip-creation wizard (`/host/trips/create`), a new host meetup-preset library, and the schema needed to support both.

## 1. Problem

The current three-step wizard works but is thin and, in places, wrong:

- Step 1 is labelled "Mountain" although Peeps covers all trip types.
- `difficulty` is validated as **required** in step 1 but there is no `trips.difficulty` column and `publish()` never writes it. The field blocks progress and does nothing.
- Destination is a `<select>` over every place — it will not scale and reads as a database form.
- A trip can only have one destination, so multi-day routes cannot be expressed.
- Meetup points must be retyped for every trip even when a host always departs from the same terminal.
- Validation surfaces raw array paths: `The meetups.0.lat field must be between -90 and 90.`
- File inputs are the browser default; there is no drag-drop, preview, reorder, or multi-photo support.
- Price is a bare number field with no currency context.
- Step 3 is a label/value table that tells the host nothing they did not just type.

## 2. Decisions

| # | Decision | Rationale |
|---|---|---|
| 1 | Multi-destination is an **ordered itinerary** | Real trips are routes: Day 1 Sagada → Day 2 Pulag. Order carries meaning. |
| 2 | Meetup presets are a **host-owned managed library** | There is no admin role — only `joiner` and `host`. Hosts curate their own spots. |
| 3 | Preset values are **copied**, never referenced | Editing a preset must not rewrite the meetup on a trip that already sold seats. |
| 4 | Upload is a **custom Trail Earth dropzone**, not FilePond/Dropzone | No new dependency, and it matches the design system rather than fighting a library's chrome. |
| 5 | Difficulty is an **optional override** | Null inherits from the first destination; set overrides. Same mountain can host an easy day hike and a hard traverse. |
| 6 | Layout is **stepped with a live preview rail** | Keeps guidance for first-time hosts; the preview answers "will this look good to a stranger?" continuously. |
| 7 | `trips.place_id` is **retained and synced** to the first destination | ~20 read sites depend on it. Migrating them is a separate job from redesigning the wizard. |

## 3. Schema

### New: `trip_destinations`
| Column | Type | Notes |
|---|---|---|
| `trip_id` | FK cascade | |
| `place_id` | FK cascade | |
| `position` | unsigned small int | Stop order, 0-based |
| `day_label` | string, nullable | e.g. "Day 1" |

Index `(trip_id, position)`.

**Migration backfills** one row per existing trip from `trips.place_id`, so no trip loses its destination.

`trips.place_id` remains and is written on every save as `destinations[0].place_id`. New code reads `destinations`; existing reads keep working unchanged.

### New: `host_meetup_presets`
| Column | Type | Notes |
|---|---|---|
| `host_id` | FK cascade | Owner |
| `label` | string | "Cubao terminal" |
| `address` | string, nullable | |
| `lat`, `lng` | decimal(10,7), nullable | |
| `image` | string, nullable | Photo of the spot |
| `note` | string, nullable | "Look for the blue van" |

Values are **copied** into `trip_meetups` on use. There is no FK from `trip_meetups` back to this table.

### New: `trip_photos`
| Column | Type | Notes |
|---|---|---|
| `trip_id` | FK cascade | |
| `path` | string | Public disk |
| `position` | unsigned small int | Carousel order |

`trips.cover_photo` is unchanged and remains the single hero used by cards, the detail page and OG images.

### Altered: `trips`
- `difficulty` — new `string(20)` nullable. Null inherits from the first destination's place.

### Unchanged
`trip_meetups` and `trip_inclusions` already carry everything needed.

## 4. Wizard

### Step 1 · The trip
- **Destinations** — search-as-you-type combobox. Selected places become ordered, drag-reorderable chips, each with an optional day label. First chip is primary.
- Title, description.
- **Difficulty** — optional. Default chip reads "Inherit · {place difficulty}"; picking Easy/Challenging/Hard overrides.
- **Inclusions** — repeatable: label, note, optional photo.
- **Photos** — cover dropzone (single) and carousel dropzone (multiple, reorderable).

### Step 2 · Logistics
- **Meetup points** — "Pick from saved ▾" loads the host's presets and copies the chosen preset's values into an editable form row; "+ New spot" adds a blank row with a "save to my library" toggle. Rows exist only in component state until publish, when they are written to `trip_meetups`; a row with "save to my library" checked also creates a `host_meetup_presets` record at that point.
- **Departures** — date, optional end date, capacity. Unchanged behaviour.
- **Price** — `<x-money-input>`.

### Step 3 · Review & publish
Renders the **actual trip card and hero** as joiners will see them, plus a readiness checklist (set ✓ / missing ⚠).

**Hard requirements** (block publish): title, ≥1 destination, ≥1 departure, price.
Everything else is a soft warning.

### Layout
Left rail stepper with per-step completion state · centre form · right live-preview panel. Single column below `lg`.

## 5. Components

| Component | Responsibility | Used by |
|---|---|---|
| `<x-upload>` | Drag-drop zone, thumbnail grid, drag-to-reorder, per-file remove, progress via Livewire upload events. Props: `:multiple`, `:max`, `wire:model`. Native file input as no-JS fallback. | cover, carousel, meetup photo, inclusion photo |
| `<x-place-picker>` | Search combobox → ordered destination chips with day labels | Step 1 |
| `<x-money-input>` | Inline currency prefix, thousands separators while typing, `inputmode="decimal"`, "per person" suffix inside the control | Step 2 |

New Livewire component `App\Livewire\Host\MeetupPresets` at `/host/meetup-points` for library CRUD, linked from the host sidebar.

## 6. Validation

**`validationAttributes()`** maps array paths to human nouns — `meetups.*.lat` → "latitude", `meetups.*.label` → "meetup name", `destinations.*.place_id` → "destination", `departures.*.starts_at` → "start date". Every rule referencing the attribute inherits the friendly noun.

**`messages()`** overrides only where the generic phrasing still fails a human:
- `meetups.*.lat|lng` → "That doesn't look like a valid map pin. Copy the coordinates from Google Maps."
- `destinations` required → "Add at least one destination."
- `departures.*.starts_at` required → "Every departure needs a start date."

Errors render **inline beside the offending field** inside each repeater row, not pooled below the card.

## 7. Error handling

- Per-file upload validation on drop — an oversized photo fails immediately, named, rather than at publish.
- `publish()` wrapped in `DB::transaction` so a trip never persists with destinations but no departures.
- Uploads written before a failed transaction are deleted.

## 8. Testing

### Pest (rules)
- Multi-destination persists in order; `trips.place_id` equals the first destination.
- **Preset copy isolation:** using a preset then editing the preset leaves the trip's meetup unchanged.
- Difficulty null inherits from the first destination; set value overrides.
- Blank repeater rows (meetups, inclusions, destinations) are dropped on save.
- Title / destination / departure / price still block publish; description does not.
- Friendly validation text appears instead of raw attribute paths.
- Cover and carousel photos land on the public disk with correct `position`.
- Presets are scoped to their owner — another host cannot read or edit them.

### Dusk (flows)
Search a destination → add two → reorder → add an inclusion with a photo → pick a saved meetup → publish → land on the trip page with both destinations shown.

## 9. Out of scope (follow-ups)

- Migrating the ~20 existing read sites off `trips.place_id` onto `destinations`. The sync keeps them correct meanwhile.
- An **edit** flow for already-published trips (meetups/inclusions/destinations are currently create-time only).
- Reordering destinations after publish.

### Shipped 2026-07-28

All 12 requirements implemented and verified (116 Pest + 11 Dusk green).

Two deviations from the plan, both discovered during execution:

1. **Task 3 → Task 4 ordering.** The plan claimed the preset page's Pest tests would pass before
   `<x-upload>` existed. They do not — `assertSee` renders the Blade, so the missing component
   throws. Task 4 was pulled forward.
2. **Six pre-existing tests drove the old wizard API** (`set('place_id', …)`). Replacing a single FK
   with an ordered collection is a public-contract change, so those tests were migrated to
   `call('addDestination', …)` and the `place_id` error assertion became `destinations`.

Still deferred: migrating the ~20 read sites off `trips.place_id`, and an edit flow for published trips.
