@php($fmt = fn ($n) => $currency.' '.number_format($n))

<div>
    <a href="{{ route('host.dashboard') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-ink mb-3">
        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 6l-6 6 6 6"/></svg>
        Dashboard
    </a>

    {{-- ═══ HEADER ═══ --}}
    <div class="flex flex-wrap items-start gap-4 mb-6">
        <img src="{{ $trip->cover_url }}" alt="" loading="lazy" class="w-24 h-[3.75rem] sm:w-32 sm:h-20 rounded-2xl object-cover shrink-0 shadow-sm">
        <div class="min-w-0 flex-1">
            <h1 class="font-serif font-semibold text-3xl leading-tight">{{ $trip->title }}</h1>
            <p class="text-ink/70 mt-1">
                {{ $departure->starts_at->format('D j M Y') }}@if($departure->ends_at) → {{ $departure->ends_at->format('j M') }}@endif
                <span class="text-ink/50">·</span> {{ $trip->place?->name }}
                <span class="text-ink/50">·</span> {{ $fmt($trip->current_price) }} per person
            </p>
            @if($trip->takesDeposit())
                <p class="text-sm text-ink/70 mt-1">
                    Down payment trip — <b class="font-semibold">{{ $fmt($trip->payable_now) }}</b> to reserve,
                    balance collected on the day.
                </p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('host.trips.edit', $trip) }}" wire:navigate class="rounded-full border border-ink/15 px-4 min-h-[44px] inline-flex items-center text-sm font-semibold hover:border-moss transition-colors">Edit trip</a>
            <a href="{{ route('chat.show', $departure) }}" wire:navigate class="rounded-full border border-ink/15 px-4 min-h-[44px] inline-flex items-center text-sm font-semibold hover:border-ink/30 transition-colors">Chat</a>
            <a href="{{ route('host.checkin', $departure) }}" wire:navigate dusk="open-checkin"
               class="rounded-full border border-ink/15 px-4 min-h-[44px] inline-flex items-center gap-2 text-sm font-semibold hover:border-moss transition-colors">
                <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/></svg>
                Check-in
            </a>
            <a href="{{ route('host.trips.qr', $trip) }}" target="_blank" class="rounded-full border border-ink/15 px-4 min-h-[44px] inline-flex items-center text-sm font-semibold hover:border-ink/30 transition-colors">Trip QR</a>
            <button wire:click="$toggle('showAdd')" dusk="toggle-add" class="rounded-full bg-pine text-cream px-5 min-h-[44px] font-semibold hover:bg-pine/90 transition-colors">＋ Add guest</button>

            {{-- Calling the whole thing off. Behind a confirmation because it releases every seat
                 and emails every joiner — and it is offered only while there is still a trip to
                 cancel. --}}
            @if($departure->status !== 'cancelled' && $departure->starts_at->isFuture())
                <x-confirm-action
                    action="cancelDeparture"
                    title="Cancel this departure?"
                    body="Every held and confirmed seat is released and each joiner is emailed. Anyone who has already paid arranges their refund with you directly — Terra never held the money."
                    confirm-label="Yes, cancel the trip"
                    cancel-label="Keep it running"
                    tone="terra" icon="warning"
                    dusk="cancel-departure"
                    class="rounded-full border border-ink/15 px-4 min-h-[44px] inline-flex items-center text-sm font-semibold text-terra/80 hover:text-terra hover:border-terra/30 transition-colors">
                    <x-slot:details>
                        <label class="block">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.09em] text-ink/45">Reason (shown to joiners)</span>
                            <input wire:model="cancelReason" type="text" placeholder="e.g. Typhoon signal 3"
                                   class="field mt-1.5" dusk="cancel-reason">
                        </label>
                    </x-slot:details>
                    Cancel departure
                </x-confirm-action>
            @endif
        </div>
    </div>

    {{-- ═══ AT A GLANCE ═══ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="glass-card px-5 py-4">
            <div class="marker text-[10px] font-semibold text-ink/65">Seats held</div>
            <div class="font-serif font-bold text-2xl leading-none mt-1.5 tabular-nums">{{ $departure->heldCount() }}<span class="text-ink/45">/{{ $departure->capacity }}</span></div>
            <div class="mt-2 h-1.5 rounded-full bg-ink/10 overflow-hidden">
                <div class="h-full bg-moss" style="width: {{ $departure->capacity ? min(100, (int) round($departure->heldCount() / $departure->capacity * 100)) : 0 }}%"></div>
            </div>
        </div>

        <div class="glass-card px-5 py-4">
            <div class="marker text-[10px] font-semibold text-ink/65">Expected</div>
            <div class="font-serif font-bold text-2xl leading-none mt-1.5 tabular-nums">{{ $fmt($money['expected']) }}</div>
            <div class="text-sm text-ink/65 mt-1">if everyone travels</div>
        </div>

        <div class="glass-moss rounded-2xl px-5 py-4">
            <div class="marker text-[10px] font-semibold text-pine">Collected</div>
            <div class="font-serif font-bold text-2xl leading-none mt-1.5 tabular-nums text-pine">{{ $fmt($money['collected']) }}</div>
            <div class="text-sm text-ink/75 mt-1">confirmed payments</div>
        </div>

        <div class="{{ $money['outstanding'] > 0 ? 'glass-terra rounded-2xl' : 'glass-card' }} px-5 py-4">
            <div class="marker text-[10px] font-semibold {{ $money['outstanding'] > 0 ? 'text-ink/75' : 'text-ink/65' }}">Outstanding</div>
            <div class="font-serif font-bold text-2xl leading-none mt-1.5 tabular-nums">{{ $fmt($money['outstanding']) }}</div>
            <div class="text-sm text-ink/65 mt-1">still to collect</div>
        </div>
    </div>

    @if($showAdd)
        <div class="glass-moss rounded-2xl p-5 flex flex-wrap gap-4 items-end mb-4">
            <label class="block flex-1 min-w-[12rem]">
                <span class="field-label">Guest name</span>
                <input wire:model="guest_name" class="field" placeholder="Walk-in name" dusk="guest-name">
                @error('guest_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <label class="block">
                <span class="field-label">Party</span>
                <input type="number" wire:model="guest_party" class="field-narrow w-24" dusk="guest-party">
                @error('guest_party')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <button wire:click="addOffline" dusk="add-offline" class="rounded-full bg-moss text-cream px-6 min-h-[48px] font-semibold hover:bg-moss/90 transition-colors">Add offline guest</button>
        </div>
    @endif

    {{-- ═══ WHO'S COMING ═══ --}}
    <div class="flex flex-wrap items-end justify-between gap-4 mb-4">
        <h2 class="font-serif font-semibold text-xl">
            Who's coming
            <span class="font-sans font-normal text-base text-ink/65">
                @if($reservations->count() === $totalCount)
                    · {{ $totalCount }} {{ \Illuminate\Support\Str::plural('booking', $totalCount) }}
                @else
                    · showing {{ $reservations->count() }} of {{ $totalCount }}
                @endif
            </span>
        </h2>

        {{-- Name, email, phone or payment reference — a host looks people up by whatever they
             have in front of them, which is rarely the same field twice. --}}
        <label class="relative w-full sm:w-72">
            <span class="sr-only">Search this roster</span>
            <svg viewBox="0 0 24 24" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-ink/40 pointer-events-none" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
            <input wire:model.live.debounce.250ms="search" type="search" dusk="roster-search"
                   placeholder="Search name, email, phone, reference…"
                   class="w-full rounded-full border-ink/15 bg-surface pl-10 pr-4 py-2.5 text-sm focus:border-moss focus:ring-moss">
        </label>
    </div>

    {{-- Counts on the chips so a host can see there is nothing to chase without clicking. --}}
    @php($filterLabels = [
        'all' => 'Everyone',
        'unconfirmed' => 'Not confirmed',
        'unpaid' => 'Not fully paid',
        'paid' => 'Fully paid',
        'cancelled' => 'Cancelled',
    ])
    <div class="flex flex-wrap gap-2 mb-4" role="group" aria-label="Filter roster">
        @foreach($filterLabels as $key => $label)
            @continue($key === 'cancelled' && ($counts['cancelled'] ?? 0) === 0)
            <button type="button" wire:click="setFilter('{{ $key }}')" dusk="filter-{{ $key }}"
                    aria-pressed="{{ $activeFilter === $key ? 'true' : 'false' }}"
                    class="inline-flex items-center gap-2 rounded-full border px-3.5 min-h-[36px] text-sm font-semibold transition-colors
                           {{ $activeFilter === $key ? 'border-pine bg-pine text-cream' : 'border-ink/15 bg-surface hover:border-ink/35' }}">
                {{ $label }}
                <span class="rounded-full px-1.5 text-[11px] font-bold tabular-nums
                             {{ $activeFilter === $key ? 'bg-cream/20' : 'bg-ink/[.07] text-ink/60' }}">{{ $counts[$key] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    {{--
        A manifest of people, not a ledger of transactions.

        Every version of this page so far has been shaped around the booking: the booker's name
        was the heading, the travellers were a footnote inside it, and the money did the talking.
        But nobody stands at a trailhead reading bookings — they read a list of who is coming.
        So a row is a person. Where several people share a booking, the things that belong to
        the booking rather than the person — seat, money, controls — are merged across their
        rows with rowspan, which says "these three are one payment" without repeating it.
    --}}
    <div class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <caption class="sr-only">Travellers on {{ $trip->title }}, {{ $departure->starts_at->format('j M Y') }}</caption>
                <thead>
                    <tr class="bg-ink/[.03] text-left">
                        <th scope="col" class="px-4 py-2.5 text-[11px] uppercase tracking-wide text-ink/50 font-semibold">Traveller</th>
                        <th scope="col" class="px-4 py-2.5 text-[11px] uppercase tracking-wide text-ink/50 font-semibold">Status</th>
                        <th scope="col" class="px-4 py-2.5 text-[11px] uppercase tracking-wide text-ink/50 font-semibold text-right">Balance</th>
                        <th scope="col" class="px-4 py-2.5 text-[11px] uppercase tracking-wide text-ink/50 font-semibold text-right">Actions</th>
                    </tr>
                </thead>

        @forelse($reservations as $r)
            @php($owed = $r->money())
            {{-- One word each. "Awaiting your review" and "Nothing paid · PHP 2,000" were
                 sentences in a column three words wide; the money column already says the
                 amount, so the chip only has to name the state. --}}
            @php($seat = match ($r->status) {
                'confirmed' => ['Confirmed', 'moss'],
                'payment_submitted' => ['Review', 'amber'],
                'awaiting_payment' => ['Unpaid', 'amber'],
                'cancelled' => ['Cancelled', 'ink'],
                default => ['Pending', 'ink'],
            })
            @php($people = $r->participants->isNotEmpty() ? $r->participants : collect([null]))
            @php($span = $people->count())

                <tbody class="border-t-2 border-ink/[.07]" dusk="reservation-{{ $r->id }}">
                    @foreach($people as $p)
                        <tr class="{{ $loop->first ? '' : 'border-t border-ink/[.06]' }} align-top
                                   {{ $r->status === 'payment_submitted' ? 'bg-amber/[.05]' : '' }}">

                            {{--
                                The traveller's own details, in a fixed shape.

                                This is the roster, so the row is the person travelling — it was
                                showing the booker's email and phone even when the booker was a
                                different human, which on a page whose whole job is "who is
                                coming" is the wrong contact entirely. Their own details win; the
                                booking's are the fallback for a seat booked on someone's behalf.

                                Three lines every time, never two on one row and four on the next.
                            --}}
                            @php($personEmail = $p?->email ?: ($loop->first ? ($r->joiner?->email ?: $r->contact_email) : null))
                            @php($personPhone = $p?->phone ?: ($loop->first ? ($r->joiner?->phone ?: $r->guest_phone) : null))

                            <th scope="row" class="px-4 py-3.5 text-left font-normal align-top min-w-[17rem]">
                                <span class="flex items-start gap-2.5">
                                    <span class="grid place-items-center w-8 h-8 rounded-full bg-pine/8 text-pine text-[11px] font-bold shrink-0 mt-0.5">
                                        {{ strtoupper(mb_substr($p?->first_name ?: $r->displayName(), 0, 1).mb_substr($p?->last_name ?: '', 0, 1)) }}
                                    </span>

                                    <span class="min-w-0">
                                        <span class="flex items-center gap-2 flex-wrap">
                                            @if($p && $p->isComplete())
                                                <span class="font-semibold text-[15px] text-ink leading-tight">{{ $p->fullName() }}</span>
                                            @elseif($p)
                                                <span class="text-ink/45 italic">Details still needed</span>
                                            @else
                                                <span class="text-ink/45 italic">No travellers named</span>
                                            @endif

                                            @if($trip->requires_id)
                                                {{-- The reason the host turned the requirement on:
                                                     at a permit desk they need the document, not a
                                                     note that one exists. --}}
                                                @if($p?->id_image)
                                                    <a href="{{ route('participants.id', $p) }}" target="_blank" rel="noopener noreferrer"
                                                       dusk="roster-id-{{ $p->id }}"
                                                       class="inline-flex items-center gap-1 rounded-full bg-moss/12 text-pine px-2.5 py-1 text-[11px] font-semibold hover:bg-moss/20 transition-colors">
                                                        ID ↗
                                                    </a>
                                                @elseif($p)
                                                    <x-badge tone="terra" dot dusk="roster-id-missing-{{ $p->id }}">No ID</x-badge>
                                                @endif
                                            @endif

                                            @if($p?->is_booker)
                                                <x-badge tone="ink">Booker</x-badge>
                                            @elseif($loop->first && ! $r->bookerIsTraveling())
                                                {{-- The person who paid is not on the trip, so the
                                                     host should not look for them at the trailhead.
                                                     A badge in this cell, not a block stranded
                                                     between </th> and the first <td> — which is
                                                     inside the row but outside every cell, and the
                                                     browser hoists that out above the table. --}}
                                                <x-badge tone="ink">Booked by {{ $r->displayName() }}</x-badge>
                                            @endif
                                        </span>

                                        <span class="block text-xs text-ink/55 mt-1">
                                            @if($p?->birthday)
                                                {{-- (int): Carbon 3 returns a float here. --}}
                                                {{ (int) $p->birthday->diffInYears($departure->starts_at) }}y
                                                @if($p->gender)<span class="capitalize">· {{ $p->gender }}</span>@endif
                                            @else
                                                <span class="text-ink/30">Age not given</span>
                                            @endif
                                        </span>

                                        <span class="block text-xs text-ink/55 mt-0.5 truncate">
                                            @if($personEmail)
                                                <a href="mailto:{{ $personEmail }}" class="hover:text-ink hover:underline">{{ $personEmail }}</a>
                                            @endif
                                            @if($personPhone)
                                                @if($personEmail)<span class="text-ink/25" aria-hidden="true">·</span>@endif
                                                <a href="tel:{{ $personPhone }}" class="font-semibold text-moss hover:underline">{{ $personPhone }}</a>
                                            @endif
                                            @if(! $personEmail && ! $personPhone)<span class="text-ink/30">No contact given</span>@endif
                                        </span>
                                    </span>
                                </span>
                            </th>


                            @if($loop->first)
                                {{-- Status: badges only, stacked, one per fact. --}}
                                <td rowspan="{{ $span }}" class="px-4 py-3.5 align-top whitespace-nowrap">
                                    @php($unreviewed = $r->latestPayment && ! $r->latestPayment->confirmed_at && ! $r->latestPayment->rejected_reason)
                                    <span class="flex flex-col items-start gap-1.5">
                                        <x-badge :tone="$seat[1]" :dot="$r->status !== 'confirmed'">{{ $seat[0] }}</x-badge>

                                        @if($r->status === 'cancelled' && $r->refund_due > 0)
                                            <x-badge :tone="$r->refunded_at ? 'moss' : 'terra'" :dot="! $r->refunded_at"
                                                     dusk="refund-{{ $r->id }}">
                                                {{ $r->refunded_at ? 'Refunded' : 'Refund owed' }}
                                            </x-badge>
                                        @endif

                                        @if($r->status !== 'cancelled')
                                            @if($owed['due'] <= 0)
                                                <x-badge tone="moss" dusk="paid-chip-{{ $r->id }}">Paid in full</x-badge>
                                            @elseif($owed['paid'] > 0)
                                                <x-badge tone="amber">Part paid</x-badge>
                                            @else
                                                <x-badge tone="ink">Unpaid</x-badge>
                                            @endif
                                        @endif

                                        {{-- A receipt nobody has ruled on. Status cannot express it:
                                             confirming leaves a booking at `confirmed` and an
                                             unverified submission credits nothing, so a new upload
                                             against a confirmed booking left no trace at all. --}}
                                        @if($unreviewed)
                                            <x-badge tone="terra" dot dusk="new-proof-{{ $r->id }}">New proof</x-badge>
                                        @endif

                                        {{-- Late, and only ever said out loud. Nothing cancels a seat
                                             on a timer: the host granted this person a place, and
                                             taking it back is their call, not a scheduled command's. --}}
                                        @if($r->paymentOverdue())
                                            <x-badge tone="terra" dot dusk="overdue-{{ $r->id }}">{{ $r->overdueLabel() }}</x-badge>
                                        @endif
                                    </span>
                                </td>

                                {{-- Balance: numbers right-aligned and tabular, so a column of them lines up. --}}
                                <td rowspan="{{ $span }}" class="px-4 py-3.5 align-top text-right whitespace-nowrap">
                                    @if($r->status === 'cancelled')
                                        {{-- A cancelled booking that had paid is not a dash: it is
                                             money the host owes back, and this is the only place
                                             it is recorded. --}}
                                        @if($r->refund_due > 0)
                                            <span class="block font-serif font-bold text-base tabular-nums {{ $r->refunded_at ? 'text-moss' : 'text-terra' }}">
                                                {{ $fmt((float) $r->refund_due) }}
                                            </span>
                                            <span class="block text-[11px] mt-0.5 {{ $r->refunded_at ? 'text-moss' : 'text-terra' }}">
                                                {{ $r->refunded_at ? 'refunded '.$r->refunded_at->format('j M') : 'to refund' }}
                                            </span>
                                        @else
                                            <span class="text-ink/30">—</span>
                                        @endif
                                    @else
                                        <span class="block font-serif font-bold text-base tabular-nums {{ $owed['due'] > 0 ? 'text-amber' : 'text-moss' }}">
                                            {{ $owed['due'] > 0 ? $fmt($owed['due']).' due' : 'Settled' }}
                                        </span>
                                        <span class="block text-[11px] text-ink/50 mt-0.5">
                                            {{ $fmt($owed['paid']) }} of {{ $fmt($owed['total']) }}
                                        </span>
                                        {{-- When each decision was taken. A host chasing a
                                             discrepancy weeks later needs the dates, not just
                                             the current state. --}}
                                        @if($r->confirmed_at)
                                            <span class="block text-[11px] text-ink/40 mt-0.5">Confirmed {{ $r->confirmed_at->format('j M Y') }}</span>
                                        @endif
                                        @if($r->fully_paid_at)
                                            <span class="block text-[11px] text-moss mt-0.5" dusk="paid-date-{{ $r->id }}">Paid in full {{ $r->fully_paid_at->format('j M Y') }}</span>
                                        @endif
                                    @endif
                                </td>

                                <td rowspan="{{ $span }}" class="px-4 py-3">
                            {{--
                                                                                        Actions for any live booking, not only one with a submission waiting.

                                                                                        Money changes hands outside the app, so a host regularly knows a booking is
                                                                                        paid without the joiner ever uploading anything — cash at the jump-off, or a
                                                                                        transfer they saw in their own notifications. This block used to render only
                                                                                        for `payment_submitted`, which left every `pending` booking with no button at
                                                                                        all and the collected total permanently short.
                                                                                    --}}
                                                                                    @php($canAct = in_array($r->status, ['pending', 'awaiting_payment', 'payment_submitted'], true))
                                                                                    {{-- Any submission at all, not only one carrying an image: a reference-only
                                                                                         payment is still something the host may need to look back at, and gating
                                                                                         on the image left those bookings with no actions row whatsoever. --}}
                                                                                    @php($hasProofs = $r->paymentSubmissions->isNotEmpty())
                                                                                    {{-- A live booking always has something to offer: money to settle, or a paid tag
                                                                                         to take back. Without this a confirmed+paid row had no controls at all, which
                                                                                         is exactly the row a host needs to correct after a mis-tap. --}}
                                                                                    @php($canPay = $r->status !== 'cancelled' && ($owed['due'] > 0 || $r->isFullyPaid()))
                                                                                    {{-- Ruling on a proof hangs off the proof, not off a status.
                                                                                         Since payment now follows confirmation, an unreviewed receipt sits on a
                                                                                         `confirmed` booking — gating these buttons on `payment_submitted` (a state
                                                                                         only reachable by bookings made under the old pay-first order) meant a
                                                                                         host could see a new proof arrive and have no way to accept or refuse it. --}}
                                                                                    @php($canRule = $unreviewed && $r->status !== 'cancelled')

                                                                                    {{-- Only draw the row if something will actually land in it. Guarding on "is this
                                                                                         booking live" instead left a bordered strip of empty space under every
                                                                                         settled booking. --}}
                                                                                    @if($canAct || $hasProofs || $canPay || $r->owesRefund())
                                                                                        {{-- Right-aligned and wrapping inside the cell. The border-t and top padding here were
                                                                         a card-era divider; in a table row they drew a stray rule across the
                                                                         actions column. --}}
                                                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                            {{-- A booking can have several attempts — sent, rejected, sent again — and
                                                 only ever linking the newest hid the history that explains the current
                                                 state. The button opens the list. --}}
                                            @if($r->paymentSubmissions->isNotEmpty())
                                                <button type="button" wire:click="showProofs({{ $r->id }})" dusk="proof-{{ $r->id }}"
                                                        class="rounded-lg border border-ink/15 px-3 min-h-[36px] inline-flex items-center gap-2 text-[13px] font-semibold hover:border-ink/35 transition-colors">
                                                    <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                                    {{ $r->paymentSubmissions->count() }} {{ \Illuminate\Support\Str::plural('proof', $r->paymentSubmissions->count()) }}
                                                </button>
                                            @elseif($canAct)
                                                <span class="text-[13px] text-ink/45">No proof sent</span>
                                            @endif

                                                                                            @if($canRule)
                                                                                                {{-- Accept the money. Separate from confirming the seat, which happened
                                                                                                     earlier and is not a payment decision. --}}
                                                                                                <x-confirm-action
                                                                                                    action="verifyPayment({{ $r->id }})"
                                                                                                    title="Accept this payment?"
                                                                                                    body="Credits {{ $fmt($trip->payable_now * $r->party_size) }} against {{ $r->displayName() }}'s booking. Settling the rest is a separate step."
                                                                                                    confirm-label="Yes, it's in"
                                                                                                    cancel-label="Keep reviewing"
                                                                                                    tone="moss" icon="money"
                                                                                                    dusk="verify-{{ $r->id }}"
                                                                                                    class="rounded-full bg-moss text-cream px-4 min-h-[40px] inline-flex items-center text-sm font-semibold hover:bg-moss/90 transition-colors">Accept payment</x-confirm-action>

                                                                                                <input wire:model="rejectReason.{{ $r->id }}" type="text" placeholder="Why are you rejecting it? (optional)"
                                                                                                       aria-label="Reason for rejecting {{ $r->displayName() }}'s payment"
                                                                                                       class="flex-1 min-w-[12rem] rounded-xl border-ink/15 px-3.5 py-2 text-sm focus:border-moss focus:ring-moss">
                                                                                                <x-confirm-action
                                                                                                    action="reject({{ $r->id }})"
                                                                                                    title="Reject this payment?"
                                                                                                    body="{{ $r->displayName() }} will be asked to send proof again. Their seat stays theirs — only the payment is refused. They will see the reason you gave."
                                                                                                    confirm-label="Yes, reject it"
                                                                                                    cancel-label="Keep reviewing"
                                                                                                    tone="terra" icon="warning"
                                                                                                    dusk="reject-{{ $r->id }}"
                                                                                                    class="rounded-full border border-ink/15 px-4 min-h-[40px] text-sm font-semibold hover:border-terra hover:text-terra transition-colors">Reject</x-confirm-action>
                                                                                            @endif

                                                                                                                    {{--
                                                                                                Accepting the seat. On a deposit trip this credits the deposit only —
                                                                                                the balance is the separate control beside it, which is the whole point
                                                                                                of splitting them.
                                                                                            --}}
                                                                                            {{-- Removing someone. Outlined, not filled: it is the one control in this row
                                                                                                 that costs a seat, and it emails them. --}}
                                                                                            {{-- Recording a transfer that happened elsewhere, the same shape as marking
                                                                                                 a booking paid and for the same reason. --}}
                                                                                            @if($r->owesRefund())
                                                                                                <x-confirm-action
                                                                                                    action="markRefunded({{ $r->id }})"
                                                                                                    title="Mark as refunded?"
                                                                                                    body="Records that you have sent {{ $fmt((float) $r->refund_due) }} back to {{ $r->displayName() }}. Terra never held it, so this is a note rather than a transfer."
                                                                                                    confirm-label="Yes, refunded"
                                                                                                    cancel-label="Not yet"
                                                                                                    tone="moss" icon="money"
                                                                                                    dusk="mark-refunded-{{ $r->id }}"
                                                                                                    class="rounded-lg bg-moss text-cream px-3 min-h-[36px] inline-flex items-center text-[13px] font-semibold hover:bg-moss/90 transition-colors">Mark refunded</x-confirm-action>
                                                                                            @endif

                                                                                            @if($r->status !== 'cancelled')
                                                                                                <x-confirm-action
                                                                                                    action="cancelBooking({{ $r->id }})"
                                                                                                    title="Cancel this booking?"
                                                                                                    body="{{ $r->displayName() }}'s seat is released and they are emailed. Anything already paid is settled between you directly."
                                                                                                    confirm-label="Yes, cancel it"
                                                                                                    cancel-label="Keep it"
                                                                                                    tone="terra" icon="trash"
                                                                                                    dusk="cancel-booking-{{ $r->id }}"
                                                                                                    class="rounded-lg border border-ink/15 px-3 min-h-[36px] inline-flex items-center text-[13px] font-semibold text-terra/80 hover:text-terra hover:border-terra/30 transition-colors">Cancel</x-confirm-action>
                                                                                            @endif

                                                                                            @if($canAct)
                                                                                                <x-confirm-action
                                                                                                    action="confirm({{ $r->id }})"
                                                                                                    title="Confirm this booking?"
                                                                                                    body="{{ $r->status === 'payment_submitted'
                                                                                                        ? 'Accepts '.$r->displayName().'\'s seat and credits '.$fmt($trip->payable_now * $r->party_size).'. Marking the balance paid is a separate step.'
                                                                                                        : $r->displayName().' uploaded no proof. Only confirm if you have been paid another way — it accepts their seat and credits '.$fmt($trip->payable_now * $r->party_size).'.' }}"
                                                                                                    confirm-label="Yes, confirm seat"
                                                                                                    cancel-label="Not yet"
                                                                                                    tone="moss" icon="warning"
                                                                                                    dusk="confirm-{{ $r->id }}"
                                                                                                    class="rounded-full border border-pine/30 text-pine px-4 min-h-[40px] text-sm font-semibold hover:bg-pine/[.07] transition-colors">
                                                                                                    Confirm seat
                                                                                                </x-confirm-action>
                                                                                            @endif

                                                                                            {{-- Money, and its undo. Available on any live booking, including one
                                                                                                 already confirmed — that is the row a mis-tap needs correcting on. --}}
                                                                                            {{-- Nothing left to settle means nothing to offer: on a no-deposit trip a
                                                                                                 confirmed seat is already square, and "Mark fully paid" there would be
                                                                                                 a button that changes nothing a host can see. --}}
                                                                                            @if($canPay)
                                                                                                @if($r->isFullyPaid())
                                                                                                    <x-confirm-action
                                                                                                        action="unmarkFullyPaid({{ $r->id }})"
                                                                                                        title="Undo the fully-paid tag?"
                                                                                                        body="{{ $fmt($owed['total']) }} stops counting as collected for {{ $r->displayName() }}. Their seat stays confirmed — only the payment claim is withdrawn."
                                                                                                        confirm-label="Yes, undo it"
                                                                                                        cancel-label="Leave it paid"
                                                                                                        tone="terra" icon="warning"
                                                                                                        dusk="unmark-paid-{{ $r->id }}"
                                                                                                        class="rounded-full border border-ink/15 px-4 min-h-[40px] text-sm font-semibold hover:border-terra hover:text-terra transition-colors">Undo paid</x-confirm-action>
                                                                                                @else
                                                                                                    <x-confirm-action
                                                                                                        action="markFullyPaid({{ $r->id }})"
                                                                                                        title="Mark as fully paid?"
                                                                                                        body="Records that {{ $r->displayName() }} has settled the whole {{ $fmt($owed['total']) }}. You can undo this afterwards."
                                                                                                        confirm-label="Yes, fully paid"
                                                                                                        cancel-label="Not yet"
                                                                                                        tone="moss" icon="money"
                                                                                                        dusk="mark-paid-{{ $r->id }}"
                                                                                                        class="rounded-full bg-moss text-cream px-5 min-h-[40px] text-sm font-semibold hover:bg-moss/90 transition-colors">
                                                                                                            {{-- What the host is deciding about, in front of them while they decide. --}}
                                                                                                            <x-slot:details>
                                                                                                                <dl class="divide-y divide-ink/8 text-sm">
                                                                                                                    <div class="flex items-baseline justify-between gap-3 pb-2.5">
                                                                                                                        <dt class="text-ink/60">Trip total</dt>
                                                                                                                        <dd class="font-semibold tabular-nums">{{ $fmt($owed['total']) }}</dd>
                                                                                                                    </div>
                                                                                                                    <div class="flex items-baseline justify-between gap-3 py-2.5">
                                                                                                                        <dt class="text-ink/60">Recorded so far</dt>
                                                                                                                        <dd class="tabular-nums">{{ $fmt($owed['paid']) }}</dd>
                                                                                                                    </div>
                                                                                                                    <div class="flex items-baseline justify-between gap-3 py-2.5">
                                                                                                                        <dt class="font-semibold">This marks as received</dt>
                                                                                                                        <dd class="font-serif font-semibold text-base tabular-nums text-moss">{{ $fmt($owed['due']) }}</dd>
                                                                                                                    </div>
                                                                                                                    @if($r->latestPayment?->reference_no)
                                                                                                                        <div class="flex items-baseline justify-between gap-3 py-2.5">
                                                                                                                            <dt class="text-ink/60">Reference</dt>
                                                                                                                            <dd class="font-mono text-xs tabular-nums break-all text-right">{{ $r->latestPayment->reference_no }}</dd>
                                                                                                                        </div>
                                                                                                                    @endif
                                                                                                                    @if($r->latestPayment?->method)
                                                                                                                        <div class="flex items-baseline justify-between gap-3 py-2.5">
                                                                                                                            <dt class="text-ink/60">Method</dt>
                                                                                                                            <dd>{{ $r->latestPayment->method }}</dd>
                                                                                                                        </div>
                                                                                                                    @endif
                                                                                                                    @if($r->latestPayment?->submitted_at)
                                                                                                                        <div class="flex items-baseline justify-between gap-3 py-2.5">
                                                                                                                            <dt class="text-ink/60">Sent</dt>
                                                                                                                            <dd>{{ $r->latestPayment->submitted_at->format('j M Y, g:ia') }}</dd>
                                                                                                                        </div>
                                                                                                                    @endif
                                                                                                                </dl>
                                                                                                        
                                                                                                                @if($r->latestPayment?->proof_image)
                                                                                                                    {{-- The receipt itself. Deciding from a filename is not deciding. --}}
                                                                                                                    <a href="{{ route('host.proof', $r->latestPayment) }}" target="_blank" rel="noopener noreferrer"
                                                                                                                       dusk="modal-proof-{{ $r->id }}"
                                                                                                                       class="block mt-3 rounded-lg border border-ink/12 bg-white p-2">
                                                                                                                        <img src="{{ route('host.proof', $r->latestPayment) }}" alt="Proof of payment"
                                                                                                                             class="w-full h-auto max-h-56 object-contain rounded">
                                                                                                                        <span class="block text-[11px] text-ink/50 text-center mt-1.5">Tap to open full size</span>
                                                                                                                    </a>
                                                                                                                @else
                                                                                                                    <p class="mt-3 text-xs text-ink/55">No receipt was uploaded — you are recording this on your own word.</p>
                                                                                                                @endif
                                                                                                            </x-slot:details>
                                                                                                        Mark fully paid</x-confirm-action>
                                                                                                @endif
                                                                                            @endif
                                                                                        </div>
                                                            @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
        @empty
                <tbody>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center">
                            <p class="font-semibold mb-1">Nobody has booked yet</p>
                            <p class="text-ink/65 text-sm">Share the trip link, or add a walk-in with ＋ Add guest.</p>
                        </td>
                    </tr>
                </tbody>
        @endforelse
            </table>
        </div>
    </div>

    {{--
        Every payment attempt, not just the newest.

        The row used to link straight to one image. A booking that was rejected and re-sent has a
        history, and the history is what explains why it currently reads the way it does — so the
        control opens the list and each entry carries its own verdict as a badge.
    --}}
    @if($proofsFor && ($openFor = $reservations->firstWhere('id', $proofsFor)))
        <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center" dusk="proof-modal"
             role="dialog" aria-modal="true" aria-label="Payments from {{ $openFor->displayName() }}">
            <div class="absolute inset-0 bg-ink/50 backdrop-blur-[2px]" wire:click="closeProofs" aria-hidden="true"></div>

            <div class="confirm-pop relative w-full sm:max-w-lg max-h-[90dvh] flex flex-col
                        bg-surface rounded-t-3xl sm:rounded-2xl shadow-2xl sm:m-4 overflow-hidden">

                <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-ink/8 shrink-0">
                    <div class="min-w-0">
                        <h3 class="font-serif font-semibold text-lg leading-tight">Payments</h3>
                        <p class="text-sm text-ink/60 truncate">{{ $openFor->displayName() }} · {{ $fmt($openFor->money()['total']) }} total</p>
                    </div>
                    <button type="button" wire:click="closeProofs" dusk="close-proofs" aria-label="Close"
                            class="grid place-items-center w-10 h-10 rounded-full text-ink/50 hover:bg-ink/5 hover:text-ink transition-colors shrink-0">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <div class="overflow-y-auto px-5 py-4 space-y-3">
                    @foreach($openFor->paymentSubmissions->sortByDesc('submitted_at') as $sub)
                        <div class="rounded-xl border border-ink/12 p-3.5" dusk="proof-row-{{ $sub->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-semibold tabular-nums break-all">{{ $sub->reference_no ?: 'No reference' }}</p>
                                    <p class="text-xs text-ink/55 mt-0.5">
                                        {{ $sub->submitted_at?->format('j M Y, g:ia') }}
                                        @if($sub->method)<span class="text-ink/25">·</span> {{ $sub->method }}@endif
                                    </p>
                                </div>

                                @if($sub->confirmed_at)
                                    <x-badge tone="moss">Confirmed</x-badge>
                                @elseif($sub->rejected_reason)
                                    <x-badge tone="terra">Rejected</x-badge>
                                @else
                                    <x-badge tone="amber" dot>Awaiting you</x-badge>
                                @endif
                            </div>

                            @if($sub->rejected_reason)
                                <p class="text-xs text-terra mt-2">{{ $sub->rejected_reason }}</p>
                            @endif

                            @if($sub->proof_image)
                                <a href="{{ route('host.proof', $sub) }}" target="_blank" rel="noopener noreferrer"
                                   dusk="proof-image-{{ $sub->id }}"
                                   class="block mt-3 rounded-lg border border-ink/12 bg-white p-2">
                                    <img src="{{ route('host.proof', $sub) }}" alt="Proof of payment"
                                         class="w-full h-auto max-h-64 object-contain rounded" loading="lazy">
                                </a>
                            @else
                                <p class="text-xs text-ink/45 mt-2">No image attached — reference only.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
