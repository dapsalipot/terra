<?php

use App\Models\HostProfile;
use App\Models\User;
use App\Support\SocialLinks;

it('turns however a host types a handle into an openable url', function (string $kind, string $typed, string $expected) {
    expect(SocialLinks::url($kind, $typed))->toBe($expected);
})->with([
    'bare handle' => ['instagram', 'trailco', 'https://instagram.com/trailco'],
    'at-prefixed' => ['instagram', '@trailco', 'https://instagram.com/trailco'],
    'pasted path' => ['instagram', 'instagram.com/trailco', 'https://instagram.com/trailco'],
    'full url kept' => ['instagram', 'https://instagram.com/trailco', 'https://instagram.com/trailco'],
    'facebook handle' => ['facebook', '@trailco', 'https://facebook.com/trailco'],
    'bare domain' => ['website', 'trailco.ph', 'https://trailco.ph'],
    'http kept' => ['website', 'http://trailco.ph', 'http://trailco.ph'],
]);

it('returns null for an empty link so the pill is skipped', function () {
    expect(SocialLinks::url('instagram', ''))->toBeNull()
        ->and(SocialLinks::url('website', null))->toBeNull();
});

it('renders host links as real anchors on the public page', function () {
    $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
    HostProfile::factory()->for($host)->create([
        'links' => ['instagram' => '@trailco', 'website' => 'trailco.ph'],
    ]);

    $this->get('/@trailco')->assertOk()
        ->assertSee('href="https://instagram.com/trailco"', escape: false)
        ->assertSee('href="https://trailco.ph"', escape: false)
        // opened safely in a new tab
        ->assertSee('rel="noopener noreferrer"', escape: false);
});
