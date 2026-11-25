@php($currency = auth()->user()->hostProfile?->currency ?? 'PHP')
@php($firstPlace = count($destinations) ? $places->firstWhere('id', $destinations[0]['place_id']) : null)
@php($steps = [
    1 => $this->isHike ? 'Where & the trail' : 'Where you\'re going',
    2 => 'The trip',
    3 => "What's included",
    4 => 'Logistics',
    5 => 'Publish',
])

{{-- Rail + form. No preview column: the form gets everything the rail does not. --}}
<div class="grid lg:grid-cols-[13rem_minmax(0,1fr)] gap-10 items-start">

    {{-- ══ RAIL ══ --}}
    <nav class="hidden lg:block sticky top-8" aria-label="Wizard steps">
        <ol class="space-y-1">
            @foreach($steps as $n => $name)
                <li class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ $step === $n ? 'bg-ink/[.05]' : '' }}">
                    <span class="grid place-items-center w-6 h-6 rounded-full text-[11px] font-bold shrink-0
                                 {{ $step > $n ? 'bg-moss text-cream' : ($step === $n ? 'bg-pine text-cream' : 'bg-ink/10 text-ink/65') }}">
                        @if($step > $n)
                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                        @else{{ $n }}@endif
                    </span>
                    <span class="text-sm font-semibold {{ $step === $n ? 'text-ink' : 'text-ink/65' }}">{{ $name }}</span>
                </li>
            @endforeach
        </ol>
    </nav>

    {{-- ══ FORM ══ --}}
    {{-- min-w-0: a grid item defaults to min-width:auto, so the intrinsic width of the
         text inputs inside would prop this column open past the viewport on a phone. --}}
    <div class="min-w-0">
        {{-- Mobile stepper: the desktop rail is hidden below lg, so without this a phone user
             has no idea how many steps remain. Progress is the whole point of a wizard. --}}
        <div class="lg:hidden mb-6" role="group" aria-label="Wizard progress">
            <div class="flex items-baseline justify-between mb-2">
                <span class="text-sm font-semibold">{{ $steps[$step] }}</span>
                <span class="text-xs text-ink/65">Step {{ $step }} of {{ count($steps) }}</span>
            </div>
            <div class="flex gap-1.5" aria-hidden="true">
                @foreach($steps as $n => $name)
                    <span class="h-1.5 flex-1 rounded-full {{ $step >= $n ? 'bg-moss' : 'bg-ink/12' }}"></span>
                @endforeach
            </div>
        </div>
        {{-- Work is saved on every change, so say so — otherwise a host has no reason to
             trust the form with twenty minutes of typing. --}}
        @if($draftRestored)
            <div class="flex items-start gap-3 rounded-2xl border border-amber/30 bg-amber/[.07] px-4 py-3 mb-6" role="status">
                <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 mt-0.5 text-amber" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v4h4"/></svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold">Picked up where you left off</p>
                    <p class="text-sm text-ink/70">Your unfinished trip was saved automatically.</p>
                </div>
                <button type="button" wire:click="discardDraft" dusk="discard-draft"
                        class="shrink-0 text-sm font-semibold text-ink/70 hover:text-terra underline">Start fresh</button>
            </div>
        @endif

        @if($step === 1)
            @if($this->isEditing)
                <p class="inline-flex items-center gap-2 rounded-full bg-moss/12 text-pine px-4 py-2 text-sm font-semibold mb-4">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/></svg>
                    Editing one schedule
                </p>
            @endif
            <h1 class="tracking-tight font-semibold text-3xl mb-1">Where are you going?</h1>
            <p class="text-ink/65 mb-7">Add each stop, how long you spend there, and — for a hike — the trail itself.</p>

            @if($pastTrips->isNotEmpty() && ! count($destinations))
                <div class="rounded-2xl border border-ink/10 bg-cream px-5 py-4 mb-5">
                    <p class="font-semibold mb-0.5">Run one of these again?</p>
                    <p class="text-sm text-ink/65 mb-3">Copies everything but the dates.</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($pastTrips as $past)
                            <a href="{{ route('host.trips.duplicate', $past) }}" wire:navigate dusk="reuse-{{ $past->id }}"
                               class="inline-flex items-center gap-2 rounded-full border border-ink/15 bg-surface px-4 min-h-[44px] text-sm font-semibold hover:border-moss transition-colors">
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 9h10v10H9zM5 15V5h10"/></svg>
                                {{ $past->title }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Each stop is its own card: its days and its trail belong to the destination,
                 so they live inside it rather than floating as separate sections. --}}
            <div class="space-y-4">
                @foreach($destinations as $i => $row)
                    @php($place = $places->firstWhere('id', $row['place_id']))
                    @php($isHike = $place?->category === 'hike')
                    @php($h = $this->hikeFor($row['place_id']))
                    <section class="rounded-2xl border border-ink/12 bg-surface overflow-hidden" wire:key="dest-{{ $row['place_id'] }}">

                        <header class="flex items-center gap-3 px-5 py-4 border-b border-ink/8 bg-cream/50">
                            <span class="grid place-items-center w-8 h-8 rounded-full bg-moss text-cream text-sm font-bold shrink-0">{{ $i + 1 }}</span>
                            <span class="flex-1 min-w-0">
                                <span class="block tracking-tight font-semibold text-xl truncate">{{ $place?->name }}</span>
                                <span class="block text-sm text-ink/65 truncate">{{ $place?->country }} · {{ ucfirst($place?->category) }}</span>
                            </span>
                            <span class="flex items-center gap-0.5 shrink-0">
                                <button type="button" wire:click="moveDestination({{ $i }}, -1)" @disabled($i === 0)
                                        aria-label="Move {{ $place?->name }} earlier" class="grid place-items-center w-11 h-11 rounded-lg text-ink/65 hover:text-ink hover:bg-ink/5 disabled:opacity-30">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 14l6-6 6 6"/></svg>
                                </button>
                                <button type="button" wire:click="moveDestination({{ $i }}, 1)" @disabled($i === count($destinations) - 1)
                                        aria-label="Move {{ $place?->name }} later" class="grid place-items-center w-11 h-11 rounded-lg text-ink/65 hover:text-ink hover:bg-ink/5 disabled:opacity-30">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 10l6 6 6-6"/></svg>
                                </button>
                                <button type="button" wire:click="removeDestination({{ $i }})" aria-label="Remove {{ $place?->name }}"
                                        class="grid place-items-center w-11 h-11 rounded-lg text-ink/65 hover:text-terra hover:bg-ink/5">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            </span>
                        </header>

                        <div class="p-5">
                            {{-- Days as a list, not a count. "3 days" tells a joiner nothing;
                                 a line per day is the actual itinerary for this stop. --}}
                            @php($days = $row['days'] ?: [''])
                            <div class="space-y-2.5">
                                @foreach($days as $d => $dayLabel)
                                    <div class="flex items-center gap-3" wire:key="day-{{ $row['place_id'] }}-{{ $d }}">
                                        <span class="w-16 shrink-0 text-sm font-semibold text-ink/70">Day {{ $d + 1 }}</span>
                                        <input wire:model.blur="destinations.{{ $i }}.days.{{ $d }}" type="text"
                                               placeholder="{{ $d === 0 ? 'Jump-off and trek to camp' : 'Summit push and descent' }}"
                                               aria-label="What happens on day {{ $d + 1 }} at {{ $place?->name }}"
                                               dusk="day-{{ $i }}-{{ $d }}" class="field flex-1 min-w-0">
                                        @if(count($days) > 1)
                                            <button type="button" wire:click="removeDay({{ $i }}, {{ $d }})"
                                                    aria-label="Remove day {{ $d + 1 }}"
                                                    class="grid place-items-center w-11 h-11 shrink-0 rounded-lg text-ink/65 hover:text-terra hover:bg-ink/5">
                                                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @error('destinations.'.$i.'.days')<p class="text-terra text-sm mt-2">{{ $message }}</p>@enderror

                            <button type="button" wire:click="addDay({{ $i }})" dusk="add-day-{{ $i }}"
                                    class="inline-flex items-center gap-2 mt-3 rounded-xl border border-ink/15 px-4 min-h-[44px] text-sm font-semibold hover:border-moss transition-colors">
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                Add a day here
                            </button>

                            @if($isHike)
                                {{-- ═══ THE TRAIL — directly under the stop it describes ═══ --}}
                                <div class="mt-6 pt-6 border-t border-ink/10">
                                    <div class="flex items-center gap-2.5 mb-1">
                                        <svg viewBox="0 0 24 24" class="w-5 h-5 text-moss shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M3 20l7-13 4 7 3-5 4 11z"/></svg>
                                        <h3 class="font-semibold text-lg">The trail</h3>
                                        <span class="text-sm text-ink/65">— optional</span>
                                    </div>
                                    <p class="text-sm text-ink/65 mb-5">What hikers want to know about {{ $place?->name }} before they commit.</p>

                                    <div class="grid sm:grid-cols-2 gap-4">
                                        <label class="block min-w-0">
                                            <span class="field-label">Trail / route name</span>
                                            <input wire:model.blur="hikes.{{ $place->id }}.trail_name" type="text" placeholder="Akiki–Ambangeg traverse" class="field">
                                        </label>
                                        <label class="block min-w-0">
                                            <span class="field-label">Jump-off point</span>
                                            <input wire:model.blur="hikes.{{ $place->id }}.jump_off" type="text" placeholder="Brgy. Ambangeg, Bokod" class="field">
                                        </label>
                                    </div>

                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
                                        @foreach([
                                            ['masl', 'MASL', '2,922', 'Summit height'],
                                            ['elevation_gain_m', 'Ascent', '1,450', 'Metres gained'],
                                            ['distance_km', 'Distance', '14.5', 'Kilometres'],
                                            ['duration_hours', 'Duration', '9', 'Hours'],
                                        ] as [$key, $label, $ph, $hint])
                                            <label class="block min-w-0">
                                                <span class="flex items-center gap-1.5 text-sm font-semibold text-ink/70 mb-2 h-5">
                                                    {{ $label }}
                                                    <x-prefill-tag compact :on="in_array('hikes.'.$place->id.'.'.$key, $prefilled, true)" />
                                                </span>
                                                <input wire:model.blur="hikes.{{ $place->id }}.{{ $key }}" type="text" inputmode="decimal" placeholder="{{ $ph }}"
                                                       dusk="hike-{{ $key }}-{{ $place->id }}" class="field">
                                                <span class="block text-xs text-ink/65 mt-1.5">{{ $hint }}</span>
                                                @error('hikes.'.$place->id.'.'.$key)<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="mt-6">
                                        <span class="field-label mb-1">Trail grade <span class="font-normal text-ink/65">— PinoyMountaineer 1–9</span></span>
                                        <p class="text-xs text-ink/65 mb-2.5">1–4 minor climb · 5–8 major climb · 9 hardcore</p>
                                        <div class="grid grid-cols-5 sm:grid-cols-9 gap-2">
                                            @foreach(\App\Support\HikeGrades::DIFFICULTY as $n => $meta)
                                                <button type="button" wire:click="$set('hikes.{{ $place->id }}.difficulty', {{ $n }})" title="{{ $meta['note'] }}"
                                                        dusk="hike-difficulty-{{ $n }}-{{ $place->id }}"
                                                        aria-pressed="{{ (int) ($h['difficulty'] ?? 0) === $n ? 'true' : 'false' }}"
                                                        aria-label="Trail grade {{ $n }} — {{ $meta['label'] }}"
                                                        class="grid place-items-center h-12 rounded-xl font-bold border transition-colors
                                                               {{ (int) ($h['difficulty'] ?? 0) === $n ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">{{ $n }}</button>
                                            @endforeach
                                        </div>
                                        @if(\App\Support\HikeGrades::difficultyNote((int) ($h['difficulty'] ?? 0)))
                                            <p class="text-sm text-ink/70 mt-2.5">
                                                <b>{{ \App\Support\HikeGrades::band((int) $h['difficulty']) }} · {{ \App\Support\HikeGrades::difficultyLabel((int) $h['difficulty']) }}</b>
                                                — {{ \App\Support\HikeGrades::difficultyNote((int) $h['difficulty']) }}
                                            </p>
                                        @endif
                                        @error('hikes.'.$place->id.'.difficulty')<p class="text-terra text-xs mt-2">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="mt-6">
                                        <span class="field-label">Trail class <span class="font-normal text-ink/65">— hardest move on the route</span></span>
                                        <div class="grid grid-cols-5 gap-2 max-w-[20rem]">
                                            @foreach(\App\Support\HikeGrades::TRAIL_CLASS as $n => $meta)
                                                <button type="button" wire:click="$set('hikes.{{ $place->id }}.trail_class', {{ $n }})" title="{{ $meta['note'] }}"
                                                        aria-pressed="{{ (int) ($h['trail_class'] ?? 0) === $n ? 'true' : 'false' }}"
                                                        aria-label="{{ $meta['label'] }} — {{ $meta['note'] }}"
                                                        class="grid place-items-center h-12 rounded-xl font-bold border transition-colors
                                                               {{ (int) ($h['trail_class'] ?? 0) === $n ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">{{ $n }}</button>
                                            @endforeach
                                        </div>
                                        @if(\App\Support\HikeGrades::trailClassNote((int) ($h['trail_class'] ?? 0)))
                                            <p class="text-sm text-ink/70 mt-2.5">{{ \App\Support\HikeGrades::trailClassNote((int) $h['trail_class']) }}</p>
                                        @endif
                                        @error('hikes.'.$place->id.'.trail_class')<p class="text-terra text-xs mt-2">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="mt-6">
                                        <span class="field-label">Trail shape</span>
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                            @foreach(\App\Support\HikeGrades::TRAIL_TYPE as $key => $label)
                                                <button type="button" wire:click="$set('hikes.{{ $place->id }}.trail_type', '{{ $key }}')" dusk="hike-type-{{ $key }}-{{ $place->id }}"
                                                        aria-pressed="{{ ($h['trail_type'] ?? '') === $key ? 'true' : 'false' }}"
                                                        class="rounded-xl px-3 min-h-[48px] text-sm font-semibold border transition-colors
                                                               {{ ($h['trail_type'] ?? '') === $key ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">{{ $label }}</button>
                                            @endforeach
                                        </div>
                                        @error('hikes.'.$place->id.'.trail_type')<p class="text-terra text-xs mt-2">{{ $message }}</p>@enderror
                                    </div>

                                    {{-- Always-on toggle cards. The disclosure that used to hide these
                                         behind an "Edit" link made the selected set hard to read at a
                                         glance and added a click to every change. --}}
                                    <div class="mt-6">
                                        <span class="field-label">What's on the trail</span>
                                        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-2">
                                            @foreach(\App\Support\TrailFeatures::CATALOG as $key => [$label, $note, $icon])
                                                @php($on = in_array($key, $h['features'] ?? [], true))
                                                <button type="button" wire:click="toggleFeature({{ $place->id }}, '{{ $key }}')"
                                                        aria-pressed="{{ $on ? 'true' : 'false' }}"
                                                        dusk="hike-feature-{{ $key }}-{{ $place->id }}"
                                                        class="flex items-start gap-3 text-left rounded-xl border-2 px-4 py-3.5 transition-colors
                                                               {{ $on ? 'border-moss bg-moss/10' : 'border-ink/10 hover:border-ink/25' }}">
                                                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 mt-0.5 {{ $on ? 'text-moss' : 'text-ink/40' }}" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block text-sm font-semibold">{{ $label }}</span>
                                                        <span class="block text-xs text-ink/65 leading-snug">{{ $note }}</span>
                                                    </span>
                                                    <span class="grid place-items-center w-5 h-5 shrink-0 rounded-full mt-0.5 {{ $on ? 'bg-moss text-cream' : 'border border-ink/15' }}">
                                                        @if($on)
                                                            <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12.5l5.5 5.5L20 6.5"/></svg>
                                                        @endif
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="mt-6">
                                        <span class="field-label mb-1">Conditions to expect</span>
                                        <p class="text-xs text-ink/65 mb-2.5">The PULAG factors — each one effectively adds a difficulty point.</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach(\App\Support\HikeGrades::PULAG_FACTORS as $key => $label)
                                                <label class="inline-flex items-center gap-2.5 rounded-xl border border-ink/12 px-4 min-h-[48px] text-sm cursor-pointer hover:border-ink/25">
                                                    <input type="checkbox" wire:model.live="hikes.{{ $place->id }}.pulag_factors" value="{{ $key }}" class="rounded border-ink/25 text-amber focus:ring-amber">
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="mt-4">
                <span class="field-label">{{ count($destinations) ? 'Add another stop' : 'Destinations' }}</span>
                <x-place-picker :places="$places" :destinations="$destinations" />
                @error('destinations')<p class="text-terra text-sm mt-2">{{ $message }}</p>@enderror
            </div>

            @if(count($destinations))
                <p class="text-sm text-ink/65 mt-3">
                    {{ $this->lengthSummary() }} across {{ count($destinations) }}
                    {{ \Illuminate\Support\Str::plural('stop', count($destinations)) }}
                </p>
            @endif

            <button wire:click="next" dusk="wizard-next"
                    class="mt-7 w-full rounded-full bg-pine text-cream py-4 font-semibold text-lg hover:bg-pine/90 transition-colors">Continue</button>
        @elseif($step === 2)
            <h1 class="tracking-tight font-semibold text-3xl mb-1">The trip</h1>
            <p class="text-ink/65 mb-7">How it reads and what it costs.</p>

            <div class="space-y-6">
                <section class="rounded-2xl border border-ink/12 bg-surface p-5">
                    <label class="block">
                        <span class="flex items-center gap-2 text-sm font-semibold text-ink/70 mb-2">
                            Title
                            <x-prefill-tag :on="in_array('title', $prefilled, true)" />
                        </span>
                        <input wire:model.live="title" type="text" dusk="trip-title" placeholder="Mount Pulag Sunrise Traverse"
                               class="field tracking-tight text-xl py-4">
                        @error('title')<p class="text-terra text-sm mt-2">{{ $message }}</p>@enderror
                    </label>

                    <label class="block mt-5">
                        <span class="field-label">Description <span class="font-normal text-ink/65">— optional</span></span>
                        <textarea wire:model.live="description" rows="5" placeholder="What the trip is like, who it suits, what to expect."
                                  class="field"></textarea>
                        @error('description')<p class="text-terra text-sm mt-2">{{ $message }}</p>@enderror
                    </label>
                </section>

                <section class="rounded-2xl border border-ink/12 bg-surface p-5">
                    <h2 class="tracking-tight font-semibold text-xl mb-4">Photos</h2>
                    <x-upload wire:model="cover" label="Cover photo" :multiple="false" :max="1" dusk="cover"
                              hint="The single image used on cards and the trip hero." />
                    @error('cover')<p class="text-terra text-sm mt-2">{{ $message }}</p>@enderror

                    <div class="mt-6">
                        <x-upload wire:model="photos" label="More photos" :multiple="true" :max="10"
                                  hint="Shown as a carousel on the trip page." />
                        @error('photos.*')<p class="text-terra text-sm mt-2">{{ $message }}</p>@enderror
                    </div>
                </section>

                <section class="rounded-2xl border border-ink/12 bg-surface p-5">
                    <h2 class="tracking-tight font-semibold text-xl mb-4">Price</h2>
                    <x-money-input wire:model.live="price" :currency="$currency" dusk="trip-price" />
                    @error('price')<p class="text-terra text-sm mt-2">{{ $message }}</p>@enderror
                    <p class="text-sm text-ink/65 mt-3">Per person. Joiners pay you directly — Tarana never holds the money.</p>

                    {{-- ── Price windows ──────────────────────────────────────────────
                         An early-bird rate that expires on its own beats one the host has
                         to remember to change. Empty means the flat price above applies. --}}
                    <div class="mt-6 pt-6 border-t border-ink/10">
                        <div class="flex items-center justify-between gap-3 mb-1">
                            <h3 class="font-semibold">Price windows <span class="font-normal text-ink/65">— optional</span></h3>
                            @if($priceTiers)
                                <button type="button" wire:click="addTier" dusk="add-tier" class="text-sm font-semibold text-moss min-h-[44px]">＋ Add a window</button>
                            @endif
                        </div>
                        <p class="text-sm text-ink/65 mb-4">Charge less for booking early. Whichever window covers the day a joiner books is the price they pay.</p>

                        @if(! $priceTiers)
                            <button type="button" wire:click="addTier" dusk="add-tier"
                                    class="inline-flex items-center gap-2 rounded-xl border border-ink/15 px-4 min-h-[44px] text-sm font-semibold hover:border-moss transition-colors">
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                Add an early-bird rate
                            </button>
                        @else
                            <div class="space-y-3">
                                @foreach($priceTiers as $i => $tier)
                                    <div class="rounded-xl border border-ink/10 p-4" wire:key="tier-{{ $i }}">
                                        <div class="flex flex-wrap gap-4">
                                            <label class="block flex-1 min-w-[9rem]">
                                                <span class="field-label">Name</span>
                                                <input wire:model.live="priceTiers.{{ $i }}.label" type="text" placeholder="Early bird" dusk="tier-{{ $i }}-label" class="field">
                                                @error('priceTiers.'.$i.'.label')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                            </label>
                                            <label class="block w-36">
                                                <span class="field-label">{{ $currency }}</span>
                                                <input wire:model.live="priceTiers.{{ $i }}.amount" type="text" inputmode="decimal" placeholder="2,000" dusk="tier-{{ $i }}-amount" class="field">
                                                @error('priceTiers.'.$i.'.amount')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                            </label>
                                            <button type="button" wire:click="removeTier({{ $i }})" aria-label="Remove {{ $tier['label'] ?: 'this window' }}"
                                                    class="grid place-items-center w-11 h-11 self-end rounded-lg text-ink/65 hover:text-terra hover:bg-ink/5">
                                                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-2 gap-4 mt-4">
                                            <label class="block min-w-0">
                                                <span class="field-label">From <span class="font-normal text-ink/65">— optional</span></span>
                                                <input wire:model.live="priceTiers.{{ $i }}.starts_at" type="date" class="field">
                                                @error('priceTiers.'.$i.'.starts_at')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                            </label>
                                            <label class="block min-w-0">
                                                <span class="field-label">Until <span class="font-normal text-ink/65">— optional</span></span>
                                                <input wire:model.live="priceTiers.{{ $i }}.ends_at" type="date" class="field">
                                                @error('priceTiers.'.$i.'.ends_at')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                            </label>
                                        </div>
                                        <p class="text-xs text-ink/65 mt-2">Leave a date blank for open-ended — no start means "from now", no end means "until departure".</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- ── Down payment ─────────────────────────────────────────────── --}}
                    <div class="mt-6 pt-6 border-t border-ink/10">
                        <h3 class="font-semibold mb-1">How joiners pay</h3>
                        <p class="text-sm text-ink/65 mb-4">Take the full amount up front, or hold a place with a down payment and settle the rest on the day.</p>

                        <div class="flex flex-wrap gap-2 mb-4">
                            <button type="button" wire:click="$set('deposit', '')" aria-pressed="{{ $deposit === '' ? 'true' : 'false' }}" dusk="pay-full"
                                    class="rounded-xl px-5 min-h-[48px] font-semibold border {{ $deposit === '' ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">
                                Pay in full
                            </button>
                            <button type="button" wire:click="$set('deposit', '{{ $price !== '' ? (string) round((float) $price * 0.3) : '500' }}')"
                                    aria-pressed="{{ $deposit !== '' ? 'true' : 'false' }}" dusk="pay-deposit"
                                    class="rounded-xl px-5 min-h-[48px] font-semibold border {{ $deposit !== '' ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">
                                Down payment
                            </button>
                        </div>

                        @if($deposit !== '')
                            <label class="block max-w-xs">
                                <span class="field-label">Down payment ({{ $currency }})</span>
                                <input wire:model.live="deposit" type="text" inputmode="decimal" placeholder="500" dusk="deposit-amount" class="field">
                                @error('deposit')<p class="text-terra text-sm mt-1">{{ $message }}</p>@enderror
                            </label>
                            @if(is_numeric($deposit) && is_numeric($price) && (float) $deposit > (float) $price)
                                {{-- Live, not just on Continue: a host should not fill in three more
                                     sections before being told this figure is impossible. --}}
                                <p class="text-terra text-sm mt-2 font-semibold">
                                    A down payment can't be more than the full price of {{ $currency }} {{ number_format((float) $price) }}.
                                </p>
                            @elseif(is_numeric($deposit) && is_numeric($price))
                                <p class="text-sm text-ink/70 mt-2">
                                    Joiners pay <b>{{ $currency }} {{ number_format((float) $deposit) }}</b> to hold a place,
                                    then <b>{{ $currency }} {{ number_format((float) $price - (float) $deposit) }}</b> on the day.
                                </p>
                            @endif
                        @endif
                    </div>
                </section>

                {{--
                    When the money is due.

                    Joiners pay only after you accept them onto the trip, so every deadline here is
                    measured from that moment — or pinned to a date the whole group works to. Two
                    payment types get their own clock because they answer different questions: the
                    down payment is "are you serious", the balance is "are we going".
                --}}
                <section class="rounded-2xl border border-ink/10 bg-surface p-5 sm:p-6">
                    <h2 class="tracking-tight font-semibold text-xl">Payment deadlines</h2>
                    <p class="text-ink/65 text-sm mt-1 mb-5">
                        Optional. Nobody is cancelled automatically — a late booking is flagged on your roster
                        and you decide what to do about it.
                    </p>

                    <div class="space-y-5">
                        @foreach([
                            ['key' => 'downpayment', 'label' => 'Down payment due', 'show' => $deposit !== ''],
                            ['key' => 'full', 'label' => $deposit !== '' ? 'Balance due' : 'Full payment due', 'show' => true],
                        ] as $term)
                            @continue(! $term['show'])
                            @php($mode = $term['key'].'DueMode')
                            @php($hours = $term['key'].'DueHours')
                            @php($date = $term['key'].'DueDate')

                            <div dusk="terms-{{ $term['key'] }}">
                                <span class="field-label">{{ $term['label'] }}</span>

                                <div class="flex flex-wrap gap-2 mt-1.5 mb-3">
                                    @foreach(['none' => 'No deadline', 'relative' => 'After I confirm', 'fixed' => 'On a date'] as $value => $caption)
                                        <button type="button" wire:click="$set('{{ $mode }}', '{{ $value }}')"
                                                aria-pressed="{{ $this->$mode === $value ? 'true' : 'false' }}"
                                                dusk="{{ $term['key'] }}-{{ $value }}"
                                                class="rounded-xl px-4 min-h-[44px] text-sm font-semibold border transition-colors
                                                       {{ $this->$mode === $value ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">{{ $caption }}</button>
                                    @endforeach
                                </div>

                                @if($this->$mode === 'relative')
                                    <label class="flex items-center gap-2 max-w-xs">
                                        <input wire:model.live="{{ $hours }}" type="text" inputmode="numeric" placeholder="48"
                                               dusk="{{ $term['key'] }}-hours" class="field w-24">
                                        <span class="text-sm text-ink/70">hours after I confirm the seat</span>
                                    </label>
                                    @error($hours)<p class="text-terra text-sm mt-1">{{ $message }}</p>@enderror
                                    @if(is_numeric($this->$hours) && (int) $this->$hours > 0)
                                        <p class="text-sm text-ink/70 mt-2">
                                            That's <b>{{ \App\Support\PaymentTerms::describeHours((int) $this->$hours) }}</b> from the moment you accept someone.
                                        </p>
                                    @endif
                                @elseif($this->$mode === 'fixed')
                                    <label class="block max-w-xs">
                                        <input wire:model.live="{{ $date }}" type="date" dusk="{{ $term['key'] }}-date" class="field">
                                    </label>
                                    @error($date)<p class="text-terra text-sm mt-1">{{ $message }}</p>@enderror
                                    <p class="text-sm text-ink/70 mt-2">Everyone on this trip works to the same date.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="flex gap-3 mt-7">
                <button wire:click="back" dusk="wizard-back" class="rounded-full border border-ink/20 px-8 py-4 font-semibold hover:bg-ink/5 transition-colors">Back</button>
                <button wire:click="next" dusk="wizard-next" class="flex-1 rounded-full bg-pine text-cream py-4 font-semibold text-lg hover:bg-pine/90 transition-colors">Continue</button>
            </div>
        @elseif($step === 3)
            <h1 class="tracking-tight font-semibold text-3xl mb-1">What's included</h1>
            <p class="text-ink/65 text-sm mb-7">Everything a joiner gets for the price. A photo sells it better than a word.</p>

            <div class="space-y-3">
                @foreach($inclusions as $i => $c)
                    <div class="rounded-2xl border border-ink/10 bg-surface p-5" wire:key="inc-{{ $i }}">
                        <div class="flex items-center gap-2.5">
                            <span class="grid place-items-center w-7 h-7 rounded-full bg-moss/12 text-moss shrink-0">
                                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                            </span>
                            <input wire:model.live="inclusions.{{ $i }}.label" type="text" placeholder="Transport"
                                   class="flex-1 rounded-lg border-ink/15 px-3 py-2.5 font-semibold focus:border-moss focus:ring-moss">
                            @if(count($inclusions) > 1)
                                <button type="button" wire:click="removeInclusion({{ $i }})" aria-label="Remove inclusion" class="text-ink/65 hover:text-terra">
                                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                </button>
                            @endif
                        </div>
                        @error('inclusions.'.$i.'.label')<p class="text-terra text-xs mt-1.5">{{ $message }}</p>@enderror

                        <input wire:model="inclusions.{{ $i }}.note" type="text" placeholder="Air-conditioned van, Manila round trip"
                               class="w-full rounded-lg border-ink/15 px-3 py-2 text-sm mt-2.5 focus:border-moss focus:ring-moss">

                        <div class="mt-3">
                            <x-upload wire:model="inclusions.{{ $i }}.image" label="" :multiple="false" :max="1" />
                            @error('inclusions.'.$i.'.image')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="button" wire:click="addInclusion" class="mt-4 text-sm font-semibold text-moss">＋ Add an inclusion</button>

            {{--
                ═══ WHAT TO BRING ═══

                A picker, not another repeater. Inclusions are yours to describe — "Transport",
                "air-conditioned van from Manila" — but what a joiner packs is the same short list
                on every trip, so it is toggles over an allow-list. One vocabulary means the trip
                page can render it the same way every time, and it can be searched on later.
            --}}
            <div class="mt-10 pt-8 border-t border-ink/10">
                <h2 class="tracking-tight font-semibold text-2xl mb-1">What to bring</h2>
                <p class="text-ink/65 mb-6">
                    Tap everything a joiner needs to pack. This shows on the trip page and on their booking.
                </p>

                @foreach(\App\Support\PackingItems::CATALOG as $group => $items)
                    <div class="mb-6 last:mb-0">
                        <span class="field-label">{{ $group }}</span>
                        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-2">
                            @foreach($items as $key => [$label, $note, $icon])
                                @php($on = in_array($key, $packing, true))
                                <button type="button" wire:click="togglePacking('{{ $key }}')"
                                        aria-pressed="{{ $on ? 'true' : 'false' }}"
                                        dusk="packing-{{ $key }}"
                                        class="flex items-start gap-3 text-left rounded-xl border-2 px-4 py-3.5 transition-colors
                                               {{ $on ? 'border-moss bg-moss/10' : 'border-ink/10 hover:border-ink/25' }}">
                                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 mt-0.5 {{ $on ? 'text-moss' : 'text-ink/40' }}" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold">{{ $label }}</span>
                                        <span class="block text-xs text-ink/65 leading-snug">{{ $note }}</span>
                                    </span>
                                    <span class="grid place-items-center w-5 h-5 shrink-0 rounded-full mt-0.5 {{ $on ? 'bg-moss text-cream' : 'border border-ink/15' }}">
                                        @if($on)
                                            <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12.5l5.5 5.5L20 6.5"/></svg>
                                        @endif
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- A permit-controlled climb needs an ID from every head; a beach day does not.
                     Off by default, because asking a stranger for their passport when the trip
                     does not need one is a cost with no benefit. --}}
                <label class="flex items-start gap-3 rounded-2xl border border-ink/12 p-4 mt-5 cursor-pointer hover:border-ink/25 transition-colors">
                    <input type="checkbox" wire:model="requiresId" dusk="requires-id"
                           class="mt-0.5 rounded border-ink/25 text-pine focus:ring-pine">
                    <span class="text-sm">
                        <span class="block font-semibold">Require a photo ID from every traveller</span>
                        <span class="block text-ink/65 mt-0.5">
                            Joiners upload one when they register, and you see them on the roster.
                            Turn this on for permits, boat manifests or anywhere names are checked.
                        </span>
                    </span>
                </label>

                <p class="text-sm text-ink/65 mt-4" dusk="packing-count">
                    {{ count($packing) }} {{ \Illuminate\Support\Str::plural('item', count($packing)) }} selected.
                    @if(count($packing) === 0) Optional, but joiners turn up better prepared when it is filled in. @endif
                </p>
            </div>

            <div class="flex gap-3 mt-9">
                <button wire:click="back" dusk="wizard-back" class="rounded-full border border-ink/20 px-7 py-3.5 font-semibold hover:bg-ink/5 transition-colors">Back</button>
                <button wire:click="next" dusk="wizard-next" class="flex-1 rounded-full bg-pine text-cream py-3.5 font-semibold hover:bg-pine/90 transition-colors">Continue to logistics</button>
            </div>

        @elseif($step === 4)
            <h1 class="tracking-tight font-semibold text-3xl mb-1">Logistics</h1>
            <p class="text-ink/65 mb-7">When you leave, where you pick people up, and where you drop them back.</p>

            <div class="space-y-3">
                <x-form-card title="Departure dates" :open="true" :summary="$this->departureSummary()"
                             icon="M7 3v3M17 3v3M3.5 9h17M4 6h16v14H4z">
                    <p class="text-sm text-ink/65 mb-4">Each date has its own limit. Reservations stop automatically once a date is full.</p>
                    <div class="space-y-4">
                        @foreach($departures as $i => $d)
                            <div class="rounded-xl border border-ink/10 p-4" wire:key="dep-{{ $i }}">
                                @if($this->isEditing)
                                    {{-- Locked: people have booked these dates. Moving one under them
                                         is not an edit, it is a different trip. --}}
                                    <div class="flex flex-wrap items-center gap-3 mb-4">
                                        <span class="grid place-items-center w-9 h-9 rounded-xl bg-ink/[.06] text-ink/65 shrink-0">
                                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 11V8a5 5 0 0 1 10 0v3M5 11h14v9H5z"/></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold">
                                                {{ $d['starts_at'] ? \Illuminate\Support\Carbon::parse($d['starts_at'])->format('D j M Y') : 'No date' }}
                                                @if($d['ends_at']) <span class="font-normal text-ink/65">→ {{ \Illuminate\Support\Carbon::parse($d['ends_at'])->format('j M') }}</span>@endif
                                            </p>
                                            <p class="text-sm text-ink/65">This date is fixed. To run it on another day, publish a new schedule.</p>
                                        </div>
                                    </div>
                                @endif
                                <div class="flex flex-wrap gap-4 items-end">
                                    @unless($this->isEditing)
                                        <label class="block flex-1 min-w-[10rem]">
                                            <span class="field-label">Starts</span>
                                            <input type="date" wire:model.live="departures.{{ $i }}.starts_at" dusk="dep-{{ $i }}-start" class="field">
                                            @error('departures.'.$i.'.starts_at')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                        </label>
                                        <label class="block flex-1 min-w-[10rem]">
                                            <span class="field-label">Ends</span>
                                            <input type="date" wire:model="departures.{{ $i }}.ends_at" class="field">
                                        </label>
                                    @endunless
                                    <label class="block">
                                        <span class="field-label">Max joiners</span>
                                        <input type="number" min="1" max="200" wire:model="departures.{{ $i }}.capacity" class="field-narrow w-28">
                                        @error('departures.'.$i.'.capacity')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                    </label>
                                    @if(count($departures) > 1)
                                        <button type="button" wire:click="removeDeparture({{ $i }})" aria-label="Remove departure" class="grid place-items-center w-11 h-11 rounded-lg text-ink/65 hover:text-terra hover:bg-ink/5">
                                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @unless($this->isEditing)
                        <button type="button" wire:click="addDeparture" class="mt-4 font-semibold text-moss min-h-[44px]">＋ Add a departure</button>
                    @endunless
                    @error('departures')<p class="text-terra text-sm mt-2">{{ $message }}</p>@enderror
                </x-form-card>

                {{-- Open when it already holds something: on an edit or a copy, a collapsed
                     card hides the very content the host came back to check. --}}
                <x-form-card title="Pickup points" optional
                             :open="collect($meetups)->contains(fn ($m) => filled($m['label'] ?? null))"
                             :summary="$this->meetupSummary()"
                             icon="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11zM12 12.4a2.4 2.4 0 1 0 0-4.8 2.4 2.4 0 0 0 0 4.8z">
                    <div class="space-y-4">
                        @foreach($meetups as $i => $m)
                            <div class="rounded-xl border border-ink/10 p-4" wire:key="meet-{{ $i }}">
                                <div class="flex items-center gap-2 mb-4">
                                    <span class="grid place-items-center w-7 h-7 rounded-full bg-terra text-cream text-xs font-bold shrink-0">{{ $i + 1 }}</span>
                                    @if(count($meetups) > 1)
                                        <button type="button" wire:click="removeMeetup({{ $i }})" aria-label="Remove pickup point" class="grid place-items-center w-11 h-11 ml-auto rounded-lg text-ink/65 hover:text-terra hover:bg-ink/5">
                                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-4">
                                    <label class="block flex-1 min-w-[12rem]">
                                        <span class="field-label">Where</span>
                                        <input wire:model.live="meetups.{{ $i }}.label" type="text" placeholder="Cubao terminal" class="field">
                                        @error('meetups.'.$i.'.label')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="block">
                                        <span class="field-label">Pickup time</span>
                                        <input wire:model="meetups.{{ $i }}.time" type="time" dusk="meetup-{{ $i }}-time" class="field w-40">
                                        @error('meetups.'.$i.'.time')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                    </label>
                                </div>

                                <label class="block mt-4">
                                    <span class="field-label">Address</span>
                                    <input wire:model="meetups.{{ $i }}.address" type="text" placeholder="EDSA cor. Aurora Blvd, Quezon City" class="field">
                                </label>
                                <label class="block mt-4">
                                    <span class="field-label">Note <span class="font-normal text-ink/65">— optional</span></span>
                                    <input wire:model="meetups.{{ $i }}.note" type="text" placeholder="Look for the blue van" class="field">
                                </label>

                                <div x-data="{ more: false }" class="mt-4">
                                    <button type="button" x-on:click="more = !more" :aria-expanded="more ? 'true' : 'false'"
                                            class="font-semibold text-moss text-sm min-h-[44px]">
                                        <span x-show="!more">＋ Map pin, photo and saving</span>
                                        <span x-show="more" x-cloak>− Hide extras</span>
                                    </button>

                                    <div x-show="more" x-collapse x-cloak>
                                        <div class="grid grid-cols-2 gap-4 mt-3">
                                            <label class="block min-w-0">
                                                <span class="field-label">Latitude</span>
                                                <input wire:model.live.debounce.700ms="meetups.{{ $i }}.lat" type="text" inputmode="decimal" placeholder="14.6220" class="field">
                                                @error('meetups.'.$i.'.lat')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                            </label>
                                            <label class="block min-w-0">
                                                <span class="field-label">Longitude</span>
                                                <input wire:model.live.debounce.700ms="meetups.{{ $i }}.lng" type="text" inputmode="decimal" placeholder="121.0530" class="field">
                                                @error('meetups.'.$i.'.lng')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                            </label>
                                        </div>
                                        <p class="text-xs text-ink/65 mt-2">In Google Maps, right-click the spot → click the coordinates to copy.</p>
                                        {{-- the pin proves itself: paste coordinates, see the spot --}}
                                        <x-map-preview :lat="$m['lat'] ?? null" :lng="$m['lng'] ?? null" :label="$m['label'] ?: 'this pickup point'" />

                                        <div class="mt-4">
                                            <x-upload wire:model="meetups.{{ $i }}.image" label="" :multiple="false" :max="1" />
                                            @error('meetups.'.$i.'.image')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                        </div>

                                        <label class="flex items-center gap-2.5 mt-4 text-sm font-semibold text-ink/70 min-h-[44px]">
                                            <input type="checkbox" wire:model="meetups.{{ $i }}.save_to_library" class="rounded border-ink/25 text-moss focus:ring-moss">
                                            Save this spot to my library
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <x-spot-picker kind="pickup" :presets="$presets" label="Add a pickup point" />
                </x-form-card>

                {{-- The return leg. Previously invisible: joiners had no idea where or when
                     the trip actually ended, which matters for booking onward travel. --}}
                <x-form-card title="Drop-off points" optional
                             :open="collect($dropoffs)->contains(fn ($d) => filled($d['label'] ?? null))"
                             :summary="$this->dropoffSummary()"
                             icon="M5 12h14M13 6l6 6-6 6">
                    <p class="text-sm text-ink/65 mb-4">Where the trip ends, and roughly when — so joiners can book their onward travel.</p>
                    <div class="space-y-4">
                        @foreach($dropoffs as $i => $d)
                            <div class="rounded-xl border border-ink/10 p-4" wire:key="drop-{{ $i }}">
                                <div class="flex flex-wrap gap-4">
                                    <label class="block flex-1 min-w-[12rem]">
                                        <span class="field-label">Where</span>
                                        <input wire:model.live="dropoffs.{{ $i }}.label" type="text" placeholder="Cubao terminal" dusk="drop-{{ $i }}-label" class="field">
                                        @error('dropoffs.'.$i.'.label')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                    </label>
                                    <label class="block">
                                        <span class="field-label">Drop-off time</span>
                                        <input wire:model="dropoffs.{{ $i }}.time" type="time" dusk="drop-{{ $i }}-time" class="field w-40">
                                        @error('dropoffs.'.$i.'.time')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                    </label>
                                    @if(count($dropoffs) > 1)
                                        <button type="button" wire:click="removeDropoff({{ $i }})" aria-label="Remove drop-off point" class="grid place-items-center w-11 h-11 self-end rounded-lg text-ink/65 hover:text-terra hover:bg-ink/5">
                                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    @endif
                                </div>

                                <label class="block mt-4">
                                    <span class="field-label">Address <span class="font-normal text-ink/65">— optional</span></span>
                                    <input wire:model="dropoffs.{{ $i }}.address" type="text" placeholder="EDSA cor. Aurora Blvd, Quezon City" class="field">
                                </label>
                                <label class="block mt-4">
                                    <span class="field-label">Note <span class="font-normal text-ink/65">— optional</span></span>
                                    <input wire:model="dropoffs.{{ $i }}.note" type="text" placeholder="Traffic dependent, could be an hour later" class="field">
                                </label>

                                {{-- Same extras as a pickup: a drop-off is just as easy to miss. --}}
                                <div x-data="{ more: false }" class="mt-4">
                                    <button type="button" x-on:click="more = !more" :aria-expanded="more ? 'true' : 'false'"
                                            dusk="drop-{{ $i }}-extras" class="font-semibold text-moss text-sm min-h-[44px]">
                                        <span x-show="!more">＋ Map pin, photo and saving</span>
                                        <span x-show="more" x-cloak>− Hide extras</span>
                                    </button>

                                    <div x-show="more" x-collapse x-cloak>
                                        <div class="grid grid-cols-2 gap-4 mt-3">
                                            <label class="block min-w-0">
                                                <span class="field-label">Latitude</span>
                                                <input wire:model.live.debounce.700ms="dropoffs.{{ $i }}.lat" type="text" inputmode="decimal" placeholder="14.6220" class="field">
                                                @error('dropoffs.'.$i.'.lat')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                            </label>
                                            <label class="block min-w-0">
                                                <span class="field-label">Longitude</span>
                                                <input wire:model.live.debounce.700ms="dropoffs.{{ $i }}.lng" type="text" inputmode="decimal" placeholder="121.0530" class="field">
                                                @error('dropoffs.'.$i.'.lng')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                            </label>
                                        </div>
                                        <p class="text-xs text-ink/65 mt-2">In Google Maps, right-click the spot → click the coordinates to copy.</p>
                                        <x-map-preview :lat="$d['lat'] ?? null" :lng="$d['lng'] ?? null" :label="$d['label'] ?: 'this drop-off point'" />

                                        <div class="mt-4">
                                            <x-upload wire:model="dropoffs.{{ $i }}.image" label="" :multiple="false" :max="1" />
                                            @error('dropoffs.'.$i.'.image')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                        </div>

                                        <label class="flex items-center gap-2.5 mt-4 text-sm font-semibold text-ink/70 min-h-[44px]">
                                            <input type="checkbox" wire:model="dropoffs.{{ $i }}.save_to_library" class="rounded border-ink/25 text-moss focus:ring-moss">
                                            Save this spot to my library
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <x-spot-picker kind="dropoff" :presets="$presets" label="Add a drop-off point" />
                </x-form-card>
            </div>

            <div class="flex gap-3 mt-7">
                <button wire:click="back" dusk="wizard-back" class="rounded-full border border-ink/20 px-8 py-4 font-semibold hover:bg-ink/5 transition-colors">Back</button>
                <button wire:click="next" dusk="wizard-next" class="flex-1 rounded-full bg-pine text-cream py-4 font-semibold text-lg hover:bg-pine/90 transition-colors">Review</button>
            </div>
        @else
            <h1 class="tracking-tight font-semibold text-3xl mb-1">{{ $this->isEditing ? 'Review &amp; save' : 'Review &amp; publish' }}</h1>
            <p class="text-ink/65 mb-7">
                Everything you've entered, by section. Edit any of it without losing the rest.
                @if($this->isEditing)<span class="block mt-1">Only this schedule changes — the other dates keep their own details.</span>@endif
            </p>

            {{-- Every section shows what it actually holds and jumps back to the step that
                 owns it. A checklist of ticks told a host nothing about what they typed. --}}
            <div class="space-y-4">

                {{-- ── 1 · Where & the trail ── --}}
                <x-review-section title="Where you're going" :step="1" :label="$steps[1]">
                    @forelse($destinations as $i => $row)
                        @php($place = $places->firstWhere('id', $row['place_id']))
                        @php($h = $this->hikeFor($row['place_id']))
                        <div class="@if(! $loop->first) mt-4 pt-4 border-t border-ink/8 @endif">
                            <p class="font-semibold">{{ $i + 1 }}. {{ $place?->name }}
                                <span class="font-normal text-ink/65">· {{ $place?->country }}</span></p>
                            <ul class="mt-1.5 space-y-0.5">
                                @foreach($row['days'] ?: [''] as $d => $dayLabel)
                                    <li class="text-sm text-ink/70">
                                        <span class="font-semibold text-ink/80">Day {{ $d + 1 }}</span>
                                        {{ filled($dayLabel) ? '· '.$dayLabel : '· not described yet' }}
                                    </li>
                                @endforeach
                            </ul>
                            @if($place?->category === 'hike')
                                @php($bits = array_filter([
                                    filled($h['trail_name']) ? $h['trail_name'] : null,
                                    filled($h['masl']) ? number_format((float) $h['masl']).' m' : null,
                                    filled($h['distance_km']) ? $h['distance_km'].' km' : null,
                                    \App\Support\HikeGrades::band((int) $h['difficulty']),
                                    \App\Support\HikeGrades::trailTypeLabel($h['trail_type'] ?: null),
                                    $h['features'] ? count($h['features']).' features' : null,
                                ]))
                                <p class="text-sm mt-2 {{ $bits ? 'text-ink/70' : 'text-amber-deep' }}">
                                    <span class="font-semibold">Trail</span> ·
                                    {{ $bits ? implode(' · ', $bits) : 'nothing added — hikers will not know what they are walking into' }}
                                </p>
                            @endif
                        </div>
                    @empty
                        <p class="text-terra text-sm">No destination yet — this is required.</p>
                    @endforelse
                </x-review-section>

                {{-- ── 2 · The trip ── --}}
                <x-review-section title="The trip" :step="2" :label="$steps[2]">
                    <p class="tracking-tight font-semibold text-xl">{{ $title ?: 'Untitled' }}</p>
                    <p class="text-sm text-ink/70 mt-1">{{ $description ?: 'No description yet.' }}</p>
                    <dl class="flex flex-wrap gap-x-8 gap-y-2 mt-4">
                        <div>
                            <dt class="marker text-[10px] font-semibold text-ink/65">Price</dt>
                            <dd class="font-bold text-lg">{{ $currency }} {{ $price !== '' ? number_format((float) $price) : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="marker text-[10px] font-semibold text-ink/65">Cover</dt>
                            <dd class="font-semibold {{ $cover ? '' : 'text-ink/65' }}">{{ $cover ? 'Uploaded' : 'Stock image' }}</dd>
                        </div>
                        <div>
                            <dt class="marker text-[10px] font-semibold text-ink/65">Carousel</dt>
                            <dd class="font-semibold {{ count($photos) ? '' : 'text-ink/65' }}">{{ count($photos) ?: 'None' }}</dd>
                        </div>
                        <div>
                            <dt class="marker text-[10px] font-semibold text-ink/65">Length</dt>
                            <dd class="font-semibold">{{ $this->lengthSummary() }}</dd>
                        </div>
                    </dl>
                </x-review-section>

                {{-- ── 3 · What's included ── --}}
                <x-review-section title="What's included" :step="3" :label="$steps[3]">
                    @php($named = collect($inclusions)->filter(fn ($c) => filled($c['label'] ?? null)))
                    @if($named->isEmpty())
                        <p class="text-ink/65 text-sm">Nothing listed.</p>
                    @else
                        <div class="flex flex-wrap gap-2">
                            @foreach($named as $c)
                                <span class="inline-flex items-center gap-2 rounded-full bg-moss/10 px-3.5 py-2 text-sm font-semibold">
                                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-moss" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                                    {{ $c['label'] }}
                                    @if(filled($c['note'] ?? null))<span class="font-normal text-ink/65">· {{ $c['note'] }}</span>@endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                </x-review-section>

                {{-- ── 4 · Logistics ── --}}
                <x-review-section title="Logistics" :step="4" :label="$steps[4]">
                    <p class="text-sm font-semibold text-ink/70 mb-1.5">Departures</p>
                    @php($dated = collect($departures)->filter(fn ($d) => filled($d['starts_at'] ?? null)))
                    @if($dated->isEmpty())
                        <p class="text-terra text-sm">No dates yet — at least one is required.</p>
                    @else
                        <ul class="space-y-1">
                            @foreach($dated as $d)
                                <li class="text-sm text-ink/70">
                                    {{ \Illuminate\Support\Carbon::parse($d['starts_at'])->format('D j M Y') }}
                                    @if(filled($d['ends_at'] ?? null)) → {{ \Illuminate\Support\Carbon::parse($d['ends_at'])->format('D j M Y') }} @endif
                                    · up to {{ $d['capacity'] }} joiners
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @foreach([['Pickup points', $meetups], ['Drop-off points', $dropoffs]] as [$heading, $spots])
                        @php($listed = collect($spots)->filter(fn ($m) => filled($m['label'] ?? null)))
                        <p class="text-sm font-semibold text-ink/70 mt-4 mb-1.5">{{ $heading }}</p>
                        @if($listed->isEmpty())
                            <p class="text-ink/65 text-sm">None set.</p>
                        @else
                            <ul class="space-y-1">
                                @foreach($listed as $m)
                                    <li class="text-sm text-ink/70">
                                        <span class="font-semibold text-ink/80">{{ $m['label'] }}</span>
                                        @if(filled($m['time'] ?? null)) · {{ $m['time'] }} @endif
                                        @if(filled($m['address'] ?? null)) · {{ $m['address'] }} @endif
                                        @if(filled($m['lat'] ?? null) && filled($m['lng'] ?? null))
                                            <span class="text-moss">· pinned</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                </x-review-section>

                {{-- ── Who can see this trip ── --}}
                {{-- A control, not a summary, so no Edit button. Listing only: the trip's link opens for
                     anyone whichever is picked, which is what the copy under each choice says. --}}
                @php($blurbs = [
                    \App\Support\Visibility::PUBLIC => 'Listed on Explore, Home and your page.',
                    \App\Support\Visibility::FOLLOWERS => 'People who follow you see it first, then everyone once the window ends.',
                    \App\Support\Visibility::LINK => 'Not listed anywhere. Only people with the link can open it.',
                ])
                <section class="rounded-2xl border border-ink/12 bg-surface overflow-hidden">
                    <header class="px-5 py-3.5 border-b border-ink/8 bg-cream/50">
                        <h2 id="visibility-heading" class="font-semibold text-lg">Who can see this trip</h2>
                    </header>
                    <div class="p-5">
                        <div role="radiogroup" aria-labelledby="visibility-heading" class="space-y-2">
                            @foreach(\App\Support\Visibility::CATALOG as $key => $label)
                                <label class="flex items-start gap-3 rounded-xl border p-4 cursor-pointer transition-colors
                                              {{ $visibility === $key ? 'border-moss ring-1 ring-moss' : 'border-ink/12 hover:border-ink/30' }}">
                                    <input type="radio" name="visibility" value="{{ $key }}" wire:model.live="visibility"
                                           dusk="visibility-{{ $key }}"
                                           class="mt-1 w-4 h-4 shrink-0 text-moss border-ink/25 focus:ring-moss">
                                    <span class="min-w-0">
                                        <span class="block font-semibold">{{ $label }}</span>
                                        <span class="block text-sm text-ink/70">{{ $blurbs[$key] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('visibility')<p class="text-terra text-sm mt-2">{{ $message }}</p>@enderror

                        @if($visibility === \App\Support\Visibility::FOLLOWERS)
                            <label class="block max-w-xs mt-4">
                                <span class="field-label">Followers get it first for</span>
                                <select wire:model="followersWindow" dusk="followers-window" class="field">
                                    @if($currentOpensAt)
                                        <option value="keep">Keep: opens {{ $currentOpensAt }}</option>
                                    @endif
                                    @foreach(\App\Support\Visibility::WINDOWS as $window => [, $windowLabel])
                                        <option value="{{ $window }}">{{ $windowLabel }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @error('followersWindow')<p class="text-terra text-sm mt-1">{{ $message }}</p>@enderror
                        @endif
                    </div>
                </section>
            </div>

            <div class="flex gap-3 mt-7">
                <button wire:click="back" dusk="wizard-back" class="rounded-full border border-ink/20 px-8 py-4 font-semibold hover:bg-ink/5 transition-colors">Back</button>
                <button wire:click="publish" dusk="wizard-publish" wire:loading.attr="disabled"
                        class="flex-1 rounded-full bg-pine text-cream py-4 font-semibold text-lg hover:bg-pine/90 transition-colors">
                    <span wire:loading.remove wire:target="publish">{{ $this->isEditing ? 'Save this schedule' : 'Publish trip' }}</span>
                    <span wire:loading wire:target="publish">{{ $this->isEditing ? 'Saving…' : 'Publishing…' }}</span>
                </button>
            </div>
        @endif
    </div>

</div>
