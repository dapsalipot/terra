<?php

namespace App\Livewire\Trips;

use App\Exceptions\TripFullException;
use App\Models\Review;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Services\ReservationService;
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

    public function reserve(ReservationService $service)
    {
        if (! Auth::check()) {
            return $this->redirectRoute('login', navigate: true);
        }

        if (Auth::user()->isHost()) {
            $this->error = 'Host accounts cannot join trips. Sign up as a joiner to reserve.';
            return;
        }

        $departure = TripDeparture::findOrFail($this->departureId);

        try {
            $reservation = $service->reserve($departure, [
                'joiner_id' => Auth::id(),
                'party_size' => max(1, $this->party),
            ]);
        } catch (TripFullException $e) {
            $this->error = $e->getMessage();
            return;
        }

        return $this->redirectRoute('reservations.pay', ['reservation' => $reservation->id], navigate: true);
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
