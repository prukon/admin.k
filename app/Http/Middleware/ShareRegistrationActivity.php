<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\View;
use App\Models\Setting;

class ShareRegistrationActivity
{
//    только 1 флаг разрешения
    public function handle2($request, Closure $next)
    {
        // Берём настройку по имени
        $setting = Setting::where('name', 'registrationActivity')->first();

        // Если есть — берём статус, иначе null
        $isRegistrationActivity = $setting
            ? $setting->status
            : null;

        // Делаем доступным во всех blade
        View::share('isRegistrationActivity', $isRegistrationActivity);

        return $next($request);
    }

//     массив флаг + partner_id
    public function handle($request, Closure $next)
    {
        // 1) Определяем текущего партнёра
        $partnerId = session('current_partner')
            ?? optional(auth()->user())->partner_id;

        // 2) Берём из БД настройку активности регистрации для этого партнёра
        //    (если в таблице settings есть колонка partner_id — фильтруем по ней,
        //     иначе уберите ->where('partner_id', $partnerId))
        $setting = Setting::where('name', 'registrationActivity')
            ->when($partnerId, fn($q) => $q->where('partner_id', $partnerId))
                          ->first();

        $isRegistrationActivity = $setting
            ? $setting->status
            : null;

        // 3) Прокидываем в представления массив с partner_id и флагом
        $registrationConfig = [
            'partner_id'             => $partnerId,
            'isRegistrationActivity' => $isRegistrationActivity,
        ];

        View::share('registrationConfig', $registrationConfig);

        return $next($request);
    }

}







