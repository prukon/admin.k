<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Запоминает IP и браузер последнего запроса ученика, чтобы карточка видела смену страны после входа.
 */
final class RememberClientActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user !== null) {
            $ip = trim((string) $request->ip());
            $userAgent = mb_substr(trim((string) $request->userAgent()), 0, 512);
            $storedIp = trim((string) ($user->last_activity_ip ?? ''));
            $storedAgent = trim((string) ($user->last_activity_user_agent ?? ''));

            if ($ip !== '' && ($ip !== $storedIp || $userAgent !== $storedAgent)) {
                DB::table('users')->where('id', $user->id)->update([
                    'last_activity_ip' => $ip,
                    'last_activity_user_agent' => $userAgent !== '' ? $userAgent : null,
                ]);
                $user->last_activity_ip = $ip;
                $user->last_activity_user_agent = $userAgent !== '' ? $userAgent : null;
            }
        }

        return $next($request);
    }
}
