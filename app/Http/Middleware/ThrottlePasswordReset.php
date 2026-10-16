<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottlePasswordReset
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is('forgot-password')) {
            $key = 'password-reset|'.$request->ip().'|'.mb_strtolower((string) $request->input('email'));

            if ($this->limiter->tooManyAttempts($key, 5)) {
                return response('Too many password reset requests.', 429)
                    ->header('Retry-After', (string) $this->limiter->availableIn($key));
            }

            $this->limiter->hit($key, 60);
        }

        return $next($request);
    }
}
