<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Partner;
use App\Services\PartnerContext;

class SetPartner
{
    public function __construct(
        protected PartnerContext $partnerContext
    ) {}

    public function handle(Request $request, Closure $next)
    {

        // Если пользователь не авторизован, пропускаем middleware
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Для не-superadmin запрещаем переключение партнёра через session.
        // Всегда фиксируем current_partner = user->partner_id.
        if (!$this->partnerContext->isSuperAdmin($user)) {
            session(['current_partner' => $user?->partner_id]);
        }

        // Если пользователь авторизован и в сессии ещё не установлен партнёр, устанавливаем его из данных пользователя
        if (!session()->has('current_partner')) {
            session(['current_partner' => $user?->partner_id]);
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


