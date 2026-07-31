@php($statusLabel = [
    'pending' => ['Awaiting payment', 'amber'],
    'awaiting_payment' => ['Awaiting payment', 'amber'],
    'payment_submitted' => ['Waiting on your host', 'amber'],
    'confirmed' => ['Confirmed', 'moss'],
])

<div class="max-w-3xl mx-auto px-5 py-8">
    <h1 class="font-serif font-semibold text-3xl mb-1">Your trips</h1>
    <p class="text-ink/65 mb-7">Everything you have booked, and anything still waiting on you.</p>

    {{-- ═══ NEEDS YOU ═══ --}}
    @if(filled($attention))
        <section class="mb-8" dusk="needs-you">
            <h2 class="marker text-[11px] font-semibold text-ink/60 mb-3">Needs you</h2>

            <div class="space-y-2">
                @foreach($attention as $item)
                    <div class="flex flex-wrap items-center gap-4 rounded-2xl p-5 {{ $item['urgent'] ? 'bg-terra/10' : 'bg-amber/[.08]' }}"
                         dusk="attention-{{ $item['kind'] }}">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ $item['title'] }}</p>
                            <p class="text-sm text-ink/70 mt-0.5">{{ $item['detail'] }}</p>
                        </div>
                        <a href="{{ $item['href'] }}" wire:navigate
                           class="shrink-0 inline-flex items-center rounded-full px-5 min-h-[44px] text-sm font-semibold transition-colors
                                  {{ $item['urgent'] ? 'bg-pine text-cream hover:bg-pine/90' : 'border border-ink/20 hover:border-ink/40' }}">
                            {{ $item['cta'] }}
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ═══ UPCOMING ═══ --}}
    <section class="mb-8">
        <h2 class="marker text-[11px] font-semibold text-ink/60 mb-3">Upcoming</h2>

        @forelse($upcoming as $r)
            @php($trip = $r->departure->trip)
            @php($st = $statusLabel[$r->status] ?? [ucfirst(str_replace('_', ' ', $r->status)), 'ink'])
            <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
               dusk="upcoming-{{ $r->id }}"
               class="flex items-center gap-4 rounded-2xl border border-ink/10 p-4 mb-2 hover:border-ink/25 transition-colors">
                <img src="{{ $trip->cover_url }}" alt="" class="w-16 h-16 rounded-xl object-cover shrink-0">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold truncate">{{ $trip->title }}</p>
                    <p class="text-sm text-ink/65">
                        {{ $r->departure->starts_at->format('D j M Y') }}
                        <span class="text-ink/40">·</span> {{ $r->party_size }} {{ \Illuminate\Support\Str::plural('seat', $r->party_size) }}
                        <span class="text-ink/40">·</span> {{ $trip->host->name }}
                    </p>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold
                             {{ $st[1] === 'moss' ? 'bg-moss/12 text-pine' : ($st[1] === 'amber' ? 'bg-amber/15 text-amber' : 'bg-ink/[.06] text-ink/65') }}">
                    {{ $st[0] }}
                </span>
            </a>
        @empty
            <div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 py-12 text-center">
                <p class="font-semibold mb-1">No trips booked yet</p>
                <p class="text-ink/65 text-sm mb-5">Find a group heading somewhere you want to go.</p>
                <a href="{{ route('trips.index') }}" wire:navigate
                   class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors">Explore trips</a>
            </div>
        @endforelse
    </section>

    {{-- ═══ PAST ═══ --}}
    @if($past->isNotEmpty())
        <section class="mb-8">
            <h2 class="marker text-[11px] font-semibold text-ink/60 mb-3">Past</h2>

            @foreach($past as $r)
                @php($trip = $r->departure->trip)
                <a href="{{ route('reservations.show', ['token' => $r->public_token]) }}" wire:navigate
                   dusk="past-{{ $r->id }}"
                   class="flex items-center gap-4 rounded-2xl border border-ink/10 p-4 mb-2 opacity-80 hover:opacity-100 hover:border-ink/25 transition-all">
                    <img src="{{ $trip->cover_url }}" alt="" class="w-14 h-14 rounded-xl object-cover shrink-0 grayscale">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold truncate">{{ $trip->title }}</p>
                        <p class="text-sm text-ink/65">{{ $r->departure->starts_at->format('D j M Y') }}</p>
                    </div>
                </a>
            @endforeach
        </section>
    @endif

    {{-- ═══ CLAIM GUEST BOOKINGS ═══ --}}
    {{-- A booking made without an account has no joiner_id, so it cannot appear above. Without
         this, someone who booked as a guest and later signed up sees an empty page. --}}
    <section class="rounded-2xl bg-ink/[.03] p-5">
        @if($claimStatus)
            <p class="text-sm text-moss font-semibold" dusk="claim-status">{{ $claimStatus }}</p>
        @else
            <p class="text-sm font-semibold mb-1">Booked as a guest before?</p>
            <p class="text-sm text-ink/70 mb-3">
                Bookings made without an account are not listed here. We can email
                {{ auth()->user()->email }} a link to attach them.
            </p>
            <button wire:click="claimGuestBookings" dusk="claim-bookings"
                    class="inline-flex items-center rounded-full border border-ink/20 px-5 min-h-[44px] text-sm font-semibold hover:border-moss transition-colors">
                Email me a link
            </button>
        @endif
    </section>
</div>
