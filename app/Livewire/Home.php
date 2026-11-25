<?php

namespace App\Livewire;

use App\Models\Trip;
use App\Models\User;
use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        // One tile per offering, not one per date, soonest first. The cap keeps the page's own query bounded as
        // the catalogue grows; the rows below show a handful each, and "show all" is the way to the rest.
        $all = Trip::collapseSchedules(
            Trip::published()->with(['place', 'host.hostProfile', 'departures'])->latest()->limit(120)->get()
        );

        $soon = $all->take(8);

        // A row per kind of trip, but only for kinds with enough trips to fill one: a row of two reads as empty.
        $rows = $all
            ->groupBy(fn ($trip) => $trip->place?->category)
            ->filter(fn ($group, $category) => $category && $group->count() >= 4)
            ->map->take(10)
            ->take(2);

        // The same ordering as the hosts directory's "featured" sort, limited to hosts who have something to join.
        $published = fn ($q) => $q->where('status', 'published');
        $hosts = User::query()
            ->select('users.*')
            ->leftJoin('host_profiles', 'host_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'host')
            ->whereNotNull('users.handle')
            ->whereHas('trips', $published)
            ->with('hostProfile')
            ->withCount(['trips as published_trips' => $published])
            ->orderByRaw('host_profiles.rating IS NULL, host_profiles.rating DESC')
            ->orderByDesc('host_profiles.followers_count')
            ->orderBy('users.name')
            ->limit(8)
            ->get();

        $stats = [
            'hosts' => User::where('role', 'host')->count(),
            'trips' => Trip::published()->count(),
        ];

        // Trip types that actually have published trips, with counts, for the type strip.
        $tripTypes = Trip::published()
            ->join('places', 'places.id', '=', 'trips.place_id')
            ->selectRaw('places.category, count(*) as total')
            ->groupBy('places.category')
            ->orderByDesc('total')
            ->pluck('total', 'places.category');

        return view('livewire.home', compact('soon', 'rows', 'hosts', 'stats', 'tripTypes'))
            ->layout('components.layouts.site');
    }
}
