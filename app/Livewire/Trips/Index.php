<?php

namespace App\Livewire\Trips;

use App\Models\Place;
use App\Models\Trip;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $country = '';

    #[Url]
    public string $difficulty = '';

    #[Url]
    public string $category = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCountry(): void
    {
        $this->resetPage();
    }

    public function updatedDifficulty(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

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

        /*
         * Collapse first, then page.
         *
         * A search for "pulag" should return the trip once whatever its date count, and that
         * grouping happens in PHP — so paginating the query would hand out pages of wildly
         * different card counts as each one collapsed by a different amount.
         *
         * The honest limit: this still reads every matching trip before slicing, so it bounds
         * what the browser renders rather than what the database returns. Right for a catalogue
         * of this size; the fix when it stops being right is to collapse in SQL and page on the
         * offering rather than the schedule.
         */
        $collapsed = Trip::collapseSchedules($trips);

        $perPage = 24;
        $page = Paginator::resolveCurrentPage();

        $trips = new LengthAwarePaginator(
            $collapsed->forPage($page, $perPage)->values(),
            $collapsed->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );

        $countries = Place::query()->distinct()->orderBy('country')->pluck('country');
        $categories = Place::query()->distinct()->orderBy('category')->pluck('category');

        return view('livewire.trips.index', compact('trips', 'countries', 'categories'))
            ->layout('components.layouts.site');
    }
}
