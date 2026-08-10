<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Redirects a logged-in user who hasn't finished onboarding (role, address...) to /onboarding. */
class EnsureProfileOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::user()?->profile?->isOnboarded()) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
