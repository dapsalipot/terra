# Host Reach and Sharing — Design

**Date:** 2026-11-26
**Status:** Approved
**Scope:** Phase 2 of the four-track host plan (identity has shipped; proof and discovery follow):
per-trip listing control, share links with real previews, and "More from this host".

## Intent

Hosts who run the same hike worry that joiners will compare them with competitors. On Facebook the
host who is famous, first seen or available wins; on Tarana a joiner sees every host side by side.

Tarana is a marketplace, so exploring stays open to everyone, always. The answer is therefore not
walls but control and reach: a host decides how widely each trip is listed, brings their own audience
with a link that looks good wherever it is shared, and their own pages only ever promote them.

## Decisions made with the user

| Question | Decision |
|---|---|
| Does a host's own link change what a joiner sees? | **No.** Hosts share their Tarana link, and nobody's exploring is ever restricted. No own-link mode, no hidden nav or footer, no `?via` tag. |
| Booking and payment pages | Unchanged. They keep the normal nav. |
| What does "Link only" mean? | Unlisted everywhere, the host's own page included, so it also suits private group trips. *(Proposed in conversation and not objected to.)* |

## Goals

1. A host chooses, per trip, how widely it is listed: **Everyone**, **Followers first** (then everyone
   after a window the host picks), or **Link only**.
2. A host can share their page or any of their trips: copy link, the phone share sheet, Facebook, QR.
   A shared link shows a proper preview (title, photo, price) wherever it lands.
3. A trip page offers **More from this host**: only that host's own trips.
4. A host's own pages never recommend another host, and a test says so.

## Non-goals

- No own-link mode, no stripped nav or footer, no per-host switch for either.
- No generated share-card images. Previews use the trip's existing cover photo.
- No follower notifications and no "where bookings came from": both are Phase 4.
- Listing control does not change access. A published trip's page, join page, QR and booking page
  open by link whatever its visibility.

## Anti-goals (a failure even if it technically works)

- A link-only trip showing in any list, for anyone except its own host.
- A followers-first trip showing to a non-follower before its opening date.
- Anything that stops a joiner exploring.
- A visibility change that touches existing bookings or their pages.

## Constraints

- `Trip::published()` stays the lifecycle scope (status plus suspension). Visibility is orthogonal to
  it: a "link only" trip is still published.
- One trip row is one schedule. Schedules published together share a `trip_group_id` and are chosen
  together; a single schedule can still be edited alone.
- About 54 test files lean on the published listing. The default must be "everyone" so they stay green.
- No new dependencies. Livewire and Alpine only.
- Pest for logic, Dusk only where a browser is the point. While the project is in dev mode, run only the
  tests for what changes.

## Approaches considered

**How visibility is stored**

| Option | Trade-off |
|---|---|
| **A `visibility` column and an opening date on `trips`** *(chosen)* | One scope to apply everywhere; trivial to query; matches how the codebase already keeps `status`. |
| An audience-rules table (per trip, per audience) | Room for lists and groups later, but nothing asks for that and every listing query gets harder. |
| New `status` values (`unlisted`, `followers`) | Mixes lifecycle with reach: moderation, admin counts and "is it bookable" all read `status`. |

**How a shared link previews**

| Option | Trade-off |
|---|---|
| **Open Graph and Twitter tags from the cover photo, plus a default brand card** *(chosen)* | Small, works on Facebook and Messenger today. Trips already always have a cover (stock fallback). |
| Generated card images (photo, title, date, price, host) | Prettier, but fonts, layout and caching work. Revisit if plain previews disappoint. |
| A copy button only | Facebook would show a bare link, which is the current problem. |

**Where a host changes visibility after publishing**

| Option | Trade-off |
|---|---|
| **The wizard plus a control on each offering in My trips** *(chosen)* | "Open this up now" is a common action and should not mean re-running the wizard. |
| The wizard only | Less to build, but clumsy. |

## Design

### 1. Listing control

**Data.** `trips.visibility` (string, default `public`: one of `public`, `followers`, `link`) and
`trips.public_at` (nullable timestamp: when a followers-first trip opens to everyone). An index on
`(status, visibility, public_at)`. A small `App\Support\Visibility` class holds the options, their
labels and the window presets (1 day, 3 days, 1 week), in the same style as `Vibes` and `Accents`.

**Effective visibility.** `Trip::effectiveVisibility()` returns `public`, `followers` or `link`. A
followers-first trip whose `public_at` has passed is `public`; the stored value is not rewritten, so
no scheduled job is needed. A followers-first trip with no `public_at` stays followers-only.

