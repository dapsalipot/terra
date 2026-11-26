<?php

use App\Mail\MagicLinkMail;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Boot the app's own provider again as a given environment would: it reads APP_ENV and APP_URL at boot. */
function bootLinksAs(string $environment): void
{
    config(['app.url' => 'https://tarana.example']);
    app()['env'] = $environment;
    (new AppServiceProvider(app()))->boot();
}

it('names its own address in the sign-in email, whatever Host the request claims', function (string $environment) {
    Mail::fake();
    $user = User::factory()->create();
    bootLinksAs($environment);

    // Outside the testing environment the framework asks for a CSRF token, which is not what is on trial here.
    $this->withoutMiddleware(ValidateCsrfToken::class)
        ->post('http://attacker.example/magic', ['email' => $user->email])
        ->assertRedirect();

    Mail::assertSent(MagicLinkMail::class, 1);
    expect(Mail::sent(MagicLinkMail::class)->first()->url)->toStartWith('https://tarana.example/magic/');
})->with(['production', 'staging']);

it('names its own address in the password reset email too', function () {
    $user = User::factory()->create();
    bootLinksAs('production');
    // The reset link is written while a request is being answered, so hand the URL generator the forged one.
    app('url')->setRequest(Request::create('http://attacker.example/forgot-password', 'POST'));

    expect((new ResetPassword('the-token'))->toMail($user)->actionUrl)
        ->toStartWith('https://tarana.example/reset-password/the-token');
});

it('follows whatever address is serving the app on a developer machine and under test', function (string $environment) {
    bootLinksAs($environment);
    app('url')->setRequest(Request::create('http://127.0.0.1:8020/'));

    expect(route('trips.index'))->toBe('http://127.0.0.1:8020/trips');
})->with(['local', 'testing']);
