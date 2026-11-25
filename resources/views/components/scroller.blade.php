@props(['title', 'href' => null])

{{--
    A titled row that scrolls sideways: swipe on a phone, arrows beside the title from md up.

    The arrows live in this component, not in the page, because their disabled state depends on where
    the row is scrolled to. That is read off the row's own scroll event (an element's, not the window's),
    which is cheap and fires only while this row moves.
--}}
<section {{ $attributes->class('mx-auto max-w-7xl px-6') }}
         x-data="{
            atStart: true, atEnd: false,
            sync() {
                const row = this.$refs.row;
                this.atStart = row.scrollLeft < 8;
                this.atEnd = row.scrollLeft + row.clientWidth >= row.scrollWidth - 8;
            },
            by(direction) { this.$refs.row.scrollBy({ left: direction * this.$refs.row.clientWidth * .9, behavior: 'smooth' }); },
         }"
         x-init="sync()">
    <div class="flex items-end justify-between gap-4">
        <h2 class="text-xl font-semibold tracking-tight md:text-2xl">
            @if($href)
                <a href="{{ $href }}" class="inline-flex items-center gap-1.5 hover:underline underline-offset-4">
                    {{ $title }}
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                </a>
            @else
                {{ $title }}
            @endif
        </h2>

        <div class="hidden gap-2 md:flex">
            @foreach([['Scroll back', -1, 'M15 6l-6 6 6 6', 'atStart'], ['Scroll forward', 1, 'M9 6l6 6-6 6', 'atEnd']] as [$label, $direction, $path, $flag])
                <button type="button" aria-label="{{ $label }}" :disabled="{{ $flag }}" @click="by({{ $direction }})"
                        class="grid h-9 w-9 place-items-center rounded-full border border-ink/15 bg-surface text-ink transition hover:shadow-md active:scale-95 disabled:cursor-default disabled:opacity-30 disabled:hover:shadow-none">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
                </button>
            @endforeach
        </div>
    </div>

    <div x-ref="row" @scroll.passive.throttle.100ms="sync()" tabindex="0" role="region" aria-label="{{ $title }}"
         class="no-scrollbar -mx-6 mt-4 flex snap-x snap-mandatory gap-5 overflow-x-auto scroll-px-6 px-6 pb-3 focus-visible:outline-none">
        {{ $slot }}
    </div>
</section>
