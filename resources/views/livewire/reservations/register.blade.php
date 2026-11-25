@php($cur = $trip->host->hostProfile?->currency ?? 'PHP')
@php($money = fn ($n) => $cur.' '.number_format($n))

{{--
    Field ids double as anchor targets for the error summary, so a problem on seat 6 is one click
    away instead of a scroll-and-hunt. The key is the validation key with dots swapped for dashes.
--}}
@php($fieldId = fn ($key) => 'f-'.str_replace(['.', '_'], '-', $key))

{{--
    Tight horizontally, never vertically.

    Two seat cards abreast leaves the gender row about 340px, and at px-3/text-sm the four options
    came to ~390 — so "Rather not say" wrapped alone onto a second line and stopped reading as one
    of a set. The padding and label size come down; min-h-[44px] does not, because that is the
    touch target and it is not the part that was too big.
--}}
@php($chip = 'rounded-xl border px-2 min-h-[44px] text-xs font-semibold whitespace-nowrap transition-colors')
@php($chipOn = 'bg-moss text-cream border-moss')
@php($chipOff = 'border-ink/15 bg-surface hover:border-moss/50 hover:bg-moss/5')

@if($closed)
    <div class="max-w-2xl mx-auto px-5 py-16">
        <a href="{{ route('trips.show', $trip) }}" wire:navigate class="inline-flex items-center py-1.5 -my-1.5 text-sm text-ink/65 hover:text-ink">← {{ $trip->title }}</a>
        <h1 class="font-serif font-semibold text-3xl mt-2 mb-6">Join this trip</h1>
        <div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 py-12 text-center">
            <p class="font-semibold mb-1">{{ $closedReason }}</p>
            <p class="text-ink/65 text-sm mb-5">This departure is no longer taking registrations.</p>
            <a href="{{ route('trips.index') }}" wire:navigate
               class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors">Find another trip</a>
        </div>
    </div>
@elseif($soldOut)
    {{-- Sold out is a queue, not a closure: an unpaid hold lapses here every few hours, so this
         is very often temporary and the only page that was throwing away demand it already had. --}}
    <div class="max-w-xl mx-auto px-5 py-16">
        <a href="{{ route('trips.show', $trip) }}" wire:navigate class="inline-flex items-center py-1.5 -my-1.5 text-sm text-ink/65 hover:text-ink">← {{ $trip->title }}</a>

        @if($waitlisted)
            <div class="rounded-2xl bg-moss/10 border border-moss/25 p-8 mt-6 text-center" dusk="waitlisted">
                <span class="inline-grid place-items-center w-14 h-14 rounded-full bg-moss/15 text-moss mb-4">
                    <x-icon.check class="w-7 h-7" />
                </span>
                <h1 class="font-serif font-semibold text-2xl">You're on the list</h1>
                <p class="text-ink/70 mt-2">
                    We'll email {{ $waitlistEmail }} the moment a seat opens on {{ $departure->starts_at->format('j M') }}.
                    Seats go to whoever books first, so be quick when it lands.
                </p>
            </div>
        @else
            <h1 class="font-serif font-semibold text-3xl mt-2">This departure is full</h1>
            <p class="text-ink/65 mt-2">
                Seats open up often — an unpaid hold is released every few hours. Leave your details
                and we'll tell you the moment one does.
            </p>

            <form wire:submit="joinWaitlist" class="mt-6 rounded-2xl border border-ink/10 bg-surface p-5 space-y-4">
                <label class="block">
                    <span class="field-label">Your name</span>
                    <input wire:model="waitlistName" dusk="waitlist-name" class="field">
                    @error('waitlistName')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="field-label">Email</span>
                    <input type="email" wire:model="waitlistEmail" dusk="waitlist-email" class="field">
                    @error('waitlistEmail')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>

                <label class="block">
                    <span class="field-label">Mobile <span class="font-normal text-ink/65">optional</span></span>
                    <input type="tel" wire:model="waitlistPhone" dusk="waitlist-phone" class="field">
                    @error('waitlistPhone')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>

                <button dusk="join-waitlist"
                        class="w-full rounded-full bg-pine text-cream min-h-[52px] font-semibold hover:bg-pine/90 transition-colors">
                    Tell me when a seat opens
                </button>
                <p class="text-xs text-ink/65 text-center">One email, only if a seat actually frees up.</p>
            </form>
        @endif
    </div>
@else

