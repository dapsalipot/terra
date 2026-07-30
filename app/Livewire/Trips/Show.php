<?php

namespace App\Livewire\Trips;

use App\Models\Review;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Services\ReviewGate;
use App\Services\ReviewService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public Trip $trip;
    public ?int $departureId = null;
    public int $party = 1;
    public ?string $error = null;

    // review composer
    public bool $showReview = false;
    public int $tripRating = 5;
    public int $hostRating = 5;
    public int $placeRating = 5;
    public string $reviewBody = '';

    public function mount(Trip $trip): void
    {
        abort_unless($trip->status === 'published', 404);
        $this->trip = $trip->load(['host.hostProfile', 'place', 'openDepartures', 'meetups', 'inclusionItems', 'destinations.place', 'photos']);
        $this->departureId = $this->trip->openDepartures->first()?->id;
    }

    /** The trip page no longer books directly — registration collects who is travelling. */
    public function reserve()
    {
        // Answered here so a host gets a reason at the click point rather than a silent bounce
        // off the join page. Register::mount() is the authoritative guard for direct URL hits.
        if (Auth::user()?->isHost()) {
            $this->error = 'Host accounts cannot join trips. Sign up as a joiner to reserve.';

            return null;
        }

        return $this->redirectRoute('trips.join', ['trip' => $this->trip], navigate: true);
    }

    public function submitReview(ReviewService $service): void
    {
        $service->record(Auth::user(), $this->trip, [
            'trip' => $this->tripRating,
            'host' => $this->hostRating,
            'place' => $this->placeRating,
        ], trim($this->reviewBody) ?: null);

        $this->reset('showReview', 'reviewBody');
        $this->trip->refresh();
    }

    public function getCanReviewProperty(): bool
    {
        return Auth::check()
            && ! Auth::user()->isHost()
            && app(ReviewGate::class)->allows(Auth::user(), $this->trip);
    }

    public function render()
    {
        $reviews = Review::where('reviewable_type', Trip::class)
            ->where('reviewable_id', $this->trip->id)
            ->with('joiner')
            ->latest()
            ->get();

        return view('livewire.trips.show', ['reviews' => $reviews])
            ->layout('components.layouts.site');
    }
}
