@php($trip = $departure->trip)

{{--
    The scanner.

    Camera decoding happens in the browser via BarcodeDetector and the component only ever
    receives a string, so a typed code and a scanned one travel exactly the same path. That is
    deliberate: BarcodeDetector is not in Safari, and a trailhead is the last place to discover
    your only check-in method needs a specific browser. Manual entry is not a fallback bolted on,
    it is the primary path that scanning feeds.
--}}
<div x-data="{
        scanning: false,
        supported: 'BarcodeDetector' in window,
        error: '',
        stream: null,
        async start() {
            this.error = '';
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                this.$refs.video.srcObject = this.stream;
                await this.$refs.video.play();
                this.scanning = true;
                this.loop();
            } catch (e) {
                this.error = 'No camera available — type the code instead.';
            }
        },
        stop() {
            this.scanning = false;
            this.stream?.getTracks().forEach(t => t.stop());
            this.stream = null;
        },
        async loop() {
            if (!this.scanning) return;
            try {
                const detector = new BarcodeDetector({ formats: ['qr_code'] });
                const codes = await detector.detect(this.$refs.video);
                if (codes.length) {
                    const value = codes[0].rawValue;
                    this.stop();
                    $wire.set('code', value).then(() => $wire.scan());
                    return;
                }
            } catch (e) { /* a frame that will not decode is not an error worth showing */ }
            requestAnimationFrame(() => this.loop());
        }
     }"
     x-on:livewire:navigating.window="stop()"
     class="max-w-2xl mx-auto">

    <div class="mb-6">
        <a href="{{ route('host.roster', $departure) }}" wire:navigate class="inline-flex items-center py-1.5 -my-1.5 text-sm text-ink/65 hover:text-ink">← Roster</a>
        <h1 class="tracking-tight font-semibold text-3xl mt-2">Check-in</h1>
        <p class="text-ink/65">{{ $trip->title }} · {{ $departure->starts_at->format('D, j M Y') }}</p>
    </div>

    <div class="rounded-2xl border border-ink/10 bg-surface p-5 mb-5">
        <div class="flex items-baseline justify-between gap-4 mb-4">
            <p class="tracking-tight font-semibold text-2xl tabular-nums">{{ $inCount }} <span class="text-ink/65 font-normal">of {{ $participants->count() }}</span></p>
            <p class="text-sm text-ink/65">checked in</p>
        </div>

        {{-- Camera, when the browser can decode. --}}
        <template x-if="supported">
            <div>
                <div x-show="scanning" x-cloak class="relative rounded-xl overflow-hidden bg-ink">
                    <video x-ref="video" playsinline muted class="w-full aspect-[4/3] object-cover"></video>
                    <div class="absolute inset-8 border-2 border-cream/70 rounded-xl pointer-events-none" aria-hidden="true"></div>
                </div>

                <button type="button" x-show="!scanning" x-on:click="start()" dusk="start-camera"
                        class="w-full rounded-full bg-pine text-cream min-h-[52px] font-semibold hover:bg-pine/90 transition-colors">
                    Scan a pass
                </button>
                <button type="button" x-show="scanning" x-cloak x-on:click="stop()"
                        class="w-full mt-3 rounded-full border border-ink/20 min-h-[44px] font-semibold hover:border-ink/40 transition-colors">
                    Stop the camera
                </button>
            </div>
        </template>

        <template x-if="!supported">
            <p class="rounded-xl bg-amber/12 text-ink/75 text-sm px-4 py-3">
                This browser cannot open the camera for scanning. Type the code from the pass instead — it works the same.
            </p>
        </template>

        <p x-show="error" x-cloak x-text="error" class="text-sm text-terra mt-3"></p>

        {{-- Typed entry, always present. --}}
        <form wire:submit="scan" class="mt-4 flex gap-2">
            <input wire:model="code" dusk="code-input" placeholder="TRA-XXXXXXXXXXXX"
                   class="field font-mono uppercase flex-1" autocomplete="off" spellcheck="false">
            <button class="rounded-full border border-ink/20 px-5 min-h-[44px] font-semibold hover:border-ink/40 transition-colors" dusk="check-in">
                Check in
            </button>
        </form>

        @if($lastMessage)
            <p dusk="scan-result"
               class="mt-4 rounded-xl px-4 py-3 text-sm font-semibold {{ $lastOk ? 'bg-moss/12 text-pine' : 'bg-terra/10 text-terra-deep' }}">
                {{ $lastMessage }}
            </p>
        @endif
    </div>

    {{-- The manifest, so a host can also just tick people off by eye. --}}
    <div class="rounded-2xl border border-ink/10 bg-surface overflow-hidden">
        <table class="w-full text-sm">
            <caption class="sr-only">Everyone expected on this departure</caption>
            <thead>
                <tr class="bg-ink/[.03] text-left">
                    <th scope="col" class="px-4 py-2.5 text-[11px] uppercase tracking-wide text-ink/65 font-semibold">Traveller</th>
                    <th scope="col" class="px-4 py-2.5 text-[11px] uppercase tracking-wide text-ink/65 font-semibold text-right">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($participants as $p)
                    <tr class="border-t border-ink/8">
                        <th scope="row" class="px-4 py-3 text-left font-normal">
                            <span class="font-semibold">{{ $p->fullName() ?: 'Unnamed traveller' }}</span>
                            @unless($p->pass_code)
                                <span class="block text-xs text-ink/65">No pass — not marked paid yet</span>
                            @endunless
                        </th>
                        <td class="px-4 py-3 text-right">
                            @if($p->checked_in_at)
                                <span class="inline-flex items-center gap-2">
                                    <x-badge tone="moss">In · {{ $p->checked_in_at->format('g:ia') }}</x-badge>
                                    <button wire:click="undo({{ $p->id }})" dusk="undo-{{ $p->id }}"
                                            class="text-[11px] font-semibold text-ink/65 hover:text-terra underline decoration-ink/20">Undo</button>
                                </span>
                            @else
                                <x-badge tone="ink">Expected</x-badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-4 py-10 text-center text-ink/65">Nobody is booked on this departure yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
