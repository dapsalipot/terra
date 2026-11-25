@props([
    'action',                      // Livewire call to run on confirm, e.g. "confirm(12)"
    'title',
    'body' => null,
    'confirmLabel' => 'Confirm',
    'cancelLabel' => 'Cancel',
    'tone' => 'terra',             // terra = destructive/irreversible · moss = affirmative
    'icon' => 'warning',           // warning · money · trash
])

{{--
    A branded confirmation step in front of one Livewire call.

    Replaces two different things that were both wrong: critical actions with no guard at all
    (marking a booking paid, cancelling a reservation) and `wire:confirm`, which raises the
    browser's own alert — unstyled, unbrandable, and stripped of the context that makes a
    confirmation worth reading in the first place.

    The gate is presentational, not a security control. Anyone can still reach the underlying
    Livewire method from the console, so authorisation stays where it already is, in the component
    and the service. This exists to stop an accidental click, not a determined one.

    Teleported to <body> so a parent with overflow-hidden or its own stacking context — a roster
    card, the pinned booking rail — cannot clip or bury it.
--}}

@php
    $tones = [
        'terra' => ['bg-terra/10 text-terra', 'bg-terra hover:bg-terra/90'],
        'moss' => ['bg-moss/12 text-pine', 'bg-moss hover:bg-moss/90'],
    ];
    [$iconTone, $buttonTone] = $tones[$tone] ?? $tones['terra'];

    $icons = [
        'warning' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/>',
        'money' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    ];
    $iconPath = $icons[$icon] ?? $icons['warning'];
    $labelId = 'confirm-'.\Illuminate\Support\Str::random(8);
@endphp

<div x-data="{ open: false }" class="contents">
    <button type="button" x-on:click="open = true" {{ $attributes }}>{{ $slot }}</button>

    <template x-teleport="body">
        {{--
            x-show only — deliberately no x-transition anywhere in here.

            Alpine's transitions do not survive x-teleport: the element ends up with
            `display: none; opacity: 0` and the transition properties set, then never advances,
            so the dialog "opens" as an invisible box that swallows the page. Verified by reading
            the stuck inline style rather than guessing. The entrance is a CSS keyframe instead
            (see .confirm-pop in app.css), which replays on its own every time display flips.
        --}}
        <div x-show="open" x-cloak
             x-on:keydown.escape.window="open = false"
             {{-- Toggling both ways, so closing the dialog gives the page its scroll back. --}}
             x-effect="document.body.classList.toggle('overflow-hidden', open)"
             x-on:livewire:navigating.window="document.body.classList.remove('overflow-hidden')"
             class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center"
             role="dialog" aria-modal="true" aria-labelledby="{{ $labelId }}">

            <div x-on:click="open = false"
                 class="absolute inset-0 bg-ink/50 backdrop-blur-[2px]" aria-hidden="true"></div>

            <div class="confirm-pop relative w-full sm:max-w-md bg-surface rounded-t-3xl sm:rounded-2xl shadow-2xl sm:m-4 p-5 sm:p-6">

                <div class="flex items-start gap-3.5">
                    <span class="grid place-items-center w-10 h-10 rounded-full shrink-0 {{ $iconTone }}">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconPath !!}</svg>
                    </span>
                    <div class="min-w-0">
                        <h3 id="{{ $labelId }}" class="font-semibold text-base leading-snug">{{ $title }}</h3>
                        @if($body)
                            <p class="text-sm text-ink/70 mt-1.5 leading-relaxed">{{ $body }}</p>
                        @endif
                    </div>
                </div>

                {{--
                    An optional record of what is being confirmed.

                    A sentence is enough for "cancel this booking?", but not for a money decision:
                    a host marking someone paid should be looking at the reference, the amount and
                    the receipt while they decide, not recalling them from the row behind the
                    dialog. Callers that pass nothing are unchanged.
                --}}
                @isset($details)
                    <div class="mt-4 rounded-xl border border-ink/12 bg-cream/60 p-3.5 max-h-[45vh] overflow-y-auto">
                        {{ $details }}
                    </div>
                @endisset

                <div class="flex items-center justify-end gap-2 mt-6 pt-4 border-t border-ink/8">
                    <button type="button" x-on:click="open = false"
                            class="rounded-full border border-ink/20 px-5 min-h-[44px] text-sm font-semibold hover:border-ink/40 transition-colors">
                        {{ $cancelLabel }}
                    </button>
                    {{-- x-ref + $nextTick focus: opening straight onto the confirm button would
                         make Enter-after-click destructive, so focus lands here only once the
                         dialog is actually on screen and the user has had to look at it. --}}
                    {{-- Hook derived from the trigger's own, so "confirm-25" gates "confirm-25-yes".
                         Several of these modals can be in the DOM at once — one per row — and a
                         shared hook would let a test click a hidden one belonging to another row. --}}
                    <button type="button"
                            @if($attributes->get('dusk')) dusk="{{ $attributes->get('dusk') }}-yes" @endif
                            {{--
                                Parentheses added when the caller left them off.

                                `action="cancel"` rendered `$wire.cancel`, which is a property
                                reference — Alpine evaluated it, nothing was called, and the button
                                silently did nothing. Every other call site happened to pass
                                `method(args)`, so the one that did not was the only broken one.
                                Normalising here kills the whole class rather than the instance.
                            --}}
                            x-on:click="open = false; $wire.{{ \Illuminate\Support\Str::contains($action, '(') ? $action : $action.'()' }}"
                            class="rounded-full text-cream px-5 min-h-[44px] text-sm font-semibold transition-colors {{ $buttonTone }}">
                        {{ $confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
