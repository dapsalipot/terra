# Your Philippines — Design

**Date:** 2026-11-26
**Status:** Awaiting approval
**Scope:** A province-fill map of the Philippines that fills in as a traveler completes Tarana trips. First of two projects (the Discover feed follows and reuses its province data).

## Intent

People rarely travel to the same place twice, so a traveler's sense of progress comes from covering new ground. A map of the Philippines that fills in province by province, from trips they actually booked on Tarana, gives them that feeling and turns every empty province into an invitation to book.

## Decisions made with the user

| Question | Decision |
|---|---|
| Pins or filled areas? | **Province fill**, like a scratch map. |
| What counts as "been there"? | **Tarana bookings only.** No self-added places, so travelers book instead of typing their history in. |
| Which countries? | The Philippines only, for now. |

## Goals

1. A page, "Your Philippines", that draws every province and fills the ones where the traveler has a counted trip, with a header such as "7 of 82 provinces".
2. Tapping a filled province shows the trips; tapping an empty one offers "See trips in {province}", which opens Explore filtered to that province.
3. The map is usable by keyboard and screen reader, and a list of provinces mirrors it.

## Non-goals

- Pins, self-reported places, other countries, municipality detail, share images, badges or leaderboards, and anyone else's map.
- Any live map service: no tiles, no external requests, no new runtime dependency.

## Anti-goals (a failure even if it technically works)

- Counting a trip that has not started, was cancelled or was never confirmed, or counting someone else's booking.
- A map that cannot be read or tapped at 320px.
- A province that silently never counts because a place has no province.

## Constraints

- The boundary data must be public-domain or otherwise free to ship, and the SVG should stay small (target under 300 KB). Downloading it needs the user's OK (file, source, size) when the plan reaches that step.
- Province keys are stable identifiers shared by the places, the map and the Explore filter.
- While the project is in dev mode, run only the tests for what changes.

## "Been there"

A reservation counts when its `joiner_id` is the traveler, its status is `confirmed`, and its departure's `starts_at` is in the past (the same "past" as My trips). It credits every itinerary place (`trip_destinations`) that is in the Philippines and has a province, once per trip however many stops fall in one province. A province's visit count is the number of such trips. Guest bookings count once they are attached to the account. A confirmed booking whose traveler did not turn up still counts: check-in is optional for hosts, so it cannot be the rule.

## Approaches considered

**How the map is drawn**

| Option | Trade-off |
|---|---|
| **Inline SVG of province outlines we ship** *(chosen)* | Fast, brand-styled, accessible, works offline, no external requests. Needs a one-off conversion of boundary data. |
| A tile map (Leaflet or MapLibre) with GeoJSON | Familiar zoom and pan, but external tile requests, a library, and no scratch-map look. |
| A pre-rendered image per traveler | Not interactive, and has to be regenerated on every booking. |

**How a place knows its province**

| Option | Trade-off |
|---|---|
| **A `province` key on places, filled by the seeder and a one-off backfill** *(chosen)* | Explicit and fast. Places are only created by the seeder today, so there is one place to maintain. |
| Point-in-polygon from coordinates | Places have no coordinates today. |
| Keep matching the loose `region` text | Unreliable ("Surigao", "Palawan"): it already disagrees with real province names. |

## Design

**Data.**
- `places.province` (string, nullable, indexed), holding a province key.
- `App\Support\Provinces`: `CATALOG` of key => name for the provinces in the boundary data (about 82, Metro Manila included), plus `keys()` and `label()`, in the style of `PlaceCategories`.
- `PlaceSeeder` sets `province` for each Philippine place. A one-off data migration backfills existing rows from `region` when it matches a province name or a listed alias, logs what it could not match, and leaves places outside the Philippines null.

**Geometry.** `resources/data/ph-provinces.json`, a list of `{key, name, d}`, generated once by a committed script from the downloaded boundary data (projected, simplified, coordinates rounded). A Blade component `x-province-map` draws one `<path>` per province from it. There is no GIS code at runtime.

**Counting.** `App\Support\VisitedProvinces`: `for(User $user): array` (province key => trip count) and `trips(User $user, string $province)` for the panel. One joined query over reservations, departures, trips, destinations and places.

**Page.** `GET /my-map`, route `my.map`, signed-in joiners (a host is sent to their dashboard). Livewire component `Joiner\Map` in the site layout. Entry point: a "Your Philippines" button with the count in the My trips header. The phone tab bar keeps "Trips" active on this page.

**Look.** Empty provinces are sand with cream outlines; filled ones are terracotta in three steps (1 trip, 2 to 3, 4 or more). A short staggered reveal on load, switched off for reduced motion. A legend and the count sit above the map. Selecting a province opens a panel (below the map on a phone, beside it on desktop) with its name, its count, and either its trips (title, date, a link to the booking page) or "See trips in {province}".

**Accessibility.** Every province is a focusable control with an accessible name that includes its trip count (`Escape` clears the selection). A list of provinces, filled ones first, follows the map and carries the same information, so colour is never the only cue.

**Explore.** `Trips\Index` gains a `province` filter, kept in the URL, applied through the listed-trips scope. An unknown key is ignored.

**Empty state.** All sand, with "Book your first trip to start filling it in" and a button to Explore.

## Testing

- The counting rule as a matrix: confirmed or not, started or not, own or another's, one or several stops in a province, outside the Philippines, place without a province, two trips in one province.
- The page: path count, filled classes and the header count for a seeded traveler, the empty state, the host redirect, the guest redirect to login.
- The Explore province filter, including an unknown key.
- Dusk: tap a filled and an empty province, 320px layout; the page joins the contrast audit.

## Left to the builder

The dataset choice and its licence check, the simplification tolerance, the exact colour steps, the panel layout, demo data (extend the fake-data seeder with past bookings), and where the My trips button sits.

## Honest limits

- A brand-new traveler sees an empty map until a booked trip starts. That is the point of "bookings only".
- Small provinces and Metro Manila are tiny on a phone; the list and the panel are the fallback.
- Places the backfill cannot match stay uncounted until someone sets their province.
