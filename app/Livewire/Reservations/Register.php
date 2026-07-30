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

    public int $partySize = 1;
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
            $this->contact = [
                'first_name' => $user->first_name ?? '',
                'middle_name' => $user->middle_name ?? '',
                'last_name' => $user->last_name ?? '',
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ];
            $this->participants[0] = array_merge(self::BLANK_PARTICIPANT, [
                'first_name' => $user->first_name ?? '',
                'middle_name' => $user->middle_name ?? '',
                'last_name' => $user->last_name ?? '',
                'birthday' => $user->birthday?->format('Y-m-d') ?? '',
                'gender' => $user->gender ?? '',
            ]);
        }

        $this->restoreDraft();

        return null;
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

    /** Seats drive the traveller list; one row per seat, always. */
    public function updatedPartySize(): void
    {
        $this->partySize = max(1, min(20, (int) $this->partySize));

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
        return [
            'partySize' => ['required', 'integer', 'min:1', 'max:20'],
            'contact.first_name' => ['required', 'string', 'max:80'],
            'contact.middle_name' => ['nullable', 'string', 'max:80'],
            'contact.last_name' => ['required', 'string', 'max:80'],
            'contact.email' => ['required', 'email', 'max:180'],
            'contact.phone' => ['required', 'string', 'max:32'],
            'participants.*.first_name' => ['nullable', 'string', 'max:80'],
            'participants.*.middle_name' => ['nullable', 'string', 'max:80'],
            'participants.*.last_name' => ['nullable', 'string', 'max:80'],
            'participants.*.birthday' => ['nullable', 'date', 'before:today'],
            'participants.*.gender' => ['nullable', 'string', 'max:24'],
            'participants.*.email' => ['nullable', 'email', 'max:180'],
            'participants.*.phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function submit(RegistrationService $service)
    {
        if ($this->closed) {
            return null;
        }

        $this->validate();

        try {
            $result = $service->register($this->departure, [
                'party_size' => $this->partySize,
                'contact' => $this->normalisedContact(),
                'booker_is_traveling' => $this->bookerIsTraveling,
                'participants' => $this->normalisedParticipants(),
                'create_account' => $this->isSignedIn ? false : $this->createAccount,
                'actor' => Auth::user(),
            ]);
        } catch (TripFullException $e) {
            $this->error = $e->getMessage();
            $this->partySize = max(1, $this->departure->fresh()->spotsLeft());

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
        return collect($this->participants)->map(fn (array $p) => [
            'first_name' => trim($p['first_name'] ?? '') ?: null,
            'middle_name' => trim($p['middle_name'] ?? '') ?: null,
            'last_name' => trim($p['last_name'] ?? '') ?: null,
            'birthday' => ($p['birthday'] ?? '') ?: null,
            'gender' => ($p['gender'] ?? '') ?: null,
            'email' => trim($p['email'] ?? '') ?: null,
            'phone' => ($p['phone'] ?? '') ? $this->normalisePhone($p['phone']) : null,
        ])->all();
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
        return view('livewire.reservations.register', [
            'spotsLeft' => $this->closed ? 0 : $this->departure->spotsLeft(),
            'total' => $this->trip->current_price * $this->partySize,
            'payableNow' => $this->trip->payable_now * $this->partySize,
        ])->layout('components.layouts.site');
    }
}
