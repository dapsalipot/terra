<?php

namespace App\Services;

use App\Models\Place;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function __construct(private ReviewGate $gate) {}

    /**
     * Record a completed attendee's ratings for a trip, its host, and its place.
     * Idempotent per (joiner, target) via updateOrCreate. Recomputes aggregates.
     */
    public function record(User $joiner, Trip $trip, array $ratings, ?string $body = null): void
    {
        abort_unless($this->gate->allows($joiner, $trip), 403, 'You can only review trips you have completed.');

        $host = $trip->host;
        $place = $trip->place;

        DB::transaction(function () use ($joiner, $trip, $host, $place, $ratings, $body) {
            $this->upsert($joiner, $trip, $ratings['trip'] ?? 5, $body);
            $this->upsert($joiner, $host, $ratings['host'] ?? 5, null);
            $this->upsert($joiner, $place, $ratings['place'] ?? 5, null);

            $this->recompute($place);
            $this->recompute($trip);
            $this->recomputeHost($host);
        });
    }

    private function upsert(User $joiner, $target, int $rating, ?string $body): void
    {
        Review::updateOrCreate(
            ['joiner_id' => $joiner->id, 'reviewable_type' => $target::class, 'reviewable_id' => $target->id],
            ['rating' => max(1, min(5, $rating)), 'body' => $body],
        );
    }

    private function reviewsFor($target)
    {
        return Review::where('reviewable_type', $target::class)->where('reviewable_id', $target->id);
    }

    private function recompute(Place|Trip $target): void
    {
        $q = $this->reviewsFor($target);
        $avg = $q->avg('rating');
        $target->forceFill([
            'aggregate_rating' => $avg ? round($avg, 1) : null,
            'reviews_count' => $q->count(),
        ])->save();
    }

    private function recomputeHost(User $host): void
    {
        if (! $host->hostProfile) {
            return;
        }
        $avg = $this->reviewsFor($host)->avg('rating');
        $host->hostProfile->update(['rating' => $avg ? round($avg, 1) : null]);
    }
}
