<?php

namespace App\Livewire\Host;

use App\Models\Reservation;
use App\Models\TripDeparture;
use App\Support\DaySpan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    /** The month on screen, as YYYY-MM. In the URL so a host can link to a month. */
    #[Url]
    public string $month = '';

    /** 'grid' or 'list'. In the URL so a host's preference survives a reload. */
    #[Url]
    public string $view = 'grid';

    public function setView(string $view): void
    {
        $this->view = $view === 'list' ? 'list' : 'grid';
    }

    /**
     * Which day the phone-sized calendar is showing trips for.
     *
     * A 375px screen gives each column about 48 pixels — enough for a dot, nowhere near
     * enough for a trip title. So on a phone the grid marks which days have trips and
     * this holds the one whose trips are listed underneath.
     */
    public string $selectedDate = '';

    public function selectDate(string $date): void
    {
        // Tapping only ever selects. An empty value means "nothing chosen yet", which
        // render() refills with the soonest day — so a toggle-off could never stick, and
        // no calendar lets you deselect a day anyway.
        $this->selectedDate = $date;
    }

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
    }

    public function shiftMonth(int $by): void
    {
        $this->month = $this->cursor()->addMonths($by)->format('Y-m');
    }

    public function today(): void
    {
        $this->month = now()->format('Y-m');
    }

    /** First day of the month being viewed. Falls back to today on a malformed URL. */
    private function cursor(): Carbon
    {
        try {
            // The `!` matters: without it Carbon fills unparsed fields from today, so on the
            // 31st "2026-06" parses as 31 June and overflows to 1 July — the dashboard would
            // silently show the wrong month for any short month, a few days every month.
            return Carbon::createFromFormat('!Y-m', $this->month)->startOfMonth();
        } catch (\Throwable) {
            return now()->startOfMonth();
        }
    }

    public function render()
    {
        $host = Auth::user();
        $tripIds = $host->trips()->pluck('id');
        $departureIds = TripDeparture::whereIn('trip_id', $tripIds)->pluck('id');

        $cursor = $this->cursor();

        // The grid shows whole weeks, so it spills into the neighbouring months.
        $gridStart = $cursor->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        /*
         * Overlapping, not starting-inside: a traverse that left on the 30th of last month and
         * comes back on the 2nd of this one belongs in both grids. Filtering on `starts_at`
         * alone made the days a host was actually away look free.
         */
        $departures = DaySpan::group(
            TripDeparture::whereIn('trip_id', $tripIds)
                ->overlapping($gridStart, $gridEnd)
                ->with(['trip.place', 'reservations'])
                ->orderBy('starts_at')
                ->get(),
            fn (TripDeparture $d) => [$d->starts_at, $d->ends_at],
            $gridStart,
            $gridEnd,
        );

        // Default to the soonest day in this month that actually has something on it.
        $withTrips = $departures->keys()->sort()->values();

        if ($this->selectedDate === '' || ! $departures->has($this->selectedDate)) {
            $this->selectedDate = $withTrips->first(fn ($d) => $d >= now()->toDateString())
                ?? $withTrips->last()
                ?? '';
        }

        $days = collect();
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $days->push([
                'date' => $day->copy(),
                'inMonth' => $day->month === $cursor->month,
                'isToday' => $day->isToday(),
                'departures' => $departures->get($day->toDateString(), collect()),
            ]);
        }

        return view('livewire.host.dashboard', [
            'host' => $host,
            'cursor' => $cursor,
            'weeks' => $days->chunk(7),
            // Unique: a multi-day departure sits in several day buckets, and the header counts
            // trips, not trip-days.
            'monthDepartures' => $departures->flatten()->unique('id')->values(),
            'isList' => $this->view === 'list',
            'selectedDepartures' => $departures->get($this->selectedDate, collect()),
            'toConfirm' => Reservation::whereIn('trip_departure_id', $departureIds)
                ->where('status', 'payment_submitted')->count(),
            'awaitingPayment' => Reservation::whereIn('trip_departure_id', $departureIds)
                ->whereIn('status', ['pending', 'awaiting_payment'])->count(),
            'confirmedGuests' => (int) Reservation::whereIn('trip_departure_id', $departureIds)
                ->where('status', 'confirmed')->sum('party_size'),
            'nextUp' => TripDeparture::whereIn('trip_id', $tripIds)
                ->where('starts_at', '>', now())
                ->with('trip')
                ->orderBy('starts_at')
                ->take(3)
                ->get(),
        ])->layout('components.layouts.host', ['title' => 'Terra · Dashboard']);
    }
}
