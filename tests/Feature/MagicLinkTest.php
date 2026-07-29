<?php

use App\Models\MagicLink;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\DB;

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

it('will not re-stamp a token that was already used', function () {
    $user = User::factory()->create();
    ['link' => $link, 'url' => $url] = $this->svc->issue('login', ['user_id' => $user->id]);
    $raw = str($url)->afterLast('/')->toString();

    $firstUse = now()->subMinutes(5);
    $link->forceFill(['used_at' => $firstUse])->save();

    expect($this->svc->consume($raw))->toBeNull()
        // The old unconditional update would move used_at to now().
        ->and($link->fresh()->used_at->timestamp)->toBe($firstUse->timestamp);
});

it('lets only one of two concurrent redemptions of the same token win', function () {
    $user = User::factory()->create();
    ['url' => $url] = $this->svc->issue('login', ['user_id' => $user->id]);
    $raw = str($url)->afterLast('/')->toString();
    $hash = hash('sha256', $raw);

    // Simulate a second request racing this one for the same raw token by re-entering
    // consume() from a query listener, right as this call's first database statement
    // completes. A read-then-write implementation issues a SELECT before it decides
    // anything, leaving a window where a second, interleaved caller can also read
    // "unused" and also go on to redeem it. An atomic conditional UPDATE has no such
    // window: its first (and decisive) statement is the guarded write itself, so a
    // caller that interleaves at that same point is already too late.
    $reentered = false;
    $innerResult = null;

    DB::listen(function ($query) use (&$reentered, &$innerResult, $hash, $raw) {
        if ($reentered || ! str_contains($query->sql, 'select') || ! in_array($hash, $query->bindings, true)) {
            return;
        }

        $reentered = true;
        $innerResult = $this->svc->consume($raw);
    });

    $outerResult = $this->svc->consume($raw);

    $winners = collect([$outerResult, $innerResult])->filter(fn ($r) => $r instanceof MagicLink)->count();

    expect($reentered)->toBeTrue()
        ->and($winners)->toBe(1);
});
