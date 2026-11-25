{{--
    A directory, not a list of names.

    This page was three white rectangles holding a name and two lines of bio — nothing to choose
    between hosts on, and nothing to narrow by. The search field leads because that is the job
    here; the chips below it are the same vocabulary the calendar and trip cards already use.
--}}
<div class="w-full px-5 sm:px-8 2xl:px-12 py-8 sm:py-10">

    {{-- ═══ HEADER + SEARCH ═══ --}}
    <div class="max-w-2xl">
        <h1 class="tracking-tight font-semibold text-3xl sm:text-4xl">Hosts on Tarana</h1>
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
                <div class="stagger-in" style="--i:{{ min($loop->index, 8) }}">
                    <x-host-card :host="$host" :extras="$hostExtras" :kinds="$categoriesByHost[$host->id] ?? []" />
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
                    Tarana is new here. If you plan trips, this is a good moment to be the first.
                </p>
                <a href="{{ route('register') }}" wire:navigate
                   class="mt-5 inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[44px] font-semibold hover:bg-pine/90 transition-colors">
                    Become a host
                </a>
            @endif
        </div>
    @endif
</div>
