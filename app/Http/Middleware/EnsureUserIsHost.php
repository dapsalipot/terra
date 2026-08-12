<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsHost
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isHost(), 403, 'Host accounts only.');

        /*
         * Suspension has to stop the host working, not merely hide their listings.
         *
         * Blocking only discovery left a suspended host free to sign in, publish new trips, run
         * rosters and mark people paid — which is most of what a suspension is meant to prevent.
         * Their joiners keep everything: those pages are token-based and live outside this
         * middleware entirely.
         */
        abort_if(
            $request->user()->isSuspended(),
            403,
            'This account is suspended. '.($request->user()->suspended_reason ?: 'Contact Terra for details.'),
        );

        return $next($request);
    }
}
