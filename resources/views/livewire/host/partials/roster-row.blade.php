@php
    $owed = $r->money();
    $people = $r->participants->isNotEmpty() ? $r->participants : collect([null]);
    $lead = $r->participants->first();
    $unreviewed = $r->latestPayment && ! $r->latestPayment->confirmed_at && ! $r->latestPayment->rejected_reason;
    $isOpen = $openBooking && $openBooking->id === $r->id;

    $state = match (true) {
        $r->status === 'cancelled' => ['Cancelled', 'ink'],
        $r->owesRefund() => ['Refund owed', 'terra'],
        $r->isFullyPaid() || $owed['due'] <= 0 => ['Fully paid', 'moss'],
        $owed['paid'] > 0 => ['Part paid', 'amber'],
        $r->status !== 'confirmed' => ['Not confirmed', 'ink'],
        default => ['Unpaid', 'ink'],
    };
@endphp

{{--
    One row, one shape, every state.

    Four cells that never change. Whatever this booking's state allows is in the drawer, which is
    why a cancelled row and a row with a proof waiting are the same height.
--}}
<li dusk="reservation-{{ $r->id }}">
    <div class="sm:grid sm:gap-3 sm:items-center px-4 py-3 sm:min-h-[4.75rem]
                {{ $mode === 'checkin' ? 'sm:grid-cols-[1fr_11rem_6rem]' : 'sm:grid-cols-[1fr_12rem_8.5rem_2.75rem]' }}
                {{ $isOpen ? 'bg-moss/[.07]' : '' }}">

        {{-- Who. The traveller, not the booker — this is a list of who is coming. --}}
        <div class="min-w-0 flex items-center gap-3">
            <span class="grid place-items-center w-9 h-9 rounded-full bg-pine/10 text-pine text-[11px] font-bold shrink-0">
                {{ strtoupper(mb_substr($lead?->first_name ?: $r->displayName(), 0, 1).mb_substr($lead?->last_name ?: '', 0, 1)) }}
            </span>
            <span class="min-w-0">
                @if($mode === 'manifest' && $r->participants->count() > 1)
                    @foreach($r->participants as $p)
                        <span class="block font-semibold text-sm truncate">
                            {{ $p->fullName() ?: 'Seat '.($loop->index + 1) }}
                            @if($p->birthday)<span class="font-normal text-ink/70">· {{ (int) $p->birthday->diffInYears($departure->starts_at) }}y</span>@endif
                            @if($p->gender)<span class="font-normal text-ink/70">· {{ ucfirst($p->gender) }}</span>@endif
                            @unless($p->isComplete())<span class="font-normal text-amber">· Details still needed</span>@endunless
                        </span>
                    @endforeach
                    <span class="block text-xs text-ink/70 truncate">Booked by {{ $r->displayName() }}</span>
                @else
                <span class="block font-semibold text-sm truncate">
                    {{ $lead?->fullName() ?: $r->displayName() }}
                    @if($mode === 'manifest' && $lead?->birthday)<span class="font-normal text-ink/70">· {{ (int) $lead->birthday->diffInYears($departure->starts_at) }}y</span>@endif
                    @if($mode === 'manifest' && $lead?->gender)<span class="font-normal text-ink/70">· {{ ucfirst($lead->gender) }}</span>@endif
                    @if($mode === 'manifest' && $lead && ! $lead->isComplete())<span class="font-normal text-amber">· Details still needed</span>@endif
                </span>
                <span class="block text-xs text-ink/70 truncate">
                    @if($r->party_size > 1)
                        {{ $r->party_size }} seats
                        @if($lead && $lead->fullName() !== $r->displayName()) · Booked by {{ $r->displayName() }} @endif
                    @elseif($lead && $lead->fullName() !== $r->displayName())
                        Booked by {{ $r->displayName() }}
                    @else
                        {{ $r->contact_email ?: $r->joiner?->email }}
                    @endif
                </span>
                @endif
            </span>
        </div>

        @if($mode === 'money')
            <div class="mt-2 sm:mt-0 flex sm:flex-nowrap flex-wrap items-center gap-1.5 whitespace-nowrap">
                <x-badge :tone="$state[1]" :dot="$state[1] !== 'moss'">{{ $state[0] }}</x-badge>
                @if($unreviewed)<x-badge tone="terra" dot dusk="new-proof-{{ $r->id }}">New proof</x-badge>@endif
                @if($r->paymentOverdue())<x-badge tone="terra" dot dusk="overdue-{{ $r->id }}">{{ $r->overdueLabel() }}</x-badge>@endif
                @if($owed['due'] <= 0 && $r->status !== 'cancelled')<span class="sr-only" dusk="paid-chip-{{ $r->id }}">Paid in full</span>@endif
                @if($r->refund_due !== null)
                    <x-badge :tone="$r->refunded_at ? 'moss' : 'terra'" :dot="! $r->refunded_at" dusk="refund-{{ $r->id }}">
                        {{ $r->refunded_at ? 'Refunded' : 'Refund owed' }}
                    </x-badge>
                @endif
            </div>
            <div class="mt-1 sm:mt-0 sm:text-right tabular-nums text-sm whitespace-nowrap
                        {{ $r->owesRefund() ? 'text-terra font-semibold' : ($owed['due'] > 0 ? 'text-terra' : 'text-ink/70') }}">
                {{ $r->owesRefund()
                    ? $fmt($r->refund_due).' to refund'
                    : ($owed['due'] > 0 ? $fmt($owed['due']).' due' : '—') }}
            </div>

        @elseif($mode === 'manifest')
            <div class="mt-2 sm:mt-0 flex sm:flex-nowrap flex-wrap items-center gap-1.5 whitespace-nowrap">
                @if($r->incompleteParticipants()->isNotEmpty())
                    <x-badge tone="amber" dot>{{ $r->incompleteParticipants()->count() }} incomplete</x-badge>
                @else
                    <x-badge tone="moss">Complete</x-badge>
                @endif
                @if($trip->requires_id && $r->participants->contains(fn ($p) => blank($p->id_image)))
                    <x-badge tone="terra" dot dusk="roster-id-missing-{{ $r->id }}">No ID</x-badge>
                @endif
            </div>
            <div class="mt-1 sm:mt-0 sm:text-right text-xs text-ink/70 truncate">
                <span class="block truncate">{{ $lead?->email ?: $r->contact_email ?: $r->joiner?->email ?: '—' }}</span>
                <span class="block truncate">{{ $lead?->phone ?: $r->contact_phone ?: $r->joiner?->phone ?: '' }}</span>
            </div>

        @else
            @php($arrived = $r->participants->filter(fn ($p) => $p->checked_in_at)->count())
            <div class="mt-2 sm:mt-0">
                <x-badge :tone="$arrived === $r->participants->count() && $arrived > 0 ? 'moss' : 'ink'"
                         :dot="$arrived !== $r->participants->count()">
                    {{ $arrived }} of {{ $r->participants->count() }}
                </x-badge>
            </div>
            <div class="mt-1 sm:mt-0 text-xs text-ink/70 tabular-nums">
                {{ $r->participants->firstWhere('checked_in_at', '!=', null)?->checked_in_at?->format('g:ia') ?: '—' }}
            </div>
        @endif

        @if($mode !== 'checkin')
            <div class="hidden sm:block text-right">
                <button type="button" wire:click="openBooking({{ $r->id }})" dusk="open-{{ $r->id }}"
                        aria-label="Open {{ $lead?->fullName() ?: $r->displayName() }}"
                        class="grid place-items-center w-11 h-11 -mr-2 rounded-lg text-ink/45 hover:text-ink hover:bg-ink/5 transition-colors">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 6l6 6-6 6"/></svg>
                </button>
            </div>
            {{-- On a phone the whole row is the target; a 44px chevron beside a name is a smaller
                 one than the row it sits in. --}}
            <button type="button" wire:click="openBooking({{ $r->id }})" dusk="open-mobile-{{ $r->id }}"
                    class="sm:hidden mt-2 w-full rounded-xl border border-ink/15 min-h-[44px] text-sm font-semibold hover:border-ink/35 transition-colors">
                Open
            </button>
        @endif
    </div>

    {{-- Check-in's inline toggle: one tap per traveller, the exception the drawer rule earns. --}}
    @if($mode === 'checkin')
        <div class="px-4 pb-3 flex flex-wrap gap-2">
            @foreach($r->participants as $p)
                <button type="button" wire:click="toggleArrival({{ $p->id }})" dusk="arrive-{{ $p->id }}"
                        aria-pressed="{{ $p->checked_in_at ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-2 rounded-full px-4 min-h-[44px] text-sm font-semibold border transition-colors
                               {{ $p->checked_in_at ? 'bg-moss text-cream border-moss' : 'border-ink/20 hover:border-ink/40' }}">
                    @if($p->checked_in_at)
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    @endif
                    {{ $p->first_name ?: 'Traveller' }}
                </button>
            @endforeach
        </div>
    @endif
</li>
