<?php

namespace App\Support;

use App\Models\Trip;
use DateInterval;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Who can find a trip in a listing, as a fixed list a host picks from.
 *
 * This is listing control and nothing more: a published trip's page, join page, QR and booking page open
 * by link whatever it says. Followers first is a head start rather than a gate, so how long it lasts comes
 * from a few presets instead of a free date, and the stored value is never rewritten when the window
 * passes: the trip simply reads as public from then on (see Trip::effectiveVisibility()).
 *
 * @see Vibes for the same catalog shape.
 */
class Visibility
{
    public const PUBLIC = 'public';

    public const FOLLOWERS = 'followers';

    public const LINK = 'link';

    /** key => label */
    public const CATALOG = [
        self::PUBLIC => 'Everyone',
        self::FOLLOWERS => 'Followers first',
        self::LINK => 'Link only',
    ];

    /** key => [ISO 8601 interval, label]: how long a followers-first trip stays with followers. */
    public const WINDOWS = [
        '1d' => ['P1D', '1 day'],
        '3d' => ['P3D', '3 days'],
        '1w' => ['P1W', '1 week'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    /** When a followers-first trip that starts its window now opens to everyone. */
    public static function opensAt(string $window): Carbon
    {
        $interval = self::WINDOWS[$window][0] ?? throw new InvalidArgumentException("Unknown visibility window [{$window}].");

        return now()->add(new DateInterval($interval));
    }

    /** What the host sees beside a trip that is not listed to everyone right now; null when it is. */
    public static function chip(Trip $trip): ?string
    {
        return match ($trip->effectiveVisibility()) {
            self::LINK => self::CATALOG[self::LINK],
            self::FOLLOWERS => self::CATALOG[self::FOLLOWERS].($trip->public_at ? ' until '.$trip->public_at->format('j M') : ''),
            default => null,
        };
    }
}
