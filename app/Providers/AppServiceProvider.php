<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

//        Прокидывание переменной авторизации
        $setting = Setting::where('name', 'registrationActivity')->first();
        $isRegistrationActivity = $setting ? $setting->status : null;
        $allTeamsCount = Team::all()->count();
        $allUsersCount = User::all()->count();


        // Убедитесь, что запрос инициализирован
//        if (Auth::check()) {
//            $currentUserId = Auth::id(); // Получение ID текущего пользователя
//            $currentUserName = Auth::user()->name; // Получение имени текущего пользователя
//        }

        View::share('isRegistrationActivity', $isRegistrationActivity);
        View::share('allTeamsCount', $allTeamsCount);
        View::share('allUsersCount', $allUsersCount);

//        View::share('currentUserId', $currentUserId);
//        View::share('currentUserName', $currentUserName);

    }
}