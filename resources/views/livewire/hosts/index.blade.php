{{--
    A directory, not a list of names.

    This page was three white rectangles holding a name and two lines of bio — nothing to choose
    between hosts on, and nothing to narrow by. The search field leads because that is the job
    here; the chips below it are the same vocabulary the calendar and trip cards already use.
--}}
<div class="w-full px-5 sm:px-8 2xl:px-12 py-8 sm:py-10">

    {{-- ═══ HEADER + SEARCH ═══ --}}
    <div class="max-w-2xl">
        <h1 class="tracking-tight font-semibold text-3xl sm:text-4xl">Hosts on Terra</h1>
        <p class="text-ink/65 mt-2">
            The people who plan the route, book the permits and get everyone to the trailhead.
            Follow the ones you want to travel with again.
        </p>
    </div>

    <div class="mt-6 flex flex-col lg:flex-row lg:items-center gap-3">
        {{-- Search is the primary control on a directory, so it gets the width and the first tab stop. --}}
        <label class="relative flex-1 min-w-0">
            <span class="sr-only">Search hosts</span>
            <svg viewBox="0 0 24 24" class="absolute left-4 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-ink/40 pointer-events-none"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
            </svg>
            <input type="search" wire:model.live.debounce.300ms="search" dusk="host-search"
                   placeholder="Search by name, place or what they run"
                   class="w-full rounded-full border border-ink/15 bg-surface pl-11 pr-4 min-h-[48px]
                          placeholder:text-ink/65 focus:border-moss focus:ring-2 focus:ring-moss/25 transition-colors">
        </label>

        {{-- A native select, deliberately: five options do not earn a custom menu, and this one
             is reachable by keyboard and by screen reader without any work. --}}
        {{-- w-full on the label AND the select, so the custom chevron (positioned against the
             label) sits on the control's own edge. Stacked on a phone the label spanned the row
             while the select stayed intrinsic, and the arrow floated off on its own. --}}
        <label class="relative w-full lg:w-auto lg:shrink-0">
            <span class="sr-only">Sort hosts</span>
            <select wire:model.live="sort" dusk="host-sort"
                    class="appearance-none w-full rounded-full border border-ink/15 bg-surface pl-4 pr-10 min-h-[48px] text-sm font-semibold
                           focus:border-moss focus:ring-2 focus:ring-moss/25 transition-colors cursor-pointer">
                @foreach(\App\Livewire\Hosts\Index::SORTS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <svg viewBox="0 0 24 24" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-ink/45 pointer-events-none"
                 fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </label>
    </div>

    {{-- ═══ CATEGORY CHIPS ═══ --}}
    <div class="mt-4 flex flex-wrap items-center gap-2" role="group" aria-label="Filter by trip type">
        <button type="button" wire:click="setCategory('')" dusk="cat-all"
                aria-pressed="{{ $category === '' ? 'true' : 'false' }}"
                class="inline-flex items-center rounded-full border px-4 min-h-[44px] text-sm font-semibold transition-colors cursor-pointer
                       {{ $category === '' ? 'bg-pine text-cream border-pine' : 'border-ink/15 text-ink/70 hover:border-ink/35 hover:text-ink' }}">
            All hosts
        </button>

        @foreach(\App\Support\PlaceCategories::CATALOG as $key => $meta)
            @php($on = $category === $key)
            <button type="button" wire:click="setCategory('{{ $key }}')" dusk="cat-{{ $key }}"
                    aria-pressed="{{ $on ? 'true' : 'false' }}"
                    class="inline-flex items-center gap-1.5 rounded-full border px-4 min-h-[44px] text-sm font-semibold transition-colors cursor-pointer
                           {{ $on ? 'bg-pine text-cream border-pine' : 'border-ink/15 text-ink/70 hover:border-ink/35 hover:text-ink' }}">
                <svg viewBox="0 0 24 24" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="{{ $meta[1] }}"/>
                </svg>
                {{ $meta[0] }}
            </button>
        @endforeach
    </div>

    {{-- ═══ RESULT COUNT ═══ --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mt-6 pb-4 border-b border-ink/8">
        <p class="text-sm text-ink/65" aria-live="polite" dusk="host-count">
            {{-- total(), not count(): count() is how many are on this page, which would read
                 "24 hosts" on a directory of two hundred. --}}
            <span class="font-semibold text-ink">{{ $hosts->total() }}</span>
            {{ \Illuminate\Support\Str::plural('host', $hosts->total()) }}
            @if($hosts->total() !== $total) of {{ $total }} @endif
        </p>

        @if($this->hasFilters())
            <button type="button" wire:click="clearFilters" dusk="clear-filters"
                    class="text-sm font-semibold text-moss hover:underline cursor-pointer">Clear filters</button>
        @endif
    </div>

    {{-- ═══ CARDS ═══ --}}
    @if($hosts->isNotEmpty())
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 mt-6">
            @foreach($hosts as $host)
                @php($profile = $host->hostProfile)
                @php($kinds = $categoriesByHost[$host->id] ?? [])
                @php($upcoming = (int) ($upcomingByHost[$host->id] ?? 0))

                {{-- Same stretched-link shape as the trip card: a container with an absolutely
                     positioned anchor, so the "Follow"-adjacent bits below can be links too
                     without nesting anchors inside anchors. --}}
                <div class="stagger-in" style="--i:{{ min($loop->index, 8) }}">
                <div class="group relative flex flex-col h-full rounded-3xl border border-ink/8 bg-surface overflow-hidden
                            shadow-[0_10px_30px_-20px_rgba(31,28,21,.35)]
                            hover:shadow-[0_24px_44px_-24px_rgba(31,28,21,.45)] hover:-translate-y-1
                            transition-all duration-300 focus-within:ring-2 focus-within:ring-moss focus-within:ring-offset-2"
                     dusk="host-card-{{ $host->id }}">

                    <a href="{{ route('hosts.show', $host) }}" wire:navigate class="absolute inset-0 z-0 focus:outline-none">
                        <span class="sr-only">View {{ $host->name }}</span>
                    </a>

                    {{-- Cover band. Falls back to the host's own accent rather than a grey box, so
                         a profile with no photo still reads as theirs. --}}
                    <div class="relative h-24 shrink-0 overflow-hidden"
                         style="background: linear-gradient(135deg, {{ $profile?->accent_hex ?? '#5E6B44' }}, {{ $profile?->accent_soft ?? 'rgba(94,107,68,.35)' }});">
                        @if($profile?->cover_url)
                            <img src="{{ $profile->cover_url }}" alt="" loading="lazy"
                                 class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-105">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/25 to-transparent" aria-hidden="true"></div>

                        @if($upcoming > 0)
                            <span class="absolute top-3 right-3 rounded-full glass-dark px-2.5 py-1 text-[11px] font-bold text-cream">
                                {{ $upcoming }} upcoming
                            </span>
                        @endif
                    </div>

                    <div class="px-5 pb-5 flex-1 flex flex-col">
                        {{-- Avatar straddles the cover edge — the one bit of overlap on the card,
                             which is what makes it read as a profile rather than a tile. --}}
                        {{-- `relative` so it paints above the positioned cover band rather than
                             behind it, and `pointer-events-none` so raising it does not punch a
                             64px hole in the card's own stretched link. --}}
                        <div class="relative pointer-events-none -mt-8 mb-3">
                            <span class="grid place-items-center w-16 h-16 rounded-2xl overflow-hidden bg-pine text-cream
                                         tracking-tight font-semibold text-xl ring-4 ring-surface shadow-sm">
                                @if($host->avatar_url)
                                    <img src="{{ $host->avatar_url }}" alt="" class="w-full h-full object-cover">
                                @else
                                    {{ $host->initials }}
                                @endif
                            </span>
                        </div>

                        <h2 class="font-semibold text-lg leading-tight truncate">{{ $host->name }}</h2>
                        <p class="text-sm text-moss font-semibold truncate">&commat;{{ $host->handle }}</p>

                        @if($profile?->tagline)
                            <p class="text-sm text-ink/70 mt-2 line-clamp-2">{{ $profile->tagline }}</p>
                        @elseif($profile?->bio)
                            <p class="text-sm text-ink/70 mt-2 line-clamp-2">{{ $profile->bio }}</p>
                        @endif

                        @if($profile?->location)
                            <p class="flex items-center gap-1.5 text-xs text-ink/65 mt-2">
                                <x-icon.pin class="w-3.5 h-3.5 shrink-0 text-moss" />
                                <span class="truncate">{{ $profile->location }}</span>
                            </p>
                        @endif

                        @if($kinds)
                            <div class="flex flex-wrap gap-1.5 mt-3">
                                @foreach($kinds as $kind)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-moss/10 text-pine px-2 py-1 text-[11px] font-semibold">
                                        <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="{{ $kind['icon'] }}"/>
                                        </svg>
                                        {{ $kind['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        {{-- Stats last and quiet: they are what you compare on once the name and
                             the photo have done their work, not the headline. --}}
                        <dl class="grid grid-cols-3 gap-2 mt-auto pt-4 border-t border-ink/8">
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-ink/65 font-semibold">Trips</dt>
                                <dd class="font-semibold tabular-nums">{{ $host->published_trips }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-ink/65 font-semibold">Joiners</dt>
                                <dd class="font-semibold tabular-nums">{{ number_format($profile?->guests_count ?? 0) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-ink/65 font-semibold">Rating</dt>
                                <dd class="font-semibold tabular-nums">
                                    @if($profile?->rating)
                                        <span class="text-amber" aria-hidden="true">★</span> {{ $profile->rating }}
                                    @else
                                        <span class="text-ink/65">—</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
                </div>
            @endforeach
        </div>
        @if($hosts->hasPages())
            <div class="mt-8">{{ $hosts->links() }}</div>
        @endif
    @else
        {{-- Empty because of a filter is a different problem from empty because there is nobody,
             and the way out is different too. --}}
        <div class="mt-10 rounded-3xl border border-dashed border-ink/15 p-10 text-center" dusk="hosts-empty">
            <span class="inline-grid place-items-center w-14 h-14 rounded-full bg-moss/10 text-moss mb-4">
                <svg viewBox="0 0 24 24" class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                </svg>
            </span>

            @if($this->hasFilters())
                <p class="tracking-tight font-semibold text-xl">No hosts match that</p>
                <p class="text-ink/65 mt-1.5 max-w-sm mx-auto">
                    Try a different trip type, or search for a place instead of a name.
                </p>
                <button type="button" wire:click="clearFilters"
                        class="mt-5 inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[44px] font-semibold hover:bg-pine/90 transition-colors cursor-pointer">
                    Clear filters
                </button>
            @else
                <p class="tracking-tight font-semibold text-xl">No hosts yet</p>
                <p class="text-ink/65 mt-1.5 max-w-sm mx-auto">
                    Terra is new here. If you plan trips, this is a good moment to be the first.
                </p>
                <a href="{{ route('register') }}" wire:navigate
                   class="mt-5 inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[44px] font-semibold hover:bg-pine/90 transition-colors">
                    Become a host
                </a>
            @endif
        </div>
    @endif
</div>
