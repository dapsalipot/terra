<?php

namespace App\Livewire\Host;

use App\Models\Reservation;
use App\Models\TripDeparture;
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

    public const FILTERS = ['all', 'unpaid', 'paid', 'unconfirmed', 'cancelled'];

    public function updatedSearch(): void
    {
        // Nothing to reset — the list is not paginated — but the hook keeps the URL honest.
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
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
    public function verifyPayment(int $reservationId, ReservationService $service): void
    {
        $service->verifyPayment($this->guardedReservation($reservationId), Auth::user());
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

        // Counts come from the same unfiltered set, so a filter chip can show what it would find.
        $counts = [
            'all' => $reservations->count(),
            'unpaid' => $live->reject($settled)->count(),
            'paid' => $live->filter($settled)->count(),
            'unconfirmed' => $live->filter(fn ($r) => $r->status !== 'confirmed')->count(),
            'cancelled' => $reservations->filter(fn ($r) => $r->status === 'cancelled')->count(),
        ];

        $filter = in_array($this->filter, self::FILTERS, true) ? $this->filter : 'all';

        $visible = $reservations
            ->filter(fn ($r) => match ($filter) {
                'unpaid' => $r->status !== 'cancelled' && ! $settled($r),
                'paid' => $r->status !== 'cancelled' && $settled($r),
                'unconfirmed' => $r->status !== 'cancelled' && $r->status !== 'confirmed',
                'cancelled' => $r->status === 'cancelled',
                default => true,
            })
            ->filter(fn ($r) => $this->matchesSearch($r))
            ->values();

        return view('livewire.host.roster', [
            'reservations' => $visible,
            'counts' => $counts,
            'activeFilter' => $filter,
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
