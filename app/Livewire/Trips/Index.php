<?php

namespace App\Livewire\Trips;

use App\Models\Place;
use App\Models\Trip;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $country = '';

    #[Url]
    public string $difficulty = '';

    #[Url]
    public string $category = '';

    public function render()
    {
        $trips = Trip::published()
            ->with(['place', 'host.hostProfile', 'inclusionItems'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$this->search}%")
                ->orWhereHas('place', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
            ->when($this->country, fn ($q) => $q->whereHas('place', fn ($p) => $p->where('country', $this->country)))
            ->when($this->difficulty, fn ($q) => $q->whereHas('place', fn ($p) => $p->where('difficulty', $this->difficulty)))
            ->when($this->category, fn ($q) => $q->whereHas('place', fn ($p) => $p->where('category', $this->category)))
            ->with('departures')
            ->latest()
            ->get();

        // a search for "pulag" should return the trip once, whatever its date count
        $trips = Trip::collapseSchedules($trips);

        $countries = Place::query()->distinct()->orderBy('country')->pluck('country');
        $categories = Place::query()->distinct()->orderBy('category')->pluck('category');

        return view('livewire.trips.index', compact('trips', 'countries', 'categories'))
            ->layout('components.layouts.site');
    }
}
