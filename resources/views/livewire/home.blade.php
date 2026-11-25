<div class="trail-bg">
    @php($types = \App\Support\PlaceCategories::resolve($tripTypes->keys()))

    {{-- ═══════════════════════════════ SEARCH ═══════════════════════════════
         No hero photograph. The page opens on the thing people came to do, and the first row of trips is
         already on screen below it. --}}
    <section class="mx-auto max-w-3xl px-6 pt-8 text-center md:pt-12">
        <h1 class="text-balance text-3xl font-semibold tracking-tight md:text-4xl">Find your next group trip</h1>
        <p class="mt-2 text-balance text-ink/65">Join a departure led by a local host. Pick a trip, reserve a seat, and show up.</p>

        <form action="{{ route('trips.index') }}" method="get" role="search"
              class="mx-auto mt-7 flex max-w-2xl items-stretch rounded-full border border-ink/10 bg-surface text-left shadow-[0_6px_24px_-10px_rgba(31,28,21,.28)] transition-shadow focus-within:shadow-[0_10px_32px_-10px_rgba(31,28,21,.4)]">
            <label class="flex min-w-0 flex-1 cursor-text flex-col justify-center rounded-full px-6 py-2.5 transition-colors hover:bg-ink/[.04]">
                <span class="text-xs font-semibold">Where</span>
                <input type="search" name="search" placeholder="Search destinations or trips" autocomplete="off"
                       class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink/65 focus:ring-0" dusk="home-search">
            </label>

            @if(count($types))
                <span class="my-3 hidden w-px bg-ink/10 md:block" aria-hidden="true"></span>
                <label class="hidden min-w-0 flex-1 flex-col justify-center rounded-full px-6 py-2.5 transition-colors hover:bg-ink/[.04] md:flex">
                    <span class="text-xs font-semibold">Terrain</span>
                    <select name="category" class="w-full border-0 bg-transparent p-0 pr-6 text-sm text-ink/65 focus:ring-0">
                        <option value="">Any terrain</option>
                        @foreach($types as $type)
                            <option value="{{ $type['key'] }}">{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <button type="submit" aria-label="Search trips"
                    class="m-2 grid h-12 w-12 shrink-0 place-items-center rounded-full bg-terra text-cream transition hover:bg-terra-deep active:scale-95">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.6"/><path d="M15.8 15.8l4.7 4.7"/></svg>
            </button>
        </form>
    </section>

    {{-- the kinds of trip, as a strip you can swipe --}}
    @if(count($types))
        <nav aria-label="Trip types" class="mt-6 border-b border-ink/10">
            <ul class="no-scrollbar mx-auto flex max-w-7xl gap-8 overflow-x-auto px-6 md:justify-center">
                @foreach($types as $type)
                    <li class="shrink-0">
                        <a href="{{ route('trips.index', ['category' => $type['key']]) }}"
                           class="flex flex-col items-center gap-2 border-b-2 border-transparent py-3 text-xs font-semibold text-ink/65 transition-colors hover:border-ink/25 hover:text-ink">
                            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $type['icon'] }}"/></svg>
                            {{ $type['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    {{-- ═══════════════════════════════ TRIPS ═══════════════════════════════ --}}
    @php($tripSlot = 'shrink-0 snap-start w-[72vw] sm:w-[calc(50%-10px)] md:w-[calc(33.333%-13.34px)] lg:w-[calc(25%-15px)]')

    @if($soon->isNotEmpty())
        <x-scroller title="Departing soon" :href="route('trips.index')" class="mt-8" dusk="departing-soon">
            @foreach($soon as $trip)
                <div class="{{ $tripSlot }} stagger-in" style="--i:{{ $loop->index }}"><x-trip-tile :trip="$trip" /></div>
            @endforeach
        </x-scroller>
    @endif

    {{-- ═══════════════════════════════ HOSTS ═══════════════════════════════ --}}
    @if($hosts->isNotEmpty())
        <x-scroller title="Top hosts" :href="route('hosts.index')" class="mt-10 md:mt-14">
            @foreach($hosts as $host)
                <div class="w-[58vw] shrink-0 snap-start sm:w-[calc(33.333%-13.34px)] md:w-[calc(25%-15px)] lg:w-[calc(20%-16px)] stagger-in" style="--i:{{ $loop->index }}"><x-host-tile :host="$host" /></div>
            @endforeach
        </x-scroller>
    @endif

    {{-- more rows, one per kind of trip that has enough to fill it --}}
    @php($rowTitles = ['hike' => 'Hikes', 'island' => 'Islands', 'beach' => 'Beaches', 'surf' => 'Surf trips', 'city' => 'City trips'])
    @foreach($rows as $category => $trips)
        <x-scroller :title="$rowTitles[$category] ?? ucfirst($category)" :href="route('trips.index', ['category' => $category])" class="mt-10 md:mt-14">
            @foreach($trips as $trip)
                <div class="{{ $tripSlot }}"><x-trip-tile :trip="$trip" /></div>
            @endforeach
        </x-scroller>
    @endforeach

    {{-- ═══════════════════════════════ HOSTING ═══════════════════════════════
         Organisers do not know they can register and post for free, so this section says so and shows the
         three steps. A guest sees it; a joiner or a host already chose a side at signup, so they do not. --}}
    @guest
        <section id="host" class="mx-auto mt-16 max-w-7xl px-6 md:mt-24">
            <div class="grid overflow-hidden rounded-3xl bg-sand md:grid-cols-2">
                <div class="p-8 md:p-12 lg:p-14">
                    <h2 class="text-3xl font-semibold tracking-tight md:text-4xl">Host your Trips on Terra</h2>
                    <p class="mt-3 max-w-md text-ink/70">List your trips for free, fill your seats, and get paid directly by your guests. Terra takes no cut.</p>

                    <ol class="mt-8 space-y-5">
                        @foreach([
                            ['Create your host account', 'Sign up with your name and email. No fee, no card.'],
                            ['Post your trip', 'Add the destination, dates, price and what is included, then publish.'],
                            ['Welcome your guests', 'Guests reserve with you and pay you directly. Run the roster and check-in in one place.'],
                        ] as $i => $step)
                            <li class="flex gap-4">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-ink text-sm font-semibold text-cream" aria-hidden="true">{{ $i + 1 }}</span>
                                <span>
                                    <span class="block font-semibold">{{ $step[0] }}</span>
                                    <span class="block text-sm text-ink/70">{{ $step[1] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>

                    <div class="mt-9 flex flex-wrap items-center gap-x-6 gap-y-3">
                        <a href="{{ route('register', ['role' => 'host']) }}"
                           class="inline-flex items-center rounded-full bg-terra px-7 py-3.5 font-semibold text-cream transition hover:bg-terra-deep active:scale-[.98]">Host your Trips</a>
                        <p class="text-sm text-ink/65">
                            <span class="font-semibold text-ink tabular-nums" data-count="{{ $stats['hosts'] }}">{{ $stats['hosts'] }}</span> {{ Str::plural('host', $stats['hosts']) }} already {{ $stats['hosts'] === 1 ? 'runs' : 'run' }}
                            <span class="font-semibold text-ink tabular-nums" data-count="{{ $stats['trips'] }}">{{ $stats['trips'] }}</span> open {{ Str::plural('trip', $stats['trips']) }} here.
                        </p>
                    </div>
                </div>

                <div class="relative min-h-[16rem] md:min-h-full">
                    <img src="{{ asset('images/stock/pulag-climb.jpg') }}" alt="" aria-hidden="true" loading="lazy" class="absolute inset-0 h-full w-full object-cover">
                </div>
            </div>
        </section>
    @endguest
</div>
