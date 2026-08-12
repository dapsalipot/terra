@props(['participant', 'reservation', 'compact' => false])

@php
    $trip = $reservation->departure->trip;
    $departure = $reservation->departure;
    $host = $trip->host;
    $qr = route('passes.qr', ['participant' => $participant, 'token' => $reservation->public_token]);
@endphp

{{--
    A boarding pass, borrowing the shape people already know.

    Dark header block, a perforation, then the code — the layout of every ticket anyone has held,
    which means nobody has to be taught how to read it. The print:* rules exist because half of
    these will be printed rather than shown on a phone with 2% battery at a trailhead.

    A component rather than page markup: this now renders both inline on the booking and on its own
    print route, and two copies of a shape this fiddly — notch geometry, QR sizing, page-break
    rules — would drift apart the first time either was touched.

    `compact` trims the QR for the inline case, where the pass sits among other cards rather than
    being the entire page.
--}}
<article {{ $attributes->merge(['class' => 'rounded-3xl overflow-hidden bg-surface border border-ink/10 shadow-[0_16px_44px_-28px_rgba(31,28,21,.5)] print:shadow-none print:break-inside-avoid']) }}
         dusk="pass-{{ $participant->id }}">

    {{-- The stub: who and what, in the colours of the brand. --}}
    <div class="bg-pine text-cream px-5 sm:px-6 pt-5 sm:pt-6 pb-5">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-cream/70">Terra check-in pass</p>
                <p class="font-serif font-semibold text-2xl leading-tight mt-1 truncate">{{ $participant->fullName() }}</p>
            </div>
            @if($participant->checked_in_at)
                <span class="rounded-full bg-moss text-cream px-3 py-1 text-[11px] font-bold shrink-0"
                      dusk="pass-checked-in-{{ $participant->id }}">Checked in</span>
            @endif
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 mt-5 text-sm">
            @foreach([
                ['Trip', $trip->title],
                ['Host', $host->name],
                ['Departs', $departure->starts_at->format('D, j M Y')],
                ['Meet at', $trip->meetup_point ?: '—'],
            ] as [$term, $value])
                <div class="min-w-0">
                    <dt class="text-[10px] uppercase tracking-[0.09em] text-cream/70 font-semibold">{{ $term }}</dt>
                    <dd class="font-semibold truncate">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    {{-- The perforation. Two notches and a dashed rule: the one bit of skeuomorphism here, and
         the reason the thing reads as a ticket at a glance. --}}
    <div class="relative bg-pine">
        <div class="absolute -left-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-canvas print:bg-white" aria-hidden="true"></div>
        <div class="absolute -right-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-canvas print:bg-white" aria-hidden="true"></div>
        <div class="border-t-2 border-dashed border-cream/25 mx-6"></div>
    </div>

    <div class="bg-pine px-5 sm:px-6 pb-6 pt-6 flex flex-col items-center">
        {{-- object-contain, never object-cover: cropping a QR clips its alignment squares and the
             thing stops scanning, which you only discover at the trailhead. --}}
        <div class="rounded-2xl bg-white p-3">
            <img src="{{ $qr }}" alt="Check-in code for {{ $participant->fullName() }}"
                 class="{{ $compact ? 'w-44 h-44 sm:w-52 sm:h-52' : 'w-56 h-56 sm:w-64 sm:h-64' }} object-contain">
        </div>

        {{-- The code in type, because a camera cannot always read a screen and iOS Safari has no
             BarcodeDetector — typing it in is the path that always works. --}}
        <p class="font-mono text-lg font-semibold tracking-[0.12em] text-cream mt-4">{{ $participant->pass_code }}</p>
        <p class="text-[11px] text-cream/70 mt-1 text-center">Show this to {{ $host->name }} at the meeting point</p>

        <div class="print:hidden mt-4 flex flex-wrap items-center justify-center gap-2">
            <a href="{{ $qr }}" download="terra-pass-{{ $participant->pass_code }}.png"
               dusk="download-pass-{{ $participant->id }}"
               class="inline-flex items-center justify-center gap-2 rounded-full bg-cream/15 hover:bg-cream/25 text-cream px-4 min-h-[44px] text-sm font-semibold transition-colors">
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 11l5 5 5-5M5 21h14"/></svg>
                Save the code
            </a>
            {{ $slot }}
        </div>
    </div>
</article>
