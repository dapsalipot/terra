@props(['on' => false, 'compact' => false])

{{--
    Marks a value the app filled in from the destination record rather than one the host
    typed. Without it a prefilled number reads as something they already entered and
    checked — which is exactly how a wrong height ends up published.

    `compact` is the icon-only form for narrow numeric columns, where the full pill wraps
    to three lines and drags its field out of alignment with its neighbours.
--}}
@if($on)
    @if($compact)
        <span class="grid place-items-center w-4 h-4 shrink-0 rounded-full bg-moss text-cream"
              title="Filled in from the destination — edit it if it's wrong">
            <svg viewBox="0 0 24 24" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
            <span class="sr-only">Filled in for you from the destination</span>
        </span>
    @else
        <span class="inline-flex items-center gap-1 rounded-full bg-moss/12 text-pine px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide whitespace-nowrap"
              title="Filled in from the destination — edit it if it's wrong">
            <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
            Filled for you
        </span>
    @endif
@endif
