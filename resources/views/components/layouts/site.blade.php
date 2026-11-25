<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FBF9F4">
    <link rel="preload" href="/fonts/geist.woff2" as="font" type="font/woff2" crossorigin>
    <title>{{ $title ?? 'Tarana' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
@php($showTabbar = $tabbar ?? true)
<body class="min-h-full font-sans antialiased bg-canvas text-ink {{ $showTabbar ? 'has-tabbar' : '' }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:top-3 focus:left-3 focus:rounded-full focus:bg-pine focus:text-cream focus:px-4 focus:py-2 focus:text-sm focus:font-semibold">Skip to content</a>

    <nav class="site-nav sticky top-0 z-30 flex h-[68px] items-center gap-3 border-b border-ink/5 bg-canvas/90 px-6 text-ink backdrop-blur md:gap-7 md:px-10">
        <a href="{{ route('home') }}"><x-wordmark /></a>
        <div class="hidden md:flex gap-6 ml-3 text-sm font-semibold">
            <a href="{{ route('trips.index') }}" @class(['inline-flex items-center min-h-[44px] px-2 -mx-2 nav-link transition-opacity', 'is-current' => request()->routeIs('trips.*')]) @if(request()->routeIs('trips.*')) aria-current="page" @endif>Explore</a>
            <a href="{{ route('hosts.index') }}" @class(['inline-flex items-center min-h-[44px] px-2 -mx-2 nav-link transition-opacity', 'is-current' => request()->routeIs('hosts.*')]) @if(request()->routeIs('hosts.*')) aria-current="page" @endif>Hosts</a>
        </div>
        <div class="ml-auto flex items-center gap-2 md:gap-4">
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
                <a href="{{ route('register', ['role' => 'host']) }}" class="inline-flex items-center whitespace-nowrap min-h-[44px] px-2 -mx-2 text-sm font-semibold" dusk="nav-host">Host your Trips</a>
                <a href="{{ route('login') }}" class="max-md:hidden inline-flex items-center min-h-[44px] px-2 -mx-2 text-sm font-semibold">Log in</a>
                <a href="{{ route('register') }}" class="nav-cta text-sm font-semibold text-cream bg-pine rounded-full px-4 py-2.5 transition-colors">Sign up</a>
            @endauth
        </div>
    </nav>

    <main id="main">{{ $slot }}</main>

    <footer class="mt-20 border-t border-ink/10 bg-sand/60">
        <div class="mx-auto grid max-w-7xl gap-10 px-6 py-12 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <a href="{{ route('home') }}"><x-wordmark /></a>
                <p class="mt-3 max-w-xs text-ink/65">Group trips led by local hosts. Hikes, islands and everywhere in between.</p>
                <p class="mt-2 max-w-xs text-ink/65">Tarana comes from &ldquo;Tara na&rdquo;, Filipino for &ldquo;let&rsquo;s go&rdquo;.</p>
            </div>

            <nav aria-label="Explore">
                <h2 class="font-semibold">Explore</h2>
                <ul class="mt-3 space-y-2 text-ink/70">
                    <li><a href="{{ route('trips.index') }}" class="hover:text-ink hover:underline underline-offset-4">All trips</a></li>
                    @foreach(\App\Support\PlaceCategories::CATALOG as $key => $category)
                        <li><a href="{{ route('trips.index', ['category' => $key]) }}" class="hover:text-ink hover:underline underline-offset-4">{{ $category[0] }} trips</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav aria-label="Hosting">
                <h2 class="font-semibold">Hosting</h2>
                <ul class="mt-3 space-y-2 text-ink/70">
                    @auth
                        @if(auth()->user()->isHost())
                            <li><a href="{{ route('host.dashboard') }}" class="hover:text-ink hover:underline underline-offset-4">Dashboard</a></li>
                            <li><a href="{{ route('host.trips.create') }}" class="hover:text-ink hover:underline underline-offset-4">Post a trip</a></li>
                        @endif
                    @else
                        <li><a href="{{ route('register', ['role' => 'host']) }}" class="hover:text-ink hover:underline underline-offset-4">Host your Trips</a></li>
                    @endauth
                    <li><a href="{{ route('hosts.index') }}" class="hover:text-ink hover:underline underline-offset-4">All hosts</a></li>
                </ul>
            </nav>

            <nav aria-label="Account">
                <h2 class="font-semibold">Account</h2>
                <ul class="mt-3 space-y-2 text-ink/70">
                    @auth
                        @unless(auth()->user()->isHost())
                            <li><a href="{{ route('my.trips') }}" class="hover:text-ink hover:underline underline-offset-4">Your trips</a></li>
                        @endunless
                        <li><a href="{{ route('profile') }}" class="hover:text-ink hover:underline underline-offset-4">Settings</a></li>
                    @else
                        <li><a href="{{ route('login') }}" class="hover:text-ink hover:underline underline-offset-4">Log in</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-ink hover:underline underline-offset-4">Sign up</a></li>
                    @endauth
                </ul>
            </nav>
        </div>

        <div class="border-t border-ink/10">
            <p class="mx-auto max-w-7xl px-6 py-5 text-sm text-ink/65">&copy; {{ date('Y') }} Tarana</p>
        </div>
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
