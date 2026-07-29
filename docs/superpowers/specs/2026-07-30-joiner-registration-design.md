# Joiner Registration & Identity — Design

**Date:** 2026-07-30
**Status:** Approved for planning
**Scope:** Spec 1 of 2. Sharing (host share panel, invite links, QR re-pointing) is Spec 2.

## Goal

Let a stranger register for a trip in one painless pass — capturing the identity details a
Philippine trip host actually needs for permits and manifests — with or without creating an
account, while making the account the obvious default.

## Non-goals (deferred to Spec 2)

- Host-facing share panel (copy link / generate invite / download QR)
- `invite`-purpose magic links and their attribution
- Re-pointing the existing trip QR at the registration URL
- Per-participant editing by the host after booking

## Decisions

| Question | Decision |
|---|---|
| Whose details | Booker gives full contact details; each traveler gives name + birthday + gender, with optional per-person contact |
| Booker vs traveler | Separate concerns. The booker may or may not occupy a seat — an "I'm joining this trip too" toggle, on by default |
| Participant storage | Dedicated `reservation_participants` table |
| Magic link meaning | Both share and passwordless sign-in — Spec 1 builds the token mechanism plus the `login` and `attach` purposes; Spec 2 adds `invite` |
| Account UX | One pre-checked toggle at the end, zero extra typing; unchecking books as guest |
| Mail dependency | Ship an on-screen "copy your link" fallback so nothing depends on a mail provider; send email additionally when one is configured |

## Current state this replaces

- `Trips\Show::reserve()` redirects unauthenticated visitors to `login`. There is no guest path.
- `reservations.guest_name` / `guest_phone` exist only for host-added walk-ins (`source = 'manual'`).
- `Reservations\Pay::mount()` does `abort_unless($reservation->joiner_id === Auth::id(), 403)`,
  which 403s for every guest booking since `joiner_id` is null.
- `users.password` is `NOT NULL`.
- `endroid/qr-code` v6 is installed and used by `TripQrController`.
- `MAIL_MAILER=log`, `QUEUE_CONNECTION=database`.

---

## Schema

### New: `reservation_participants`

```php
Schema::create('reservation_participants', function (Blueprint $t) {
    $t->id();
    $t->foreignId('reservation_id')->constrained()->cascadeOnDelete();
    $t->unsignedTinyInteger('position')->default(0);
    $t->boolean('is_booker')->default(false);   // this traveler is also the booker
    $t->string('first_name')->nullable();
    $t->string('middle_name')->nullable();
    $t->string('last_name')->nullable();
    $t->date('birthday')->nullable();
    $t->string('gender', 24)->nullable();
    $t->string('email')->nullable();            // optional, any participant
    $t->string('phone', 32)->nullable();        // optional, any participant
    $t->timestamps();
    $t->index(['reservation_id', 'position']);
});
```

This table holds **travelers only** — exactly `party_size` rows, one per seat. The person who
books and pays is a property of the *booking*, not an entry in the traveler list, and lives on
`reservations` (below). `is_booker` marks the at-most-one traveler who is also the booker, so
the host roster can render "Ada Reyes — booker & traveler". When the booker is not travelling,
no row carries the flag.

Name columns are nullable **by design**: a companion the booker defers ("I'll add this later")
is a sparse row. `ReservationParticipant::isComplete()` is what the host UI reads.

Per-person `email` and `phone` are optional for every participant, revealed behind a small
"add contact" control. They serve emergency contact and let the host reach a specific traveler
directly; they are never required, because requiring them would undo the painless case.

**`reservations.party_size` stays and remains authoritative for seats.** Participants are
identity records, not the seat count. Do not derive `party_size` from
`participants()->count()` — `ReservationService::reserve()` sums `party_size` under a row lock
to enforce capacity, and `Reservation::money()` multiplies by it to price a booking. Deriving it
would couple oversell protection and pricing to whether the booker had finished typing their
companions' names. The registration flow writes both, and creates exactly `party_size` participant
rows (blank ones included) so the two never disagree.

### New: `magic_links`

