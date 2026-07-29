<?php

use App\Models\MagicLink;
use App\Models\User;
use App\Services\MagicLinkService;

beforeEach(function () {
    $this->svc = app(MagicLinkService::class);
});

it('never stores the raw token', function () {
    $user = User::factory()->create();
    ['link' => $link, 'url' => $url] = $this->svc->issue('login', ['user_id' => $user->id]);

    $raw = str($url)->afterLast('/')->toString();

    expect($raw)->not->toBe($link->token)
        ->and($link->token)->toBe(hash('sha256', $raw))
        ->and(strlen($link->token))->toBe(64);
});

it('consumes a valid token once', function () {
    $user = User::factory()->create();
    ['url' => $url] = $this->svc->issue('login', ['user_id' => $user->id]);
    $raw = str($url)->afterLast('/')->toString();

    $first = $this->svc->consume($raw);

    expect($first)->not->toBeNull()
        ->and($first->user_id)->toBe($user->id)
        ->and($first->used_at)->not->toBeNull()
        ->and($this->svc->consume($raw))->toBeNull();   // second use rejected
});

it('rejects an expired token', function () {
    ['url' => $url] = $this->svc->issue('login', ['email' => 'ada@example.com'], ttlMinutes: 30);
    $raw = str($url)->afterLast('/')->toString();

    $this->travel(31)->minutes();

    expect($this->svc->consume($raw))->toBeNull();
});

it('rejects a token that never existed', function () {
    expect($this->svc->consume('not-a-real-token'))->toBeNull();
});

it('records the purpose and target so a consumer can act on it', function () {
    ['link' => $link] = $this->svc->issue('attach', [
        'email' => 'ada@example.com',
        'reservation_id' => null,
    ]);

    expect($link->purpose)->toBe('attach')
        ->and($link->email)->toBe('ada@example.com')
        ->and($link->isValid())->toBeTrue();
});
