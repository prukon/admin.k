<?php

namespace App\Providers;

use App\Models\MenuItem;
use App\Models\Partner;
use App\Models\PartnerAccess;
use App\Models\Setting;
use App\Models\SocialItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

use App\Services\Signatures\SignatureProvider;
use App\Services\Signatures\Providers\PodpislonProvider;


use Illuminate\Support\Facades\Cache;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SignatureProvider::class, function () {
            return new PodpislonProvider();
        });
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


        //Баланс партнера
        View::composer('layouts.admin2', function ($view) {

            $balance = 0.0;
            if (Auth::check()) {
                $partnerId = auth()->user()->partner_id ?? session('partner_id'); // замени на свою логику
                if ($partnerId) {
                    $balance = Cache::remember("partner_balance_{$partnerId}", 60, function () use ($partnerId) {
                        return (float) (Partner::where('id', $partnerId)->value('wallet_balance') ?? 0);
                    });
                }
            }

            $view->with([

                'partnerWalletBalance' => $balance,
            ]);
        });

        //Каунтеры юзеров и групп
        View::composer('includes.sidebar', function ($view) {
            $view->with([
                'allTeamsCount'  =>  $allTeamsCount = Team::count(),
                'allUsersCount'  => $allUsersCount = User::count(),
            ]);
        });

    }
}