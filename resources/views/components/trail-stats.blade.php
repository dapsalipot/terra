@props(['stats', 'tone' => 'moss', 'compact' => false])

@php
    $tiles = \App\Support\TrailStats::tiles($stats);
    $accent = $tone === 'pine' ? 'text-cream' : 'text-pine';
    $sub = $tone === 'pine' ? 'text-cream/70' : 'text-ink/70';
    $label = $tone === 'pine' ? 'text-cream/80' : 'text-ink/70';
@endphp

{{--
    What a run of trips added up to.

    A row of figures rather than a chart: there are four or five numbers and no trend in them, and
    a sparkline over "trips per month" would be decoration standing where a fact should be. The
    tiles wrap rather than scroll — a horizontally scrolling strip of numbers hides the last one
    on exactly the screen where it is hardest to discover.
--}}
<dl {{ $attributes->merge(['class' => 'grid grid-cols-2 sm:grid-cols-'.min(count($tiles), 5).' gap-x-4 gap-y-3']) }}>
    @foreach($tiles as $tile)
        <div class="min-w-0">
            <dt class="text-[10px] uppercase tracking-[0.09em] font-semibold {{ $label }}">{{ $tile['label'] }}</dt>
            <dd class="{{ $compact ? 'text-lg' : 'text-2xl' }} tracking-tight font-semibold {{ $accent }} tabular-nums leading-tight mt-0.5">
                {{ $tile['value'] }}
            </dd>
            @if($tile['sub'])
                {{-- The comparison line, only when the number is big enough to deserve one. --}}
                <p class="text-[11px] {{ $sub }} truncate">{{ $tile['sub'] }}</p>
            @endif
        </div>
    @endforeach
</dl>
