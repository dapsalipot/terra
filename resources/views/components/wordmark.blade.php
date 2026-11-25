@props([
    'tone' => 'light',   // light: pine, for the canvas · dark: cream, for the pine console and the login panel
    'size' => 'md',      // sm: host console bars · md: site nav and footer · lg: login
])

{{--
    The Tarana logo: the signpost, then the word.

    The post follows the text colour (pine on the canvas, cream on pine) and the sign is always terracotta,
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
        <rect x="41" y="36" width="14" height="56" rx="3.5" fill="currentColor"/>
        <polygon points="14,17 68,17 84,29 68,41 14,41" fill="#BC4B26" stroke="#BC4B26" stroke-width="6" stroke-linejoin="round"/>
        <circle cx="24" cy="29" r="3.2" fill="#F7F4EC"/>
    </svg>
    <span class="font-brand font-semibold lowercase leading-none tracking-[-0.01em] {{ $word }}">Tarana</span>
</span>
