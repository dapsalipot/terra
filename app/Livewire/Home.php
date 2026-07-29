<?php

namespace App\Livewire;

use App\Models\Place;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        // one card per offering, not one per date
        $trips = Trip::collapseSchedules(
            Trip::published()->with(['place', 'host.hostProfile', 'departures'])->latest()->get()
        )->take(6);
        $hosts = User::where('role', 'host')->whereNotNull('handle')->with('hostProfile')->take(4)->get();

        $reviews = Review::where('reviewable_type', Trip::class)
            ->whereNotNull('body')->with('joiner', 'reviewable')->latest()->take(3)->get();

        $stats = [
            'countries' => Place::distinct('country')->count('country'),
            'hosts' => User::where('role', 'host')->count(),
            'departures' => Trip::published()->count(),   // every schedule counts here — it is a departure count
        ];

        // Trip types that actually have published trips, with counts, ordered by popularity.
        $tripTypes = Trip::published()
            ->join('places', 'places.id', '=', 'trips.place_id')
            ->selectRaw('places.category, count(*) as total')
            ->groupBy('places.category')
            ->orderByDesc('total')
            ->pluck('total', 'places.category');

        return view('livewire.home', compact('trips', 'hosts', 'reviews', 'stats', 'tripTypes'))
            ->layout('components.layouts.site', ['transparentNav' => true]);
    }
}
