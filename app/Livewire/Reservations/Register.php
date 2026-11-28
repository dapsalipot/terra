<?php

namespace App\Livewire\Reservations;

use App\Exceptions\AlreadyBookedException;
use App\Exceptions\TripFullException;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\WaitlistService;
use App\Support\PageMeta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class Register extends Component
{
    use WithFileUploads;

    public Trip $trip;

    public TripDeparture $departure;

    /**
     * Seats. Deliberately untyped.
     *
     * A `public int` cannot hold the empty string Livewire sends when someone clears the number
     * field to retype it. PHP leaves the typed property uninitialized, then routes the next read
     * to __get(), where Livewire throws PropertyNotFoundException — a 500 for the ordinary act of
     * backspacing over a number.
     */
    public $partySize = 1;

    public array $contact = [
        'first_name' => '', 'middle_name' => '', 'last_name' => '', 'email' => '', 'phone' => '',
    ];

    public bool $bookerIsTraveling = true;

    public array $participants = [];

    /**
     * A photo ID per seat, keyed by seat index, staged until the booking is written.
     *
     * Separate from $participants because a Livewire temporary upload is an object, and mixing
     * one into the array that gets normalised and serialised straight into the database is how
     * you end up storing a file handle in a name column.
     */
    public array $ids = [];

    public bool $createAccount = true;

    public ?string $error = null;

    /** True when this departure can no longer be joined. Renders a dead-end notice, not a form. */
    public bool $closed = false;

    /** Sold out, which is a queue rather than a closure. */
    public bool $soldOut = false;

    public bool $waitlisted = false;

    public string $waitlistName = '';

    public string $waitlistEmail = '';

    public string $waitlistPhone = '';

    public string $closedReason = '';

    public const BLANK_PARTICIPANT = [
        'first_name' => '', 'middle_name' => '', 'last_name' => '',
        'birthday' => '', 'gender' => '', 'email' => '', 'phone' => '',
        'emergency_contact_name' => '', 'emergency_contact_phone' => '',
        'show_contact' => false,
    ];

    /*
     * How many bookings one source may make in a window. A booking holds its seats for a day
     * before anything is paid (ReservationService::$holdHours), so the window is a day too.
     * The window is fixed, not sliding: it opens at a source's first booking. Late holds from
     * one window overlap the next, so almost twice these numbers can be alive at once.
     *
     * Bookings are counted, not seats: a real group is one booking of up to twenty. The address
     * gets more room because a mobile carrier can put many people behind one.
     */
    private const BOOKINGS_PER_EMAIL = 3;

    private const BOOKINGS_PER_ADDRESS = 10;

    private const BOOKING_WINDOW_SECONDS = 86400;

    public function mount(Trip $trip)
    {
        abort_unless($trip->status === 'published', 404);

        if (Auth::user()?->isHost()) {
            return $this->redirectRoute('trips.show', ['trip' => $trip], navigate: true);
        }

        $this->trip = $trip;
        $this->departure = $trip->departures()->firstOrFail();
        $this->participants = [self::BLANK_PARTICIPANT];

        // A trip can be published while its one departure has sailed or been called off. Show a
        // dead end rather than a form that can only fail on submit.
        if (in_array($this->departure->status, ['cancelled', 'completed'], true)) {
            $this->closed = true;
            $this->closedReason = 'This departure has been cancelled.';

            return null;
        }

        if ($this->departure->starts_at->isPast()) {
            $this->closed = true;
            $this->closedReason = 'This trip has already left.';

            return null;
        }

        /*
         * Sold out is not a dead end, it is a queue.
         *
         * An unpaid hold lapses every few hours here, so "no seats" is very often temporary — and
         * a page that simply says no is the one place this app throws away demand it already has.
         */
        if ($this->departure->spotsLeft() < 1) {
            $this->soldOut = true;

            if ($user = Auth::user()) {
                $this->waitlistName = $user->name ?? '';
                $this->waitlistEmail = $user->email ?? '';
                $this->waitlistPhone = $user->phone ?? '';
            }

            return null;
        }

        // Someone already holding a live booking here wants that booking, not a second one.
        if ($existing = $this->existingReservation()) {
            return $this->openBooking($existing);
        }

        if ($user = Auth::user()) {
            [$first, $last] = $this->nameParts($user);

            $this->contact = [
                'first_name' => $first,
                'middle_name' => $user->middle_name ?? '',
                'last_name' => $last,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ];
            $this->participants[0] = array_merge(self::BLANK_PARTICIPANT, [
                'first_name' => $first,
                'middle_name' => $user->middle_name ?? '',
                'last_name' => $last,
                'birthday' => $user->birthday?->format('Y-m-d') ?? '',
                'gender' => $user->gender ?? '',
            ]);
        }

        $this->restoreDraft();

        return null;
    }

    /**
     * First and last name, falling back to splitting the legacy single `name` column.
     *
     * Every account created before structured name parts existed has only `name`, so without
     * this fallback a long-standing member would meet an empty form — worse than the guest
     * experience. A split is a starting point they can correct, not a claim to be right.
     *
     * @return array{0: string, 1: string}
     */
    private function nameParts(User $user): array
    {
        if (filled($user->first_name) || filled($user->last_name)) {
            return [$user->first_name ?? '', $user->last_name ?? ''];
        }

        $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];

        return [$parts[0] ?? '', count($parts) > 1 ? (string) end($parts) : ''];
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
    private function existingReservation(): ?Reservation
    {
        return Auth::check() ? $this->departure->liveBookingFor(Auth::user()) : null;
    }

    private function openBooking(Reservation $booking)
    {
        return $this->redirectRoute('reservations.show', ['token' => $booking->public_token], navigate: true);
    }

    /**
     * The most seats this booking can take.
     *
     * Capped by what is actually left rather than the blanket 20, so the stepper cannot walk
     * someone into a TripFullException they could have been shown up front.
     */
    public function getMaxSeatsProperty(): int
    {
        return max(1, min(20, $this->spotsLeft()));
    }

    /**
     * Photos for the trip panel, best first.
     *
     * `cover_url` is not a plain accessor: when the host has uploaded no cover it returns a stock
     * photo chosen from the place's category. So this cannot be "cover, then the rest" — a host
     * with three real photos and no cover set would lead with a picture of somewhere they have
     * never been. Real images always win; the stock shot appears only when there is nothing else.
     *
     * @return array<int, array{url: string, alt: string}>
     */
    public function getGalleryProperty(): array
    {
        $shot = fn (?string $url) => $url ? ['url' => $url, 'alt' => $this->trip->title] : null;

        $gallery = collect([$this->trip->cover_photo ? $shot($this->trip->cover_url) : null])
            ->concat($this->trip->photos->map(fn ($p) => $shot($p->url)))
            ->filter()
            ->values();

        return $gallery->isNotEmpty()
            ? $gallery->all()
            : array_filter([$shot($this->trip->cover_url)]);
    }

    /**
     * Seats remaining, resolved once per request.
     *
     * addSeat() and render() both want it, and spotsLeft() is a query — without the memo a single
     * tap on the stepper pays for it twice.
     */
    private ?int $spotsLeft = null;

    private function spotsLeft(): int
    {
        return $this->spotsLeft ??= $this->closed ? 0 : $this->departure->spotsLeft();
    }

    public function addSeat(): void
    {
        $this->partySize = (int) $this->partySize + 1;
        $this->updatedPartySize();
    }

    public function removeSeat(): void
    {
        $this->partySize = max(1, (int) $this->partySize - 1);
        $this->updatedPartySize();
    }

    /** Seats drive the traveller list; one row per seat, always. */
    public function updatedPartySize(): void
    {
        // Mid-edit the field is legitimately empty: select-all-and-retype, or backspace before
        // typing "12". Clamping to 1 here would rewrite the box under the user's cursor and make
        // a two-digit number impossible to type. Leave it be; validation catches it on submit.
        if (blank($this->partySize)) {
            return;
        }

        $this->partySize = max(1, min($this->maxSeats, (int) $this->partySize));

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

        // Clear seat 1 only if it still mirrors the contact block untouched. If the booker typed
        // a different name there it is theirs, and wiping it would destroy their work.
        if ($this->seatMirrorsContact(0)) {
            $this->participants[0] = self::BLANK_PARTICIPANT;
        }

        $this->saveDraft();
    }

    public function toggleContactFor(int $i): void
    {
        $this->participants[$i]['show_contact'] = ! ($this->participants[$i]['show_contact'] ?? false);
    }

    /**
     * Put the booker's own address on someone else's seat.
     *
     * Requiring an email per seat is only reasonable if there is a one-tap answer: a booker
     * organising five friends rarely has five addresses to hand, and without this the rule would
     * be a wall rather than a prompt. Sharing one inbox across seats is a legitimate answer — the
     * booker is the person the host needs to reach.
     */
    public function useBookerEmail(int $i): void
    {
        $this->participants[$i]['email'] = trim((string) ($this->contact['email'] ?? ''));
        $this->saveDraft();
    }

    /**
     * Gender chips toggle rather than latch.
     *
     * It is optional, so a mis-tap has to be undoable. With a plain $set the only way back from
     * an accidental answer was to reload the page and lose the rest of the form.
     */
    public function setGender(int $i, string $value): void
    {
        $this->participants[$i]['gender'] = ($this->participants[$i]['gender'] ?? '') === $value ? '' : $value;
        $this->saveDraft();
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

    public function joinWaitlist(WaitlistService $waitlist): void
    {
        $this->validate([
            'waitlistName' => ['required', 'string', 'max:120'],
            'waitlistEmail' => ['required', 'email', 'max:180'],
            'waitlistPhone' => ['nullable', 'string', 'max:32'],
        ], attributes: [
            'waitlistName' => 'name',
            'waitlistEmail' => 'email',
            'waitlistPhone' => 'mobile',
        ]);

        $waitlist->join($this->departure, [
            'user_id' => Auth::id(),
            'name' => $this->waitlistName,
            'email' => $this->waitlistEmail,
            'phone' => $this->waitlistPhone ?: null,
            'party_size' => 1,
        ]);

        $this->waitlisted = true;
    }

    protected function rules(): array
    {
        $rules = [
            'partySize' => ['required', 'integer', 'min:1', 'max:20'],
            'contact.first_name' => ['required', 'string', 'max:80'],
            'contact.middle_name' => ['nullable', 'string', 'max:80'],
            'contact.last_name' => ['required', 'string', 'max:80'],
            'contact.email' => ['required', 'email', 'max:180'],
            'contact.phone' => ['required', 'string', 'max:32'],
            'participants.*.middle_name' => ['nullable', 'string', 'max:80'],
            'participants.*.birthday' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'participants.*.gender' => ['nullable', 'string', 'max:24'],
            'participants.*.phone' => ['nullable', 'string', 'max:32'],
            // Required, unlike every other per-traveller field. A trip up a mountain with
            // nobody to call is the one gap worth blocking a booking over.
            'participants.*.emergency_contact_name' => ['required', 'string', 'max:120'],
            'participants.*.emergency_contact_phone' => ['required', 'string', 'max:32'],
            'ids.*' => ['nullable', 'image', 'max:5120'],
        ];

        /*
         * Names are required per seat, not by wildcard, because seat 1 is a special case.
         *
         * While the booker is travelling, seat 1 mirrors the contact block — the same box, shown
         * once. Requiring it here too would raise two errors for one empty field and send the
         * booker hunting for a second thing to fill in. The contact rules above already cover the
         * name and the email; submit() re-mirrors the name before validating so the copy is never
         * stale, and normalisedParticipants() fills that seat's email from the contact.
         */
        foreach (array_keys($this->participants) as $i) {
            $presence = $this->bookerIsTraveling && $i === 0 ? 'nullable' : 'required';

            $rules["participants.{$i}.first_name"] = [$presence, 'string', 'max:80'];
            $rules["participants.{$i}.last_name"] = [$presence, 'string', 'max:80'];
            $rules["participants.{$i}.email"] = [$presence, 'email', 'max:180'];

            /*
             * The whole point of the host's toggle.
             *
             * Without this rule the requirement was decoration: a joiner booked, was never asked,
             * and the host found out at the permit desk. Required per seat rather than by
             * wildcard so the message names the traveller it is missing for.
             */
            if ($this->trip->requires_id) {
                $rules["ids.{$i}"] = ['required', 'image', 'max:5120'];
            }
        }

        return $rules;
    }

    /** Laravel would otherwise surface the attribute path: "The contact.first name field...". */
    protected function validationAttributes(): array
    {
        return [
            'partySize' => 'number of seats',
            'contact.first_name' => 'first name',
            'contact.middle_name' => 'middle name',
            'contact.last_name' => 'last name',
            'contact.email' => 'email',
            'contact.phone' => 'mobile number',
        ];
    }

    /**
     * Custom messages.
     *
     * Named `messages()`, not `validationMessages()`. Livewire reads only the former
     * (HandlesValidation::getMessages) even though it accepts either name for attributes, so
     * everything in here was silently inert until the seat messages needed to reach the screen.
     */
    protected function messages(): array
    {
        $messages = [
            // The summary at the top of the form is read as a list of things to go and do, so
            // these are phrased as requests rather than as "The X field is required."
            'contact.first_name.required' => 'We need your first name.',
            'contact.last_name.required' => 'We need your last name.',
            'contact.email.required' => 'We need an email — your booking link goes there.',
            'contact.email.email' => 'That email address does not look right.',
            'contact.phone.required' => 'We need a mobile number so the host can reach you.',
            'participants.*.birthday.before' => 'A birthday has to be in the past.',
            'participants.*.birthday.after' => 'That birthday looks like a typo — please check the year.',
        ];

        /*
         * Say which seat.
         *
         * The default renders the attribute path — "The participants.1.first name field is
         * required" — which makes the booker count cards to find the one at fault. On a six-seat
         * booking the offending card is usually off-screen, so the message is the only pointer
         * they have.
         */
        foreach (array_keys($this->participants) as $i) {
            $seat = 'Seat '.($i + 1);

            $messages["participants.{$i}.first_name.required"] = "{$seat} needs a first name.";
            $messages["participants.{$i}.last_name.required"] = "{$seat} needs a last name.";
            $messages["participants.{$i}.email.required"] = "{$seat} needs an email address.";
            $messages["participants.{$i}.email.email"] = "The email on {$seat} does not look right.";
        }

        return $messages;
    }

    /** Set once the form has passed validation and the review sheet is on screen. */
    public bool $showReview = false;

    /**
     * Check everything, then show what is about to be booked.
     *
     * Validation runs here rather than on the final button so the review sheet can never open
     * over a form that is going to be rejected — being shown a tidy summary and *then* told a
     * seat is missing an email is worse than never seeing the summary at all.
     */
    public function review(): void
    {
        if ($this->closed) {
            return;
        }

        $this->error = null;
        $this->mirrorBookerSeat();
        $this->validate();

        $this->showReview = true;
    }

    public function closeReview(): void
    {
        $this->showReview = false;
    }

    public function submit(RegistrationService $service)
    {
        if ($this->closed) {
            return null;
        }

        // Booked from another tab since this page opened. Asked first, so nothing is counted and no
        // ID photo is stored. register() asks again under a lock, for submits that arrive together.
        if ($existing = $this->existingReservation()) {
            return $this->openBooking($existing);
        }

        // Seat 1 is exempt from the name rules while it mirrors the contact block, so the mirror
        // has to be current before those rules are skipped. wire:model.blur means the last field
        // touched may not have synced when the button was clicked.
        $this->mirrorBookerSeat();

        $this->error = null;
        $this->validate();

        // After validation, so a typo costs nothing. Before any ID photo is saved.
        if (! $this->claimBooking()) {
            return null;
        }

        $participants = $this->normalisedParticipants();
        $result = null;

        try {
            // Only the rows register() will write: after a "full" refusal lowers the seat count,
            // the form still holds more travellers than that. Each path goes into $participants as
            // its photo is saved, so the clean-up below knows of it even if a later one fails.
            for ($seat = 0; $seat < (int) $this->partySize; $seat++) {
                if (isset($participants[$seat], $this->ids[$seat])) {
                    // Private disk, like every other identity document here.
                    $participants[$seat]['id_image'] = $this->ids[$seat]->store('ids', 'local');
                    $participants[$seat]['id_uploaded_at'] = now();
                }
            }

            $result = $service->register($this->departure, [
                'party_size' => (int) $this->partySize,
                'contact' => $this->normalisedContact(),
                'booker_is_traveling' => $this->bookerIsTraveling,
                'participants' => $participants,
                'create_account' => $this->isSignedIn ? false : $this->createAccount,
                'actor' => Auth::user(),
            ]);
        } catch (AlreadyBookedException $e) {
            $this->releaseBooking();

            return $this->openBooking($e->reservation);
        } catch (TripFullException $e) {
            // No seats were held, so this is not one of the visitor's bookings.
            $this->releaseBooking();
            $this->error = $e->getMessage();
            $this->partySize = max(1, (int) $this->departure->fresh()->spotsLeft());

            return null;
        } finally {
            // A rollback undoes rows, not files. With no booking, however that came about, its
            // photos go. The form still holds the uploads, so another try saves them again.
            // ponytail: a request killed outright never gets here. If that shows up, sweep ids/ for files no traveller points to.
            if ($result === null) {
                Storage::disk('local')->delete(array_filter(array_column($participants, 'id_image')));
            }
        }

        session()->forget($this->draftKey());

        // What happened, never the sign-in link: when the address already had an account, the link signs in to it.
        session()->flash('registration_outcome', $result->outcome->value);

        return $this->openBooking($result->reservation);
    }

    /** @return array<string, int> limiter key => bookings it allows in a window */
    private function bookingLimits(): array
    {
        return [
            // ponytail: the exact address, so an IPv6 client can move around its /64. Group by /64 if that shows up.
            'booking:ip:'.request()->ip() => self::BOOKINGS_PER_ADDRESS,
            // Lowercased as it is stored. Hashed so that no email address sits in a cache key.
            'booking:email:'.sha1(strtolower(trim($this->contact['email']))) => self::BOOKINGS_PER_EMAIL,
        ];
    }

    /**
     * Take one booking from the allowance of this address and this email, or refuse.
     *
     * Counted first and compared after: reading the count, booking and then counting would let a
     * burst of requests sent together all find room. A refused attempt is handed straight back.
     */
    private function claimBooking(): bool
    {
        $wait = null;

        foreach ($this->bookingLimits() as $key => $allowed) {
            if (RateLimiter::hit($key, self::BOOKING_WINDOW_SECONDS) > $allowed) {
                $wait = max($wait ?? 0, RateLimiter::availableIn($key));
            }
        }

        if ($wait === null) {
            return true;
        }

        $this->releaseBooking();

        // Rounded up, so nobody is told to come back too early. The wording does not say which
        // limit it was, so it does not report on what else an email address has been booking.
        $hours = max(1, (int) ceil($wait / 3600));
        $this->error = 'Nothing was booked: there have been too many new bookings from this email or connection. '
            .'Your details are still filled in. Try again in '.$hours.' '.Str::plural('hour', $hours).'.';

        return false;
    }

    private function releaseBooking(): void
    {
        foreach (array_keys($this->bookingLimits()) as $key) {
            RateLimiter::decrement($key, self::BOOKING_WINDOW_SECONDS);
        }
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
        return collect($this->participants)->map(function (array $p, int $i) {
            $email = strtolower(trim($p['email'] ?? '')) ?: null;

            /*
             * The booker's own seat inherits their contact address.
             *
             * It is the only seat exempt from the email rule, because asking for it there would
             * be a second box for an address already given a section earlier. Filling it here
             * keeps the roster complete without the duplicate question — and only when blank, so
             * a booker who deliberately gave their seat a different address keeps it.
             */
            if ($email === null && $this->bookerIsTraveling && $i === 0) {
                $email = strtolower(trim($this->contact['email'] ?? '')) ?: null;
            }

            return [
                'first_name' => trim($p['first_name'] ?? '') ?: null,
                'middle_name' => trim($p['middle_name'] ?? '') ?: null,
                'last_name' => trim($p['last_name'] ?? '') ?: null,
                'birthday' => ($p['birthday'] ?? '') ?: null,
                'gender' => ($p['gender'] ?? '') ?: null,
                'email' => $email,
                'phone' => ($p['phone'] ?? '') ? $this->normalisePhone($p['phone']) : null,
                'emergency_contact_name' => trim($p['emergency_contact_name'] ?? '') ?: null,
                'emergency_contact_phone' => ($p['emergency_contact_phone'] ?? '')
                    ? $this->normalisePhone($p['emergency_contact_phone'])
                    : null,
            ];
        })->all();
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
        // Seats can be blank while the field is being retyped, so the totals fall back to one
        // rather than rendering a price of zero.
        $seats = max(1, (int) $this->partySize);

        return view('livewire.reservations.register', [
            'gallery' => $this->gallery,
            'spotsLeft' => $this->spotsLeft(),
            'maxSeats' => $this->maxSeats,
            'seats' => $seats,
            'perSeat' => $this->trip->current_price,
            'total' => $this->trip->current_price * $seats,
            'payableNow' => $this->trip->payable_now * $seats,
        ])->layout('components.layouts.site', ['tabbar' => false, 'meta' => PageMeta::join($this->trip)]);
    }
}
