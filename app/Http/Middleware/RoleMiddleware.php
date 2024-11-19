<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed ...$roles
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        // Проверяем, есть ли у пользователя одна из требуемых ролей
        if ($user && in_array($user->role, $roles)) {
            return $next($request);
        }

        // Запрет доступа
        abort(403, 'Access denied');
    }
}
