<?php

use App\Models\User;
use App\Support\Handles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $handle = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->handle = (string) Auth::user()->handle;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'handle' => Handles::rules($user->id),
        ], Handles::messages());

        // an empty handle means "no public page", not an empty string in a unique column
        $validated['handle'] = $validated['handle'] !== '' ? $validated['handle'] : null;

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        {{-- The handle is the host's public address, so show it as the URL it becomes. --}}
        <div>
            <x-input-label for="handle" :value="__('Handle')" />
            <div class="mt-1 flex items-stretch rounded-md border border-gray-300 overflow-hidden focus-within:border-moss focus-within:ring-1 focus-within:ring-moss">
                <span class="grid place-items-center px-3 bg-ink/[.04] text-sm text-ink/65 border-r border-gray-300 whitespace-nowrap">
                    {{ rtrim(parse_url(config('app.url'), PHP_URL_HOST) ?: config('app.url'), '/') }}/
                </span>
                <input wire:model.blur="handle" id="handle" name="handle" type="text" dusk="handle"
                       placeholder="trailco" autocomplete="off" spellcheck="false"
                       class="flex-1 min-w-0 border-0 focus:ring-0 px-3 py-2">
            </div>
            <p class="mt-1 text-sm text-gray-600">
                @if($handle)
                    Your page is at
                    <a href="{{ url('/'.$handle) }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-moss hover:underline">{{ url('/'.$handle) }}</a>
                    · people can find you as <b>&commat;{{ $handle }}</b>.
                @else
                    Pick a handle to get a public page. Leave it blank to stay unlisted.
                @endif
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('handle')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
