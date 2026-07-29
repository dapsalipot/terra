@props(['places', 'destinations'])
@php($chosen = collect($destinations)->pluck('place_id')->filter()->all())

{{--
    Search only. The chosen destinations are rendered by the wizard itself, because each
    one now owns its own nights and its own trail detail — content this component has no
    business knowing about.
--}}
<div x-data="{ q: '', open: false }" class="relative">
    <input x-model="q" @focus="open = true" @click.outside="open = false" type="text" dusk="destination-search"
           placeholder="Search a destination — try &ldquo;pulag&rdquo;" class="field">

    <div x-show="open && q.length > 0" x-transition.opacity x-cloak
         class="absolute z-20 inset-x-0 mt-1 max-h-72 overflow-y-auto rounded-xl border border-ink/12 bg-surface shadow-lg">
        @foreach($places as $place)
            @continue(in_array($place->id, $chosen, true))
            <button type="button" wire:click="addDestination({{ $place->id }})" @click="q = ''; open = false"
                    dusk="place-option-{{ $place->id }}"
                    x-show="'{{ Str::lower($place->name.' '.$place->country) }}'.includes(q.toLowerCase())"
                    class="flex w-full items-center gap-3 px-4 py-3.5 text-left hover:bg-ink/[.03]">
                <span class="font-semibold">{{ $place->name }}</span>
                <span class="text-sm text-ink/65">{{ $place->country }}</span>
                <span class="ml-auto text-[11px] font-bold uppercase tracking-wide text-ink/65">{{ $place->category }}</span>
            </button>
        @endforeach
    </div>
</div>
