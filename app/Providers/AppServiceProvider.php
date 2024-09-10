<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Pagination\Paginator;
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
        // Делимся переменной с любым представлением
        View::share('isRegistrationActivity', $isRegistrationActivity);
    }
}
