@php($trip = $reservation->departure->trip)
@php($host = $trip->host)
{{-- A deposit holds the place; the balance is settled with the host on the day. --}}
@php($perHead = $trip->current_price)
@php($amount = $trip->payable_now * $reservation->party_size)
@php($balance = ($perHead - $trip->payable_now) * $reservation->party_size)
<div class="max-w-xl mx-auto px-6 py-10">
    <a href="{{ route('trips.show', $trip) }}" class="text-sm text-ink/60">‹ {{ $trip->title }}</a>

    @if($reservation->status === 'confirmed')
        <div class="mt-6 rounded-2xl bg-moss/10 p-8 text-center">
            <div class="inline-grid place-items-center w-16 h-16 rounded-full bg-moss/15 text-moss mb-4">
                <x-icon.check class="w-8 h-8" />
            </div>
            <h1 class="font-serif font-semibold text-2xl">You're confirmed</h1>
            <p class="text-ink/60 mt-2">{{ $host->name }} confirmed your spot on {{ $reservation->departure->starts_at->format('M j') }}.</p>
            <a href="{{ route('chat.show', $reservation->departure) }}" class="inline-block mt-5 rounded-full bg-pine text-cream px-6 py-3 font-semibold">Open trip chat</a>
        </div>
    @elseif($reservation->status === 'payment_submitted')
        <div class="mt-6 rounded-2xl bg-amber/10 p-8 text-center">
            <div class="inline-grid place-items-center w-16 h-16 rounded-full bg-amber/15 text-amber mb-4">
                <x-icon.check class="w-8 h-8" />
            </div>
            <h1 class="font-serif font-semibold text-2xl">Waiting for {{ $host->name }}</h1>
            <p class="text-ink/60 mt-2">You sent {{ $host->hostProfile?->currency }} {{ number_format($amount) }} · we'll notify you when it's confirmed.</p>
            <p class="mt-2 font-mono text-sm text-ink/50">ref {{ $reservation->latestPayment?->reference_no }}</p>
        </div>
    @else
        <h1 class="font-serif font-semibold text-3xl mt-4">Pay your host</h1>
        @if($reservation->status === 'awaiting_payment' && $reservation->latestPayment?->rejected_reason)
            <p class="mt-3 rounded-xl bg-terra/10 text-terra text-sm px-4 py-3">
                {{ $host->name }} couldn't verify your last payment: {{ $reservation->latestPayment->rejected_reason }}
            </p>
        @endif

        <div class="mt-5 flex items-center justify-between gap-4">
            <span class="text-ink/65">{{ $reservation->departure->starts_at->format('M j') }} · {{ $reservation->party_size }} {{ Str::plural('guest', $reservation->party_size) }}</span>
            <span class="text-right shrink-0">
                <span class="block font-serif font-bold text-2xl">{{ $host->hostProfile?->currency }} {{ number_format($amount) }}</span>
                @if($trip->takesDeposit())
                    <span class="block text-xs font-semibold text-ink/65">to reserve</span>
                @endif
            </span>
        </div>

        {{-- Say what is still owed. A joiner who thinks they have paid in full and turns up
             with no cash is a problem the host has to solve at the jump-off. --}}
        @if($trip->takesDeposit())
            <div class="mt-3 rounded-xl border border-amber/30 bg-amber/[.07] px-4 py-3">
                <p class="text-sm font-semibold">This is a down payment</p>
                <p class="text-sm text-ink/70 mt-0.5">
                    {{ $host->hostProfile?->currency }} {{ number_format($balance) }} is due to your host on the day
                    ({{ $host->hostProfile?->currency }} {{ number_format($perHead) }} per person in total).
                </p>
            </div>
        @endif

        <div class="mt-5 rounded-2xl bg-pine text-cream p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-cream/60 mb-3">Send directly to your host</p>
            @forelse($host->hostProfile?->payment_methods ?? [] as $pm)
                <div class="flex items-center gap-3 py-3 {{ !$loop->first ? 'border-t border-cream/10' : '' }}">
                    @if($pm['qr'] ?? null)
                        {{-- white plate: scanners need light quiet-space around a QR --}}
                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($pm['qr']) }}" target="_blank" rel="noopener noreferrer"
                           class="shrink-0 rounded-xl bg-cream p-1.5" title="Open {{ $pm['label'] ?? '' }} QR full size">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($pm['qr']) }}"
                                 alt="{{ $pm['label'] ?? '' }} QR code" class="w-20 h-20 object-cover rounded-lg">
                        </a>
                    @endif
                    <span class="min-w-0">
                        <span class="font-mono break-words">{{ $pm['detail'] }}</span>
                        <span class="block text-xs text-cream/60">{{ $pm['label'] }}@if($pm['qr'] ?? null) · tap the code to enlarge @endif</span>
                    </span>
                </div>
            @empty
                <p class="text-cream/70 text-sm">Ask {{ $host->name }} for payment details in the trip chat.</p>
            @endforelse
        </div>

        <form wire:submit="submit" class="mt-5">
            <label class="block text-sm font-semibold text-ink/70 mb-1">Reference number</label>
            <input wire:model="reference_no" name="reference_no" dusk="reference_no" class="w-full rounded-xl border border-ink/15 px-4 py-3 font-mono" placeholder="e.g. 9021 5567 3382">
            @error('reference_no')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror

            <label class="block text-sm font-semibold text-ink/70 mt-4 mb-1">Screenshot <span class="font-normal text-ink/50">— optional, seen only by your host</span></label>
            <input type="file" wire:model="proof" dusk="proof" accept="image/*" class="block w-full text-sm">
            @error('proof')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror

            <button class="mt-5 w-full rounded-full bg-pine text-cream py-3 font-semibold">Submit proof of payment</button>
        </form>
        <button wire:click="cancel" class="mt-3 w-full text-center text-sm text-terra">Cancel reservation</button>
        <p class="mt-4 text-center text-xs text-ink/50">Peeps never holds your money — you pay {{ $host->name }} directly.</p>
    @endif
</div>
