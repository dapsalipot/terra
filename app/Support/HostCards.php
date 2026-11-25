<?php

namespace App\Support;

use App\Models\Review;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * What a row of host cards needs beyond the hosts themselves.
 *
 * Each card shows the host's next departure, how many reviews stand behind their rating, and whether the
 * viewer already follows them. Asking for those per card is a hundred queries on a directory page, so
 * this answers for the whole row at once: one query each, keyed by host id.
 */
class HostCards
{
    /**
     * @param  iterable<int>  $ids  host user ids
     * @return array{next: array<int, TripDeparture>, reviews: array<int, int>, following: array<int, true>}
     */
    public static function extras(iterable $ids, ?User $viewer = null): array
    {
        $ids = collect($ids)->unique()->values();

        if ($ids->isEmpty()) {
            return ['next' => [], 'reviews' => [], 'following' => []];
        }

        // The soonest open departure each host has, with the trip it belongs to.
        $next = TripDeparture::query()
            ->with('trip.place')
            ->whereHas('trip', fn ($t) => $t->published()->whereIn('host_id', $ids))
            ->where('status', 'open')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn ($d) => $d->trip->host_id)
            ->map->first()
            ->all();

        // Reviews of the host themselves plus reviews left on any of their trips, which is the same set
        // the host page lists.
        $direct = Review::visible()->whereNotNull('body')
            ->where('reviewable_type', User::class)->whereIn('reviewable_id', $ids)
            ->groupBy('reviewable_id')->select('reviewable_id', DB::raw('count(*) as total'))
            ->pluck('total', 'reviewable_id');

        $viaTrips = Review::visible()->whereNotNull('reviews.body')
            ->where('reviews.reviewable_type', Trip::class)
            ->join('trips', 'trips.id', '=', 'reviews.reviewable_id')
            ->whereIn('trips.host_id', $ids)
            ->groupBy('trips.host_id')->select('trips.host_id', DB::raw('count(*) as total'))
            ->pluck('total', 'trips.host_id');

        $reviews = $ids->mapWithKeys(fn ($id) => [$id => (int) ($direct[$id] ?? 0) + (int) ($viaTrips[$id] ?? 0)])->all();

        $following = $viewer
            ? DB::table('follows')->where('follower_id', $viewer->id)->whereIn('host_id', $ids)->pluck('host_id')
                ->mapWithKeys(fn ($id) => [$id => true])->all()
            : [];

        return compact('next', 'reviews', 'following');
    }
}
