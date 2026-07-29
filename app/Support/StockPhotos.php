<?php

namespace App\Support;

use App\Models\Trip;

/**
 * Category-keyed stock photography used as the cover fallback until a host
 * uploads a real photo. Files live in public/images/stock (Unsplash license).
 */
class StockPhotos
{
    /** Destination-accurate pools, keyed by place slug. Checked before the category pools. */
    private const PLACE_COVERS = [
        'mount-pulag' => ['pulag-climb.jpg', 'pulag-clouds.jpg', 'pulag-summit.jpg'],
    ];

    /** @var array<string, list<string>> */
    private const COVERS = [
        'hike' => ['hike-1.jpg', 'pulag-climb.jpg', 'hike-2.jpg'],
        'island' => ['island-1.jpg'],
        'beach' => ['beach-1.jpg'],
        'surf' => ['surf-1.jpg'],
        'city' => ['city-1.jpg'],
    ];

    private const DEFAULT = 'hike-2.jpg';

    public static function cover(Trip $trip): string
    {
        $options = self::PLACE_COVERS[$trip->place?->slug]
            ?? self::COVERS[$trip->place?->category]
            ?? [self::DEFAULT];

        // Deterministic per-trip pick so a trip keeps the same photo everywhere.
        return asset('images/stock/'.$options[$trip->id % count($options)]);
    }
}
