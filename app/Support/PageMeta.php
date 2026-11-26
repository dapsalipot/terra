<?php

namespace App\Support;

use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * What a page says about itself when its link is pasted into Facebook, Messenger or a group chat.
 *
 * Every page gets the same five things, which <x-seo> writes as tags: a title, a line under it, a picture, the
 * address it answers to, and whether search engines should leave it alone. The picture and the address are
 * absolute because a crawler has no site to resolve a relative one against.
 *
 * Nothing here asks who is looking. A link is the same link for everyone it is sent to, so the preview it
 * shows cannot depend on who fetches it: a trip that is only listed to some people is never borrowed as a
 * host's picture, and a trip that is not public right now says so to search engines whoever asks.
 */
class PageMeta
{
    /** The one card every page without a photograph of its own shows, 1200 x 630. */
    private const CARD = 'images/og-card.png';

    /**
     * What any page without a story of its own says. It answers to the address it was asked for, filters and
     * page number included: a shared search keeps its search, and page 2 is not taken for page 1.
     */
    public static function default(): array
    {
        return [
            'title' => 'Tarana',
            'description' => 'Group trips led by local hosts. Hikes, islands and everywhere in between.',
            'image' => asset(self::CARD),
            'url' => url()->full(),
            'noindex' => false,
        ];
    }

    public static function trip(Trip $trip): array
    {
        $host = $trip->host;
        $currency = $host->hostProfile?->currency ?? 'PHP';
        $next = $trip->openDepartures->first();

        return [
            'title' => "{$trip->title} · {$host->name}",
            'description' => implode(' · ', array_filter([
                implode(', ', array_filter([$trip->place?->name, $trip->place?->country])),
                self::dates($next),
                "from {$currency} ".number_format($trip->current_price).' per person',
                self::spots($next),
                "led by {$host->name}",
            ])),
            'image' => self::absolute($trip->cover_url),
            'url' => route('trips.show', $trip),
            // A trip that opens to everyone later, or to no one, is for the people it is sent to, not for a search page.
            'noindex' => $trip->effectiveVisibility() !== Visibility::PUBLIC,
        ];
    }

    public static function host(User $host): array
    {
        $profile = $host->hostProfile;

        return [
            'title' => "{$host->name} on Tarana",
            'description' => trim((string) $profile?->tagline) ?: "Group trips led by {$host->name}",
            'image' => self::absolute($profile?->cover_url ?? self::nextTripCover($host)),
            'url' => route('hosts.handle', $host),
            'noindex' => false,
        ];
    }

    /** "Dec 14-16", "Dec 30-Jan 2", or "Dec 14" for a trip that is over in a day. Null when nothing is coming up. */
    private static function dates(?TripDeparture $departure): ?string
    {
        if (! $departure) {
            return null;
        }

        [$from, $to] = [$departure->starts_at, $departure->ends_at];

        if (DaySpan::length($from, $to) === 1) {
            return $from->format('M j');
        }

        return $from->format('M j').'-'.$to->format($to->isSameMonth($from) ? 'j' : 'M j');
    }

    /** "4 spots left", "1 spot left", or "Full": the site's own wording for the departure coming up. Null when nothing is coming up. */
    private static function spots(?TripDeparture $departure): ?string
    {
        if (! $departure) {
            return null;
        }

        $left = $departure->spotsLeft();

        return $left === 0 ? 'Full' : $left.' '.Str::plural('spot', $left).' left';
    }

    /**
     * The cover of the trip a host leaves for next among the ones anyone can find, for a host with no cover of
     * their own. Listed to everyone and not to the viewer, so the picture does not change with who asks.
     */
    private static function nextTripCover(User $host): ?string
    {
        return TripDeparture::query()
            ->with('trip.place')
            ->whereHas('trip', fn ($t) => $t->listed()->where('host_id', $host->id))
            ->where('status', 'open')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first()
            ?->trip->cover_url;
    }

    /** An uploaded cover is stored as a path on this site, a stock one is already a full address; the card stands in for neither. */
    private static function absolute(?string $image): string
    {
        return $image ? url($image) : asset(self::CARD);
    }
}
