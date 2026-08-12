@php($trip = $reservation->departure->trip)
@php($departure = $reservation->departure)
@php($host = $trip->host)
{{-- A deposit holds the place; the balance is settled with the host on the day. --}}
@php($currency = $host->hostProfile?->currency ?? 'PHP')
@php($money = fn ($n) => $currency.' '.number_format($n))
@php($perHead = $trip->current_price)
@php($amount = $trip->payable_now * $reservation->party_size)
@php($balance = ($perHead - $trip->payable_now) * $reservation->party_size)
@php($pending = $reservation->incompleteParticipants())
{{-- What is actually still owed, from the money the booking has recorded — never inferred
     from the seat being confirmed. That inference is what made a confirmed booking read as
     settled and hid the payment form from someone who still had to pay. --}}
@php($owed = $reservation->money())
@php($due = $owed['due'])
{{-- A receipt the host has not looked at yet.
     Status alone could not express this: confirming keeps a booking at `confirmed`, and an
     unverified submission credits nothing, so sending proof against a confirmed seat redrew the
     page identically and looked like the button had done nothing. --}}
@php($lastPayment = $reservation->latestPayment)
@php($awaitingReview = $lastPayment && ! $lastPayment->confirmed_at && ! $lastPayment->rejected_reason)
{{-- The deadline the host set, resolved onto this booking when the seat was granted. Null when
     the trip asks for no deadline at all, which is a third of them. --}}
@php($nextDue = $reservation->nextPaymentDue())
@php($overdue = $reservation->paymentOverdue())
@php($gallery = $this->gallery)

{{--
    Same skeleton as the join page: a header and a main column locked to `xl:w-2/3`, with the
    trip pinned beside them in a fixed third. This page used to be a `max-w-xl` ribbon down the
    middle of a 2560px screen — the booking you just made deserves the same room as the form
    that made it, and the trip you are paying for should stay on screen while you copy an
    account number into your banking app.
--}}
@php($state = $reservation->status)
{{-- "Pay your host" is no longer the default: before the host grants the seat there is nothing
     to pay, so leading with a payment instruction promised something the page cannot deliver. --}}
