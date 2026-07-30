@props(['label' => 'Or continue with'])

@php($providers = \App\Support\SocialProviders::enabled())

{{--
    Renders nothing at all when no provider is configured, so a fresh clone with an empty .env
    does not show buttons that can only fail.
--}}
@if(filled($providers))
    <div class="mt-6">
        <div class="relative text-center">
            <span class="absolute inset-x-0 top-1/2 border-t border-ink/10" aria-hidden="true"></span>
            <span class="relative inline-block bg-canvas px-3 text-xs font-semibold uppercase tracking-wide text-ink/50">{{ $label }}</span>
        </div>

        <div class="mt-4 space-y-2">
            @foreach($providers as $provider => $meta)
                <a href="{{ route('social.redirect', $provider) }}" dusk="social-{{ $provider }}"
                   class="flex items-center justify-center gap-3 w-full rounded-full border border-ink/15 min-h-[48px] px-5 text-sm font-semibold hover:border-ink/35 hover:bg-ink/[.02] transition-colors">
                    @if($provider === 'google')
                        {{-- Google's mark, inlined: a strict CSP blocks remote assets. --}}
                        <svg viewBox="0 0 18 18" class="w-[18px] h-[18px] shrink-0" aria-hidden="true">
                            <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92a8.78 8.78 0 0 0 2.68-6.62z"/>
                            <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.81.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.34A9 9 0 0 0 9 18z"/>
                            <path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.94H.96a9 9 0 0 0 0 8.12l3.01-2.34z"/>
                            <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.46 3.44 1.35l2.58-2.58A8.99 8.99 0 0 0 .96 4.94l3.01 2.34C4.68 5.16 6.66 3.58 9 3.58z"/>
                        </svg>
                    @elseif($provider === 'facebook')
                        <svg viewBox="0 0 24 24" class="w-[18px] h-[18px] shrink-0" fill="#1877F2" aria-hidden="true">
                            <path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.7 4.53-4.7 1.31 0 2.68.24 2.68.24v2.95h-1.5c-1.48 0-1.95.93-1.95 1.88v2.26h3.32l-.53 3.49h-2.79V24C19.61 23.1 24 18.1 24 12.07z"/>
                        </svg>
                    @endif
                    Continue with {{ $meta['label'] }}
                </a>
            @endforeach
        </div>
    </div>
@endif
