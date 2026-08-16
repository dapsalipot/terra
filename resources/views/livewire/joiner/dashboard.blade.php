@php($statusLabel = [
    'pending' => ['Awaiting payment', 'amber'],
    'awaiting_payment' => ['Awaiting payment', 'amber'],
    'payment_submitted' => ['Waiting on your host', 'amber'],
    'confirmed' => ['Confirmed', 'moss'],
])

@php($attentionIcon = [
    'pay' => '<svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
    'details' => '<svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'balance' => '<svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    'review' => '<svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
])

@php($attentionColor = [
    'pay' => 'text-terra',
    'details' => 'text-amber',
    'balance' => 'text-amber',
    'review' => 'text-ink/70',
])

@php($attentionBg = [
    'pay' => 'bg-terra/8 border-terra/15',
    'details' => 'bg-amber/[.06] border-amber/12',
    'balance' => 'bg-amber/[.06] border-amber/12',
    'review' => 'bg-ink/[.02] border-ink/8',
])

@php($participantInitials = function ($r) {
    // Only travellers who actually have a name. An unnamed one produced an empty grey disc,
    // which reads as a broken avatar rather than as "details still pending" — and the
    // "N of M travellers still need details" card already tells that story properly.
    // The ?? '' also keeps mb_substr from being handed null.
    return $r->participants
        ->filter(fn ($p) => filled($p->first_name) || filled($p->last_name))
        ->take(3)
        ->map(fn ($p) => strtoupper(mb_substr($p->first_name ?? '', 0, 1).mb_substr($p->last_name ?? '', 0, 1)))
        ->values()
        ->all();
})

@php($namedTravellers = fn ($r) => $r->participants->filter(fn ($p) => filled($p->first_name) || filled($p->last_name))->count())

