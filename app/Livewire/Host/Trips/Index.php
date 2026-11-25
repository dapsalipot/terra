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

    /**
     * Who can find every schedule of one offering, chosen from the menu in its header.
     *
     * `$group` is the key the page already groups by (a trip group, or `solo-{id}` for a trip with no group) and
     * `$choice` is `public`, `link`, or `followers:` plus a key of Visibility::WINDOWS. The trips are looked up
     * among the signed-in host's own, so another host's key matches nothing, and a choice the menu does not
     * offer changes nothing. One UPDATE writes one opening date, so the schedules cannot disagree on it.
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

        Trip::where('host_id', Auth::id())
            ->when(
                str_starts_with($group, 'solo-'),
                fn ($q) => $q->whereKey((int) substr($group, 5)),
                fn ($q) => $q->where('trip_group_id', $group),
            )
            ->update([
                'visibility' => $visibility,
                'public_at' => $visibility === Visibility::FOLLOWERS ? Visibility::opensAt($window) : null,
            ]);
    }

    public function render()
    {
        $trips = Trip::where('host_id', Auth::id())
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
            ->sortBy(fn ($t) => $t->departure?->starts_at ?? $t->created_at)
            ->when($this->filter === 'upcoming', fn ($c) => $c->filter(fn ($t) => $t->departure?->starts_at?->isFuture()))
            ->when($this->filter === 'past', fn ($c) => $c->filter(fn ($t) => $t->departure?->starts_at?->isPast()));

        return view('livewire.host.trips.index', [
            // Trips published together are one offering with several schedules, so they
            // group under a single heading rather than repeating identically down the page.
            'groups' => $trips->groupBy(fn ($t) => $t->trip_group_id ?: 'solo-'.$t->id),
            'counts' => [
                'upcoming' => Trip::where('host_id', Auth::id())->whereHas('departures', fn ($q) => $q->where('starts_at', '>', now()))->count(),
                'past' => Trip::where('host_id', Auth::id())->whereHas('departures', fn ($q) => $q->where('starts_at', '<=', now()))->count(),
                'all' => Trip::where('host_id', Auth::id())->count(),
            ],
        ])->layout('components.layouts.host', ['title' => 'Tarana · My trips']);
    }
}
