# Joiner Dashboard Refinement — Design

**Date:** 2026-08-02
**Status:** Approved for planning
**Scope:** Repairs and simplification of `/my-trips`, plus moving Philippine holidays to an owned table.

## Goal

Fix eight measured defects in the joiner dashboard, cut the feature surface back to what a joiner
with a handful of bookings actually needs, and remove the runtime dependency on a third-party
holiday API.

## Audit findings (measured, not estimated)

Query counts came from a `DB::listen` probe against a joiner with 6 bookings of 3 travellers each.

| # | Area | Finding |
|---|---|---|
| 1 | Backend | **18 queries per render.** 6× `trip_price_tiers`, 4× `places`. The eager load is `with(['departure.trip.host', 'participants'])`, but `owesBalance() → money() → current_price → currentTier()` reads `$trip->priceTiers` lazily, once per booking. Grows linearly with bookings. |
| 2 | Backend | **A heavy query runs and is discarded.** `$recommended` is a four-join correlated subquery, rendered only inside the empty state. Every joiner who *has* bookings pays for it and never sees it. |
| 3 | UX | **Dismiss does not dismiss.** `$dismissedAttention` is a plain public array — no `#[Url]`, no session, no storage. Dismiss an item, reload, it returns. |
| 4 | UX | **A filter can silently hide every upcoming trip.** `$filter` is `#[Url]` so it survives a reload, but the funnel button only highlights while the *panel is open*, never to say a filter is *applied*. |
| 5 | UX | **The heading contradicts the calendar.** The section heading reads from `$filter` ("Past", "Needs action"), while the calendar deliberately ignores `$filter` and shows every booking. |
| 6 | Code | 407-line component, 571-line view. |
| 7 | Code | Booking-card markup duplicated six ways; two separate copies of the "No trips booked yet" empty state (view lines 372 and 500). |
| 8 | Docs | A comment claims the grid "renders whole weeks from Monday". It has started on Sunday since the last change. |

## Decisions

| Question | Decision |
|---|---|
| Scope | Fix the defects, then simplify hard |
| Holidays | Own the data in our own table |
| Sorting | Removed entirely |
| Dismissing an urgent item | Not offered |

---

## 1. Backend

**Eager-load the price tiers.** Add `departure.trip.priceTiers` to the existing `with()`. This is
the whole of finding 1 — `currentTier()` stops issuing a query per booking.

**Compute `$recommended` only when it will be shown.** It is referenced solely inside the empty
state, so guard it behind `$reservations->isEmpty()`.

**Target: 18 → roughly 8, and flat as bookings grow.** The `DB::listen` probe becomes a permanent
test so a future eager-load regression fails the suite rather than the page. The test asserts a
ceiling of 10, not 8 — the expectation is 8, the ceiling carries headroom for session and framework
queries so the test does not break on an unrelated change.

## 2. Simplification

| Now | After | Why |
|---|---|---|
| 4 filters behind a funnel icon | **Upcoming / Past**, always visible | A hidden filter that persists in the URL is how upcoming trips silently vanish |
| 4 sort orders | none — always by date | Trips are events; date is the only ordering that means anything, and a joiner has a handful of rows |
| 6 booking-card templates | one `<x-booking-card>` | Six copies of one card is six places for a fix to be forgotten |
| 2 empty states | one | Same reason |

Removing the hidden filter panel **deletes finding 4 outright** rather than patching an
"active" badge onto it. `$showFilters`, `toggleFilters()`, `$sort` and `setSort()` all go.

Together these address finding 6: the extraction and the deletions are what bring the 407-line
component and 571-line view back down, rather than a reorganisation for its own sake.

A bookmarked `?sort=host` from before this change simply stops being read. No redirect is needed —
Livewire ignores a URL parameter with no matching property.

**Finding 5 resolves structurally:** the Upcoming/Past tabs render only in Cards and List. In
Calendar the month *is* the scope, so there is nothing to filter and nothing to contradict.

## 3. Attention items

**Urgent items lose their dismiss button.** An unpaid hold expires and releases the seat; offering
to hide it is an affordance that costs someone their place.

**Soft nudges (the review prompt) stay dismissible, persisted in the session** so a reload does not
resurrect them. Session rather than a database column: a dismissal is a per-visit preference, not
a fact about the booking, and the item disappears permanently once the review is written anyway.

## 4. Holidays — own table, API as importer

```php
Schema::create('holidays', function (Blueprint $t) {
    $t->id();
    $t->date('date')->unique();
    $t->string('name');
    $t->string('source', 12)->default('import');   // import | manual
    $t->timestamps();
});
```

- **`php artisan holidays:import {year?}`** pulls from Nager.Date and upserts, **never overwriting
  a row whose `source` is `manual`**. Defaults to the current and next year.
- **Scheduled monthly**, so next year's holidays appear on their own once published.
- **`PhilippineHolidays` reads only the table.** No HTTP during a page render, which keeps the
  `Http::preventStrayRequests()` guard in the test suite honest.
- A Presidential Proclamation moves a date → one `manual` row wins over the import, permanently.

This is the mitigation for the risk in the chosen option: nobody hand-types 18 dates a year, and a
forgotten year self-corrects at the next scheduled run.

**Deliberately not built:** an admin UI for holidays. One row of SQL or a tinker call covers the
rare proclamation case; a CRUD screen for something touched once or twice a year is not earned.

## 5. Documentation

Correct the comment that still claims a Monday week start.

---

## Testing

**Kept as-is:** all 36 existing `JoinerDashboardTest` cases and the 7 `PhilippineHolidaysTest`
cases, adjusted where the removed filters and sorts are referenced.

**New:**

1. **Query budget.** Render with 6 bookings of 3 travellers and assert **10 queries or fewer**
   (measured baseline before this work: 18). This is the regression guard for finding 1 and must
   fail if someone drops the eager load. Ten rather than eight leaves headroom for session and
   framework queries without being so loose it stops meaning anything.
2. **Query count does not grow with bookings.** Render with 3 bookings and with 12; assert the
   counts match. A ceiling alone can be met by a fixed-size N+1.
3. `$recommended` is not queried when the joiner has bookings.
4. The review nudge stays dismissed across a re-render.
5. No dismiss control is rendered for an urgent item.
6. Upcoming/Past tabs are absent in Calendar view and present in Cards.
7. `holidays:import` inserts rows, is idempotent on a second run, and leaves a `manual` row
   untouched while updating an `import` row beside it.
8. `PhilippineHolidays` issues no HTTP at all — asserted with `Http::preventStrayRequests()`.

**Browser:** the existing `SystemAuditTest` must still report zero findings, and the contrast
figures established for the calendar cells (title 12.22:1 worst case, weekend numeral 10.3:1,
holiday numeral 13.96:1) must not regress.

## Non-goals

- No visual redesign. Spacing, hierarchy and colour stay as they are; this is repair and
  subtraction, not a new look.
- No change to the host calendar, which still starts on Monday. Worth aligning, but it is a
  separate screen and a separate decision.
- No admin UI for holidays.
