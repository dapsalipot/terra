<?php

namespace App\Livewire\Host;

use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\TripDeparture;
use App\Services\CheckInService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

class Roster extends Component
{
    public TripDeparture $departure;

    public array $rejectReason = [];

    /**
     * Search and filter, both in the URL so a host can bookmark or share "who still owes me".
     *
     * #[Url] hydrates straight onto the property, so `filter` is re-checked against the allow-list
     * in render() rather than trusted here — a stray ?filter=nonsense must fall back to showing
     * everyone, not silently hide the roster.
     */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $filter = 'all';

    /**
     * Which job this page is doing right now.
     *
     * The page serves three: chasing money, knowing who is coming, and running the day. One
     * table trying to answer all three at once is how it ended up with eight possible actions
     * per row and no two rows alike. A mode picks the columns, the filters and the row's one
     * affordance; the set of bookings underneath never changes.
     */
    #[Url(except: 'money')]
    public string $mode = 'money';

    public const MODES = ['money', 'manifest', 'checkin'];

    /**
     * Filters belong to a mode.
     *
     * "Fully paid" is noise while you are checking people in, and "arrived" is meaningless while
     * you are reconciling transfers. Offering only what the current job can use is what stops the
     * chip row becoming another thing to read past.
     */
    public const FILTERS = [
        'money' => ['all', 'proof', 'unpaid', 'paid', 'unconfirmed', 'refund', 'cancelled'],
        'manifest' => ['all', 'incomplete', 'no-id'],
        'checkin' => ['all', 'waiting', 'arrived'],
    ];

