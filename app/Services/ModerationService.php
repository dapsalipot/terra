<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Trip;
use App\Models\User;

/**
 * What the platform can do to content it did not create.
 *
 * Every action here is reversible and leaves the record intact. Nothing is deleted: a suspended
 * host still has bookings in flight that joiners need to see, and a hidden review is evidence in
 * whatever argument caused it to be hidden.
 */
class ModerationService
{
    /** Take a trip out of discovery without destroying it or its bookings. */
    public function unpublish(Trip $trip): Trip
    {
        $trip->update(['status' => 'archived']);

        return $trip;
    }

    public function republish(Trip $trip): Trip
    {
        $trip->update(['status' => 'published']);

        return $trip;
    }

    public function suspend(User $user, ?string $reason = null, ?User $by = null): User
    {
        $user->update([
            'suspended_at' => now(),
            'suspended_reason' => $reason,
            'suspended_by' => $by?->id,
        ]);

        return $user;
    }

    public function restore(User $user): User
    {
        $user->update(['suspended_at' => null, 'suspended_reason' => null, 'suspended_by' => null]);

        return $user;
    }

    public function hideReview(Review $review, ?string $reason = null, ?User $by = null): Review
    {
        $review->update([
            'hidden_at' => now(),
            'hidden_reason' => $reason,
            'hidden_by' => $by?->id,
        ]);

        return $review;
    }

    public function unhideReview(Review $review): Review
    {
        $review->update(['hidden_at' => null, 'hidden_reason' => null, 'hidden_by' => null]);

        return $review;
    }
}
