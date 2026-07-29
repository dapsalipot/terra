@php($fmt = fn ($n) => $currency.' '.number_format($n))

<div>
    <a href="{{ route('host.dashboard') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-ink/65 hover:text-ink mb-3">
        <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 6l-6 6 6 6"/></svg>
        Dashboard
    </a>

    {{-- ═══ HEADER ═══ --}}
    <div class="flex flex-wrap items-start gap-4 mb-6">
        <img src="{{ $trip->cover_url }}" alt="" class="w-20 h-20 rounded-2xl object-cover shrink-0">
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
            <a href="{{ route('host.trips.qr', $trip) }}" target="_blank" class="rounded-full border border-ink/15 px-4 min-h-[44px] inline-flex items-center text-sm font-semibold hover:border-ink/30 transition-colors">Trip QR</a>
            <button wire:click="$toggle('showAdd')" dusk="toggle-add" class="rounded-full bg-pine text-cream px-5 min-h-[44px] font-semibold hover:bg-pine/90 transition-colors">＋ Add guest</button>
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
    <h2 class="font-serif font-semibold text-xl mb-3">
        Who's coming
        <span class="font-sans font-normal text-base text-ink/65">· {{ $reservations->count() }} {{ \Illuminate\Support\Str::plural('booking', $reservations->count()) }}</span>
    </h2>

    <div class="space-y-2">
        @forelse($reservations as $r)
            @php($owed = $r->money())
            @php($map = [
                'payment_submitted' => ['Awaiting your review', 'amber'],
                'confirmed' => ['Confirmed', 'moss'],
                'pending' => ['Pending', 'ink'],
                'awaiting_payment' => ['Awaiting payment', 'amber'],
                'cancelled' => ['Cancelled', 'ink'],
            ])
            @php($st = $map[$r->status] ?? [ucfirst(str_replace('_', ' ', $r->status)), 'ink'])

            <div class="{{ $r->status === 'payment_submitted' ? 'glass-terra rounded-2xl' : 'glass-card' }} p-5"
                 dusk="reservation-{{ $r->id }}">
                <div class="flex flex-wrap items-start gap-4">
                    <span class="grid place-items-center w-11 h-11 rounded-full text-sm font-bold text-cream shrink-0 {{ $r->isOffline() ? 'bg-ink/40' : 'bg-moss' }}">
                        {{ strtoupper(substr($r->displayName(), 0, 2)) }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ $r->displayName() }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold
                                {{ $st[1] === 'moss' ? 'bg-moss/12 text-pine' : ($st[1] === 'amber' ? 'bg-amber/15 text-amber' : 'bg-ink/[.06] text-ink/65') }}">{{ $st[0] }}</span>
                            @if($r->isOffline())
                                <span class="rounded-full bg-ink/[.06] text-ink/65 px-2.5 py-1 text-xs font-bold">Booked offline</span>
                            @endif
                            @if($r->owesBalance())
                                <span class="rounded-full bg-amber/15 text-amber px-2.5 py-1 text-xs font-bold">Balance due</span>
                            @endif
                        </div>

                        {{-- Contact details: the host has to reach these people on the day. --}}
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-ink/70 mt-1.5">
                            <span>{{ $r->party_size }} {{ \Illuminate\Support\Str::plural('place', $r->party_size) }}</span>
                            @if($r->joiner?->email)<span class="truncate">{{ $r->joiner->email }}</span>@endif
                            @php($phone = $r->joiner?->phone ?: $r->guest_phone)
                            @if($phone)<a href="tel:{{ $phone }}" class="font-semibold text-moss hover:underline">{{ $phone }}</a>@endif
                            @if($r->latestPayment?->reference_no)
                                <span class="font-mono text-xs text-ink/65">ref {{ $r->latestPayment->reference_no }}</span>
                            @endif
                            @if($r->latestPayment?->method)<span class="capitalize">{{ $r->latestPayment->method }}</span>@endif
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="font-serif font-bold text-lg tabular-nums">{{ $fmt($owed['paid']) }}</div>
                        <div class="text-xs text-ink/65">
                            paid of {{ $fmt($owed['total']) }}
                            @if($owed['due'] > 0)
                                <span class="block font-semibold text-amber">{{ $fmt($owed['due']) }} due</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if($r->status === 'payment_submitted')
                    <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-ink/8">
                        @if($r->latestPayment?->proof_image)
                            <a href="{{ route('host.proof', $r->latestPayment) }}" target="_blank" rel="noopener noreferrer"
                               class="rounded-full border border-ink/15 px-4 min-h-[40px] inline-flex items-center text-sm font-semibold hover:border-ink/30 transition-colors">See proof ↗</a>
                        @endif
                        <input wire:model="rejectReason.{{ $r->id }}" type="text" placeholder="Why are you rejecting it? (optional)"
                               aria-label="Reason for rejecting {{ $r->displayName() }}'s payment"
                               class="flex-1 min-w-[12rem] rounded-xl border-ink/15 px-3.5 py-2 text-sm focus:border-moss focus:ring-moss">
                        <button wire:click="reject({{ $r->id }})" dusk="reject-{{ $r->id }}"
                                class="rounded-full border border-ink/15 px-4 min-h-[40px] text-sm font-semibold hover:border-terra hover:text-terra transition-colors">Reject</button>
                        <button wire:click="confirm({{ $r->id }})" dusk="confirm-{{ $r->id }}"
                                class="rounded-full bg-moss text-cream px-5 min-h-[40px] text-sm font-semibold hover:bg-moss/90 transition-colors">Mark as paid</button>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 py-12 text-center">
                <p class="font-semibold mb-1">Nobody has booked yet</p>
                <p class="text-ink/65 text-sm">Share the trip link, or add a walk-in with ＋ Add guest.</p>
            </div>
        @endforelse
    </div>
</div>
