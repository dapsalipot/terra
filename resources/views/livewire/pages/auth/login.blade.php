<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-terra shadow-sm focus:ring-moss" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-ink/60 hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-moss" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

    {{-- An account created during trip registration has no password at all — it signs in by
         emailed link. Without this, its owner would type their email here, have nothing to put
         in the password field, and be told their credentials are wrong with no way forward. --}}
    <form method="POST" action="{{ route('magic.send') }}" class="mt-6 pt-6 border-t border-ink/10">
        @csrf
        <p class="text-sm text-ink/70 mb-2">{{ __('No password? We’ll email you a sign-in link.') }}</p>
        <input type="email" name="email" required autocomplete="email" placeholder="you@example.com" dusk="magic-email"
               aria-label="{{ __('Email for a sign-in link') }}"
               class="w-full rounded-xl border-ink/15 px-3.5 py-2 text-sm focus:border-moss focus:ring-moss">
        <button type="submit" dusk="send-magic"
                class="mt-2 w-full rounded-full border border-ink/15 py-2.5 text-sm font-semibold hover:border-moss transition-colors">
            {{ __('Email me a sign-in link') }}
        </button>
        @if(session('status'))
            <p class="text-sm text-moss mt-2">{{ session('status') }}</p>
        @endif
    </form>
</div>
