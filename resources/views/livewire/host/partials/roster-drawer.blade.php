@php
    $r = $openBooking;
    $owed = $r?->money() ?? ['total' => 0, 'paid' => 0, 'due' => 0];
    $unreviewed = $r && $r->latestPayment && ! $r->latestPayment->confirmed_at && ! $r->latestPayment->rejected_reason;
    $canRule = $r && $unreviewed && $r->status !== 'cancelled';
    $canAct = $r && in_array($r->status, ['pending', 'awaiting_payment', 'payment_submitted'], true);
    $canPay = $r && $r->status !== 'cancelled' && ($owed['due'] > 0 || $r->isFullyPaid());
@endphp

{{--
    The workspace.

    Everything the row shed lives here: the traveller detail, the proofs, the reject reason, and
    every action this booking's state allows. A side panel rather than a modal so a run of proofs
    can be worked without losing your place in the list; a bottom sheet on a phone.
--}}
@if($r)
    <div class="fixed inset-0 z-40 xl:hidden" x-data x-on:keydown.escape.window="$wire.closeBooking()">
        <div class="absolute inset-0 bg-ink/50" wire:click="closeBooking" aria-hidden="true"></div>
    </div>
@endif

<aside
    @class([
        'min-w-0',
        'fixed inset-x-0 bottom-0 z-50 max-h-[85dvh] overflow-y-auto rounded-t-3xl xl:rounded-2xl' => (bool) $r,
        'xl:static xl:z-auto xl:max-h-none xl:overflow-visible' => (bool) $r,
        'hidden xl:block' => ! $r,
    ])
    @if($r) dusk="roster-drawer" role="dialog" aria-label="Booking detail" @endif>

    <div class="border border-ink/10 bg-surface {{ $r ? 'rounded-t-3xl xl:rounded-2xl' : 'rounded-2xl' }} xl:sticky xl:top-4">
        @if(! $r)
            <div class="p-6 text-center">
                <p class="text-sm font-semibold">No booking selected</p>
                <p class="text-xs text-ink/70 mt-1">Pick someone from the list to see their details and act on them.</p>
            </div>
        @else
            <div class="flex items-start justify-between gap-3 p-4 border-b border-ink/8">
                <div class="min-w-0">
                    <p class="font-semibold text-lg leading-tight truncate">{{ $r->participants->first()?->fullName() ?: $r->displayName() }}</p>
                    <p class="text-xs text-ink/70">
                        {{ $r->party_size }} {{ \Illuminate\Support\Str::plural('seat', $r->party_size) }}
                        @if($r->confirmed_at) · Confirmed {{ $r->confirmed_at->format('j M Y') }} @endif
                    </p>
                </div>
                <button type="button" wire:click="closeBooking" dusk="close-drawer" aria-label="Close"
                        class="grid place-items-center w-11 h-11 -mr-2 -mt-2 rounded-lg text-ink/65 hover:text-ink hover:bg-ink/5 shrink-0">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            {{-- Money, spelled out once rather than split across two columns of a table row. --}}
            <dl class="grid grid-cols-3 gap-2 p-4 border-b border-ink/8 text-center">
                @foreach([['Total', $owed['total'], ''], ['Paid', $owed['paid'], 'text-moss'], ['Due', $owed['due'], $owed['due'] > 0 ? 'text-terra-deep' : 'text-ink/70']] as [$label, $value, $tone])
                    <div>
                        <dt class="text-[10px] uppercase tracking-[0.09em] font-semibold text-ink/70">{{ $label }}</dt>
                        <dd class="text-sm font-semibold tabular-nums {{ $tone }}">{{ $fmt($value) }}</dd>
                    </div>
                @endforeach
            </dl>

            @if($r->paymentOverdue() || $r->nextPaymentDue())
                <p class="px-4 py-2.5 text-xs border-b border-ink/8 {{ $r->paymentOverdue() ? 'text-terra-deep font-semibold' : 'text-ink/70' }}">
                    {{ $r->paymentOverdue() ? $r->overdueLabel().' — due '.$r->nextPaymentDue()->diffForHumans() : 'Due '.$r->nextPaymentDue()->format('j M') }}
                </p>
            @endif

            {{-- Whoever made the booking and how to reach them.

                 Rendered from the booking rather than from its participants, because a manual or
                 legacy booking can have no participant rows at all — and "who do I ring about
                 this seat" is the question this panel exists to answer. --}}
            <div class="p-4 border-b border-ink/8">
                <p class="text-[10px] uppercase tracking-[0.09em] font-semibold text-ink/70 mb-1">Booked by</p>
                <p class="text-sm font-semibold">{{ $r->displayName() }}</p>
                <p class="text-xs text-ink/70">
                    {{ $r->contact_email ?: $r->joiner?->email ?: 'no email' }}
                    · {{ $r->contact_phone ?: $r->joiner?->phone ?: 'no number' }}
                </p>
            </div>

            {{-- Who is actually travelling, with the details a permit desk asks for. --}}
            <div class="p-4 border-b border-ink/8">
                <p class="text-[10px] uppercase tracking-[0.09em] font-semibold text-ink/70 mb-2">Travelling</p>
                <ul class="space-y-3">
                    @foreach($r->participants as $p)
                        <li class="text-sm">
                            <p class="font-semibold">{{ $p->fullName() ?: 'Seat '.($loop->index + 1) }}
                                @if($p->birthday)<span class="font-normal text-ink/70"> · {{ (int) $p->birthday->diffInYears(now()) }}y</span>@endif
                                @if($p->gender)<span class="font-normal text-ink/70"> · {{ ucfirst($p->gender) }}</span>@endif
                            </p>
                            <p class="text-xs text-ink/70">{{ $p->email ?: $r->contact_email }} · {{ $p->phone ?: $r->contact_phone ?: 'no number' }}</p>

                            @if($p->hasEmergencyContact())
                                <p class="text-xs text-ink/70">In an emergency: {{ $p->emergency_contact_name }} · {{ $p->emergency_contact_phone }}</p>
                            @else
                                <p class="text-xs text-terra font-semibold">No emergency contact</p>
                            @endif

                            @if($trip->requires_id)
                                @if($p->id_image)
                                    <a href="{{ route('participants.id', $p) }}" target="_blank" dusk="roster-id-{{ $p->id }}"
                                       class="inline-flex items-center min-h-[44px] text-xs font-semibold text-pine hover:underline">View ID</a>
                                @else
                                    <span class="inline-block text-xs font-semibold text-terra" dusk="roster-id-missing-{{ $p->id }}">No ID uploaded</span>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            {{--
                ═══ ACTIONS ═══

                Grouped by consequence, not source order. The screenshot that prompted this said
                it plainly: five same-weight buttons in a column read as one undifferentiated
                list, and "Reject payment" sitting directly under "Mark fully paid" in the same
                pale outline made the two look like a matched pair — one rejects the proof in
                front of you, the other overrides the whole booking regardless of it. Nothing in
                their old styling said so.

                Three bands now, each with its own tint and a label where the grouping is not
                obvious on its own: reviewing what was sent (moss — affirmative), overriding the
                booking's tag by hand (amber — the host's own word, not a receipt), and
                undoing the booking (terra — refund, cancel).
            --}}
            <div class="p-4 space-y-4">
                @if($r->fully_paid_at)
                    <p class="text-xs text-moss font-semibold" dusk="paid-date-{{ $r->id }}">
                        Paid in full {{ $r->fully_paid_at->format('j M Y') }}
                    </p>
                @endif

                @if($r->paymentSubmissions->isNotEmpty())
                    <button type="button" wire:click="showProofs({{ $r->id }})" dusk="modal-proof-{{ $r->id }}"
                            class="w-full rounded-xl border border-ink/15 min-h-[44px] inline-flex items-center justify-center gap-2 text-sm font-semibold hover:border-ink/35 transition-colors">
                        <svg viewBox="0 0 24 24" class="w-4 h-4 text-moss" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        {{ $r->paymentSubmissions->count() }} {{ \Illuminate\Support\Str::plural('proof', $r->paymentSubmissions->count()) }}
                    </button>
                @endif

                @if($canAct)
                    <x-confirm-action
                        action="confirm({{ $r->id }})"
                        title="Confirm this booking?"
                        body="{{ $r->paymentSubmissions->isNotEmpty()
                            ? $r->displayName().' gets the seat and can then pay. Marking money received is a separate step.'
                            : $r->displayName().' uploaded no proof. Only confirm if you have agreed the seat another way — it does not credit any money.' }}"
                        confirm-label="Yes, confirm seat"
                        cancel-label="Not yet"
                        tone="moss" icon="warning"
                        dusk="confirm-{{ $r->id }}"
                        class="w-full rounded-xl bg-pine text-cream min-h-[44px] inline-flex items-center justify-center text-sm font-semibold hover:bg-pine/90 transition-colors">Confirm seat</x-confirm-action>
                @endif

                @if($canRule)
                    {{--
                        Band 1 — ruling on what was actually sent. Moss: this is the affirmative,
                        evidence-backed path, and it is the button a host reaches for most.
                    --}}
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.09em] font-semibold text-ink/70 mb-1.5">This payment</p>

                        {{--
                            A form, not a yes/no. A confirmation dialog could only ever say "the
                            whole thing arrived", which is wrong twice over: joiners send odd
                            amounts, and a booking is often a group of friends where two have paid
                            and two have not.
                        --}}
                        @if($payOpen)
                            <div class="rounded-xl border border-moss/40 bg-moss/[.05] p-3 space-y-3" dusk="pay-form">
                                <label class="block">
                                    <span class="field-label">How much arrived?</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-ink/70 shrink-0">{{ $currency }}</span>
                                        <input wire:model="payAmount" type="text" inputmode="decimal" dusk="pay-amount"
                                               class="field flex-1 min-w-0 @error('payAmount') !border-terra @enderror">
                                    </div>
                                    @error('payAmount')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                                    <p class="text-xs text-ink/70 mt-1">{{ $fmt($owed['due']) }} still owed on this booking.</p>
                                </label>

                                @if($r->participants->isNotEmpty())
                                    <fieldset>
                                        <legend class="field-label">Whose share does this cover?</legend>
                                        <div class="space-y-1 mt-1">
                                            @foreach($r->participants as $p)
                                                @php($alreadyPaid = $r->participantIsPaid($p))
                                                <label class="flex items-center gap-2.5 min-h-[44px] text-sm {{ $alreadyPaid ? 'text-ink/65' : '' }}">
                                                    <input type="checkbox" wire:model="payFor" value="{{ $p->id }}"
                                                           dusk="pay-for-{{ $p->id }}" @disabled($alreadyPaid)
                                                           class="rounded border-ink/30 text-moss focus:ring-moss w-5 h-5">
                                                    <span class="min-w-0 truncate">
                                                        {{ $p->fullName() ?: 'Seat '.($loop->index + 1) }}
                                                        @if($alreadyPaid)<span class="text-xs">· already settled</span>@endif
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <p class="text-xs text-ink/70">Tick nobody to record it against the whole booking.</p>
                                    </fieldset>
                                @endif

                                <div class="flex gap-2">
                                    <button wire:click="verifyPayment({{ $r->id }})" dusk="pay-confirm"
                                            class="flex-1 rounded-xl bg-moss text-cream min-h-[44px] text-sm font-semibold hover:bg-moss/90 transition-colors">Record it</button>
                                    <button wire:click="closePayment" dusk="pay-cancel"
                                            class="rounded-xl border border-ink/15 px-4 min-h-[44px] text-sm font-semibold hover:border-ink/35 transition-colors">Cancel</button>
                                </div>
                            </div>
                        @else
                            <div class="space-y-2">
                                <button wire:click="openPayment({{ $r->id }})" dusk="verify-{{ $r->id }}"
                                        class="w-full rounded-xl bg-moss text-cream min-h-[44px] inline-flex items-center justify-center text-sm font-semibold hover:bg-moss/90 transition-colors">Accept payment</button>

                                {{-- Rejecting is its own small unit — the reason field belongs to the
                                     button beneath it, not floating loose above two unrelated
                                     buttons the way it did before. A hairline border draws the two
                                     together and off everything else. --}}
                                <div class="rounded-xl border border-terra/25 bg-terra/[.03] p-2.5 space-y-2">
                                    <input wire:model="rejectReason.{{ $r->id }}" type="text"
                                           placeholder="Why are you rejecting it? (optional)"
                                           aria-label="Reason for rejecting {{ $r->displayName() }}'s payment"
                                           class="w-full rounded-lg border-ink/15 bg-surface px-3.5 min-h-[44px] text-sm focus:border-terra focus:ring-terra">
                                    <x-confirm-action
                                        action="reject({{ $r->id }})"
                                        title="Reject this payment?"
                                        body="{{ $r->displayName() }} is asked to send proof again. Their seat stays theirs — only the payment is refused."
                                        confirm-label="Yes, reject it"
                                        cancel-label="Keep reviewing"
                                        tone="terra" icon="warning"
                                        dusk="reject-{{ $r->id }}"
                                        class="w-full rounded-lg min-h-[40px] inline-flex items-center justify-center text-sm font-semibold text-terra hover:bg-terra/10 transition-colors">Reject payment</x-confirm-action>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                @if($canPay)
                    {{--
                        Band 2 — the host's own word, overriding whatever a receipt does or does
                        not say. Amber, deliberately not moss: this is not "I looked at the proof
                        and it checks out", it is "I am telling you this is settled" — cash at the
                        jump-off, a transfer the host saw in their own banking app. A different
                        colour for a different kind of claim.
                    --}}
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.09em] font-semibold text-ink/70 mb-1.5">Override</p>
                        @if($r->isFullyPaid())
                            <button wire:click="unmarkFullyPaid({{ $r->id }})" dusk="unmark-paid-{{ $r->id }}"
                                    class="w-full rounded-xl border border-ink/15 min-h-[44px] text-sm font-semibold hover:border-ink/35 transition-colors">Undo paid in full</button>
                        @else
                            <x-confirm-action
                                action="markFullyPaid({{ $r->id }})"
                                title="Mark this booking paid in full?"
                                body="This marks as received the whole {{ $fmt($owed['total']) }}, and issues a check-in pass for every traveller on the booking. {{ $r->latestPayment
                                    ? 'Their latest receipt: '.($r->latestPayment->method ?: 'payment').' '.($r->latestPayment->reference_no ?: '').'.'
                                    : 'No receipt was uploaded — this is your word for it.' }}"
                                confirm-label="Yes, it's all in"
                                cancel-label="Not yet"
                                tone="moss" icon="money"
                                dusk="mark-paid-{{ $r->id }}"
                                class="w-full rounded-xl border border-amber/40 bg-amber/[.06] text-amber-deep min-h-[44px] inline-flex items-center justify-center text-sm font-semibold hover:bg-amber/[.12] transition-colors">Mark fully paid</x-confirm-action>
                        @endif
                    </div>
                @endif

                @if($r->owesRefund() || $r->status !== 'cancelled')
                    {{-- Band 3 — undoing the booking. Nothing here is reachable by accident: no
                         fill, terra text only, and set apart by the rule above it. --}}
                    <div class="pt-3 border-t border-ink/8 space-y-2">
                        @if($r->owesRefund())
                            <x-confirm-action
                                action="markRefunded({{ $r->id }})"
                                title="Mark this refund sent?"
                                body="Records {{ $fmt($r->refund_due) }} as returned to {{ $r->displayName() }}. Terra does not move the money — this is your record that you did."
                                confirm-label="Yes, refunded"
                                cancel-label="Not yet"
                                tone="moss" icon="money"
                                dusk="mark-refunded-{{ $r->id }}"
                                class="w-full rounded-xl bg-moss text-cream min-h-[44px] inline-flex items-center justify-center text-sm font-semibold hover:bg-moss/90 transition-colors">Mark refunded</x-confirm-action>
                        @endif

                        @if($r->status !== 'cancelled')
                            <x-confirm-action
                                action="cancelBooking({{ $r->id }})"
                                title="Cancel this booking?"
                                body="The seat is released and {{ $r->displayName() }} is emailed. Anything they paid is listed as owed back."
                                confirm-label="Yes, cancel it"
                                cancel-label="Keep it"
                                tone="terra" icon="warning"
                                dusk="cancel-booking-{{ $r->id }}"
                                class="w-full rounded-xl min-h-[44px] inline-flex items-center justify-center text-sm font-semibold text-terra hover:bg-terra/5 transition-colors">Cancel booking</x-confirm-action>
                        @endif
                    </div>
                @endif

                @if($r->paymentSubmissions->isEmpty())
                    <p class="text-xs text-ink/70">No proof sent — this booking has no receipt attached.</p>
                @endif
            </div>
        @endif
    </div>
</aside>
