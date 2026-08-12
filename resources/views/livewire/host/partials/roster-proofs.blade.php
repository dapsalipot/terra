{{-- Every attempt, not just the latest.

     A rejected-then-resubmitted booking is precisely the case a host needs to see whole, and the
     reference number is the part they reconcile against their bank app — so it has to be readable
     as text, not only inside the image. --}}
@if($proofsFor)
    @php($proofBooking = $reservations->firstWhere('id', $proofsFor) ?? $openBooking)
    <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-6"
         role="dialog" aria-modal="true" aria-label="Payment proofs" dusk="proof-modal"
         x-data x-on:keydown.escape.window="$wire.closeProofs()">
        <div class="absolute inset-0 bg-ink/50 backdrop-blur-[2px]" wire:click="closeProofs" aria-hidden="true"></div>

        <div class="relative w-full sm:max-w-lg max-h-[85dvh] overflow-y-auto rounded-t-3xl sm:rounded-2xl bg-surface">
            <div class="sticky top-0 flex items-center justify-between gap-3 px-5 py-4 bg-surface border-b border-ink/8">
                <p class="font-serif font-semibold text-lg">Payment proofs</p>
                <button type="button" wire:click="closeProofs" dusk="close-proofs" aria-label="Close"
                        class="grid place-items-center w-11 h-11 -mr-2 rounded-lg text-ink/45 hover:text-ink hover:bg-ink/5">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <ul class="divide-y divide-ink/8">
                @foreach(($proofBooking?->paymentSubmissions ?? collect())->sortByDesc('submitted_at') as $sub)
                    <li class="p-5" dusk="proof-row-{{ $sub->id }}">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div class="min-w-0">
                                <p class="font-semibold text-sm">{{ $sub->method ?: 'Payment' }}</p>
                                <p class="text-xs text-ink/70">{{ $sub->submitted_at?->format('j M Y, g:ia') }}</p>
                            </div>
                            @if($sub->confirmed_at)
                                <x-badge tone="moss">Confirmed</x-badge>
                            @elseif($sub->rejected_reason)
                                <x-badge tone="terra" dot>Rejected</x-badge>
                            @else
                                <x-badge tone="amber" dot>Waiting</x-badge>
                            @endif
                        </div>

                        @if($sub->reference_no)
                            <p class="text-sm"><span class="text-ink/70">Reference</span> <span class="font-mono">{{ $sub->reference_no }}</span></p>
                        @endif
                        @if($sub->rejected_reason)
                            <p class="text-xs text-terra mt-1">Rejected: {{ $sub->rejected_reason }}</p>
                        @endif

                        @if($sub->proof_image)
                            <a href="{{ route('host.proof', $sub) }}" target="_blank" class="block mt-3">
                                <img src="{{ route('host.proof', $sub) }}" alt="Payment proof"
                                     class="w-full rounded-xl border border-ink/10">
                            </a>
                        @else
                            <p class="text-xs text-ink/70 mt-2">Reference only — no image sent.</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
