<?php

namespace App\Support;

/**
 * What a joiner should bring. Allow-listed keys rather than free text, for the same reason
 * TrailFeatures is: "headlamp" and "head lamp" and "flashlight" are one thing, and a fixed
 * vocabulary can be filtered, translated and compared across trips later.
 *
 * Grouped so the picker reads as sections rather than forty undifferentiated squares. The
 * groups are what a host actually thinks in when packing a trip: what you wear, what you
 * carry, what keeps you safe, what you need for the water.
 *
 * @see TrailFeatures for the same catalog shape and the toggle-card UI that renders it.
 */
class PackingItems
{
    /** group label => [key => [label, one-line explanation, icon path (24px viewBox)]] */
    public const CATALOG = [
        'Wear' => [
            'trail_shoes' => ['Trail shoes', 'Closed shoes with grip. No slippers on the trail.',
                'M3 17h11l3-3 4 1v2H3zM3 17v-4l4-2 3 3'],
            'jacket' => ['Rain jacket', 'It will rain. Bring a light waterproof layer.',
                'M8 3l4 3 4-3 4 3v6l-2 1v8H6v-8l-2-1V6z'],
            'warm_layer' => ['Warm layer', 'Summit temperatures drop fast after dark.',
                'M12 2v20M4 7l16 10M4 17L20 7M12 6l-3 3M12 6l3 3M12 18l-3-3M12 18l3-3'],
            'hat' => ['Hat or cap', 'Open ridges give no shade at all.',
                'M4 15h16M6 15a6 6 0 0 1 12 0M9 9a3 3 0 0 1 6 0'],
            'swimwear' => ['Swimwear', 'For the beach, falls or hot spring stop.',
                'M7 4l2 5h6l2-5M7 9v6a3 3 0 0 0 6 0 3 3 0 0 0 6 0V9'],
        ],
        'Carry' => [
            'daypack' => ['Daypack', 'Enough for a day out, not a week away.',
                'M8 7V5a4 4 0 0 1 8 0v2M5 7h14v13H5zM9 7v4h6V7'],
            'water' => ['Water', 'At least 2 litres per person per day.',
                'M12 3s5 6 5 10a5 5 0 0 1-10 0c0-4 5-10 5-10z'],
            'trail_food' => ['Trail food', 'Snacks you can eat while walking.',
                'M12 4c4 0 7 3 7 7s-3 9-7 9-7-5-7-9 3-7 7-7zM12 4V2M12 4l2-2'],
            'dry_bag' => ['Dry bag', 'Keeps clothes and electronics out of the rain.',
                'M6 8h12l-1 12H7zM6 8l1-4h10l1 4M9 8V4'],
            'power_bank' => ['Power bank', 'No sockets on the mountain.',
                'M4 8h13v8H4zM17 11h3v2h-3M7 11v2M10 11v2'],
            'cash' => ['Cash', 'Registration, guides and sari-sari stores are cash only.',
                'M2 6h20v12H2zM12 9a3 3 0 0 1 0 6 3 3 0 0 1 0-6M5 9h.01M19 15h.01'],
        ],
        'Safety' => [
            'headlamp' => ['Headlamp', 'Hands-free light for an early start or late finish.',
                'M12 4a5 5 0 0 1 5 5v2H7V9a5 5 0 0 1 5-5zM9 11v7h6v-7M3 9h2M19 9h2'],
            'sunblock' => ['Sunblock', 'Altitude and open water both burn faster.',
                'M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8zM12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19'],
            'meds' => ['Personal meds', 'Bring your own — and tell your host about them.',
                'M9 3h6v3H9zM6 6h12v15H6zM12 10v7M8.5 13.5h7'],
            'insect_repellent' => ['Insect repellent', 'Mosquitoes and limatik in the forest.',
                'M9 4h6v3H9zM8 7h8v10a4 4 0 0 1-8 0zM10 11h4M10 15h4'],
            'id' => ['Valid ID', 'Registration desks and boat terminals ask for one.',
                'M3 5h18v14H3zM8 12a2 2 0 1 1 0-4 2 2 0 0 1 0 4M6 16c0-1.5 1-2.5 2-2.5s2 1 2 2.5M14 10h4M14 14h4'],
        ],
        'Water trips' => [
            'reef_shoes' => ['Reef shoes', 'Coral and rocks cut bare feet.',
                'M4 16h10l4-2 2 1v3H4zM4 16v-3l3-2 3 3'],
            'rash_guard' => ['Rash guard', 'Sun and board rash on a long day in the water.',
                'M8 4l4 2 4-2 4 3-2 4-2-1v10H8V10l-2 1-2-4z'],
            'snorkel' => ['Snorkel set', 'Bring your own if you would rather not rent.',
                'M6 9a6 6 0 0 1 12 0v3a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3zM18 6V3M9 15v4a2 2 0 0 0 4 0'],
            'motion_meds' => ['Motion sickness meds', 'Crossings can be rough outside the coves.',
                'M3 15c2-2 4-2 6 0s4 2 6 0 4-2 6 0M8 9h8l-1-4H9z'],
        ],
    ];

    /** Every key in the catalog, flattened — the allow-list validation checks against. */
    public static function keys(): array
    {
        return collect(self::CATALOG)->flatMap(fn (array $group) => array_keys($group))->all();
    }

    /**
     * Resolve stored keys to their catalog entries, in catalog order.
     *
     * Catalog order, not the order the host tapped them: the list reads the same on every trip,
     * and a key that has since been retired from the catalog drops out rather than rendering as
     * a blank row.
     *
     * @return array<int, array{key: string, label: string, note: string, icon: string}>
     */
    public static function resolve(?array $keys): array
    {
        $keys = array_filter((array) $keys);

        return collect(self::CATALOG)
            ->flatMap(fn (array $group) => $group)
            ->filter(fn ($v, $k) => in_array($k, $keys, true))
            ->map(fn ($v, $k) => ['key' => $k, 'label' => $v[0], 'note' => $v[1], 'icon' => $v[2]])
            ->values()
            ->all();
    }
}
