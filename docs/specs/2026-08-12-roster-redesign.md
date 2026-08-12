# Roster redesign — departure roster (host)

Agreed 2026-08-12. Supersedes every previous pass at this page.

## Why the previous attempts failed

Each one restyled the row while keeping the model where **the row is both the record and
the workspace**. A booking can be in any of ~8 action states — `confirm`, `verifyPayment`,
`reject`, `markFullyPaid`, `unmarkFullyPaid`, `markRefunded`, `cancelBooking`, `showProofs` —
and the template rendered every applicable one inline. So one row carried six controls
(including a free-text reject-reason input wedged into a table cell) and its neighbour carried
one. Nothing could align, row heights varied ~3×, and 375px was hopeless.

The template had reached 644 lines at 125 spaces of indentation. The visual mess and the
structural mess were the same problem.

## The decisions

| Question | Answer |
|---|---|
| Where it's used | Desktop **and** phone, equally. Two real layouts. |
| Row ↔ actions | Row is a **record**. A **drawer** is the workspace. |
| Three jobs | **Three modes over one list**: Money · Manifest · Check-in |
| Row content | Name, status badge, balance, one affordance |
| Summary — desktop | Keep the four figures |
| Summary — mobile | Small stat strip, plain text, no cards |
| Filters | **Change per mode** |
| Header actions | One primary (`Add guest`) + `⋯` overflow |

## Structure

### Modes

A segmented switch above the list. Each mode changes the columns, the filters, and the one
affordance a row offers. The list underneath is always the same set of bookings.

| Mode | Columns | Filters | Row affordance |
|---|---|---|---|
| **Money** | Traveller · status · balance | All · New proof · Unpaid · Part paid · Settled · Refund owed | Open drawer |
| **Manifest** | Traveller · age/sex · contact | All · Details missing · No ID | Open drawer |
| **Check-in** | Traveller · arrived · time | All · Not arrived · Arrived | Toggle arrival inline |

Check-in is the exception that earns an inline control: it is the one action performed
repeatedly, fast, one-handed, with a person standing in front of you. Everything else opens
the drawer.

The `Check-in` header button disappears — it is a mode now, not a destination.

### The row

Four fixed cells, in every mode and every state. Nothing conditional in the row itself.
A row never grows, never shrinks, never gains a control its neighbour lacks.

### The drawer

Desktop: slides in from the right; the list stays visible so a run of proofs can be worked
without losing your place. Mobile: bottom sheet.

Holds everything the row shed — full traveller detail, the proof list, the reject-reason
field, and every action valid for that booking's state, stacked vertically with one primary.

### Summary

Desktop keeps four figures. Mobile collapses to one line of plain text
(`3/13 seats · 5,741 in · 11,482 out`) — no cards, no fills, roughly one line tall.

## Colour

Colour carries one meaning and is never the only signal:

- **amber** — money owed to the host
- **red** — money owed back to the joiner (refund)
- **green** — settled
- **neutral** — everything else

Every state also carries a word. No status is communicated by colour alone.

## Constraints carried forward

- 44px minimum on every control (there is already a Dusk test enforcing this on the joiner
  dashboard; the same guard should cover this page).
- Muted text at or above `ink/65`; below that fails AA over canvas.
- No native file inputs (existing `NoNativeFileInputTest` covers this).
- Destructive actions stay behind `<x-confirm-action>`.

## Explicitly out of scope

**Emergency contact and medical notes.** Requested for Manifest mode, but no columns exist
on `reservation_participants` and nothing in the joiner registration flow captures them.
That is a migration + capture-form + display change — a feature, not a redesign — and
bolting it onto this work would hide it. To be built separately, immediately after.

## Done means

- Every row identical height within a mode, verified by measurement in a browser.
- No horizontal scroll at 375px.
- Every control ≥44px.
- The existing roster behaviour tests still pass unchanged in meaning: traveller-not-booker,
  proof modal, filters, search, confirm-≠-paid.