```php
Schema::create('magic_links', function (Blueprint $t) {
    $t->id();
    $t->string('token', 64)->unique();     // sha256 of the raw token, never the raw value
    $t->string('purpose', 24);             // 'login' | 'attach'  ('invite' arrives in Spec 2)
    $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
    $t->string('email')->nullable();
    $t->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
    $t->dateTime('expires_at');
    $t->dateTime('used_at')->nullable();
    $t->timestamps();
});
```

The raw token exists only in the URL and the email body. The column stores
`hash('sha256', $raw)`, matching how Laravel treats password-reset tokens: a leaked database
yields no usable links.

### Alter: `users`

```php
$t->string('first_name')->nullable()->after('name');
$t->string('middle_name')->nullable()->after('first_name');
$t->string('last_name')->nullable()->after('middle_name');
$t->date('birthday')->nullable()->after('phone');
$t->string('gender', 24)->nullable()->after('birthday');
$t->string('password')->nullable()->change();
```

`name` remains the display string. The parts are stored so a returning joiner's next booking
pre-fills — this is the concrete payoff that makes an account worth creating.

`password` becomes nullable so a passwordless account is encoded honestly rather than as a
random-hash decoy. This is safe: `AbstractHasher::check()` returns `false` when the stored hash
is null, so no submitted password can authenticate a passwordless account. Storing the truth
also lets the login page detect the case and offer a sign-in link instead.

Laravel 12 changes columns natively; no `doctrine/dbal` dependency is needed.

### Alter: `reservations`

```php
$t->uuid('public_token')->nullable()->unique()->after('id');
$t->string('contact_first_name')->nullable()->after('guest_phone');
$t->string('contact_middle_name')->nullable()->after('contact_first_name');
$t->string('contact_last_name')->nullable()->after('contact_middle_name');
$t->string('contact_email')->nullable()->after('contact_last_name');
$t->string('contact_phone', 32)->nullable()->after('contact_email');
```

The `contact_*` block is **who to talk to about this booking** and is always populated —
from the signed-in user when there is one, otherwise from the form. It is deliberately a
snapshot rather than a pointer: a guest has no user row to point at, and even for a member the
booking should record what was true when it was made.

The legacy `guest_name` / `guest_phone` columns stay untouched and continue to serve host-added
walk-ins (`source = 'manual'`), which do not go through this flow.

Backfill existing rows with generated UUIDs in the same migration. The column stays nullable at
the DB level (SQLite cannot cheaply add a non-null unique column) but is always populated by a
`creating` model event, so application code may treat it as present.

---

## Field requirements

**Booker (the `contact_*` block) — always shown, always required.**

| Field | Required | Why |
|---|---|---|
| First / Last name | **Yes** | The host needs a name to hold the booking against |
| Middle name | No | Plenty of people don't have one; foreigners usually don't |
| Email | **Yes** | Booking identity, confirmation target, and the magic-link address |
| Phone | **Yes** | The host must be able to reach *someone* on the day |

**Each traveler (`reservation_participants`) — one block per seat.**

| Field | Required | Why |
|---|---|---|
| First / Last name | **Yes** to complete | Permits and manifests are per-head |
| Middle name | No | Same reason as above |
| Birthday | **Yes** to complete | Permits, and it surfaces minors |
| Gender | Nudged, not forced | Segmented Male/Female, with a reveal for Other / Prefer not to say |
| Email / Phone | No | Optional per-person contact, behind an "add contact" reveal |

"Required **to complete**" means the row fails `isComplete()` without it — the booking still
saves, and the host roster shows what is outstanding. This is what makes "I'll add this later"
possible without weakening the manifest: the data is still demanded, just not all at once.

---

## Models

**`ReservationParticipant`**
- `belongsTo(Reservation::class)`
- `fullName(): string` — collapses first/middle/last, skipping blanks
- `isComplete(): bool` — first name, last name and birthday all present
- `isMinorAt(CarbonInterface $date): bool` — age < 18 **on the departure date**, not today.
  A 17-year-old who turns 18 before the trip is not a minor for permit purposes.

