@props(['trip'])
@php($next = $trip->relationLoaded('departures') ? $trip->departures->first() : $trip->openDepartures->first())
@php($currency = $trip->host->hostProfile?->currency ?? 'PHP')
@php($left = $next?->spotsLeft())
@php($when = $next ? $next->starts_at->format('M j').($next->ends_at && ! $next->ends_at->isSameDay($next->starts_at) ? '–'.$next->ends_at->format($next->ends_at->month === $next->starts_at->month ? 'j' : 'M j') : '') : null)

{{--
    A listing tile: the photograph is the card, and everything else is plain text beneath it.

    No border, no shadow, no description. A browsing page earns its density from tiles that all say the
    same five things in the same places (what, where, when, who, how much), so the eye can compare a
    dozen of them in one sweep. The richer card with reviews and inclusions lives on the Explore page.
--}}
<a href="{{ route('trips.show', $trip) }}" class="group block" dusk="trip-tile" data-trip="{{ $trip->id }}">
    <div class="relative aspect-[5/4] overflow-hidden rounded-2xl bg-sand">
        <img src="{{ $trip->cover_url }}" alt="" loading="lazy"
             class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]">
        @if($left !== null && $left <= 4)
            <span class="absolute left-3 top-3 rounded-full bg-surface px-3 py-1 text-xs font-semibold shadow-sm">{{ $left === 0 ? 'Full' : $left.' '.Str::plural('spot', $left).' left' }}</span>
        @endif
    </div>

    <div class="mt-3 flex items-start justify-between gap-3">
        <h3 class="font-semibold leading-snug line-clamp-1">{{ $trip->title }}</h3>
        @if($trip->reviews_count)
            <span class="flex shrink-0 items-center gap-1 text-sm">
                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 text-ink" fill="currentColor" aria-hidden="true"><path d="M12 2.8l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.6 6.1 20.8l1.2-6.5L2.5 9.7l6.6-.9z"/></svg>
                {{ $trip->aggregate_rating }}
            </span>
        @endif
    </div>
    <p class="text-sm text-ink/65 line-clamp-1">{{ $trip->place?->name }}, {{ $trip->place?->country }}</p>
    @if($when)
        <p class="text-sm text-ink/65 line-clamp-1">{{ $when }}, led by {{ $trip->host->name }}</p>
    @endif
    <p class="mt-1 text-sm"><span class="font-semibold">{{ $currency }} {{ number_format($trip->current_price) }}</span> <span class="text-ink/65">per person</span></p>
</a>
