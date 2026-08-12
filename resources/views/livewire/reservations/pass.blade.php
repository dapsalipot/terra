@php($passes = $reservation->participants->filter(fn ($p) => $p->pass_code)->values())

{{--
    The passes on their own page.

    They live inline on the booking now — that is where a joiner meets them — so this route exists
    for the two things a page can do that a section cannot: print cleanly, and be a link somebody
    saved to their home screen. The pass itself is the shared component, so the two never diverge.
--}}
<div class="w-full max-w-2xl mx-auto px-4 sm:px-6 py-8 print:py-0">
    <div class="flex items-center justify-between gap-4 mb-6 print:hidden">
        <a href="{{ route('reservations.show', ['token' => $reservation->public_token]) }}" wire:navigate
           class="inline-flex items-center min-h-[44px] px-2 -mx-2 text-sm text-ink/70 hover:text-ink">← Your booking</a>
        <button type="button" onclick="window.print()" dusk="print-pass"
                class="rounded-full border border-ink/20 px-4 min-h-[44px] inline-flex items-center gap-2 text-sm font-semibold hover:border-ink/40 transition-colors">
            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4v-6h16v6h-2M8 14h8v7H8z"/></svg>
            Print
        </button>
    </div>

    <div class="space-y-6">
        @foreach($passes as $p)
            <x-check-in-pass :participant="$p" :reservation="$reservation" />
        @endforeach
    </div>

    <p class="text-xs text-ink/70 text-center mt-6 print:hidden">
        One pass per traveller. Screenshot or print them — the trailhead may have no signal.
    </p>
</div>
