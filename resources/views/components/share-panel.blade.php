@props([
    'url',              // the address to hand out
    'title',            // what it is called: the group's label, and the title on the phone's share sheet
    'qr' => null,       // where that address's QR image is served; no QR link without one
    'open' => false,    // start open, for the page a host lands on straight after publishing
])

{{--
    Where a host gets the link to one of their pages, to hand out.

    A button that opens a small panel with the address to copy, the phone's own share sheet where the browser has
    one, a Facebook share and, when it is given a `qr`, that code to download. Escape, a click anywhere else and
    keyboard focus moving on to anything outside it all close it, so it never sits over the tab stops after it.
    Nothing is sent through Tarana: Facebook opens in the host's own tab and the share sheet is their phone's.

    The panel exists only while it is open (x-if, not x-show). My trips draws one per schedule, and x-anchor keeps a
    panel's position current as the page scrolls and resizes, which is not worth doing for those nobody is looking at.
    x-anchor also flips and slides it back inside the screen, so it fits at 320px beside a button at either edge.

    Copy asks the clipboard API, which only a secure page has; anywhere else, or if it refuses, it selects the field
    and has the browser copy that instead. The share sheet likewise only exists on some browsers, so its row is found
    out on the page (canShare) rather than assumed.

    The url and the title are written into the Alpine state with @js, which leaves nothing in them that can end the
    attribute or the string. Everywhere else they go through {{ }} or urlencode().

    It is keyed by its address. Livewire keeps a component's Alpine state with the element it was on, so without a key a
    re-render that moves schedules around could hand one trip's panel, and the link its Copy puts on the clipboard, to
    another.

    This only draws the panel. Whose it is to see is for the page that places it to decide.
--}}
@php
    $id = 'share-'.substr(md5($url), 0, 8);
    $row = 'flex w-full items-center gap-3 rounded-xl px-2 min-h-[44px] text-left text-sm font-semibold hover:bg-ink/5 transition-colors';
@endphp

<div x-data="{
        open: {{ $open ? 'true' : 'false' }},
        url: @js($url),
        title: @js($title),
        canShare: false,
        note: '',
        timer: null,
        copy() {
            const done = () => this.say('Copied');
            const select = () => { this.$refs.field.select(); document.execCommand('copy') ? done() : this.say('Select the link, then copy it'); };
            navigator.clipboard ? navigator.clipboard.writeText(this.url).then(done, select) : select();
        },
        async share() {
            try {
                await navigator.share({ title: this.title, url: this.url });
            } catch (e) {
                // Closing the sheet is an answer, not a failure. Anything else is one, and a button that just does nothing is worse.
                if (e.name !== 'AbortError') this.say('Could not open sharing. Copy the link instead.', 6000);
            }
        },
        say(text, ms = 2500) {
            clearTimeout(this.timer);
            this.note = text;
            this.timer = setTimeout(() => this.note = '', ms);
        },
    }"
     x-init="canShare = typeof navigator.share === 'function'"
     @click.outside="open = false"
     @focusin.window="if (open && ! $el.contains($event.target)) open = false"
     @keydown.escape.window="if (open) { open = false; if ($el.contains(document.activeElement)) $refs.trigger.focus(); }"
     {{ $attributes->class('relative inline-flex')->merge(['wire:key' => $id]) }}>
    <button type="button" x-ref="trigger" dusk="share-toggle"
            @click="open = ! open" aria-label="Share {{ $title }}" aria-expanded="{{ $open ? 'true' : 'false' }}" :aria-expanded="open" aria-controls="{{ $id }}"
            class="inline-flex items-center gap-1.5 rounded-full border border-ink/15 bg-surface px-4 min-h-[44px] text-sm font-semibold hover:border-moss transition-colors">
        <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V4m0 0L8 8m4-4 4 4M5 13v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg>
        Share
    </button>

    <template x-if="open">
        <div id="{{ $id }}" role="group" aria-label="Share {{ $title }}" x-anchor.bottom-start.offset.8="$refs.trigger"
             class="absolute z-20 w-[22rem] max-w-[calc(100vw-2rem)] rounded-2xl border border-ink/10 bg-surface p-4 shadow-[0_24px_60px_-20px_rgba(31,28,21,.45)]">
            <div class="flex items-center justify-between gap-3 mb-1.5">
                <label for="{{ $id }}-url" class="text-xs font-semibold text-ink/65">Link</label>
                {{-- Said aloud as well as shown: the one place a copy that worked, or did not, is told. --}}
                <span class="text-xs font-semibold text-pine text-right" role="status" aria-live="polite" x-text="note"></span>
            </div>
            <div class="flex gap-2">
                <input id="{{ $id }}-url" type="text" readonly value="{{ $url }}" dusk="share-url" x-ref="field" @focus="$el.select()"
                       class="min-w-0 flex-1 rounded-xl border-ink/15 bg-canvas px-3 min-h-[44px] text-sm focus:border-moss focus:ring-1 focus:ring-moss">
                <button type="button" dusk="share-copy" @click="copy()"
                        class="shrink-0 rounded-xl bg-pine text-cream px-4 min-h-[44px] text-sm font-semibold hover:bg-pine/90 transition-colors">Copy</button>
            </div>

            <div class="mt-3 -mx-2 flex flex-col">
                <button type="button" x-show="canShare" dusk="share-native" @click="share()" class="{{ $row }}">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-ink/65" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V4m0 0L8 8m4-4 4 4M5 13v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg>
                    Share…
                </button>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($url) }}" target="_blank" rel="noopener noreferrer" dusk="share-facebook" class="{{ $row }}">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-ink/65" fill="currentColor" aria-hidden="true"><path d="M14 8.5V7c0-.7.2-1 1.1-1H17V3.2C16.6 3.1 15.7 3 14.7 3 12.2 3 10.5 4.5 10.5 7.2v1.3H8V12h2.5v9H14v-9h2.6l.4-3.5z"/></svg>
                    Facebook<span class="sr-only"> (opens in a new tab)</span>
                </a>
                @if($qr)
                    <a href="{{ $qr }}" download dusk="share-qr" class="{{ $row }}">
                        <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-ink/65" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z"/></svg>
                        Download QR code
                    </a>
                @endif
            </div>
        </div>
    </template>
</div>
