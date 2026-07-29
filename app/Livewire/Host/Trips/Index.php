<?php

namespace App\Livewire\Host\Trips;

use App\Models\Trip;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $filter = 'upcoming';

    public function render()
    {
        $trips = Trip::where('host_id', Auth::id())
            ->with(['departure', 'place', 'destinations.place'])
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
        ])->layout('components.layouts.host', ['title' => 'Peeps · My trips']);
    }
}
