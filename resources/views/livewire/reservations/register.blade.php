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
@else

{{--
    A checkout: form on the left, what you are buying pinned on the right.

    Two thirds / one third of the viewport, both panes at least a screen tall, and the rail pinned
    under the nav so the trip and its total never leave the screen no matter how many travellers
    get added below. Nothing here is a fixed pixel width — the columns are fractions of whatever
    display it lands on, and the rail's photo takes whatever height is left after its own text, so
    a short laptop screen and a tall monitor both fill correctly rather than one of them cropping.

    The rail is written first in the source. On a phone, where the grid collapses to a single
    column, that puts the trip and its price above the form they belong to; from xl the explicit
    column placement moves it to the right without a second copy of the markup existing.
--}}
<div class="xl:grid xl:grid-cols-3 xl:grid-rows-[auto_1fr] xl:min-h-[calc(100dvh-var(--nav-h))]">

    {{--
        The heading is its own grid child rather than the first thing in the form column.

        That is what lets the source order read heading → trip → form on a phone, which is the
        order someone actually needs it in: what am I doing, what am I booking, now fill this in.
        On xl it takes the top of the left column and the rail spans past it to the full height.
    --}}
    <header class="px-5 sm:px-8 2xl:px-12 pt-8 xl:col-start-1 xl:col-span-2 xl:row-start-1">
        <a href="{{ route('trips.show', $trip) }}" wire:navigate class="inline-flex items-center py-1.5 -my-1.5 text-sm text-ink/65 hover:text-ink">← {{ $trip->title }}</a>
        <h1 class="font-serif font-semibold text-3xl mt-2">Join this trip</h1>
        <p class="text-ink/65">Takes about a minute. Nothing is charged here — you pay the host directly.</p>
    </header>

    <aside class="px-5 sm:px-8 pt-6 xl:p-0
                  xl:col-start-3 xl:row-start-1 xl:row-span-2 xl:self-start
                  xl:sticky xl:top-[var(--nav-h)] xl:h-[calc(100dvh-var(--nav-h))]">
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
                            <dd class="text-ink/75">{{ $trip->place->name }}<span class="text-ink/50"> · {{ $trip->place->country }}</span></dd>
                        </div>
                    @endif

                    <div class="flex items-start gap-2.5">
                        <dt class="sr-only">When</dt>
                        <x-icon.calendar class="w-4 h-4 mt-0.5 text-moss shrink-0" />
                        <dd class="text-ink/75">
                            <span class="font-semibold text-ink">{{ $departure->starts_at->format('D, j M Y') }}</span>
                            @if($departure->ends_at && ! $departure->ends_at->isSameDay($departure->starts_at))
                                <span class="block text-ink/60">to {{ $departure->ends_at->format('D, j M Y') }}</span>
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

                <div class="flex items-center gap-3 mt-4 pt-4 border-t border-ink/8">
                    <span class="grid place-items-center w-9 h-9 rounded-full bg-pine text-cream font-serif font-semibold shrink-0">{{ strtoupper(substr($trip->host->name, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold truncate">{{ $trip->host->name }}</div>
                        <div class="text-xs text-ink/60">
                            Hosting
                            @if($trip->host->hostProfile?->trips_count)· {{ $trip->host->hostProfile->trips_count }} trips led @endif
                        </div>
                    </div>
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
                        <p class="text-xs text-ink/55 mt-1">Balance paid to the host on the day.</p>
                    @endif

                    <div class="flex items-center gap-2 mt-3">
                        <span class="w-1.5 h-1.5 rounded-full {{ $spotsLeft <= 3 ? 'bg-terra' : 'bg-moss' }}"></span>
                        <span class="text-sm {{ $spotsLeft <= 3 ? 'text-terra font-semibold' : 'text-ink/65' }}">
                            {{ $spotsLeft }} {{ \Illuminate\Support\Str::plural('seat', $spotsLeft) }} left
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div class="px-5 sm:px-8 2xl:px-12 pt-6 pb-8 xl:col-start-1 xl:col-span-2 xl:row-start-2">

    @if($error)
        <div class="rounded-xl bg-terra/10 text-terra px-4 py-3 mb-5 text-sm font-semibold" dusk="reg-error">{{ $error }}</div>
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
    <div class="grid lg:grid-cols-5 gap-4 mb-4">

    {{-- ═══ 1 · SEATS ═══ --}}
    {{-- self-start: stretching it to Your details' height opened a hole under the stepper --}}
    <section class="lg:col-span-2 lg:self-start rounded-2xl border border-ink/10 bg-surface p-5">
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
    </section>

    {{-- ═══ 2 · BOOKER ═══ --}}
    <section class="lg:col-span-3 rounded-2xl border border-ink/10 bg-surface p-5">
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
            <label class="block"><span class="field-label">Middle name <span class="font-normal text-ink/45">optional</span></span>
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
    </section>

    </div>{{-- end steps 1+2 row --}}

    {{-- ═══ 3 · TRAVELLERS ═══ --}}
    <section class="rounded-2xl border border-ink/10 bg-surface p-5 mb-4">
        <div class="flex items-baseline gap-3">
            <span class="grid place-items-center w-6 h-6 rounded-full bg-pine text-cream text-xs font-bold shrink-0">3</span>
            <div>
                <h2 class="font-serif font-semibold text-lg">Who's going?</h2>
                <p class="text-sm text-ink/65">The host needs a name and an email for every seat.</p>
            </div>
        </div>

        <label class="flex items-start gap-3 rounded-xl bg-moss/8 border border-moss/15 p-4 my-4 cursor-pointer hover:bg-moss/12 transition-colors">
            <input type="checkbox" wire:model.live="bookerIsTraveling" dusk="booker-traveling"
                   class="mt-0.5 rounded border-ink/25 text-moss focus:ring-moss">
            <span class="text-sm">
                <b class="font-semibold">I'm joining this trip too</b>
                @unless($bookerIsTraveling)
                    <span class="block text-ink/70 mt-1">
                        You book and pay; {{ $seats }} {{ \Illuminate\Support\Str::plural('person', $seats) }} travel.
                        Your details above are for contact only and take no seat.
                    </span>
                @endunless
            </span>
        </label>

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

                @if($isBooker)
                    {{--
                        The booker's own seat mirrors the contact block, so rendering the name boxes
                        again asked for the same three answers twice on one screen — the single
                        biggest source of clutter on this page. Shown as a read-only summary now;
                        the seat still collects what it alone adds, the birthday and gender.
                    --}}
                    <div class="rounded-lg bg-canvas px-3 py-2.5 mb-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold truncate">
                                {{ trim(($contact['first_name'] ?? '').' '.($contact['last_name'] ?? '')) ?: 'Name from your details above' }}
                            </span>
                            <span class="text-xs text-ink/50 shrink-0">from step 2</span>
                        </div>
                        @if(filled($contact['email'] ?? '') && blank($p['email'] ?? ''))
                            <p class="text-xs text-ink/60 mt-0.5 truncate">{{ $contact['email'] }}</p>
                        @endif
                    </div>
                @else
                    <div class="grid sm:grid-cols-3 gap-3">
                        <label class="block"><span class="field-label">First name</span>
                            <input wire:model.blur="participants.{{ $i }}.first_name" dusk="seat-{{ $i }}-first"
                                   id="{{ $fieldId('participants.'.$i.'.first_name') }}"
                                   @error('participants.'.$i.'.first_name') aria-invalid="true" @enderror
                                   class="field @error('participants.'.$i.'.first_name') !border-terra @enderror">
                            @error('participants.'.$i.'.first_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                        <label class="block"><span class="field-label">Middle <span class="font-normal text-ink/45">optional</span></span>
                            <input wire:model.blur="participants.{{ $i }}.middle_name" class="field">
                        </label>
                        <label class="block"><span class="field-label">Last name</span>
                            <input wire:model.blur="participants.{{ $i }}.last_name" dusk="seat-{{ $i }}-last"
                                   id="{{ $fieldId('participants.'.$i.'.last_name') }}"
                                   @error('participants.'.$i.'.last_name') aria-invalid="true" @enderror
                                   class="field @error('participants.'.$i.'.last_name') !border-terra @enderror">
                            @error('participants.'.$i.'.last_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        </label>
                    </div>
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
                    <label class="block sm:w-40 sm:shrink-0"><span class="field-label">Birthday <span class="font-normal text-ink/45">optional</span></span>
                        <input type="date" max="{{ now()->toDateString() }}"
                               wire:model.blur="participants.{{ $i }}.birthday" dusk="seat-{{ $i }}-birthday"
                               id="{{ $fieldId('participants.'.$i.'.birthday') }}"
                               class="field !px-3 @error('participants.'.$i.'.birthday') !border-terra @enderror">
                        @error('participants.'.$i.'.birthday')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                    </label>

                    <div class="mt-3 sm:mt-0 sm:flex-1 sm:min-w-0">
                        <span class="field-label">Gender <span class="font-normal text-ink/45">optional</span></span>
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

                @if($isBooker)
                    {{-- Their address is already on file from step 2; this is only for overriding it. --}}
                    @if($p['show_contact'] ?? false)
                        <div class="grid sm:grid-cols-2 gap-3 mt-3">
                            <label class="block"><span class="field-label">A different email <span class="font-normal text-ink/45">optional</span></span>
                                <input type="email" inputmode="email" wire:model.blur="participants.{{ $i }}.email"
                                       dusk="seat-{{ $i }}-email" id="{{ $fieldId('participants.'.$i.'.email') }}"
                                       placeholder="{{ $contact['email'] ?? 'name@example.com' }}"
                                       class="field @error('participants.'.$i.'.email') !border-terra @enderror">
                                @error('participants.'.$i.'.email')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                            </label>
                            <label class="block"><span class="field-label">Mobile <span class="font-normal text-ink/45">optional</span></span>
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
                            <span class="field-label flex items-center min-h-[1.75rem] !mb-2">Their mobile <span class="font-normal text-ink/45 ml-1">optional</span></span>
                            <input type="tel" inputmode="tel" wire:model.blur="participants.{{ $i }}.phone" class="field">
                        </label>
                    </div>
                @endif
            </div>
        @endforeach
        </div>

        <p class="text-sm text-ink/60">
            Booking for friends? Add seats above. If you don't have their email, tap
            <b class="font-semibold text-ink/75">Use account email</b> and the host will reach them through you.
        </p>
    </section>

    {{--
        ═══ 4 · CONFIRM ═══

        A row rather than a stack on a laptop: the account offer takes the width it needs for its
        two lines of prose, and the button sits beside it instead of below a band of empty card.
    --}}
    <section class="rounded-2xl border border-ink/10 bg-surface p-5 lg:flex lg:items-center lg:gap-6">
        @if($this->isSignedIn)
            {{-- Signed in there is no account offer, so the reassurance takes the left side rather
                 than leaving the button to stretch across the whole column as a 1100px pill. --}}
            <p class="hidden lg:block lg:flex-1 text-sm text-ink/65">
                Nothing is charged here. You'll get {{ $trip->host->name }}'s payment details on the next screen,
                and your seat is held while you pay.
            </p>
        @else
            <label class="flex items-start gap-3 rounded-xl bg-moss/8 border border-moss/15 p-4 mb-4 lg:mb-0 lg:flex-1 cursor-pointer hover:bg-moss/12 transition-colors">
                <input type="checkbox" wire:model="createAccount" dusk="create-account"
                       class="mt-0.5 rounded border-ink/25 text-moss focus:ring-moss">
                <span class="text-sm">
                    <b class="font-semibold">Create my Peeps account</b>
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
        <button wire:click="submit" wire:loading.attr="disabled" wire:target="submit" dusk="submit-registration"
                class="w-full rounded-full bg-pine text-cream py-4 font-semibold text-base hover:bg-pine/90
                       disabled:opacity-60 transition-colors">
            <span wire:loading.remove wire:target="submit">Confirm registration · {{ $money($payableNow) }}</span>
            <span wire:loading wire:target="submit">Booking your {{ \Illuminate\Support\Str::plural('seat', $seats) }}…</span>
        </button>

            <p class="text-xs text-ink/55 text-center mt-3 {{ $this->isSignedIn ? 'lg:hidden' : '' }}">
                No payment now. You'll get the host's payment details on the next screen.
                @unless($this->isSignedIn) Booking as a guest? Untick the box. @endunless
            </p>
        </div>
    </section>

    </div>{{-- end form column --}}
</div>{{-- end checkout grid --}}

@endif
