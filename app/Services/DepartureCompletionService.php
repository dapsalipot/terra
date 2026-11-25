<?php

namespace App\Services;

use App\Models\Post;
use App\Models\TripDeparture;
use App\Support\Visibility;

class DepartureCompletionService
{
    /** Mark a departure completed and, for a trip that is public by now, auto-publish a recap to the host wall. */
    public function complete(TripDeparture $departure): void
    {
        if ($departure->status === 'completed') {
            return;
        }

        $departure->update(['status' => 'completed']);

        $trip = $departure->trip;

        // The recap names the trip on the host's public wall, so a trip only some people can find stays off it: a link-only
        // trip is for its own group, and a followers-first one is announced once its window has passed.
        if ($trip->effectiveVisibility() !== Visibility::PUBLIC) {
            return;
        }

        $guests = (int) $departure->reservations()->where('status', 'confirmed')->sum('party_size');

        Post::create([
            'host_id' => $trip->host_id,
            'trip_id' => $trip->id,
            'type' => 'recap',
            'body' => "Just wrapped {$trip->title} — {$guests} ".str('joiner')->plural($guests).' on the trail. Until the next one!',
        ]);
    }

    /** Complete every departure whose end date has passed (scheduled daily). */
    public function completePastDepartures(): int
    {
        $due = TripDeparture::whereIn('status', ['open', 'full'])
            ->where(fn ($q) => $q->whereNotNull('ends_at')->where('ends_at', '<', now())
                ->orWhere(fn ($w) => $w->whereNull('ends_at')->where('starts_at', '<', now()->subDay())))
            ->get();

        $due->each(fn (TripDeparture $d) => $this->complete($d));

        return $due->count();
    }
}
