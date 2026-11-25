# Terra — MVP Design Spec

**Date:** 2026-07-18
**Status:** Approved (brainstorm phase complete)

> **Rebrand (2026-07-27): Terra → Terra.** "Terra" is slang for people, and the brand line is
> **"Better trips with better terra."** The emphasis moves from *place* to *the people you go with* —
> the trip is the occasion, the crew is the product. The outdoor identity is unchanged: Mount Pulag
> photography, the Trail Earth palette, the mountain divider, and the dashed trail-line all stay.
> References to "Terra" below predate the rename and mean Terra.
>
> Naming caution for implementers: the Tailwind token `terra` (`#BC4B26`) is **terracotta the colour**,
> not the old brand — `text-terra` / `bg-terra` must never be renamed. Likewise "terrain" in copy.
>
> Deliberately left on the old name (local infrastructure, not user-facing): the repo directory
> `~/Herd/terra`, the Herd domain `terra.test`, and the `terra` / `terra_dusk` databases.
>
> **Rebrand (2026-11-25): Terra → Tarana.** The name comes from "Tara na", Filipino for "let's go".
> Every "Terra" in this spec, and the garbled rebrand note above, means Tarana. Left alone on purpose
> because changing them would break something: the `terra` colour token, the repo directory and the
> `terra.test` dev domain, the database names, and the `@terra` suffix on calendar event UIDs (invites
> already sitting in people's calendars match on it).

## 1. Product Summary

Terra digitizes the Philippine "joiner" trip culture: hosts organize group hikes/trips, strangers join and pay the host, the host handles logistics. Today this runs on Facebook posts, comment threads, GCash screenshots, and spreadsheets.

**Strategic wedge:** Terra is a **companion to Facebook, not a replacement**. Hosts keep posting on FB/IG for reach; the post carries a Terra QR/link. Terra owns the transaction — joining, payment tracking, roster management, chat, and ratings — not discovery. Search/catalogue is deliberately deferred.

> **Positioning (2026-07-27):** Terra is **group trips of every kind — mainly hiking, but not only.** The outdoor/mountain aesthetic (Trail Earth, ridge art, the trail-line) is the brand and stays; copy is trip-inclusive (destinations, not just mountains). Places carry a `category` (hike · island · beach · surf · city …) so the catalogue and filters span trip types while the feel stays outdoors.

**Money model (MVP):** Terra never touches money. It displays the host's GCash/Maya/bank details; joiners pay outside the app and upload proof; hosts confirm manually. No license, no float, no refund liability. The reservation state machine is designed so a real gateway (PayMongo/Xendit) can slot in later without remodeling.

## 2. Scope

### In v1
- Host posts a trip with **multiple departure dates** (no reposting); gets one QR + deep link for their FB post
- Host can **add offline participants** (walk-ins with no account) to any departure's roster
- Joiner opens trip via QR/link (viewable without account), reserves a slot
- Manual payment: show host details → joiner uploads proof + reference → host confirms
- Host roster management (confirm/reject/extend/cancel reservations)
- Per-trip chat room (confirmed joiners + host)
- Post-trip ratings: host, trip, and mountain/place (all gated on completed attendance)
- Curated places catalogue (PH mountains) that trips tag and reviews collate against
- Instagram overlay: downloadable transparent-PNG trip card for stories
- Basic host profile: bio, payment details, history of completed trips

### Deferred (each gets its own spec later)
- Drivers (accounts, matching, ratings) — third marketplace side, wait for host liquidity
- Discovery/search/browse catalogue — companion wedge makes this non-essential
- Host wall / social feed
- In-app payments / escrow
- Waitlists

## 3. Architecture

> **Pivot (2026-07-25):** Terra is now a **Laravel + Livewire server-rendered web application**, not a React Native app on a JSON API. Discovery/search, the community homepage, and the host wall — deferred under the old Facebook-companion mobile model — are now **in scope** for the web product. Audience is **international** (English), so the places catalogue is global, payment methods are host-configured per country, and prices render in the host's currency. The manual-payment model and reservation state machine are unchanged.

```
Browser ──HTTP──►  Laravel 11 + Livewire 3 (server-rendered)  ──►  MySQL
```

- **Backend/Frontend:** Laravel 11, Livewire 3 (class-based components + Blade), MySQL. Server-rendered; no separate API for v1.
- **Auth:** Laravel Breeze (Livewire stack), session-based. Role-locked signup (`users.role` = `joiner|host`).
- **CSS:** Tailwind CSS v3 with Trail Earth design tokens (see §8b) in `tailwind.config.js`; Fraunces + system sans; drawn SVG icon set as Blade components. No Bootstrap.
- **Real-time:** Laravel Reverb + Laravel Echo for per-departure chat (later phase; polling acceptable as a fallback).
- **Storage:** local/`public` disk in dev, S3-compatible in prod (payment proofs private; trip/cover photos public) via `spatie/laravel-medialibrary`.
- **Queue:** database queue in dev, Redis in prod (notifications, overlay/QR rendering, reservation-expiry job).
- **QR & links:** `terra.app/t/{slug}` is a public server-rendered trip page (rich OG unfurl on FB). Host downloads a styled QR poster (Browsershot or `endroid/qr-code`) to post on social. No native deep links needed — the link opens the web app directly.

### Account model: role-locked accounts
An account is **either a joiner or a host**, chosen at signup (decided 2026-07-20, supersedes the earlier one-account/two-modes design). `users.role` (`joiner` | `host`); hosts additionally get a `host_profiles` row (payment details, bio, wall). The app renders a role-specific tab bar: joiners get Home · My Trips · Chat · Profile; hosts get Dashboard · Calendar · Post · Wall · Profile. A joiner who wants to host registers a host account separately.

## 4. Data Model

| Table | Key fields | Notes |
|---|---|---|
| `users` | name, phone, email, password, avatar | Everyone |
| `host_profiles` | user_id, bio, cover_photo, gcash_number, maya_number, bank_details | Existence = is a host |
| `places` | name, region, elevation, difficulty, tags, aggregate_rating | **Curated seed list** of PH mountains; hosts tag trips from it ("request to add" flow for gaps). Prevents name fragmentation so ratings collate |
| `trips` | host_id, place_id, title, description, meetup_point, price, itinerary, cover_photo, status, slug | The **offering** (added 2026-07-20): a trip is reusable across dates; slug/QR is per-trip; status: `draft → published → archived` |
| `trip_departures` | trip_id, starts_at, ends_at, capacity, status | 1..n departures per trip so hosts never repost; status: `open → full → completed → cancelled`. Roster, chat room, and capacity are **per-departure**; the joiner picks a departure on the trip page |
| `reservations` | trip_departure_id, joiner_id (nullable), guest_name, guest_phone, source (`app`\|`manual`), party_size, status, expires_at | status: `pending → awaiting_payment → payment_submitted → confirmed → cancelled`. **Manual/offline participants** (added 2026-07-20): host-added rows with `joiner_id = null`, `source = manual` — they consume capacity and show in the roster with an "Offline" badge, but have no account, chat membership, or QR check-in; host marks them paid directly |
| `payment_submissions` | reservation_id, method, reference_no, proof_image, submitted_at, confirmed_at, confirmed_by, rejected_reason | Separate table → supports resubmission and future gateway rows |
| `reviews` | reviewable_type/id (host \| trip \| place), joiner_id, rating, body | Polymorphic; one gating rule for all three |
| `chat_rooms` / `messages` | one room per trip; message body, sender, pinned flag | Membership = confirmed reservations + host |
| `trip_photos` | trip_id, uploader_id, image | Post-trip gallery; overlay source |

## 5. Core Flow: Join → Pay → Confirm

1. **Entry** — QR/FB link → deep link → trip page (no account needed to view).
2. **Reserve** — "Join" → auth if needed → party size → reservation `pending`. Capacity checked atomically (DB transaction + row lock on trip) — overselling seats is the cardinal failure.
3. **Pay** — App shows host's payment details/QR. Joiner pays externally, uploads screenshot + reference → `payment_submitted`.
4. **Confirm** — Host's "to confirm" queue → view proof → confirm (`confirmed`, push sent, joiner added to chat) or reject with reason (→ back to `awaiting_payment`).
5. **Complete** — Host marks trip `completed` after the date → review prompts unlock for confirmed joiners.

**Expiry:** reservations in `pending`/`awaiting_payment` auto-expire (default 24h, host-extendable per joiner) via scheduled job, releasing the seat. Prevents seat-squatting.

**Cancellation:** joiner may cancel any pre-confirmed state; host may cancel any reservation or the whole trip, but cancelling a *confirmed* reservation or a published trip requires a logged, joiner-visible reason (money already moved off-platform).

## 6. Subsystems

### QR & links
`terra.app/t/{slug}` is simultaneously: web preview (rich FB unfurl), deferred deep link, and QR payload. Host downloads the QR as a styled brand image sized for FB post graphics.

### Chat
One room per trip, auto-join on confirmation, host is admin (can pin messages). Reverb realtime + push when backgrounded. Rooms go read-only 7 days after completion — logistics rooms, not a social network.

### Reviews & gating
Single server-side rule: a joiner may review target X only if they hold a `confirmed` reservation on a `completed` trip that matches X (the trip itself, its host, or its place). Enforced by policy; UI merely reflects eligibility. Place aggregate rating recomputed on review write.

### Instagram overlays
Queued job renders transparent-PNG trip cards (few templates: mountain name, date, elevation, host, Terra brand, optional trip QR) via Browsershot/spatie-image. Downloadable by host and confirmed joiners of completed trips.

## 7. Error Handling Principles

- All state transitions validated server-side (policies + state-machine guards); the app requests, never decides.
- Trip-full returns a clean typed error; app shows sold-out state (no waitlist in v1).
- Payment proof uploads validated (image type/size), stored privately, visible only to host + submitter.
- Deep link fallback: if Branch fails, the web preview page always works and shows store badges.

## 8. Testing Strategy

- **Pest feature tests are the priority on two rule sets:** the reservation state machine (every legal/illegal transition, capacity race via parallel requests, expiry job) and review gating. These rules *are* the product.
- API contract tests for the mobile endpoints; the rest is conventional CRUD coverage.
- RN side: component tests for the join flow; Detox/e2e deferred.

## 8b. Design System Decisions (2026-07-20)

Mockups live in `design/`: `terra-ui-mockups.html` (joiner core), `terra-discover-mockups.html` (search & join), `terra-host-mockups.html` (host), `terra-core-mockups.html` (auth, pending, declined, check-in, reviews, cancel).

- **Palette** — Sand `#F1EBDD`/off-white `#FBF9F4` grounds, white surfaces, Pine `#2C3826` and Moss `#5E6B44` as the only button fills. Terracotta `#BC4B26` = saved items, summits, destructive text only. Amber `#A8761F` = waiting states only. Never use accents as button fills.
- **Type** — Fraunces (variable, embedded via `expo-font`) is restricted to trip names and prices; all UI text is the system sans at 700 for headings. Monospace for references and permit numbers.
- **Icons** — one drawn set, 24px viewBox, 1.7px stroke, `currentColor`. **No emoji in UI chrome** (they clash with the palette and render inconsistently across platforms). Emoji are allowed only inside user-generated content such as chat messages.
- **Navigation** — light labeled tab bar (icon + label, translucent white, hairline top border). Rejected the dark floating pill: unlabeled icons cost discoverability and it fought the airy surfaces.
- **Brand signatures** — exactly three, applied consistently and not expanded: (1) the dashed **trail line** wherever progress or sequence exists, (2) the **elevation/mountain graphic language** (trip charts, capacity gauge, place art), (3) **Fraunces on names and prices**. Everything else stays conventional.
- **Wordmark** — pine circle with a cream peak glyph + "Terra" in Fraunces. Appears in onboarding, the QR poster, and marketing surfaces.
- **Photography rules (for build)** — mockups use flat SVG placeholders; production must use real photography: 3:2 hero and 1:1 thumbnails, consistent warm grade, bottom scrim behind any overlaid text, `cover` fit, and a sand-toned skeleton while loading. Photos carry each screen — this is the single largest visual upgrade at build time.
- **Still to design** — trip photo gallery, host review list, place/mountain page, chat inbox, saved list, notification centre, IG overlay picker, departures overview, plus a systematic pass on empty / loading / error states.

## 9. Open Items for the Implementation Plan

- Repo layout: single monorepo (`api/` + `app/`) vs two repos
- Branch vs AppsFlyer vs roll-your-own deferred deep linking
- Reverb hosting specifics; push credential setup (APNs/FCM via Expo)
- Seed data source for the PH mountains places list
