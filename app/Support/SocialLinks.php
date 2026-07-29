<?php

namespace App\Support;

/**
 * Hosts type links however they like — "@trailco", "trailco", "instagram.com/trailco"
 * or a full URL. This turns any of those into something a browser can actually open.
 */
class SocialLinks
{
    private const BASES = [
        'instagram' => 'https://instagram.com/',
        'facebook' => 'https://facebook.com/',
        'tiktok' => 'https://tiktok.com/@',
        'x' => 'https://x.com/',
    ];

    public static function url(string $kind, ?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Already a full URL — trust it.
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        // Bare domain typed for a website, or a pasted profile path like "instagram.com/x".
        if (str_contains($value, '.') && ! str_starts_with($value, '@')) {
            return 'https://'.ltrim($value, '/');
        }

        $handle = ltrim($value, '@/');

        return isset(self::BASES[$kind]) ? self::BASES[$kind].$handle : 'https://'.$handle;
    }

    /** What to show on screen — always the handle, never the scheme noise. */
    public static function label(string $kind, ?string $value): string
    {
        $value = trim((string) $value);

        if ($kind === 'website') {
            return preg_replace('#^https?://(www\.)?#', '', $value) ?: $value;
        }

        return str_starts_with($value, '@') ? $value : '@'.ltrim(basename(parse_url($value, PHP_URL_PATH) ?: $value), '@');
    }
}
