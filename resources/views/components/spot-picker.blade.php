@props(['kind', 'presets', 'label'])

@php($id = 'spot-'.$kind)

{{--
    "Add a pickup point" used to drop a blank row and hide the saved ones behind a small
    select inside it. A host with a library had to add, then hunt, then pick. This asks the
    question first: blank, or one you have used before.
--}}
<div x-data="{ open: false }" @keydown.escape.window="open = false">
    <button type="button" x-on:click="open = true" dusk="add-{{ $kind }}"
            class="inline-flex items-center gap-2 mt-4 rounded-xl border border-ink/15 px-4 min-h-[44px] font-semibold text-moss hover:border-moss transition-colors">
        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        {{ $label }}
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
            <div x-show="open" x-transition.opacity x-on:click="open = false" class="absolute inset-0 bg-ink/50"></div>

            <div x-show="open" x-transition
                 class="relative w-full max-w-lg max-h-[80vh] overflow-y-auto rounded-2xl border border-ink/12 bg-surface shadow-2xl">
                <header class="flex items-center gap-3 px-5 py-4 border-b border-ink/8 sticky top-0 bg-surface">
                    <h3 id="{{ $id }}-title" class="flex-1 font-serif font-semibold text-xl">{{ $label }}</h3>
                    <button type="button" x-on:click="open = false" aria-label="Close"
                            class="grid place-items-center w-11 h-11 rounded-lg text-ink/65 hover:text-ink hover:bg-ink/5">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </header>

                <div class="p-5">
                    <button type="button" wire:click="addSpot('{{ $kind }}')" x-on:click="open = false"
                            dusk="{{ $kind }}-blank"
                            class="flex w-full items-center gap-3 rounded-xl border-2 border-ink/12 px-4 py-4 text-left hover:border-moss transition-colors">
                        <span class="grid place-items-center w-10 h-10 rounded-xl bg-moss/10 text-moss shrink-0">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        </span>
                        <span>
                            <span class="block font-semibold">Start blank</span>
                            <span class="block text-sm text-ink/65">Type a new spot from scratch</span>
                        </span>
                    </button>

                    @if($presets->isNotEmpty())
                        <p class="text-sm font-semibold text-ink/70 mt-6 mb-2">Or use one you've saved</p>
                        <div class="space-y-2">
                            @foreach($presets as $preset)
                                <button type="button" wire:click="addSpot('{{ $kind }}', {{ $preset->id }})" x-on:click="open = false"
                                        dusk="{{ $kind }}-preset-{{ $preset->id }}"
                                        class="flex w-full items-start gap-3 rounded-xl border border-ink/12 px-4 py-3.5 text-left hover:border-moss transition-colors">
                                    <svg viewBox="0 0 24 24" class="w-5 h-5 mt-0.5 shrink-0 text-terra" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                                    <span class="min-w-0">
                                        <span class="block font-semibold truncate">{{ $preset->label }}</span>
                                        @if($preset->address)<span class="block text-sm text-ink/65 truncate">{{ $preset->address }}</span>@endif
                                        @if($preset->lat && $preset->lng)<span class="block text-xs text-moss font-semibold">pinned</span>@endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-ink/65 mt-6">
                            You have no saved spots yet. Tick <b class="font-semibold">Save this spot to my library</b> on one
                            and it will show up here next time.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </template>
</div>
