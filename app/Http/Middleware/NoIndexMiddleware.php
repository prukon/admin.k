<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NoIndexMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!config('app.noindex', false)) {
            return $response;
        }

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive', true);

        return $response;
    }
}

