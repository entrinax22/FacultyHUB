<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! config('auth.require_admin_2fa')
            || ! $user?->isAdmin()
            || $user->hasEnabledTwoFactorAuthentication()
            || $request->routeIs('security.edit', 'two-factor.*', 'password.confirm')
        ) {
            return $next($request);
        }

        return redirect()
            ->route('security.edit')
            ->with('warning', 'Enable two-factor authentication before accessing administrator features.');
    }
}
