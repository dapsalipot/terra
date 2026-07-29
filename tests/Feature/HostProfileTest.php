<?php

use App\Models\HostProfile;
use App\Models\User;

it('flags a host and exposes json payment methods', function () {
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline']);
    HostProfile::factory()->for($host)->create([
        'currency' => 'PHP',
        'payment_methods' => [
            ['label' => 'Bank transfer', 'detail' => '•••• 4821'],
            ['label' => 'Wise', 'detail' => '@ridgeline'],
        ],
    ]);

    expect($host->isHost())->toBeTrue()
        ->and($host->hostProfile->payment_methods)->toHaveCount(2)
        ->and($host->hostProfile->currency)->toBe('PHP');
});

it('treats a joiner as not a host', function () {
    expect(User::factory()->create(['role' => 'joiner'])->isHost())->toBeFalse();
});
