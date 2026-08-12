<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Terra · Host' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="host-ui min-h-full font-sans antialiased bg-canvas text-ink">
@php($u = auth()->user())

{{-- One nav definition, rendered in both the desktop sidebar and the mobile drawer. --}}
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

<div class="min-h-screen md:grid md:grid-cols-[248px_1fr]" x-data="{ nav: false }" @keydown.escape.window="nav = false">

    {{-- ══ DESKTOP SIDEBAR ══ --}}
    <aside class="hidden md:flex flex-col bg-pine text-cream/90 sticky top-0 h-screen">
        <div class="px-6 h-[68px] flex items-center border-b border-cream/10">
            <a href="{{ route('host.dashboard') }}" class="inline-flex items-center gap-2">
                <span class="grid place-items-center w-7 h-7 rounded-full bg-cream/15">
                    <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 fill-cream" aria-hidden="true"><path d="M3 19.5l6.4-9.7 3.7 5.1 3.2-4.7 6.3 9.3z"/></svg>
                </span>
                <span class="font-serif font-semibold text-lg text-cream">Terra</span>
                <span class="text-[10px] font-semibold uppercase tracking-widest text-cream/50 mt-1">Host</span>
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
                <span class="grid place-items-center w-9 h-9 rounded-full bg-cream/15 font-serif font-semibold text-sm shrink-0">{{ strtoupper(substr($u?->name ?? 'H', 0, 1)) }}</span>
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
        {{-- mobile top bar: the menu button is the only route to the rest of the console --}}
        <div class="md:hidden sticky top-0 z-30 h-[60px] flex items-center gap-2 px-4 glass-pine text-cream">
            <button type="button" @click="nav = true" aria-label="Open menu" dusk="mobile-menu" aria-controls="host-mobile-nav" :aria-expanded="nav ? 'true' : 'false'"
                    class="grid place-items-center w-11 h-11 -ml-1.5 rounded-xl hover:bg-cream/10">
                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a href="{{ route('host.dashboard') }}" class="font-serif font-semibold text-lg">Terra <span class="text-[10px] uppercase tracking-widest text-cream/60">Host</span></a>
            <a href="{{ route('host.trips.create') }}" class="ml-auto inline-flex items-center h-11 px-4 rounded-full bg-cream/15 text-sm font-semibold hover:bg-cream/25">＋ New</a>
        </div>

        {{-- mobile drawer --}}
        <div x-show="nav" x-cloak class="md:hidden fixed inset-0 z-40" role="dialog" aria-modal="true" aria-label="Host menu">
            <div x-show="nav" x-transition.opacity @click="nav = false" class="absolute inset-0 bg-ink/50"></div>

            <div id="host-mobile-nav" x-show="nav"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                 class="absolute inset-y-0 left-0 w-[17rem] max-w-[85%] glass-pine text-cream/90 flex flex-col">

                <div class="px-5 h-[60px] flex items-center border-b border-cream/10">
                    <span class="font-serif font-semibold text-lg text-cream">Terra <span class="text-[10px] uppercase tracking-widest text-cream/60">Host</span></span>
                    <button type="button" @click="nav = false" aria-label="Close menu" dusk="mobile-nav-close"
                            class="ml-auto grid place-items-center w-11 h-11 -mr-2 rounded-xl hover:bg-cream/10">
                        <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1" aria-label="Host">
                    @foreach($nav as [$route, $label, $path, $param])
                        @php($active = $param ? request()->routeIs($route) && request()->route('user')?->is($param) : request()->routeIs($route))
                        <a href="{{ $param ? route($route, $param) : route($route) }}" @if($active) aria-current="page" @endif
                           @if($route === 'hosts.show') target="_blank" rel="noopener" @endif
                           class="flex items-center gap-3 px-3 min-h-[44px] rounded-xl text-sm font-semibold
                                  {{ $active ? 'bg-cream/12 text-cream' : 'text-cream/80 hover:text-cream hover:bg-cream/10' }}">
                            <svg viewBox="0 0 24 24" class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg>
                            {!! $label !!}
                        </a>
                    @endforeach
                </nav>

                <div class="p-3 border-t border-cream/10">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <span class="grid place-items-center w-9 h-9 rounded-full bg-cream/15 font-serif font-semibold text-sm shrink-0">{{ strtoupper(substr($u?->name ?? 'H', 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-cream truncate">{{ $u?->name }}</span>
                            <a href="{{ route('home') }}" class="block text-[11px] text-cream/60">View public site ↗</a>
                        </span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="px-3 pt-1">
                        @csrf
                        <button class="flex items-center gap-3 w-full px-3 min-h-[44px] rounded-xl text-sm font-semibold text-cream/80 hover:text-cream hover:bg-cream/10">
                            <svg viewBox="0 0 24 24" class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15M10 12h9M16 8l4 4-4 4"/></svg>
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- No width cap: the console is a working tool, and a capped main left a dead
             gutter down the right of every data-dense page. Individual pages set their own
             measure where one actually helps. --}}
        <main id="host-main" class="px-5 sm:px-6 md:px-10 py-6 md:py-8">{{ $slot }}</main>
    </div>
</div>

@livewireScripts
</body>
</html>
