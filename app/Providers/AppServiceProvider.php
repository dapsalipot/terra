<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Away from a developer's machine, every link route() and url() write starts with APP_URL.
         *
         * Left alone they take their host from the request being answered. Sign-in and password reset emails
         * carry a token, so anyone could ask for one for another person's address under a Host header of
         * their own, and the recipient's click would hand them the token. Local and test runs go on following
         * the request, so one checkout works on terra.test, under `artisan serve` and under Dusk.
         */
        // ponytail: other Host headers are not refused, and url()->full() still repeats one to whoever sent it.
        if (! $this->app->environment('local', 'testing')) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme(parse_url(config('app.url'), PHP_URL_SCHEME));
        }
    }
}
