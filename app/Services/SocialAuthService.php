<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialProviders;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;
use RuntimeException;

class SocialAuthService
{
    /**
     * Resolve a provider callback to a Peeps user, creating or linking as needed.
     *
     * Identity is the provider's subject id, never the email. An email can change hands or be
     * re-pointed at a different mailbox; the subject id is stable for the life of the provider
     * account, so it is what the social_accounts unique key is built on.
     *
     * @param  ?User  $actor  the already-signed-in user, when this is a "link another provider" flow
     *
     * @throws RuntimeException when the provider gives us nothing we can safely act on
     */
    public function resolve(string $provider, SocialUser $social, ?User $actor = null): User
    {
        if (! SocialProviders::supports($provider)) {
            throw new RuntimeException('Unsupported sign-in provider.');
        }

        $providerId = (string) $social->getId();

        if ($providerId === '') {
            throw new RuntimeException('That provider did not identify the account.');
        }

        return DB::transaction(function () use ($provider, $providerId, $social, $actor) {
            $existingLink = SocialAccount::where('provider', $provider)
                ->where('provider_id', $providerId)
                ->first();

            // ── 1. Known link. The stable path, and the only one that needs no email at all.
            if ($existingLink) {
                if ($actor && $existingLink->user_id !== $actor->id) {
                    throw new RuntimeException(
                        'That '.SocialProviders::label($provider).' account is already linked to another Peeps account.'
                    );
                }

                $this->refreshLink($existingLink, $social);

                return $existingLink->user;
            }

            // ── 2. Signed in already: attach this provider to the current account.
            if ($actor) {
                $this->link($actor, $provider, $providerId, $social);

                return $actor;
            }

            $email = $social->getEmail() ? strtolower(trim($social->getEmail())) : null;

            /*
             * ── 3. No email. Facebook returns none for accounts registered by phone, and there is
             * nothing safe to do: we cannot match an existing user and cannot create one, since
             * email is the booking identity everywhere else in the app.
             */
            if ($email === null) {
                throw new RuntimeException(
                    SocialProviders::label($provider).' did not share an email address, which Peeps needs to hold a booking. '
                    .'Add an email to that account, or sign up with your email instead.'
                );
            }

            $user = User::where('email', $email)->first();

            // ── 4. Email matches an existing account: link it.
            if ($user) {
                $this->link($user, $provider, $providerId, $social);
                $this->markVerified($user, $social);

                return $user;
            }

            // ── 5. Brand new. Passwordless by design; they sign in through this provider.
            $user = User::create([
                'name' => $social->getName() ?: $social->getNickname() ?: Str::before($email, '@'),
                'first_name' => $this->firstName($social),
                'last_name' => $this->lastName($social),
                'email' => $email,
                'password' => null,
                'role' => 'joiner',        // never mint a host account from a social callback
                'avatar' => null,          // provider avatars are remote URLs; see getAvatarUrl
            ]);

            // Not part of the create() payload: email_verified_at is intentionally absent from
            // User::$fillable so no request can mass-assign itself a verified email. Setting it
            // here keeps that protection while still honouring the provider's claim.
            $this->markVerified($user, $social);
            $this->link($user, $provider, $providerId, $social);

            return $user;
        });
    }

    private function link(User $user, string $provider, string $providerId, SocialUser $social): SocialAccount
    {
        return SocialAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $provider],
            [
                'provider_id' => $providerId,
                'email' => $social->getEmail(),
                'avatar' => $social->getAvatar(),
            ],
        );
    }

    private function refreshLink(SocialAccount $link, SocialUser $social): void
    {
        $link->update([
            'email' => $social->getEmail(),
            'avatar' => $social->getAvatar(),
        ]);
    }

    /**
     * Trust the provider's own verification claim, never our own inference.
     *
     * Google returns `email_verified`. Facebook does not expose a flag, so we do not invent one —
     * an unverified email must not silently become a verified one.
     */
    private function providerVerifiedEmail(SocialUser $social): bool
    {
        $raw = $social->user ?? [];

        return ($raw['email_verified'] ?? false) === true
            || ($raw['verified_email'] ?? false) === true;
    }

    private function markVerified(User $user, SocialUser $social): void
    {
        if ($user->email_verified_at !== null || ! $this->providerVerifiedEmail($social)) {
            return;
        }

        // forceFill, not update(): email_verified_at is deliberately absent from User::$fillable
        // so no request can mass-assign itself a verified email. update() would honour that guard
        // and silently drop the value, which is exactly what it did before this was fixed.
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    private function firstName(SocialUser $social): ?string
    {
        $raw = $social->user ?? [];

        if (filled($raw['given_name'] ?? null)) {
            return $raw['given_name'];
        }

        $parts = preg_split('/\s+/', trim((string) $social->getName())) ?: [];

        return $parts[0] ?? null;
    }

    private function lastName(SocialUser $social): ?string
    {
        $raw = $social->user ?? [];

        if (filled($raw['family_name'] ?? null)) {
            return $raw['family_name'];
        }

        $parts = preg_split('/\s+/', trim((string) $social->getName())) ?: [];

        return count($parts) > 1 ? (string) end($parts) : null;
    }
}
