<?php

use App\Models\Trip;
use App\Models\User;
use App\Support\Visibility;
use Illuminate\Support\Carbon;

it('lists a public trip to everyone, guests included', function () {
    $t = Trip::factory()->published()->create();

    expect(Trip::listed()->pluck('id'))->toContain($t->id)
        ->and(Trip::listedFor(null)->pluck('id'))->toContain($t->id);
});

it('lists a followers-first trip to the host\'s followers only until it opens', function () {
    $t = Trip::factory()->published()->followersFirst()->create();
    $follower = User::factory()->create();
    $follower->follow($t->host);

    expect(Trip::listedFor($follower)->pluck('id'))->toContain($t->id)
        ->and(Trip::listedFor(User::factory()->create())->pluck('id'))->not->toContain($t->id)
        ->and(Trip::listedFor(null)->pluck('id'))->not->toContain($t->id)
        ->and(Trip::listed()->pluck('id'))->not->toContain($t->id);
});

it('keeps a followers-first trip with no opening date followers-only', function () {
    $t = Trip::factory()->published()->create(['visibility' => 'followers', 'public_at' => null]);
    $follower = User::factory()->create();
    $follower->follow($t->host);

    expect(Trip::listedFor($follower)->pluck('id'))->toContain($t->id)
        ->and(Trip::listed()->pluck('id'))->not->toContain($t->id)
        ->and($t->effectiveVisibility())->toBe('followers')
        ->and(Visibility::chip($t))->toBe('Followers first');
});

it('counts public_at exactly now as open', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $t = Trip::factory()->published()->followersFirst(now())->create();

    expect(Trip::listed()->pluck('id'))->toContain($t->id)
        ->and($t->effectiveVisibility())->toBe('public');
});

it('never lists a link-only trip', function () {
    $t = Trip::factory()->published()->linkOnly()->create();
    $follower = User::factory()->create();
    $follower->follow($t->host);

    expect(Trip::listedFor($follower)->pluck('id'))->not->toContain($t->id)
        ->and($t->effectiveVisibility())->toBe('link');
});

it('never lists a suspended host\'s trips, followers or not', function () {
    $t = Trip::factory()->published()->followersFirst()->create();
    $t->host->forceFill(['suspended_at' => now()])->save();
    $follower = User::factory()->create();
    $follower->follow($t->host);

    expect(Trip::listedFor($follower)->pluck('id'))->not->toContain($t->id);
});

it('words the chip for the host', function () {
    $this->travelTo('2026-11-26 09:00:00');

    expect(Visibility::chip(Trip::factory()->create()))->toBeNull()
        ->and(Visibility::chip(Trip::factory()->linkOnly()->create()))->toBe('Link only')
        ->and(Visibility::chip(Trip::factory()->followersFirst(Carbon::parse('2026-12-05 18:00'))->create()))->toBe('Followers first until 5 Dec');
});

it('opens a window the preset length from now and refuses an unknown one', function () {
    $this->travelTo('2026-11-26 09:00:00');

    expect(Visibility::keys())->toBe(['public', 'followers', 'link'])
        ->and(Visibility::opensAt('1d')->toDateTimeString())->toBe('2026-11-27 09:00:00')
        ->and(Visibility::opensAt('3d')->toDateTimeString())->toBe('2026-11-29 09:00:00')
        ->and(Visibility::opensAt('1w')->toDateTimeString())->toBe('2026-12-03 09:00:00')
        ->and(fn () => Visibility::opensAt('2w'))->toThrow(InvalidArgumentException::class);
});
