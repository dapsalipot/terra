<?php

namespace App\Livewire\Admin;

use App\Models\Reservation;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use App\Services\ModerationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The moderator's desk.
 *
 * One page rather than a section, because the platform is small and the three things staff
 * actually need — take a trip out of discovery, suspend a host, hide a review — are three
 * buttons, not three screens.
 */
class Dashboard extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $tab = 'trips';   // trips | hosts | reviews

    public const TABS = ['trips' => 'Trips', 'hosts' => 'Hosts', 'reviews' => 'Reviews'];

    public function setTab(string $tab): void
    {
        // Each tab is its own list; carrying page 3 across from another one lands on nothing.
        $this->resetPage();

        $this->tab = isset(self::TABS[$tab]) ? $tab : 'trips';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function unpublish(int $tripId, ModerationService $moderation): void
    {
        $moderation->unpublish(Trip::findOrFail($tripId));
        $this->dispatch('notify', 'Trip taken out of discovery.');
    }

    public function republish(int $tripId, ModerationService $moderation): void
    {
        $moderation->republish(Trip::findOrFail($tripId));
        $this->dispatch('notify', 'Trip is public again.');
    }

    public function suspend(int $userId, ModerationService $moderation): void
    {
        $user = User::findOrFail($userId);

        // Staff cannot suspend staff from here; that is an account decision, not moderation.
        abort_if($user->isAdmin(), 403);

        $moderation->suspend($user, null, Auth::user());
        $this->dispatch('notify', $user->name.' suspended.');
    }

    public function unsuspend(int $userId, ModerationService $moderation): void
    {
        $moderation->restore(User::findOrFail($userId));
        $this->dispatch('notify', 'Suspension lifted.');
    }

    public function hideReview(int $reviewId, ModerationService $moderation): void
    {
        $moderation->hideReview(Review::findOrFail($reviewId), null, Auth::user());
        $this->dispatch('notify', 'Review hidden.');
    }

    public function unhideReview(int $reviewId, ModerationService $moderation): void
    {
        $moderation->unhideReview(Review::findOrFail($reviewId));
        $this->dispatch('notify', 'Review restored.');
    }

    public function render()
    {
        $term = trim($this->search);
        $like = '%'.str_replace('%', '\%', $term).'%';

        return view('livewire.admin.dashboard', [
            'counts' => [
                'trips' => Trip::where('status', 'published')->count(),
                'hosts' => User::where('role', 'host')->count(),
                'joiners' => User::where('role', 'joiner')->count(),
                'bookings' => Reservation::whereNot('status', 'cancelled')->count(),
                'suspended' => User::whereNotNull('suspended_at')->count(),
                'hidden' => Review::whereNotNull('hidden_at')->count(),
            ],
            'trips' => $this->tab !== 'trips' ? new LengthAwarePaginator([], 0, 25) : Trip::query()
                ->when($term !== '', fn ($q) => $q->where('title', 'like', $like))
                ->with('host')
                ->latest()
                ->paginate(25),
            'hosts' => $this->tab !== 'hosts' ? new LengthAwarePaginator([], 0, 25) : User::query()
                ->where('role', 'host')
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->withCount('trips')
                ->latest()
                ->paginate(25),
            'reviews' => $this->tab !== 'reviews' ? new LengthAwarePaginator([], 0, 25) : Review::query()
                ->when($term !== '', fn ($q) => $q->where('body', 'like', $like))
                ->with('joiner')
                ->latest()
                ->paginate(25),
        ])->layout('components.layouts.site', ['title' => 'Terra · Admin', 'tabbar' => false]);
    }
}
