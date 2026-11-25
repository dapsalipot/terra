<?php

namespace App\Livewire\Hosts;

use App\Livewire\Concerns\FollowsHosts;
use App\Models\Trip;
use App\Models\User;
use App\Support\HostCards;
use App\Support\PlaceCategories;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use FollowsHosts, WithPagination;

    /** In the URL so a filtered directory can be linked and shared. */
    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $sort = 'featured';

    /** The only orderings offered. Anything else falls back rather than reaching the query. */
    public const SORTS = [
        'featured' => 'Featured',
        'rating' => 'Top rated',
        'trips' => 'Most trips',
        'followers' => 'Most followed',
        'newest' => 'Newest',
    ];

    public function updatedSearch(): void
    {
        // Narrowing the list while on page 5 lands on a page that no longer exists.
        $this->resetPage();
    }

    public function setCategory(string $key): void
    {
        $this->resetPage();

        // Tapping the active chip clears it: a filter you cannot switch off is a trap, and
        // there is no other affordance for "show me everyone" once one is set.
        $this->category = ($this->category === $key || ! isset(PlaceCategories::CATALOG[$key])) ? '' : $key;
    }

    public function setSort(string $sort): void
    {
        $this->resetPage();

        $this->sort = isset(self::SORTS[$sort]) ? $sort : 'featured';
    }

    public function clearFilters(): void
    {
        $this->resetPage();
        $this->search = '';
        $this->category = '';
        $this->sort = 'featured';
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->category !== '' || $this->sort !== 'featured';
    }

    public function render()
    {
        $viewer = Auth::user();

        /*
         * Sorting reads host_profiles columns, so the profile is joined rather than eager-loaded
         * for ordering. leftJoin, not join: a host who has not filled in a profile yet still
         * belongs in the directory — an inner join would quietly delete them from it.
         */
        $query = User::query()
            ->select('users.*')
            ->leftJoin('host_profiles', 'host_profiles.user_id', '=', 'users.id')
            ->where('users.role', 'host')
            ->whereNotNull('users.handle')
            ->with('hostProfile')
            ->withCount(['trips as published_trips' => fn ($t) => $t->listedFor($viewer)]);

        if ($this->search !== '') {
            $term = '%'.str_replace('%', '\%', $this->search).'%';

            $query->where(function ($w) use ($term) {
                $w->where('users.name', 'like', $term)
                    ->orWhere('users.handle', 'like', $term)
                    ->orWhere('host_profiles.tagline', 'like', $term)
                    ->orWhere('host_profiles.location', 'like', $term)
                    ->orWhere('host_profiles.bio', 'like', $term);
            });
        }

        if (isset(PlaceCategories::CATALOG[$this->category])) {
            $query->whereHas('trips', fn ($t) => $t->listedFor($viewer)
                ->whereHas('place', fn ($p) => $p->where('category', $this->category)));
        }

        /*
         * `rating IS NULL` first in every rating ordering: without it an unrated host sorts as
         * though they scored zero on MySQL and as though they scored top on SQLite, so the same
         * page would order differently in tests and in production.
         */
        match ($this->sort) {
            'rating' => $query->orderByRaw('host_profiles.rating IS NULL, host_profiles.rating DESC'),
            'trips' => $query->orderByDesc('published_trips'),
            'followers' => $query->orderByDesc('host_profiles.followers_count'),
            'newest' => $query->orderByDesc('users.created_at'),
            default => $query->orderByRaw('host_profiles.rating IS NULL, host_profiles.rating DESC')
                ->orderByDesc('host_profiles.followers_count'),
        };

        // 24 to a page: the grid is four across at 2xl, so every page fills its last row.
        $hosts = $query->orderBy('users.name')->paginate(24);

        /*
         * What each host actually runs, and what they have coming up.
         *
         * Grouped queries for the whole page rather than per card — the cards show all of it, and
         * asking per host is the N+1 that turns a directory into a hundred queries.
         */
        $ids = $hosts->pluck('id');

        $categoriesByHost = Trip::listedFor($viewer)
            ->join('places', 'places.id', '=', 'trips.place_id')
            ->whereIn('trips.host_id', $ids)
            ->get(['trips.host_id', 'places.category'])
            ->groupBy('host_id')
            ->map(fn ($rows) => PlaceCategories::resolve($rows->pluck('category')));

        // The next departure, review count and follow state each card shows, in one query apiece.
        $hostExtras = HostCards::extras($ids, $viewer);

        return view('livewire.hosts.index', [
            'hosts' => $hosts,
            'categoriesByHost' => $categoriesByHost,
            'hostExtras' => $hostExtras,
            'total' => User::where('role', 'host')->whereNotNull('handle')->count(),
        ])->layout('components.layouts.site', ['title' => 'Tarana · Hosts']);
    }
}
