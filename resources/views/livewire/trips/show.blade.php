<div>
    {{-- ═══ HERO ═══ --}}
    <div class="relative h-[300px] md:h-[380px] overflow-hidden">
        @if($trip->cover_url)
            <img src="{{ $trip->cover_url }}" alt="{{ $trip->title }}" class="absolute inset-0 w-full h-full object-cover">
        @else
            <svg viewBox="0 0 1440 380" preserveAspectRatio="xMidYMid slice" class="absolute inset-0 w-full h-full" aria-hidden="true">
                <defs><linearGradient id="th" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#D9A05B"/><stop offset=".5" stop-color="#A86A3F"/><stop offset="1" stop-color="#33301F"/></linearGradient></defs>
                <rect width="1440" height="380" fill="url(#th)"/>
                <circle cx="1080" cy="96" r="52" fill="#F4E2AE" opacity=".9"/>
                <path d="M0 250 L240 150 L440 230 L680 120 L920 220 L1180 130 L1440 210 L1440 380 L0 380 Z" fill="#4E5A3E" opacity=".7"/>
                <path d="M0 300 L280 220 L560 290 L840 210 L1120 285 L1440 235 L1440 380 L0 380 Z" fill="#2C3826"/>
            </svg>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-ink/70 via-ink/10 to-transparent"></div>
        <div class="absolute inset-x-0 top-0 p-5">
            <a href="{{ route('trips.index') }}" class="inline-flex items-center gap-1.5 rounded-full glass-dark px-3.5 py-2 text-sm font-semibold text-cream">‹ Explore</a>
        </div>
        {{-- Matches the content container below exactly, so the title sits on the same left edge
             as the host row rather than floating in from somewhere else. --}}
        <div class="absolute inset-x-0 bottom-0 px-6 lg:px-10 pb-6 md:pb-10 max-w-[1600px] mx-auto w-full">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="rounded-full glass px-3 py-1 text-xs font-semibold capitalize">{{ $trip->place->category }}</span>
                @if($trip->effective_difficulty)
                    <span class="rounded-full glass-dark px-3 py-1 text-xs font-semibold text-cream capitalize">{{ $trip->effective_difficulty }}</span>
                @endif
                @if($trip->place->elevation_m)<span class="rounded-full glass-dark px-3 py-1 text-xs font-semibold text-cream">{{ number_format($trip->place->elevation_m) }} m</span>@endif
                @if($trip->reviews_count)<span class="rounded-full glass-dark px-3 py-1 text-xs font-semibold text-cream"><span class="text-amber">★</span> {{ $trip->aggregate_rating }} ({{ $trip->reviews_count }})</span>@endif
            </div>
            <h1 class="tracking-tight font-semibold text-4xl md:text-5xl text-cream leading-tight">{{ $trip->title }}</h1>
            <p class="text-cream/80 mt-2">{{ $trip->place->name }} · {{ $trip->place->country }}</p>
        </div>
    </div>

    {{--
        max-w-5xl left 352px of empty canvas down each side of a 1728px screen — 41% of the
        display — while squeezing the content column to 576px, which is what made the page run
        nearly 4,000px deep. The container is wide now and the width is spent on card grids that
        gain columns, not on line length: prose and ordered lists below stay capped at a readable
        measure, because a 1,100px line is harder to read, not easier.
    --}}
    <div class="max-w-[1600px] mx-auto px-6 lg:px-10 py-10 grid lg:grid-cols-[minmax(0,1fr)_380px] gap-8 xl:gap-12 items-start">
        {{-- LEFT --}}
        <div class="min-w-0">
            {{-- host row --}}
            <div class="pb-6 border-b border-ink/8">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink/65 mb-2">Hosted by</p>
                {{-- Avatar and name are one target now. The photo used to be inert while only the
                     name was a link, which is the half of the pair people actually aim at. --}}
                <x-host-link :host="$trip->host" size="lg" class="gap-3 font-semibold"
                             :subtitle="($trip->host->hostProfile?->trips_count ?? 0).' trips led · '.($trip->host->hostProfile?->rating ?? '—').' ★'" />
            </div>

            {{-- ═══ THE TRAIL — one panel per hiking stop ═══ --}}
            {{-- A traverse has a summit, a distance and a set of features per mountain, so
                 each stop gets its own panel, headed by the mountain it describes. --}}
            @php($trailStops = $trip->destinations->filter(fn ($d) => $d->hikeDetail?->hasContent()))
            @foreach($trailStops as $stop)
                @php($h = $stop->hikeDetail)
                <h2 class="tracking-tight font-semibold text-xl mt-9 mb-4">
                    {{ $trailStops->count() > 1 ? $stop->place?->name : 'The trail' }}
                    @if($h->trail_name)<span class="text-ink/65 font-sans text-base font-normal">· {{ $h->trail_name }}</span>@endif
                </h2>
                @if($trailStops->count() > 1 && $loop->first)
                    <p class="text-sm text-ink/65 -mt-3 mb-4">This trip takes in {{ $trailStops->count() }} summits.</p>
                @endif

                {{-- headline numbers --}}
                @php($stats = collect([
                    ['MASL', $h->masl ? number_format($h->masl).' m' : null],
                    ['Ascent', $h->elevation_gain_m ? '+'.number_format($h->elevation_gain_m).' m' : null],
                    ['Distance', $h->distance_km ? rtrim(rtrim(number_format($h->distance_km, 1), '0'), '.').' km' : null],
                    ['Time', $h->duration_hours ? rtrim(rtrim(number_format($h->duration_hours, 1), '0'), '.').' h' : null],
                ])->filter(fn ($s) => $s[1] !== null))
                @if($stats->isNotEmpty())
                    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        @foreach($stats as [$label, $value])
                            <div class="rounded-2xl border border-ink/8 bg-surface px-4 py-3">
                                <dd class="tracking-tight font-bold text-xl leading-none">{{ $value }}</dd>
                                <dt class="marker text-[10px] font-semibold text-ink/65 mt-1.5">{{ $label }}</dt>
                            </div>
                        @endforeach
                    </dl>
                @endif

                {{-- grades: the numbers only mean something with their definition attached --}}
                @if($h->difficulty || $h->trail_class || $h->trail_type)
                    <div class="flex flex-wrap gap-2 mb-4">
                        @if($h->band)
                            <span class="inline-flex items-center gap-2 rounded-full bg-moss/12 text-pine px-4 py-2 text-sm font-semibold"
                                  title="{{ \App\Support\HikeGrades::difficultyNote($h->difficulty) }}">
                                <span class="grid place-items-center w-6 h-6 rounded-full bg-moss text-cream text-[11px] font-bold">{{ $h->difficulty }}</span>
                                {{ $h->band }} · {{ $h->difficulty_label }}
                            </span>
                        @endif
                        @if($h->trail_class_label)
                            <span class="rounded-full border border-ink/12 px-4 py-2 text-sm font-semibold"
                                  title="{{ \App\Support\HikeGrades::trailClassNote($h->trail_class) }}">{{ $h->trail_class_label }}</span>
                        @endif
                        @if($h->trail_type_label)
                            <span class="rounded-full border border-ink/12 px-4 py-2 text-sm font-semibold">{{ $h->trail_type_label }}</span>
                        @endif
                    </div>
                    @if(\App\Support\HikeGrades::difficultyNote($h->difficulty))
                        <p class="text-sm text-ink/65 mb-4">{{ \App\Support\HikeGrades::difficultyNote($h->difficulty) }}</p>
                    @endif
                @endif

                @if($h->jump_off)
                    <p class="flex items-center gap-2 text-sm text-ink/70 mb-4">
                        <svg viewBox="0 0 24 24" class="w-4 h-4 text-terra shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                        Jump-off · <b class="font-semibold text-ink/80">{{ $h->jump_off }}</b>
                    </p>
                @endif

                @if($h->features)
                    <h3 class="text-sm font-semibold text-ink/70 mb-2">On the trail</h3>
                    {{-- Eight features at two columns is four rows; at three it is three. This is
                         the single biggest vertical saving on the page. --}}
                    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-2 mb-4">
                        {{-- catalogue order, not click order, so every trip reads the same way --}}
                        @foreach(array_intersect(\App\Support\TrailFeatures::keys(), $h->features) as $key)
                            <div class="flex items-start gap-3 rounded-xl border border-ink/8 bg-surface px-3.5 py-3">
                                <svg viewBox="0 0 24 24" class="w-5 h-5 text-moss shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ \App\Support\TrailFeatures::icon($key) }}"/></svg>
                                <span>
                                    <span class="block text-sm font-semibold">{{ \App\Support\TrailFeatures::label($key) }}</span>
                                    <span class="block text-xs text-ink/65 leading-snug">{{ \App\Support\TrailFeatures::note($key) }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($h->pulag_factors)
                    <div class="rounded-2xl border border-amber/25 bg-amber/[.06] px-4 py-3">
                        <p class="text-sm font-semibold text-ink/80 mb-1.5">Come prepared for</p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                            @foreach(array_intersect(array_keys(\App\Support\HikeGrades::PULAG_FACTORS), $h->pulag_factors) as $key)
                                <span class="inline-flex items-center gap-1.5 text-sm text-ink/70">
                                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-amber shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 8v5M12 16.5v.5"/><circle cx="12" cy="12" r="9"/></svg>
                                    {{ \App\Support\HikeGrades::PULAG_FACTORS[$key] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- ═══ ITINERARY ═══ --}}
            {{-- Shown for a single stop too, now that a stop can span several described days. --}}
            @php($hasItinerary = $trip->destinations->count() > 1
                || $trip->destinations->contains(fn ($s) => collect($s->days ?? [])->filter()->isNotEmpty()))
            @if($hasItinerary)
                <h2 class="tracking-tight font-semibold text-xl mt-9 mb-1">The route</h2>
                @if($trip->day_count)
                    <p class="text-sm text-ink/65 mb-4">{{ $trip->day_count }} {{ \Illuminate\Support\Str::plural('day', $trip->day_count) }} across {{ $trip->destinations->count() }} {{ \Illuminate\Support\Str::plural('stop', $trip->destinations->count()) }}</p>
                @endif
                {{-- Capped, not stretched. A numbered route is read top to bottom; running each
                     line to 1,100px would make it harder to follow, not easier. --}}
                <ol class="space-y-4 max-w-3xl">
                    @php($dayNo = 0)
                    @foreach($trip->destinations as $i => $stop)
                        <li class="flex items-start gap-3">
                            <span class="grid place-items-center w-7 h-7 rounded-full bg-moss text-cream text-xs font-bold shrink-0">{{ $i + 1 }}</span>
                            <div class="min-w-0">
                                <div class="font-semibold">{{ $stop->place?->name }}<span class="text-ink/65 font-normal"> · {{ $stop->place?->country }}</span></div>
                                @if(collect($stop->days ?? [])->filter()->isNotEmpty())
                                    <ul class="mt-1.5 space-y-1">
                                        @foreach($stop->days as $label)
                                            @php($dayNo++)
                                            @continue(blank($label))
                                            <li class="text-sm text-ink/70">
                                                <span class="font-semibold text-ink/80">Day {{ $dayNo }}</span> · {{ $label }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif(count($stop->days ?? []) > 1)
                                    <p class="text-sm text-ink/65 mt-1">{{ count($stop->days) }} days here</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if($trip->description)
                <p class="text-ink/75 leading-relaxed mt-6 max-w-3xl">{{ $trip->description }}</p>
            @endif

            {{-- ═══ PHOTO CAROUSEL ═══ --}}
            @if($trip->photos->isNotEmpty())
                <h2 class="tracking-tight font-semibold text-xl mt-9 mb-4">Photos</h2>
                <x-carousel :images="$trip->photos->map(fn($p) => ['url' => $p->url, 'alt' => $trip->title])"
                            ratio="aspect-[16/10]" rounded="rounded-2xl" />
            @endif

            {{-- ═══ WHAT'S INCLUDED ═══ --}}
            @if($trip->inclusionItems->isNotEmpty())
                <h2 class="tracking-tight font-semibold text-xl mt-9 mb-4">What's included</h2>

                @php($withPhotos = $trip->inclusionItems->filter->image)
                @if($withPhotos->isNotEmpty())
                    {{-- richer card grid once a host has photographed what joiners actually get --}}
                    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($trip->inclusionItems as $inc)
                            <div class="rounded-2xl border border-ink/8 bg-surface overflow-hidden">
                                @if($inc->image_url)
                                    <img src="{{ $inc->image_url }}" alt="{{ $inc->label }}" class="w-full aspect-[16/10] object-cover" loading="lazy">
                                @endif
                                <div class="p-4">
                                    <div class="flex items-center gap-2">
                                        <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                                        <span class="font-semibold">{{ $inc->label }}</span>
                                    </div>
                                    @if($inc->note)<p class="text-sm text-ink/65 mt-1.5 leading-relaxed">{{ $inc->note }}</p>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach($trip->inclusionItems as $inc)
                            <span class="inline-flex items-center gap-2 rounded-full bg-moss/10 text-pine px-3.5 py-1.5 text-sm font-medium"
                                  @if($inc->note) title="{{ $inc->note }}" @endif>
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                                {{ $inc->label }}
                            </span>
                        @endforeach
                    </div>
                @endif
            @endif

            {{--
                ═══ WHAT TO BRING ═══

                Distinct from what's included, and worth its own heading: one is what the host
                hands you, the other is what you are on the hook for. A joiner who reads these as
                one list turns up expecting a headlamp to be provided.
            --}}
            @php($packing = \App\Support\PackingItems::resolve($trip->packing_items))
            @if($packing)
                <h2 class="tracking-tight font-semibold text-xl mt-9 mb-4">What to bring</h2>
                <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-2" dusk="trip-packing">
                    @foreach($packing as $item)
                        <div class="flex items-start gap-3 rounded-xl border border-ink/8 bg-surface px-4 py-3">
                            <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 mt-0.5 text-moss" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $item['icon'] }}"/></svg>
                            <div class="min-w-0">
                                <span class="block text-sm font-semibold">{{ $item['label'] }}</span>
                                <span class="block text-xs text-ink/65 leading-snug">{{ $item['note'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ═══ MEETUP POINTS ═══ --}}
            {{-- Pickups and drop-offs render identically; only the heading differs. --}}
            @foreach([['Pickup', 'Pickup points', $trip->meetups], ['Drop-off', 'Drop-off points', $trip->dropoffs]] as [$one, $many, $spots])
            @if($spots->isNotEmpty())
                <h2 class="tracking-tight font-semibold text-xl mt-9 mb-4">
                    {{ $spots->count() > 1 ? $many : $one }}
                </h2>

                <div class="space-y-4">
                    @foreach($spots as $i => $spot)
                        <div class="rounded-2xl border border-ink/8 bg-surface overflow-hidden">
                            <div class="flex items-start gap-3 p-4">
                                <span class="grid place-items-center w-7 h-7 rounded-full bg-terra text-cream text-xs font-bold shrink-0 mt-0.5">{{ $i + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold">
                                        {{ $spot->label }}
                                        @if($spot->time_label)
                                            <span class="ml-1.5 rounded-full bg-ink/[.06] px-2.5 py-1 text-xs font-bold align-middle">{{ $spot->time_label }}</span>
                                        @endif
                                    </div>
                                    @if($spot->address)<p class="text-sm text-ink/65 mt-0.5">{{ $spot->address }}</p>@endif
                                    @if($spot->note)<p class="text-sm text-ink/70 mt-1.5">{{ $spot->note }}</p>@endif
                                </div>
                                @if($spot->map_link)
                                    <a href="{{ $spot->map_link }}" target="_blank" rel="noopener noreferrer"
                                       class="shrink-0 inline-flex items-center gap-1.5 rounded-full border border-ink/15 px-3.5 py-2 text-xs font-semibold hover:bg-ink/5 transition-colors">
                                        Directions
                                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M8 7h9v9"/></svg>
                                    </a>
                                @endif
                            </div>

                            @if($spot->image_url)
                                <img src="{{ $spot->image_url }}" alt="{{ $one }} point: {{ $spot->label }}"
                                     class="w-full aspect-[16/9] object-cover" loading="lazy">
                            @endif

                            @if($spot->map_embed_url)
                                <iframe src="{{ $spot->map_embed_url }}" title="Map of {{ $spot->label }}"
                                        class="w-full h-56 border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                        allowfullscreen></iframe>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
            @endforeach

            {{-- Reviews --}}
            <div class="mt-10 pt-8 border-t border-ink/8">
                <div class="flex items-center justify-between">
                    <h2 class="tracking-tight font-semibold text-xl">Reviews @if($trip->reviews_count)<span class="text-ink/65 font-sans text-base font-normal">· {{ $trip->aggregate_rating }} ★ ({{ $trip->reviews_count }})</span>@endif</h2>
                    @if($this->canReview && ! $showReview)
                        <button wire:click="$set('showReview', true)" dusk="rate-trip" class="rounded-full border border-pine text-pine px-5 py-2 text-sm font-semibold">Rate this trip</button>
                    @endif
                </div>

                @if($showReview)
                    <div class="mt-5 rounded-2xl border border-ink/10 bg-surface p-6">
                        <div class="grid sm:grid-cols-3 gap-4">
                            @foreach(['trip' => 'The trip', 'host' => 'Your host', 'place' => 'The place'] as $key => $label)
                                <div>
                                    <div class="text-sm font-semibold text-ink/70 mb-1">{{ $label }}</div>
                                    <div class="flex gap-1">
                                        @for($n = 1; $n <= 5; $n++)
                                            <button type="button" wire:click="$set('{{ $key }}Rating', {{ $n }})" dusk="{{ $key }}-star-{{ $n }}"
                                                    aria-label="Rate {{ $n }} out of 5 for {{ $label }}"
                                                    aria-pressed="{{ $this->{$key.'Rating'} >= $n ? 'true' : 'false' }}"
                                                    class="text-xl {{ $this->{$key.'Rating'} >= $n ? 'text-amber' : 'text-ink/65' }}">★</button>
                                        @endfor
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <label for="review-body" class="sr-only">Your review</label>
                        <textarea id="review-body" wire:model="reviewBody" dusk="review-body" rows="3" class="mt-4 w-full rounded-xl border border-ink/15 px-4 py-3" placeholder="Share what it was like…"></textarea>
                        <button wire:click="submitReview" dusk="post-review" class="mt-3 rounded-full bg-pine text-cream px-6 py-2.5 font-semibold">Post review</button>
                    </div>
                @endif

                <div class="mt-6 space-y-5">
                    @forelse($reviews as $review)
                        <div class="border-b border-ink/5 pb-5 last:border-0">
                            <div class="flex items-center gap-2">
                                <span class="text-amber text-sm">{{ str_repeat('★', $review->rating) }}</span>
                                <span class="font-semibold text-sm">{{ $review->joiner->name }}</span>
                            </div>
                            @if($review->body)<p class="text-ink/70 mt-1.5">{{ $review->body }}</p>@endif

                            {{-- The host's answer, and the way to write one.

                                 Indented under the review it answers rather than presented as a peer comment: a reply is
                                 a response to that person, and floating it alongside would read as a second review. --}}
                            @if($review->hasReply())
                                <div class="mt-3 ml-4 pl-4 border-l-2 border-moss/30" dusk="reply-{{ $review->id }}">
                                    <p class="text-xs font-semibold text-moss">
                                        {{ $trip->host->name }} replied
                                        <span class="font-normal text-ink/65">· {{ $review->replied_at->format('j M Y') }}</span>
                                    </p>
                                    <p class="text-ink/70 text-sm mt-1">{{ $review->reply_body }}</p>
                                </div>
                            @endif

                            @if(auth()->id() === $trip->host_id)
                                @if($replyingTo === $review->id)
                                    <form wire:submit="reply({{ $review->id }})" class="mt-3 ml-4 pl-4 border-l-2 border-ink/10">
                                        <textarea wire:model="replyBody.{{ $review->id }}" rows="3" dusk="reply-box-{{ $review->id }}"
                                                  class="field" placeholder="Answer this review — everyone reading it will see your reply."></textarea>
                                        @error('replyBody.'.$review->id)<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                        <div class="flex items-center gap-2 mt-2">
                                            <button dusk="save-reply-{{ $review->id }}"
                                                    class="rounded-full bg-pine text-cream px-4 min-h-[40px] text-sm font-semibold hover:bg-pine/90 transition-colors">Post reply</button>
                                            <button type="button" wire:click="cancelReply"
                                                    class="rounded-full border border-ink/20 px-4 min-h-[40px] text-sm font-semibold hover:border-ink/40 transition-colors">Cancel</button>
                                        </div>
                                    </form>
                                @else
                                    <button type="button" wire:click="startReply({{ $review->id }})" dusk="start-reply-{{ $review->id }}"
                                            class="mt-2 text-xs font-semibold text-moss hover:underline">
                                        {{ $review->hasReply() ? 'Edit your reply' : 'Reply' }}
                                    </button>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="text-ink/65">No reviews yet — be the first to come back and rate it.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- RIGHT: sticky booking card --}}
        {{-- Booking card. One element, two lives: a sticky sidebar card on desktop, and on
             phones a glass sheet pinned to the bottom of the viewport whose details expand on
             tap. Repositioning beats duplicating — a second copy would mean duplicate ids and
             two sets of controls bound to the same Livewire state. --}}
        <div x-data="{ open: false }"
             class="md:sticky md:top-24
                    max-md:fixed max-md:inset-x-0 max-md:bottom-0 max-md:z-40">

            <div class="bg-surface border border-ink/10 rounded-2xl p-6 shadow-sm
                        max-md:glass max-md:rounded-none max-md:rounded-t-3xl max-md:border-x-0 max-md:border-b-0 max-md:p-5
                        max-md:shadow-[0_-12px_40px_-16px_rgba(31,28,21,.35)]">

                {{-- summary row: on mobile this is the collapsed sheet handle --}}
                @php($cur = $trip->host->hostProfile?->currency ?? 'PHP')
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="tracking-tight font-bold text-2xl">
                            {{ $cur }} {{ number_format($trip->current_price) }}
                            <span class="text-sm font-sans font-normal text-ink/70">/ person</span>
                        </div>
                        {{-- A rate that is about to expire is the single most useful thing
                             this card can say, so it says it. --}}
                        @if($tier = $trip->currentTier())
                            <p class="text-xs font-semibold text-moss mt-0.5">
                                {{ $tier->label }}@if($tier->window_label) · {{ $tier->window_label }}@endif
                            </p>
                        @endif
                        @if($next = $trip->nextTier())
                            <p class="text-xs text-ink/65">
                                then {{ $cur }} {{ number_format((float) $next->amount) }} from {{ $next->starts_at->format('j M') }}
                            </p>
                        @endif
                        @if($trip->takesDeposit())
                            <p class="text-xs text-ink/70 mt-1">
                                <b class="font-semibold">{{ $cur }} {{ number_format($trip->payable_now) }}</b> to reserve, rest on the day
                            </p>
                        @endif
                    </div>
                    @if($trip->openDepartures->isNotEmpty())
                        <button type="button" @click="open = !open" x-show="!open" dusk="sheet-toggle"
                                class="md:hidden shrink-0 rounded-full bg-pine text-cream px-6 py-3 font-semibold text-sm">
                            See dates
                        </button>
                        <button type="button" @click="open = false" x-show="open" x-cloak aria-label="Collapse"
                                class="md:hidden shrink-0 grid place-items-center w-11 h-11 rounded-full text-ink/65 hover:bg-ink/5">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 10l6 6 6-6"/></svg>
                        </button>
                    @endif
                </div>

                {{-- details: always shown from md up, collapsible below it --}}
                <div :class="open ? '' : 'max-md:hidden'">
                    @if($error)<p class="mt-4 rounded-xl bg-terra/10 text-terra-deep text-sm px-4 py-3">{{ $error }}</p>@endif

                    <div class="mt-4 space-y-2">
                        @forelse($trip->openDepartures as $d)
                            <label class="flex items-center justify-between rounded-xl border px-4 py-3 min-h-[44px] cursor-pointer transition-colors
                                          {{ $departureId === $d->id ? 'border-moss ring-1 ring-moss bg-moss/5' : 'border-ink/10 hover:border-ink/25' }}">
                                <span class="flex items-center gap-3">
                                    <input type="radio" wire:model.live="departureId" value="{{ $d->id }}" class="text-moss focus:ring-moss">
                                    <span class="font-semibold">{{ $d->starts_at->format('M j') }}@if($d->ends_at)–{{ $d->ends_at->format('j') }}@endif</span>
                                </span>
                                <span class="text-ink/70 text-sm">{{ $d->spotsLeft() }} spots</span>
                            </label>
                        @empty
                            <p class="text-ink/65 text-sm">No upcoming departures.</p>
                        @endforelse
                    </div>

                    @if($trip->openDepartures->isNotEmpty())
                        <div class="mt-4 flex items-center justify-between">
                            <label for="party-size" class="text-sm text-ink/70">Guests</label>
                            <input id="party-size" type="number" min="1" max="10" wire:model.live="party" aria-label="Number of guests" class="w-20 rounded-lg border border-ink/15 px-3 py-2 text-center">
                        </div>
                        <button wire:click="reserve" wire:loading.attr="disabled"
                                class="mt-5 w-full rounded-full bg-pine text-cream py-3.5 font-semibold hover:bg-pine/90 transition-colors">Join this trip</button>
                        <p class="mt-2.5 text-center text-xs text-ink/65">Spot held 24h · you pay the host directly</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- clears the fixed sheet so the last of the page is never trapped underneath it --}}
    @if($trip->openDepartures->isNotEmpty())
        <div class="md:hidden h-24" aria-hidden="true"></div>
    @endif
</div>
