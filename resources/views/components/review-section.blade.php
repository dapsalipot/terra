@props(['title', 'step', 'label' => null])

{{--
    A section of the review screen. The Edit button returns to the step that owns this
    content, so a host who spots a wrong price on the last screen does not have to walk
    backwards through every step to reach it.
--}}
<section class="rounded-2xl border border-ink/12 bg-surface overflow-hidden">
    <header class="flex items-center gap-3 px-5 py-3.5 border-b border-ink/8 bg-cream/50">
        <h2 class="flex-1 min-w-0 font-semibold text-lg truncate">{{ $title }}</h2>
        <button type="button" wire:click="goToStep({{ $step }})" dusk="edit-step-{{ $step }}"
                aria-label="Edit {{ $label ?? $title }}"
                class="shrink-0 inline-flex items-center gap-1.5 rounded-full border border-ink/15 px-4 min-h-[40px] text-sm font-semibold hover:border-moss transition-colors">
            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-moss" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/></svg>
            Edit
        </button>
    </header>
    <div class="p-5">{{ $slot }}</div>
</section>
