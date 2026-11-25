@props(['host'])
@php($profile = $host->hostProfile)
@php($trips = $host->published_trips ?? $profile?->trips_count ?? 0)

{{-- A host, as a person first: who they are, where they are, and the two numbers that say whether to trust them. --}}
<a href="{{ route('hosts.show', $host) }}" dusk="host-tile"
   class="group flex h-full flex-col items-center rounded-2xl border border-ink/10 bg-surface p-5 text-center transition-shadow duration-300 hover:shadow-[0_14px_30px_-18px_rgba(31,28,21,.4)]">
    <span class="grid h-20 w-20 place-items-center rounded-full bg-pine text-3xl font-semibold text-cream transition-transform duration-300 group-hover:scale-105">{{ strtoupper(substr($host->name, 0, 1)) }}</span>
    <span class="mt-3 block max-w-full truncate font-semibold">{{ $host->name }}</span>
    @if($profile?->location)
        <span class="block max-w-full truncate text-sm text-ink/65">{{ $profile->location }}</span>
    @endif
    <span class="mt-3 flex items-center gap-3 text-sm">
        @if($profile?->rating)
            <span class="flex items-center gap-1 font-semibold">
                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true"><path d="M12 2.8l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.6 6.1 20.8l1.2-6.5L2.5 9.7l6.6-.9z"/></svg>
                {{ $profile->rating }}
            </span>
        @endif
        <span class="text-ink/65">{{ $trips }} {{ Str::plural('trip', $trips) }}</span>
    </span>
</a>