{{--
    A checkout: form on the left, what you are buying pinned on the right.

    Two thirds / one third of the viewport. From `xl` the rail is taken clean out of the document
    flow with `fixed` rather than kept in a grid track with `sticky` — sticky only holds its
    position while its own containing block still has room underneath it, and a short form (few
    seats, nothing typed into the optional fields) ends before the rail's own height does. The
    rail would then detach and fall to the bottom of that track, leaving the gap where it used to
    be sitting empty. `fixed` has no track to run out of: it is pinned to the viewport itself, so
    there is no length of form that can out-scroll it and no bottom edge for it to expose.

    The trade a fixed rail makes is that the form column has to leave room for it itself, since a
    fixed element does not push document flow around the way an in-flow one does — `xl:w-2/3` on
    the wrapper below is that reserved space, sized to match the rail's own `xl:w-1/3`.
--}}
<div>

    {{--
        The heading sits above the form, not inside the same wrapper — this is what keeps the
        source order reading heading → trip → form on a phone, the order someone actually needs
        it in: what am I doing, what am I booking, now fill this in. It shares the form's `xl:w-2/3`
        so the two line up under the same left edge once the rail is pinned beside them.
    --}}
    <header class="px-4 sm:px-8 2xl:px-12 pt-6 sm:pt-8 xl:w-2/3">
        <a href="{{ route('trips.show', $trip) }}" wire:navigate class="inline-flex items-center py-1.5 -my-1.5 text-sm text-ink/65 hover:text-ink">← {{ $trip->title }}</a>
        <h1 class="font-serif font-semibold text-3xl mt-2">Join this trip</h1>
        <p class="text-ink/65">Takes about a minute. Nothing is charged here — you pay the host directly.</p>
    </header>

    <aside class="px-4 sm:px-8 pt-5 xl:p-0
                  xl:fixed xl:top-[var(--nav-h)] xl:right-0 xl:z-10
                  xl:w-1/3 xl:h-[calc(100dvh-var(--nav-h))]">
        {{--
            Three shapes, one card.

            Stacked on a phone; side-by-side from `sm`, because between a phone and a wide laptop
            this card spans the full page and a 16/9 image at that width was a 540px hero that
            pushed the whole form below the fold — advertising the trip to someone who had already
            chosen it. From xl it becomes the full-height rail: a column that scrolls internally if
            it ever has to, so the summary can never be taller than the screen it sits on.
        --}}
        <div class="rounded-2xl border border-ink/10 bg-surface overflow-hidden shadow-sm sm:flex
                    xl:rounded-none xl:border-0 xl:border-l xl:shadow-none
                    xl:h-full xl:flex-col xl:overflow-y-auto">
            @if($gallery)
                {{--
                    The photo is the flexible part.

                    aspect-ratio on a phone, stretched by the flex row at `sm`, and from xl it is
                    flex-1 — it takes the whole rail minus whatever the details below it need. That
                    is what makes the thumbnail scale with the viewport instead of being a size I
                    guessed. min-h keeps it a photo rather than a sliver on a short screen.
                --}}
                <div class="relative aspect-[16/9] sm:aspect-auto sm:w-60 sm:shrink-0
                            xl:w-auto xl:flex-1 xl:min-h-[15rem]">
                    {{-- One photo renders as a plain image; more than one brings its own arrows,
                         counter and dots. Sized by height rather than ratio because this box is
                         a flex child whose height is decided by the rail, not by the picture. --}}
                    <x-carousel :images="$gallery" ratio="h-full" class="absolute inset-0 w-full h-full" />

                    {{--
                        Two-sided scrim, and it must not swallow clicks.

                        The chips sit over a photo we do not control, so their contrast cannot be
                        left to whatever the image happens to be at that corner. It covers the
                        carousel's own controls, hence pointer-events-none — without it the arrows
                        and dots underneath would look enabled and do nothing.
                    --}}
                    <div class="absolute inset-0 pointer-events-none bg-gradient-to-b from-ink/60 via-transparent to-ink/45"></div>

                    {{-- Top-left, clear of the carousel's counter (top-right) and dots (bottom). --}}
                    <div class="absolute inset-x-0 top-0 p-3 pr-16 flex flex-wrap gap-1.5 pointer-events-none">
                        @if($trip->place?->category)
                            <span class="rounded-full glass-dark px-2.5 py-1 text-[11px] font-semibold text-cream capitalize">{{ $trip->place->category }}</span>
                        @endif
                        @if($trip->effective_difficulty)
                            <span class="rounded-full glass-dark px-2.5 py-1 text-[11px] font-semibold text-cream capitalize">{{ $trip->effective_difficulty }}</span>
                        @endif
                        @if($trip->place?->elevation_m)
                            <span class="rounded-full glass-dark px-2.5 py-1 text-[11px] font-semibold text-cream">{{ number_format($trip->place->elevation_m) }} m</span>
                        @endif
                        @if($trip->reviews_count)
                            <span class="rounded-full glass-dark px-2.5 py-1 text-[11px] font-semibold text-cream inline-flex items-center gap-1">
                                <x-icon.star class="w-3 h-3 text-amber" /> {{ $trip->aggregate_rating }} ({{ $trip->reviews_count }})
                            </span>
                        @endif
                    </div>
                </div>
            @endif

            <div class="p-5 sm:flex-1 sm:min-w-0 xl:flex-none">
                <h2 class="font-serif font-semibold text-xl leading-snug">{{ $trip->title }}</h2>

                <dl class="mt-4 space-y-2.5 text-sm">
                    @if($trip->place)
                        <div class="flex items-start gap-2.5">
                            <dt class="sr-only">Where</dt>
                            <x-icon.pin class="w-4 h-4 mt-0.5 text-moss shrink-0" />
                            <dd class="text-ink/75">{{ $trip->place->name }}<span class="text-ink/65"> · {{ $trip->place->country }}</span></dd>
                        </div>
                    @endif

                    <div class="flex items-start gap-2.5">
                        <dt class="sr-only">When</dt>
                        <x-icon.calendar class="w-4 h-4 mt-0.5 text-moss shrink-0" />
                        <dd class="text-ink/75">
                            <span class="font-semibold text-ink">{{ $departure->starts_at->format('D, j M Y') }}</span>
                            @if($departure->ends_at && ! $departure->ends_at->isSameDay($departure->starts_at))
                                <span class="block text-ink/65">to {{ $departure->ends_at->format('D, j M Y') }}</span>
                            @endif
                        </dd>
                    </div>

                    @if($trip->meetup_point)
                        <div class="flex items-start gap-2.5">
                            <dt class="sr-only">Meeting point</dt>
                            <x-icon.mountain class="w-4 h-4 mt-0.5 text-moss shrink-0" />
                            <dd class="text-ink/75">{{ $trip->meetup_point }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 pt-4 border-t border-ink/8">
                    {{-- One target covering the photo and the name, so the rail is a way
                         through to the host rather than a dead credit. --}}
                    <x-host-link :host="$trip->host" size="md" class="gap-3" subtitle="Hosting" />
                </div>

                {{--
                    The live total. It moved out of the seats card so there is one price on the
                    page rather than two that have to be kept saying the same thing — and being in
                    the pinned rail it stays on screen while the traveller list is filled in.
                --}}
                <div class="mt-4 pt-4 border-t border-ink/8">
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-ink/65">{{ $money($perSeat) }} × {{ $seats }} {{ \Illuminate\Support\Str::plural('seat', $seats) }}</span>
                        <span class="font-serif font-semibold text-xl">{{ $money($total) }}</span>
                    </div>

                    @if($trip->takesDeposit())
                        <div class="flex items-baseline justify-between gap-3 text-sm mt-2 pt-2 border-t border-dashed border-ink/10">
                            <span class="text-ink/65">To reserve now</span>
                            <span class="font-semibold">{{ $money($payableNow) }}</span>
                        </div>
                        <p class="text-xs text-ink/65 mt-1">Balance paid to the host on the day.</p>
                    @endif

                    <div class="flex items-center gap-2 mt-3">
                        <span class="w-1.5 h-1.5 rounded-full {{ $spotsLeft <= 3 ? 'bg-terra' : 'bg-moss' }}"></span>
                        <span class="text-sm {{ $spotsLeft <= 3 ? 'text-terra-deep font-semibold' : 'text-ink/65' }}">
                            {{ $spotsLeft }} {{ \Illuminate\Support\Str::plural('seat', $spotsLeft) }} left
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div class="px-4 sm:px-8 2xl:px-12 pt-5 pb-8 xl:w-2/3">

    @if($error)
        <div class="rounded-xl bg-terra/10 text-terra-deep px-4 py-3 mb-5 text-sm font-semibold" dusk="reg-error">{{ $error }}</div>
    @endif

    {{--
        Error summary.

        A six-seat booking is taller than the viewport, so an inline message on the offending card
        can sit entirely off-screen — the booker presses Confirm, nothing visibly happens, and the
        form looks broken. Every problem is listed here, at the top, each one a link to its field.
    --}}
    @if($errors->any())
        <div role="alert" tabindex="-1" dusk="reg-summary"
             class="rounded-2xl border-2 border-terra/40 bg-terra/5 px-5 py-4 mb-5">
            <p class="font-semibold text-terra mb-2">
                {{ $errors->count() }} {{ \Illuminate\Support\Str::plural('thing', $errors->count()) }} to fix before we can book this
            </p>
            <ul class="space-y-1 text-sm">
                @foreach($errors->getMessages() as $key => $messages)
                    <li>
                        <a href="#{{ $fieldId($key) }}" class="text-ink/80 underline decoration-terra/40 underline-offset-2 hover:text-terra">{{ $messages[0] }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{--
        Steps 1 and 2 share a row from `lg` up.

        The seats card is a stepper and a line of status; a full-width band of its own was the
        single largest stretch of empty pixels on the page, and it pushed everything below it a
        card-height further down.
    --}}
    {{-- ═══ 1 · SEATS ═══ --}}
    {{-- Full width. Boxed into two of five columns it was a title and a 150px stepper marooned
         in a card three times their size, which read as something broken rather than something
         small. Across the column the question and its answer sit at opposite ends of one line. --}}
    <section class="rounded-2xl border border-ink/10 bg-surface p-4 sm:p-5 mb-3 sm:mb-4">
        {{--
            Heading and control on one line.

            Once the price moved to the rail this card held a title and a 150px stepper, so
            stacking them left most of it empty. Side by side it reads as one question with its
            answer next to it.
        --}}
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <span class="grid place-items-center w-6 h-6 rounded-full bg-pine text-cream text-xs font-bold shrink-0">1</span>
                <h2 class="font-serif font-semibold text-lg">How many seats?</h2>
            </div>

            {{--
                A stepper, not a bare number box. Booking one or two seats is the overwhelming
                case and now costs a single tap with no keyboard — which on a phone means no
                numeric pad covering half the form.

                Disabling at the bounds is safe here, unlike on the submit button: the ceiling is
                seats remaining, which the server already knows and no unsynced field can change.
            --}}
            <div class="inline-flex items-center rounded-2xl border border-ink/15 bg-canvas p-1">
                <button type="button" wire:click="removeSeat" dusk="seat-minus"
                        @disabled((int) $seats <= 1) aria-label="One fewer seat"
                        class="grid place-items-center w-11 h-11 rounded-xl text-xl font-semibold text-ink
                               hover:bg-surface disabled:opacity-30 disabled:hover:bg-transparent transition-colors">−</button>

                <input type="number" min="1" max="{{ $maxSeats }}" wire:model.live.debounce.500ms="partySize"
                       dusk="party-size" id="{{ $fieldId('partySize') }}" aria-label="Number of seats"
                       class="w-14 bg-transparent border-0 text-center text-lg font-semibold tabular-nums
                              focus:ring-0 focus:outline-none [appearance:textfield]
                              [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">

                <button type="button" wire:click="addSeat" dusk="seat-plus"
                        @disabled((int) $seats >= $maxSeats) aria-label="One more seat"
                        class="grid place-items-center w-11 h-11 rounded-xl text-xl font-semibold text-ink
                               hover:bg-surface disabled:opacity-30 disabled:hover:bg-transparent transition-colors">+</button>
            </div>

        </div>

        @error('partySize')<p class="text-terra text-sm mt-3 font-semibold">{{ $message }}</p>@enderror

        {{--
            This decides whether a travellers section is needed at all, so it cannot live inside
            one. It used to, and hiding that section on a one-seat booking took the toggle with
            it — leaving a booker who was paying for someone else no way to say so.
        --}}
        <label class="flex items-start gap-3 rounded-xl bg-moss/8 border border-moss/15 p-3.5 mt-4 cursor-pointer hover:bg-moss/12 transition-colors">
            <input type="checkbox" wire:model.live="bookerIsTraveling" dusk="booker-traveling"
                   class="mt-0.5 rounded border-ink/25 text-moss focus:ring-moss">
            <span class="text-sm">
                <b class="font-semibold">I'm joining this trip too</b>
                @unless($bookerIsTraveling)
                    <span class="block text-ink/70 mt-1">
                        You book and pay; {{ $seats }} {{ \Illuminate\Support\Str::plural('person', $seats) }} travel.
                        Your details are for contact only and take no seat.
                    </span>
                @endunless
            </span>
        </label>
    </section>

    {{--
        ═══ 2 · BOOKER ═══

        Shown only when it is not a duplicate of a seat.

        On a one-seat booking the booker is the traveller, so this block alone is the whole
        answer and "Who's going" below is switched off. Add a second seat and the reverse
        applies: seat 1 becomes "You" and collects the same three names, so this card would ask
        for them twice on one screen — it steps aside and the seat carries the contact fields.
        A booker who is not travelling has no seat to hide behind, so it always returns for them.
    --}}
    @php($showBookerCard = $seats === 1 || ! $bookerIsTraveling)
    @if($showBookerCard)
    <section class="rounded-2xl border border-ink/10 bg-surface p-4 sm:p-5 mb-3 sm:mb-4">
        <div class="flex items-baseline gap-3">
            <span class="grid place-items-center w-6 h-6 rounded-full bg-pine text-cream text-xs font-bold shrink-0">2</span>
            <div>
                <h2 class="font-serif font-semibold text-lg">Your details</h2>
                <p class="text-sm text-ink/65">Where the host reaches you about this booking.</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-3 mt-4">
            <label class="block"><span class="field-label">First name</span>
                <input wire:model.blur="contact.first_name" dusk="contact-first" autocomplete="given-name"
                       id="{{ $fieldId('contact.first_name') }}" @error('contact.first_name') aria-invalid="true" @enderror
                       class="field @error('contact.first_name') !border-terra @enderror">
                @error('contact.first_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <label class="block"><span class="field-label">Middle name <span class="font-normal text-ink/65">optional</span></span>
                <input wire:model.blur="contact.middle_name" autocomplete="additional-name" class="field">
            </label>
            <label class="block"><span class="field-label">Last name</span>
                <input wire:model.blur="contact.last_name" dusk="contact-last" autocomplete="family-name"
                       id="{{ $fieldId('contact.last_name') }}" @error('contact.last_name') aria-invalid="true" @enderror
                       class="field @error('contact.last_name') !border-terra @enderror">
                @error('contact.last_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
        </div>

        <div class="grid sm:grid-cols-2 gap-3 mt-3">
            <label class="block"><span class="field-label">Email</span>
                <input type="email" inputmode="email" autocomplete="email" wire:model.blur="contact.email" dusk="contact-email"
                       id="{{ $fieldId('contact.email') }}" @error('contact.email') aria-invalid="true" @enderror
                       class="field @error('contact.email') !border-terra @enderror">
                @error('contact.email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <label class="block"><span class="field-label">Mobile</span>
                <input type="tel" inputmode="tel" autocomplete="tel" wire:model.blur="contact.phone" dusk="contact-phone"
                       placeholder="0917 123 4567" id="{{ $fieldId('contact.phone') }}"
                       @error('contact.phone') aria-invalid="true" @enderror
                       class="field @error('contact.phone') !border-terra @enderror">
                @error('contact.phone')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
        </div>

        {{--
            Birthday and gender come here when there is no travellers section to hold them.

            On a one-seat booking the booker is the only traveller, so hiding "Who's going"
            removed the only place these were ever asked — and a host needs a birthday for
            permits. They keep their seat-0 bindings and dusk hooks, so nothing downstream
            notices they moved.
        --}}
        @if($seats === 1 && $bookerIsTraveling)
            <div class="mt-3 sm:flex sm:items-start sm:gap-5">
                <label class="block sm:w-40 sm:shrink-0"><span class="field-label">Birthday <span class="font-normal text-ink/65">optional</span></span>
                    <input type="date" max="{{ now()->toDateString() }}"
                           wire:model.blur="participants.0.birthday" dusk="seat-0-birthday"
                           id="{{ $fieldId('participants.0.birthday') }}"
                           class="field !px-3 @error('participants.0.birthday') !border-terra @enderror">
                    @error('participants.0.birthday')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>

                <div class="mt-3 sm:mt-0 sm:flex-1 sm:min-w-0">
                    <span class="field-label">Gender <span class="font-normal text-ink/65">optional</span></span>
                    <div class="flex flex-wrap gap-2">
                        @php($current = $participants[0]['gender'] ?? '')
                        @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'undisclosed' => 'Rather not say'] as $value => $label)
                            <button type="button" wire:click="setGender(0, '{{ $value }}')"
                                    aria-pressed="{{ $current === $value ? 'true' : 'false' }}"
                                    class="{{ $chip }} {{ $current === $value ? $chipOn : $chipOff }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Same reasoning as the birthday above: on a one-seat booking there is no travellers
                 section to hold this, and it is required, so it has to be asked here or the form
                 refuses a booking over a field nobody was shown. Keeps the seat-0 binding and
                 hook, so validation and tests find it in either layout. --}}
            <div class="mt-4 pt-4 border-t border-ink/8">
                <span class="field-label">Emergency contact</span>
                <p class="text-xs text-ink/70 -mt-1 mb-2">Someone not on this trip we could call.</p>
                <div class="sm:flex sm:gap-3">
                    <label class="block sm:flex-1 sm:min-w-0">
                        <span class="sr-only">Emergency contact name</span>
                        <input wire:model.blur="participants.0.emergency_contact_name"
                               dusk="seat-0-ec-name" placeholder="Full name"
                               id="{{ $fieldId('participants.0.emergency_contact_name') }}"
                               class="field @error('participants.0.emergency_contact_name') !border-terra @enderror">
                        @error('participants.0.emergency_contact_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                    </label>
                    <label class="block mt-3 sm:mt-0 sm:w-48 sm:shrink-0">
                        <span class="sr-only">Emergency contact mobile</span>
                        <input type="tel" inputmode="tel"
                               wire:model.blur="participants.0.emergency_contact_phone"
                               dusk="seat-0-ec-phone" placeholder="09XX XXX XXXX"
                               id="{{ $fieldId('participants.0.emergency_contact_phone') }}"
                               class="field @error('participants.0.emergency_contact_phone') !border-terra @enderror">
                        @error('participants.0.emergency_contact_phone')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                    </label>
                </div>
            </div>
        @endif
    </section>

    @endif

    {{--
        ═══ 3 · TRAVELLERS ═══

        Hidden on a one-seat booking. There, the only traveller is the person filling the form in,
        and "Your details" above has already asked for every field this section would repeat — a
        whole numbered step whose one card restated the answers directly above it.
    --}}
    @php($showTravellers = $seats > 1 || ! $bookerIsTraveling)
    @if($showTravellers)
    <section class="rounded-2xl border border-ink/10 bg-surface p-4 sm:p-5 mb-3 sm:mb-4">
        <div class="flex items-baseline gap-3">
            <span class="grid place-items-center w-6 h-6 rounded-full bg-pine text-cream text-xs font-bold shrink-0">3</span>
            <div>
                <h2 class="font-serif font-semibold text-lg">Who's going?</h2>
                <p class="text-sm text-ink/65">The host needs a name and an email for every seat.</p>
            </div>
        </div>

        {{--
            Two seats abreast once there is room for it.

            This is where the extra width actually pays: a five-seat booking becomes three rows of
            cards instead of five, and each card still lays its fields out at a comfortable size.
            Widening a single card to the full column instead would have stretched a first-name
            box to several hundred pixels for no gain.
        --}}
        {{-- items-start: the booker's own card is shorter than a full seat, and stretching it to
             match left a band of empty card below the last control. --}}
        <div class="grid 2xl:grid-cols-2 gap-3 mb-3 items-start">
        @foreach($participants as $i => $p)
            @php($isBooker = $bookerIsTraveling && $i === 0)
            @php($seatBad = $errors->has('participants.'.$i.'.first_name')
                            || $errors->has('participants.'.$i.'.last_name')
                            || $errors->has('participants.'.$i.'.email'))

            <div class="rounded-xl border p-4 {{ $seatBad ? 'border-terra/50 bg-terra/[0.03]' : 'border-ink/10' }}" dusk="seat-{{ $i }}">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="grid place-items-center w-7 h-7 rounded-full text-xs font-bold shrink-0
                                 {{ $isBooker ? 'bg-moss text-cream' : 'bg-ink/8 text-ink/70' }}">{{ $i + 1 }}</span>
                    <p class="font-semibold text-sm">{{ $isBooker ? 'You' : 'Seat '.($i + 1) }}</p>
                </div>

                {{--
                    Same three boxes on every card, booker included.

                    Where they write to depends on whether the "Your details" card is on screen.
                    While it is, these mirror it and are disabled — one place to fix a typo rather
                    than two copies that can drift. Once it steps aside (two or more seats) this
                    seat *is* the contact block, so the very same boxes bind straight to it and
                    become editable. Without that swap the booker would have no way to correct
                    their own name anywhere on the page.
                --}}
                @php($bookerOwnsContact = $isBooker && ! $showBookerCard)
                @php($nameModel = fn ($field) => $bookerOwnsContact ? "contact.$field" : "participants.$i.$field")
                @php($nameError = fn ($field) => $bookerOwnsContact ? "contact.$field" : "participants.$i.$field")
                @php($locked = $isBooker && ! $bookerOwnsContact)

                <div class="grid sm:grid-cols-3 gap-3">
                    <label class="block"><span class="field-label">First name</span>
                        <input wire:model.blur="{{ $nameModel('first_name') }}" dusk="seat-{{ $i }}-first"
                               id="{{ $fieldId('participants.'.$i.'.first_name') }}"
                               @disabled($locked)
                               @error($nameError('first_name')) aria-invalid="true" @enderror
                               class="field @error($nameError('first_name')) !border-terra @enderror
                                      {{ $locked ? 'disabled:bg-canvas disabled:text-ink/70 disabled:cursor-not-allowed' : '' }}">
                        @error($nameError('first_name'))<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                    </label>
                    <label class="block"><span class="field-label">Middle <span class="font-normal text-ink/65">optional</span></span>
                        <input wire:model.blur="{{ $nameModel('middle_name') }}"
                               @disabled($locked)
                               class="field {{ $locked ? 'disabled:bg-canvas disabled:text-ink/70 disabled:cursor-not-allowed' : '' }}">
                    </label>
                    <label class="block"><span class="field-label">Last name</span>
                        <input wire:model.blur="{{ $nameModel('last_name') }}" dusk="seat-{{ $i }}-last"
                               id="{{ $fieldId('participants.'.$i.'.last_name') }}"
                               @disabled($locked)
                               @error($nameError('last_name')) aria-invalid="true" @enderror
                               class="field @error($nameError('last_name')) !border-terra @enderror
                                      {{ $locked ? 'disabled:bg-canvas disabled:text-ink/70 disabled:cursor-not-allowed' : '' }}">
                        @error($nameError('last_name'))<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                    </label>
                </div>

                {{-- The contact channel comes with the seat when the card above is gone. --}}
                @if($bookerOwnsContact)
                    <div class="grid sm:grid-cols-2 gap-3 mt-3">
                        <label class="block"><span class="field-label">Email</span>
                            <input type="email" inputmode="email" autocomplete="email"
                                   wire:model.blur="contact.email" dusk="contact-email"
                                   id="{{ $fieldId('contact.email') }}"
                                   @error('contact.email') aria-invalid="true" @enderror
                                   class="field @error('contact.email') !border-terra @enderror">
                            @error('contact.email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        <label class="block"><span class="field-label">Mobile</span>
                            <input type="tel" inputmode="tel" autocomplete="tel"
                                   wire:model.blur="contact.phone" dusk="contact-phone"
                                   placeholder="0917 123 4567" id="{{ $fieldId('contact.phone') }}"
                                   @error('contact.phone') aria-invalid="true" @enderror
                                   class="field @error('contact.phone') !border-terra @enderror">
                            @error('contact.phone')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                    </div>
                @elseif($isBooker && filled($contact['email'] ?? '') && blank($p['email'] ?? ''))
                    {{-- Their address is filled in further down only if they choose to override it;
                         until then, this is the one that will be used. --}}
                    <p class="text-xs text-ink/65 mt-1.5">{{ $contact['email'] }} · from your details above</p>
                @endif

                {{--
                    Birthday and gender share a row from `sm` up.

                    They used to stack, which cost every seat card an extra row for two short
                    optional answers. Gender still gets whatever width is left rather than a fixed
                    share: squeezing the chips into a narrow grid column dropped the fourth option
                    onto a line of its own, where it read as a different kind of control.
                --}}
                <div class="{{ $isBooker ? '' : 'mt-3' }} sm:flex sm:items-start sm:gap-5">
                    {{--
                        Two seat cards abreast leaves this row ~467px and the four gender chips
                        need ~276 of it, so the date box gets 160 and gives up some of its own
                        padding rather than the chip set giving up a line. Below w-40 the native
                        control starts clipping its own "mm/dd/yyyy" placeholder.
                    --}}
                    <label class="block sm:w-40 sm:shrink-0"><span class="field-label">Birthday <span class="font-normal text-ink/65">optional</span></span>
                        <input type="date" max="{{ now()->toDateString() }}"
                               wire:model.blur="participants.{{ $i }}.birthday" dusk="seat-{{ $i }}-birthday"
                               id="{{ $fieldId('participants.'.$i.'.birthday') }}"
                               class="field !px-3 @error('participants.'.$i.'.birthday') !border-terra @enderror">
                        @error('participants.'.$i.'.birthday')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                    </label>

                    <div class="mt-3 sm:mt-0 sm:flex-1 sm:min-w-0">
                        <span class="field-label">Gender <span class="font-normal text-ink/65">optional</span></span>
                        {{--
                            All four options in one wrapping row. The old control hid two of them
                            behind a bare "…", which read as a truncation rather than a button, so
                            the options most in need of being findable were the hardest to find.
                            Chips toggle off, because a mis-tap on an optional question should not
                            need a page reload to undo.
                        --}}
                        <div class="flex flex-wrap gap-2">
                            @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other', 'undisclosed' => 'Rather not say'] as $value => $label)
                                <button type="button" wire:click="setGender({{ $i }}, '{{ $value }}')"
                                        aria-pressed="{{ ($p['gender'] ?? '') === $value ? 'true' : 'false' }}"
                                        class="{{ $chip }} {{ ($p['gender'] ?? '') === $value ? $chipOn : $chipOff }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{--
                    Who to call if this goes wrong.

                    Required, and asked at the moment of booking for the same reason the ID is:
                    it is the only point at which it can be a condition. Chased afterwards, a host
                    finds out there is nobody to ring while standing over somebody with a broken
                    ankle — which is the exact situation the field exists for.

                    Both halves are required together. A name with no number is not a contact and
                    a number with no name is a stranger's phone.
                --}}
                <div class="mt-4 pt-4 border-t border-ink/8">
                    <span class="field-label">Emergency contact</span>
                    <p class="text-xs text-ink/70 -mt-1 mb-2">
                        Someone not on this trip who {{ trim(($p['first_name'] ?? '')) ?: 'this traveller' }} would want called.
                    </p>
                    <div class="sm:flex sm:gap-3">
                        <label class="block sm:flex-1 sm:min-w-0">
                            <span class="sr-only">Emergency contact name</span>
                            <input wire:model.blur="participants.{{ $i }}.emergency_contact_name"
                                   dusk="seat-{{ $i }}-ec-name" placeholder="Full name"
                                   id="{{ $fieldId('participants.'.$i.'.emergency_contact_name') }}"
                                   class="field @error('participants.'.$i.'.emergency_contact_name') !border-terra @enderror">
                            @error('participants.'.$i.'.emergency_contact_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        <label class="block mt-3 sm:mt-0 sm:w-48 sm:shrink-0">
                            <span class="sr-only">Emergency contact mobile</span>
                            <input type="tel" inputmode="tel"
                                   wire:model.blur="participants.{{ $i }}.emergency_contact_phone"
                                   dusk="seat-{{ $i }}-ec-phone" placeholder="09XX XXX XXXX"
                                   id="{{ $fieldId('participants.'.$i.'.emergency_contact_phone') }}"
                                   class="field @error('participants.'.$i.'.emergency_contact_phone') !border-terra @enderror">
                            @error('participants.'.$i.'.emergency_contact_phone')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                    </div>
                </div>

                {{-- Asked here, at the moment of booking, because that is the only point at which
                     it can be made a condition. Collecting it afterwards means a host discovers a
                     missing ID at the permit desk, which is the failure this requirement exists to
                     prevent. Only rendered when the host turned it on. --}}
                @if($trip->requires_id)
                    <div class="mt-4 pt-4 border-t border-ink/8">
                        <x-upload wire:model="ids.{{ $i }}" dusk="seat-{{ $i }}-id" :multiple="false"
                                  label="Photo ID"
                                  prompt="Add {{ trim(($p['first_name'] ?? '')) ?: 'this traveller' }}'s ID"
                                  sub="Passport, licence or any government ID"
                                  hint="{{ $trip->host->name }} needs this for permits. Only they see it." />
                        @error('ids.'.$i)<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if($isBooker)
                    {{-- Their address is already on file from step 2; this is only for overriding it. --}}
                    @if($p['show_contact'] ?? false)
                        <div class="grid sm:grid-cols-2 gap-3 mt-3">
                            <label class="block"><span class="field-label">A different email <span class="font-normal text-ink/65">optional</span></span>
                                <input type="email" inputmode="email" wire:model.blur="participants.{{ $i }}.email"
                                       dusk="seat-{{ $i }}-email" id="{{ $fieldId('participants.'.$i.'.email') }}"
                                       placeholder="{{ $contact['email'] ?? 'name@example.com' }}"
                                       class="field @error('participants.'.$i.'.email') !border-terra @enderror">
                                @error('participants.'.$i.'.email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                            </label>
                            <label class="block"><span class="field-label">Mobile <span class="font-normal text-ink/65">optional</span></span>
                                <input type="tel" inputmode="tel" wire:model.blur="participants.{{ $i }}.phone" class="field">
                            </label>
                        </div>
                    @else
                        <button type="button" wire:click="toggleContactFor({{ $i }})"
                                class="inline-flex items-center gap-1 py-1.5 -my-1.5 text-sm font-semibold text-moss hover:underline mt-3">
                            + Add a different email or mobile
                        </button>
                    @endif
                @else
                    @php($bookerEmail = trim($contact['email'] ?? ''))
                    {{--
                        Email and mobile share a row, and mobile is no longer behind a disclosure.

                        Hiding one optional field cost a row for the toggle link anyway, so the
                        control saved nothing and added a click. Side by side they cost one row
                        between them.
                    --}}
                    <div class="grid sm:grid-cols-2 gap-3 mt-3">
                        <div>
                            {{-- Beside the label, not pushed to the far edge: justify-between put
                                 half a column of gap between the two and broke the association. --}}
                            <div class="flex items-center gap-2.5 mb-2 min-h-[1.75rem]">
                                <span class="block text-sm font-semibold text-ink/70">Email</span>
                                {{--
                                    The rule is only fair because of this button. A booker organising
                                    five friends rarely has five addresses to hand, so a required field
                                    with no shortcut would be a wall. Hidden once the seat already
                                    holds that address — an offer to do something already done reads
                                    as a control that does not work.
                                --}}
                                @if(filled($bookerEmail) && ($p['email'] ?? '') !== $bookerEmail)
                                    <button type="button" wire:click="useBookerEmail({{ $i }})"
                                            dusk="seat-{{ $i }}-use-my-email"
                                            class="shrink-0 rounded-full border border-moss/30 bg-moss/8 px-2.5 py-1
                                                   text-xs font-semibold text-moss hover:bg-moss/15 transition-colors">
                                        Use account email
                                    </button>
                                @endif
                            </div>
                            <input type="email" inputmode="email" wire:model.blur="participants.{{ $i }}.email"
                                   dusk="seat-{{ $i }}-email" id="{{ $fieldId('participants.'.$i.'.email') }}"
                                   placeholder="name@example.com"
                                   @error('participants.'.$i.'.email') aria-invalid="true" @enderror
                                   class="field @error('participants.'.$i.'.email') !border-terra @enderror">
                            @error('participants.'.$i.'.email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <label class="block">
                            {{-- min-h matches the email label row so the two inputs align --}}
                            <span class="field-label flex items-center min-h-[1.75rem] !mb-2">Their mobile <span class="font-normal text-ink/65 ml-1">optional</span></span>
                            <input type="tel" inputmode="tel" wire:model.blur="participants.{{ $i }}.phone" class="field">
                        </label>
                    </div>
                @endif
            </div>
        @endforeach
        </div>

        <p class="text-sm text-ink/65">
            Booking for friends? Add seats above. If you don't have their email, tap
            <b class="font-semibold text-ink/75">Use account email</b> and the host will reach them through you.
        </p>
    </section>
    @endif

    {{--
        ═══ 4 · CONFIRM ═══

        A row rather than a stack on a laptop: the account offer takes the width it needs for its
        two lines of prose, and the button sits beside it instead of below a band of empty card.
    --}}
    {{-- Pinned to the bottom of the phone viewport. The form is long enough that the button
         was often a screen or two below whatever the joiner was filling in; sticky keeps the way
         forward on screen without a second copy of the button to keep in sync. --}}
    {{-- The account offer stays in normal flow. Pinning it along with the button made the sticky
         block a third of a phone screen tall, which is a panel that follows you rather than a bar.
         Only the thing you press should be always-on. --}}
    @unless($this->isSignedIn)
        <label class="flex lg:hidden items-start gap-3 rounded-2xl bg-moss/8 border border-moss/15 p-4 mb-3 cursor-pointer">
            <input type="checkbox" wire:model="createAccount" dusk="create-account-mobile"
                   class="mt-0.5 rounded border-ink/25 text-moss focus:ring-moss">
            <span class="text-sm">
                <b class="font-semibold">Create my Terra account</b>
                <span class="block text-ink/70 mt-0.5">Track this trip, chat with your group, and rebook in one tap. No password needed.</span>
            </span>
        </label>
    @endunless

    <section class="rounded-2xl border border-ink/10 bg-surface p-4 sm:p-5 lg:flex lg:items-center lg:gap-6
                    max-lg:sticky max-lg:bottom-0 max-lg:z-20 max-lg:shadow-[0_-8px_24px_-12px_rgba(31,28,21,.25)]">
        @if($this->isSignedIn)
            {{-- Signed in there is no account offer, so the reassurance takes the left side rather
                 than leaving the button to stretch across the whole column as a 1100px pill. --}}
            <p class="hidden lg:block lg:flex-1 text-sm text-ink/65">
                Nothing is charged here. You'll get {{ $trip->host->name }}'s payment details on the next screen,
                and your seat is held while you pay.
            </p>
        @else
            <label class="hidden lg:flex items-start gap-3 rounded-xl bg-moss/8 border border-moss/15 p-4 mb-4 lg:mb-0 lg:flex-1 cursor-pointer hover:bg-moss/12 transition-colors">
                <input type="checkbox" wire:model="createAccount" dusk="create-account"
                       class="mt-0.5 rounded border-ink/25 text-moss focus:ring-moss">
                <span class="text-sm">
                    <b class="font-semibold">Create my Terra account</b>
                    <span class="block text-ink/70 mt-0.5">Track this trip, chat with your group, and rebook in one tap. No password needed.</span>
                </span>
            </label>
        @endif

        <div class="lg:w-80 lg:shrink-0">

        {{--
            Deliberately never disabled.

            Every field here syncs on blur, so the moment someone fills the last box and clicks
            straight through, the click lands before the sync — on a button that a disabled state
            would have switched off a frame too late. They would press it, watch it go live, and
            have to press again. Validation is the gate; this button's job is to run it and say
            plainly what is missing.
        --}}
        <button wire:click="review" wire:loading.attr="disabled" wire:target="review" dusk="submit-registration"
                class="w-full rounded-full bg-pine text-cream py-4 font-semibold text-base hover:bg-pine/90
                       disabled:opacity-60 transition-colors">
            <span wire:loading.remove wire:target="review">Review booking · {{ $money($payableNow) }}</span>
            <span wire:loading wire:target="review">Checking your {{ \Illuminate\Support\Str::plural('seat', $seats) }}…</span>
        </button>

            <p class="hidden lg:block text-xs text-ink/65 text-center mt-3 {{ $this->isSignedIn ? 'lg:hidden' : '' }}">
                No payment now. You'll get the host's payment details on the next screen.
                @unless($this->isSignedIn) Booking as a guest? Untick the box. @endunless
            </p>
        </div>
    </section>

    {{--
        ═══ REVIEW BEFORE BOOKING ═══

        Rendered only once review() has validated, so this is always a summary of something that
        will actually go through. It restates the three things a joiner cannot check anywhere else
        at once: which departure, who is on it, and what the money is.
    --}}
    @if($showReview)
        <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center"
             x-data x-on:keydown.escape.window="$wire.closeReview()"
             x-init="document.body.classList.add('overflow-hidden')"
             x-on:livewire:navigating.window="document.body.classList.remove('overflow-hidden')"
             role="dialog" aria-modal="true" aria-labelledby="review-title" dusk="review-sheet">

            <div class="absolute inset-0 bg-ink/50 backdrop-blur-[2px]" wire:click="closeReview" aria-hidden="true"></div>

            <div class="confirm-pop relative w-full sm:max-w-lg max-h-[92dvh] flex flex-col
                        bg-surface rounded-t-3xl sm:rounded-2xl shadow-2xl sm:m-4">

                <div class="flex items-start gap-3 p-5 sm:p-6 pb-4 shrink-0">
                    <div class="min-w-0 flex-1">
                        <p class="marker text-[10px] font-semibold text-moss">Check before booking</p>
                        <h2 id="review-title" class="font-serif font-semibold text-xl leading-snug mt-1">{{ $trip->title }}</h2>
                        <p class="text-sm text-ink/65 mt-1">
                            {{ $departure->starts_at->format('D, j M Y') }}@if($departure->ends_at && ! $departure->ends_at->isSameDay($departure->starts_at)) → {{ $departure->ends_at->format('j M') }}@endif
                            @if($trip->place) · {{ $trip->place->name }} @endif
                        </p>
                    </div>
                    <button type="button" wire:click="closeReview" aria-label="Close"
                            class="shrink-0 grid place-items-center w-9 h-9 rounded-full text-ink/65 hover:bg-ink/5 hover:text-ink transition-colors">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <div class="px-5 sm:px-6 pb-5 sm:pb-6 overflow-y-auto">
                    <div class="rounded-xl border border-ink/10 divide-y divide-ink/8">
                        @foreach($participants as $i => $p)
                            @php($who = trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? '')))
                            <div class="flex items-center gap-3 px-4 py-3">
                                <span class="grid place-items-center w-7 h-7 rounded-full text-xs font-bold shrink-0
                                             {{ $bookerIsTraveling && $i === 0 ? 'bg-moss text-cream' : 'bg-ink/8 text-ink/70' }}">{{ $i + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-sm truncate">{{ $who ?: 'Seat '.($i + 1) }}</p>
                                    <p class="text-xs text-ink/65 truncate">
                                        {{ ($bookerIsTraveling && $i === 0 ? ($p['email'] ?: ($contact['email'] ?? '')) : $p['email']) ?: 'no email' }}
                                    </p>
                                </div>
                                @if($bookerIsTraveling && $i === 0)
                                    <span class="text-[11px] font-bold text-moss shrink-0">you</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 rounded-xl bg-canvas p-4">
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-ink/70">{{ $money($perSeat) }} × {{ $seats }} {{ \Illuminate\Support\Str::plural('seat', $seats) }}</span>
                            <span class="font-semibold tabular-nums">{{ $money($total) }}</span>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 mt-2.5 pt-2.5 border-t border-ink/10">
                            <span class="font-semibold">Total</span>
                            <span class="font-serif font-semibold text-xl tabular-nums">{{ $money($total) }}</span>
                        </div>
                        @if($trip->takesDeposit())
                            <div class="flex items-baseline justify-between gap-3 mt-2.5 pt-2.5 border-t border-dashed border-ink/15 text-sm">
                                <span class="text-ink/70">To reserve now</span>
                                <span class="font-semibold tabular-nums">{{ $money($payableNow) }}</span>
                            </div>
                            <p class="text-xs text-ink/65 mt-1">Balance of {{ $money($total - $payableNow) }} paid to {{ $trip->host->name }} on the day.</p>
                        @endif
                    </div>

                    <p class="text-xs text-ink/65 mt-3">
                        Nothing is charged here. You'll get {{ $trip->host->name }}'s payment details on the next screen.
                    </p>

                    <div class="flex items-center gap-2 mt-5">
                        <button type="button" wire:click="closeReview"
                                class="rounded-full border border-ink/20 px-5 min-h-[48px] text-sm font-semibold hover:border-ink/40 transition-colors">
                            Back
                        </button>
                        <button wire:click="submit" wire:loading.attr="disabled" wire:target="submit" dusk="confirm-booking"
                                class="flex-1 rounded-full bg-pine text-cream min-h-[48px] font-semibold hover:bg-pine/90 disabled:opacity-60 transition-colors">
                            <span wire:loading.remove wire:target="submit">Confirm registration · {{ $money($payableNow) }}</span>
                            <span wire:loading wire:target="submit">Booking your {{ \Illuminate\Support\Str::plural('seat', $seats) }}…</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    </div>{{-- end form column --}}
</div>{{-- end checkout wrapper --}}

@endif
