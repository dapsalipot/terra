<?php

namespace App\Livewire\Joiner;

use App\Mail\MagicLinkMail;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Trip;
use App\Services\MagicLinkService;
use App\Services\ReservationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $showClaim = false;
    public ?string $claimStatus = null;

    #[Url]
    public string $filter = 'upcoming'; // upcoming | past

    /*
     * Calendar leads: a joiner's trips are dates first and foremost, and a month grid answers
     * "when am I away?" at a glance in a way a list cannot.
     */
    #[Url]
    public string $view = 'calendar'; // calendar | grid | list

    /** Month on screen as YYYY-MM, in the URL so a month can be linked. */
    #[Url]
    public string $month = '';

    /** Which day the phone calendar is listing. Not in the URL — it is a transient tap. */
    public string $selectedDate = '';

    public array $dismissedAttention = [];

    /**
     * Email an attach link for any guest bookings made with this account's address.
     */
    public function claimGuestBookings(MagicLinkService $links): void
    {
        $user = Auth::user();

        $orphan = Reservation::whereNull('joiner_id')
            ->where('contact_email', $user->email)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        $this->claimStatus = 'If any bookings were made with '.$user->email.', we have emailed a link to attach them.';
        $this->showClaim = false;

        if (! $orphan) {
            return;
        }

        ['url' => $url] = $links->issue('attach', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reservation_id' => $orphan->id,
        ]);

        try {
            Mail::to($user->email)->send(new MagicLinkMail($url, $user->full_name));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function cancelBooking(int $reservationId, ReservationService $service): void
    {
        $reservation = Reservation::where('joiner_id', Auth::id())
            ->where('id', $reservationId)
            ->firstOrFail();

        $service->cancel($reservation, 'Cancelled by joiner');

        $this->dispatch('notify', 'Booking cancelled.');
    }

    public function dismissAttention(string $kind): void
    {
        $this->dismissedAttention[] = $kind;
        $this->dismissedAttention = array_values(array_unique($this->dismissedAttention));
    }

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    public function setFilter(string $filter): void
    {
        // Two options only. A hidden four-way filter that persisted in the URL is how a joiner
        // lost sight of every upcoming trip with nothing on screen to explain it.
        $this->filter = $filter === 'past' ? 'past' : 'upcoming';
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['calendar', 'grid', 'list'], true) ? $view : 'calendar';
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->cursor()->addMonthsNoOverflow($by)->format('Y-m');
        $this->selectedDate = '';
    }

    public function today(): void
    {
        $this->month = now()->format('Y-m');
        $this->selectedDate = '';
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    /**
     * First day of the month being viewed.
     *
     * The `!` is load-bearing: without it Carbon fills unparsed fields from today, so parsing
     * "2026-06" on a 31st yields 31 June and overflows into July. The host calendar shipped
     * that bug and showed the wrong month for a few days every month.
     */
    private function cursor(): \Illuminate\Support\Carbon
    {
        try {
            return \Illuminate\Support\Carbon::createFromFormat('!Y-m', $this->month)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    private function holdWording(Reservation $r): string
    {
        if (! $r->expires_at) {
            return 'Your host is waiting on payment';
        }

        if ($r->expires_at->isPast()) {
            return 'This hold has lapsed — pay now to see if the seat is still free';
        }

        return 'Your seat is held for another '
            .$r->expires_at->diffForHumans(['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]);
    }

    /**
     * Grouped attention items keyed by kind.
     *
     * @return array<string, array<int, array{kind:string, urgent:bool, title:string, detail:string, cta:string, href:string, reservation_id:int}>>
     */
    private function attention(Collection $reservations, Collection $reviewedTripIds): array
    {
        $groups = [];

        foreach ($reservations as $r) {
            $trip = $r->departure?->trip;

            if (! $trip) {
                continue;
            }

            $link = route('reservations.show', ['token' => $r->public_token]);

            if (in_array($r->status, ['pending', 'awaiting_payment'], true)) {
                $groups['pay'][] = [
                    'kind' => 'pay',
                    'urgent' => true,
                    'title' => 'Pay for '.$trip->title,
                    'detail' => $this->holdWording($r),
                    'cta' => 'Pay now',
                    'href' => $link,
                    'reservation_id' => $r->id,
                ];

                continue;
            }

            if ($r->status === 'confirmed') {
                $missing = $r->incompleteParticipants();

                if ($missing->isNotEmpty()) {
                    $groups['details'][] = [
                        'kind' => 'details',
                        'urgent' => false,
                        'title' => $missing->count().' of '.$r->participants->count().' travellers still need details',
                        'detail' => $trip->title.' — your host needs each name and birthday for permits',
                        'cta' => 'Add them',
                        'href' => $link,
                        'reservation_id' => $r->id,
                    ];
                }

                if ($r->owesBalance()) {
                    $money = $r->money();
                    $groups['balance'][] = [
                        'kind' => 'balance',
                        'urgent' => false,
                        'title' => 'Balance due on '.$trip->title,
                        'detail' => number_format($money['due']).' to settle with your host on the day',
                        'cta' => 'See booking',
                        'href' => $link,
                        'reservation_id' => $r->id,
                    ];
                }
            }
        }

        foreach ($reservations as $r) {
            $trip = $r->departure?->trip;

            if (! $trip || $r->status !== 'confirmed' || $r->departure->status !== 'completed') {
                continue;
            }

            if ($reviewedTripIds->contains($trip->id)) {
                continue;
            }

            $groups['review'][] = [
                'kind' => 'review',
                'urgent' => false,
                'title' => 'How was '.$trip->title.'?',
                'detail' => 'Your review helps the next group choose',
                'cta' => 'Leave a review',
                'href' => route('trips.show', $trip),
                'reservation_id' => $r->id,
            ];
        }

        return $groups;
    }

    public function render()
    {
        $user = Auth::user();

        $reservations = Reservation::where('joiner_id', $user->id)
            ->where('status', '!=', 'cancelled')
            // priceTiers and place are both load-bearing: owesBalance() -> money() ->
            // current_price -> currentTier() reads $trip->priceTiers, and every card's
            // cover image calls $trip->cover_url -> StockPhotos::cover() which reads
            // $trip->place. Without either, every booking costs one extra query and the
            // page degrades linearly with how much someone travels.
            ->with(['departure.trip.host', 'departure.trip.priceTiers', 'departure.trip.place', 'participants'])
            ->get()
            ->filter(fn (Reservation $r) => $r->departure !== null);

        $reviewedTripIds = Review::where('joiner_id', $user->id)
            ->where('reviewable_type', Trip::class)
            ->pluck('reviewable_id');

        $attention = $this->attention($reservations, $reviewedTripIds);

        $reservations = $reservations->sortBy(fn (Reservation $r) => $r->departure->starts_at)->values();

        $actionKinds = ['pay', 'details', 'balance'];
        $needsAction = collect($attention)
            ->filter(fn ($items) => in_array(key($attention) ?? '', $actionKinds))
            ->flatten(1)
            ->values();

        $upcoming = $reservations->filter(fn (Reservation $r) => ! $r->departure->starts_at->isPast())->values();
        $past = $reservations->filter(fn (Reservation $r) => $r->departure->starts_at->isPast())->values();

        $filtered = $this->filter === 'past' ? $past : $upcoming;

        // Always by date. A trip is an event; nothing else is a meaningful order for six rows.
        $sorted = $filtered->sortBy(fn (Reservation $r) => $r->departure->starts_at);

        $orphaned = Reservation::whereNull('joiner_id')
            ->where('contact_email', $user->email)
            ->whereNotIn('status', ['cancelled'])
            ->with('departure.trip')
            ->get();

        /*
         * Only computed for an empty dashboard, which is the only place it renders. This is a
         * four-join correlated subquery; running it for every joiner who already has bookings
         * costs a real query and shows nobody anything.
         */
        $recommended = $reservations->isEmpty()
            ? Trip::published()
                ->whereHas('place', fn ($p) => $p->whereIn('category', function ($q) use ($user) {
                    $q->select('category')->from('places')
                        ->join('trip_destinations', 'places.id', '=', 'trip_destinations.place_id')
                        ->join('trips', 'trip_destinations.trip_id', '=', 'trips.id')
                        ->join('trip_departures', 'trips.id', '=', 'trip_departures.trip_id')
                        ->join('reservations', 'trip_departures.id', '=', 'reservations.trip_departure_id')
                        ->where('reservations.joiner_id', $user->id)
                        ->where('reservations.status', '!=', 'cancelled');
                }))
                ->with('place', 'host.hostProfile')
                ->latest()
                ->take(3)
                ->get()
            : collect();

        /*
         * Calendar data.
         *
         * Built from every booking, not the filtered set: a month grid that hid trips because a
         * filter chip was set would be lying about where you are going. The grid renders whole
         * weeks from Sunday, so it spills into the neighbouring months.
         */
        $cursor = $this->cursor();
        // Sunday-first, which is how a Philippine wall calendar reads.
        $gridStart = $cursor->copy()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
        $gridEnd = $cursor->copy()->endOfMonth()->endOfWeek(\Illuminate\Support\Carbon::SATURDAY);

        $byDate = $reservations->groupBy(fn (Reservation $r) => $r->departure->starts_at->toDateString());

        // Default the phone's day list to the soonest day in view that has something on it.
        $daysWithTrips = $byDate->keys()
            ->filter(fn ($d) => $d >= $gridStart->toDateString() && $d <= $gridEnd->toDateString())
            ->sort()
            ->values();

        /*
         * Only default when nothing has been chosen. The old condition also reset whenever the
         * selected day held no booking, which made picking a free square impossible: a free day
         * is by definition absent from $byDate, so every such click was overwritten on render.
         * shiftMonth() clears the selection, so a stale cross-month value cannot linger.
         */
        if ($this->selectedDate === '') {
            $this->selectedDate = $daysWithTrips->first(fn ($d) => $d >= now()->toDateString())
                ?? $daysWithTrips->last()
                ?? '';
        }

        // Holidays for the visible range. Fails silently — a planning hint must never be able to
        // stop someone reaching their own bookings.
        $holidays = app(\App\Services\PhilippineHolidays::class)->between($gridStart, $gridEnd);

        $days = collect();
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $iso = $day->toDateString();
            $days->push([
                'date' => $day->copy(),
                'inMonth' => $day->month === $cursor->month,
                'isToday' => $day->isToday(),
                'isWeekend' => $day->isWeekend(),
                'holiday' => $holidays[$iso] ?? null,
                'bookings' => $byDate->get($iso, collect()),
            ]);
        }

        /*
         * What is on offer on the tapped day. Clicking an empty square should answer "could I go
         * somewhere on this date?" rather than doing nothing.
         */
        $availableOnDate = collect();

        if ($this->selectedDate !== '' && $byDate->get($this->selectedDate, collect())->isEmpty()) {
            $availableOnDate = \App\Models\TripDeparture::query()
                ->whereDate('starts_at', $this->selectedDate)
                ->where('status', 'open')
                ->whereHas('trip', fn ($t) => $t->where('status', 'published'))
                ->with('trip.host')
                ->get()
                // Excludes anything already booked, and anything with no seat left to sell.
                ->reject(fn ($d) => $reservations->contains(fn (Reservation $r) => $r->trip_departure_id === $d->id))
                ->filter(fn ($d) => $d->spotsLeft() > 0)
                ->take(6)
                ->values();
        }

        // Card view groups by month so a long list reads as a year rather than a wall of rows.
        $byMonth = $sorted->values()->groupBy(fn (Reservation $r) => $r->departure->starts_at->format('F Y'));

        return view('livewire.joiner.dashboard', [
            'attention' => $attention,
            'needsAction' => $needsAction,
            'upcoming' => $upcoming,
            'past' => $past,
            'filtered' => $sorted->values(),
            'byMonth' => $byMonth,
            'cursor' => $cursor,
            'weeks' => $days->chunk(7),
            'selectedBookings' => $byDate->get($this->selectedDate, collect()),
            'availableOnDate' => $availableOnDate,
            'selectedHoliday' => $holidays[$this->selectedDate] ?? null,
            'monthCount' => $byDate->flatten()->filter(
                fn (Reservation $r) => $r->departure->starts_at->isSameMonth($cursor)
            )->count(),
            'orphaned' => $orphaned,
            'recommended' => $recommended,
            'hasAnyBookings' => $reservations->isNotEmpty(),
        ])->layout('components.layouts.site');
    }
}
