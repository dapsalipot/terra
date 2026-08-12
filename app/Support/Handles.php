<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Handles are public URLs: terra.test/trailco. That means they share a namespace with
 * every top-level route, so a handle can never be a word the router already owns.
 */
class Handles
{
    /**
     * Anything the app routes at the root, plus words we may want later.
     *
     * `peeps` stays reserved even though it is no longer the name — that is precisely why it
     * has to stay. An unreserved former brand is a handle a stranger can claim and then use to
     * look official at terra.test/peeps.
     */
    public const RESERVED = [
        'about', 'admin', 'api', 'assets', 'blog', 'broadcasting', 'build', 'contact',
        'css', 'dashboard', 'docs', 'explore', 'faq', 'forgot-password', 'help', 'home',
        'host', 'hosts', 'images', 'img', 'js', 'legal', 'live', 'livewire', 'login',
        'logout', 'me', 'new', 'password', 'peeps', 'photos', 'press', 'pricing',
        'privacy', 'profile', 'register', 'reset-password', 'reservations', 'root',
        'search', 'settings', 'signup', 'storage', 'support', 'system', 't', 'terms',
        'terra', 'trip', 'trips', 'up', 'user', 'users', 'verify-email',
    ];

    /** Route constraint: what a root-level handle segment may look like. */
    public const PATTERN = '[A-Za-z0-9][A-Za-z0-9_-]{2,29}';

    /** @return array<int, mixed> */
    public static function rules(?int $ignoreUserId = null): array
    {
        return [
            'nullable',
            'string',
            'min:3',
            'max:30',
            'regex:/^[a-z0-9][a-z0-9_-]*$/',
            Rule::notIn(self::RESERVED),
            Rule::unique('users', 'handle')->ignore($ignoreUserId),
        ];
    }

    public static function messages(): array
    {
        return [
            'handle.regex' => 'Use lowercase letters, numbers, dashes and underscores only.',
            'handle.not_in' => 'That handle is reserved. Try another.',
            'handle.unique' => 'Someone already has that handle.',
            'handle.min' => 'Handles need at least 3 characters.',
        ];
    }
}
