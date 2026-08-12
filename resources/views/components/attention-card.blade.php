@props(['item', 'dismissible' => false])

@php
    $tone = [
        'pay' => ['bg-terra/[.06] border-terra/20', 'text-terra'],
        'balance' => ['bg-amber/[.07] border-amber/25', 'text-amber'],
        'details' => ['bg-amber/[.07] border-amber/25', 'text-amber'],
        'waiting' => ['bg-ink/[.03] border-ink/8', 'text-ink/55'],
        'review' => ['bg-moss/[.07] border-moss/20', 'text-moss'],
    ][$item['kind']] ?? ['bg-ink/[.03] border-ink/8', 'text-ink/60'];

    $icon = [
        'pay' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/>',
        'balance' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'details' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
        'waiting' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'review' => '<path d="M12 2l3 6.5 7 .9-5 4.8 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.8 7-.9Z"/>',
    ][$item['kind']] ?? '';
@endphp

{{--
    One card for every to-do on this dashboard.

    Pulled out of the old "Needs you" stack when these became tabs: the same markup now renders in
    three different panels, and three copies of it is three places for the dismiss button to drift.
    Stacked rather than a horizontal row — these sit in a 22rem sidebar on wide screens, where an
    inline CTA crushed the text to about 110px and wrapped every title three lines deep.
--}}
<div class="rounded-2xl p-4 border {{ $tone[0] }}"
     dusk="attention-{{ $item['kind'] }}-{{ $item['reservation_id'] }}">
    <div class="flex items-start gap-3">
        <div class="shrink-0 mt-0.5 {{ $tone[1] }}">
            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
        </div>
        <div class="min-w-0 flex-1">
            <p class="font-semibold leading-snug">{{ $item['title'] }}</p>
            <p class="text-sm text-ink/70 mt-1">{{ $item['detail'] }}</p>
        </div>

        {{-- Nothing time-sensitive gets a dismiss control: a button that hides an unpaid deadline
             is an affordance that costs somebody their place on a trip. --}}
        @if($dismissible && ! $item['urgent'])
            <button type="button" wire:click="dismissAttention('{{ $item['kind'] }}')"
                    class="shrink-0 -mt-1 -mr-1 text-ink/35 hover:text-ink/70 p-2 rounded-lg hover:bg-ink/5 transition-colors"
                    aria-label="Dismiss">
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        @endif
    </div>

    <a href="{{ $item['href'] }}" wire:navigate
       class="mt-3 flex items-center justify-center rounded-full px-5 min-h-[44px] text-sm font-semibold transition-colors
              {{ $item['urgent'] ? 'bg-pine text-cream hover:bg-pine/90 shadow-sm' : 'border border-ink/20 hover:border-ink/40 bg-surface' }}">
        {{ $item['cta'] }}
    </a>
</div>