**`Reservation`** gains
- `participants(): HasMany` ordered by `position` — travelers only, always `party_size` rows
- `bookerParticipant(): HasOne` where `is_booker = true` — **null when the booker isn't going**
- `contactName(): string` — from the `contact_*` snapshot
- `bookerIsTraveling(): bool` — `participants()->where('is_booker', true)->exists()`
- `minors()` — participants failing the age check against `departure->starts_at`
- `incompleteParticipants()` — drives the roster's "3 of 5 details pending" line
- `boot()`: generate `public_token` on `creating`
- `displayName()` updated to prefer `contactName()`, falling back to `joiner?->name` then
  `guest_name` (the last for host-added walk-ins)

Note there is deliberately no `lead()` relation. "Lead" fused two ideas — who pays and who
travels — and separating them is the whole point of this revision.

**`MagicLink`**
- `isValid(): bool` — `used_at === null && expires_at->isFuture()`
- `consume(): void` — sets `used_at`

**`User`** gains the five new fillables and `getFullNameAttribute()`.

---

## Services

### `RegistrationService`

The orchestrator. One public method, run in a transaction:

```php
public function register(TripDeparture $departure, array $data): RegistrationResult
```

```php
$data = [
    'party_size'          => int,      // seats, and therefore participant rows
    'contact'             => array,    // first/middle/last/email/phone — the booker
    'booker_is_traveling' => bool,     // the "I'm joining this trip too" toggle
    'participants'        => array,    // list of arrays, one per seat; may contain blanks
    'create_account'      => bool,
    'actor'               => ?User,
];
```

Steps:

1. **Take the seats via `ReservationService::reserve()`.** Never reimplement this — its
   `lockForUpdate()` on the departure row is the only thing preventing oversell. Nesting
   transactions is safe; Laravel uses savepoints.
2. Write the `contact_*` snapshot onto the reservation.
3. Create exactly `party_size` participant rows from `participants`, including blank ones so
   the host can see who is still missing. If `booker_is_traveling`, mark position 0
   `is_booker = true`; otherwise no row carries the flag.
4. Resolve identity (below) and set `joiner_id` accordingly.
5. Return a `RegistrationResult` so the UI can render the right confirmation without re-querying:

```php
final class RegistrationResult
{
    public function __construct(
        public Reservation $reservation,
        public AccountOutcome $outcome,   // enum, below
        public ?string $magicUrl = null,  // raw URL, for the on-screen fallback
    ) {}
}

enum AccountOutcome: string
{
    case Guest = 'guest';                  // booked without an account, by choice
    case AccountCreated = 'created';       // passwordless account made and attached
    case AlreadySignedIn = 'signed_in';    // existing joiner, attached directly
    case EmailTaken = 'email_taken';       // guest booking + attach link sent
}
```

`magicUrl` is the raw, un-hashed URL and is returned **only** to the request that created it —
it is never re-derivable from the database, which is the point of storing the hash.

**Identity resolution:**

| Situation | Behaviour |
|---|---|
| Actor is signed in (joiner) | `joiner_id = actor->id`. Fill **only blank** profile fields on the user (birthday, name parts) so the next booking is faster — never overwrite a value the user already set. No account toggle shown. |
| Actor is signed in (host) | Rejected — hosts cannot join trips. Preserves the existing rule. |
| Guest, `create_account = true`, email unused | Create a passwordless `User` with `role = 'joiner'` from the **booker's** `contact_*` details. Attach as `joiner_id`. Issue a `login` magic link. |
| Guest, `create_account = true`, email already registered | **Do not attach and do not sign in.** Create the reservation as a guest booking, issue an `attach`-purpose magic link to that address, and tell the user on-screen — offering an inline "Sign in to attach" as the fast path. |
| Guest, `create_account = false` | Pure guest booking, `joiner_id = null`. Identity lives in the `contact_*` snapshot and the participant rows. |

The account is always created from the **booker**, never from a traveler — the booker is the
one who supplied an email, agreed to the terms, and will pay. A coordinator who books for five
colleagues gets one account; the five travelers get none, which is correct.

