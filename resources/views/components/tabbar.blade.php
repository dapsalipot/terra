@props([
    // [['label' => 'Home', 'href' => '/', 'icon' => 'home', 'active' => true]]
    //   'action' => true   raised primary action (a filled disc), never marked active
    //   'click'  => '...'  an Alpine expression: renders a button instead of a link (the "More" sheet)
    //   'dusk'   => '...'  test hook
    'tabs',
    'tone' => 'light',   // light: over canvas · dark: over the pine host console
    'name' => 'site',    // keys the remembered position, so each bar slides from where it last was
    'label' => 'Main',
])

{{--
    The bottom tab bar a phone user already knows from every native app.

    It only exists below `md`; desktop keeps the top nav. Five things make it feel native rather
    than like a footer:
      · it stays put, docked to the bottom edge, and pads for the home indicator
      · the active tab is marked by a pill that slides to its new place, not by a colour that flips
      · a press shrinks the icon under the thumb, and landing on a tab gives it a small pop
      · it steps out of the way while the keyboard is up, instead of riding over the input
      · a tap buzzes on the phones that allow it

    The pill slides across a page load by remembering the last position in sessionStorage: the new
    page paints the pill where it was, then moves it. That works whether the click was a
    wire:navigate or a full load, and survives login or logout swapping which tabs exist.
--}}

@php
    $icons = [
        'home' => '<path d="M4 11.2 12 4.6l8 6.6V19a1.6 1.6 0 0 1-1.6 1.6H5.6A1.6 1.6 0 0 1 4 19z"/><path d="M9.8 20.6v-5.4h4.4v5.4"/>',
        'compass' => '<circle cx="12" cy="12" r="8.6"/><path d="m15.6 8.4-2 5.2-5.2 2 2-5.2z"/>',
        'users' => '<circle cx="9" cy="8.4" r="3.2"/><path d="M3.4 19.6c0-3.1 2.5-5 5.6-5s5.6 1.9 5.6 5"/><path d="M15.6 5.4a3 3 0 0 1 0 6"/><path d="M17.4 14.9c2 .5 3.2 2.1 3.2 4.7"/>',
        'map' => '<path d="M9 5 4.5 7v12L9 17l6 2 4.5-2V5L15 7z"/><path d="M9 5v12M15 7v12"/>',
        'user' => '<circle cx="12" cy="8.2" r="3.7"/><path d="M4.6 20.2c0-4.1 3.4-6.2 7.4-6.2s7.4 2.1 7.4 6.2"/>',
        'login' => '<path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15"/><path d="M4 12h10M10 8l4 4-4 4"/>',
        'grid' => '<rect x="4" y="4" width="6.6" height="8" rx="1.6"/><rect x="13.4" y="4" width="6.6" height="4.6" rx="1.6"/><rect x="13.4" y="11.6" width="6.6" height="8.4" rx="1.6"/><rect x="4" y="15.4" width="6.6" height="4.6" rx="1.6"/>',
        'plus' => '<path d="M12 5.5v13M5.5 12h13"/>',
        'dots' => '<circle cx="5.5" cy="12" r="1.3" fill="currentColor"/><circle cx="12" cy="12" r="1.3" fill="currentColor"/><circle cx="18.5" cy="12" r="1.3" fill="currentColor"/>',
    ];

    $activeAt = collect($tabs)->search(fn ($t) => ! empty($t['active']) && empty($t['action']));
    $activeAt = $activeAt === false ? -1 : $activeAt;
@endphp

<nav aria-label="{{ $label }}" data-tone="{{ $tone }}" data-tabbar="{{ $name }}"
     x-data="{
        n: {{ count($tabs) }}, at: {{ $activeAt }}, ready: false, typing: false,
        init() {
            const key = 'tarana.tab.{{ $name }}', to = this.at;
            let from = -1;
            try { from = Number(sessionStorage.getItem(key) ?? -1); } catch (e) {}
            const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (to >= 0 && from >= 0 && from < this.n && from !== to && ! still) {
                this.at = from;
                requestAnimationFrame(() => requestAnimationFrame(() => { this.ready = true; this.at = to; }));
            } else {
                this.ready = true;
            }

            try { if (to >= 0) sessionStorage.setItem(key, String(to)); } catch (e) {}
        },
     }"
     @focusin.window="typing = $event.target.matches('input:not([type=checkbox],[type=radio],[type=range],[type=file],[type=button],[type=submit],[type=hidden]),textarea,select,[contenteditable=true]')"
     @focusout.window="typing = false"
     :class="{ 'is-away': typing }"
     class="tabbar md:hidden fixed inset-x-0 bottom-0 z-40">

    <div class="tabbar-track relative grid h-[60px]" style="grid-template-columns: repeat({{ count($tabs) }}, minmax(0, 1fr));">
        {{-- Behind the icons. Hidden until Alpine places it, so it never flashes at the wrong tab. --}}
        <span x-cloak class="tabbar-pill" :class="{ 'is-ready': ready, 'is-none': at < 0 }" :style="`--n:${n};--at:${Math.max(at, 0)}`" aria-hidden="true"></span>

        @foreach($tabs as $tab)
            @php
                $isAction = ! empty($tab['action']);
                $classes = 'tab '.(! empty($tab['active']) && ! $isAction ? 'is-active' : '').($isAction ? ' is-action' : '');
                $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$tab['icon']] ?? '').'</svg>';
            @endphp

            @if(! empty($tab['click']))
                <button type="button" @click="navigator.vibrate?.(8); {{ $tab['click'] }}" class="{{ $classes }}"
                        @if(! empty($tab['dusk'])) dusk="{{ $tab['dusk'] }}" @endif>
                    <span class="tab-icon">{!! $svg !!}</span>
                    <span class="tab-label">{{ $tab['label'] }}</span>
                </button>
            @else
                <a href="{{ $tab['href'] }}" wire:navigate @click="navigator.vibrate?.(8)" class="{{ $classes }}"
                   @if(! empty($tab['active']) && ! $isAction) aria-current="page" @endif
                   @if(! empty($tab['dusk'])) dusk="{{ $tab['dusk'] }}" @endif>
                    <span class="tab-icon">{!! $svg !!}</span>
                    <span class="tab-label">{{ $tab['label'] }}</span>
                </a>
            @endif
        @endforeach
    </div>
</nav>
