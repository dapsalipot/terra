<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $role = 'joiner';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request. Role is chosen at signup and
     * never switched — a host additionally gets a handle and a host profile.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role' => ['required', 'in:joiner,host'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        if ($validated['role'] === 'host') {
            $validated['handle'] = $this->uniqueHandle($validated['name']);
        }

        event(new Registered($user = User::create($validated)));

        if ($user->isHost()) {
            $user->hostProfile()->create(['currency' => 'PHP']);
        }

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    private function uniqueHandle(string $name): string
    {
        $base = Str::slug($name) ?: 'host';
        $handle = $base;
        $i = 1;

        while (User::where('handle', $handle)->exists()) {
            $handle = $base.'-'.(++$i);
        }

        return $handle;
    }
}; ?>

<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Role (chosen once, never switched) -->
        <div class="mt-4">
            <x-input-label :value="__('I want to')" />
            <div class="mt-2 grid grid-cols-2 gap-3">
                <label class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer"
                       :class="{ 'border-moss ring-1 ring-moss': $wire.role === 'joiner' }">
                    <input type="radio" wire:model.live="role" value="joiner" class="mt-1 text-moss focus:ring-moss">
                    <span><span class="block font-semibold text-sm">Join trips</span>
                        <span class="block text-xs text-gray-500">Find and reserve trips</span></span>
                </label>
                <label class="flex items-start gap-3 rounded-xl border p-3 cursor-pointer"
                       :class="{ 'border-moss ring-1 ring-moss': $wire.role === 'host' }">
                    <input type="radio" wire:model.live="role" value="host" class="mt-1 text-moss focus:ring-moss">
                    <span><span class="block font-semibold text-sm">Host trips</span>
                        <span class="block text-xs text-gray-500">Post trips and get paid</span></span>
                </label>
            </div>
            <p class="mt-1 text-xs text-gray-500">You can't switch later, so pick the one that fits.</p>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-ink/60 hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-moss" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</div>
