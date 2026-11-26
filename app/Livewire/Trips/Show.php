<?php

namespace App\Livewire\Trips;

use App\Livewire\Concerns\FollowsHosts;
use App\Models\Review;
use App\Models\Trip;
use App\Services\ReviewGate;
use App\Services\ReviewService;
use App\Support\HostCards;
use App\Support\PageMeta;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    use FollowsHosts;

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

    /** Host replies, keyed by review id, so several drafts can sit open at once. */
    public array $replyBody = [];

    public ?int $replyingTo = null;

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

    public function startReply(int $reviewId): void
    {
        $this->replyingTo = $reviewId;
        $this->replyBody[$reviewId] ??= (string) Review::find($reviewId)?->reply_body;
    }

    public function cancelReply(): void
    {
        $this->replyingTo = null;
    }

    public function reply(int $reviewId, ReviewService $reviews): void
    {
        $this->validate(
            ["replyBody.{$reviewId}" => ['required', 'string', 'max:1000']],
            attributes: ["replyBody.{$reviewId}" => 'reply'],
        );

        $reviews->reply(Review::findOrFail($reviewId), Auth::user(), $this->replyBody[$reviewId]);

        $this->replyingTo = null;
    }

    public function render()
    {
        // visible(): a hidden review is gone from the page as well as from the average.
        $reviews = Review::visible()->where('reviewable_type', Trip::class)
            ->where('reviewable_id', $this->trip->id)
            ->with('joiner')
            ->latest()
            ->get();

        // How many reviews stand behind the host's rating, and whether this viewer already follows them.
        $hostExtras = HostCards::extras([$this->trip->host_id], Auth::user());

        // What the link looks like when it is shared, and the tab's title with it.
        $meta = PageMeta::trip($this->trip);

        // More from this host: up to six of their other trips with something left to join, one tile per offering and
        // never the offering on this page. They are listed to this viewer, except that the host sees everything they
        // have published (each tile carries its chip). `departures` holds only the open ones, so a tile is dated and
        // ordered by the next date a joiner could book. The tiles borrow this page's host, who is already loaded.
        // Collapsed in PHP like Home and the host page: a host with hundreds of live schedules would want that in SQL.
        $open = fn ($departures) => $departures->where('status', 'open')->where('starts_at', '>', now());
        $more = Trip::collapseSchedules(
            $this->trip->host->trips()->chaperone('host')
                ->whereNotIn('trips.id', $this->trip->groupSiblings()->select('id'))
                ->whereHas('departures', $open)
                ->when(
                    Auth::id() === $this->trip->host_id,
                    fn ($trips) => $trips->published(),
                    fn ($trips) => $trips->listedFor(Auth::user()),
                )
                ->with(['place', 'priceTiers', 'departures' => fn ($departures) => $open($departures)->orderBy('starts_at')])
                ->get()
        )->take(6);

        return view('livewire.trips.show', ['reviews' => $reviews, 'hostExtras' => $hostExtras, 'more' => $more])
            ->layout('components.layouts.site', ['tabbar' => false, 'meta' => $meta, 'title' => $meta['title']]);
    }
}
