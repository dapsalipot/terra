@props([
    'host',
    'extras' => [],     // from App\Support\HostCards::extras(): next departure, review count, following
    'kinds' => [],      // kinds of trip they run, shown only while they have picked no vibes of their own
    'preview' => false, // the profile editor draws the card unsaved: no links, no follow
])

@php
    $p = $host->hostProfile;
    $next = $extras['next'][$host->id] ?? null;
    $reviews = (int) ($extras['reviews'][$host->id] ?? 0);
    $following = isset($extras['following'][$host->id]);
    $vibes = array_slice($p?->vibeLabels() ?? [], 0, 3, true);
    $trips = $host->published_trips ?? $p?->trips_count ?? 0;
    $mine = auth()->id() === $host->id;
    $seats = $next?->spotsLeft();
@endphp

{{--
    The storefront card: a host as someone to choose, not a row in a list.

    When several hosts run the same hike, what a joiner is choosing between is who leads it. So the card
    leads with the host's own words and colours (cover, one-line promise, vibe tags), puts the proof beside
    them (rating, reviews, trips) and ends on the next date, which is what turns looking into booking.
    It is a container with a stretched link rather than one big anchor, so the follow button and the next
    departure can be links of their own without nesting anchors.
--}}
<article class="group relative flex h-full flex-col overflow-hidden rounded-3xl border border-ink/10 bg-surface transition-shadow duration-300 hover:shadow-[0_18px_40px_-22px_rgba(31,28,21,.45)] focus-within:ring-2 focus-within:ring-moss focus-within:ring-offset-2"
         style="--accent: {{ $p?->accent_hex ?? '#5E6B44' }}; --accent-soft: {{ $p?->accent_soft ?? '#EDF0E6' }}; --accent-text: {{ $p?->accent_text ?? '#5E6B44' }}"
         @unless($preview) dusk="host-card-{{ $host->id }}" @endunless>

    @if(! $preview && filled($host->handle))
        <a href="{{ route('hosts.show', $host) }}" wire:navigate class="absolute inset-0 z-0 focus:outline-none">
            <span class="sr-only">View {{ $host->name }}</span>
        </a>
    @endif

    {{-- The cover is the host's photo, or their accent with the trail drawn over it in white. --}}
    <div class="relative h-20 shrink-0 overflow-hidden" style="background: var(--accent)">
        @if($p?->cover_url)
            <img src="{{ $p->cover_url }}" alt="" loading="lazy"
                 class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-105">
        @else
            <div class="cover-trail absolute inset-0" aria-hidden="true"></div>
        @endif
    </div>

    <div class="relative flex flex-1 flex-col px-5 pb-5">
        <div class="-mt-7 flex items-end justify-between gap-3">
            <span class="pointer-events-none relative grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-2xl bg-pine text-lg font-semibold text-cream ring-4 ring-surface">
                @if($host->avatar_url)
                    <img src="{{ $host->avatar_url }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                @else
                    {{ $host->initials }}
                @endif
            </span>

            @if(! $preview && ! $mine)
                <button type="button" wire:click="toggleFollow({{ $host->id }})" aria-pressed="{{ $following ? 'true' : 'false' }}" dusk="card-follow-{{ $host->id }}"
                        class="relative z-10 rounded-full px-4 py-1.5 text-xs font-semibold transition active:scale-95 {{ $following ? 'border border-ink/20 bg-surface text-ink hover:border-ink/35' : 'bg-pine text-cream hover:bg-pine/90' }}">
                    {{ $following ? 'Following' : 'Follow' }}
                </button>
            @endif
        </div>

        <h3 class="mt-3 flex items-center gap-1.5 font-semibold leading-tight">
            <span class="truncate">{{ $host->name }}</span>
            @if($p?->isVerified())
                <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" style="color: var(--accent-text)" role="img" aria-label="Verified host"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M7.5 12.5l3 3 6-6.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @endif
        </h3>
        <p class="mt-0.5 truncate text-sm text-ink/65">&commat;{{ $host->handle ?: 'handle' }}@if($p?->location), {{ $p->location }}@endif</p>

        @if($p?->tagline)
            <p class="mt-2 line-clamp-2 text-sm font-medium" style="color: var(--accent-text)">{{ $p->tagline }}</p>
        @elseif($p?->bio)
            <p class="mt-2 line-clamp-2 text-sm text-ink/70">{{ $p->bio }}</p>
        @endif

        @if($vibes)
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach($vibes as $label)
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: var(--accent-soft); color: var(--accent-text)">{{ $label }}</span>
                @endforeach
            </div>
        @elseif($kinds)
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach($kinds as $kind)
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold" style="background: var(--accent-soft); color: var(--accent-text)">
                        <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $kind['icon'] }}"/></svg>
                        {{ $kind['label'] }}
                    </span>
                @endforeach
            </div>
        @endif

        <p class="mt-3 flex flex-wrap items-center gap-x-1.5 text-sm">
            @if($p?->rating)
                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true"><path d="M12 2.8l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.6 6.1 20.8l1.2-6.5L2.5 9.7l6.6-.9z"/></svg>
                <span class="font-semibold">{{ $p->rating }}</span>
                @if($reviews)<span class="text-ink/65">{{ number_format($reviews) }} {{ Str::plural('review', $reviews) }}</span>@endif
                <span class="text-ink/65" aria-hidden="true">·</span>
            @else
                <span class="font-semibold">New host</span>
                <span class="text-ink/65" aria-hidden="true">·</span>
            @endif
            <span class="text-ink/65">{{ number_format($trips) }} {{ Str::plural('trip', $trips) }}</span>
        </p>

        @if($next)
            <div class="mt-auto pt-4">
                <a href="{{ route('trips.show', $next->trip) }}" wire:navigate dusk="card-next-{{ $host->id }}"
                   class="relative z-10 flex items-center justify-between gap-3 rounded-xl border-t border-ink/10 pt-3 text-xs text-ink/65 hover:text-ink">
                    <span class="min-w-0 truncate">Next: <span class="font-semibold text-ink">{{ $next->trip->place?->name ?: $next->trip->title }}, {{ $next->starts_at->format('M j') }}</span></span>
                    @if($seats !== null)
                        <span class="shrink-0 font-semibold {{ $seats <= 4 ? 'text-terra-deep' : '' }}">{{ $seats === 0 ? 'Full' : $seats.' left' }}</span>
                    @endif
                </a>
            </div>
        @endif
    </div>
</article>
