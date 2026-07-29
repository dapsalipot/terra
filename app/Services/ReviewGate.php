<?php

namespace App\Services;

use App\Models\Place;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReviewGate
{
    /**
     * A joiner may review a target only if they hold a confirmed reservation on a
     * completed departure whose trip matches the target (the trip, its host, or its place).
     */
    public function allows(User $user, Model $target): bool
    {
        $trips = Reservation::query()
            ->where('joiner_id', $user->id)
            ->where('status', 'confirmed')
            ->whereHas('departure', fn ($d) => $d->where('status', 'completed'))
            ->with('departure.trip')
            ->get()
            ->map(fn (Reservation $r) => $r->departure->trip)
            ->filter();

        foreach ($trips as $trip) {
            if ($target instanceof Trip && $target->id === $trip->id) {
                return true;
            }
            if ($target instanceof User && $target->id === $trip->host_id) {
                return true;
            }
            if ($target instanceof Place && $target->id === $trip->place_id) {
                return true;
            }
        }

        return false;
    }
}
