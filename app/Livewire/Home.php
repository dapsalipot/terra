<?php

namespace App\Livewire;

use App\Livewire\Concerns\FollowsHosts;
use App\Models\Trip;
use App\Models\User;
use App\Support\HostCards;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Home extends Component
{
    use FollowsHosts;

    public function render()
    {
        $viewer = Auth::user();

        // One tile per offering, not one per date, soonest first. The cap keeps the page's own query bounded as
        // the catalogue grows; the rows below show a handful each, and "show all" is the way to the rest.
        $all = Trip::collapseSchedules(
            Trip::listedFor($viewer)->with(['place', 'host.hostProfile', 'departures'])->latest()->limit(120)->get()
        );

        $soon = $all->take(8);

        // A row per kind of trip, but only for kinds with enough trips to fill one: a row of two reads as empty.
        $rows = $all
            ->groupBy(fn ($trip) => $trip->place?->category)
            ->filter(fn ($group, $category) => $category && $group->count() >= 4)
            ->map->take(10)
            ->take(2);

        // The same ordering as the hosts directory's "featured" sort, limited to hosts who have something to join.
        $listed = fn ($q) => $q->listedFor($viewer);
        $hosts = User::query()
            ->select('users.*')
            ->leftJoin('host_profiles', 'host_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'host')
            ->whereNotNull('users.handle')
            ->whereHas('trips', $listed)
            ->with('hostProfile')
            ->withCount(['trips as published_trips' => $listed])
            ->orderByRaw('host_profiles.rating IS NULL, host_profiles.rating DESC')
            ->orderByDesc('host_profiles.followers_count')
            ->orderBy('users.name')
            ->limit(8)
            ->get();

        $stats = [
            'hosts' => User::where('role', 'host')->count(),
            // Listed to everyone, not to this viewer: the headline reads the same whoever is looking.
            'trips' => Trip::listed()->count(),
        ];

        // Trip types that actually have trips this viewer can find, with counts, for the type strip.
        $tripTypes = Trip::listedFor($viewer)
            ->join('places', 'places.id', '=', 'trips.place_id')
            ->selectRaw('places.category, count(*) as total')
            ->groupBy('places.category')
            ->orderByDesc('total')
            ->pluck('total', 'places.category');

        $hostExtras = HostCards::extras($hosts->pluck('id'), $viewer);

        return view('livewire.home', compact('soon', 'rows', 'hosts', 'hostExtras', 'stats', 'tripTypes'))
            ->layout('components.layouts.site');
    }
}
