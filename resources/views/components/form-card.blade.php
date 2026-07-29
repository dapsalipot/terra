@props([
    'title',
    'summary' => null,      // one-line state shown while collapsed — the whole point of the pattern
    'badge' => null,        // short count, e.g. "6 added"
    'open' => false,
    'optional' => false,
    'icon' => null,         // 24px path data
])

@php($id = 'card-'.\Illuminate\Support\Str::slug($title))

{{--
    Collapsible section. A long form is only usable if the parts you are not
    filling in right now collapse to a single readable line, so the summary is
    required reading, not decoration.
--}}
<section x-data="{ open: @js($open) }" class="rounded-2xl border border-ink/10 bg-surface overflow-hidden transition-shadow"
         :class="open && 'shadow-[0_6px_28px_-18px_rgba(31,28,21,.45)]'">
    <h3>
        <button type="button" x-on:click="open = !open" :aria-expanded="open ? 'true' : 'false'" aria-controls="{{ $id }}" dusk="{{ $id }}"
                class="w-full flex items-center gap-3.5 px-5 py-4 min-h-[64px] text-left hover:bg-ink/[.02] transition-colors">
            @if($icon)
                <span class="grid place-items-center w-9 h-9 rounded-xl shrink-0 transition-colors"
                      :class="open ? 'bg-moss text-cream' : 'bg-moss/10 text-moss'">
                    <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                </span>
            @endif

            <span class="flex-1 min-w-0">
                <span class="block font-semibold leading-tight">
                    {{ $title }}
                    @if($optional)<span class="font-normal text-ink/50 text-sm">— optional</span>@endif
                </span>
                @if($summary)
                    <span x-show="!open" class="block text-sm text-ink/60 truncate mt-0.5">{{ $summary }}</span>
                @endif
            </span>

            @if($badge)
                <span x-show="!open" class="shrink-0 rounded-full bg-moss/12 text-pine px-2.5 py-1 text-xs font-bold">{{ $badge }}</span>
            @endif

            <svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0 text-ink/40 transition-transform duration-200" :class="open && 'rotate-180'"
                 fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </button>
    </h3>

    <div x-show="open" x-collapse id="{{ $id }}" x-cloak>
        <div class="px-5 pb-5 pt-1 border-t border-ink/8">{{ $slot }}</div>
    </div>
</section>
