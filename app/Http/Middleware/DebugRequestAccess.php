<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DebugRequestAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1) Allow via secret header token (for Postman / external checks)
        $expected = (string) env('DEBUG_REQUEST_TOKEN', '');
        $provided = (string) $request->header('X-Debug-Token', '');

        if ($expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
            return $next($request);
        }

        // 2) Allow for authenticated users who passed 2FA and have permission
        if (Auth::check()) {
            $passed2fa = (bool) session('2fa:passed');
            if ($passed2fa && Gate::allows('viewing-all-logs')) {
                return $next($request);
            }
        }

        // Hide existence in production-like environments
        return abort(404);
    }
}


