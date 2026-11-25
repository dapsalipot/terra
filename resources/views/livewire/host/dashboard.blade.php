<div>
    <div class="flex flex-wrap items-end justify-between gap-4 mb-7">
        <div>
            <h1 class="font-serif font-semibold text-3xl mb-1">Host dashboard</h1>
            <p class="text-ink/65">Welcome back, {{ $host->name }}.</p>
        </div>
        <a href="{{ route('host.trips.create') }}" wire:navigate
           class="inline-flex items-center gap-2 rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors">
            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            New trip
        </a>
    </div>

    {{-- Only numbers a host can act on. "0 followers" was never a task. --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        <a href="{{ route('host.trips.index') }}" wire:navigate
           class="glass-card glass-interactive px-5 py-4">
            <div class="marker text-[10px] font-semibold text-ink/65">Confirmed guests</div>
            <div class="font-serif font-bold text-3xl leading-none mt-1.5 tabular-nums">{{ $confirmedGuests }}</div>
            <div class="text-sm text-ink/65 mt-1">across all schedules</div>
        </a>

        <div class="{{ $toConfirm ? 'glass-terra rounded-2xl' : 'glass-card' }} px-5 py-4">
            <div class="marker text-[10px] font-semibold {{ $toConfirm ? 'text-ink/75' : 'text-ink/65' }}">Payments to confirm</div>
            <div class="font-serif font-bold text-3xl leading-none mt-1.5 tabular-nums">{{ $toConfirm }}</div>
            <div class="text-sm text-ink/65 mt-1">{{ $toConfirm ? 'joiners waiting on you' : 'nothing waiting' }}</div>
        </div>

        <div class="glass-card px-5 py-4 col-span-2 lg:col-span-1">
            <div class="marker text-[10px] font-semibold text-ink/65">Holds awaiting payment</div>
            <div class="font-serif font-bold text-3xl leading-none mt-1.5 tabular-nums">{{ $awaitingPayment }}</div>
            <div class="text-sm text-ink/65 mt-1">places reserved but unpaid</div>
        </div>
    </div>

    {{-- ═══ CALENDAR ═══ --}}
    <section class="glass-card overflow-hidden">
        {{-- Stacked on a phone: the month, the toggle and the pager cannot share a 375px
             row without the title wrapping a word per line. --}}
        <header class="flex flex-col sm:flex-row sm:items-center gap-3 px-5 py-4 border-b border-ink/8">
            <h2 class="font-serif font-semibold text-xl sm:flex-1 sm:min-w-0">
                {{ $cursor->format('F Y') }}
                <span class="font-sans font-normal text-base text-ink/65">
                    · {{ $monthDepartures->count() }} {{ \Illuminate\Support\Str::plural('trip', $monthDepartures->count()) }}
                </span>
            </h2>

            {{-- Grid or list. Below sm the grid is unusable, so the list is forced there
                 regardless of this preference. --}}
            <div class="flex items-center gap-1 rounded-xl border border-ink/15 p-1 self-start sm:self-auto" role="group" aria-label="Calendar view">
                @foreach([
                    ['grid', 'Calendar', 'M7 3v3M17 3v3M3.5 9h17M4 6h16v14H4z'],
                    ['list', 'Event list', 'M4 6h16M4 12h16M4 18h16'],
                ] as [$key, $label, $icon])
                    <button type="button" wire:click="setView('{{ $key }}')" dusk="view-{{ $key }}"
                            aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 min-h-[36px] text-sm font-semibold transition-colors
                                   {{ $view === $key ? 'bg-moss text-cream' : 'text-ink/70 hover:bg-ink/[.05]' }}">
                        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-1.5 self-start sm:self-auto">
                <button type="button" wire:click="shiftMonth(-1)" dusk="month-prev" aria-label="Previous month"
                        class="grid place-items-center w-11 h-11 rounded-xl border border-ink/15 hover:border-ink/30 transition-colors">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 6l-6 6 6 6"/></svg>
                </button>
                <button type="button" wire:click="today" dusk="month-today"
                        class="rounded-xl border border-ink/15 px-4 min-h-[44px] text-sm font-semibold hover:border-ink/30 transition-colors">Today</button>
                <button type="button" wire:click="shiftMonth(1)" dusk="month-next" aria-label="Next month"
                        class="grid place-items-center w-11 h-11 rounded-xl border border-ink/15 hover:border-ink/30 transition-colors">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6l6 6-6 6"/></svg>
                </button>
            </div>
        </header>

        {{-- Month grid. Seven columns are unreadable on a phone, so below sm the same
             month renders as a list instead. --}}
        <div class="{{ $isList ? 'hidden' : '' }}">
            <div class="grid grid-cols-7 border-b border-ink/8">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                    <div class="marker text-[10px] font-semibold text-ink/65 text-center py-2">{{ $dow }}</div>
                @endforeach
            </div>

            @foreach($weeks as $week)
                <div class="grid grid-cols-7 border-b border-ink/8 last:border-b-0">
                    @foreach($week as $day)
                        @php($key = $day['date']->toDateString())
                        @php($isSelected = $selectedDate === $key)
                        {{-- aspect-square, not a fixed height: the console is full-width now,
                             so a 7rem cell under a 275px column read as a squat letterbox. --}}
                        <div class="border-r border-ink/8 last:border-r-0 sm:aspect-square sm:p-1.5 sm:flex sm:flex-col {{ $day['inMonth'] ? '' : 'bg-cream/40' }}">

                            {{-- PHONE: the whole cell is a target. 48px of column will not carry
                                 a title, so it carries dots and the day's trips list below. --}}
                            <button type="button" wire:click="selectDate('{{ $key }}')"
                                    dusk="day-{{ $key }}"
                                    aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                    aria-label="{{ $day['date']->format('j F') }}{{ $day['departures']->count() ? ', '.$day['departures']->count().' trips' : ', no trips' }}"
                                    class="sm:hidden w-full min-h-[3.25rem] flex flex-col items-center justify-center gap-1 transition-colors
                                           {{ $isSelected ? 'bg-moss/20' : '' }}">
                                <span class="grid place-items-center w-7 h-7 rounded-full text-xs font-semibold tabular-nums
                                             {{ $day['isToday'] ? 'bg-terra text-cream' : ($day['inMonth'] ? 'text-ink/70' : 'text-ink/65') }}">
                                    {{ $day['date']->format('j') }}
                                </span>
                                <span class="flex items-center justify-center gap-0.5 h-1.5">
                                    @foreach($day['departures']->take(3) as $dep)
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dep->spotsLeft() === 0 ? 'bg-terra' : 'bg-moss' }}"></span>
                                    @endforeach
                                    @if($day['departures']->count() > 3)
                                        <span class="text-[8px] font-bold text-ink/65 leading-none">+{{ $day['departures']->count() - 3 }}</span>
                                    @endif
                                </span>
                            </button>

                            {{-- TABLET AND UP: the cell has room for the cards themselves. --}}
                            <div class="hidden sm:flex sm:flex-col sm:min-h-0 sm:flex-1">
                            <div class="flex items-center justify-between px-1 mb-1">
                                <span class="text-xs font-semibold tabular-nums {{ $day['inMonth'] ? 'text-ink/70' : 'text-ink/65' }}">
                                    {{ $day['date']->format('j') }}
                                </span>
                                @if($day['isToday'])
                                    <span class="rounded-full bg-terra text-cream px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide">Today</span>
                                @endif
                            </div>

                            {{-- scrolls rather than bursting the square when a day is busy --}}
                            <div class="space-y-1 sm:overflow-y-auto sm:min-h-0 sm:flex-1">
                                @foreach($day['departures'] as $dep)
                                    @php($full = $dep->spotsLeft() === 0)
                                    {{-- A multi-day trip appears on every day it runs, so each chip has to say
                                         which day it is. Without that, three days of one traverse read as three
                                         separate departures — the reason this used to only mark the start. --}}
                                    @php($span = $dep->spanDays())
                                    @php($dayNo = $span > 1 ? $dep->dayNumberOn($day['date']) : 0)
                                    <a href="{{ route('host.roster', $dep) }}" wire:navigate dusk="cal-departure-{{ $dep->id }}-{{ $day['date']->toDateString() }}"
                                       title="{{ $dep->trip->title }}{{ $dayNo ? ' — day '.$dayNo.' of '.$span : '' }} — {{ $dep->heldCount() }} of {{ $dep->capacity }} held"
                                       class="block rounded-lg px-2.5 py-2 transition-colors {{ $full ? 'glass-terra' : 'glass-moss' }}
                                              {{ $dayNo > 1 ? 'rounded-l-none border-l-2 border-dashed border-pine/30' : '' }}">
                                        <span class="block text-[11px] font-bold leading-tight truncate {{ $full ? 'text-ink' : 'text-pine' }}">
                                            {{ $dep->trip->title }}
                                        </span>
                                        <span class="block text-[10px] font-semibold tabular-nums {{ $full ? 'text-ink/70' : 'text-pine' }}">
                                            @if($dayNo)
                                                Day {{ $dayNo }}/{{ $span }} ·
                                            @endif
                                            {{ $dep->heldCount() }}/{{ $dep->capacity }}{{ $full ? ' · full' : '' }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            {{-- PHONE: the selected day's trips, in full. --}}
            <div class="sm:hidden border-t border-ink/8">
                @forelse($selectedDepartures as $dep)
                    @php($full = $dep->spotsLeft() === 0)
                    <a href="{{ route('host.roster', $dep) }}" wire:navigate dusk="day-trip-{{ $dep->id }}"
                       class="flex items-center gap-3 px-4 py-3 border-b border-ink/8 last:border-b-0">
                        <span class="w-1 self-stretch rounded-full {{ $full ? 'bg-terra' : 'bg-moss' }}"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold truncate">{{ $dep->trip->title }}</span>
                            <span class="block text-sm text-ink/65 tabular-nums">
                                {{-- The whole span, so a trip listed under the 25th does not look like it
                                     starts there when it actually began on the 24th. --}}
                                {{ $dep->starts_at->format('D j M') }}@if($dep->isMultiDay()) – {{ $dep->ends_at->format('D j M') }}@endif
                                · {{ $dep->heldCount() }}/{{ $dep->capacity }}{{ $full ? ' · full' : '' }}
                            </span>
                        </span>
                        <svg viewBox="0 0 24 24" class="w-4 h-4 text-ink/40 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6l6 6-6 6"/></svg>
                    </a>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-ink/65">
                        {{ $monthDepartures->isEmpty() ? 'Nothing scheduled in '.$cursor->format('F').'.' : 'Tap a marked day to see its trips.' }}
                    </p>
                @endforelse
            </div>

            @if($monthDepartures->isEmpty())
                <p class="hidden sm:block px-5 py-6 text-center text-sm text-ink/65 border-t border-ink/8">
                    Nothing scheduled in {{ $cursor->format('F Y') }}.
                </p>
            @endif
        </div>

        {{-- List: only when chosen. The phone keeps the calendar now. --}}
        <div class="{{ $isList ? '' : 'hidden' }} divide-y divide-ink/8">
            @forelse($monthDepartures as $dep)
                @php($full = $dep->spotsLeft() === 0)
                <a href="{{ route('host.roster', $dep) }}" wire:navigate dusk="list-departure-{{ $dep->id }}"
                   class="flex flex-wrap items-center gap-4 px-5 py-4 hover:bg-ink/[.02] transition-colors">
                    <div class="text-center shrink-0 w-12">
                        <div class="marker text-[10px] font-semibold text-ink/65">{{ $dep->starts_at->format('M') }}</div>
                        <div class="font-serif font-bold text-2xl leading-none tabular-nums">{{ $dep->starts_at->format('j') }}</div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="font-semibold truncate">{{ $dep->trip->title }}</div>
                        <div class="text-sm text-ink/65 truncate">
                            {{ $dep->starts_at->format('l') }}
                            @if($dep->trip->place)<span class="text-ink/65">·</span> {{ $dep->trip->place->name }}@endif
                            <span class="text-ink/65">·</span> {{ $dep->starts_at->diffForHumans() }}
                        </div>
                    </div>

                    {{-- The list has room the grid cell does not, so it says more. --}}
                    <div class="hidden sm:flex items-center gap-3 w-48 shrink-0">
                        <div class="flex-1 h-1.5 rounded-full bg-ink/10 overflow-hidden">
                            <div class="h-full {{ $full ? 'bg-terra' : 'bg-moss' }}" style="width: {{ $dep->capacity ? min(100, (int) round($dep->heldCount() / $dep->capacity * 100)) : 0 }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums shrink-0">{{ $dep->heldCount() }}/{{ $dep->capacity }}</span>
                    </div>
                    <span class="sm:hidden text-sm text-ink/65 tabular-nums">{{ $dep->heldCount() }}/{{ $dep->capacity }} held</span>

                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $full ? 'bg-terra/15 text-ink' : 'bg-moss/15 text-pine' }}">
                        {{ $full ? 'Full' : $dep->spotsLeft().' left' }}
                    </span>

                    <svg viewBox="0 0 24 24" class="w-4 h-4 text-ink/40 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6l6 6-6 6"/></svg>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-ink/65">Nothing scheduled in {{ $cursor->format('F Y') }}.</p>
            @endforelse
        </div>
    </section>

    {{-- ═══ NEXT UP ═══ --}}
    @if($nextUp->isNotEmpty())
        <h2 class="font-serif font-semibold text-xl mt-8 mb-3">Next up</h2>
        <div class="grid sm:grid-cols-3 gap-3">
            @foreach($nextUp as $dep)
                <a href="{{ route('host.roster', $dep) }}" wire:navigate
                   class="glass-card glass-interactive p-5">
                    <div class="marker text-[10px] font-semibold text-ink/65">{{ $dep->starts_at->diffForHumans() }}</div>
                    <div class="font-serif font-semibold text-lg leading-tight mt-1">{{ $dep->trip->title }}</div>
                    <div class="text-sm text-ink/65 mt-1">{{ $dep->starts_at->format('D j M Y') }}</div>
                    <div class="flex items-center gap-2 mt-3">
                        <div class="flex-1 h-1.5 rounded-full bg-ink/10 overflow-hidden">
                            <div class="h-full bg-moss" style="width: {{ $dep->capacity ? min(100, (int) round($dep->heldCount() / $dep->capacity * 100)) : 0 }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-ink/70 shrink-0 tabular-nums">{{ $dep->heldCount() }}/{{ $dep->capacity }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
