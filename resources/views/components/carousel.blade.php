@props(['images', 'ratio' => 'aspect-[4/3]', 'rounded' => ''])
{{-- $images: iterable of ['url' => string, 'alt' => ?string] --}}
@php($items = collect($images)->values())

@if($items->count() === 1)
    <img src="{{ $items[0]['url'] }}" alt="{{ $items[0]['alt'] ?? '' }}"
         class="w-full {{ $ratio }} object-cover {{ $rounded }}" loading="lazy">
@elseif($items->count() > 1)
    <div x-data="{ i: 0, count: {{ $items->count() }} }" class="relative group {{ $rounded }} overflow-hidden">
        {{-- track --}}
        <div class="flex transition-transform duration-500 ease-[cubic-bezier(.2,.7,.2,1)]"
             :style="`transform: translateX(-${i * 100}%)`">
            @foreach($items as $img)
                <img src="{{ $img['url'] }}" alt="{{ $img['alt'] ?? '' }}"
                     class="w-full shrink-0 {{ $ratio }} object-cover" loading="lazy">
            @endforeach
        </div>

        {{-- arrows --}}
        <button type="button" x-show="i > 0" x-transition.opacity
                @click="i--" aria-label="Previous image"
                class="absolute left-3 top-1/2 -translate-y-1/2 grid place-items-center w-9 h-9 rounded-full bg-ink/45 backdrop-blur text-cream hover:bg-ink/65 transition-colors">
            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 5.5L8 12l6.5 6.5"/></svg>
        </button>
        <button type="button" x-show="i < count - 1" x-transition.opacity
                @click="i++" aria-label="Next image"
                class="absolute right-3 top-1/2 -translate-y-1/2 grid place-items-center w-9 h-9 rounded-full bg-ink/45 backdrop-blur text-cream hover:bg-ink/65 transition-colors">
            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.5 5.5L16 12l-6.5 6.5"/></svg>
        </button>

        {{-- counter --}}
        <span class="absolute top-3 right-3 rounded-full bg-ink/50 backdrop-blur px-2.5 py-1 text-[11px] font-semibold text-cream"
              x-text="`${i + 1}/${count}`"></span>

        {{-- dots --}}
        <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5">
            @foreach($items as $k => $img)
                <button type="button" @click="i = {{ $k }}" aria-label="Go to image {{ $k + 1 }}"
                        class="h-1.5 rounded-full transition-all"
                        :class="i === {{ $k }} ? 'w-5 bg-cream' : 'w-1.5 bg-cream/55'"></button>
            @endforeach
        </div>
    </div>
@endif
