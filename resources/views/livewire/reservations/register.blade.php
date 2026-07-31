@php($cur = $trip->host->hostProfile?->currency ?? 'PHP')
@php($money = fn ($n) => $cur.' '.number_format($n))

<div class="max-w-2xl mx-auto px-5 py-8">
    <a href="{{ route('trips.show', $trip) }}" wire:navigate class="inline-flex items-center py-1.5 -my-1.5 text-sm text-ink/65 hover:text-ink">← {{ $trip->title }}</a>
    <h1 class="font-serif font-semibold text-3xl mt-2 mb-6">Join this trip</h1>

    @if($closed)
        <div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 py-12 text-center">
            <p class="font-semibold mb-1">{{ $closedReason }}</p>
            <p class="text-ink/65 text-sm mb-5">This departure is no longer taking registrations.</p>
            <a href="{{ route('trips.index') }}" wire:navigate
               class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors">Find another trip</a>
        </div>
    @else

    @if($error)
        <div class="rounded-xl bg-terra/10 text-terra px-4 py-3 mb-5 text-sm font-semibold" dusk="reg-error">{{ $error }}</div>
    @endif

    {{-- ═══ 1 · SEATS ═══ --}}
    <section class="rounded-2xl border border-ink/10 p-5 mb-4">
        <h2 class="font-serif font-semibold text-lg mb-3">How many seats?</h2>

        <div class="flex items-center gap-4">
            <input type="number" min="1" max="20" wire:model.live.debounce.500ms="partySize" dusk="party-size"
                   aria-label="Number of seats" class="field-narrow w-24 text-center">
            <div class="text-sm text-ink/70">
                <div>{{ $money($total) }} total</div>
                <div class="text-ink/50">{{ $spotsLeft }} {{ \Illuminate\Support\Str::plural('seat', $spotsLeft) }} left</div>
            </div>
        </div>

        @error('partySize')<p class="text-terra text-xs mt-2">{{ $message }}</p>@enderror

        @if($trip->takesDeposit())
            <p class="text-sm text-ink/70 mt-3">{{ $money($payableNow) }} to reserve, balance on the day.</p>
        @endif
    </section>

    {{-- ═══ 2 · BOOKER ═══ --}}
    <section class="rounded-2xl border border-ink/10 p-5 mb-4">
        <h2 class="font-serif font-semibold text-lg">Your details</h2>
        <p class="text-sm text-ink/65 mb-4">Who we contact about this booking.</p>

        <div class="grid sm:grid-cols-3 gap-3">
            <label class="block"><span class="field-label">First name</span>
                <input wire:model.blur="contact.first_name" dusk="contact-first" class="field">
                @error('contact.first_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <label class="block"><span class="field-label">Middle name <span class="text-ink/45">optional</span></span>
                <input wire:model.blur="contact.middle_name" class="field">
            </label>
            <label class="block"><span class="field-label">Last name</span>
                <input wire:model.blur="contact.last_name" dusk="contact-last" class="field">
                @error('contact.last_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
        </div>

        <div class="grid sm:grid-cols-2 gap-3 mt-3">
            <label class="block"><span class="field-label">Email</span>
                <input type="email" wire:model.blur="contact.email" dusk="contact-email" class="field">
                @error('contact.email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <label class="block"><span class="field-label">Mobile</span>
                <input type="tel" wire:model.blur="contact.phone" dusk="contact-phone" placeholder="09xx xxx xxxx" class="field">
                @error('contact.phone')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
        </div>
    </section>

    {{-- ═══ 3 · TRAVELLERS ═══ --}}
    <section class="rounded-2xl border border-ink/10 p-5 mb-4">
        <h2 class="font-serif font-semibold text-lg mb-3">Who's going?</h2>

        <label class="flex items-start gap-3 rounded-xl bg-moss/8 p-4 mb-4 cursor-pointer">
            <input type="checkbox" wire:model.live="bookerIsTraveling" dusk="booker-traveling"
                   class="mt-0.5 rounded border-ink/25 text-moss focus:ring-moss">
            <span class="text-sm">
                <b class="font-semibold">I'm joining this trip too</b>
                @unless($bookerIsTraveling)
                    <span class="block text-ink/70 mt-1">
                        You book and pay; {{ $partySize }} {{ \Illuminate\Support\Str::plural('person', $partySize) }} travel.
                        Your details are used for contact only and take no seat.
                    </span>
                @endunless
            </span>
        </label>

        @foreach($participants as $i => $p)
            <div class="rounded-xl border border-ink/10 p-4 mb-3" dusk="seat-{{ $i }}">
                <p class="font-semibold text-sm mb-3">
                    {{ $bookerIsTraveling && $i === 0 ? 'You' : 'Seat '.($i + 1) }}
                </p>

                <div class="grid sm:grid-cols-3 gap-3">
                    <label class="block"><span class="field-label">First name</span>
                        <input wire:model.blur="participants.{{ $i }}.first_name" dusk="seat-{{ $i }}-first" class="field">
                    </label>
                    <label class="block"><span class="field-label">Middle <span class="text-ink/45">optional</span></span>
                        <input wire:model.blur="participants.{{ $i }}.middle_name" class="field">
                    </label>
                    <label class="block"><span class="field-label">Last name</span>
                        <input wire:model.blur="participants.{{ $i }}.last_name" dusk="seat-{{ $i }}-last" class="field">
                    </label>
                </div>

                <div class="grid sm:grid-cols-2 gap-3 mt-3">
                    <label class="block"><span class="field-label">Birthday</span>
                        <input type="date" max="{{ now()->toDateString() }}"
                               wire:model.blur="participants.{{ $i }}.birthday" dusk="seat-{{ $i }}-birthday" class="field">
                        @error('participants.'.$i.'.birthday')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                    </label>

                    <div x-data="{ more: false }">
                        <span class="field-label">Gender</span>
                        <div class="flex gap-2">
                            @foreach(['male' => 'Male', 'female' => 'Female'] as $value => $label)
                                <button type="button" wire:click="$set('participants.{{ $i }}.gender', '{{ $value }}')"
                                        class="flex-1 rounded-xl border px-3 min-h-[44px] text-sm font-semibold transition-colors
                                               {{ ($p['gender'] ?? '') === $value ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">{{ $label }}</button>
                            @endforeach
                            <button type="button" @click="more = !more" aria-label="More gender options"
                                    class="rounded-xl border border-ink/15 px-3 min-h-[44px] text-sm hover:border-ink/30">…</button>
                        </div>
                        <div x-show="more" x-cloak class="flex gap-2 mt-2">
                            @foreach(['other' => 'Other', 'undisclosed' => 'Prefer not to say'] as $value => $label)
                                <button type="button" wire:click="$set('participants.{{ $i }}.gender', '{{ $value }}')"
                                        class="flex-1 rounded-xl border px-3 min-h-[40px] text-xs font-semibold transition-colors
                                               {{ ($p['gender'] ?? '') === $value ? 'bg-moss text-cream border-moss' : 'border-ink/15' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if($p['show_contact'] ?? false)
                    <div class="grid sm:grid-cols-2 gap-3 mt-3">
                        <label class="block"><span class="field-label">Their email <span class="text-ink/45">optional</span></span>
                            <input type="email" wire:model.blur="participants.{{ $i }}.email" class="field">
                            @error('participants.'.$i.'.email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        <label class="block"><span class="field-label">Their mobile <span class="text-ink/45">optional</span></span>
                            <input type="tel" wire:model.blur="participants.{{ $i }}.phone" class="field">
                        </label>
                    </div>
                @else
                    <button type="button" wire:click="toggleContactFor({{ $i }})"
                            class="inline-flex items-center py-1.5 -my-1.5 text-sm font-semibold text-moss hover:underline mt-3">+ add contact</button>
                @endif
            </div>
        @endforeach

        <p class="text-sm text-ink/60">Don't have everyone's details yet? Leave a seat blank and add them later.</p>
    </section>

    {{-- ═══ 4 · CONFIRM ═══ --}}
    <section class="rounded-2xl border border-ink/10 p-5">
        @unless($this->isSignedIn)
            <label class="flex items-start gap-3 rounded-xl bg-moss/8 p-4 mb-4 cursor-pointer">
                <input type="checkbox" wire:model="createAccount" dusk="create-account"
                       class="mt-0.5 rounded border-ink/25 text-moss focus:ring-moss">
                <span class="text-sm">
                    <b class="font-semibold">Create my Peeps account</b>
                    <span class="block text-ink/70 mt-0.5">Track this trip, chat with your group, and rebook in one tap. No password needed.</span>
                </span>
            </label>
        @endunless

        <button wire:click="submit" wire:loading.attr="disabled" dusk="submit-registration"
                class="w-full rounded-full bg-pine text-cream py-3.5 font-semibold hover:bg-pine/90 transition-colors">
            Confirm registration · {{ $money($payableNow) }}
        </button>

        @unless($this->isSignedIn)
            <p class="text-xs text-ink/55 text-center mt-2">Booking as a guest? Untick the box above.</p>
        @endunless
    </section>

    @endif
</div>
