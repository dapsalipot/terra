@props([
    'reservation',
    'variant' => 'card',   // card = cover on top; row = thumbnail beside the text
    'actions' => true,
])

@php($trip = $reservation->departure->trip)
@php($link = route('reservations.show', ['token' => $reservation->public_token]))
@php($status = [
    'pending' => ['Awaiting payment', 'amber'],
    'awaiting_payment' => ['Awaiting payment', 'amber'],
    'payment_submitted' => ['Waiting on your host', 'amber'],
    'confirmed' => ['Confirmed', 'moss'],
][$reservation->status] ?? [ucfirst(str_replace('_', ' ', $reservation->status)), 'ink'])
@php($pill = $status[1] === 'moss' ? 'bg-moss/90 text-cream'
    : ($status[1] === 'amber' ? 'bg-amber/90 text-cream' : 'bg-ink/70 text-cream/90'))
@php($past = $reservation->departure->starts_at->isPast())

@if($variant === 'card')
    <div dusk="booking-card" class="flex flex-col rounded-2xl border border-ink/10 overflow-hidden hover:border-ink/25 transition-all duration-300">
        <a href="{{ $link }}" wire:navigate class="block relative group">
            <img src="{{ $trip->cover_url }}" alt=""
                 class="w-full h-36 object-cover transition-transform duration-[900ms] ease-[cubic-bezier(.2,.7,.2,1)] group-hover:scale-[1.04] {{ $past ? 'grayscale' : '' }}">
            <div class="absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-ink/60 to-transparent" aria-hidden="true"></div>
            {{-- The date reads before the title: a trip is an event first. --}}
            <span class="absolute top-3 left-3 grid place-items-center rounded-xl bg-cream/95 px-2.5 py-1 text-center shadow-sm">
                <span class="block text-[10px] font-bold uppercase tracking-wide text-terra leading-none">{{ $reservation->departure->starts_at->format('M') }}</span>
                <span class="block font-serif font-bold text-lg text-ink leading-none mt-0.5">{{ $reservation->departure->starts_at->format('j') }}</span>
            </span>
            <span class="absolute top-3 right-3 rounded-full px-3 py-1 text-xs font-bold {{ $pill }}">{{ $status[0] }}</span>
        </a>
        <div class="p-4 flex-1 flex flex-col">
            <a href="{{ $link }}" wire:navigate class="block min-w-0 mb-1">
                <p class="font-semibold truncate">{{ $trip->title }}</p>
            </a>
            <p class="text-sm text-ink/65">
                {{ $reservation->departure->starts_at->format('D j M Y') }}
                <span class="text-ink/40">·</span> {{ $reservation->party_size }} {{ \Illuminate\Support\Str::plural('seat', $reservation->party_size) }}
                <span class="text-ink/40">·</span>
                {{-- Not inside either of this card's anchors, so it can be a link in its own right. --}}
                <x-host-link :host="$trip->host" :avatar="false" class="align-bottom" />
            </p>
            @if($actions)
                <div class="flex items-center justify-end gap-1 mt-auto pt-3 border-t border-ink/8">
                    <a href="{{ $link }}" wire:navigate
                       class="inline-flex items-center rounded-full border border-ink/20 text-sm font-semibold text-pine hover:border-ink/30 min-h-[40px] px-4 transition-colors">View</a>
                </div>
            @endif
        </div>
    </div>
@else
    <a href="{{ $link }}" wire:navigate dusk="booking-card"
       class="flex items-center gap-4 rounded-2xl border border-ink/10 p-4 hover:border-ink/25 transition-all {{ $past ? 'opacity-75 hover:opacity-100' : '' }}">
        <img src="{{ $trip->cover_url }}" alt="" class="w-16 h-16 rounded-xl object-cover shrink-0 {{ $past ? 'grayscale' : '' }}">
        <span class="min-w-0 flex-1">
            <span class="block font-semibold truncate">{{ $trip->title }}</span>
            <span class="block text-sm text-ink/65">
                {{ $reservation->departure->starts_at->format('D j M Y') }}
                <span class="text-ink/40">·</span> {{ $reservation->party_size }} {{ \Illuminate\Support\Str::plural('seat', $reservation->party_size) }}
            </span>
        </span>
        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $pill }}">{{ $status[0] }}</span>
    </a>
@endif