**Who sees what, where.**

| Surface | Everyone | Followers first, window open | Followers first, window passed | Link only |
|---|---|---|---|---|
| Home, Explore, host cards, the directory (counts, category filter, category chips) | listed | followers of that host only | listed | never |
| The host's own page (trips and "coming up") | listed | followers only | listed | never |
| "More from this host" | listed | followers only | listed | never |
| The joiner dashboard's suggestions | listed | followers only | listed | never |
| Trip page, join page, QR, booking page, pass | by link | by link | by link | by link |

The host sees all of their own trips on their own page, and in "More from this host" on their own trip
pages, each with a chip saying who else can see it. Their unlisted trips do not appear in Explore or Home
just because the host is the one looking: that would suggest other people can see them. Admin counts and
moderation read `status` and are unaffected. Guests count as "everyone".

**Scopes.** `Trip::listed()` is what everyone sees right now; `Trip::listedFor(?User $viewer)` adds the
followers-first trips of hosts the viewer follows. Both include `published()`. The host's own page and
their own "More from this host" skip the filter when the viewer is that host. Every listing site switches from `published()` to one of these. Home's headline counts
use `listed()` so the number does not change with who is looking.

**Wizard.** Step 5 (Review and publish) gains "Who can see this trip": three choices, and for followers
first a select for when it opens to everyone. Schedules created together share the choice and one
opening date. Editing a schedule shows its current choice; leaving it unchanged keeps its opening date,
changing the choice or the window restarts the window from now. A duplicated schedule inherits the
source trip's choice, with the window restarting at publish. The draft saver includes the new fields.

**My trips.** Each schedule row gets a chip ("Followers first until 5 Dec", "Link only") and each
offering's header gets one control that sets the choice for all of its schedules at once.

**Search engines.** Any page that is not `public` right now is served `noindex`.

### 2. Sharing

**Share panel** (`x-share-panel`): a link field with Copy, a "Share…" button that uses the phone's
share sheet where the browser has one, a Facebook share link, and a QR download. Alpine only.

**Where it appears.** My trips (per schedule); the trip page for that trip's host, opened after
publishing; the host's own page, for the host; and "Your page" in the console. The trip QR route
stays; a host-page QR route is added, sharing one QR-building helper with it.

**Previews.** The site layout gains optional page metadata, rendered as Open Graph and Twitter tags, a
canonical URL and, where needed, `noindex`. Image URLs are absolute.

- Trip page: title "{trip} · {host}"; description with place, dates, price from and seats left; image is
  the cover.
- Host page: "{name} on Tarana"; description from their tagline; image is their cover, else their next
  trip's cover, else the default card.
- Everything else gets the default card: `public/images/og-card.png`, 1200×630 (pine, the signpost mark,
  the wordmark, "Group trips led by local hosts"), made once.

### 3. More from this host

A row on the trip page, after the reviews, headed "More from {host name}": up to six of that host's other
trips that are listed to this viewer, one tile per offering, excluding the offering being viewed and
trips with nothing left to join. It links on to the host's page and is absent when there is nothing to
show. It reuses `x-scroller` and `x-trip-tile`.

### 4. The guard

A feature test builds two hosts with trips and asserts that the host page, trip page, join page and
booking page of one never contain the other's name or trip titles. The nav may still link to Explore:
the test is about content a host controls.

## Testing

- Scopes: public, followers open and closed, link only, owner, suspended host, guest.
- Each listing surface honours visibility (Home, Explore, directory, host page, host cards, dashboard
  suggestions, More from this host).
- Direct access: a link-only trip's page, join page and QR still open.
- Wizard: choice persists for every schedule in a group; edit keeps or restarts the opening date;
  drafts restore the choice. My trips control changes a whole offering.
- Previews: Open Graph tags on trip and host pages with absolute image URLs; `noindex` exactly when the
  trip is not public.
- Share panel: shown to the host only.
- Dusk: pick a visibility in the wizard and publish; the share panel opens. No clipboard assertions.

## Left to the builder

Wording of chips and panel copy; the panel's visual design; the control used to edit an opening date;
tile ordering in More from this host; demo trips in the fake-data seeder (one link-only, one followers
first); the art for the default card.

## Honest limits

- Followers first is only as strong as followers' chances to notice it. Until Phase 4 announces to
  followers they see it by browsing, which is a weaker promise than it sounds.
- Link only is unlisted, not private: anyone with the link can open it, and can pass it on.
- Visibility is stored per schedule row. The My trips control changes a whole offering at once, but the
  wizard's edit mode changes one schedule.