The email-collision rule is the security-critical one: silently attaching would let anyone
create a booking that appears inside a stranger's account.

### `MagicLinkService`

```php
public function issue(string $purpose, array $attrs, int $ttlMinutes = 30): array  // {link, url}
public function consume(string $rawToken): ?MagicLink
```

`issue()` generates 48 random bytes, stores the sha256, returns the raw URL.
`consume()` hashes the input, finds the row, verifies validity, marks it used, returns it.
Expired and already-used tokens both return null — the caller cannot distinguish them, which
avoids leaking whether a token ever existed.

---

## Routes

```php
Route::get('/t/{trip:slug}/join', Reservations\Register::class)->name('trips.join');
Route::get('/r/{token}', Reservations\Show::class)->name('reservations.show');
Route::get('/magic/{token}', MagicLinkController::class)->name('magic.consume');
Route::post('/magic', SendMagicLinkController::class)
    ->middleware('throttle:5,1')->name('magic.send');
```

`/reservations/{reservation}/pay` is kept as a permanent redirect to `/r/{token}` so links
already in the wild and existing tests keep working.

`/r/{token}` carries **no `auth` middleware**. It authorizes by token possession OR ownership —
the standard guest-order-status pattern. The page exposes only that one booking.

---

## Components & UX

### `Reservations\Register` (Livewire, full page)

One scrolling page rather than a wizard. Progressive disclosure keeps the group case from
bleeding into the solo case: someone booking one seat for themselves never sees a companion
card, an "add contact" field, or a booker/traveler distinction.

**Section 1 — Seats.** Stepper control, live total price against the trip's current tier, and
remaining-spots text.

**Section 2 — Your details.** The booker's `contact_*` block. Fully pre-filled for a signed-in
joiner. Labelled by role ("Who we contact about this booking"), not by assumption of travel.

**Section 3 — Who's going.** Opens with the **"I'm joining this trip too"** toggle, checked by
default.

- *Checked* — Seat 1 is titled "You" and auto-fills its name from the contact block; the
  traveler-only fields (birthday, gender) still need answering. Remaining seats render as
  collapsed cards offering "Add details" or "I'll add this later."
- *Unchecked* — every seat is a separate person and the card titles drop to "Seat 1…N". Helper
  text states plainly: *"You book and pay; N people travel. Your details are used for contact
  only and take no seat."* This is the coordinator and the parent-booking-for-kids case.

Each traveler card carries an optional **"+ add contact"** reveal for that person's own email
and phone. Collapsed by default so it costs nothing when unused.

**Section 4 — Confirm.** Summary, then the pre-checked account toggle (hidden entirely for a
signed-in joiner), then submit.

**Toggle-off rule.** When "I'm joining this trip too" is unchecked, seat 1's auto-filled name is
cleared **only if it still exactly matches the contact block** — that is, only if the booker
never edited it. If they typed something different there, it is theirs and is preserved. This
keeps the toggle from destroying work while still not stranding the booker's own name in a seat
meant for someone else.

Field behaviour:
- Validation runs on blur, not only on submit.
- Birthday is `type="date"` with `max` = today.
- Gender renders as a segmented Male/Female with a "more options" reveal for Other and Prefer
  not to say — one tap for the common case, no forced disclosure.
- Phone accepts `09xxxxxxxxx` and `+639xxxxxxxxx`, normalising to one stored form.
- The in-progress form autosaves to the **session**, keyed by departure, so a refresh or an
  accidental back-navigation does not wipe it. Session-only; no table, unlike the host-side
  `TripDraft`.

### `Reservations\Show` (Livewire, full page)

Replaces `Reservations\Pay`. Same payment-submission behaviour, but authorized by token, and it
renders the booking status for guests too. Shows the "save this link" panel described below.

### Login page addition

A signed-in-by-link option ("Email me a sign-in link") is added to the existing login form.
Without it, a passwordless account holder returning to `/login` would type their email, have no
password, and be told their credentials are wrong — an unrecoverable dead end.

### The mail-independent fallback

