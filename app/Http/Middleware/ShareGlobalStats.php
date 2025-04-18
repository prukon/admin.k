<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\View;
use App\Models\Team;
use App\Models\User;

class ShareGlobalStats
{
    public function handle($request, Closure $next)
    {
        View::share('allTeamsCount', Team::count());
        View::share('allUsersCount',  User::count());

        return $next($request);
    }
}
