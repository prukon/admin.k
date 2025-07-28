<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Partner;

class SetPartner
{
    public function handle(Request $request, Closure $next)
    {

        // Если пользователь не авторизован, пропускаем middleware
        if (!Auth::check()) {
            return $next($request);
        }

        // Если пользователь авторизован и в сессии ещё не установлен партнёр, устанавливаем его из данных пользователя
        if (Auth::check() && !session()->has('current_partner')) {
            session(['current_partner' => Auth::user()->partner_id]);
        }

        // Проверяем наличие партнёра в сессии
        if (!session()->has('current_partner')) {
            return redirect()->back()->withErrors(['partner' => 'Партнёр не выбран.']);
        }

        $partnerId = session('current_partner');
        $partner = Partner::find($partnerId);

        // Если партнёра с таким ID не найден, возвращаем редирект с ошибкой
        if (!$partner) {
            return redirect()->back()->withErrors(['partner' => 'Партнёр не выбран.']);
        }

        // Регистрируем объект партнёра в контейнере приложения для дальнейшего удобного доступа
        app()->instance('current_partner', $partner);

        return $next($request);
    }

}


