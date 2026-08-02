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
    'review' => 'text-ink/60',
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
    {{-- Stacked on a phone: the controls held their width and squeezed the title to two lines
         and the description to four. --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between mb-8">
        <div>
            <h1 class="font-serif font-semibold text-3xl mb-1">Your trips</h1>
            <p class="text-ink/65">Everything you have booked, and anything still waiting on you.</p>
        </div>
        <div class="flex items-center gap-2 sm:shrink-0">
            {{-- Three views, shown as one segmented control so the current one is always
                 visible rather than inferred from an icon that toggles. --}}
            <div class="inline-flex items-center rounded-xl border border-ink/15 p-1 bg-surface" role="group" aria-label="View">
                @foreach([
                    'calendar' => ['Calendar', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
                    'grid' => ['Cards', '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'],
                    'list' => ['List', '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'],
                ] as $key => $meta)
                    <button type="button" wire:click="setView('{{ $key }}')" dusk="view-{{ $key }}"
                            aria-pressed="{{ $view === $key ? 'true' : 'false' }}"
                            aria-label="{{ $meta[0] }} view"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 min-h-[38px] text-sm font-semibold transition-colors
                                   {{ $view === $key ? 'bg-pine text-cream shadow-sm' : 'text-ink/60 hover:text-ink hover:bg-ink/5' }}">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $meta[1] !!}</svg>
                        <span class="hidden sm:inline">{{ $meta[0] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Two columns once there is room for them. Below xl this collapses to one, and the
         aside comes first in the DOM so "Needs you" still leads on a phone. --}}
    <div class="xl:grid xl:grid-cols-[minmax(0,1fr)_22rem] xl:gap-8 xl:items-start">

        <aside class="xl:col-start-2 xl:row-start-1 xl:sticky xl:top-6 space-y-4 mb-8 xl:mb-0">
        {{-- ═══ NEEDS YOU ═══ --}}
        @php($flatAttention = collect($attention)->flatten(1)->filter(fn ($item) => ! in_array($item['kind'], $dismissedAttention))->values())
        @if($flatAttention->isNotEmpty())
            <section class="mb-10" dusk="needs-you">
                <h2 class="marker text-[11px] font-semibold text-ink/60 mb-3">Needs you</h2>

                <div class="space-y-2">
                    @foreach($flatAttention as $item)
                        {{-- Stacked, not a horizontal row. These sit in a 22rem sidebar on wide screens,
                             where an inline CTA crushed the text to about 110px and wrapped every title
                             three lines deep. --}}
                        <div class="rounded-2xl p-4 border {{ $attentionBg[$item['kind']] ?? 'bg-ink/[.03] border-ink/8' }}"
                             dusk="attention-{{ $item['kind'] }}-{{ $item['reservation_id'] }}">
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 mt-0.5 {{ $attentionColor[$item['kind']] ?? 'text-ink/60' }}">
                                    {!! $attentionIcon[$item['kind']] ?? '' !!}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold leading-snug">{{ $item['title'] }}</p>
                                    <p class="text-sm text-ink/70 mt-1">{{ $item['detail'] }}</p>
                                </div>
                                <button type="button" wire:click="dismissAttention('{{ $item['kind'] }}')"
                                        class="shrink-0 -mt-1 -mr-1 text-ink/35 hover:text-ink/70 p-2 rounded-lg hover:bg-ink/5 transition-colors"
                                        aria-label="Dismiss">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                </button>
                            </div>
                            <a href="{{ $item['href'] }}" wire:navigate
                               class="mt-3 flex items-center justify-center rounded-full px-5 min-h-[44px] text-sm font-semibold transition-colors
                                      {{ $item['urgent'] ? 'bg-pine text-cream hover:bg-pine/90 shadow-sm' : 'border border-ink/20 hover:border-ink/40 bg-surface' }}">
                                {{ $item['cta'] }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
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
                    <div class="w-10 h-10 rounded-full bg-ink/[.05] text-ink/50 grid place-items-center shrink-0">
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

        <div class="xl:col-start-1 xl:row-start-1 min-w-0">
        {{-- Two tabs, always visible. Cards and List only: in Calendar the month is the scope, and
             the grid deliberately shows every booking, so a filter tab there would contradict it. --}}
        @if($view !== 'calendar')
            <div class="flex items-center gap-2 mb-4" role="group" aria-label="Filter trips">
                @foreach(['upcoming' => 'Upcoming', 'past' => 'Past'] as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')" dusk="tab-{{ $key }}"
                            aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                            class="rounded-full px-4 min-h-[40px] text-sm font-semibold transition-colors
                                   {{ $filter === $key ? 'bg-pine text-cream' : 'border border-ink/15 hover:border-ink/35' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ═══ BOOKINGS ═══ --}}
        <section class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="marker text-[11px] font-semibold text-ink/60">
                    {{ $view === 'calendar' ? 'Your calendar' : ($filter === 'past' ? 'Past' : 'Upcoming') }}
                </h2>
                <span class="text-xs text-ink/50 font-medium">{{ $filtered->count() }} {{ \Illuminate\Support\Str::plural('booking', $filtered->count()) }}</span>
            </div>

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
                                <p class="text-xs text-ink/55">
                                    {{ $monthCount }} {{ \Illuminate\Support\Str::plural('trip', $monthCount) }} this month
                                </p>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" wire:click="shiftMonth(-1)" dusk="cal-prev" aria-label="Previous month"
                                        class="grid place-items-center w-10 h-10 rounded-lg text-ink/60 hover:text-ink hover:bg-ink/5 transition-colors">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                                </button>
                                <button type="button" wire:click="today" dusk="cal-today"
                                        class="rounded-lg px-3 h-10 text-sm font-semibold text-ink/70 hover:text-ink hover:bg-ink/5 transition-colors">Today</button>
                                <button type="button" wire:click="shiftMonth(1)" dusk="cal-next" aria-label="Next month"
                                        class="grid place-items-center w-10 h-10 rounded-lg text-ink/60 hover:text-ink hover:bg-ink/5 transition-colors">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                                </button>
                            </div>
                        </div>

                        {{-- weekday rail --}}
                        <div class="grid grid-cols-7 gap-1 sm:gap-2 px-2 sm:px-3 pt-3">
                            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow)
                                @php($isWeekendCol = in_array($dow, ['Sat', 'Sun']))
                                <div class="text-center text-[10px] font-semibold uppercase tracking-wide pb-1 {{ $isWeekendCol ? 'text-moss' : 'text-ink/40' }}">
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
                                            {{-- A booked day goes straight to that booking. It is the thing the joiner wants:
                                                 pay, traveller details and the host's payment methods all live there. --}}
                                            <a href="{{ route('reservations.show', ['token' => $booking->public_token]) }}" wire:navigate
                                               dusk="cal-day-{{ $iso }}"
                                               title="{{ $trip->title }}@if($day['holiday']) · {{ $day['holiday'] }}@endif"
                                               aria-label="{{ $day['date']->format('j F') }} — {{ $trip->title }}, open this booking"
                                               class="group relative aspect-square rounded-lg sm:rounded-2xl overflow-hidden block
                                                      ring-offset-2 ring-offset-surface transition-all duration-300
                                                      {{ $day['isToday'] ? 'ring-2 ring-pine' : 'hover:ring-2 hover:ring-ink/25' }}">
                                                <img src="{{ $trip->cover_url }}" alt=""
                                                     class="absolute inset-0 w-full h-full object-cover transition-transform duration-[1200ms] ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.12] {{ $isPast ? 'grayscale' : '' }}">
                                                <div class="absolute inset-0 bg-gradient-to-t from-ink/70 via-ink/25 to-ink/10" aria-hidden="true"></div>

                                                <span class="absolute top-1 left-1 sm:top-2 sm:left-2 grid place-items-center min-w-[1.35rem] sm:min-w-[1.6rem] h-5 sm:h-6 px-1 rounded-full bg-cream text-ink text-[10px] sm:text-xs font-bold shadow-sm">
                                                    {{ $day['date']->day }}
                                                </span>

                                                @if($day['bookings']->count() > 1)
                                                    <span class="absolute top-1 right-1 sm:top-2 sm:right-2 grid place-items-center h-5 sm:h-6 px-1.5 rounded-full bg-pine text-cream text-[10px] font-bold">
                                                        +{{ $day['bookings']->count() - 1 }}
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
                                            {{-- An empty square is a question — "could I go somewhere that day?" — so it answers it
                                                 rather than sitting inert. Weekends are tinted moss and holidays flagged, because
                                                 those are the days a joiner is actually free to travel. --}}
                                            <button type="button" wire:click="selectDate('{{ $iso }}')"
                                                    dusk="cal-free-{{ $iso }}"
                                                    title="@if($day['holiday']){{ $day['holiday'] }}@else{{ $day['date']->format('j F') }}@endif"
                                                    aria-label="{{ $day['date']->format('j F') }}@if($day['holiday']) — {{ $day['holiday'] }}@endif — see trips leaving this day"
                                                    class="relative aspect-square rounded-lg sm:rounded-2xl border grid place-items-center transition-colors
                                                           hover:border-moss/50 hover:bg-moss/10
                                                           {{ $day['isToday'] ? 'ring-2 ring-pine border-transparent' : '' }}
                                                           {{ $selectedDate === $iso ? 'border-moss bg-moss/12' : ($day['holiday'] ? 'border-amber/45 bg-amber/[.13]' : ($day['isWeekend'] ? 'border-moss/55 bg-moss/[.10]' : 'border-ink/[.07] '.($day['inMonth'] ? 'bg-ink/[.015]' : 'bg-transparent'))) }}">
                                                <span class="text-[11px] sm:text-xs {{ $day['isWeekend'] || $day['holiday'] ? 'font-bold' : 'font-semibold' }}
                                                             {{ $day['inMonth'] ? ($day['holiday'] ? 'text-ink' : ($day['isWeekend'] ? 'text-pine' : 'text-ink/40')) : 'text-ink/15' }}">
                                                    {{ $day['date']->day }}
                                                </span>
                                                @if($day['holiday'])
                                                    <span class="absolute bottom-1 inset-x-1 hidden sm:block text-[9px] leading-tight text-ink/70 font-semibold truncate">{{ $day['holiday'] }}</span>
                                                    <span class="sm:hidden absolute bottom-1 w-1 h-1 rounded-full bg-amber" aria-hidden="true"></span>
                                                @endif
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        {{-- Phone: a 48px cell has no room for a title, so the tapped day is listed here. --}}
                        {{-- What the tapped free day has on offer. Shown at every width, unlike the phone-only
                             booking list below: on a laptop this is the answer to clicking an empty square. --}}
                        @if($selectedDate !== '' && $selectedBookings->isEmpty())
                            <div class="border-t border-ink/8 p-4" dusk="cal-free-panel">
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1 mb-3">
                                    <p class="font-semibold">{{ \Illuminate\Support\Carbon::parse($selectedDate)->format('D j F Y') }}</p>
                                    @if($selectedHoliday)
                                        <span class="rounded-full bg-amber/15 text-amber px-2.5 py-0.5 text-xs font-bold">{{ $selectedHoliday }}</span>
                                    @endif
                                    @if(\Illuminate\Support\Carbon::parse($selectedDate)->isWeekend())
                                        <span class="rounded-full bg-moss/15 text-pine px-2.5 py-0.5 text-xs font-bold">Weekend</span>
                                    @endif
                                </div>

                                @if($availableOnDate->isNotEmpty())
                                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                        @foreach($availableOnDate as $dep)
                                            <a href="{{ route('trips.show', $dep->trip) }}" wire:navigate
                                               class="flex items-center gap-3 rounded-xl border border-ink/10 p-2.5 hover:border-moss transition-colors">
                                                <img src="{{ $dep->trip->cover_url }}" alt="" class="w-12 h-12 rounded-lg object-cover shrink-0">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block font-semibold text-sm truncate">{{ $dep->trip->title }}</span>
                                                    <span class="block text-xs text-ink/60">{{ $dep->trip->host->name }} · {{ $dep->spotsLeft() }} {{ \Illuminate\Support\Str::plural('seat', $dep->spotsLeft()) }} left</span>
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-ink/60">
                                        Nothing leaves on this day yet.
                                        <a href="{{ route('trips.index') }}" wire:navigate class="font-semibold text-moss hover:underline">Browse every trip</a>
                                    </p>
                                @endif
                            </div>
                        @endif
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
                    <div class="mt-4 rounded-2xl border-2 border-dashed border-ink/15 px-6 py-10 text-center bg-ink/[.01]">
                        <p class="font-semibold mb-1">No trips booked yet</p>
                        <p class="text-ink/65 text-sm mb-5">Find a group heading somewhere you want to go.</p>
                        <a href="{{ route('trips.index') }}" wire:navigate
                           class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors shadow-sm">Explore trips</a>
                    </div>
                @endif
            @elseif($filtered->isNotEmpty())
                @if($view === 'grid')
                    {{-- Grouped by month: a year of bookings reads as a shape rather than a wall of rows. --}}
                    @foreach($byMonth as $monthLabel => $monthBookings)
                        <div class="mb-8 last:mb-0">
                            <div class="flex items-center gap-3 mb-3">
                                <h3 class="font-serif font-semibold text-lg">{{ $monthLabel }}</h3>
                                <span class="h-px flex-1 bg-ink/10"></span>
                                <span class="text-xs text-ink/50 font-semibold">{{ $monthBookings->count() }}</span>
                            </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach($monthBookings as $r)
                            @php($trip = $r->departure->trip)
                            @php($st = $statusLabel[$r->status] ?? [ucfirst(str_replace('_', ' ', $r->status)), 'ink'])
                            <div class="flex flex-col rounded-2xl border border-ink/10 overflow-hidden hover:border-ink/25 hover:shadow-[0_10px_30px_-18px_rgba(31,28,21,.35)] transition-all duration-300"
                                 dusk="booking-{{ $r->id }}">
                                <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                                   class="block relative group">
                                    <img src="{{ $trip->cover_url }}" alt="" class="w-full h-36 object-cover transition-transform duration-[900ms] ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.04]">
                                    <div class="absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-ink/60 to-transparent" aria-hidden="true"></div>
                                    <span class="absolute top-3 left-3 grid place-items-center rounded-xl bg-cream/95 backdrop-blur-sm px-2.5 py-1 text-center shadow-sm">
                                        <span class="block text-[10px] font-bold uppercase tracking-wide text-terra leading-none">{{ $r->departure->starts_at->format('M') }}</span>
                                        <span class="block font-serif font-bold text-lg text-ink leading-none mt-0.5">{{ $r->departure->starts_at->format('j') }}</span>
                                    </span>
                                    <span class="absolute top-3 right-3 rounded-full px-3 py-1 text-xs font-bold backdrop-blur-sm
                                                 {{ $st[1] === 'moss' ? 'bg-moss/90 text-cream' : ($st[1] === 'amber' ? 'bg-amber/90 text-cream' : 'bg-ink/70 text-cream/90') }}">
                                        {{ $st[0] }}
                                    </span>
                                </a>
                                <div class="p-4 flex-1 flex flex-col">
                                    <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                                       class="block min-w-0 mb-1">
                                        <p class="font-semibold truncate">{{ $trip->title }}</p>
                                    </a>
                                    <p class="text-sm text-ink/65 mb-3">
                                        {{ $r->departure->starts_at->format('D j M Y') }}
                                        <span class="text-ink/40">·</span> {{ $r->party_size }} {{ \Illuminate\Support\Str::plural('seat', $r->party_size) }}
                                        <span class="text-ink/40">·</span> {{ $trip->host->name }}
                                    </p>
                                    @if($r->owesBalance())
                                        @php($money = $r->money())
                                        <div class="flex items-center gap-2 rounded-xl bg-amber/[.08] border border-amber/15 px-3 py-2 mb-3">
                                            <svg viewBox="0 0 24 24" class="w-4 h-4 text-amber shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                            <p class="text-sm text-amber font-semibold">
                                                {{ number_format($money['due']) }} balance due on the day
                                            </p>
                                        </div>
                                    @endif
                                    <div class="flex items-center justify-between mt-auto pt-3 border-t border-ink/8">
                                        <div class="flex -space-x-2">
                                            @foreach($participantInitials($r) as $init)
                                                <span class="w-7 h-7 rounded-full bg-ink/10 text-ink/60 text-xs font-bold grid place-items-center border-2 border-surface">{{ $init }}</span>
                                            @endforeach
                                            @if($namedTravellers($r) > 3)
                                                <span class="w-7 h-7 rounded-full bg-ink/5 text-ink/50 text-xs font-bold grid place-items-center border-2 border-surface">+{{ $namedTravellers($r) - 3 }}</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                                               class="inline-flex items-center rounded-full border border-ink/20 text-sm font-semibold text-pine hover:text-pine/80 hover:border-ink/30 min-h-[40px] px-4 transition-colors">View</a>
                                            @if(in_array($r->status, ['pending', 'awaiting_payment', 'payment_submitted']))
                                                <button type="button" wire:click="cancelBooking({{ $r->id }})" wire:confirm="Are you sure you want to cancel this booking?"
                                                        class="inline-flex items-center rounded-full border border-ink/20 text-sm font-semibold text-terra/80 hover:text-terra hover:border-terra/30 min-h-[40px] px-4 transition-colors">Cancel</button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                        </div>
                    @endforeach
                @else
                    <div class="space-y-2">
                        @foreach($filtered as $r)
                            @php($trip = $r->departure->trip)
                            @php($st = $statusLabel[$r->status] ?? [ucfirst(str_replace('_', ' ', $r->status)), 'ink'])
                            <div class="flex items-center gap-4 rounded-2xl border border-ink/10 p-4 hover:border-ink/25 hover:shadow-[0_4px_12px_-8px_rgba(31,28,21,.2)] transition-all"
                                 dusk="booking-{{ $r->id }}">
                                <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate class="shrink-0">
                                    <img src="{{ $trip->cover_url }}" alt="" class="w-16 h-16 rounded-xl object-cover">
                                </a>
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate class="block">
                                        <p class="font-semibold truncate">{{ $trip->title }}</p>
                                        <p class="text-sm text-ink/65">
                                            {{ $r->departure->starts_at->format('D j M Y') }}
                                            <span class="text-ink/40">·</span> {{ $r->party_size }} {{ \Illuminate\Support\Str::plural('seat', $r->party_size) }}
                                            <span class="text-ink/40">·</span> {{ $trip->host->name }}
                                        </p>
                                    </a>
                                    @if($r->owesBalance())
                                        @php($money = $r->money())
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-amber shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                            <p class="text-sm text-amber font-semibold">
                                                {{ number_format($money['due']) }} balance due on the day
                                            </p>
                                        </div>
                                    @endif
                                </div>
                                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold backdrop-blur-sm
                                             {{ $st[1] === 'moss' ? 'bg-moss/90 text-cream' : ($st[1] === 'amber' ? 'bg-amber/90 text-cream' : 'bg-ink/70 text-cream/90') }}">
                                    {{ $st[0] }}
                                </span>
                                <div class="flex items-center gap-1 shrink-0">
                                    <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                                       class="inline-flex items-center rounded-full border border-ink/20 text-sm font-semibold text-pine hover:text-pine/80 hover:border-ink/30 min-h-[40px] px-4 transition-colors">View</a>
                                    @if(in_array($r->status, ['pending', 'awaiting_payment', 'payment_submitted']))
                                        <button type="button" wire:click="cancelBooking({{ $r->id }})" wire:confirm="Are you sure you want to cancel this booking?"
                                                class="inline-flex items-center rounded-full border border-ink/20 text-sm font-semibold text-terra/80 hover:text-terra hover:border-terra/30 min-h-[40px] px-4 transition-colors">Cancel</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 py-16 text-center bg-ink/[.01]">
                    <div class="w-16 h-16 rounded-full bg-ink/[.04] grid place-items-center mx-auto mb-4">
                        <svg viewBox="0 0 24 24" class="w-8 h-8 text-ink/30" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <p class="font-semibold mb-1">No trips booked yet</p>
                    <p class="text-ink/65 text-sm mb-5">Find a group heading somewhere you want to go.</p>
                    <a href="{{ route('trips.index') }}" wire:navigate
                       class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors shadow-sm">Explore trips</a>

                    @if($recommended->isNotEmpty())
                        <div class="mt-10 pt-8 border-t border-ink/10 text-left">
                            <div class="flex items-center gap-2 mb-4">
                                <svg viewBox="0 0 24 24" class="w-5 h-5 text-moss shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                                <p class="text-sm font-semibold text-ink/70">Based on trips you have booked before</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach($recommended as $trip)
                                    <a href="{{ route('trips.show', $trip) }}" wire:navigate
                                       class="group block rounded-2xl border border-ink/10 overflow-hidden hover:border-ink/25 hover:shadow-[0_10px_30px_-18px_rgba(31,28,21,.35)] transition-all duration-300">
                                        <div class="relative aspect-[16/10] overflow-hidden">
                                            <img src="{{ $trip->cover_url }}" alt="" class="w-full h-full object-cover transition-transform duration-[900ms] ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.06]">
                                        </div>
                                        <div class="p-3">
                                            <p class="font-semibold text-sm truncate">{{ $trip->title }}</p>
                                            <p class="text-xs text-ink/65">{{ $trip->place->name }} · {{ $trip->host->name }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        {{-- ═══ PAST ═══ --}}
        @if($filter !== 'past' && $filter !== 'action' && $past->isNotEmpty())
            <section class="mb-8">
                <h2 class="marker text-[11px] font-semibold text-ink/60 mb-3">Past</h2>

                @if($view === 'grid')
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach($past as $r)
                            @php($trip = $r->departure->trip)
                            <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                               dusk="past-{{ $r->id }}"
                               class="flex items-center gap-4 rounded-2xl border border-ink/10 p-4 hover:border-ink/25 hover:shadow-[0_4px_12px_-8px_rgba(31,28,21,.2)] transition-all opacity-75 hover:opacity-100">
                                <img src="{{ $trip->cover_url }}" alt="" class="w-16 h-16 rounded-xl object-cover shrink-0 grayscale">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold truncate">{{ $trip->title }}</p>
                                    <p class="text-sm text-ink/65">{{ $r->departure->starts_at->format('D j M Y') }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($past as $r)
                            @php($trip = $r->departure->trip)
                            <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                               dusk="past-{{ $r->id }}"
                               class="flex items-center gap-4 rounded-2xl border border-ink/10 p-4 hover:border-ink/25 hover:shadow-[0_4px_12px_-8px_rgba(31,28,21,.2)] transition-all opacity-75 hover:opacity-100">
                                <img src="{{ $trip->cover_url }}" alt="" class="w-14 h-14 rounded-xl object-cover shrink-0 grayscale">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold truncate">{{ $trip->title }}</p>
                                    <p class="text-sm text-ink/65">{{ $r->departure->starts_at->format('D j M Y') }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
        </div>
    </div>
</div>