Because a mail provider is not assumed, the confirmation screen always renders the booking's
`/r/{token}` URL with a copy button and clear "save this link — it's how you get back to your
booking" text. Email is sent *in addition* when a transport is configured. Registration is
therefore fully functional with `MAIL_MAILER=log`.

**Operational note for deployment:** `QUEUE_CONNECTION=database` means queued mail requires a
running worker. Without one, magic-link emails queue silently and never send. The on-screen
fallback means this degrades rather than breaks.

---

## Security rules

1. **Only a link emailed to the address itself may create a session.** Spec 2's host-shared
   invite links carry pre-fill and attribution only, never authentication. A link pasted into a
   group chat must never log anyone in.
2. Sign-in links are single-use with a 30-minute TTL, invalidated on consumption.
3. Tokens are stored hashed; raw values appear only in URLs and email bodies.
4. Email collision never auto-attaches a booking to an existing account.
5. `/magic` issuance is rate-limited to 5 per minute per IP to prevent using it as a mail bomb.
6. `/r/{token}` exposes exactly one booking and no account data.

---

## Edge cases

| Case | Handling |
|---|---|
| Departure sells out mid-form | `TripFullException` is already thrown by `reserve()`; surface "Only N seats left" and reset the stepper to N |
| Departure cancelled or past | Block registration with an explanatory message before the form renders |
| Booker already holds an active reservation on this departure | Redirect to the existing booking rather than creating a second one |
| Traveler rows left blank | Allowed. Reservation is created; host roster shows "N of M details pending" |
| Participant is a minor on the departure date | Flagged on the host roster, since PH permits commonly require guardian consent |
| Party size reduced after travelers were filled | Trim from the highest position. If the trimmed row was `is_booker`, clear the flag and leave the toggle unchecked rather than silently reassigning it |
| Booker not travelling, `party_size = 1` | Valid. One traveler who is not the booker — the parent-books-for-one-child case |
| Booker not travelling, all traveler rows blank | Allowed at save time, but the confirmation screen leads with the outstanding-details prompt, since the host has a booking with no named heads |
| Booker's own email also entered as a traveler's optional contact | Permitted and not deduplicated — a coordinator may legitimately be a traveler's contact too |
| Host account attempts to join | Rejected with the existing message |

---

## Testing

**Pest feature tests**

1. Guest registers for one seat with the toggle on → reservation created, one participant with
   `is_booker = true`, `contact_*` populated, `joiner_id` null
2. Guest registers three seats with one traveler left blank → three participant rows, one
   failing `isComplete()`
2b. **Booker not travelling:** toggle off with `party_size = 3` → three participant rows, none
   flagged `is_booker`, `bookerIsTraveling()` false, and the booker's name appears in
   `contact_*` but in no participant row
2c. Toggling off after seat 1 auto-filled clears it; toggling off after the booker *edited*
   seat 1 preserves what they typed
2d. Optional per-participant email/phone persists on a non-booker traveler
3. `create_account = true` with an unused email → passwordless user created **from the booker's
   contact details, not from a traveler**, `joiner_id` set, `login` magic link issued
4. `create_account = true` with an already-registered email → booking stays guest, `joiner_id`
   null, `attach` link issued, no session created
5. Signed-in joiner registers → form pre-filled, missing profile fields written back to the user
6. Host account is refused
7. `/r/{token}` loads with no authentication; an unknown token 404s
8. Magic link consumes once; second use and expired use both rejected
9. Oversell remains impossible through the new path (extends the existing capacity test)
10. A participant under 18 on the departure date is flagged; one who turns 18 before it is not
11. Passwordless user cannot authenticate with any submitted password

**Dusk**

12. Full guest happy path: open trip → join → fill → confirm → land on the booking page
13. Account toggle path: same, with the toggle left checked, asserting the account exists after

---

## Handoff to Spec 2

Spec 2 adds the `invite` purpose to `magic_links`, the host share panel (copy link / generate
invite / download QR), re-points `TripQrController` at `trips.join`, and adds attribution so a
host can see which share channel produced a booking. No schema change to
`reservation_participants` is anticipated.
