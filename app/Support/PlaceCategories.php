<?php

namespace App\Support;

/**
 * The kinds of place a trip can go to, with a label and an icon for each.
 *
 * The category itself already lived on `places`, but only ever as a raw lowercase string printed
 * straight into the page. This is the one place that decides how it is spelled and drawn, so a
 * calendar square, a trip card and a filter chip cannot drift into saying "hike", "Hike" and
 * "Hiking" for the same thing.
 *
 * @see TrailFeatures, PackingItems for the same catalog shape.
 */
class PlaceCategories
{
    /** key => [label, icon path (24px viewBox)] */
    public const CATALOG = [
        'hike' => ['Hike', 'M3 20l6-11 3.5 6 2-3.2L21 20zM9 9l1.6-3 1.6 3'],
        'island' => ['Island', 'M3 20c3-1.4 6-1.4 9 0s6 1.4 9 0M12 20V9M12 9c-1.6-2.6-4.4-2.8-5.6-1.2M12 9c1.6-2.6 4.4-2.8 5.6-1.2M12 9c-.6-1.4-.2-2.8.8-3.6'],
        'beach' => ['Beach', 'M12 21v-8M4 13a8 8 0 0 1 16 0zM3 20c2-1.2 4-1.2 6 0'],
        'surf' => ['Surf', 'M3 16.5c3.2 0 5-2.2 6-5.2S12.2 5 15 6s5.4 6.2 6 8.4M3 20.5c4.2 0 7.4-1.2 9.4-3'],
        'city' => ['City', 'M4 21V8.5L10 5v16M14 21V11.5L20 9v12M7 12h.01M7 15.5h.01M17 14h.01M17 17.5h.01'],
    ];

    /** Trips can point at a category we have no entry for; those simply do not draw a badge. */
    public static function label(?string $key): ?string
    {
        return self::CATALOG[$key][0] ?? null;
    }

    public static function icon(?string $key): ?string
    {
        return self::CATALOG[$key][1] ?? null;
    }

    /**
     * Resolve raw category strings to their catalog entries, deduplicated and in catalog order.
     *
     * Catalog order rather than the order the trips happened to be created in, so the same mix of
     * trips always draws the same row of badges.
     *
     * @return array<int, array{key: string, label: string, icon: string}>
     */
    public static function resolve(iterable $keys): array
    {
        $keys = collect($keys)->filter()->unique()->all();

        return collect(self::CATALOG)
            ->filter(fn ($v, $k) => in_array($k, $keys, true))
            ->map(fn ($v, $k) => ['key' => $k, 'label' => $v[0], 'icon' => $v[1]])
            ->values()
            ->all();
    }
}
