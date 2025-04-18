<?php

namespace App\Providers;

use App\Models\MenuItem;
use App\Models\PartnerAccess;
use App\Models\Setting;
use App\Models\SocialItem;
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


        // Убедитесь, что запрос инициализирован
//        if (Auth::check()) {
//            $currentUserId = Auth::id(); // Получение ID текущего пользователя
//            $currentUserName = Auth::user()->name; // Получение имени текущего пользователя
//        }

        //Получаем срок оплаты сервиса
        View::composer('*', function ($view) {
            $latestEndDate = PartnerAccess::where('is_active', 1)->max('end_date');
            $view->with('latestEndDate', $latestEndDate);
        });


    }
}