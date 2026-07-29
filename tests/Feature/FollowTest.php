<?php

use App\Models\HostProfile;
use App\Models\User;

it('lets a joiner follow and unfollow a host and keeps the count', function () {
    $host = User::factory()->create(['role' => 'host']);
    HostProfile::factory()->for($host)->create();
    $joiner = User::factory()->create(['role' => 'joiner']);

    $joiner->follow($host);
    expect($joiner->isFollowing($host))->toBeTrue()
        ->and($host->fresh()->hostProfile->followers_count)->toBe(1);

    $joiner->unfollow($host);
    expect($joiner->fresh()->isFollowing($host))->toBeFalse()
        ->and($host->fresh()->hostProfile->followers_count)->toBe(0);
});

it('cannot follow a non-host or yourself', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);
    $otherJoiner = User::factory()->create(['role' => 'joiner']);

    $joiner->follow($otherJoiner);
    $joiner->follow($joiner);

    expect($joiner->following()->count())->toBe(0);
});
