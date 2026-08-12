<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The admin area's only door.
 *
 * 404 rather than 403 for anyone who is not staff: a 403 confirms the area exists, and there is
 * no reason to tell a curious joiner that it does.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Auth::check() && Auth::user()->isAdmin(), 404);

        return $next($request);
    }
}
