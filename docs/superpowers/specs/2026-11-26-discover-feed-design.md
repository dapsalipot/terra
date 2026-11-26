# Discover — Design

**Date:** 2026-11-26
**Status:** Awaiting approval
**Scope:** A TikTok-style vertical feed of photos from host organizers, each one tap from booking. Second of two projects; it ranks with the province data from "Your Philippines" when that exists.

## Intent

Travelers rarely go to the same place twice, so they need a steady stream of new places that make them want to go. A feed of host photos they can flick through, with a Book button on every card, turns idle scrolling into bookings.

## Decisions made with the user

| Question | Decision |
|---|---|
| Format | Infinite vertical scroll, one photo per screen, like TikTok. |
| Media | Pictures only, no video for now. |
| Source | Photos posted by host organizers. **Reuse host wall posts that are tagged with a trip**, with a "Show in Discover" switch on the composer. No separate upload flow. |
| From a photo | The traveler can jump straight to booking. |

## Goals

1. A page, Discover, that scrolls endlessly through host photos, one per screen on a phone.
2. Every card has a Book button that opens that trip's join page, and the trip line opens the trip page.
3. For a signed-in traveler, places they have not been to come first.
4. A host controls what appears: a post is in Discover only when they tag a trip and switch it on.

## Non-goals

- Video, likes, comments, saves, share counts, hashtags, search.
- A moderation queue (admin tools stay as they are) and analytics.
- Any personalisation beyond "new places first" and host variety.

## Anti-goals (a failure even if it technically works)

- A card whose Book button leads nowhere bookable.
- A link-only trip's photo shown to anyone, or a followers-first trip's photo shown to a non-follower before its window ends.
- The same host filling the screen several cards in a row when others are available.
- A feed that stops loading, repeats cards, or traps keyboard focus.

## Constraints

- Livewire 3.8 and Alpine only (the intersect plugin ships with Livewire). No new dependency.
- Reuse `posts`, `post_images`, `Trip::listedFor()` and the open-departure rule; a suspended host never appears (already inside `published()`).
- While the project is in dev mode, run only the tests for what changes.

## What qualifies

A post appears in Discover when it has at least one image, `in_discover` is true, its `trip_id` points at a trip that is listed to the viewer (`Trip::listedFor`) and has an open future departure. Auto-generated recap posts never qualify.

## Approaches considered

**Where the photos come from**

| Option | Trade-off |
|---|---|
| **Wall posts tagged with a trip, plus a "Show in Discover" switch** *(chosen)* | No new upload flow or table; hosts already post. Needs the composer to gain a trip tag. |
| An "Add to Discover" upload on each trip | Always bookable, but hosts upload twice. |
| A new `moments` table | The cleanest model, with the most to build; nothing asks for it yet. |

**How it scrolls**

| Option | Trade-off |
|---|---|
| **A snap-scrolling column of full-screen cards; a Livewire component loads the next batch when a late card scrolls into view** *(chosen)* | The native feel, with a small amount of code. |
| Numbered pages | Not infinite. |
| A masonry grid | Not TikTok. |

## Design

**Data.** `posts.in_discover` (boolean, default false), indexed with `created_at`. Existing posts stay out of Discover. A post's tag and switch are set when it is created (the wall has no post editing today), so hosts add new photo posts to appear.

**Composer (host page).** `Hosts\Show`'s post composer gains an optional "Tag a trip" select, offering only the host's published trips with an open future departure, and a "Show in Discover" switch that appears when a trip is tagged and a photo is attached, on by default. The saved post carries `trip_id` and `in_discover`. The trip must belong to the host. Without a photo the switch is hidden and the post is saved as before.

**Feed query.** `App\Support\DiscoverFeed::next(?User $viewer, array $seen, int $size = 8): Collection`. It selects qualifying posts newest first (capped candidate set, with `images`, `trip.place`, `trip.host.hostProfile` and the soonest open departure eager-loaded), drops ids in `$seen`, orders them, and returns the next `$size`.

**Ordering.** For a signed-in traveler, posts whose trip visits a province they have not been to come first (using `VisitedProvinces` when it exists, otherwise the places they have booked), then the rest, each group newest first. Within the result no host appears twice in a row while another host's post is available. Guests get newest first with the same host variety.

**Page.** `GET /discover`, route `discover`, Livewire component `Discover`, open to guests. A snap-scrolling column (`snap-y snap-mandatory`), each card the height of the visible screen above the tab bar. The first card's image loads eagerly, later ones lazily. A late card carries `x-intersect="$wire.loadMore()"`. On desktop the column is a centred phone-width strip on a dark ground, and arrow keys step between cards. An empty feed shows a friendly message and a link to Explore.

**Card.** Full-bleed photo, a gradient at the bottom, the host's avatar and name (linking to the host page), the first lines of the caption, and a trip line (title, place and province, next date, price). A primary **Book** button links to `trips.join`; the trip line links to `trips.show`. A post with several photos swipes sideways inside the card, with dots. A host viewing the feed sees "View trip" instead of Book, since hosts cannot join.

**Navigation.** "Discover" becomes a tab in the phone bar in place of "Hosts" (the bar stays at five; Hosts remains in the desktop nav, on Home and in Explore). *My call, flagged for you.*

**Accessibility.** Every image has alt text (the caption, else "{trip title} photo"); Tab moves through the Book buttons and links without being trapped by the snap container; reduced motion turns off smooth scrolling.

## Testing

- Eligibility matrix: no image, flag off, no trip, trip not listed to the viewer (link only, followers first before and after its window), no open departure, suspended host, recap posts.
- Ordering: unvisited places first, host variety, guests.
- Paging: batches of eight, no duplicates when `seen` is respected, an empty last page.
- The composer: saves `trip_id` and `in_discover`, rejects another host's trip, hides the switch without a photo.
- Cards: Book goes to `trips.join`, the trip line to `trips.show`, a host sees View trip.
- Dusk: scrolling loads more, Book lands on the join page, a 320px layout; the page joins the contrast audit.

## Left to the builder

Scroll-snap details on desktop, the tab's icon, caption truncation, dot styling, and whether the seen-ids list travels in the component or the URL.

## Honest limits

- Until hosts tag trips and flip the switch, the feed is thin.
- Photos are the host's own, so there is no moderation beyond what admin tools already do.
- Ordering is recomputed on every load, so a new post can shift what comes next.
- No likes or saves in this first version.
