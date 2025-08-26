<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\View;
use App\Models\MenuItem;

class SetMenuItems
{
    public function handle($request, Closure $next)
    {
        // 1) Проверяем сессию на только что выбранного партнёра
        if (session()->has('current_partner')) {
            $partnerId = session('current_partner');
        }
        // 2) Иначе — берём партнёра из авторизации, если нужно
        else {
            $partnerId = optional(auth()->user())->partner_id;
        }

        // Для дебага: пишем в лог
//        \Log::debug('SetMenuItems: partner_id=', ['partnerId' => $partnerId]);

        // Загружаем пункты меню только для этого партнёра (или пустую коллекцию)
        $menuItems = $partnerId
            ? MenuItem::where('partner_id', $partnerId)->get()
            : collect();

        // Делаем доступным во всех blade
        View::share('menuItems', $menuItems);

        return $next($request);
    }
}
