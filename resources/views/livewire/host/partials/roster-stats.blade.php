{{--
    The money, in two shapes.

    Desktop keeps the four figures as cards. Mobile gets one line of plain text — cards there
    pushed the first traveller below the fold, which on the device you use at a trailhead is the
    opposite of what the page is for.
--}}
<div class="sm:hidden text-sm text-ink/70 mb-4" dusk="roster-stats-mobile">
    <span class="font-semibold text-ink">{{ $departure->heldCount() }}/{{ $departure->capacity }}</span> seats ·
    {{ $fmt($money['collected']) }} in ·
    <span class="font-semibold {{ $money['outstanding'] > 0 ? 'text-terra' : 'text-moss' }}">{{ $fmt($money['outstanding']) }} out</span>
    @if($mode === 'checkin')
        · {{ $arrivedCount }}/{{ $travellerCount }} arrived
    @endif
</div>

<div class="hidden sm:grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5" dusk="roster-stats">
    @foreach([
        ['Seats held', $departure->heldCount().'/'.$departure->capacity, null, false],
        ['Expected', $fmt($money['expected']), 'if everyone travels', false],
        ['Collected', $fmt($money['collected']), 'confirmed payments', false],
        ['Outstanding', $fmt($money['outstanding']), 'still to collect', $money['outstanding'] > 0],
    ] as [$label, $value, $sub, $warn])
        <div class="rounded-2xl p-4 {{ $warn ? 'bg-terra/10' : 'bg-ink/[.03]' }}">
            <p class="text-[10px] uppercase tracking-[0.09em] font-semibold {{ $warn ? 'text-terra' : 'text-ink/70' }}">{{ $label }}</p>
            <p class="font-serif font-semibold text-2xl leading-tight mt-1 tabular-nums {{ $warn ? 'text-terra' : 'text-ink' }}">{{ $value }}</p>
            @if($sub)<p class="text-xs text-ink/70">{{ $sub }}</p>@endif
        </div>
    @endforeach
</div>
