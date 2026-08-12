@props([
    'host',
    'size' => 'md',
    'avatar' => true,
    'name' => true,
    'subtitle' => null,
    'tone' => 'ink',
])

@php
    $sizes = [
        'xs' => ['box' => 'w-6 h-6', 'type' => 'text-[10px]'],
        'sm' => ['box' => 'w-8 h-8', 'type' => 'text-xs'],
        'md' => ['box' => 'w-9 h-9', 'type' => 'text-sm'],
        'lg' => ['box' => 'w-12 h-12', 'type' => 'text-lg'],
    ];
    $box = $sizes[$size] ?? $sizes['md'];
    $onDark = $tone === 'cream';

    /*
     * A host with no handle has no page to send anyone to, so the tag itself changes rather
     * than rendering an anchor pointing nowhere. Every caller can pass any host without
     * checking first — that check was the reason most of these were plain text to begin with.
     */
    $linked = $host && filled($host->handle);
    $tag = $linked ? 'a' : 'span';
@endphp

<{{ $tag }}
    @if($linked)
        href="{{ route('hosts.show', $host) }}"
        wire:navigate
        dusk="host-link-{{ $host->id }}"
        title="View {{ $host->name }}'s host page"
    @endif
    {{ $attributes->class(['group/host inline-flex items-center gap-2 min-w-0', 'relative z-10' => $linked]) }}>

    @if($avatar)
        <span class="grid place-items-center {{ $box['box'] }} {{ $box['type'] }} rounded-full shrink-0 overflow-hidden
                     font-serif font-semibold {{ $onDark ? 'bg-cream/15 text-cream' : 'bg-pine text-cream' }}
                     {{ $linked ? 'transition-transform group-hover/host:scale-105' : '' }}">
            @if($host?->avatar_url)
                <img src="{{ $host->avatar_url }}" alt="" class="w-full h-full object-cover">
            @else
                {{ $host?->initials }}
            @endif
        </span>
    @endif

    @if($name)
        <span class="min-w-0">
            <span class="block truncate {{ $linked ? 'group-hover/host:underline decoration-from-font underline-offset-2' : '' }}">
                {{ $host?->name }}
            </span>

            @if($subtitle)
                <span class="block truncate text-xs {{ $onDark ? 'text-cream/80' : 'text-ink/70' }}">{{ $subtitle }}</span>
            @endif
        </span>
    @endif
</{{ $tag }}>
