<?php

namespace App\Livewire\Joiner;

use App\Mail\MagicLinkMail;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Services\MagicLinkService;
use App\Services\PhilippineHolidays;
use App\Services\ReservationService;
use App\Support\DaySpan;
use App\Support\PlaceCategories;
use App\Support\TrailStats;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Url;
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

    /**
     * Which panel of the right-hand column is showing.
     *
     * In the URL so "the thing I still owe money on" is a linkable place rather than a state you
     * have to re-find. Blank means "whichever has something in it" — resolved in render() against
     * the panels that actually exist, because a remembered tab can empty out from under you.
     */
    #[Url]
    public string $panel = '';

    /** Which day the phone calendar is listing. Not in the URL — it is a transient tap. */
    public string $selectedDate = '';

    /**
     * Whether the day sheet is open.
     *
     * Separate from $selectedDate because render() seeds that with the joiner's next booked day so
     * the grid has something highlighted. If the sheet keyed off the date alone it would fly open
     * on page load, before anyone had clicked anything.
     */
    public bool $dayOpen = false;

    public array $dismissedAttention = [];

    /**
     * Kinds a joiner may hide.
     *
     * Anything time-sensitive is deliberately absent. An unpaid hold expires and releases the
     * seat, so a control that hides it is an affordance that costs someone their place on a trip.
     */
    private const DISMISSIBLE = ['review'];

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
        // Guarded server-side, not just by hiding the button: the method is reachable from the
        // browser regardless of what the markup renders.
        if (! in_array($kind, self::DISMISSIBLE, true)) {
            return;
        }

        $this->dismissedAttention = array_values(array_unique([...$this->dismissedAttention, $kind]));

        session()->put($this->dismissKey(), $this->dismissedAttention);
    }

    /**
     * Session, not a column: a dismissal is a per-visit preference rather than a fact about the
     * booking, and the review nudge disappears for good once the review is actually written.
     *
     * Keyed per user so one account's dismissal cannot hide another's nudge on a shared browser.
     */
    private function dismissKey(): string
    {
        return 'joiner-dismissed.'.Auth::id();
    }

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');

        // #[Url] hydration writes the raw query value straight onto the property via
        // data_set(), bypassing setFilter()/setView() entirely — neither has an enum type to
        // coerce it. A stray ?filter=action or ?view=xyz left over from before this task (or a
        // hand-edited link) would leave every tab/view button showing unselected, which is
        // exactly the invisible state this task exists to remove. SupportAttributes hydrates
        // before SupportLifecycleHooks runs mount(), so re-running each value through its own
        // setter here catches it before render() — one call per #[Url] property, using the same
        // single definition of validity the property's own setter already has.
        $this->setFilter($this->filter);
        $this->setView($this->view);
        $this->setPanel($this->panel);

        $this->dismissedAttention = session($this->dismissKey(), []);
    }

    /** Filled in only once somebody asks for it. */
    public ?string $calendarUrl = null;

    /**
     * Mint the subscription token on demand.
     *
     * Deliberately not in render(): minting writes a row, and most joiners never subscribe to
     * anything — every one of them would have paid for a secret they never use, on every page
     * load. A click is the honest trigger.
     */
    public function revealCalendar(): void
    {
        $this->calendarUrl = route('calendar.feed', ['token' => Auth::user()->calendarToken()]);
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

    /**
     * Only names a panel; whether it exists is render()'s call.
     *
     * Validating against the four keys here rather than against what is currently on screen keeps
     * a linked URL working: ?panel=review is a reasonable thing to send someone even on a render
     * where they have nothing to review, and it costs nothing to fall back gracefully.
     */
    public function setPanel(string $panel): void
    {
        $this->panel = in_array($panel, ['next', 'pay', 'confirm', 'review'], true) ? $panel : '';
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->cursor()->addMonthsNoOverflow($by)->format('Y-m');
        $this->selectedDate = '';
        $this->dayOpen = false;
    }

    public function today(): void
    {
        $this->month = now()->format('Y-m');
        $this->selectedDate = '';
        $this->dayOpen = false;
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->dayOpen = true;
    }

    public function closeDay(): void
    {
        $this->dayOpen = false;
    }

    /**
     * First day of the month being viewed.
     *
     * The `!` is load-bearing: without it Carbon fills unparsed fields from today, so parsing
     * "2026-06" on a 31st yields 31 June and overflows into July. The host calendar shipped
     * that bug and showed the wrong month for a few days every month.
     */
    private function cursor(): Carbon
    {
        try {
            return Carbon::createFromFormat('!Y-m', $this->month)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    /**
     * The hold sentence, in one place.
     *
     * Public because the For-confirmation cards render it directly. The alternative — repeating
     * the three branches in Blade — is two definitions of the same sentence that drift apart the
     * first time the wording is tuned.
     *
     * The wording used to end "pay now to see if the seat is still free", which was right when a
     * joiner paid to hold a place. Paying is now the one thing they cannot do here: this window
     * is the host's to answer in, not theirs to buy their way out of.
     */
    public function holdWording(Reservation $r): string
    {
        if (! $r->expires_at) {
            return 'Waiting on your host';
        }

        if ($r->expires_at->isPast()) {
            return 'This hold has lapsed — your host may have released the seat';
        }

        return 'Your seat is held for another '
            .$r->expires_at->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE])
            .' while your host decides';
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

            /*
             * A request the host has not answered yet is not a bill.
             *
             * This used to shout "Pay for X — Pay now" at anyone holding an unconfirmed booking,
             * which was true under the old pay-first order and is now a link to a page with no
             * payment form on it. Nothing is owed until a seat has actually been granted.
             */
            if (in_array($r->status, ['pending', 'awaiting_payment'], true)) {
                $groups['details'][] = [
                    'kind' => 'waiting',
                    'urgent' => false,
                    'title' => $trip->host->name.' is reviewing your request',
                    'detail' => 'You will be asked to pay once your seat on '.$trip->title.' is granted',
                    'cta' => 'See booking',
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

                /*
                 * A confirmed seat with money still on it is the real "pay now".
                 *
                 * Urgency comes from the deadline the host set rather than from the status, so a
                 * booking two days from its cut-off shouts and one with no deadline at all simply
                 * sits there. "on the day" was the old wording and is wrong now: there is a date.
                 */
                if ($r->owesBalance()) {
                    $money = $r->money();
                    $due = $r->nextPaymentDue();
                    $overdue = $r->paymentOverdue();

                    $groups['pay'][] = [
                        'kind' => 'pay',
                        'urgent' => $overdue || ($due !== null && $due->isBefore(now()->addDays(3))),
                        'title' => ($overdue ? $r->overdueLabel().' — ' : '').'Pay for '.$trip->title,
                        'detail' => number_format($money['due']).' owed'.match (true) {
                            $due === null => ' — no deadline set',
                            $overdue => ', due '.$due->diffForHumans(),
                            default => ', by '.$due->format('j M').' at '.$due->format('g:ia'),
                        },
                        'cta' => 'Pay now',
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
            // latestPayment joins the list because money() now reads it to decide what has
            // actually been paid; left lazy it is one query per booking on every render.
            // hikeDetails joins the list for the past summary: elevation and distance live two
            // hops away (trip -> destinations -> hike detail), and left lazy that is one query
            // per past trip on a page that already loads every booking a joiner has.
            ->with([
                'departure.trip.host', 'departure.trip.priceTiers', 'departure.trip.place',
                'departure.trip.hikeDetails', 'participants', 'latestPayment',
                // money() sums confirmed submission amounts, so the whole set is needed per booking.
                'paymentSubmissions',
            ])
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
            ? Trip::listedFor($user)
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
        $gridStart = $cursor->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        // Every day of the trip, not just the day it leaves. "When am I away?" is the question
        // this grid exists to answer, and a three-day traverse marked on one square answers it
        // wrongly on the other two.
        $byDate = DaySpan::group(
            $reservations,
            fn (Reservation $r) => [$r->departure->starts_at, $r->departure->ends_at],
            $gridStart,
            $gridEnd,
        );

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
        $holidays = app(PhilippineHolidays::class)->between($gridStart, $gridEnd);

        /*
         * What is joinable on each visible day — a count and the cover photos to show for it.
         *
         * Still one query for the whole grid, not one per day, and seats-held is still summed in
         * SQL: spotsLeft() queries per departure, so calling it in a loop would be an N+1 that
         * scales with how many trips exist rather than how many the joiner has.
         *
         * The two eager loads are the price of showing photos instead of a bare number, and they
         * are two queries for the whole grid rather than two per trip. Columns are trimmed to what
         * the cell and the cover fallback actually read: cover_url returns the host's upload when
         * there is one and otherwise picks a stock photo from the place's slug and category.
         */
        $open = TripDeparture::query()
            ->overlapping($gridStart, $gridEnd)
            ->where('status', 'open')
            ->whereHas('trip', fn ($t) => $t->where('status', 'published'))
            ->with(['trip:id,title,cover_photo,place_id', 'trip.place:id,slug,category'])
            ->withSum(
                ['reservations as seats_held' => fn ($q) => $q->whereIn('status', Reservation::HOLDING)],
                'party_size',
            )
            ->orderBy('starts_at')
            // ends_at is load-bearing, not decoration: leave it out of the column list and every
            // departure looks like a single day no matter how long it runs.
            ->get(['id', 'trip_id', 'starts_at', 'ends_at', 'capacity'])
            // Already booked is not "available", and neither is sold out.
            ->reject(fn ($d) => $reservations->contains(fn (Reservation $r) => $r->trip_departure_id === $d->id))
            ->filter(fn ($d) => $d->capacity - (int) ($d->seats_held ?? 0) > 0);

        $openByDate = DaySpan::group(
            $open,
            fn ($d) => [$d->starts_at, $d->ends_at],
            $gridStart,
            $gridEnd,
        )
            ->map(fn ($deps, $iso) => [
                'count' => $deps->count(),
                // Three is what fits a calendar square; the cell shows "+N" for the rest.
                'covers' => $deps->take(3)
                    ->map(fn ($d) => ['url' => $d->trip->cover_url, 'title' => $d->trip->title])
                    ->values()
                    ->all(),
                /*
                 * What kind of trips they are. Free — the category rides along on the place that
                 * is already loaded for the cover fallback — and it answers the question the
                 * photos raise but cannot settle: three beaches and a mountain look like four
                 * photographs until something says which is which.
                 */
                'types' => PlaceCategories::resolve(
                    $deps->map(fn ($d) => $d->trip->place?->category),
                ),
                /*
                 * Of those, how many are mid-trip rather than setting off. The cell's own label
                 * said "N trips leaving this day" for every day a trip touched, which stopped
                 * being true once a departure started appearing on its middle days.
                 */
                'continuing' => $deps->reject(fn ($d) => $d->starts_at->toDateString() === $iso)->count(),
            ]);

        $days = collect();
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $iso = $day->toDateString();
            $days->push([
                'date' => $day->copy(),
                'inMonth' => $day->month === $cursor->month,
                'isToday' => $day->isToday(),
                'isWeekend' => $day->isWeekend(),
                'holiday' => $holidays[$iso] ?? null,
                'available' => $openByDate->get($iso)['count'] ?? 0,
                'covers' => $openByDate->get($iso)['covers'] ?? [],
                'types' => $openByDate->get($iso)['types'] ?? [],
                'continuing' => $openByDate->get($iso)['continuing'] ?? 0,
                'bookings' => $byDate->get($iso, collect()),
            ]);
        }

        /*
         * What is on offer on the tapped day. Clicking an empty square should answer "could I go
         * somewhere on this date?" rather than doing nothing.
         */
        $availableOnDate = collect();
        $availableTotal = 0;
        $availableContinuing = 0;

        if ($this->selectedDate !== '' && $byDate->get($this->selectedDate, collect())->isEmpty()) {
            $offers = TripDeparture::query()
                // Overlapping the day, so tapping the middle of a multi-day trip offers it too.
                ->overlapping($this->selectedDate, $this->selectedDate)
                ->where('status', 'open')
                ->whereHas('trip', fn ($t) => $t->where('status', 'published'))
                /*
                 * place and priceTiers are loaded because the sheet shows a photo and a price:
                 * cover_url reads the place, current_price reads the tiers. Left lazy they would
                 * be two more queries per trip on the day — the same N+1 shape the grid's
                 * availability count was written to avoid.
                 */
                ->with(['trip.host', 'trip.place', 'trip.priceTiers'])
                // Seats held summed in SQL: spotsLeft() is a query per departure otherwise.
                ->withSum(
                    ['reservations as seats_held' => fn ($q) => $q->whereIn('status', Reservation::HOLDING)],
                    'party_size',
                )
                ->orderBy('starts_at')
                ->get()
                // Excludes anything already booked, and anything with no seat left to sell.
                ->reject(fn ($d) => $reservations->contains(fn (Reservation $r) => $r->trip_departure_id === $d->id))
                ->filter(fn ($d) => $d->capacity - (int) ($d->seats_held ?? 0) > 0)
                ->values();

            $availableTotal = $offers->count();
            // Capped so one busy date cannot render an unbounded list; the footer says when it did.
            $availableOnDate = $offers->take(12);

            /*
             * How many of these are already under way rather than setting off today.
             *
             * The sheet used to say "trips leaving this day" unconditionally, which stopped being
             * true the moment a multi-day trip started appearing on its middle days.
             */
            $availableContinuing = $availableOnDate
                ->reject(fn ($d) => $d->starts_at->toDateString() === $this->selectedDate)
                ->count();
        }

        /*
         * The right-hand column's own data.
         *
         * $upcoming is already sorted by departure date, so "next up" is simply its head. The
         * payment list is drawn from the same collection rather than a second query — a booking
         * that needs paying is by definition one of the trips already loaded here.
         */
        $nextUp = $upcoming->first();
        $laterUp = $upcoming->slice(1)->take(3)->values();

        /*
         * What you owe on, and what you are waiting on. Two different lists now.
         *
         * This used to be one list filtered on the unconfirmed statuses, which was right when a
         * joiner paid before the host accepted them. Since that order reversed those are exactly
         * the bookings with no payment form — a "For payment" list of things you cannot pay for.
         * Money is owed by a confirmed seat; an unconfirmed one is simply waiting on a person.
         */
        $awaitingPayment = $upcoming
            ->filter(fn (Reservation $r) => $r->canSubmitPayment() && $r->money()['due'] > 0)
            ->values();

        $forConfirmation = $upcoming
            ->filter(fn (Reservation $r) => in_array($r->status, ['pending', 'awaiting_payment', 'payment_submitted'], true))
            ->values();

        $forReview = collect($attention['review'] ?? [])
            ->reject(fn ($item) => in_array($item['kind'], $this->dismissedAttention, true))
            ->values();

        // Traveller details are still someone's move, so they ride with the money rather than
        // becoming a fifth tab nobody asked for.
        $alsoNeedsYou = collect($attention['details'] ?? [])
            ->reject(fn ($item) => in_array($item['kind'], $this->dismissedAttention, true))
            ->values();

        /*
         * The tab strip, built from what actually has something in it.
         *
         * An empty tab is a promise of content that is not there; a joiner with one upcoming trip
         * and nothing owed should see one tab, not four with three dead ends. `panels` drives both
         * the strip and which panel body renders, so they cannot disagree.
         */
        $panels = collect([
            'next' => ['label' => 'Next up', 'count' => $nextUp ? 1 + $laterUp->count() : 0],
            'pay' => ['label' => 'For payment', 'count' => $awaitingPayment->count() + $alsoNeedsYou->count(), 'urgent' => true],
            'confirm' => ['label' => 'For confirmation', 'count' => $forConfirmation->count()],
            'review' => ['label' => 'For review', 'count' => $forReview->count()],
        ])->filter(fn ($p) => $p['count'] > 0);

        /*
         * A remembered tab that has since emptied — the review you just wrote — falls back to the
         * first one with anything in it rather than rendering a panel with nothing on it.
         *
         * Deliberately NOT called `$panel`: Livewire shares public properties with the view, so a
         * local of the same name as the `$panel` property is a coin toss over which one Blade
         * sees. It lost — the view read the raw property, which is blank on a first visit, so no
         * tab was selected and the whole right-hand column rendered empty.
         */
        $activePanel = $panels->has($this->panel) ? $this->panel : (string) $panels->keys()->first();

        // Card view groups by month so a long list reads as a year rather than a wall of rows.
        $byMonth = $sorted->values()->groupBy(fn (Reservation $r) => $r->departure->starts_at->format('F Y'));

        /*
         * Where you have been, month by month, with what it added up to.
         *
         * Newest first — a history read forwards from your first trip buries this year under
         * three years of scrolling. Each month carries its own totals because "6,240 m in
         * August" is a fact about a month; the same number spread over a year says nothing.
         */
        $pastByMonth = $past
            ->sortByDesc(fn (Reservation $r) => $r->departure->starts_at)
            ->groupBy(fn (Reservation $r) => $r->departure->starts_at->format('F Y'))
            ->map(fn (Collection $rows) => [
                'rows' => $rows->values(),
                'stats' => TrailStats::summarise($rows),
            ]);

        // The whole history in one line, which is the number people actually screenshot.
        $pastTotals = TrailStats::summarise($past);

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
            'availableTotal' => $availableTotal,
            'availableContinuing' => $availableContinuing,
            'selectedHoliday' => $holidays[$this->selectedDate] ?? null,
            // unique() first: a multi-day booking now sits in several buckets, and this counts
            // trips in the month, not days spent travelling.
            'monthCount' => $byDate->flatten()->unique('id')->filter(
                fn (Reservation $r) => $r->departure->starts_at->isSameMonth($cursor)
            )->count(),
            'pastByMonth' => $pastByMonth,
            'pastTotals' => $pastTotals,
            'orphaned' => $orphaned,
            'recommended' => $recommended,
            'hasAnyBookings' => $reservations->isNotEmpty(),
            'nextUp' => $nextUp,
            'laterUp' => $laterUp,
            'awaitingPayment' => $awaitingPayment,
            'forConfirmation' => $forConfirmation,
            'forReview' => $forReview,
            'alsoNeedsYou' => $alsoNeedsYou,
            'panels' => $panels,
            'activePanel' => $activePanel,
        ])->layout('components.layouts.site');
    }
}
