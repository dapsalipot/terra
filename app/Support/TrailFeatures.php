<?php

namespace App\Support;

/**
 * What a hiker will actually meet on the trail. Allow-listed keys rather than free text,
 * so the same feature is named the same way on every trip and can be filtered on later.
 *
 * The vocabulary is the one Philippine hikers use — "assault" for a sustained steep
 * section, "knife edge" for an exposed rocky ridge, "monkey trail" for the rough
 * unofficial shortcut, "jump-off" for the trailhead.
 */
class TrailFeatures
{
    /** key => [label, one-line explanation, icon path (24px viewBox)] */
    public const CATALOG = [
        'assault' => ['Assault', 'A sustained steep climb with little respite.',
            'M3 20l7-13 4 7 3-5 4 11z'],
        'river_crossing' => ['River crossing', 'You will ford or rock-hop a river.',
            'M3 15c2-2 4-2 6 0s4 2 6 0 4-2 6 0M3 19c2-2 4-2 6 0s4 2 6 0 4-2 6 0'],
        'rock_scramble' => ['Rock scramble', 'Hands-on sections over boulders or rock.',
            'M4 20l5-8 3 4 3-6 5 10z M8 8l2-3 2 3'],
        'knife_edge' => ['Knife edge', 'A narrow, exposed ridge with drops on both sides.',
            'M2 18L12 5l10 13M8 18l4-6 4 6'],
        'rope_section' => ['Rope section', 'Fixed ropes assist a steep or exposed pitch.',
            'M7 3v13a5 5 0 0 0 10 0V8M7 6h4M7 10h4M7 14h4'],
        'mossy_forest' => ['Mossy forest', 'Cloud-forest trail thick with moss and ferns.',
            'M12 21v-5M7 16a5 5 0 0 1 10 0zM9 11a3 3 0 0 1 6 0zM10.5 6.5a1.5 1.5 0 0 1 3 0z'],
        'grassland' => ['Grassland', 'Open ridges of cogon or dwarf bamboo.',
            'M4 20c0-5 2-8 3-9M10 20c0-6 1-9 2-11M16 20c0-5 2-8 3-9'],
        'monkey_trail' => ['Monkey trail', 'The rough unofficial shortcut — steeper, less maintained.',
            'M5 20c3-2 4-6 3-9M13 20c2-3 2-7 0-10M8 4a2 2 0 1 1 4 0 2 2 0 0 1-4 0M17 20c1-4 0-7-2-9'],
        'river_walk' => ['River walk', 'Long stretches walking in or along the riverbed.',
            'M4 6c3 3 3 9 0 12M12 5c3 3 3 11 0 14M20 6c3 3 3 9 0 12'],
        'waterfalls' => ['Waterfalls', 'The route passes a falls, often a swim stop.',
            'M7 3v10M12 3v13M17 3v10M5 18c2-2 4-2 6 0s4 2 6 0'],
        'cave' => ['Cave', 'A cave or rock tunnel section.',
            'M4 20v-6a8 8 0 0 1 16 0v6M10 20v-4a2 2 0 0 1 4 0v4'],
        'sea_of_clouds' => ['Sea of clouds', 'The classic dawn cloud inversion, weather permitting.',
            'M3 16h13a3.5 3.5 0 0 0 0-7 5 5 0 0 0-9.5-1A3 3 0 0 0 3 16zM6 20h14'],
        'summit_camp' => ['Summit camp', 'You camp at or near the summit.',
            'M3 20l9-13 9 13zM12 7v13'],
        'exposed_ridge' => ['Exposed ridge', 'Open ridgeline with wind and no cover.',
            'M2 17l6-7 4 4 4-6 6 9z'],
        'limestone' => ['Limestone', 'Sharp karst rock — gloves recommended.',
            'M4 20l4-9 3 5 3-7 6 11z'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function label(string $key): ?string
    {
        return self::CATALOG[$key][0] ?? null;
    }

    public static function note(string $key): ?string
    {
        return self::CATALOG[$key][1] ?? null;
    }

    public static function icon(string $key): ?string
    {
        return self::CATALOG[$key][2] ?? null;
    }
}
