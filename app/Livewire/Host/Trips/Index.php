<?php

namespace App\Livewire\Host\Trips;

use App\Models\Reservation;
use App\Models\Trip;
use App\Support\Visibility;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $filter = 'upcoming';

    /** What a screen reader hears after a change, since the chips update and the menu resets without a word. Empty until there is one. */
    public string $announcement = '';

    /**
     * Who can find every schedule of one offering, chosen from the menu in its header and sent by its Apply button.
     *
     * `$group` is the key the page already groups by (a trip group, or `solo-{id}` for a trip with no group) and
     * `$choice` is `public`, `link`, or `followers:` plus a key of Visibility::WINDOWS. The trips are looked up
     * among the signed-in host's own, so another host's key matches nothing, and a choice the menu does not
     * offer changes nothing. One UPDATE writes one opening date, so the schedules cannot disagree on it.
     * Only a change that happened is announced, and the announcement names every schedule it wrote, whatever tab is showing.
     */
    public function setVisibility(string $group, string $choice): void
    {
        [$visibility, $window] = array_pad(explode(':', $choice, 2), 2, null);

        $offered = match ($visibility) {
            Visibility::PUBLIC, Visibility::LINK => $window === null,
            Visibility::FOLLOWERS => array_key_exists((string) $window, Visibility::WINDOWS),
            default => false,
        };

        if (! $offered) {
            return;
        }

        $trips = Trip::where('host_id', Auth::id())
            ->when(
                str_starts_with($group, 'solo-'),
                fn ($q) => $q->whereKey((int) substr($group, 5)),
                fn ($q) => $q->where('trip_group_id', $group),
            )
            ->get(['id', 'title']);

        if ($trips->isEmpty()) {
            return;
        }

        $listing = [
            'visibility' => $visibility,
            'public_at' => $visibility === Visibility::FOLLOWERS ? Visibility::opensAt($window) : null,
        ];

        Trip::whereKey($trips->modelKeys())->update($listing);

        // An unsaved copy that carries the new listing, so the chip's own wording says what was heard, as the row will show it.
        $trip = $trips->first()->forceFill($listing);
        $now = Visibility::chip($trip) ?? Visibility::CATALOG[Visibility::PUBLIC];
        $this->announcement = 'Who can see '.self::reach($trip->title, $trips->count()).": {$now}.";
    }

    /**
     * What a choice made on an offering's control reaches, in the control's own words.
     *
     * Every schedule of the offering, which is more than the tab in view may show: the Past tab lists the dates that
     * have run, and setVisibility() writes the dates still to come along with them.
     */
    private static function reach(string $title, int $schedules): string
    {
        return $schedules === 1 ? "this schedule of {$title}" : "all {$schedules} schedules of {$title}";
    }

    public function render()
    {
        $all = Trip::where('host_id', Auth::id())
            /*
             * Seats held, summed in SQL on the eager load.
             *
             * One extra query for the whole page rather than heldCount() per row, which is what
             * calling it in the loop would have cost. The row shows it because the space beside
             * the date was empty and "how full is this date" is the first thing a host looks for.
             */
            ->with([
                'departure' => fn ($q) => $q->withSum(
                    ['reservations as seats_held' => fn ($r) => $r->whereIn('status', Reservation::HOLDING)],
                    'party_size',
                ),
                'place',
                'destinations.place',
            ])
            ->withCount('reviews')
            ->get()
            ->sortBy(fn ($t) => $t->departure?->starts_at ?? $t->created_at);

        $trips = $all
            ->when($this->filter === 'upcoming', fn ($c) => $c->filter(fn ($t) => $t->departure?->starts_at?->isFuture()))
            ->when($this->filter === 'past', fn ($c) => $c->filter(fn ($t) => $t->departure?->starts_at?->isPast()));

        $groupKey = fn ($t) => $t->trip_group_id ?: 'solo-'.$t->id;
        $groups = $trips->groupBy($groupKey);
        $totals = $all->countBy($groupKey);

        return view('livewire.host.trips.index', [
            // Trips published together are one offering with several schedules, so they
            // group under a single heading rather than repeating identically down the page.
            'groups' => $groups,
            // What each offering's menu and Apply button say they reach: all of its schedules, counted before the tab
            // hid any, because that is what setVisibility() writes. The header's own count stays the tab's.
            'reach' => $groups->map(fn ($group, $key) => self::reach($group->first()->title, $totals[$key])),
            'counts' => [
                'upcoming' => Trip::where('host_id', Auth::id())->whereHas('departures', fn ($q) => $q->where('starts_at', '>', now()))->count(),
                'past' => Trip::where('host_id', Auth::id())->whereHas('departures', fn ($q) => $q->where('starts_at', '<=', now()))->count(),
                'all' => Trip::where('host_id', Auth::id())->count(),
            ],
        ])->layout('components.layouts.host', ['title' => 'Tarana · My trips']);
    }
}
