<?php

namespace App\Livewire\Reservations;

use App\Exceptions\TripFullException;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Services\RegistrationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Register extends Component
{
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
    public bool $createAccount = true;
    public ?string $error = null;

    /** True when this departure can no longer be joined. Renders a dead-end notice, not a form. */
    public bool $closed = false;
    public string $closedReason = '';

    public const BLANK_PARTICIPANT = [
        'first_name' => '', 'middle_name' => '', 'last_name' => '',
        'birthday' => '', 'gender' => '', 'email' => '', 'phone' => '',
        'show_contact' => false,
    ];

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

        // Someone already holding a live booking here wants that booking, not a second one.
        if ($existing = $this->existingReservation()) {
            return $this->redirectRoute(
                'reservations.show',
                ['token' => $existing->public_token],
                navigate: true,
            );
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
    private function nameParts(\App\Models\User $user): array
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
        if (! Auth::check()) {
            return null;
        }

        return $this->departure->reservations()
            ->where('joiner_id', Auth::id())
            ->whereIn('status', Reservation::HOLDING)
            ->first();
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

    public function submit(RegistrationService $service)
    {
        if ($this->closed) {
            return null;
        }

        // Seat 1 is exempt from the name rules while it mirrors the contact block, so the mirror
        // has to be current before those rules are skipped. wire:model.blur means the last field
        // touched may not have synced when the button was clicked.
        $this->mirrorBookerSeat();

        $this->validate();

        try {
            $result = $service->register($this->departure, [
                'party_size' => (int) $this->partySize,
                'contact' => $this->normalisedContact(),
                'booker_is_traveling' => $this->bookerIsTraveling,
                'participants' => $this->normalisedParticipants(),
                'create_account' => $this->isSignedIn ? false : $this->createAccount,
                'actor' => Auth::user(),
            ]);
        } catch (TripFullException $e) {
            $this->error = $e->getMessage();
            $this->partySize = max(1, (int) $this->departure->fresh()->spotsLeft());

            return null;
        }

        session()->forget($this->draftKey());

        // Carried in the session because the raw magic URL exists only on this request.
        session()->flash('registration_outcome', $result->outcome->value);
        session()->flash('registration_magic_url', $result->magicUrl);

        return $this->redirectRoute(
            'reservations.show',
            ['token' => $result->reservation->public_token],
            navigate: true,
        );
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
        ])->layout('components.layouts.site');
    }
}
