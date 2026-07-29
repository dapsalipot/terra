<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Peeps' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full font-sans antialiased bg-canvas text-ink">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:top-3 focus:left-3 focus:rounded-full focus:bg-pine focus:text-cream focus:px-4 focus:py-2 focus:text-sm focus:font-semibold">Skip to content</a>

    {{-- Pages with a full-bleed photo hero opt into a transparent nav that turns solid on scroll. --}}
    @php($transparent = $transparentNav ?? false)
    <nav data-nav="{{ $transparent ? 'transparent' : 'solid' }}"
         class="site-nav h-[68px] z-30 flex items-center gap-7 px-6 md:px-10
                {{ $transparent ? 'fixed inset-x-0 top-0' : 'sticky top-0 bg-canvas/90 backdrop-blur border-b border-ink/5 text-ink' }}">
        <a href="{{ route('home') }}"><x-wordmark /></a>
        <div class="hidden md:flex gap-6 ml-3 text-sm font-semibold">
            <a href="{{ route('trips.index') }}" @class(['nav-link transition-opacity', 'is-current' => request()->routeIs('trips.*')]) @if(request()->routeIs('trips.*')) aria-current="page" @endif>Explore</a>
            <a href="{{ route('hosts.index') }}" @class(['nav-link transition-opacity', 'is-current' => request()->routeIs('hosts.*')]) @if(request()->routeIs('hosts.*')) aria-current="page" @endif>Hosts</a>
        </div>
        <div class="ml-auto flex items-center gap-4">
            @auth
                @if(auth()->user()->isHost())
                    <a href="{{ route('host.dashboard') }}" class="text-sm font-semibold">Dashboard</a>
                @else
                    <a href="{{ route('trips.index') }}" class="text-sm font-semibold">Explore</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link text-sm font-semibold transition-opacity">Log out</button></form>
            @else
                <a href="{{ route('login') }}" class="text-sm font-semibold">Log in</a>
                <a href="{{ route('register') }}" class="nav-cta text-sm font-semibold text-cream bg-pine rounded-full px-4 py-2.5 transition-colors">Sign up</a>
            @endauth
        </div>
    </nav>

    <main id="main">{{ $slot }}</main>

    <footer class="mt-24 border-t border-ink/5 px-6 md:px-10 py-10 flex items-center gap-3 text-sm text-ink/65">
        <x-wordmark />
        <span>· better trips with better peeps</span>
    </footer>

    @livewireScripts
</body>
</html>
