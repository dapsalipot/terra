@props(['trip'])
@php($next = $trip->relationLoaded('departures') ? $trip->departures->first() : $trip->openDepartures->first())
@php($currency = $trip->host->hostProfile?->currency ?? 'PHP')
@php($shown = $trip->relationLoaded('inclusionItems') ? $trip->inclusionItems->take(3) : collect())
@php($moreCount = $trip->relationLoaded('inclusionItems') ? max(0, $trip->inclusionItems->count() - $shown->count()) : 0)

{{--
    The card is a container with a stretched link, not one big anchor.

    It used to wrap everything in a single <a>, which meant the host credit inside it could
    never be a link of its own — nested anchors are invalid HTML and browsers drop the inner
    one. The overlay below still makes the whole card clickable; anything that needs its own
    destination just sits above it on the z-axis.
--}}
<div dusk="trip-card" data-trip="{{ $trip->id }}"
     class="group relative rounded-3xl overflow-hidden bg-surface border border-ink/6 transition-all duration-300
            shadow-[0_10px_30px_-18px_rgba(31,28,21,.35)]
            hover:shadow-[0_28px_50px_-24px_rgba(31,28,21,.5)] hover:-translate-y-1
            focus-within:ring-2 focus-within:ring-moss focus-within:ring-offset-2">

    {{-- The title rides inside the anchor as visually-hidden text rather than as an aria-label:
         an anchor with no text content is one a screen reader announces by its href, and it is
         invisible to any tooling that finds links by their words. --}}
    <a href="{{ route('trips.show', $trip) }}" class="absolute inset-0 z-0 focus:outline-none">
        <span class="sr-only">View {{ $trip->title }}</span>
    </a>

    {{-- Photo: wide, not tall — a strong first impression without dominating the card's height. --}}
    <div class="relative aspect-[16/10] overflow-hidden">
        <img src="{{ $trip->cover_url }}" alt="{{ $trip->title }}"
             class="absolute inset-0 h-full w-full object-cover transition-transform duration-[900ms] ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.06]"
             loading="lazy">

        <div class="absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-ink/80 via-ink/30 to-transparent" aria-hidden="true"></div>

        <div class="absolute inset-x-4 top-4 flex items-start justify-between gap-2">
            @if($trip->place)
                <span class="glass rounded-full px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider">{{ $trip->place->category }}</span>
            @endif
            @if($next && $next->spotsLeft() !== null && $next->spotsLeft() <= 4)
                <span class="rounded-full bg-terra px-2.5 py-1 text-[11px] font-bold text-cream">{{ $next->spotsLeft() }} spots left</span>
            @endif
        </div>

        <div class="absolute inset-x-0 bottom-0 p-4">
            @if($next)
                <span class="inline-flex items-center gap-1.5 text-cream/90 text-[11px] font-bold uppercase tracking-wider mb-1.5">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><rect x="3.5" y="5" width="17" height="16" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>
                    {{ $next->starts_at->format('M j') }}@if($next->ends_at)–{{ $next->ends_at->format('j') }}@endif
                    {{-- Listings show one card per offering, so say when other dates exist —
                         otherwise collapsing them looks like the trip runs once. --}}
                    @if(($trip->schedule_count ?? 1) > 1)
                        <span class="text-cream/70">+{{ $trip->schedule_count - 1 }} more {{ \Illuminate\Support\Str::plural('date', $trip->schedule_count - 1) }}</span>
                    @endif
                </span>
            @endif
            <h3 class="tracking-tight font-semibold text-xl leading-tight text-cream">{{ $trip->title }}</h3>
            <p class="flex items-center gap-1.5 text-sm text-cream/80 mt-1">
                <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                {{ $trip->place?->name }}<span class="text-cream/70" aria-hidden="true">·</span>{{ $trip->place?->country }}
            </p>
        </div>
    </div>

    {{-- ══ TRIP DETAILS — this is the emphasis, not the price ══ --}}
    <div class="p-5">
        {{-- Said to the trip's host only, and here on the flat card because a tinted pill over the photo could not be read. --}}
        <x-visibility-chip :trip="$trip" class="mb-3" />

        {{-- reviews, front and centre --}}
        @if($trip->reviews_count)
            <p class="flex items-center gap-1.5 text-sm">
                <span class="text-amber">★</span>
                <span class="font-semibold">{{ $trip->aggregate_rating }}</span>
                <span class="text-ink/65">· {{ $trip->reviews_count }} {{ Str::plural('review', $trip->reviews_count) }}</span>
            </p>
        @else
            <p class="text-sm text-ink/65">No reviews yet</p>
        @endif

        @if($trip->description)
            <p class="text-sm text-ink/65 leading-relaxed mt-2 line-clamp-2">{{ $trip->description }}</p>
        @endif

        @if($shown->isNotEmpty())
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-3">
                @foreach($shown as $inc)
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-ink/65">
                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-moss shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                        {{ $inc->label }}
                    </span>
                @endforeach
                @if($moreCount > 0)
                    <span class="text-xs font-medium text-ink/65">+{{ $moreCount }} more</span>
                @endif
            </div>
        @endif

        {{-- footer: host stays legible, price stays quiet — it's a detail, not the headline --}}
        <div class="flex items-center justify-between gap-3 mt-4 pt-3.5 border-t border-ink/6">
            <x-host-link :host="$trip->host" size="xs" class="text-xs text-ink/65" />

            <span class="text-sm font-medium text-ink/65 shrink-0">{{ $currency }} {{ number_format($trip->current_price) }} <span class="text-ink/65">/ person</span></span>
        </div>
    </div>
</div>
