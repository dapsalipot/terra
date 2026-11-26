@props([
    'tone' => 'light',   // light: pine, for the canvas · dark: cream, for the pine console and the login panel
    'size' => 'md',      // sm: host console bars · md: site nav and footer · lg: login
])

{{--
    The Tarana logo: a summit sign, then the word.

    The sign is a plain rectangular board on one leg, with a V notch cut into each end so it reads as rough-cut
    wood. The post follows the text colour (pine on the canvas, cream on pine) and the board is always terracotta,
    so one mark serves light and dark chrome alike. The word is written "Tarana" and lowercased by CSS,
    which keeps the name intact for screen readers, copy and paste, and search.
--}}
@php
    $dark = $tone === 'dark';
    [$mark, $word] = [
        'sm' => ['h-6 w-6', 'text-xl'],
        'md' => ['h-[26px] w-[26px]', 'text-[22px]'],
        'lg' => ['h-8 w-8', 'text-[28px]'],
    ][$size] ?? ['h-[26px] w-[26px]', 'text-[22px]'];
@endphp

<span {{ $attributes->class(['wordmark inline-flex items-center gap-2', $dark ? 'text-cream' : 'text-pine']) }}>
    <svg viewBox="0 0 96 96" class="{{ $mark }} shrink-0" aria-hidden="true">
        <rect x="42" y="40" width="12" height="52" rx="3" fill="currentColor"/>
        <path d="M15 14H81A5 5 0 0 1 86 19V24L81 28.5L86 33V43A5 5 0 0 1 81 48H15A5 5 0 0 1 10 43V37L15 32.5L10 28V19A5 5 0 0 1 15 14Z" fill="#BC4B26"/>
    </svg>
    <span class="font-brand font-semibold lowercase leading-none tracking-[-0.01em] {{ $word }}">Tarana</span>
</span>
