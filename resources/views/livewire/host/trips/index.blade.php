<div>
    {{-- Spoken, never shown. The chips change and the menu goes back to its prompt without a word otherwise, and
         the region has to be on the page already, empty, for a screen reader to speak into it. --}}
    <p class="sr-only" role="status" aria-live="polite" dusk="visibility-status">{{ $announcement }}</p>

    <div class="flex flex-wrap items-end justify-between gap-4 mb-7">
        <div>
            <h1 class="tracking-tight font-semibold text-3xl mb-1">My trips</h1>
            <p class="text-ink/65">Each date is its own schedule, so you can price and run them differently.</p>
        </div>
        {{-- On a phone the tab bar's raised "New trip" disc is this same action. --}}
        <a href="{{ route('host.trips.create') }}" wire:navigate
           class="max-md:hidden inline-flex items-center gap-2 rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors">
            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            New trip
        </a>
    </div>

    <div class="flex flex-wrap gap-2 mb-6" role="group" aria-label="Filter trips">
        @foreach(['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                    dusk="filter-{{ $key }}"
                    class="rounded-full px-5 min-h-[44px] text-sm font-semibold border transition-colors
                           {{ $filter === $key ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">
                {{ $label }} <span class="opacity-70">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    @forelse($groups as $groupKey => $group)
        @php($first = $group->first())
        <section wire:key="offering-{{ $groupKey }}" class="glass-card overflow-hidden mb-4">
            {{-- The photo carries the identity of the offering, so it gets a real size rather
                 than a 64px chip. A 16/10 tile at the header height reads as the trip; a square
                 thumbnail that small read as an icon. --}}
            <header class="flex flex-wrap items-center gap-x-4 gap-y-3 sm:gap-x-5 px-5 py-4 border-b border-ink/8 bg-cream/50">
                <img src="{{ $first->cover_url }}" alt="" loading="lazy"
                     class="w-24 h-[3.75rem] sm:w-40 sm:h-24 rounded-2xl object-cover shrink-0 shadow-sm">
                <div class="flex-1 min-w-[7rem] sm:min-w-[12rem]">
                    <h2 class="tracking-tight font-semibold text-xl sm:text-2xl leading-tight truncate">{{ $first->title }}</h2>
                    <p class="flex items-center gap-1.5 text-sm text-ink/65 mt-1 min-w-0">
                        <x-icon.pin class="w-4 h-4 shrink-0 text-moss" />
                        <span class="truncate">{{ $first->destinations->map(fn ($d) => $d->place?->name)->filter()->join(' · ') ?: $first->place?->name }}</span>
                    </p>
                    {{-- Reviews only. The schedule count is already the chip on the right of this
                         same header, and printing it twice made the card look like it was padding. --}}
                    @if($first->reviews_count)
                        <p class="text-sm text-ink/65 mt-1">
                            {{ $first->reviews_count }} {{ \Illuminate\Support\Str::plural('review', $first->reviews_count) }}
                        </p>
                    @endif
                </div>
                {{-- The count and the menu travel together: beside the title where there is room, on a line of
                     their own under it where there is not. On a phone that is two lines, the count and then the
                     menu with its button. --}}
                <div class="flex w-full flex-wrap items-center gap-x-3 gap-y-2 sm:ml-auto sm:w-auto">
                    <span class="shrink-0 rounded-full bg-ink/[.06] px-3 py-1.5 text-xs font-bold">
                        {{ $group->count() }} {{ \Illuminate\Support\Str::plural('schedule', $group->count()) }}
                    </span>

                    {{--
                        A menu of choices that the button beside it applies, and the select never does.

                        On Windows and Linux every arrow key or typed letter on a closed select fires `change`, so
                        a select that saved on change would re-list a trip on a stray keystroke (a private link-only
                        trip turning into Everyone) and a keyboard user could never arrive at "Link only". Here the
                        select only holds the pick, in `choice`, and Apply is off until there is one. Apply sends
                        it, clears it, so the menu is back on its prompt and never claims to be the offering's state
                        (the chips say that), and puts focus back on the menu, because a button that has just turned
                        itself off cannot keep it.

                        On a phone the select takes the whole row and Apply wraps under it. The closed select is the
                        only place the host can read which window they are about to apply, and "Followers first for
                        1 week" needs about 168px of text room, which Apply beside it leaves only to a 390px screen.
                    --}}
                    <div x-data="{ choice: '' }" class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                        <div class="relative min-w-0 w-full sm:w-auto sm:flex-none">
                            <select x-model="choice" x-ref="menu"
                                    dusk="visibility-{{ $groupKey }}"
                                    aria-label="Who can see it: {{ $first->title }}"
                                    class="appearance-none w-full rounded-full border border-ink/15 bg-surface pl-4 pr-10 min-h-[44px] text-sm font-semibold
                                           focus:border-moss focus:ring-2 focus:ring-moss/25 transition-colors cursor-pointer">
                                <option value="" selected disabled>Who can see it</option>
                                <option value="{{ \App\Support\Visibility::PUBLIC }}">{{ \App\Support\Visibility::CATALOG[\App\Support\Visibility::PUBLIC] }}</option>
                                @foreach(\App\Support\Visibility::WINDOWS as $window => [, $windowLabel])
                                    <option value="{{ \App\Support\Visibility::FOLLOWERS }}:{{ $window }}">{{ \App\Support\Visibility::CATALOG[\App\Support\Visibility::FOLLOWERS] }} for {{ $windowLabel }}</option>
                                @endforeach
                                <option value="{{ \App\Support\Visibility::LINK }}">{{ \App\Support\Visibility::CATALOG[\App\Support\Visibility::LINK] }}</option>
                            </select>
                            <svg viewBox="0 0 24 24" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-ink/65 pointer-events-none"
                                 fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </div>
                        <button type="button" dusk="visibility-apply-{{ $groupKey }}"
                                aria-label="Apply to {{ $first->title }}"
                                :disabled="choice === ''"
                                @click="$wire.setVisibility('{{ $groupKey }}', choice); choice = ''; $refs.menu.focus()"
                                class="shrink-0 rounded-full bg-pine text-cream px-4 min-h-[44px] text-sm font-semibold
                                       hover:bg-pine/90 disabled:opacity-40 disabled:hover:bg-pine disabled:cursor-not-allowed transition-colors">
                            Apply
                        </button>
                    </div>
                </div>
            </header>

            <ul class="divide-y divide-ink/8">
                @foreach($group as $trip)
                    @php($dep = $trip->departure)
                    @php($held = (int) ($dep->seats_held ?? 0))
                    @php($capacity = (int) ($dep->capacity ?? 0))
                    {{--
                        A labelled grid, not a row with a shoved-right button cluster.

                        The old row was a date, a chip, then `ml-auto` — on a wide screen that put
                        about 700px of nothing in the middle of every schedule. The same space now
                        answers the two questions a host opens this page with: what does it cost,
                        and how full is it.
                    --}}
                    <li class="grid gap-x-5 gap-y-3 px-5 py-4
                               sm:grid-cols-[minmax(11rem,1.3fr)_minmax(7rem,.8fr)_minmax(9rem,1fr)_auto] sm:items-center">
                        <div class="min-w-0">
                            <p class="text-[11px] uppercase tracking-wide text-ink/65 font-semibold mb-0.5">When</p>
                            <p class="font-semibold">
                                {{ $dep?->starts_at?->format('D j M Y') ?? 'No date' }}
                                @if($dep?->ends_at) <span class="font-normal text-ink/65">→ {{ $dep->ends_at->format('j M') }}</span>@endif
                            </p>
                            @if($dep)
                                <p class="text-sm mt-0.5 {{ $dep->starts_at->isPast() ? 'text-ink/65' : 'text-pine font-semibold' }}">
                                    {{ $dep->starts_at->isPast() ? 'Ran' : $dep->starts_at->diffForHumans() }}
                                </p>
                            @endif
                            <x-visibility-chip :trip="$trip" dusk="chip-{{ $trip->id }}" class="mt-1.5" />
                        </div>

                        <div class="min-w-0">
                            <p class="text-[11px] uppercase tracking-wide text-ink/65 font-semibold mb-0.5">Price</p>
                            <p class="font-semibold text-lg tabular-nums">
                                {{ $trip->host->hostProfile?->currency ?? 'PHP' }} {{ number_format((float) $trip->price) }}
                            </p>
                            <p class="text-sm text-ink/65">per person</p>
                        </div>

                        @if($dep)
                            <div class="min-w-0">
                                <p class="text-[11px] uppercase tracking-wide text-ink/65 font-semibold mb-0.5">Booked</p>
                                <p class="font-semibold tabular-nums">
                                    {{ $held }} <span class="text-ink/65 font-normal">of {{ $capacity }}</span>
                                    @if($capacity > 0 && $held >= $capacity)
                                        <span class="ml-1 rounded-full bg-terra/12 text-terra-deep px-2 py-0.5 text-[11px] font-bold align-middle">Full</span>
                                    @endif
                                </p>
                                {{-- A bar, because "8 of 12" and "2 of 12" read the same at a glance
                                     in a column of numbers but are completely different situations. --}}
                                <div class="h-1.5 rounded-full bg-ink/[.08] mt-1.5 overflow-hidden" role="presentation">
                                    <div class="h-full rounded-full {{ $capacity > 0 && $held >= $capacity ? 'bg-terra' : 'bg-moss' }}"
                                         style="width: {{ $capacity > 0 ? min(100, round($held / $capacity * 100)) : 0 }}%"></div>
                                </div>
                            </div>
                        @else
                            <div></div>
                        @endif

                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <a href="{{ route('trips.show', $trip) }}" wire:navigate
                               class="rounded-full border border-ink/15 px-4 min-h-[44px] inline-flex items-center text-sm font-semibold hover:border-ink/30 transition-colors">View</a>
                            <a href="{{ route('host.trips.edit', $trip) }}" wire:navigate dusk="edit-{{ $trip->id }}"
                               class="rounded-full border border-ink/15 px-4 min-h-[44px] inline-flex items-center gap-1.5 text-sm font-semibold hover:border-moss transition-colors">
                                <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-moss" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/></svg>
                                Edit
                            </a>
                            @if($dep)
                                <a href="{{ route('host.roster', $dep) }}" wire:navigate
                                   class="rounded-full bg-pine text-cream px-4 min-h-[44px] inline-flex items-center text-sm font-semibold hover:bg-pine/90 transition-colors">Roster</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="px-5 py-3 border-t border-ink/8 bg-cream/30">
                <a href="{{ route('host.trips.duplicate', $first) }}" wire:navigate
                   class="text-sm font-semibold text-moss hover:underline">＋ Add another schedule</a>
            </div>
        </section>
    @empty
        <div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 py-12 text-center">
            <p class="font-semibold mb-1">Nothing {{ $filter === 'all' ? 'here' : $filter }} yet</p>
            <p class="text-ink/65 text-sm mb-5">Publish a trip and every date you add becomes its own schedule.</p>
            <a href="{{ route('host.trips.create') }}" wire:navigate
               class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors">New trip</a>
        </div>
    @endforelse
</div>