@php($heading = match ($state) {
    'confirmed' => $reservation->isFullyPaid() ? "You're all set" : "You're confirmed",
    'payment_submitted' => 'Waiting for '.$host->name,
    'cancelled' => 'Booking cancelled',
    default => 'Request sent',
})
<div class="min-h-[calc(100dvh-var(--nav-h))]">
    <header class="px-3 sm:px-8 2xl:px-12 pt-6 sm:pt-8 xl:w-2/3">
        {{--
            Back goes back.

            The href is the fallback, not the intent: someone who opened this page straight from an
            email has no history to return to, and a dead link is worse than a sensible guess. When
            there IS somewhere to go back to, the browser's own stack is the honest answer — a
            joiner arriving here from their dashboard wants the dashboard, not the trip page they
            never visited. The label says "Back" rather than the trip title for the same reason:
            naming a destination the button may not go to is a lie the user only finds by clicking.
        --}}
        <a href="{{ route('trips.show', $trip) }}" wire:navigate dusk="back"
           x-data
           x-on:click="if (window.history.length > 1) { $event.preventDefault(); window.history.back(); }"
           class="inline-flex items-center py-1.5 -my-1.5 text-sm text-ink/65 hover:text-ink">← Back</a>
        <h1 class="font-serif font-semibold text-3xl mt-2">{{ $heading }}</h1>
        <p class="text-ink/65">
            @if($state === 'confirmed' && $due > 0 && $nextDue)
                {{-- The one line that matters on a granted seat: what happens next, and by when. --}}
                Your seat on {{ $departure->starts_at->format('j M') }} is held.
                {{ $overdue ? 'Payment was due' : 'Pay by' }}
                <strong class="{{ $overdue ? 'text-terra' : 'text-ink' }}">{{ $nextDue->format('j M') }}, {{ $nextDue->format('g:ia') }}</strong>.
            @elseif($state === 'confirmed')
                Your seat on {{ $departure->starts_at->format('j M') }} is locked in.
            @elseif($state === 'payment_submitted')
                With {{ $host->name }} for checking.
            @else
                {{-- Nothing to pay yet. Under the old order a stranger sent money for a place
                     that might not exist; now the host grants the seat first. --}}
                {{ $host->name }} is reviewing your request. You'll pay once your seat is granted.
            @endif
        </p>
    </header>

    {{-- The trip rail. Full-height and pinned from xl, stacked above the content below that. --}}
    <aside class="px-3 sm:px-8 pt-4 xl:p-0
                  xl:fixed xl:top-[var(--nav-h)] xl:right-0 xl:z-10
                  xl:w-1/3 xl:h-[calc(100dvh-var(--nav-h))]">
        <div class="rounded-2xl border border-ink/10 bg-surface overflow-hidden shadow-sm sm:flex
                    xl:rounded-none xl:border-0 xl:border-l xl:shadow-none
                    xl:h-full xl:flex-col xl:overflow-y-auto">
            @if($gallery)
                <div class="relative aspect-[16/9] sm:aspect-auto sm:w-60 sm:shrink-0
                            xl:w-auto xl:flex-1 xl:min-h-[15rem]">
                    <x-carousel :images="$gallery" ratio="h-full" class="absolute inset-0 w-full h-full" />
                    {{-- Covers the carousel's own arrows and dots, so it must not swallow clicks. --}}
                    <div class="absolute inset-0 pointer-events-none bg-gradient-to-b from-ink/60 via-transparent to-ink/45"></div>
                    <div class="absolute inset-x-0 top-0 p-3 pr-16 flex flex-wrap gap-1.5 pointer-events-none">
                        @if($trip->place?->category)
                            <span class="rounded-full glass-dark px-2.5 py-1 text-[11px] font-semibold text-cream capitalize">{{ $trip->place->category }}</span>
                        @endif
                        @if($trip->effective_difficulty)
                            <span class="rounded-full glass-dark px-2.5 py-1 text-[11px] font-semibold text-cream capitalize">{{ $trip->effective_difficulty }}</span>
                        @endif
                    </div>
                </div>
            @endif

            <div class="p-5 sm:flex-1 sm:min-w-0 xl:flex-none">
                <h2 class="font-serif font-semibold text-xl leading-snug">{{ $trip->title }}</h2>

                <dl class="mt-4 space-y-2.5 text-sm">
                    @if($trip->place)
                        <div class="flex items-start gap-2.5">
                            <dt class="sr-only">Where</dt>
                            <x-icon.pin class="w-4 h-4 mt-0.5 text-moss shrink-0" />
                            <dd class="text-ink/75">{{ $trip->place->name }}<span class="text-ink/50"> · {{ $trip->place->country }}</span></dd>
                        </div>
                    @endif

                    <div class="flex items-start gap-2.5">
                        <dt class="sr-only">When</dt>
                        <x-icon.calendar class="w-4 h-4 mt-0.5 text-moss shrink-0" />
                        <dd class="text-ink/75">
                            <span class="font-semibold text-ink">{{ $departure->starts_at->format('D, j M Y') }}</span>
                            @if($departure->ends_at && ! $departure->ends_at->isSameDay($departure->starts_at))
                                <span class="block text-ink/60">to {{ $departure->ends_at->format('D, j M Y') }}</span>
                            @endif
                        </dd>
                    </div>

                    @if($trip->meetup_point)
                        <div class="flex items-start gap-2.5">
                            <dt class="sr-only">Meeting point</dt>
                            <x-icon.mountain class="w-4 h-4 mt-0.5 text-moss shrink-0" />
                            <dd class="text-ink/75">{{ $trip->meetup_point }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 pt-4 border-t border-ink/8">
                    {{-- One target covering the photo and the name, so the rail is a way
                         through to the host rather than a dead credit. --}}
                    <x-host-link :host="$host" size="md" class="gap-3" subtitle="Your host" />
                </div>

                {{-- The money, in the one place that stays on screen while you use your bank app. --}}
                <div class="mt-4 pt-4 border-t border-ink/8">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-ink/65">{{ $money($perHead) }} × {{ $reservation->party_size }} {{ Str::plural('seat', $reservation->party_size) }}</span>
                        <span class="font-serif font-semibold text-xl">{{ $money($perHead * $reservation->party_size) }}</span>
                    </div>
                    {{-- Always, not only on deposit trips. Whether money is outstanding is a fact
                         about this booking, not about how the host priced the trip — hiding it on
                         a no-deposit trip is what let a confirmed-but-unpaid seat look settled. --}}
                    <div class="flex items-baseline justify-between gap-3 text-sm mt-2 pt-2 border-t border-dashed border-ink/10">
                        <span class="text-ink/65">Paid so far</span>
                        <span class="font-semibold">{{ $money($owed['paid']) }}</span>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 text-sm mt-1">
                        <span class="text-ink/65">Still to pay</span>
                        <span class="font-semibold {{ $due > 0 ? 'text-terra' : 'text-moss' }}">{{ $money($due) }}</span>
                    </div>
                    @if($reservation->latestPayment?->reference_no)
                        <p class="text-xs text-ink/50 mt-3">Payment ref <span class="font-mono break-all">{{ $reservation->latestPayment->reference_no }}</span></p>
                    @endif
                </div>
            </div>
        </div>
    </aside>

    {{-- pb-28 on a phone leaves room for whichever bar is pinned to the viewport below.
         Only one of them ever renders: the checkout launcher while money is owed, the receipt
         bar once something has been sent for review. --}}
    @php($hasPinnedBar = $due > 0 || $awaitingReview)
    <div class="px-3 sm:px-8 2xl:px-12 pt-5 xl:w-2/3 space-y-4 {{ $hasPinnedBar ? 'pb-28 lg:pb-10' : 'pb-10' }}">
        {{-- Money owed back. The booking is over, so this is the only thing left on the page
             that matters to the person reading it. --}}
        @if($reservation->refund_due > 0)
            <div class="rounded-2xl border p-4 sm:p-6 {{ $reservation->refunded_at ? 'bg-moss/10 border-moss/25' : 'bg-terra/[.07] border-terra/25' }}"
                 dusk="refund-notice">
                <p class="font-serif font-semibold text-lg">
                    @if($reservation->refunded_at)
                        {{ $host->name }} has refunded {{ $money((float) $reservation->refund_due) }}
                    @else
                        {{ $money((float) $reservation->refund_due) }} is owed back to you
                    @endif
                </p>
                <p class="text-sm text-ink/70 mt-1">
                    @if($reservation->refunded_at)
                        Marked sent on {{ $reservation->refunded_at->format('j M Y') }}. It should be with you already.
                    @else
                        Terra never held your money, so {{ $host->name }} sends this back directly. Contact them if it has not arrived.
                    @endif
                </p>
            </div>
        @endif

        {{-- The confirmed card is out for now, at the host's request.

             The page header already says "You're confirmed", and what is still owed shows in
             both the trip rail and the checkout — so the card repeated all of it. The one thing
             it uniquely carried was the way into trip chat; that also lives on the dashboard. --}}
        @if($awaitingReview)
            {{--
                Same two shapes as the checkout it replaces.

                A card sitting loose in the flow on a phone was neither a status line nor
                something you could act on. Pinned, it reads as the state of the booking; tapped,
                it opens what was actually sent — which is the only question a joiner has while
                waiting: "did the right thing go through?"
            --}}
            <div x-data="{ proof: $wire.entangle('proofOpen') }"
                 x-on:keydown.escape.window="proof = false"
                 x-effect="document.body.classList.toggle('overflow-hidden', proof && window.matchMedia('(max-width: 1023px)').matches)"
                 x-on:livewire:navigating.window="document.body.classList.remove('overflow-hidden')">

                <button type="button" x-on:click="proof = true" dusk="open-proof"
                        class="lg:hidden fixed inset-x-0 bottom-0 z-30 px-4 py-3
                               bg-amber/15 backdrop-blur border-t border-amber/30 text-left
                               flex items-center gap-3">
                    <span class="grid place-items-center w-9 h-9 rounded-full bg-amber/20 text-amber shrink-0">
                        <x-icon.check class="w-5 h-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold leading-tight">Proof sent</span>
                        <span class="block text-xs text-ink/60">Waiting on {{ $host->name }} · tap to view</span>
                    </span>
                    <svg viewBox="0 0 24 24" class="w-4 h-4 text-ink/40 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 6l6 6-6 6"/></svg>
                </button>

                <div x-show="proof" x-cloak x-transition.opacity.duration.200ms
                     x-on:click="proof = false"
                     class="lg:hidden fixed inset-0 z-40 bg-ink/50" aria-hidden="true"></div>

                <div data-sheet x-bind:data-open="proof" dusk="proof-sheet"
                     class="rounded-2xl bg-amber/10 border border-amber/25 p-4 sm:p-6
                            max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-50
                            max-lg:max-h-[92dvh] max-lg:overflow-y-auto max-lg:bg-surface
                            max-lg:rounded-b-none max-lg:rounded-t-3xl max-lg:border-x-0 max-lg:border-b-0
                            max-lg:shadow-[0_-12px_40px_-16px_rgba(31,28,21,.45)]">

                    <div class="lg:hidden sticky -top-4 -mt-4 pt-3 pb-3 mb-4 bg-surface z-10 border-b border-ink/8">
                        <span class="block w-10 h-1 rounded-full bg-ink/15 mx-auto mb-3" aria-hidden="true"></span>
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-serif font-semibold text-lg leading-tight">What you sent</p>
                            <button type="button" x-on:click="proof = false" dusk="close-proof" aria-label="Close"
                                    class="grid place-items-center w-10 h-10 rounded-full text-ink/50 hover:bg-ink/5 hover:text-ink transition-colors">
                                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                    </div>

                    <p class="hidden lg:block font-serif font-semibold text-lg" dusk="proof-sent">Proof sent — waiting on {{ $host->name }}</p>

                    <dl class="divide-y divide-ink/8 lg:mt-2">
                        <div class="pb-3">
                            <dt class="text-[10px] font-semibold uppercase tracking-[0.09em] text-ink/45">Reference</dt>
                            <dd class="font-mono text-[17px] font-semibold tabular-nums tracking-tight mt-1 break-words">{{ $lastPayment?->reference_no ?: '—' }}</dd>
                        </div>
                        <div class="py-3">
                            <dt class="text-[10px] font-semibold uppercase tracking-[0.09em] text-ink/45">Sent</dt>
                            <dd class="text-[15px] mt-1">{{ $lastPayment?->submitted_at?->format('j M Y, g:ia') ?: '—' }}</dd>
                        </div>
                        @if($lastPayment?->method)
                            <div class="py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-[0.09em] text-ink/45">Method</dt>
                                <dd class="text-[15px] mt-1">{{ $lastPayment->method }}</dd>
                            </div>
                        @endif
                        @if($lastPayment?->proof_image)
                            <div class="py-3">
                                <dt class="text-[10px] font-semibold uppercase tracking-[0.09em] text-ink/45">Receipt</dt>
                                <dd class="mt-2">
                                    <a href="{{ route('reservations.proof', ['token' => $reservation->public_token]) }}"
                                       target="_blank" rel="noopener noreferrer" dusk="view-proof"
                                       class="block rounded-lg border border-ink/12 bg-white p-2 max-w-[16rem]">
                                        <img src="{{ route('reservations.proof', ['token' => $reservation->public_token]) }}"
                                             alt="The receipt you sent" class="w-full h-auto object-contain rounded">
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <p class="text-sm text-ink/60 pt-3">{{ $host->name }} will confirm this. Nothing more to do.</p>
                </div>
            </div>
        @endif

        {{-- Leads the content when heads are unnamed: the host cannot file a permit without them. --}}
        @if($pending->isNotEmpty())
            <div class="rounded-2xl bg-amber/12 border border-amber/25 p-5" dusk="pending-details">
                <p class="font-semibold mb-1">
                    {{ $pending->count() }} of {{ $reservation->participants->count() }}
                    {{ Str::plural('traveller', $reservation->participants->count()) }} still need details
                </p>
                <p class="text-sm text-ink/70">
                    Your host needs each traveller's full name and birthday for permits. Send them this
                    page's link, or add the details before the trip.
                </p>
            </div>
        @endif

        {{--
            Who this booking is for.

            A guest booking has no account, so the contact snapshot and the traveller rows are the
            only record of who is coming — which is exactly why a row showing nothing but a name
            was wrong. Everything the host will be asked for at a permit desk is on the card.
        --}}
        <div class="rounded-2xl border border-ink/10 bg-surface p-4 sm:p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 mb-4">
                <h2 class="font-serif font-semibold text-lg">Who's going</h2>
                <p class="text-sm text-ink/60">
                    {{ $reservation->party_size }} {{ Str::plural('seat', $reservation->party_size) }}
                    @unless($reservation->bookerIsTraveling()) · you are booking for others @endunless
                </p>
            </div>

            {{-- The booker is who the host calls when something changes, so they are named even
                 when they are not on the trip. --}}
            <div class="rounded-xl bg-cream/70 border border-ink/8 p-4 mb-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-ink/50 mb-1.5">Booking contact</p>
                <p class="font-semibold">{{ $reservation->contactName() ?: $reservation->displayName() }}</p>
                <dl class="mt-2 grid sm:grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
                    @if($reservation->contact_email)
                        <div class="flex gap-2 min-w-0"><dt class="text-ink/50 shrink-0">Email</dt><dd class="text-ink/80 truncate">{{ $reservation->contact_email }}</dd></div>
                    @endif
                    @if($reservation->contact_phone)
                        <div class="flex gap-2 min-w-0"><dt class="text-ink/50 shrink-0">Mobile</dt><dd class="text-ink/80">{{ $reservation->contact_phone }}</dd></div>
                    @endif
                </dl>
            </div>

            @if($reservation->participants->isNotEmpty())
                <ol class="space-y-3">
                    @foreach($reservation->participants as $p)
                        {{-- Carbon 3 returns a float from diffInYears, so an uncast age renders as
                             "34.0014y". Cast at the point of display. --}}
                        @php($age = $p->birthday ? (int) $p->birthday->diffInYears($departure->starts_at) : null)
                        <li class="rounded-xl border border-ink/10 p-4" dusk="traveller-{{ $p->id }}">
                            <div class="flex flex-wrap items-start gap-x-3 gap-y-2">
                                <span class="grid place-items-center w-7 h-7 rounded-full bg-pine/8 text-pine text-xs font-semibold tabular-nums shrink-0 mt-0.5">{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1">
                                    @if($p->isComplete())
                                        <p class="font-semibold leading-tight">
                                            {{ $p->fullName() }}
                                            @if($p->is_booker)<span class="ml-1.5 align-middle rounded-full bg-moss/12 text-moss text-[11px] font-semibold px-2 py-0.5">you</span>@endif
                                        </p>
                                    @else
                                        <p class="font-semibold leading-tight text-ink/45 italic">Details still needed</p>
                                    @endif

                                    <dl class="mt-2 grid sm:grid-cols-2 gap-x-6 gap-y-1.5 text-sm">
                                        @if($p->birthday)
                                            <div class="flex gap-2 min-w-0">
                                                <dt class="text-ink/50 shrink-0">Born</dt>
                                                <dd class="text-ink/80">{{ $p->birthday->format('j M Y') }}@if($age !== null)<span class="text-ink/50"> · {{ $age }}y</span>@endif</dd>
                                            </div>
                                        @endif
                                        @if($p->gender)
                                            <div class="flex gap-2 min-w-0"><dt class="text-ink/50 shrink-0">Gender</dt><dd class="text-ink/80 capitalize">{{ $p->gender === 'undisclosed' ? 'Prefer not to say' : $p->gender }}</dd></div>
                                        @endif
                                            @if($trip->requires_id)
                                                <div class="flex gap-2 min-w-0">
                                                    <dt class="text-ink/50 shrink-0">Photo ID</dt>
                                                    <dd>
                                                        @if($p->id_image)
                                                            <span class="text-moss font-semibold">On file</span>
                                                        @else
                                                            <span class="text-terra font-semibold" dusk="id-missing-{{ $p->id }}">Still needed</span>
                                                        @endif
                                                    </dd>
                                                </div>
                                            @endif
                                        @if($p->email)
                                            <div class="flex gap-2 min-w-0"><dt class="text-ink/50 shrink-0">Email</dt><dd class="text-ink/80 truncate">{{ $p->email }}</dd></div>
                                        @endif
                                        @if($p->phone)
                                            <div class="flex gap-2 min-w-0"><dt class="text-ink/50 shrink-0">Mobile</dt><dd class="text-ink/80">{{ $p->phone }}</dd></div>
                                        @endif
                                    </dl>
                                </div>

                                @if($this->canEdit())
                                    <div class="flex items-center gap-1.5 shrink-0 max-sm:w-full">
                                        <button type="button" wire:click="editParticipant({{ $p->id }})" dusk="edit-participant-{{ $p->id }}"
                                                class="inline-flex items-center gap-1.5 rounded-full border border-ink/15 px-3 py-1.5 text-xs font-semibold text-pine hover:border-pine/30 transition-colors">
                                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                                            Edit
                                        </button>
                                        <button type="button" wire:click="confirmRemove({{ $p->id }})" dusk="remove-participant-{{ $p->id }}"
                                                class="inline-flex items-center gap-1.5 rounded-full border border-ink/15 px-3 py-1.5 text-xs font-semibold text-terra/80 hover:text-terra hover:border-terra/30 transition-colors">
                                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                            Remove
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        {{--
            The checkout, for a seat that has actually been granted.

            `canSubmitPayment()` is the same rule the service enforces, asked once rather than
            re-derived here — a page that offers a form the write will refuse is worse than one
            that offers nothing. Before the host confirms there is nothing to pay for, so the
            block above this explains that instead.
        --}}
        @if($reservation->canSubmitPayment() && $due > 0 && ! $awaitingReview)
            {{--
                One checkout, two shapes.

                Inline from `lg`, and a bottom sheet below it. The whole thing — method picker,
                QR, reference, receipt, totals — ran to several screens on a phone, which pushed
                the booking details a joiner also came here to read off the bottom of the page.

                Deliberately not `x-teleport`: the markup stays exactly where it is in the DOM and
                only its CSS changes, so there is one set of inputs, one set of wire:model bindings
                and nothing for Livewire's DOM diffing to lose track of between renders.
            --}}
            <div x-data="{ pay: $wire.entangle('payOpen') }"
                 x-on:keydown.escape.window="pay = false"
                 {{-- Lock the page behind the sheet, but only while it is actually a sheet. --}}
                 x-effect="document.body.classList.toggle('overflow-hidden', pay && window.matchMedia('(max-width: 1023px)').matches)"
                 x-on:livewire:navigating.window="document.body.classList.remove('overflow-hidden')">

                {{-- The launcher. Pinned low on a phone so the amount and the way to pay it are
                     reachable by thumb from anywhere on the page. --}}
                <div class="lg:hidden fixed inset-x-0 bottom-0 z-30 px-3 py-3 bg-canvas/95 backdrop-blur
                            border-t border-ink/10">
                    <button type="button" x-on:click="pay = true" dusk="open-pay"
                            class="w-full rounded-full bg-pine text-cream min-h-[52px] font-semibold text-[15px]
                                   hover:bg-pine/90 transition-colors flex items-center justify-center gap-2">
                        Pay {{ $money(min($amount, $due)) }}
                    </button>
                </div>

                <div x-show="pay" x-cloak x-transition.opacity.duration.200ms
                     x-on:click="pay = false"
                     class="lg:hidden fixed inset-0 z-40 bg-ink/50" aria-hidden="true"></div>

                {{-- The sheet itself. `max-lg:` on every positioning class, so from `lg` this is an
                     ordinary card again and the transform never applies. --}}
                <div data-sheet
                     x-bind:data-open="pay"
                     x-bind:aria-hidden="window.matchMedia('(max-width: 1023px)').matches && ! pay"
                     dusk="pay-sheet"
                     class="rounded-2xl border border-ink/10 bg-surface p-4 sm:p-6
                            max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-50
                            max-lg:max-h-[92dvh] max-lg:overflow-y-auto
                            max-lg:rounded-b-none max-lg:rounded-t-3xl max-lg:border-x-0 max-lg:border-b-0
                            max-lg:shadow-[0_-12px_40px_-16px_rgba(31,28,21,.45)]">

                    {{-- Sheet chrome, phone only. --}}
                    <div class="lg:hidden sticky -top-4 -mt-4 pt-3 pb-3 mb-3 bg-surface z-10 border-b border-ink/8">
                        <span class="block w-10 h-1 rounded-full bg-ink/15 mx-auto mb-3" aria-hidden="true"></span>
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.09em] text-ink/45">Paying</p>
                                <p class="font-serif font-semibold text-lg leading-tight">{{ $money(min($amount, $due)) }}</p>
                            </div>
                            <button type="button" x-on:click="pay = false" dusk="close-pay" aria-label="Close"
                                    class="grid place-items-center w-10 h-10 rounded-full text-ink/50 hover:bg-ink/5 hover:text-ink transition-colors">
                                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                    </div>
                @if($state === 'awaiting_payment' && $reservation->latestPayment?->rejected_reason)
                    <p class="mb-4 rounded-xl bg-terra/10 text-terra text-sm px-4 py-3">
                        {{ $host->name }} couldn't verify your last payment: {{ $reservation->latestPayment->rejected_reason }}
                    </p>
                @endif

                <div class="flex flex-wrap items-baseline justify-between gap-4">
                    <div>
                        <h2 class="font-serif font-semibold text-lg">Send {{ $money(min($amount, $due)) }}</h2>
                        <p class="text-sm text-ink/65">{{ $departure->starts_at->format('j M') }} · {{ $reservation->party_size }} {{ Str::plural('guest', $reservation->party_size) }}</p>
                    </div>
                    @if($trip->takesDeposit())
                        <span class="rounded-full bg-amber/12 text-amber text-xs font-semibold px-3 py-1.5">Down payment</span>
                    @endif
                </div>

                {{-- Say what is still owed. A joiner who thinks they have paid in full and turns up
                     with no cash is a problem the host has to solve at the jump-off. --}}
                @if($trip->takesDeposit())
                    <p class="mt-3 rounded-xl border border-amber/30 bg-amber/[.07] px-4 py-3 text-sm text-ink/75">
                        {{ $money($balance) }} is due to your host on the day
                        ({{ $money($perHead) }} per person in total).
                    </p>
                @endif

                {{--
                    A checkout, not a noticeboard.

                    This was a dark panel listing every account the host owns, then two loose
                    fields underneath — nothing said "pick one", nothing carried the brand you
                    were about to open, and the method never reached the server. Now each option
                    is a radio card in its own brand colour, choosing one reveals the details for
                    that method alone, and the amount rides on the button you press.
                --}}
                <fieldset class="mt-5">
                    {{-- Everything below is deliberately restrained: white surfaces, one type scale, numbers
                         in tabular mono. A payment screen earns trust by looking like a record, not a
                         promotion — the cream tint and mixed sizes read as marketing. --}}
                    <legend class="text-[11px] font-semibold uppercase tracking-[0.09em] text-ink/45 mb-2">How are you paying?</legend>

                    @forelse($host->hostProfile?->payment_methods ?? [] as $pm)
                        @php($brand = \App\Support\PaymentBrands::resolve($pm['label'] ?? ''))
                        @php($chosen = $method === ($pm['label'] ?? ''))

                        <label class="flex items-center gap-3 rounded-xl border bg-surface p-3 mb-2 cursor-pointer transition-colors
                                      {{ $chosen ? 'border-pine ring-1 ring-pine' : 'border-ink/12 hover:border-ink/30' }}">
                            <input type="radio" wire:model.live="method" value="{{ $pm['label'] ?? '' }}"
                                   dusk="method-{{ $loop->index }}"
                                   class="w-4 h-4 shrink-0 text-pine border-ink/25 focus:ring-pine">

                            {{-- Brand tile. Wordmark in the provider's own colour rather than a
                                 grey row — this is the thing you are about to open on your phone. --}}
                            <span class="grid place-items-center w-10 h-10 rounded-lg shrink-0 text-[13px] font-bold tracking-tight"
                                  style="background: {{ $brand['color'] }}; color: {{ $brand['fg'] }};"
                                  aria-hidden="true">{{ $brand['mark'] }}</span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-[15px] font-semibold leading-tight">{{ $brand['label'] }}</span>
                                <span class="block font-mono text-[13px] tabular-nums text-ink/70 truncate mt-0.5">{{ $pm['detail'] ?? '' }}</span>
                                @if($pm['name'] ?? null)
                                    <span class="block text-[12px] text-ink/50 truncate">{{ $pm['name'] }}</span>
                                @endif
                            </span>

                            @if($pm['qr'] ?? null)
                                <span class="rounded border border-ink/15 text-ink/55 px-1.5 py-0.5 text-[10px] font-bold tracking-wide shrink-0">QR</span>
                            @endif
                        </label>

                        {{-- The selected method's details, opened out: the number to copy and the
                             code to scan, for one method rather than all of them at once. --}}
                        @if($chosen)
                            {{--
                                A light panel, not a dark one.

                                This was pine with cream text, which put the two things a joiner
                                has to read most carefully — an account number and a QR — on the
                                lowest-contrast surface on the page. Ink on a pale card reads
                                straight off a phone in daylight, which is where this is used.
                            --}}
                            <div dusk="method-detail"
                                 class="rounded-xl border border-ink/15 bg-surface p-4 mb-3
                                        flex flex-col sm:flex-row sm:items-start gap-4 sm:gap-6">
                                @if($pm['qr'] ?? null)
                                    @php($qrUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($pm['qr']))
                                    {{--
                                        Full width on a phone.

                                        A fixed 176px code inside three nested paddings left most
                                        of a 375px screen empty around the one thing being pointed
                                        at a camera. aspect-square + w-full lets it take whatever
                                        the panel gives it, and caps at a sane size from `sm`.
                                    --}}
                                    <div class="w-full sm:w-auto sm:shrink-0 sm:self-center">
                                        {{-- object-contain, never cover: cropping a QR can cut the
                                             alignment squares off and leave a code nothing will scan. --}}
                                        <a href="{{ $qrUrl }}" target="_blank" rel="noopener noreferrer"
                                           class="block rounded-lg bg-white p-2 border border-ink/12"
                                           title="Open the {{ $brand['label'] }} QR full size">
                                            <img src="{{ $qrUrl }}" alt="{{ $brand['label'] }} QR code"
                                                 class="w-full sm:w-52 h-auto object-contain rounded-lg">
                                        </a>

                                        {{-- An explicit save, because scanning a code shown on the
                                             same phone you are paying from is impossible — you save
                                             it and open it from the wallet's own gallery picker. --}}
                                        <a href="{{ $qrUrl }}" download="{{ \Illuminate\Support\Str::slug($brand['label']) }}-qr"
                                           dusk="save-qr"
                                           class="mt-2 flex items-center justify-center gap-1.5 rounded-lg border border-ink/20 bg-surface
                                                  hover:border-ink/40 min-h-[44px] text-[13px] font-semibold transition-colors">
                                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 11l5 5 5-5M5 21h14"/></svg>
                                            Save QR
                                        </a>
                                    </div>
                                @endif

                                <dl class="min-w-0 flex-1 divide-y divide-ink/8">
                                    <div class="pb-3">
                                        <dt class="text-[10px] font-semibold uppercase tracking-[0.09em] text-ink/45">Send to</dt>
                                        <dd class="font-mono text-[19px] font-semibold tabular-nums tracking-tight break-words leading-snug mt-1">{{ $pm['detail'] ?? '' }}</dd>
                                    </div>

                                    {{-- The name is the check: an e-wallet shows who you are about
                                         to pay before it moves the money, and this is what it
                                         should match. --}}
                                    @if($pm['name'] ?? null)
                                        <div class="py-3">
                                            <dt class="text-[10px] font-semibold uppercase tracking-[0.09em] text-ink/45">Account name</dt>
                                            <dd class="text-[15px] font-semibold mt-1">{{ $pm['name'] }}</dd>
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-2 pt-3">
                                        <button type="button"
                                                x-data="{ copied: false }"
                                                x-on:click="navigator.clipboard.writeText(@js($pm['detail'] ?? '')).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-ink/20 bg-surface hover:border-ink/40 px-3 min-h-[36px] text-[13px] font-semibold transition-colors">
                                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                                            <span x-text="copied ? 'Copied' : 'Copy number'">Copy number</span>
                                        </button>
                                        @if($pm['name'] ?? null)
                                            <button type="button"
                                                    x-data="{ copied: false }"
                                                    x-on:click="navigator.clipboard.writeText(@js($pm['name'])).then(() => { copied = true; setTimeout(() => copied = false, 1600) })"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-ink/20 bg-surface hover:border-ink/40 px-3 min-h-[36px] text-[13px] font-semibold transition-colors">
                                                <span x-text="copied ? 'Copied' : 'Copy name'">Copy name</span>
                                            </button>
                                        @endif
                                    </div>
                                </dl>
                            </div>
                        @endif
                    @empty
                        <p class="rounded-2xl border border-ink/12 p-4 text-sm text-ink/70">
                            {{ $host->name }} has not listed any payment details. Ask them in the trip chat.
                        </p>
                    @endforelse
                </fieldset>

                <form wire:submit="submit" class="mt-5">
                    <div class="grid sm:grid-cols-2 gap-x-5 gap-y-4">
                        <label class="block">
                            <span class="field-label">Reference number</span>
                            <input wire:model="reference_no" id="reference_no" name="reference_no" dusk="reference_no" class="field font-mono" placeholder="e.g. 9021 5567 3382">
                            @error('reference_no')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        {{-- The browser's own file control, with its grey button and "No file
                             chosen", is the one piece of chrome on this page nobody designed. The
                             shared dropzone keeps the real input in the DOM (sr-only), so Livewire's
                             upload plumbing and no-JS behaviour are untouched. --}}
                        <div>
                            <x-upload wire:model="proof" dusk="proof" :multiple="false"
                                      label="Screenshot"
                                      prompt="Add a receipt"
                                      sub="Optional" />
                            @error('proof')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @error('method')<p class="text-terra text-xs mt-2">{{ $message }}</p>@enderror

                    {{-- The order total, then the button that acts on it — the shape every
                         checkout uses, so the amount is never a surprise at the moment of pressing. --}}
                    <dl class="mt-5 pt-4 border-t border-ink/8 space-y-1.5 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink/65">{{ $money($perHead) }} × {{ $reservation->party_size }} {{ Str::plural('seat', $reservation->party_size) }}</dt>
                            <dd class="tabular-nums">{{ $money($owed['total']) }}</dd>
                        </div>
                        @if($owed['paid'] > 0)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-ink/65">Already paid</dt>
                                <dd class="tabular-nums text-moss">− {{ $money($owed['paid']) }}</dd>
                            </div>
                        @endif
                        <div class="flex items-baseline justify-between gap-3 pt-1.5 border-t border-dashed border-ink/10">
                            <dt class="font-semibold">Sending now</dt>
                            <dd class="font-serif font-bold text-xl tabular-nums">{{ $money(min($amount, $due)) }}</dd>
                        </div>
                    </dl>

                    {{-- A hook, not a label: the copy carries the amount now, so anything selecting
                         this button by its words breaks the next time the price changes. --}}
                    <button dusk="submit-proof"
                            class="mt-4 w-full rounded-full bg-pine text-cream min-h-[52px] font-semibold text-[15px] hover:bg-pine/90 transition-colors
                                   flex items-center justify-center gap-2"
                            wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">Submit payment</span>
                        <span wire:loading wire:target="submit">Sending…</span>
                    </button>
                </form>
                </div>
            </div>
        @endif

        {{--
            ═══ THE PASS ═══

            The pass itself, not a door to it.

            It was a banner saying "your check-in pass is ready" that led somewhere else. That is a
            hop for the one thing on this page a joiner opens under time pressure, standing at a
            trailhead with a host waiting — and the banner said everything about the pass except the
            code, which is the only part that does anything.

            The dedicated /pass route stays: it prints cleanly and it is a link people save. Same
            component, so the two cannot drift.
        --}}
        @php($passes = $reservation->participants->filter(fn ($p) => $p->pass_code)->values())
        @if($passes->isNotEmpty())
            <section class="mb-6" dusk="pass-section">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h2 class="marker text-[11px] font-semibold text-ink/70">
                        {{ $passes->count() > 1 ? 'Check-in passes' : 'Your check-in pass' }}
                    </h2>
                    <a href="{{ route('reservations.pass', ['token' => $reservation->public_token]) }}" wire:navigate
                       dusk="open-pass"
                       class="inline-flex items-center gap-2 rounded-full border border-ink/20 px-4 min-h-[44px] text-sm font-semibold hover:border-ink/40 transition-colors">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4v-6h16v6h-2M8 14h8v7H8z"/></svg>
                        Print {{ $passes->count() > 1 ? 'all' : '' }}
                    </a>
                </div>

                {{-- One per traveller. Side by side from `sm` when there are several, because a
                     party of four should not be four screens of scrolling to reach the last code. --}}
                <div class="grid gap-4 {{ $passes->count() > 1 ? 'sm:grid-cols-2' : '' }}">
                    @foreach($passes as $p)
                        <x-check-in-pass :participant="$p" :reservation="$reservation" :compact="$passes->count() > 1" />
                    @endforeach
                </div>

                <p class="text-xs text-ink/70 mt-3">
                    Screenshot or print {{ $passes->count() > 1 ? 'them' : 'it' }} — the trailhead may have no signal.
                </p>
            </section>
        @endif

        <div class="grid sm:grid-cols-2 gap-4">
            {{-- The booking's own address. With no mail provider configured this is the only way back. --}}
            <div class="rounded-2xl bg-moss/10 border border-moss/20 p-5">
                <p class="text-sm font-semibold mb-1.5">Save this link</p>
                <p class="text-sm text-ink/70 mb-3">How you get back to this booking.</p>
                <input readonly value="{{ route('reservations.show', ['token' => $reservation->public_token]) }}"
                       dusk="booking-link" aria-label="Link to this booking"
                       class="w-full rounded-lg border border-ink/15 px-3 py-2 text-sm font-mono bg-cream"
                       onclick="this.select()">
            </div>

            @unless(in_array($state, ['confirmed', 'cancelled'], true))
                <div class="rounded-2xl border border-ink/10 p-5 flex flex-col">
                    <p class="text-sm font-semibold mb-1.5">Changed your mind?</p>
                    <p class="text-sm text-ink/70 mb-3">Your seat is released straight away.</p>
                    {{-- Was a bare one-click link that released the seat with no way back. --}}
                    <x-confirm-action
                        action="cancel"
                        title="Cancel this reservation?"
                        body="Your seat is released straight away and someone else can take it. If the trip fills up you will not get it back."
                        confirm-label="Yes, cancel it"
                        cancel-label="Keep my seat"
                        tone="terra" icon="warning"
                        dusk="cancel-reservation"
                        class="mt-auto w-full text-center text-sm font-semibold text-terra rounded-full border border-terra/25 min-h-[44px] inline-flex items-center justify-center hover:bg-terra/5 transition-colors">Cancel reservation</x-confirm-action>
                </div>
            @endunless
        </div>
    </div>

    @if($showEditModal)
        {{--
            Solid, not glass, and wide enough for its own contents.

            It was `bg-cream/90` over a blur, at `sm:max-w-xl` — a 576px box holding a three-column
            name row and a four-across gender picker, so the controls ran past the panel edge and
            the text behind it showed through the labels. Opaque surface, `max-w-3xl`, and the
            gender picker wraps into a grid instead of fighting for one line.
        --}}
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 lg:p-6" dusk="edit-modal">
            <div class="absolute inset-0 bg-ink/50" wire:click="closeModal"></div>
            <div class="relative bg-surface w-full h-full sm:h-auto sm:max-h-[calc(100dvh-3rem)]
                        sm:rounded-2xl sm:border sm:border-ink/10 sm:shadow-2xl sm:max-w-3xl
                        flex flex-col overflow-hidden">
                <div class="flex items-center justify-between gap-4 px-5 sm:px-7 py-4 border-b border-ink/8 shrink-0">
                    <div class="min-w-0">
                        <h3 class="font-serif font-semibold text-lg leading-tight">Edit traveller</h3>
                        <p class="text-sm text-ink/60">Your host needs the name and birthday exactly as they appear on an ID.</p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-ink/40 hover:text-ink/70 p-2 -m-2 rounded-lg hover:bg-ink/5 transition-colors shrink-0" aria-label="Close">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {{-- Only the fields scroll, so the actions never drift off a short screen. --}}
                <div class="flex-1 overflow-y-auto px-5 sm:px-7 py-5 space-y-4">
                    <div class="grid sm:grid-cols-3 gap-3">
                        <label class="block"><span class="field-label">First name</span>
                            <input wire:model="edit_first_name" dusk="edit-first-name" class="field">
                            @error('edit_first_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        <label class="block"><span class="field-label">Middle name <span class="text-ink/45">optional</span></span>
                            <input wire:model="edit_middle_name" class="field">
                        </label>
                        <label class="block"><span class="field-label">Last name</span>
                            <input wire:model="edit_last_name" dusk="edit-last-name" class="field">
                            @error('edit_last_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="block"><span class="field-label">Email <span class="text-ink/45">optional</span></span>
                            <input type="email" wire:model="edit_email" dusk="edit-email" class="field">
                            @error('edit_email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        <label class="block"><span class="field-label">Mobile <span class="text-ink/45">optional</span></span>
                            <input type="tel" wire:model="edit_phone" dusk="edit-phone" class="field">
                        </label>
                    </div>

                    @if($trip->requires_id)
                        {{-- Only when the trip asks for one. A beach day has no permit desk, and
                             a field that requests a stranger's passport for nothing is a cost
                             with no benefit to anyone. --}}
                        @php($editing = $reservation->participants->firstWhere('id', $editingId))
                        <div class="rounded-xl border border-ink/12 p-3.5">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.09em] text-ink/45 mb-2">Photo ID</p>

                            @if($editing?->id_image)
                                <a href="{{ route('participants.id', ['participant' => $editing, 'token' => $reservation->public_token]) }}"
                                   target="_blank" rel="noopener noreferrer" dusk="view-id"
                                   class="inline-flex items-center gap-2 text-sm font-semibold text-moss hover:underline mb-3">
                                    View the one on file ↗
                                </a>
                            @endif

                            <x-upload wire:model="edit_id" dusk="edit-id" :multiple="false" label=""
                                      prompt="{{ $editing?->id_image ? 'Replace the ID' : 'Add a photo ID' }}"
                                      sub="Passport, licence or any government ID"
                                      hint="Only your host sees this." />
                            @error('edit_id')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="block"><span class="field-label">Birthday</span>
                            <input type="date" max="{{ now()->toDateString() }}" wire:model="edit_birthday" dusk="edit-birthday" class="field">
                            @error('edit_birthday')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        <div class="block"><span class="field-label">Gender</span>
                            {{-- Two across. "Prefer not to say" cannot share a row with three
                                 siblings without either truncating or pushing the group wider
                                 than the panel — which is what it was doing. --}}
                            <div class="grid grid-cols-2 gap-2">
                                @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'undisclosed' => 'Prefer not to say'] as $value => $label)
                                    <button type="button" wire:click="$set('edit_gender', '{{ $value }}')"
                                            class="rounded-xl border px-3 min-h-[44px] text-sm font-semibold transition-colors
                                                   {{ $edit_gender === $value ? 'bg-pine text-cream border-pine' : 'border-ink/15 hover:border-ink/30' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                            @error('edit_gender')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 px-5 sm:px-7 py-4 border-t border-ink/8 bg-cream/60 shrink-0">
                    <button type="button" wire:click="closeModal" dusk="cancel-edit"
                            class="rounded-full border border-ink/20 px-5 min-h-[44px] text-sm font-semibold hover:border-ink/40 transition-colors">
                        Cancel
                    </button>
                    <button type="button" wire:click="updateParticipant" wire:loading.attr="disabled" dusk="update-participant"
                            class="rounded-full bg-pine text-cream px-6 min-h-[44px] text-sm font-semibold hover:bg-pine/90 transition-colors">
                        Save
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($removingId)
        <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" dusk="remove-confirm-modal">
            <div class="absolute inset-0 bg-ink/50" wire:click="cancelRemove"></div>
            <div class="relative bg-surface w-full sm:rounded-2xl sm:border sm:border-ink/10 sm:shadow-2xl sm:max-w-md flex flex-col">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-terra/10 text-terra grid place-items-center shrink-0">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        </div>
                        <div>
                            <h3 class="font-serif font-semibold text-base">Remove traveller</h3>
                            <p class="text-sm text-ink/65">This action cannot be undone.</p>
                        </div>
                    </div>
                    @if($removingLast)
                        <p class="text-sm text-ink/70 mb-5">
                            Removing <b>{{ $removingName }}</b> is your last traveller. This will also
                            <b class="text-terra">cancel the entire booking</b> and release the seat.
                        </p>
                    @else
                        <p class="text-sm text-ink/70 mb-5">
                            Remove <b>{{ $removingName }}</b> from this booking? The seat will be freed.
                        </p>
                    @endif
                    <div class="flex items-center justify-end gap-2 pt-4 border-t border-ink/8">
                        <button type="button" wire:click="cancelRemove" dusk="cancel-remove"
                                class="rounded-full border border-ink/20 px-5 min-h-[44px] text-sm font-semibold hover:border-ink/40 transition-colors">
                            Keep traveller
                        </button>
                        <button type="button" wire:click="removeParticipant({{ $removingId }})" wire:loading.attr="disabled" dusk="confirm-remove"
                                class="rounded-full bg-terra text-cream px-5 min-h-[44px] text-sm font-semibold hover:bg-terra/90 transition-colors">
                            Yes, remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
