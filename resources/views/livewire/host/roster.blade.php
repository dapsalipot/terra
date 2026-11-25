@php
    $fmt = fn ($n) => $currency.' '.number_format($n);
    $modeLabels = ['money' => 'Money', 'manifest' => 'Manifest', 'checkin' => 'Check-in'];
    $filterLabels = [
        'all' => 'Everyone', 'proof' => 'New proof', 'unpaid' => 'Owing', 'paid' => 'Settled',
        'unconfirmed' => 'Not confirmed', 'refund' => 'Refund owed', 'cancelled' => 'Cancelled',
        'incomplete' => 'Details missing', 'no-id' => 'No ID',
        'waiting' => 'Not arrived', 'arrived' => 'Arrived',
    ];
@endphp

{{--
    ═══ THE ROSTER ═══

    Rebuilt from a 644-line template whose action cell rendered whichever of eight actions a
    booking's state allowed. One row carried six controls and its neighbour carried one, so
    nothing could align and 375px was hopeless.

    The rule now: a row is a record. It shows a name, a state, a figure, and an arrow. Every
    action lives in the drawer. The one exception is check-in, which earns an inline toggle
    because it is done repeatedly, one-handed, with a person standing in front of you.
--}}
<div class="w-full px-4 sm:px-6 2xl:px-10 py-6" dusk="roster">

    {{-- ═══ HEADER ═══ --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div class="min-w-0">
            <a href="{{ route('host.dashboard') }}" wire:navigate
               class="inline-flex items-center min-h-[44px] -my-2 text-sm text-ink/70 hover:text-ink">← Dashboard</a>
            <h1 class="tracking-tight font-semibold text-2xl sm:text-3xl leading-tight mt-1">{{ $trip->title }}</h1>
            <p class="text-sm text-ink/70">
                {{ $departure->starts_at->format('D j M Y') }}
                @if($departure->isMultiDay()) → {{ $departure->ends_at->format('j M') }} @endif
                · {{ $fmt($trip->current_price) }} per person
            </p>
        </div>

        {{-- One primary, everything else behind the menu. Six equal buttons gave a destructive
             action the same weight as adding a guest. --}}
        <div class="flex items-center gap-2 shrink-0">
            <button wire:click="$toggle('showAdd')" dusk="toggle-add"
                    class="rounded-full bg-pine text-cream px-4 min-h-[44px] text-sm font-semibold hover:bg-pine/90 transition-colors">
                Add guest
            </button>

            <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                <button type="button" x-on:click="open = !open" dusk="roster-menu"
                        aria-haspopup="true" x-bind:aria-expanded="open ? 'true' : 'false'" aria-label="More actions"
                        class="grid place-items-center w-11 h-11 rounded-full border border-ink/15 hover:border-ink/35 transition-colors">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>
                </button>
                <div x-show="open" x-cloak x-on:click.outside="open = false"
                     class="absolute right-0 top-full mt-1 w-52 rounded-xl border border-ink/10 bg-surface shadow-lg py-1 z-30">
                    <a href="{{ route('host.trips.edit', $trip) }}" wire:navigate class="block px-4 py-2.5 text-sm hover:bg-ink/5">Edit trip</a>
                    <a href="{{ route('chat.show', $departure) }}" wire:navigate class="block px-4 py-2.5 text-sm hover:bg-ink/5">Trip chat</a>
                    <a href="{{ route('host.trips.qr', $trip) }}" class="block px-4 py-2.5 text-sm hover:bg-ink/5">Trip QR</a>
                    @if($departure->status !== 'cancelled')
                        <div class="border-t border-ink/8 my-1"></div>
                        <x-confirm-action
                            action="cancelDeparture"
                            title="Cancel this whole departure?"
                            body="Every booking on it is cancelled and each joiner is emailed. Anyone who paid is listed as owed a refund."
                            confirm-label="Yes, cancel the trip"
                            cancel-label="Keep it"
                            tone="terra" icon="warning"
                            dusk="cancel-departure"
                            class="block w-full text-left px-4 py-2.5 text-sm text-terra hover:bg-terra/5">Cancel departure</x-confirm-action>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('livewire.host.partials.roster-stats')

    {{-- ═══ MODE ═══ --}}
    <div role="tablist" aria-label="View" dusk="roster-modes"
         class="flex gap-1 p-1 rounded-2xl bg-ink/[.04] border border-ink/8 w-full sm:w-fit overflow-x-auto mb-3">
        @foreach($modeLabels as $key => $label)
            <button type="button" wire:click="setMode('{{ $key }}')" role="tab"
                    aria-selected="{{ $mode === $key ? 'true' : 'false' }}"
                    tabindex="{{ $mode === $key ? '0' : '-1' }}"
                    dusk="mode-{{ $key }}"
                    class="flex-1 sm:flex-none whitespace-nowrap rounded-xl px-4 min-h-[44px] text-[13px] font-semibold transition-colors
                           {{ $mode === $key ? 'bg-surface text-ink shadow-[0_1px_3px_rgba(31,28,21,.12)]' : 'text-ink/70 hover:text-ink' }}">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ═══ SEARCH + FILTERS ═══ --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <label class="relative flex-1 min-w-[12rem]">
            <span class="sr-only">Search this roster</span>
            <input wire:model.live.debounce.250ms="search" type="search" dusk="roster-search"
                   placeholder="Search name, email or phone"
                   class="w-full rounded-full border border-ink/15 pl-10 pr-4 min-h-[44px] text-sm
                          placeholder:text-ink/65 focus:border-moss focus:ring-2 focus:ring-moss/25 transition-colors">
            <svg viewBox="0 0 24 24" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-ink/45" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        </label>

        @if($mode === 'checkin')
            <a href="{{ route('host.checkin', $departure) }}" wire:navigate dusk="open-checkin"
               class="inline-flex items-center gap-2 rounded-full border border-ink/15 px-4 min-h-[44px] text-sm font-semibold hover:border-ink/35 transition-colors">
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM19 19h2v2h-2z"/></svg>
                Scan a pass
            </a>
        @endif
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach($filters as $key)
            <button type="button" wire:click="setFilter('{{ $key }}')" dusk="filter-{{ $key }}"
                    aria-pressed="{{ $activeFilter === $key ? 'true' : 'false' }}"
                    class="inline-flex items-center gap-1.5 rounded-full px-4 min-h-[44px] text-sm font-semibold border transition-colors
                           {{ $activeFilter === $key ? 'bg-pine text-cream border-pine' : 'border-ink/15 hover:border-ink/35' }}">
                {{ $filterLabels[$key] ?? $key }}
                <span class="tabular-nums {{ $activeFilter === $key ? 'text-cream/80' : 'text-ink/65' }}">{{ $counts[$key] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    @if($showAdd)
        @include('livewire.host.partials.roster-add-guest')
    @endif

    {{-- ═══ LIST + DRAWER ═══ --}}
    <div class="xl:grid xl:grid-cols-[minmax(0,1fr)_22rem] xl:gap-5 xl:items-start">
        <div class="min-w-0">
            @if($reservations->isEmpty())
                <div class="rounded-2xl border border-dashed border-ink/15 p-10 text-center">
                    <p class="font-semibold">Nobody here</p>
                    <p class="text-sm text-ink/70 mt-1">
                        {{ $totalCount === 0 ? 'No bookings on this departure yet.' : 'No booking matches that filter.' }}
                    </p>
                </div>
            @else
                <div class="rounded-2xl border border-ink/10 bg-surface overflow-hidden">
                    @include('livewire.host.partials.roster-head')
                    <ul class="divide-y divide-ink/[.07]">
                        @foreach($reservations as $r)
                            @include('livewire.host.partials.roster-row')
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($waiting > 0)
                <p class="text-sm text-ink/70 mt-3">
                    {{ $waiting }} {{ \Illuminate\Support\Str::plural('person', $waiting) }} waiting for a seat on this date.
                </p>
            @endif
        </div>

        @include('livewire.host.partials.roster-drawer')
    </div>

    {{-- Inside the root element, not after it: a Livewire component has exactly one root, and
         anything past its closing tag is silently discarded — the modal simply never existed. --}}
    @include('livewire.host.partials.roster-proofs')
</div>
