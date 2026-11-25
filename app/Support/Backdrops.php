<?php

namespace App\Support;

/**
 * The quiet pattern behind a host's page, picked from a short list.
 *
 * Like accents, a host picks a key and never uploads one, so every page stays inside the Trail Earth
 * look. The shapes are drawn once and coloured per host in CSS (`.backdrop-*` in app.css), so each host's
 * pattern comes out in their own accent.
 */
class Backdrops
{
    public const DEFAULT = 'trail';

    /** key => label */
    public const CATALOG = [
        'trail' => 'Trail',
        'contours' => 'Contours',
        'dots' => 'Dots',
        'none' => 'None',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function key(?string $key): string
    {
        return isset(self::CATALOG[$key]) ? $key : self::DEFAULT;
    }
}
