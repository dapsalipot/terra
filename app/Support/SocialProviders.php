<?php

namespace App\Support;

/**
 * The social providers this app offers.
 *
 * Kept in one place so the login page, the route constraint and the callback handler cannot drift
 * apart — adding a provider means adding a row here and its credentials in config/services.php.
 */
class SocialProviders
{
    /**
     * @var array<string, array{label: string, brand: string}>
     */
    public const SUPPORTED = [
        'google' => ['label' => 'Google', 'brand' => '#DB4437'],
        'facebook' => ['label' => 'Facebook', 'brand' => '#1877F2'],
    ];

    /** Route-constraint pattern, so an unknown provider 404s instead of reaching Socialite. */
    public const PATTERN = 'google|facebook';

    /** Only providers whose credentials are actually configured are offered to a user. */
    public static function enabled(): array
    {
        return array_filter(
            self::SUPPORTED,
            fn ($_, $provider) => filled(config("services.{$provider}.client_id")),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public static function supports(string $provider): bool
    {
        return array_key_exists($provider, self::SUPPORTED);
    }

    public static function label(string $provider): string
    {
        return self::SUPPORTED[$provider]['label'] ?? ucfirst($provider);
    }
}
