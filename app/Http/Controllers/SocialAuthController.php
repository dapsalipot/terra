<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SocialAuthService;
use App\Support\SocialProviders;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Throwable;

class SocialAuthController extends Controller
{
    /** Hand off to the provider. */
    public function redirect(string $provider)
    {
        abort_unless(array_key_exists($provider, SocialProviders::enabled()), 404);

        return Socialite::driver($provider)->redirect();
    }

    /** Come back from the provider and turn the result into a session. */
    public function callback(string $provider, SocialAuthService $service)
    {
        abort_unless(array_key_exists($provider, SocialProviders::enabled()), 404);

        // Someone signed in who is adding a second provider, rather than signing in with it.
        $actor = Auth::user();

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            report($e);

            // A denied consent screen, an expired state token, or a provider outage all land here.
            return redirect()->route('login')->withErrors([
                'form.email' => 'We could not complete sign-in with '.SocialProviders::label($provider).'. Please try again.',
            ]);
        }

        try {
            $user = $service->resolve($provider, $social, $actor);
        } catch (RuntimeException $e) {
            return redirect()->route($actor ? 'profile' : 'login')
                ->withErrors(['form.email' => $e->getMessage()]);
        }

        if (! $actor) {
            Auth::login($user, remember: true);
            request()->session()->regenerate();   // fresh id on privilege change
        }

        return redirect()->intended($this->home($user));
    }

    /** Hosts belong in the console; everyone else lands on the marketplace. */
    private function home(User $user): string
    {
        return $user->isHost() ? route('host.dashboard') : route('home');
    }
}
