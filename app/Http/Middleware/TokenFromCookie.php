<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() && $token = $request->cookie('sakan_token')) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
