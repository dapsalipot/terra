<?php

namespace App\Support;

/**
 * How a host's trips feel, as a fixed list a host picks from.
 *
 * When six hosts run the same hike, the trip stops telling a joiner anything, so the choice comes down to
 * who is leading it. Free-text "about me" does not compare; a short fixed list does, and it is also
 * something a joiner can filter on. Fixed rather than free-form so the tags stay comparable and cannot be
 * used to say anything the platform would have to moderate.
 *
 * @see PlaceCategories for the same catalog shape.
 */
class Vibes
{
    /** The most a host can pick: four is a personality, ten is a brochure. */
    public const MAX = 4;

    /** key => label */
    public const CATALOG = [
        'beginner' => 'Beginner friendly',
        'challenge' => 'Challenge seekers',
        'small_groups' => 'Small groups',
        'social' => 'Social crowd',
        'photography' => 'Photography',
        'sunrise' => 'Sunrise chasers',
        'budget' => 'Budget friendly',
        'comfort' => 'Comfort first',
        'family' => 'Family friendly',
        'solo' => 'Solo friendly',
        'women_led' => 'Women led',
        'meals' => 'Meals included',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function label(string $key): ?string
    {
        return self::CATALOG[$key] ?? null;
    }

    /**
     * Resolve stored keys to labels, in the order the host picked them, dropping anything no longer in the list.
     *
     * @param  iterable<string>|null  $keys
     * @return array<string, string> key => label
     */
    public static function resolve(?iterable $keys): array
    {
        $out = [];

        foreach ($keys ?? [] as $key) {
            if (isset(self::CATALOG[$key])) {
                $out[$key] = self::CATALOG[$key];
            }
        }

        return $out;
    }
}