    /** Which booking the drawer is showing. Null is closed. */
    public ?int $open = null;

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, self::MODES, true) ? $mode : 'money';

        // A filter from the old mode means nothing in the new one, and leaving it set would hide
        // rows for a reason the chip row no longer displays.
        $this->filter = 'all';
        $this->open = null;
    }

    /** The filters valid in whichever mode is showing. */
    public function filtersForMode(): array
    {
        return self::FILTERS[$this->mode] ?? self::FILTERS['money'];
    }

    /**
     * Open the drawer on one booking.
     *
     * Guarded against ids from another departure the same way every action here is — the method
     * is reachable from the browser whatever the list renders.
     */
    public function openBooking(int $reservationId): void
    {
        $this->open = $this->departure->reservations()->whereKey($reservationId)->value('id');
    }

    public function closeBooking(): void
    {
        $this->open = null;
    }

    /** Check-in mode's one inline action: the toggle a host taps with a person in front of them. */
    public function toggleArrival(int $participantId, CheckInService $checkIns): void
    {
        $participant = ReservationParticipant::whereKey($participantId)
            ->whereHas('reservation', fn ($q) => $q->where('trip_departure_id', $this->departure->id))
            ->first();

        if (! $participant) {
            return;
        }

        $participant->checked_in_at
            ? $checkIns->undoCheckIn($participant, Auth::user())
            : $participant->update(['checked_in_at' => now(), 'checked_in_by' => Auth::id()]);
    }

    public function updatedSearch(): void
    {
        // Nothing to reset — the list is not paginated — but the hook keeps the URL honest.
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, $this->filtersForMode(), true) ? $filter : 'all';
    }

    // Add-offline-participant form
    public bool $showAdd = false;

    public string $guest_name = '';

    public int $guest_party = 1;

    public function mount(TripDeparture $departure): void
    {
        $departure->load('trip.place', 'trip.priceTiers');
        abort_unless($departure->trip->host_id === Auth::id(), 403);
        $this->departure = $departure;
    }

    public function confirm(int $reservationId, ReservationService $service): void
    {
        $service->confirm($this->guardedReservation($reservationId), Auth::user());
    }

    /**
     * The host has looked at a proof and accepts it.
     *
     * Three separate decisions live on this row and each has its own button: granting the seat
     * (confirm), accepting the proof that arrived for it (this), and declaring the whole balance
     * settled (markFullyPaid). Confirming used to quietly do the middle one too, which is how
     * accepting somebody onto a trip ended up marking them paid.
     */
    /** The accept-payment form: what arrived, and whose share it settles. */
    public string $payAmount = '';

    public array $payFor = [];

    public bool $payOpen = false;

    /**
     * Open the form with the figure already filled in.
     *
     * Seeded with what this booking owes next — the deposit if one is outstanding, the balance
     * otherwise — and with every unpaid traveller ticked, because "all of them" is what a host
     * means most of the time and unticking two is less work than ticking three.
     */
    public function openPayment(int $reservationId): void
    {
        $r = $this->guardedReservation($reservationId);
        $trip = $this->departure->trip;
        $money = $r->money();

        $depositOutstanding = max(0, ($trip->payable_now * $r->party_size) - $money['paid']);

        $this->open = $r->id;
        $this->payOpen = true;
        $this->payAmount = (string) ($depositOutstanding > 0 ? $depositOutstanding : $money['due']);
        $this->payFor = $r->participants
            ->reject(fn ($p) => $r->participantIsPaid($p))
            ->pluck('id')->all();
    }

    public function closePayment(): void
    {
        $this->payOpen = false;
        $this->payAmount = '';
        $this->payFor = [];
    }

    public function verifyPayment(int $reservationId, ReservationService $service): void
    {
        $r = $this->guardedReservation($reservationId);

        // Only when the form was used; a bare call keeps the old behaviour of accepting whatever
        // is owed next for the whole booking.
        if ($this->payOpen) {
            $this->validate(
                ['payAmount' => ['required', 'numeric', 'min:0.01']],
                ['payAmount.required' => 'How much arrived?', 'payAmount.min' => 'That has to be more than zero.'],
            );
        }

        $service->verifyPayment(
            $r,
            Auth::user(),
            $this->payOpen && $this->payAmount !== '' ? (float) $this->payAmount : null,
            $this->payOpen ? array_map('intval', $this->payFor) : [],
        );

        $this->closePayment();
    }

    /** Separate from confirm(): accepting the seat and the money all being in are two facts. */
    public function markFullyPaid(int $reservationId, ReservationService $service): void
    {
        $service->markFullyPaid($this->guardedReservation($reservationId), Auth::user());
    }

    public function unmarkFullyPaid(int $reservationId, ReservationService $service): void
    {
        $service->unmarkFullyPaid($this->guardedReservation($reservationId));
    }

    public function reject(int $reservationId, ReservationService $service): void
    {
        $reason = trim($this->rejectReason[$reservationId] ?? '') ?: 'Payment could not be verified';
        $service->reject($this->guardedReservation($reservationId), Auth::user(), $reason);
        unset($this->rejectReason[$reservationId]);
    }

    public function addOffline(ReservationService $service): void
    {
        $this->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_party' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $service->addManualParticipant($this->departure->fresh(), [
            'guest_name' => $this->guest_name,
            'party_size' => $this->guest_party,
        ]);

        $this->reset('guest_name', 'guest_party', 'showAdd');
        $this->guest_party = 1;
    }

    /**
     * Matches the booker and every traveller on the booking.
     *
     * A host looking someone up types the name of the person standing in front of them, who is
     * very often a traveller rather than whoever paid — searching the booker alone would miss
     * them on exactly the bookings that have more than one person in them.
     */
    private function matchesSearch(Reservation $reservation): bool
    {
        $needle = trim(mb_strtolower($this->search));

        if ($needle === '') {
            return true;
        }

        $haystack = collect([
            $reservation->displayName(),
            $reservation->contact_email,
            $reservation->joiner?->email,
            $reservation->joiner?->phone,
            $reservation->guest_phone,
            $reservation->latestPayment?->reference_no,
        ])->merge($reservation->participants->map->fullName())
            ->filter()
            ->implode(' ');

        return str_contains(mb_strtolower($haystack), $needle);
    }

    private function guardedReservation(int $id): Reservation
    {
        return Reservation::where('trip_departure_id', $this->departure->id)->findOrFail($id);
    }

    /** Which booking's proof list is open, if any. */
    public ?int $proofsFor = null;

    /**
     * Call the whole departure off.
     *
     * Guarded by the same host check every other action here uses, via the mount-time authorize.
     */
    public function cancelDeparture(ReservationService $service): void
    {
        $released = $service->cancelDeparture($this->departure, $this->cancelReason ?: null);

        $this->cancelReason = '';
        $this->departure->refresh();

        $this->dispatch('notify', $released.' '.Str::plural('booking', $released).' released.');
    }

    public string $cancelReason = '';

    /** Remove a booking: a no-show, a duplicate, one the host knows is not real. */
    public function cancelBooking(int $reservationId, ReservationService $service): void
    {
        $service->cancelAsHost($this->guardedReservation($reservationId));

        $this->dispatch('notify', 'Booking cancelled and the seat released.');
    }

    /** Record that the money has gone back. Terra never held it, so this is a note, not a transfer. */
    public function markRefunded(int $reservationId, ReservationService $service): void
    {
        $service->markRefunded($this->guardedReservation($reservationId), Auth::user());

        $this->dispatch('notify', 'Marked as refunded.');
    }

    public function showProofs(int $reservationId): void
    {
        $this->proofsFor = $reservationId;
    }

    public function closeProofs(): void
    {
        $this->proofsFor = null;
    }

    public function render()
    {
        $priority = ['payment_submitted' => 0, 'awaiting_payment' => 1, 'pending' => 2, 'confirmed' => 3, 'cancelled' => 4];

        $reservations = $this->departure->reservations()
            // paymentSubmissions, not just the latest: the proof modal lists every attempt, and
            // a rejected-then-resubmitted booking is precisely the case a host needs to see whole.
            ->with('joiner', 'latestPayment', 'paymentSubmissions', 'participants')
            ->get()
            ->sortBy(fn ($r) => $priority[$r->status] ?? 9)
            ->values();

        /*
         * Money is summed over every live booking, deliberately before the search and filter are
         * applied. The totals answer "how is this departure doing", which does not change because
         * the host typed a name into a box — a filtered total would look like money going missing.
         */
        $live = $reservations->reject(fn ($r) => $r->status === 'cancelled');

        $money = [
            'expected' => $live->sum(fn ($r) => $r->money()['total']),
            'collected' => $live->sum(fn ($r) => $r->money()['paid']),
        ];
        $money['outstanding'] = $money['expected'] - $money['collected'];

        /*
         * "Settled" means nothing is owed, not that a host pressed the tag.
         *
         * Keying this off isFullyPaid() alone made the chip read "Not fully paid · 3" on a
         * no-deposit trip where all three rows displayed "Fully paid" — confirming had already
         * credited the whole price. The chip and the filter now agree because they ask the same
         * question the row does: is anything still due?
         */
        $settled = fn (Reservation $r) => $r->money()['due'] <= 0;

        // A proof nobody has ruled on. Status cannot express it: sending money leaves a confirmed
        // booking confirmed, so this is a fact about the submission, not about the reservation.
        $unreviewed = fn (Reservation $r) => $r->latestPayment
            && ! $r->latestPayment->confirmed_at
            && ! $r->latestPayment->rejected_reason;

        $incomplete = fn (Reservation $r) => $r->incompleteParticipants()->isNotEmpty();
        $missingId = fn (Reservation $r) => $this->departure->trip->requires_id
            && $r->participants->contains(fn ($p) => blank($p->id_image));
        $arrived = fn (Reservation $r) => $r->participants->isNotEmpty()
            && $r->participants->every(fn ($p) => $p->checked_in_at !== null);

        /*
         * Every predicate in one place, keyed the same way the chips are.
         *
         * Counts and filtering read the same closure, which is what stops a chip promising three
         * rows and delivering one — they used to be two separate expressions that drifted the
         * first time either definition was tuned.
         */
        $tests = [
            'all' => fn (Reservation $r) => true,
            'proof' => $unreviewed,
            'unpaid' => fn (Reservation $r) => $r->status !== 'cancelled' && ! $settled($r),
            'paid' => fn (Reservation $r) => $r->status !== 'cancelled' && $settled($r),
            'unconfirmed' => fn (Reservation $r) => $r->status !== 'cancelled' && $r->status !== 'confirmed',
            'refund' => fn (Reservation $r) => $r->owesRefund(),
            'cancelled' => fn (Reservation $r) => $r->status === 'cancelled',
            'incomplete' => $incomplete,
            'no-id' => $missingId,
            'waiting' => fn (Reservation $r) => $r->status !== 'cancelled' && ! $arrived($r),
            'arrived' => $arrived,
        ];

        $filter = in_array($this->filter, $this->filtersForMode(), true) ? $this->filter : 'all';

        // Counts come from the same unfiltered set, so a chip can show what it would find.
        $counts = collect($this->filtersForMode())
            ->mapWithKeys(fn (string $key) => [$key => $reservations->filter($tests[$key])->count()])
            ->all();

        $visible = $reservations
            ->filter($tests[$filter])
            ->filter(fn ($r) => $this->matchesSearch($r))
            ->values();

        // The drawer's booking, resolved from the full set rather than the visible one: filtering
        // to "arrived" while a drawer is open should not blank the drawer.
        $openBooking = $this->open ? $reservations->firstWhere('id', $this->open) : null;

        return view('livewire.host.roster', [
            'reservations' => $visible,
            'counts' => $counts,
            'activeFilter' => $filter,
            'mode' => in_array($this->mode, self::MODES, true) ? $this->mode : 'money',
            'filters' => $this->filtersForMode(),
            'openBooking' => $openBooking,
            'arrivedCount' => $reservations->reject(fn ($r) => $r->status === 'cancelled')
                ->sum(fn ($r) => $r->participants->filter(fn ($p) => $p->checked_in_at)->count()),
            'travellerCount' => $reservations->reject(fn ($r) => $r->status === 'cancelled')
                ->sum(fn ($r) => $r->participants->count()),
            'totalCount' => $reservations->count(),
            'money' => $money,
            // Demand the departure is sitting on. A host with six people waiting may well add a
            // second date, and nothing else on this page would tell them.
            'waiting' => $this->departure->waitlistEntries()->waiting()->count(),
            'currency' => Auth::user()->hostProfile?->currency ?? 'PHP',
            'trip' => $this->departure->trip,
        ])->layout('components.layouts.host', ['title' => 'Terra · '.$this->departure->trip->title]);
    }
}
