<?php

namespace App\Providers;

use App\Models\MenuItem;
use App\Models\Partner;
use App\Models\PartnerAccess;
use App\Models\Setting;
use App\Models\SocialItem;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

use App\Services\Signatures\SignatureProvider;
use App\Services\Signatures\Providers\PodpislonProvider;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Observers\UserObserver;

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

        // Контекст партнёра — один на запрос
        $this->app->singleton(PartnerContext::class, function () {
            return new PartnerContext();
        });

        // Чтобы не ломать существующий app('current_partner')->id
        $this->app->singleton('current_partner', function () {
            return app(PartnerContext::class)->partner();
        });

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment(['local','development','testing'])) {

//            DB::listen(function ($query) {
//                \Log::debug('SQL', [
//                    'sql'      => $query->sql,
//                    'bindings' => $query->bindings,
//                    'time_ms'  => $query->time,
//                ]);
//            });
        }

        Paginator::useBootstrap();

        // Получаем срок оплаты сервиса
        View::composer('*', function ($view) {
            $latestEndDate = PartnerAccess::where('is_active', 1)->max('end_date');
            $view->with('latestEndDate', $latestEndDate);
        });

        // Баланс партнера
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

        /**
         * ИЗМЕНЁННЫЙ БЛОК: счётчики юзеров и групп
         * Теперь считаем в разрезе текущего партнёра (partner_id),
         * а если партнёр не определён — fallback на глобальные значения.
         */
        View::composer('includes.sidebar', function ($view) {

            $partnerId = session('current_partner')
                ?? auth()->user()?->partner_id
                ?? null;

            if ($partnerId) {
                $teamsCount = Team::where('partner_id', $partnerId)->count();
                $usersCount = User::where('partner_id', $partnerId)->count();
            } else {
                // fallback (например, супер-админ)
                $teamsCount = Team::count();
                $usersCount = User::count();
            }

            $view->with([
                'allTeamsCount' => $teamsCount,
                'allUsersCount' => $usersCount,
            ]);
        });
    }
}