<div class="w-full px-5 sm:px-8 2xl:px-12 py-8">
    {{-- ═══ HEADER ═══ --}}
    {{-- The view switcher used to live up here, a full column away from the thing it switched.
         It now sits on the calendar it controls, which leaves the header as a title and nothing
         else — and stops the controls squeezing the heading to two lines on a phone. --}}
    <div class="mb-6">
        <div>
            <h1 class="font-serif font-semibold text-3xl mb-1">Your trips</h1>
            <p class="text-ink/65">Everything you have booked, and anything still waiting on you.</p>
        </div>
    </div>

    {{--
        60/40 from xl.

        The right column used to be a 22rem rail of nudges; it now carries the trip you are
        actually going on, so it earns real width. But not half: the calendar is a seven-column
        grid whose cells each hold a photo and a label, and it degrades faster than a single
        stack of cards does. Not sticky either — this column is taller than a viewport, and
        pinning it would strand the bottom.
    --}}
    <div class="xl:grid xl:grid-cols-[3fr_2fr] xl:gap-8 xl:items-start">

        {{--
            The calendar comes first in the source, and therefore first on a phone.

            The aside carries an explicit `xl:col-start-2 xl:row-start-1`, so its position in the
            grid is stated rather than inherited from source order — which means the two can be
            swapped in the DOM without a single `order-*` utility and without moving anything on
            desktop. A joiner opening this on a phone came here to see when they are away; four
            panels of admin above the calendar made them scroll past the answer to reach it.
        --}}

        <div class="xl:col-start-1 xl:row-start-1 min-w-0">
        {{-- Two tabs, always visible. Cards and List only: in Calendar the month is the scope, and
             the grid deliberately shows every booking, so a filter tab there would contradict it. --}}
        @if($view !== 'calendar')
            <div class="flex items-center gap-2 mb-4" role="group" aria-label="Filter trips">
                @foreach(['upcoming' => 'Upcoming', 'past' => 'Past'] as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')" dusk="tab-{{ $key }}"
                            aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                            class="rounded-full px-4 min-h-[44px] text-sm font-semibold transition-colors
                                   {{ $filter === $key ? 'bg-pine text-cream' : 'border border-ink/15 hover:border-ink/35' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ═══ BOOKINGS ═══ --}}
        <section class="mb-8">
            {{-- The switcher sits directly on top of what it switches. A control a full column
                 away from its effect makes you hunt for the cause of a change you just made —
                 and on a phone it was a screen-height apart from the calendar entirely. --}}
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="min-w-0">
                    <h2 class="marker text-[11px] font-semibold text-ink/70">
                        {{ $view === 'calendar' ? 'Your calendar' : ($filter === 'past' ? 'Past' : 'Upcoming') }}
                    </h2>
                    <span class="text-xs text-ink/65 font-medium">{{ $filtered->count() }} {{ \Illuminate\Support\Str::plural('booking', $filtered->count()) }}</span>
                </div>
                {{-- Three views, shown as one segmented control so the current one is always
                     visible rather than inferred from an icon that toggles. --}}
                <div class="inline-flex items-center rounded-xl border border-ink/15 p-1 bg-surface shrink-0" role="group" aria-label="View">
                @foreach([
                    'calendar' => ['Calendar', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
                    'grid' => ['Cards', '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'],
                    'list' => ['List', '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'],
                ] as $key => $meta)
                    <button type="button" wire:click="setView('{{ $key }}')" dusk="view-{{ $key }}"
                            aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                            aria-label="{{ $meta[0] }} view"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 min-w-[44px] min-h-[44px] text-sm font-semibold transition-colors
                                   {{ $view === $key ? 'bg-pine text-cream shadow-sm' : 'text-ink/70 hover:text-ink hover:bg-ink/5' }}">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $meta[1] !!}</svg>
                        <span class="hidden sm:inline">{{ $meta[0] }}</span>
                    </button>
                    @endforeach
                </div>
            </div>

            {{--
                Subscribe once, and every confirmed trip lands in whatever calendar they already
                use. A feed rather than a Google integration: no account access to ask for, and
                the same URL works in Apple Calendar and Outlook.
            --}}
            {{-- Calendar first, and outside the emptiness guard: it must render even when the
                 current filter matches nothing, or a joiner with no upcoming trips would be shown
                 an empty state with no way to page back to the month their trip is in. --}}
            @if($view === 'calendar')
                    {{-- ═══ CALENDAR ═══ --}}
                    <div class="rounded-2xl border border-ink/10 overflow-hidden bg-surface" dusk="calendar">
                        {{-- month bar --}}
                        <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3 border-b border-ink/8">
                            <div class="min-w-0">
                                <p class="font-serif font-semibold text-lg leading-tight">{{ $cursor->format('F Y') }}</p>
                                <p class="text-xs text-ink/70">
                                    {{ $monthCount }} {{ \Illuminate\Support\Str::plural('trip', $monthCount) }} this month
                                </p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" wire:click="shiftMonth(-1)" dusk="cal-prev" aria-label="Previous month"
                                        class="grid place-items-center w-11 h-11 rounded-lg text-ink/70 hover:text-ink hover:bg-ink/5 transition-colors">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                                </button>
                                <button type="button" wire:click="today" dusk="cal-today"
                                        class="rounded-lg px-3 h-11 text-sm font-semibold text-ink/70 hover:text-ink hover:bg-ink/5 transition-colors">Today</button>
                                <button type="button" wire:click="shiftMonth(1)" dusk="cal-next" aria-label="Next month"
                                        class="grid place-items-center w-11 h-11 rounded-lg text-ink/70 hover:text-ink hover:bg-ink/5 transition-colors">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- weekday rail --}}
                        <div class="grid grid-cols-7 gap-1 sm:gap-2 px-2 sm:px-3 pt-3">
                            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                                @php($isWeekendCol = in_array($dow, ['Sat', 'Sun']))
                                <div class="text-center text-[11px] font-semibold uppercase tracking-wide pb-1 {{ $isWeekendCol ? 'text-moss' : 'text-ink/70' }}">
                                    <span class="sm:hidden">{{ substr($dow, 0, 1) }}</span>
                                    <span class="hidden sm:inline">{{ $dow }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- grid --}}
                        <div class="p-2 sm:p-3 pt-0 space-y-1 sm:space-y-2">
                            @foreach($weeks as $week)
                                <div class="grid grid-cols-7 gap-1 sm:gap-2">
                                    @foreach($week as $day)
                                        @php($booking = $day['bookings']->first())
                                        @php($iso = $day['date']->toDateString())
                                        @if($booking)
                                            @php($trip = $booking->departure->trip)
                                            @php($isPast = $day['date']->isPast() && ! $day['isToday'])
                                            {{-- The tile repeats across every day of the trip now, so it has to say which
                                                 day it is. Three identical photos in a row otherwise read as three trips. --}}
                                            @php($span = $booking->departure->spanDays())
                                            @php($dayNo = $span > 1 ? $booking->departure->dayNumberOn($day['date']) : 0)
                                            {{-- Built here, not inline: Blade will not compile `@endif` butted straight
                                                 against the next `@if`, and the second directive silently becomes literal
                                                 text whose stray `@endif` then closes the wrong block. --}}
                                            @php($dayPart = $dayNo ? ' · day '.$dayNo.' of '.$span : '')
                                            @php($tileTitle = $trip->title.$dayPart.($day['holiday'] ? ' · '.$day['holiday'] : ''))
                                            @php($tileLabel = $day['date']->format('j F').' — '.$trip->title.$dayPart.', open this booking')
                                            {{-- A booked day goes straight to that booking. It is the thing the joiner wants:
                                                 pay, traveller details and the host's payment methods all live there. --}}
                                            <a href="{{ route('reservations.show', ['token' => $booking->public_token]) }}" wire:navigate
                                               dusk="cal-day-{{ $iso }}"
                                               title="{{ $tileTitle }}"
                                               aria-label="{{ $tileLabel }}"
                                               {{-- focus-visible in moss, not the browser's blue: the default ring is the one
                                                    colour on this page that belongs to no part of the palette. --}}
                                               class="group relative aspect-square rounded-lg sm:rounded-2xl overflow-hidden block
                                                      ring-offset-2 ring-offset-surface transition-all duration-300
                                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-moss
                                                      {{ $day['isToday'] ? 'ring-2 ring-pine' : 'hover:ring-2 hover:ring-ink/25' }}">
                                                <img src="{{ $trip->cover_url }}" alt=""
                                                     class="absolute inset-0 w-full h-full object-cover transition-transform duration-[1200ms] ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.12] {{ $isPast ? 'grayscale' : '' }}">
                                                <div class="absolute inset-0 bg-gradient-to-t from-ink/70 via-ink/25 to-ink/10" aria-hidden="true"></div>

                                                <span class="absolute top-1 left-1 sm:top-2 sm:left-2 grid place-items-center min-w-[1.35rem] sm:min-w-[1.6rem] h-5 sm:h-6 px-1 rounded-full bg-surface text-ink text-[11px] sm:text-xs font-bold shadow-sm">
                                                    {{ $day['date']->day }}
                                                </span>

                                                @if($day['bookings']->count() > 1)
                                                    <span class="absolute top-1 right-1 sm:top-2 sm:right-2 grid place-items-center h-5 sm:h-6 px-1.5 rounded-full bg-pine text-cream text-[10px] font-bold">
                                                        +{{ $day['bookings']->count() - 1 }}
                                                    </span>
                                                @elseif($dayNo)
                                                    {{-- Visible at every size: the title panel below is sm-only, so on a phone
                                                         this chip is the only thing distinguishing day 2 from day 1. --}}
                                                    <span class="absolute top-1 right-1 sm:top-2 sm:right-2 grid place-items-center h-5 sm:h-6 px-1.5 rounded-full bg-pine text-cream text-[10px] font-bold tabular-nums">
                                                        {{ $dayNo }}/{{ $span }}
                                                    </span>
                                                @endif

                                                {{-- A solid panel, not a gradient the text floats in: measured, a bright pixel
                                                     underneath put cream text at about 2.5:1. On ink/90 it is 12:1 regardless. --}}
                                                <span class="absolute inset-x-0 bottom-0 hidden sm:block">
                                                    <span class="block h-3 bg-gradient-to-t from-ink/90 to-transparent"></span>
                                                    <span class="block px-1.5 pb-1.5 bg-ink/90">
                                                        <span class="block text-cream text-[10.5px] font-semibold leading-tight truncate">{{ $trip->title }}</span>
                                                    </span>
                                                </span>
                                            </a>
                                        @else
                                            {{--
                                                An empty square is a question — "could I go somewhere that day?" — so it
                                                answers it rather than sitting inert.

                                                Two independent facts share this cell and must not be collapsed into one
                                                colour: the tint says when the joiner is free (weekend moss, holiday
                                                amber) and the moss ring says something is actually bookable. A day can
                                                easily be one without the other, so availability is a foreground
                                                treatment layered over whichever tint the day already carries.
                                            --}}
                                            @php($hasTrips = $day['available'] > 0)
                                            {{--
                                                A joinable day has to differ from a weekend by more than a shade.

                                                The first attempt tinted it moss/25 against the weekend's moss/10 and the
                                                two were indistinguishable on screen — the same hue two steps apart reads
                                                as one colour. It is a filled moss card now, dark enough that the
                                                difference is a difference in kind, with the day type kept in the border:
                                                amber edge for a holiday, moss for everything else.
                                            --}}
                                            @php($tint = $selectedDate === $iso
                                                ? 'bg-moss/25'
                                                : ($hasTrips
                                                    {{-- Lighter than it was: the covers now carry the "something is here"
                                                         signal, so the fill only has to sit them apart from a plain
                                                         square rather than do the shouting on its own. --}}
                                                    ? 'bg-gradient-to-br from-moss/[.24] via-moss/[.16] to-moss/[.08]'
                                                    : ($day['holiday']
                                                        ? 'bg-amber/[.13]'
                                                        : ($day['isWeekend']
                                                            ? 'bg-moss/[.10]'
                                                            : ($day['inMonth'] ? 'bg-ink/[.015]' : 'bg-transparent')))))
                                            @php($edge = $selectedDate === $iso
                                                ? 'border-moss'
                                                : ($hasTrips
                                                    ? ($day['holiday'] ? 'border-amber/70 shadow-sm' : 'border-moss/70 shadow-sm')
                                                    : ($day['holiday'] ? 'border-amber/45' : ($day['isWeekend'] ? 'border-moss/55' : 'border-ink/[.07]'))))

                                            {{--
                                                Built in PHP, not spliced together with directives inside the attribute.

                                                It used to read `…@endif@if($hasTrips)…`, and Blade will not compile a
                                                directive that butts straight up against the previous one — the `\B` in
                                                its pattern fails when `@` follows a word character. So `@if($hasTrips)`
                                                was being emitted as literal text and screen readers were reading the
                                                source code out loud. It stayed invisible because the damage was inside
                                                an attribute; adding one nested `@endif` that did compile turned it into
                                                a parse error, which is the only reason it surfaced at all.
                                            --}}
                                            @php($ariaParts = collect([$day['date']->format('j F')])
                                                ->when($day['holiday'], fn ($c) => $c->push($day['holiday']))
                                                ->push($hasTrips
                                                    ? $day['available'].' '.\Illuminate\Support\Str::plural('trip', $day['available'])
                                                        .($day['continuing'] > 0 ? ' available this day' : ' leaving this day')
                                                    : 'no trips leaving this day')
                                                ->when($hasTrips && $day['types'], fn ($c) => $c->push(collect($day['types'])->pluck('label')->implode(', '))))

                                            <button type="button" wire:click="selectDate('{{ $iso }}')"
                                                    dusk="cal-free-{{ $iso }}"
                                                    title="{{ $day['holiday'] ?: $day['date']->format('j F') }}"
                                                    aria-label="{{ $ariaParts->implode(' — ') }}"
                                                    {{-- overflow-hidden is structural, not decorative: without it a child
                                                         wider than the square simply hangs over the neighbouring day, which
                                                         is exactly how the phone layout broke. Clipping makes that a visible
                                                         crop instead of a silent overlap. --}}
                                                    class="group relative aspect-square rounded-lg sm:rounded-2xl border grid place-items-center
                                                           overflow-hidden transition-all duration-200 ease-out
                                                           focus:outline-none focus-visible:ring-2 focus-visible:ring-moss focus-visible:ring-offset-2 focus-visible:ring-offset-surface
                                                           {{ $hasTrips ? 'hover:border-moss hover:shadow-md hover:-translate-y-0.5' : 'hover:border-moss/50 hover:bg-moss/10' }}
                                                           {{ $day['isToday'] ? 'ring-2 ring-pine border-transparent' : '' }}
                                                           {{ $edge }} {{ $tint }}">
                                                @if($hasTrips)
                                                    {{--
                                                        Two compositions, because a phone cell is 44px square.

                                                        The wide one — date, type badges, a stack of covers, a label — was
                                                        measured at 52px of badges alone in a 44px box, so it hung outside
                                                        the cell and over its neighbour. Below `sm` the same facts are
                                                        stacked instead of arranged: date, one cover, and a count when
                                                        there is more than one. The kinds of trip are one tap away in the
                                                        day sheet, which is where a phone was always going to read them.
                                                    --}}
                                                    @php($types = $day['types'])

                                                    {{-- ── phone ── --}}
                                                    <span class="sm:hidden flex flex-col items-center justify-center gap-0.5 w-full px-1">
                                                        <span class="text-[11px] font-bold leading-none {{ $day['inMonth'] ? 'text-ink' : 'text-ink/70' }}">{{ $day['date']->day }}</span>
                                                        @if($day['covers'])
                                                            <img src="{{ $day['covers'][0]['url'] }}" alt="" loading="lazy"
                                                                 class="w-4 h-4 rounded object-cover ring-1 ring-surface shadow-sm">
                                                        @endif
                                                    </span>
                                                    @if($day['available'] > 1)
                                                        <span class="sm:hidden absolute top-0.5 right-0.5 grid place-items-center
                                                                     min-w-[0.85rem] h-3.5 px-0.5 rounded-full ring-1 ring-surface
                                                                     bg-pine text-cream text-[8px] font-bold leading-none"
                                                              aria-hidden="true">{{ $day['available'] }}</span>
                                                    @endif

                                                    {{-- ── sm and up ── --}}
                                                    <span class="hidden sm:block absolute top-1.5 left-2 text-xs font-bold
                                                                 {{ $day['inMonth'] ? 'text-pine' : 'text-ink/70' }}">{{ $day['date']->day }}</span>

                                                    {{--
                                                        What kind of trips these are.

                                                        A single kind gets its name spelled out, because there is room for
                                                        one word and a word cannot be misread. A mixed day falls back to
                                                        icons — three labels will not fit a calendar square — and each
                                                        carries a title, with the full list on the button's aria-label so
                                                        nothing depends on recognising a glyph.
                                                    --}}
                                                    <span class="hidden sm:flex absolute top-1.5 right-1.5 items-center gap-1">
                                                        @if(count($types) === 1)
                                                            <span class="inline-flex items-center gap-1 rounded-lg bg-pine text-cream
                                                                         px-1.5 h-5 ring-2 ring-surface"
                                                                  title="{{ $types[0]['label'] }} trip">
                                                                <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $types[0]['icon'] }}"/></svg>
                                                                <span class="text-[9px] font-bold leading-none pr-0.5">{{ $types[0]['label'] }}</span>
                                                            </span>
                                                        @else
                                                            @foreach(array_slice($types, 0, 3) as $type)
                                                                <span class="grid place-items-center w-5 h-5 rounded-lg
                                                                             bg-pine text-cream ring-2 ring-surface"
                                                                      title="{{ $type['label'] }} trip">
                                                                    <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $type['icon'] }}"/></svg>
                                                                </span>
                                                            @endforeach
                                                        @endif
                                                    </span>

                                                    <span class="hidden sm:flex items-center -space-x-2" aria-hidden="true">
                                                        @foreach($day['covers'] as $cover)
                                                            <img src="{{ $cover['url'] }}" alt="" loading="lazy"
                                                                 class="w-8 h-8 rounded-lg object-cover ring-2 ring-surface shadow-sm
                                                                        transition-transform duration-200 group-hover:scale-105">
                                                        @endforeach
                                                        @if($day['available'] > count($day['covers']))
                                                            <span class="grid place-items-center w-8 h-8 rounded-lg
                                                                         ring-2 ring-surface bg-pine text-cream text-[10px] font-bold">
                                                                +{{ $day['available'] - count($day['covers']) }}
                                                            </span>
                                                        @endif
                                                    </span>

                                                    {{-- pine, not moss: measured, moss text on the dark end of the moss fill
                                                         is 2.99:1 and this is 9px bold, which is small text. pine is 6.44:1. --}}
                                                    <span class="absolute bottom-1 inset-x-1 hidden sm:block text-[9px] leading-tight font-bold text-pine truncate">
                                                        {{ $day['available'] }} {{ \Illuminate\Support\Str::plural('trip', $day['available']) }} to join
                                                    </span>
                                                @else
                                                    <span class="text-[11px] sm:text-xs
                                                                 {{ $day['isWeekend'] || $day['holiday'] ? 'font-bold' : 'font-semibold' }}
                                                                 {{ $day['inMonth'] ? ($day['holiday'] ? 'text-ink' : ($day['isWeekend'] ? 'text-pine' : 'text-ink/70')) : 'text-ink/70' }}">
                                                        {{ $day['date']->day }}
                                                    </span>
                                                @endif

                                                @if($day['holiday'])
                                                    {{-- A joinable day is already using this line for its own label, and the
                                                         holiday name reaches a screen reader through aria-label regardless. --}}
                                                    @unless($hasTrips)
                                                        <span class="absolute bottom-1 inset-x-1 hidden sm:block text-[9px] leading-tight text-ink/70 font-semibold truncate">{{ $day['holiday'] }}</span>
                                                    @endunless
                                                    <span class="sm:hidden absolute bottom-1 w-1 h-1 rounded-full bg-amber" aria-hidden="true"></span>
                                                @endif

                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        {{-- the tapped free day opens as a sheet — see the bottom of this file --}}
                        @if($selectedBookings->isNotEmpty())
                            <div class="sm:hidden border-t border-ink/8 p-3 space-y-2" dusk="cal-day-list">
                                @foreach($selectedBookings as $r)
                                    @php($trip = $r->departure->trip)
                                    <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                                       class="flex items-center gap-3 rounded-xl border border-ink/10 p-3">
                                        <img src="{{ $trip->cover_url }}" alt="" class="w-12 h-12 rounded-lg object-cover shrink-0">
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-semibold text-sm truncate">{{ $trip->title }}</span>
                                            <span class="block text-xs text-ink/65">{{ $r->departure->starts_at->format('D j M') }} · {{ $r->party_size }} {{ \Illuminate\Support\Str::plural('seat', $r->party_size) }}</span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                {{-- Calendar is the default view, so a joiner with nothing booked lands on an empty
                     grid. Without this they get a month of blank squares and no way forward. --}}
                @if($upcoming->isEmpty() && $past->isEmpty())
                    <x-no-trips compact />
                @endif
            @elseif($filtered->isNotEmpty())
                @if($view === 'grid')
                    {{-- Grouped by month: a year of bookings reads as a shape rather than a wall of rows. --}}
                    @foreach($byMonth as $monthLabel => $monthBookings)
                        <div class="mb-8 last:mb-0">
                            <div class="flex items-center gap-3 mb-3">
                                <h3 class="font-serif font-semibold text-lg">{{ $monthLabel }}</h3>
                                <span class="h-px flex-1 bg-ink/10"></span>
                                <span class="text-xs text-ink/65 font-semibold">{{ $monthBookings->count() }}</span>
                            </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($monthBookings as $r)
                            <x-booking-card :reservation="$r" variant="card" />
                        @endforeach
                    </div>
                        </div>
                    @endforeach
                @else
                    <div class="space-y-2">
                        @foreach($filtered as $r)
                            <x-booking-card :reservation="$r" variant="row" />
                        @endforeach
                    </div>
                @endif
            @else
                <x-no-trips />
            @endif
        </section>

            <div class="mt-4 mb-8 rounded-2xl border border-ink/10 bg-surface p-4" dusk="calendar-subscribe">
                @if($calendarUrl)
                    <p class="text-xs text-ink/70 mb-2">
                        Paste this into your calendar app as a subscription — your trips appear there
                        and stay up to date on their own. Keep it private: the link is the password.
                    </p>
                    <input readonly value="{{ $calendarUrl }}" dusk="calendar-url"
                           aria-label="Your calendar subscription address"
                           class="w-full rounded-lg border border-ink/15 px-3 min-h-[44px] text-xs font-mono bg-cream"
                           onclick="this.select()">
                    <p class="text-xs text-ink/70 mt-2">
                        Google Calendar: Other calendars → From URL. It refreshes a few times a day,
                        so last-minute changes still reach you by chat.
                    </p>
                @else
                    <button wire:click="revealCalendar" dusk="reveal-calendar"
                            class="flex items-center gap-2.5 min-h-[44px] text-sm font-semibold hover:text-pine transition-colors">
                        <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        Sync to Google, Apple or Outlook
                    </button>
                @endif
            </div>

        {{--
            ═══ WHERE YOU HAVE BEEN ═══

            A flat list of past trips answers "what did I do" and nothing else. The numbers are
            already in the database — every trail carries its ascent, distance and summit — and a
            history that does not add them up is throwing away the only thing it knows that a
            calendar does not.

            Newest month first: a history read forwards buries this year under every year before it.
        --}}
        @if($filter !== 'past' && $pastByMonth->isNotEmpty())
            <section class="mb-8" dusk="past">
                <div class="flex items-end justify-between gap-3 mb-3">
                    <h2 class="marker text-[11px] font-semibold text-ink/70">Where you have been</h2>
                    <span class="text-xs text-ink/65 font-medium">{{ $past->count() }} {{ \Illuminate\Support\Str::plural('trip', $past->count()) }}</span>
                </div>

                {{-- The whole history in one band. Dark, because it is the one celebratory thing on
                     a page otherwise made of admin — and because it separates the summary from the
                     months underneath without another border. --}}
                <div class="rounded-2xl bg-pine text-cream p-5 sm:p-6 mb-4" dusk="past-totals">
                    <p class="text-[11px] uppercase tracking-[0.09em] font-semibold text-cream/80 mb-3">All time</p>
                    <x-trail-stats :stats="$pastTotals" tone="pine" />

                    @if($pastTotals['inclusions']->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5 mt-4 pt-4 border-t border-cream/15">
                            @foreach($pastTotals['inclusions']->take(8) as $inclusion)
                                <span class="rounded-full bg-cream/10 px-2.5 py-1 text-[11px] font-semibold text-cream/85">{{ $inclusion }}</span>
                            @endforeach
                            @if($pastTotals['inclusions']->count() > 8)
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold text-cream/50">
                                    +{{ $pastTotals['inclusions']->count() - 8 }} more
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="space-y-4">
                    @foreach($pastByMonth as $month => $group)
                        <div dusk="past-month" class="rounded-2xl border border-ink/10 bg-surface overflow-hidden">
                            {{-- The month's own header and its own totals. "6,240 m in August" is a
                                 fact about a month; the same number spread over a year says nothing. --}}
                            <div class="px-4 sm:px-5 py-4 border-b border-ink/8 bg-cream/40">
                                <div class="flex items-baseline justify-between gap-3 mb-3">
                                    <p class="font-serif font-semibold text-lg leading-tight">{{ $month }}</p>
                                    <span class="text-xs text-ink/65 font-medium shrink-0">
                                        {{ $group['stats']['trips'] }} {{ \Illuminate\Support\Str::plural('trip', $group['stats']['trips']) }}
                                    </span>
                                </div>
                                <x-trail-stats :stats="$group['stats']" compact />
                            </div>

                            {{-- The trips themselves, hairline-separated rather than each in its own
                                 card: inside a month card, a second card per row is a border inside
                                 a border and the rows stop reading as one group. --}}
                            <ul class="divide-y divide-ink/[.06]">
                                @foreach($group['rows'] as $r)
                                    @php($pTrip = $r->departure->trip)
                                    @php($pHike = $pTrip->hikeDetails->first())
                                    <li>
                                        <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                                           class="flex items-center gap-3 px-4 sm:px-5 py-3 hover:bg-ink/[.02] transition-colors">
                                            <img src="{{ $pTrip->cover_url }}" alt="" loading="lazy"
                                                 class="w-12 h-12 rounded-xl object-cover shrink-0">
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-semibold text-sm truncate">{{ $pTrip->title }}</span>
                                                <span class="block text-xs text-ink/70 truncate">
                                                    {{ $r->departure->starts_at->format('D, j M') }}
                                                    @if($r->departure->isMultiDay())
                                                        · {{ $r->departure->spanDays() }} days
                                                    @endif
                                                    · {{ $pTrip->host->name }}
                                                </span>
                                            </span>

                                            {{-- The trail's own figures, on the row they belong to.
                                                 Hidden below sm: at 375px this is the third column
                                                 in a row that already has a photo and two lines of
                                                 text, and it wrapped the title every time. --}}
                                            @if($pHike && (filled($pHike->masl) || filled($pHike->distance_km)))
                                                <span class="hidden sm:flex items-center gap-3 shrink-0 text-xs tabular-nums text-ink/70">
                                                    @if(filled($pHike->masl))
                                                        <span class="inline-flex items-center gap-1">
                                                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-moss" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 20 6-12 4 7 3-4 5 9Z"/></svg>
                                                            {{ number_format($pHike->masl) }} m
                                                        </span>
                                                    @endif
                                                    @if(filled($pHike->distance_km))
                                                        <span class="inline-flex items-center gap-1">
                                                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-moss" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19h16"/><path d="M4 19a8 8 0 0 1 16 0"/></svg>
                                                            {{ rtrim(rtrim(number_format($pHike->distance_km, 1), '0'), '.') }} km
                                                        </span>
                                                    @endif
                                                </span>
                                            @endif

                                            <svg viewBox="0 0 24 24" class="w-4 h-4 text-ink/30 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 6l6 6-6 6"/></svg>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        </div>

        <aside class="xl:col-start-2 xl:row-start-1 space-y-5 mb-8 xl:mb-0 min-w-0">

        {{--
            ═══ THE RIGHT-HAND COLUMN, AS TABS ═══

            These were four stacked sections, which on a 22rem sidebar meant the thing you needed
            was usually below the fold behind two things you did not. They are stages of one
            pipeline — waiting on the host, waiting on you, ready to go, done — so only one is ever
            the answer to "what now?". Tabs say that; a stack says they all matter equally.

            Only panels with something in them are offered. An empty tab is a promise of content
            that is not there, and a joiner with one upcoming trip should see one tab, not four
            with three dead ends.
        --}}
        @if($panels->isNotEmpty())
            <div role="tablist" aria-label="Your bookings" dusk="panel-tabs"
                 class="flex gap-1 p-1 rounded-2xl bg-ink/[.04] border border-ink/8 overflow-x-auto"
                 x-data
                 {{-- Arrow keys move between tabs, which is what a tablist is expected to do and
                      what a row of buttons does not do on its own. --}}
                 x-on:keydown.right.prevent="$el.querySelector('[aria-selected=true]')?.nextElementSibling?.focus()"
                 x-on:keydown.left.prevent="$el.querySelector('[aria-selected=true]')?.previousElementSibling?.focus()">
                @foreach($panels as $key => $meta)
                    <button type="button" wire:click="setPanel('{{ $key }}')"
                            role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                            aria-selected="{{ $activePanel === $key ? 'true' : 'false' }}"
                            tabindex="{{ $activePanel === $key ? '0' : '-1' }}"
                            dusk="tab-{{ $key }}"
                            class="flex-1 whitespace-nowrap rounded-xl px-3 min-h-[44px] text-[13px] font-semibold
                                   inline-flex items-center justify-center gap-1.5 transition-colors
                                   {{ $activePanel === $key
                                        ? 'bg-surface text-ink shadow-[0_1px_3px_rgba(31,28,21,.12)]'
                                        : 'text-ink/70 hover:text-ink' }}">
                        {{ $meta['label'] }}
                        <span class="rounded-full px-1.5 py-0.5 text-[11px] font-bold tabular-nums
                                     {{ ($meta['urgent'] ?? false) ? 'bg-terra text-cream' : 'bg-ink/10 text-ink' }}">{{ $meta['count'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ═══ PANEL · NEXT UP ═══ --}}
        @if($activePanel === 'next')
        <div role="tabpanel" id="panel-next" aria-labelledby="tab-next" tabindex="0">
        {{-- ═══ NEXT UP ═══ --}}
        @if($nextUp)
            @php($nextTrip = $nextUp->departure->trip)
            @php($nextDep = $nextUp->departure)
            @php($packing = \App\Support\PackingItems::resolve($nextTrip->packing_items))
            @php($nextMoney = $nextUp->money())
            @php($nextStatus = $statusLabel[$nextUp->status] ?? null)

            <section dusk="next-up">
                <h2 class="marker text-[11px] font-semibold text-ink/70 mb-3">Next up</h2>

                <div class="rounded-3xl border border-ink/8 bg-surface overflow-hidden shadow-[0_12px_34px_-22px_rgba(31,28,21,.4)]">
                    <a href="{{ route('reservations.show', ['token' => $nextUp->public_token]) }}" wire:navigate
                       class="group block relative aspect-[16/9] overflow-hidden">
                        <img src="{{ $nextTrip->cover_url }}" alt="" loading="lazy"
                             class="absolute inset-0 w-full h-full object-cover transition-transform duration-[900ms] ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-105">
                        <span class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/25 to-ink/10" aria-hidden="true"></span>

                        <span class="absolute inset-x-0 top-0 p-4 flex items-start justify-between gap-2">
                            {{-- The countdown is the one number that matters on this card. --}}
                            <span class="rounded-full glass-dark px-3 py-1.5 text-[11px] font-bold text-cream">
                                {{ $nextDep->starts_at->isToday() ? 'Today' : $nextDep->starts_at->diffForHumans(['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]).' away' }}
                            </span>
                            @if($nextStatus)
                                <span class="rounded-full px-3 py-1.5 text-[11px] font-bold
                                             {{ $nextStatus[1] === 'moss' ? 'bg-moss text-cream' : 'bg-amber text-ink' }}">
                                    {{ $nextStatus[0] }}
                                </span>
                            @endif
                        </span>

                        <span class="absolute inset-x-0 bottom-0 p-5">
                            <span class="block font-serif font-semibold text-2xl text-cream leading-tight">{{ $nextTrip->title }}</span>
                            @if($nextTrip->place)
                                <span class="flex items-center gap-1.5 text-sm text-cream/80 mt-1">
                                    <x-icon.pin class="w-3.5 h-3.5 shrink-0" />
                                    {{ $nextTrip->place->name }}<span class="text-cream/70">·</span>{{ $nextTrip->place->country }}
                                </span>
                            @endif
                        </span>
                    </a>

                    <div class="p-5 space-y-4">
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div class="min-w-0">
                                <dt class="text-[11px] uppercase tracking-wide text-ink/65 font-semibold">When</dt>
                                <dd class="font-semibold mt-0.5">
                                    {{ $nextDep->starts_at->format('D, j M') }}
                                    @if($nextDep->isMultiDay())
                                        <span class="text-ink/70">– {{ $nextDep->ends_at->format('j M') }}</span>
                                        <span class="block text-xs font-normal text-ink/70">{{ $nextDep->spanDays() }} days</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[11px] uppercase tracking-wide text-ink/65 font-semibold">Seats</dt>
                                <dd class="font-semibold mt-0.5">
                                    {{ $nextUp->party_size }} {{ \Illuminate\Support\Str::plural('seat', $nextUp->party_size) }}
                                    @if($nextMoney['due'] > 0)
                                        <span class="block text-xs font-normal text-terra">PHP {{ number_format($nextMoney['due']) }} still due</span>
                                    @else
                                        <span class="block text-xs font-normal text-moss">Fully paid</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        @if($nextTrip->meetup_point)
                            <p class="flex items-start gap-2 text-sm text-ink/70">
                                <x-icon.mountain class="w-4 h-4 mt-0.5 shrink-0 text-moss" />
                                <span><span class="font-semibold text-ink">Meet at</span> {{ $nextTrip->meetup_point }}</span>
                            </p>
                        @endif

                        <div class="pt-4 border-t border-ink/8">
                            {{-- py/-my grows the tap area to 44px without moving anything, the same trick used on the
                                 other inline links in this app. --}}
                            <x-host-link :host="$nextTrip->host" size="sm" subtitle="Your host" class="gap-2.5 text-sm font-semibold py-1.5 -my-1.5" />
                        </div>

                        {{-- ═══ WHAT TO BRING ═══ --}}
                        <div class="pt-4 border-t border-ink/8" dusk="what-to-bring">
                            <p class="text-[11px] uppercase tracking-wide text-ink/65 font-semibold mb-2.5">What to bring</p>

                            @if($packing)
                                <ul class="grid sm:grid-cols-2 gap-x-4 gap-y-2">
                                    @foreach($packing as $item)
                                        {{-- title, not a tooltip component: the note is genuinely useful
                                             ("at least 2 litres per person") but would triple the height
                                             of this list if every line carried it. --}}
                                        <li class="flex items-start gap-2 text-sm" title="{{ $item['note'] }}">
                                            <svg viewBox="0 0 24 24" class="w-4 h-4 mt-0.5 shrink-0 text-moss" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="{{ $item['icon'] }}"/>
                                            </svg>
                                            <span class="text-ink/80">{{ $item['label'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-ink/70">
                                    {{ $nextTrip->host->name }} has not listed a packing list for this trip.
                                    Ask in the trip chat if you are unsure.
                                </p>
                            @endif
                        </div>

                        <a href="{{ route('reservations.show', ['token' => $nextUp->public_token]) }}" wire:navigate
                           class="flex items-center justify-center rounded-full bg-pine text-cream min-h-[44px] font-semibold hover:bg-pine/90 transition-colors">
                            View booking
                        </a>
                    </div>
                </div>

                @if($laterUp->isNotEmpty())
                    <ul class="mt-3 space-y-2" dusk="later-trips">
                        @foreach($laterUp as $r)
                            <li>
                                <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                                   class="flex items-center gap-3 rounded-2xl border border-ink/8 bg-surface p-3 hover:border-ink/25 transition-colors">
                                    <img src="{{ $r->departure->trip->cover_url }}" alt="" loading="lazy"
                                         class="w-12 h-12 rounded-xl object-cover shrink-0">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold text-sm truncate">{{ $r->departure->trip->title }}</span>
                                        <span class="block text-xs text-ink/70">{{ $r->departure->starts_at->format('D, j M Y') }}</span>
                                    </span>
                                    <svg viewBox="0 0 24 24" class="w-4 h-4 text-ink/35 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 6l6 6-6 6"/></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
        </div>
        @endif

        {{-- ═══ PANEL · FOR PAYMENT ═══ --}}
        @if($activePanel === 'pay')
        <div role="tabpanel" id="panel-pay" aria-labelledby="tab-pay" tabindex="0" class="space-y-2.5">
        {{-- ═══ AWAITING PAYMENT ═══ --}}
        @if($awaitingPayment->isNotEmpty())
            <section dusk="awaiting-payment">
                <h2 class="marker text-[11px] font-semibold text-ink/70 mb-3">
                    For payment
                    <span class="ml-1 rounded-full bg-terra/12 text-terra px-2 py-0.5 text-[11px] font-bold tabular-nums">{{ $awaitingPayment->count() }}</span>
                </h2>

                <div class="space-y-2.5">
                    @foreach($awaitingPayment as $r)
                        @php($money = $r->money())
                        {{-- A receipt the host has not ruled on yet. Status cannot say this any
                             more: sending proof leaves a confirmed booking confirmed. --}}
                        @php($submitted = $r->latestPayment && ! $r->latestPayment->confirmed_at && ! $r->latestPayment->rejected_reason)
                        @php($payDue = $r->nextPaymentDue())
                        <div class="rounded-2xl border p-4 {{ $submitted ? 'border-amber/25 bg-amber/[.06]' : 'border-terra/20 bg-terra/[.05]' }}"
                             dusk="payment-{{ $r->id }}">
                            <div class="flex items-start gap-3">
                                <img src="{{ $r->departure->trip->cover_url }}" alt="" loading="lazy"
                                     class="w-14 h-14 rounded-xl object-cover shrink-0">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold leading-snug truncate">{{ $r->departure->trip->title }}</p>
                                    <p class="text-xs text-ink/70 mt-0.5">
                                        {{ $r->departure->starts_at->format('D, j M Y') }} ·
                                        {{ $r->party_size }} {{ \Illuminate\Support\Str::plural('seat', $r->party_size) }}
                                    </p>
                                    {{-- The deadline, spelled out. The hold clock used to live here,
                                         but a hold is what runs before the host answers — those
                                         bookings sit in the confirmation panel now. What matters on
                                         a granted seat is when the money has to be in. --}}
                                    <p class="text-xs font-semibold mt-1.5 {{ $submitted ? 'text-amber' : ($r->paymentOverdue() ? 'text-terra' : 'text-ink/70') }}">
                                        @if($submitted)
                                            Proof sent — waiting on {{ $r->departure->trip->host->name }}
                                        @elseif($r->paymentOverdue())
                                            {{ $r->overdueLabel() }} — due {{ $payDue->diffForHumans() }}
                                        @elseif($payDue)
                                            Due {{ $payDue->format('j M') }} at {{ $payDue->format('g:ia') }}
                                        @else
                                            Your host has set no deadline
                                        @endif
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="font-serif font-semibold text-lg tabular-nums">
                                        {{ $r->departure->trip->host->hostProfile?->currency ?? 'PHP' }} {{ number_format($money['due']) }}
                                    </p>
                                    <p class="text-[11px] text-ink/65">due</p>
                                </div>
                            </div>

                            <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                               class="mt-3 flex items-center justify-center rounded-full min-h-[44px] text-sm font-semibold transition-colors
                                      {{ $submitted ? 'border border-ink/20 bg-surface hover:border-ink/40' : 'bg-pine text-cream hover:bg-pine/90 shadow-sm' }}">
                                {{ $submitted ? 'See booking' : 'Pay now' }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
            {{-- Traveller details ride with the money: both are your move on a seat you already
                 hold, and a fifth tab for the rarest to-do on the page earns nothing. --}}
            @foreach($alsoNeedsYou as $item)
                <x-attention-card :item="$item" />
            @endforeach
        </div>
        @endif

        {{-- ═══ PANEL · FOR CONFIRMATION ═══ --}}
        @if($activePanel === 'confirm')
        <div role="tabpanel" id="panel-confirm" aria-labelledby="tab-confirm" tabindex="0"
             dusk="for-confirmation" class="space-y-2.5">
            <p class="text-sm text-ink/70">
                Requests your host has not answered yet. You will be asked to pay once a seat is granted.
            </p>

            @foreach($forConfirmation as $r)
                @php($cTrip = $r->departure->trip)
                <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                   dusk="confirm-row-{{ $r->id }}"
                   class="flex items-center gap-3 rounded-2xl border border-ink/8 bg-surface p-3 hover:border-ink/25 transition-colors">
                    <img src="{{ $cTrip->cover_url }}" alt="" loading="lazy" class="w-12 h-12 rounded-xl object-cover shrink-0">
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-sm truncate">{{ $cTrip->title }}</span>
                        <span class="block text-xs text-ink/70">
                            {{ $r->departure->starts_at->format('D, j M Y') }} · with {{ $cTrip->host->name }}
                        </span>
                        {{-- The hold clock belongs here: it is the window the host has to answer
                             in, and a lapsed one is the only thing on this page that costs you
                             the trip. --}}
                        <span class="block text-xs font-semibold mt-0.5 {{ $r->expires_at && $r->expires_at->isPast() ? 'text-terra' : 'text-ink/70' }}">
                            {{ $this->holdWording($r) }}
                        </span>
                    </span>
                    <x-badge tone="ink" dot>Waiting</x-badge>
                </a>
            @endforeach
        </div>
        @endif

        {{-- ═══ PANEL · FOR REVIEW ═══ --}}
        @if($activePanel === 'review')
        <div role="tabpanel" id="panel-review" aria-labelledby="tab-review" tabindex="0"
             dusk="for-review" class="space-y-2.5">
            @foreach($forReview as $item)
                <x-attention-card :item="$item" dismissible />
            @endforeach
        </div>
        @endif


        {{-- ═══ CLAIM GUEST BOOKINGS ═══ --}}
        <section class="rounded-2xl bg-ink/[.03] border border-ink/8 p-5">
            @if($orphaned->isNotEmpty() && ! $claimStatus)
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-moss/10 text-moss grid place-items-center shrink-0">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold mb-1">Found {{ $orphaned->count() }} {{ \Illuminate\Support\Str::plural('booking', $orphaned->count()) }} made as a guest</p>
                        <p class="text-sm text-ink/70 mb-3">
                            We can email {{ auth()->user()->email }} a link to attach them to your account.
                        </p>
                        <button wire:click="claimGuestBookings" dusk="claim-bookings"
                                class="inline-flex items-center rounded-full border border-ink/20 px-5 min-h-[44px] text-sm font-semibold hover:border-moss hover:bg-moss/5 transition-colors">
                            Email me a link
                        </button>
                    </div>
                </div>
            @elseif($claimStatus)
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-moss/10 text-moss grid place-items-center shrink-0">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <p class="text-sm text-moss font-semibold" dusk="claim-status">{{ $claimStatus }}</p>
                </div>
            @else
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-ink/[.05] text-ink/65 grid place-items-center shrink-0">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold mb-1">Booked as a guest before?</p>
                        <p class="text-sm text-ink/70 mb-3">
                            Bookings made without an account are not listed here. We can email
                            {{ auth()->user()->email }} a link to attach them.
                        </p>
                        <button wire:click="claimGuestBookings" dusk="claim-bookings"
                                class="inline-flex items-center rounded-full border border-ink/20 px-5 min-h-[44px] text-sm font-semibold hover:border-moss hover:bg-moss/5 transition-colors">
                            Email me a link
                        </button>
                    </div>
                </div>
            @endif
        </section>
        </aside>
    </div>

{{--
    ═══ DAY SHEET ═══

    Clicking an empty square used to push a panel into the calendar card, which shoved the whole
    grid down the page — the thing you were looking at moved because you asked a question about it.
    A sheet answers over the top and leaves the calendar where it was.

    Rendered only while open, rather than hidden with CSS, so "is the day sheet showing" stays a
    fact about the HTML that a test can check rather than a class on a permanent element.
--}}
@if($dayOpen && $selectedDate !== '' && $selectedBookings->isEmpty())
    @php($sheetDate = \Illuminate\Support\Carbon::parse($selectedDate))
    <div
        x-data="{ shown: false }"
        x-init="$nextTick(() => shown = true)"
        {{-- Livewire inserts this element already "open", so an x-show that starts true would
             never animate. Flipping shown on the next tick gives the enter transition something
             to transition from. Leaving runs the reverse first, then tells the server. --}}
        x-on:keydown.escape.window="shown = false; setTimeout(() => $wire.closeDay(), 150)"
        x-effect="document.body.classList.toggle('overflow-hidden', shown)"
        x-on:livewire:navigating.window="document.body.classList.remove('overflow-hidden')"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
        role="dialog" aria-modal="true" aria-labelledby="day-sheet-title" dusk="cal-free-panel">

        <div x-show="shown" x-transition.opacity.duration.200ms
             @click="shown = false; setTimeout(() => $wire.closeDay(), 150)"
             class="absolute inset-0 bg-ink/50 backdrop-blur-[3px]" aria-hidden="true"></div>

        {{-- A bottom sheet on a phone and a centred dialog from `sm`: the same content, reachable
             by thumb on one and out of the way of the calendar on the other. --}}
        <div x-show="shown"
             x-transition:enter="transition ease-[cubic-bezier(.2,.8,.2,1)] duration-300"
             x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
             class="relative w-full sm:max-w-3xl max-h-[88dvh] sm:max-h-[85dvh] flex flex-col
                    bg-surface rounded-t-3xl sm:rounded-3xl shadow-2xl sm:m-6 overflow-hidden">

            {{-- grab handle, phone only --}}
            <div class="sm:hidden pt-3 pb-1 flex justify-center shrink-0" aria-hidden="true">
                <span class="w-10 h-1 rounded-full bg-ink/15"></span>
            </div>

            <div class="flex items-start gap-4 px-5 sm:px-6 pt-4 sm:pt-6 pb-4 shrink-0">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-moss mb-1">
                        {{ $availableTotal > 0 ? ($availableContinuing > 0 ? 'Trips available' : 'Trips leaving') : 'Nothing leaving' }}
                    </p>
                    <h2 id="day-sheet-title" class="font-serif font-semibold text-2xl leading-tight">
                        {{ $sheetDate->format('l j F') }}
                    </h2>
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        @if($selectedHoliday)
                            <span class="rounded-full bg-amber/15 text-ink px-2.5 py-0.5 text-xs font-bold">{{ $selectedHoliday }}</span>
                        @endif
                        @if($sheetDate->isWeekend())
                            <span class="rounded-full bg-moss/15 text-pine px-2.5 py-0.5 text-xs font-bold">Weekend</span>
                        @endif
                        <span class="text-xs text-ink/65">{{ $sheetDate->diffForHumans(['parts' => 1]) }}</span>
                    </div>
                </div>

                <button type="button" x-ref="close" data-autofocus
                        @click="shown = false; setTimeout(() => $wire.closeDay(), 150)"
                        aria-label="Close" dusk="cal-free-close"
                        class="shrink-0 grid place-items-center w-10 h-10 rounded-full text-ink/65
                               hover:bg-ink/5 hover:text-ink transition-colors">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <div class="px-5 sm:px-6 pb-5 sm:pb-6 overflow-y-auto">
                @if($availableOnDate->isNotEmpty())
                    @php($cur = fn ($trip) => ($trip->host->hostProfile?->currency ?? 'PHP'))
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($availableOnDate as $dep)
                            @php($seats = $dep->capacity - (int) ($dep->seats_held ?? 0))
                            <a href="{{ route('trips.show', $dep->trip) }}" wire:navigate
                               class="group rounded-2xl border border-ink/10 overflow-hidden bg-canvas
                                      hover:border-moss hover:shadow-md transition-all duration-200">
                                <span class="block relative aspect-[16/9] overflow-hidden">
                                    <img src="{{ $dep->trip->cover_url }}" alt="" loading="lazy"
                                         class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-105">
                                    <span class="absolute inset-0 bg-gradient-to-t from-ink/60 to-transparent"></span>
                                    @if($seats <= 3)
                                        <span class="absolute top-2 right-2 rounded-full bg-terra text-cream px-2 py-0.5 text-[10px] font-bold">
                                            {{ $seats }} left
                                        </span>
                                    @endif
                                    @if($label = \App\Support\PlaceCategories::label($dep->trip->place?->category))
                                        {{-- Same vocabulary as the badge on the square that opened this sheet. --}}
                                        <span class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full glass-dark px-2 py-1 text-[10px] font-bold text-cream">
                                            <svg viewBox="0 0 24 24" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ \App\Support\PlaceCategories::icon($dep->trip->place?->category) }}"/></svg>
                                            {{ $label }}
                                        </span>
                                    @endif
                                    <span class="absolute bottom-2 left-3 right-3 block text-cream font-semibold text-sm leading-tight truncate">
                                        {{ $dep->trip->title }}
                                    </span>
                                </span>
                                {{--
                                    When it actually sets off.

                                    A trip appears on every day it runs now, so a card in this sheet
                                    can be day two of something that left yesterday. Without the
                                    start date the sheet reads as though everything begins today.
                                --}}
                                @php($startsHere = $dep->starts_at->toDateString() === $selectedDate)
                                @php($span = $dep->spanDays())
                                <span class="flex items-center gap-1.5 px-3 pt-2.5 text-xs">
                                    <x-icon.calendar class="w-3.5 h-3.5 shrink-0 {{ $startsHere ? 'text-moss' : 'text-amber' }}" />
                                    <span class="truncate {{ $startsHere ? 'text-ink/70' : 'text-amber font-semibold' }}">
                                        @if($startsHere)
                                            Starts {{ $dep->starts_at->format('D j M') }}
                                            @if($span > 1)
                                                <span class="text-ink/65">· {{ $span }} days</span>
                                            @endif
                                        @else
                                            Started {{ $dep->starts_at->format('D j M') }} · day {{ $dep->dayNumberOn($selectedDate) }} of {{ $span }}
                                        @endif
                                    </span>
                                </span>

                                <span class="flex items-center justify-between gap-3 px-3 pt-1.5 pb-2.5">
                                    <span class="min-w-0 text-xs text-ink/70 truncate">
                                        {{ $dep->trip->host->name }}@if($dep->trip->place) · {{ $dep->trip->place->name }}@endif
                                    </span>
                                    <span class="shrink-0 text-sm font-serif font-semibold">
                                        {{ $cur($dep->trip) }} {{ number_format($dep->trip->current_price) }}
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 mt-4 pt-4 border-t border-ink/8">
                        <p class="text-xs text-ink/70">
                            @if($availableTotal > $availableOnDate->count())
                                Showing {{ $availableOnDate->count() }} of {{ $availableTotal }} trips leaving this day.
                            @else
                                {{ $availableTotal }} {{ \Illuminate\Support\Str::plural('trip', $availableTotal) }}
                                {{ $availableContinuing > 0 ? 'available this day.' : 'leaving this day.' }}
                            @endif
                        </p>
                        <a href="{{ route('trips.index') }}" wire:navigate
                           class="text-sm font-semibold text-moss hover:underline">Browse every trip →</a>
                    </div>
                @else
                    <div class="text-center py-10">
                        <span class="grid place-items-center w-14 h-14 mx-auto rounded-full bg-ink/[.04] text-ink/25 mb-4">
                            <x-icon.calendar class="w-6 h-6" />
                        </span>
                        <p class="font-semibold mb-1">Nothing leaves on this day yet</p>
                        <p class="text-sm text-ink/70 mb-5 max-w-xs mx-auto">
                            Hosts add departures all the time — try a nearby weekend, or see what else is going.
                        </p>
                        <a href="{{ route('trips.index') }}" wire:navigate
                           class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[44px] font-semibold hover:bg-pine/90 transition-colors">
                            Browse every trip
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
</div>
