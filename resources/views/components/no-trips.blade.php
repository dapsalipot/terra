@props(['compact' => false])

{{-- compact is for the calendar, where this sits *under* a rendered grid rather than in place of
     one, so it needs less vertical weight and no illustration. --}}
<div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 text-center bg-ink/[.01] {{ $compact ? 'py-10 mt-4' : 'py-12' }}">
    @unless($compact)
        <div class="w-16 h-16 rounded-full bg-ink/[.04] grid place-items-center mx-auto mb-4">
            <svg viewBox="0 0 24 24" class="w-8 h-8 text-ink/30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        </div>
    @endunless
    <p class="font-semibold mb-1">No trips booked yet</p>
    <p class="text-ink/65 text-sm mb-5">Find a group heading somewhere you want to go.</p>
    <a href="{{ route('trips.index') }}" wire:navigate
       class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors shadow-sm">Explore trips</a>
</div>
