<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FBF9F4">
    <link rel="preload" href="/fonts/geist.woff2" as="font" type="font/woff2" crossorigin>
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Tarana') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full font-sans antialiased text-ink">
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            {{-- Brand panel --}}
            <div class="relative hidden lg:block bg-pine overflow-hidden">
                <svg class="absolute inset-x-0 bottom-0 w-full" viewBox="0 0 720 520" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
                    <path d="M0 360 L150 210 L280 320 L430 170 L560 300 L720 200 L720 520 L0 520 Z" fill="#3B4A32" opacity=".6"/>
                    <path d="M0 420 L160 300 L320 400 L470 290 L620 400 L720 340 L720 520 L0 520 Z" fill="#243019"/>
                    <path d="M40 470 C220 440 360 360 520 350 C620 344 680 300 710 276" fill="none" stroke="#BC4B26" stroke-width="2.5" stroke-dasharray="2 9" stroke-linecap="round" opacity=".9"/>
                    <path d="M704 270 l6 6 l6 -6 l-6 -10 z" fill="#BC4B26"/>
                    <circle cx="560" cy="120" r="46" fill="#E8C58C" opacity=".85"/>
                </svg>
                <div class="relative z-10 h-full flex flex-col p-12">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                        <span class="grid place-items-center w-8 h-8 rounded-full bg-cream/15">
                            <svg viewBox="0 0 24 24" class="w-4 h-4 fill-cream"><path d="M3 19.5l6.4-9.7 3.7 5.1 3.2-4.7 6.3 9.3z"/></svg>
                        </span>
                        <span class="tracking-tight font-semibold text-2xl text-cream">Tarana</span>
                    </a>
                    <div class="mt-auto">
                        <h1 class="tracking-tight font-semibold text-4xl text-cream leading-tight">Tara na.</h1>
                        <p class="text-cream/60 mt-3 max-w-sm">Group trips — hikes, islands, and everywhere between — hosted by people who know the way.</p>
                    </div>
                </div>
            </div>

            {{-- Form panel --}}
            <div class="flex flex-col justify-center items-center px-6 py-12 bg-canvas">
                <a href="{{ route('home') }}" class="lg:hidden mb-8 inline-flex items-center gap-2">
                    <span class="grid place-items-center w-8 h-8 rounded-full bg-pine">
                        <svg viewBox="0 0 24 24" class="w-4 h-4 fill-cream"><path d="M3 19.5l6.4-9.7 3.7 5.1 3.2-4.7 6.3 9.3z"/></svg>
                    </span>
                    <span class="tracking-tight font-semibold text-2xl">Tarana</span>
                </a>
                <div class="w-full max-w-sm">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
