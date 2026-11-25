<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FBF9F4">
    <link rel="preload" href="/fonts/geist.woff2" as="font" type="font/woff2" crossorigin>
    <title>{{ $title ?? 'Terra' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php($showTabbar = $tabbar ?? true)
<body class="min-h-full font-sans antialiased bg-canvas text-ink {{ $showTabbar ? 'has-tabbar' : '' }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:top-3 focus:left-3 focus:rounded-full focus:bg-pine focus:text-cream focus:px-4 focus:py-2 focus:text-sm focus:font-semibold">Skip to content</a>

    {{-- Pages with a full-bleed photo hero opt into a transparent nav that turns solid on scroll. --}}
    @php($transparent = $transparentNav ?? false)
    <nav data-nav="{{ $transparent ? 'transparent' : 'solid' }}"
         class="site-nav h-[68px] z-30 flex items-center gap-7 px-6 md:px-10
                {{ $transparent ? 'fixed inset-x-0 top-0' : 'sticky top-0 bg-canvas/90 backdrop-blur border-b border-ink/5 text-ink' }}">
        <a href="{{ route('home') }}"><x-wordmark /></a>
        <div class="hidden md:flex gap-6 ml-3 text-sm font-semibold">
            <a href="{{ route('trips.index') }}" @class(['inline-flex items-center min-h-[44px] px-2 -mx-2 nav-link transition-opacity', 'is-current' => request()->routeIs('trips.*')]) @if(request()->routeIs('trips.*')) aria-current="page" @endif>Explore</a>
            <a href="{{ route('hosts.index') }}" @class(['inline-flex items-center min-h-[44px] px-2 -mx-2 nav-link transition-opacity', 'is-current' => request()->routeIs('hosts.*')]) @if(request()->routeIs('hosts.*')) aria-current="page" @endif>Hosts</a>
        </div>
        <div class="ml-auto flex items-center gap-4">
            @auth
                @if(auth()->user()->isHost())
                    <a href="{{ route('host.dashboard') }}" class="max-md:hidden inline-flex items-center min-h-[44px] px-2 -mx-2 text-sm font-semibold">Dashboard</a>
                @else
                    <a href="{{ route('my.trips') }}" class="max-md:hidden inline-flex items-center min-h-[44px] px-2 -mx-2 text-sm font-semibold" dusk="nav-my-trips">Your trips</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="max-md:hidden">@csrf<button class="inline-flex items-center min-h-[44px] px-2 -mx-2 nav-link text-sm font-semibold transition-opacity">Log out</button></form>
            @else
                {{-- Organisers do not know they can register and list for free, so the invitation lives in
                     the nav on every page, on a phone included. --}}
                <a href="{{ route('register', ['role' => 'host']) }}" class="inline-flex items-center min-h-[44px] px-2 -mx-2 text-sm font-semibold" dusk="nav-host">Host a trip</a>
                <a href="{{ route('login') }}" class="max-md:hidden inline-flex items-center min-h-[44px] px-2 -mx-2 text-sm font-semibold">Log in</a>
                <a href="{{ route('register') }}" class="nav-cta text-sm font-semibold text-cream bg-pine rounded-full px-4 py-2.5 transition-colors">Sign up</a>
            @endauth
        </div>
    </nav>

    <main id="main">{{ $slot }}</main>

    <footer class="mt-24 border-t border-ink/5 px-6 md:px-10 py-10 flex items-center gap-3 text-sm text-ink/65">
        <x-wordmark />
        <span>· conquering trails together</span>
    </footer>

    @if($showTabbar)
        @php($me = auth()->user())
        @php($tabs = array_merge(
            [
                ['label' => 'Home', 'href' => route('home'), 'icon' => 'home', 'active' => request()->routeIs('home'), 'dusk' => 'tab-home'],
                ['label' => 'Explore', 'href' => route('trips.index'), 'icon' => 'compass', 'active' => request()->routeIs('trips.*'), 'dusk' => 'tab-explore'],
                ['label' => 'Hosts', 'href' => route('hosts.index'), 'icon' => 'users', 'active' => request()->routeIs('hosts.*'), 'dusk' => 'tab-hosts'],
            ],
            ! $me
                ? [['label' => 'Log in', 'href' => route('login'), 'icon' => 'login', 'active' => false, 'dusk' => 'tab-login']]
                : [
                    $me->isHost()
                        ? ['label' => 'Dashboard', 'href' => route('host.dashboard'), 'icon' => 'grid', 'active' => false, 'dusk' => 'tab-dashboard']
                        : ['label' => 'Trips', 'href' => route('my.trips'), 'icon' => 'map', 'active' => request()->routeIs('my.trips', 'reservations.*', 'chat.*'), 'dusk' => 'tab-trips'],
                    ['label' => 'Account', 'href' => route('profile'), 'icon' => 'user', 'active' => request()->routeIs('profile'), 'dusk' => 'tab-account'],
                ],
        ))
        <x-tabbar :tabs="$tabs" tone="light" name="site" label="Main" />
    @endif

    @livewireScripts
</body>
</html>
