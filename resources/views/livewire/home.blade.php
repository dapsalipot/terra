<div>
    {{-- ═══════════════════════════════ HERO — Mount Pulag, full-photography ═══════════════════════════════ --}}
    <section class="relative overflow-hidden text-cream min-h-[92vh] flex flex-col justify-center">
        {{-- Mount Pulag sunrise over the sea of clouds --}}
        <img src="{{ asset('images/stock/pulag-range.jpg') }}" alt="" aria-hidden="true"
             class="absolute inset-0 w-full h-full object-cover object-[center_30%]">

        {{-- scrims: readable text without flattening the photo --}}
        <div class="absolute inset-0 bg-gradient-to-r from-ink/75 via-ink/30 to-transparent" aria-hidden="true"></div>

        {{-- a whisper of shadow at the very bottom so the photo has a grounded edge --}}
        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-ink/35 to-transparent" aria-hidden="true"></div>

        <div class="grain-overlay" style="opacity:.3" aria-hidden="true"></div>

        {{-- Mountain divider: solid paper-cut ranges rise out of the photo, the front range is the
             canvas colour so it becomes the next section. Solid fills (no alpha) keep them crisp
             instead of muddying against the dark hillside. The trail draws itself on scroll. --}}
        <div class="trail-band absolute inset-x-0 bottom-0 pointer-events-none" data-reveal aria-hidden="true">
            <svg class="w-full h-52 md:h-72 block" viewBox="0 0 1440 260" preserveAspectRatio="none">
                <defs>
                    {{-- the dashed trail can't draw itself via stroke-dashoffset (the dash pattern
                         is already using it), so it is wiped in left-to-right by this clip --}}
                    <clipPath id="trail-reveal" clipPathUnits="userSpaceOnUse">
                        <rect class="trail-wipe" x="0" y="0" width="1440" height="260"/>
                    </clipPath>
                </defs>

                <path d="M0 110 L260 30 L520 100 L820 18 L1120 92 L1440 46 L1440 260 L0 260 Z" fill="#CDBC98"/>
                <path d="M0 160 L300 88 L600 150 L900 74 L1200 140 L1440 104 L1440 260 L0 260 Z" fill="#E6DAC0"/>
                <path d="M0 216 L340 176 L680 212 L1000 164 L1280 206 L1440 184 L1440 260 L0 260 Z" fill="#FBF9F4"/>

                {{-- a broken-line route climbing to the summit. non-scaling-stroke keeps the dashes
                     and weight even despite the non-uniform preserveAspectRatio="none" scaling --}}
                <g clip-path="url(#trail-reveal)">
                    <path d="M70 236 C260 220 420 200 580 186 C700 175 810 130 870 92 L896 76"
                          fill="none" stroke="#BC4B26" stroke-width="2.5" stroke-dasharray="3 10"
                          stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                    <path d="M890 68 l7 7 l7 -7 l-7 -11 z" fill="#BC4B26"/>
                </g>
            </svg>
        </div>

        <div class="relative w-full max-w-6xl mx-auto px-6 pt-28 md:pt-32 pb-60 md:pb-80">
            <div class="reveal flex items-center gap-4" style="animation-delay:.04s">
                <span class="marker text-xs font-semibold text-[#E8C58C]">Group trips · hosted by locals</span>
                <span class="h-px flex-1 bg-cream/15"></span>
            </div>

            <h1 class="reveal display font-serif font-semibold mt-10 max-w-[60rem]" style="animation-delay:.12s">
                Better trips with<br><span class="italic text-[#E8C58C]">better peeps.</span>
            </h1>

            <p class="reveal text-lg text-cream/80 mt-8 max-w-lg" style="animation-delay:.2s">
                Hikes, islands, road trips — skip the solo logistics. Join a departure led by a host who knows the way, and just show up.
            </p>

            {{-- glass over the Pulag photo: the sunrise refracts through the search bar --}}
            <div class="reveal glass glass-interactive mt-9 flex flex-wrap items-center gap-2 rounded-full p-2 pl-6 max-w-xl" style="animation-delay:.28s">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-ink/45" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="11" cy="11" r="6.6"/><path d="M15.8 15.8l4.7 4.7"/></svg>
                <a href="{{ route('trips.index') }}" class="flex-1 text-ink/70 text-sm py-2 min-w-[10rem]">Search a destination, country, or host…</a>
                <a href="{{ route('trips.index') }}" class="rounded-full bg-pine text-cream px-6 py-3 text-sm font-semibold hover:bg-pine/90 transition-colors">Explore</a>
            </div>

            <dl class="reveal mt-12 flex flex-wrap gap-x-10 gap-y-5 max-w-xl" style="animation-delay:.36s">
                @foreach([[$stats['departures'], 'Trips open'], [$stats['hosts'], 'Hosts']] as $stat)
                    <div class="relative pl-5 first:pl-0 before:absolute before:left-0 before:top-1 before:bottom-1 before:w-px before:bg-cream/25 first:before:hidden">
                        <dd class="font-serif font-semibold text-4xl leading-none">{{ $stat[0] }}</dd>
                        <dt class="marker text-xs font-semibold text-cream/60 mt-2">{{ $stat[1] }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ═══════════════════════════════ 01 · BROWSE BY TERRAIN (overlaps the hero bleed) ═══════════════════════════════ --}}
    @php($typeMeta = [
        'hike'   => ['Hikes',   'M2.5 19.5l6.2-9.4 3.6 5 3.1-4.6 6.1 9z'],
        'island' => ['Islands', 'M3 18h18M6 18c0-4 2.5-7 6-7s6 3 6 7M12 4c-2 1.5-2 4 0 5.5'],
        'beach'  => ['Beaches', 'M3 16c2-2 4-2 6 0s4 2 6 0 4-2 6 0M3 20c2-2 4-2 6 0s4 2 6 0 4-2 6 0'],
        'surf'   => ['Surf',    'M4 15c4-9 12-11 16-11-1 8-6 15-14 15-1 0-2-.2-2-.5M4 15c0 2 1 3 2 3.5'],
        'city'   => ['City',    'M4 21V9l5-3v15M9 21V6l6-3v18M15 21V11l5 2v8M4 21h16'],
    ])
    <section class="max-w-6xl mx-auto px-6 mt-16 relative">
        <div class="flex items-baseline gap-4 mb-6" data-reveal>
            <span class="font-serif italic text-terra text-xl leading-none">01</span>
            <h2 class="font-serif font-semibold text-2xl md:text-3xl leading-none">Browse by terrain</h2>
            <span class="hidden sm:block h-px flex-1 bg-ink/12 self-center"></span>
        </div>
        <div class="flex flex-wrap gap-3">
            @foreach($tripTypes as $cat => $count)
                @php($meta = $typeMeta[$cat] ?? [ucfirst($cat), 'M2.5 19.5l6.2-9.4 3.6 5 3.1-4.6 6.1 9z'])
                <a href="{{ route('trips.index', ['category' => $cat]) }}" data-reveal style="--reveal-delay:{{ .06 * $loop->index }}s"
                   class="group flex items-center gap-3 rounded-2xl border border-ink/10 bg-surface px-5 py-3.5 shadow-[0_16px_40px_-20px_rgba(31,28,21,.35)] hover:shadow-lg hover:border-ink/15 hover:-translate-y-0.5 transition-all">
                    <span class="grid place-items-center w-10 h-10 rounded-xl bg-moss/10 text-pine group-hover:bg-moss group-hover:text-cream transition-colors">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $meta[1] }}"/></svg>
                    </span>
                    <span>
                        <span class="block font-serif font-semibold leading-none">{{ $meta[0] }}</span>
                        <span class="block text-xs text-ink/65 mt-1">{{ $count }} {{ Str::plural('trip', $count) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- destination marquee — quiet interlude --}}
    @php($names = $trips->pluck('place.name')->filter()->unique()->values())
    @if($names->isNotEmpty())
        <div class="marquee overflow-hidden py-5 mt-16" aria-hidden="true">
            <div class="marquee-track">
                @foreach([0, 1] as $dup)
                    <div class="flex items-center shrink-0">
                        @foreach($names as $name)
                            <span class="marker text-xs font-semibold text-ink/45 px-6 whitespace-nowrap">{{ $name }}</span>
                            <svg viewBox="0 0 24 24" class="w-2.5 h-2.5 text-terra/60 shrink-0" fill="currentColor"><path d="M3 19.5l6.4-9.7 3.7 5.1 3.2-4.7 6.3 9.3z"/></svg>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════ 02 · DEPARTING SOON (lead + digest) ═══════════════════════════════ --}}
    @php($featured = $trips->first())
    @php($digest = $trips->slice(1)->take(4))
    <section class="max-w-6xl mx-auto px-6 mt-20 relative">
        <div class="flex items-baseline gap-4 mb-8" data-reveal dusk="departing-soon">
            <span class="font-serif italic text-terra text-xl leading-none">02</span>
            <h2 class="font-serif font-semibold text-2xl md:text-3xl leading-none">Departing soon</h2>
            <span class="hidden sm:block h-px flex-1 bg-ink/12 self-center"></span>
            <a href="{{ route('trips.index') }}" class="inline-flex items-center py-1.5 -my-1.5 text-sm font-semibold text-terra whitespace-nowrap">Browse all →</a>
        </div>

        @if($featured)
            <div class="grid lg:grid-cols-[1.35fr_1fr] gap-8 lg:gap-12 items-start">
                {{-- LEAD --}}
                <a href="{{ route('trips.show', $featured) }}" class="group block lift" data-reveal>
                    <div class="relative rounded-3xl overflow-hidden aspect-[16/11] shadow-[0_30px_60px_-40px_rgba(31,28,21,.6)]">
                        <img src="{{ $featured->cover_url }}" alt="{{ $featured->title }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-700">
                        <div class="grain-overlay" style="opacity:.25" aria-hidden="true"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/80 via-ink/15 to-transparent"></div>
                        <span class="absolute top-4 left-4 rounded-full bg-cream/90 px-3 py-1 text-xs font-semibold capitalize">{{ $featured->place?->category }}</span>
                        @if($featured->reviews_count)
                            <span class="absolute top-4 right-4 rounded-full bg-ink/45 backdrop-blur px-3 py-1 text-xs font-semibold text-cream"><span class="text-amber">★</span> {{ $featured->aggregate_rating }}</span>
                        @endif
                        <div class="absolute inset-x-0 bottom-0 p-6 md:p-8">
                            <span class="marker text-xs font-semibold text-cream/70">Lead departure</span>
                            <h3 class="font-serif font-semibold text-3xl md:text-4xl text-cream leading-tight mt-2 max-w-lg">{{ $featured->title }}</h3>
                            <p class="text-cream/80 mt-2">{{ $featured->place?->country }} · led by {{ $featured->host->name }}</p>
                            <p class="mt-4 font-serif font-bold text-2xl text-cream">{{ $featured->host->hostProfile?->currency ?? 'PHP' }} {{ number_format($featured->price) }}<span class="text-sm font-sans font-normal text-cream/70"> / person</span></p>
                        </div>
                    </div>
                </a>

                {{-- DIGEST --}}
                <div class="flex flex-col h-full">
                    <span class="marker text-xs font-semibold text-ink/55 mb-1" data-reveal>Also departing</span>
                    <div class="divide-y divide-ink/10">
                        @foreach($digest as $t)
                            <a href="{{ route('trips.show', $t) }}" class="group flex items-center gap-4 py-4" data-reveal style="--reveal-delay:{{ .08 * $loop->index }}s">
                                <div class="relative w-16 h-16 rounded-xl overflow-hidden shrink-0">
                                    <img src="{{ $t->cover_url }}" alt="{{ $t->title }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-serif font-semibold leading-snug truncate group-hover:text-pine transition-colors">{{ $t->title }}</h4>
                                    <p class="text-sm text-ink/65 truncate">{{ $t->place?->country }} · {{ $t->host->name }}</p>
                                </div>
                                <span class="font-serif font-bold whitespace-nowrap">{{ $t->host->hostProfile?->currency ?? 'PHP' }} {{ number_format($t->price) }}</span>
                            </a>
                        @endforeach
                    </div>
                    <a href="{{ route('trips.index') }}" data-reveal class="mt-6 inline-flex items-center justify-center rounded-full border border-pine/25 px-5 py-3 text-sm font-semibold text-pine hover:bg-pine hover:text-cream transition-colors">Browse all trips</a>
                </div>
            </div>
        @endif
    </section>

    {{-- ═══════════════════════════════ 03 · HOW TERRA WORKS ═══════════════════════════════ --}}
    <section class="max-w-6xl mx-auto px-6 mt-24">
        <div class="flex items-baseline gap-4 mb-10" data-reveal>
            <span class="font-serif italic text-terra text-xl leading-none">03</span>
            <h2 class="font-serif font-semibold text-2xl md:text-3xl leading-none">How Peeps works</h2>
            <span class="hidden sm:block h-px flex-1 bg-ink/12 self-center"></span>
        </div>
        <div class="relative grid md:grid-cols-3 gap-8">
            <div class="hidden md:block absolute top-6 left-[16%] right-[16%] border-t-2 border-dashed border-moss/35"></div>
            @php($steps = [
                ['Find a trip', 'Browse departures by terrain, country, or date. Every trip is run by a real host.'],
                ['Reserve &amp; pay your host', 'Hold your spot, then pay the host directly — Peeps never takes a cut of the trip.'],
                ['Show up with the crew', 'Get confirmed, meet everyone in the trip chat, and just turn up at the meetup.'],
            ])
            @foreach($steps as $i => $step)
                <div class="relative text-center md:px-4" data-reveal style="--reveal-delay:{{ .12 * $i }}s">
                    <span class="relative z-10 mx-auto grid place-items-center w-12 h-12 rounded-full bg-pine text-cream font-serif font-semibold">{{ $i + 1 }}</span>
                    <h3 class="font-serif font-semibold text-lg mt-4">{!! $step[0] !!}</h3>
                    <p class="text-sm text-ink/70 mt-2 leading-relaxed">{{ $step[1] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ═══════════════════════════════ 04 · FRESH OFF A TRIP ═══════════════════════════════ --}}
    <section class="mt-24 bg-pine text-cream relative overflow-hidden">
        <div class="absolute inset-0" style="background:linear-gradient(174deg,#242E17 0%,#2C3826 55%,#1C2411 100%)" aria-hidden="true"></div>
        <div class="glow-breathe absolute -bottom-56 left-[10%] w-[40rem] h-[40rem] rounded-full pointer-events-none" style="background:radial-gradient(circle, rgba(232,197,140,.12) 0%, transparent 65%)" aria-hidden="true"></div>
        <div class="grain-overlay" style="opacity:.35" aria-hidden="true"></div>
        <div class="relative max-w-6xl mx-auto px-6 py-16 md:py-20">
            <div class="flex items-baseline gap-4 mb-9" data-reveal>
                <span class="font-serif italic text-amber text-xl leading-none">04</span>
                <h2 class="font-serif font-semibold text-2xl md:text-3xl leading-none">Fresh off a trip</h2>
                <span class="hidden sm:block h-px flex-1 bg-cream/20 self-center"></span>
                <a href="{{ route('hosts.index') }}" class="inline-flex items-center py-1.5 -my-1.5 text-sm font-semibold text-cream/75 hover:text-cream whitespace-nowrap">Meet the hosts →</a>
            </div>

            @if($reviews->isNotEmpty())
                <div class="grid md:grid-cols-3 gap-5">
                    @foreach($reviews as $r)
                        <figure class="rounded-2xl bg-cream/[.06] border border-cream/10 p-6 hover:bg-cream/[.09] transition-colors" data-reveal style="--reveal-delay:{{ .1 * $loop->index }}s">
                            <div class="font-serif italic text-[#E8C58C] text-4xl leading-none">“</div>
                            <blockquote class="mt-1 text-cream/90 leading-relaxed">{{ $r->body }}</blockquote>
                            <figcaption class="mt-4 flex items-center justify-between text-sm">
                                <span class="text-cream/60">{{ $r->joiner->name }} · {{ $r->reviewable?->title }}</span>
                                <span class="text-[#E8C58C] tracking-widest">{{ str_repeat('★', $r->rating) }}</span>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            @else
                <p class="text-cream/70 max-w-md">Every completed trip earns a review here — the wall of proof that turns a stranger into a regular.</p>
            @endif

            <div class="mt-12 pt-10 border-t border-cream/[.14]">
                <span class="marker text-xs font-semibold text-cream/55" data-reveal>Hosts to follow</span>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-5">
                    @foreach($hosts as $host)
                        <a href="{{ route('hosts.show', $host) }}" class="rounded-2xl bg-cream/[.06] border border-cream/10 p-5 hover:bg-cream/10 hover:-translate-y-0.5 transition-all" data-reveal style="--reveal-delay:{{ .07 * $loop->index }}s">
                            <div class="flex items-center gap-3">
                                <span class="grid place-items-center w-11 h-11 rounded-full bg-cream/15 font-serif font-semibold shrink-0">{{ strtoupper(substr($host->name,0,1)) }}</span>
                                <div class="min-w-0">
                                    <div class="font-serif font-semibold truncate">{{ $host->name }}</div>
                                    <div class="text-xs text-cream/65">{{ $host->hostProfile?->rating ?? '—' }} ★ · {{ $host->hostProfile?->trips_count }} trips</div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════ CLOSING ═══════════════════════════════ --}}
    <section class="relative overflow-hidden text-cream">
        <img src="{{ asset('images/stock/pulag-clouds.jpg') }}" alt="" aria-hidden="true"
             class="absolute inset-0 w-full h-full object-cover object-[center_62%]">
        <div class="absolute inset-0 bg-ink/55" aria-hidden="true"></div>
        <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-ink/70 to-transparent" aria-hidden="true"></div>
        <div class="grain-overlay" style="opacity:.35" aria-hidden="true"></div>
        <div class="relative max-w-4xl mx-auto px-6 py-28 md:py-36 text-center">
            <span class="marker text-xs font-semibold text-[#E8C58C]" data-reveal>Your next trip</span>
            <h2 class="font-serif font-semibold text-4xl md:text-6xl leading-[1.02] mt-4" data-reveal style="--reveal-delay:.08s">Has a crew<br>already forming.</h2>
            <div class="mt-9 flex flex-wrap items-center justify-center gap-3" data-reveal style="--reveal-delay:.16s">
                <a href="{{ route('trips.index') }}" class="rounded-full bg-cream text-pine px-7 py-3.5 font-semibold shadow-[0_18px_40px_-24px_rgba(0,0,0,.8)] hover:bg-surface hover:-translate-y-0.5 transition-all">Browse trips</a>
                <a href="{{ route('register') }}" class="rounded-full border border-cream/40 bg-ink/20 backdrop-blur px-7 py-3.5 font-semibold text-cream hover:bg-ink/40 transition-colors">Host your own</a>
            </div>
        </div>
    </section>
</div>
