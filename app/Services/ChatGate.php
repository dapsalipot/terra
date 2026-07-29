<?php

namespace App\Services;

use App\Models\TripDeparture;
use App\Models\User;

class ChatGate
{
    /** The host of the trip, or a confirmed joiner on this departure, may read/post. */
    public function canAccess(User $user, TripDeparture $departure): bool
    {
        if ($departure->trip->host_id === $user->id) {
            return true;
        }

        return $departure->reservations()
            ->where('joiner_id', $user->id)
            ->where('status', 'confirmed')
            ->exists();
    }

    /** Rooms go read-only 7 days after the trip ends. */
    public function isReadOnly(TripDeparture $departure): bool
    {
        if ($departure->status !== 'completed') {
            return false;
        }

        $ended = $departure->ends_at ?? $departure->starts_at;

        return $ended !== null && $ended->lt(now()->subDays(7));
    }
}
