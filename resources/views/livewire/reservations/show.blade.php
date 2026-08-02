@php($trip = $reservation->departure->trip)
@php($host = $trip->host)
{{-- A deposit holds the place; the balance is settled with the host on the day. --}}
@php($perHead = $trip->current_price)
@php($amount = $trip->payable_now * $reservation->party_size)
@php($balance = ($perHead - $trip->payable_now) * $reservation->party_size)
@php($pending = $reservation->incompleteParticipants())
<div class="max-w-xl mx-auto px-6 py-10">
    <a href="{{ route('trips.show', $trip) }}" class="text-sm text-ink/60">‹ {{ $trip->title }}</a>

    {{-- Leads the page when heads are unnamed: the host cannot file a permit without them. --}}
    @if($pending->isNotEmpty())
        <div class="mt-4 rounded-2xl bg-amber/12 p-5" dusk="pending-details">
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

    {{-- Who this booking is for. A guest booking has no account, so the contact snapshot and the
         traveller rows are the only record of who is coming. --}}
    <div class="mt-4 rounded-2xl border border-ink/10 p-5">
        <h2 class="font-serif font-semibold text-lg mb-1">{{ $reservation->contactName() ?: $reservation->displayName() }}</h2>
        <p class="text-sm text-ink/65 mb-3">
            {{ $reservation->party_size }} {{ Str::plural('seat', $reservation->party_size) }}
            @unless($reservation->bookerIsTraveling()) · you are booking for others @endunless
        </p>

        @if($reservation->participants->isNotEmpty())
            <ol class="space-y-1 text-sm">
                @foreach($reservation->participants as $p)
                    <li class="flex items-center gap-2 py-1">
                        <span class="text-ink/45 tabular-nums w-4">{{ $loop->iteration }}</span>
                        @if($p->isComplete())
                            <span>{{ $p->fullName() }}</span>
                            @if($p->is_booker)<span class="text-xs font-semibold text-moss">you</span>@endif
                            @if($p->gender)<span class="text-xs text-ink/50 capitalize">{{ $p->gender }}</span>@endif
                            @if($p->email)<span class="text-xs text-ink/40">{{ $p->email }}</span>@endif
                        @else
                            <span class="text-ink/45 italic">Details still needed</span>
                        @endif
                        @if($this->canEdit())
                            <div class="flex items-center gap-1.5 ml-auto">
                                <button type="button" wire:click="editParticipant({{ $p->id }})" dusk="edit-participant-{{ $p->id }}"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-ink/15 px-3 py-1.5 text-xs font-semibold text-pine hover:text-pine hover:border-pine/30 transition-colors">
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
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    {{-- The booking's own address. With no mail provider configured this is the only way back. --}}
    <div class="mt-4 rounded-2xl bg-moss/10 p-5">
        <p class="text-sm font-semibold mb-1.5">Save this link</p>
        <p class="text-sm text-ink/70 mb-3">It's how you get back to this booking.</p>
        <input readonly value="{{ route('reservations.show', ['token' => $reservation->public_token]) }}"
               dusk="booking-link" aria-label="Link to this booking"
               class="w-full rounded-lg border border-ink/15 px-3 py-2 text-sm font-mono bg-cream"
               onclick="this.select()">
    </div>

    @if($reservation->status === 'confirmed')
        <div class="mt-6 rounded-2xl bg-moss/10 p-8 text-center">
            <div class="inline-grid place-items-center w-16 h-16 rounded-full bg-moss/15 text-moss mb-4">
                <x-icon.check class="w-8 h-8" />
            </div>
            <h1 class="font-serif font-semibold text-2xl">You're confirmed</h1>
            <p class="text-ink/60 mt-2">{{ $host->name }} confirmed your spot on {{ $reservation->departure->starts_at->format('M j') }}.</p>
            {{-- Trip chat sits behind auth, so a guest booking has nothing to open yet. --}}
            @auth
                <a href="{{ route('chat.show', $reservation->departure) }}" class="inline-block mt-5 rounded-full bg-pine text-cream px-6 py-3 font-semibold">Open trip chat</a>
            @else
                <p class="text-sm text-ink/60 mt-4">Create an account to join the trip chat with your group.</p>
            @endauth
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
            <label for="reference_no" class="block text-sm font-semibold text-ink/70 mb-1">Reference number</label>
            <input wire:model="reference_no" id="reference_no" name="reference_no" dusk="reference_no" class="w-full rounded-xl border border-ink/15 px-4 py-3 font-mono" placeholder="e.g. 9021 5567 3382">
            @error('reference_no')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror

            <label for="proof" class="block text-sm font-semibold text-ink/70 mt-4 mb-1">Screenshot <span class="font-normal text-ink/50">— optional, seen only by your host</span></label>
            <input type="file" id="proof" wire:model="proof" dusk="proof" accept="image/*" class="block w-full text-sm">
            @error('proof')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror

            <button class="mt-5 w-full rounded-full bg-pine text-cream py-3 font-semibold">Submit proof of payment</button>
        </form>
        <button wire:click="cancel" class="mt-3 w-full text-center text-sm text-terra">Cancel reservation</button>
        <p class="mt-4 text-center text-xs text-ink/50">Peeps never holds your money — you pay {{ $host->name }} directly.</p>
    @endif

    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4" dusk="edit-modal">
            <div class="absolute inset-0 bg-ink/40 sm:bg-ink/30" wire:click="closeModal"></div>
            <div class="relative bg-cream sm:bg-cream/90 w-full h-full sm:h-auto sm:max-h-[90vh] sm:rounded-2xl sm:border sm:border-ink/15 sm:shadow-2xl sm:max-w-xl sm:w-full overflow-y-auto">
                <div class="p-5 sm:p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="font-serif font-semibold text-lg">Edit traveller</h3>
                        <button type="button" wire:click="closeModal" class="text-ink/40 hover:text-ink/70 p-1 rounded-lg hover:bg-ink/5 transition-colors" aria-label="Close">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
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
                    <div class="grid sm:grid-cols-2 gap-3 mt-3">
                        <label class="block"><span class="field-label">Birthday</span>
                            <input type="date" max="{{ now()->toDateString() }}" wire:model="edit_birthday" dusk="edit-birthday" class="field">
                            @error('edit_birthday')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        <label class="block"><span class="field-label">Gender</span>
                            <div class="flex gap-2">
                                @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'undisclosed' => 'Prefer not to say'] as $value => $label)
                                    <button type="button" wire:click="$set('edit_gender', '{{ $value }}')"
                                            class="flex-1 rounded-xl border px-4 min-h-[44px] text-sm font-semibold transition-colors
                                                   {{ $edit_gender === $value ? 'bg-pine text-cream border-pine' : 'border-ink/15 hover:border-ink/30' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                            @error('edit_gender')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3 mt-3">
                        <label class="block"><span class="field-label">Email <span class="text-ink/45">optional</span></span>
                            <input type="email" wire:model="edit_email" dusk="edit-email" class="field">
                            @error('edit_email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        <label class="block"><span class="field-label">Mobile <span class="text-ink/45">optional</span></span>
                            <input type="tel" wire:model="edit_phone" dusk="edit-phone" class="field">
                        </label>
                    </div>
                    <div class="flex items-center justify-end gap-2 mt-5">
                        <button type="button" wire:click="closeModal" dusk="cancel-edit"
                                class="rounded-full border border-ink/20 px-5 min-h-[44px] text-sm font-semibold hover:border-ink/40 transition-colors">
                            Cancel
                        </button>
                        <button type="button" wire:click="updateParticipant" wire:loading.attr="disabled" dusk="update-participant"
                                class="rounded-full bg-pine text-cream px-5 min-h-[44px] text-sm font-semibold hover:bg-pine/90 transition-colors">
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($removingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-0 sm:p-4" dusk="remove-confirm-modal">
            <div class="absolute inset-0 bg-ink/40 sm:bg-ink/30" wire:click="cancelRemove"></div>
            <div class="relative bg-cream w-full h-full sm:h-auto sm:rounded-2xl sm:border sm:border-ink/15 sm:shadow-2xl sm:max-w-sm sm:w-full sm:mx-auto flex flex-col">
                <div class="p-5 sm:p-6 flex-1 flex flex-col">
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
                    <div class="flex items-center justify-end gap-2 mt-auto pt-4 border-t border-ink/8">
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
