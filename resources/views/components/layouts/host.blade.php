<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2C3826">
    <link rel="preload" href="/fonts/geist.woff2" as="font" type="font/woff2" crossorigin>
    <x-brand-head />
    <title>{{ $title ?? 'Tarana · Host' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="host-ui min-h-full font-sans antialiased bg-canvas text-ink has-tabbar">
@php($u = auth()->user())

{{-- One nav definition: the desktop sidebar renders all of it, the phone splits it between the tab bar and the More sheet. --}}
@php($nav = array_filter([
    ['host.dashboard', 'Dashboard', 'M4 11l8-6.6 8 6.6v8a1.6 1.6 0 0 1-1.6 1.6H5.6A1.6 1.6 0 0 1 4 19z', null],
    ['host.trips.index', 'My trips', 'M4 6h16M4 12h16M4 18h10', null],
    ['host.trips.create', 'New trip', 'M12 5.5v13M5.5 12h13', null],
    ['host.meetup-presets', 'Meetup points', 'M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11z', null],
    ['host.profile', 'Your page', 'M12 12.6a3.7 3.7 0 1 0 0-7.4 3.7 3.7 0 0 0 0 7.4zM4.6 20.2c0-4.1 3.4-6.2 7.4-6.2s7.4 2.1 7.4 6.2', null],
    $u?->handle ? ['hosts.show', 'My page &amp; wall', 'M4 7h16M4 12h16M4 17h16', $u] : null,
    ['profile', 'Settings', 'M12 12.6a3.7 3.7 0 1 0 0-7.4 3.7 3.7 0 0 0 0 7.4zM4.6 20.2c0-4.1 3.4-6.2 7.4-6.2s7.4 2.1 7.4 6.2', null],
]))

<a href="#host-main" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:top-3 focus:left-3 focus:rounded-full focus:bg-cream focus:text-pine focus:px-4 focus:py-2 focus:text-sm focus:font-semibold">Skip to content</a>

<div class="min-h-screen md:grid md:grid-cols-[248px_1fr]" x-data="{ more: false }" @keydown.escape.window="more = false">

    {{-- ══ DESKTOP SIDEBAR ══ --}}
    <aside class="hidden md:flex flex-col bg-pine text-cream/90 sticky top-0 h-screen">
        <div class="px-6 h-[68px] flex items-center border-b border-cream/10">
            <a href="{{ route('host.dashboard') }}" class="inline-flex items-center gap-2">
                <x-wordmark tone="dark" size="sm" />
                <span class="text-[10px] font-semibold uppercase tracking-widest text-cream/70 mt-1">Host</span>
            </a>
        </div>

        <nav class="flex-1 px-3 py-4 space-y-1" aria-label="Host">
            @foreach($nav as [$route, $label, $path, $param])
                @php($active = $param ? request()->routeIs($route) && request()->route('user')?->is($param) : request()->routeIs($route))
                <a href="{{ $param ? route($route, $param) : route($route) }}" @if($active) aria-current="page" @endif
                   @if($route === 'hosts.show') target="_blank" rel="noopener" @endif
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors
                          {{ $active ? 'bg-cream/12 text-cream' : 'text-cream/80 hover:text-cream hover:bg-cream/10' }}">
                    <svg viewBox="0 0 24 24" class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
                    {!! $label !!}
                </a>
            @endforeach
        </nav>

        <div class="p-3 border-t border-cream/10">
            <div class="flex items-center gap-3 px-3 py-2">
                <span class="grid place-items-center w-9 h-9 rounded-full bg-cream/15 font-semibold text-sm shrink-0">{{ strtoupper(substr($u?->name ?? 'H', 0, 1)) }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-cream truncate">{{ $u?->name }}</span>
                    <a href="{{ route('home') }}" class="block text-[11px] text-cream/60 hover:text-cream">View public site ↗</a>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="grid place-items-center w-11 h-11 -mr-2 rounded-lg text-cream/60 hover:text-cream hover:bg-cream/5" aria-label="Log out">
                        <svg viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15M10 12h9M16 8l4 4-4 4"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ══ MAIN ══ --}}
    <div class="min-w-0">
        {{-- mobile top bar: brand and a way back to the public site. Everything else is on the tab bar. --}}
        <div class="md:hidden sticky top-0 z-30 h-[56px] flex items-center gap-2 px-4 glass-pine text-cream">
            <a href="{{ route('host.dashboard') }}" class="inline-flex items-center gap-2">
                <x-wordmark tone="dark" size="sm" />
                <span class="text-[10px] font-semibold uppercase tracking-widest text-cream/70 mt-0.5">Host</span>
            </a>
            <a href="{{ route('home') }}" class="ml-auto inline-flex items-center h-11 px-3 -mr-2 rounded-xl text-sm font-semibold text-cream/80 hover:text-cream hover:bg-cream/10">View site</a>
        </div>

        {{-- No width cap: the console is a working tool, and a capped main left a dead
             gutter down the right of every data-dense page. Individual pages set their own
             measure where one actually helps. --}}
        <main id="host-main" class="px-5 sm:px-6 md:px-10 py-6 md:py-8">{{ $slot }}</main>
    </div>

    {{-- ══ PHONE: tab bar + the "More" sheet ══ --}}
    @php($tabs = [
        ['label' => 'Dashboard', 'href' => route('host.dashboard'), 'icon' => 'grid', 'active' => request()->routeIs('host.dashboard'), 'dusk' => 'tab-dashboard'],
        ['label' => 'Trips', 'href' => route('host.trips.index'), 'icon' => 'map', 'active' => request()->routeIs('host.trips.index', 'host.trips.edit', 'host.roster', 'host.checkin'), 'dusk' => 'tab-trips'],
        ['label' => 'New trip', 'href' => route('host.trips.create'), 'icon' => 'plus', 'action' => true, 'active' => request()->routeIs('host.trips.create', 'host.trips.duplicate'), 'dusk' => 'tab-new'],
        ['label' => 'Your page', 'href' => route('host.profile'), 'icon' => 'user', 'active' => request()->routeIs('host.profile'), 'dusk' => 'tab-page'],
        ['label' => 'More', 'icon' => 'dots', 'click' => 'more = true', 'active' => request()->routeIs('host.meetup-presets', 'profile'), 'dusk' => 'tab-more'],
    ])
    @php($more = collect($nav)->filter(fn ($n) => in_array($n[0], ['host.meetup-presets', 'hosts.show', 'profile'], true)))

    <x-tabbar :tabs="$tabs" tone="dark" name="host" label="Host" />

    <div x-show="more" x-cloak class="md:hidden fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="More" dusk="more-sheet">
        <div x-show="more" x-transition.opacity.duration.200ms @click="more = false" class="absolute inset-0 bg-ink/50"></div>

        {{-- Drag the sheet down to dismiss it, as every native sheet allows. --}}
        <div x-show="more"
             x-effect="if (more) $nextTick(() => $el.querySelector('nav a')?.focus({ preventScroll: true }))"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             x-data="{ dy: 0, y0: null }"
             @touchstart.passive="y0 = $event.touches[0].clientY"
             @touchmove.passive="if (y0 !== null) dy = Math.max(0, $event.touches[0].clientY - y0)"
             @touchend="if (dy > 90) more = false; dy = 0; y0 = null"
             :style="dy ? `transform: translateY(${dy}px); transition: none` : ''"
             class="absolute inset-x-0 bottom-0 rounded-t-3xl bg-canvas text-ink shadow-[0_-20px_60px_-20px_rgba(31,28,21,.5)] pb-[max(1rem,env(safe-area-inset-bottom))]">
            <div class="pt-3 pb-2 grid place-items-center"><span class="h-1 w-10 rounded-full bg-ink/20"></span></div>

            <nav class="px-3" aria-label="More">
                @foreach($more as [$route, $label, $path, $param])
                    <a href="{{ $param ? route($route, $param) : route($route) }}" wire:navigate @click="more = false"
                       class="flex items-center gap-3 px-3 min-h-[52px] rounded-xl text-[15px] font-semibold active:bg-ink/5">
                        <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-ink/65" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
                        {!! $label !!}
                    </a>
                @endforeach
                <a href="{{ route('home') }}" class="flex items-center gap-3 px-3 min-h-[52px] rounded-xl text-[15px] font-semibold active:bg-ink/5">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-ink/65" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 11.2 12 4.6l8 6.6V19a1.6 1.6 0 0 1-1.6 1.6H5.6A1.6 1.6 0 0 1 4 19z"/></svg>
                    View public site
                </a>
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="px-3 mt-1 pt-1 border-t border-ink/8">
                @csrf
                <button class="flex items-center gap-3 w-full px-3 min-h-[52px] rounded-xl text-[15px] font-semibold text-terra-deep active:bg-terra/5" dusk="more-logout">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15M10 12h9M16 8l4 4-4 4"/></svg>
                    Log out
                </button>
            </form>
        </div>
    </div>
</div>

@livewireScripts
</body>
</html>
