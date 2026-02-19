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
        $isSuperAdmin = $this->partnerContext->isSuperAdmin($user);

        // Для не-superadmin запрещаем переключение партнёра через session.
        // Всегда фиксируем current_partner = user->partner_id.
        if (!$isSuperAdmin) {
            session(['current_partner' => $user?->partner_id]);
        }

        // Если пользователь авторизован и в сессии ещё не установлен партнёр, устанавливаем его из данных пользователя
        if (!session()->has('current_partner')) {
            session(['current_partner' => $user?->partner_id]);
        }

        $partnerId = session('current_partner');

        // Супер-админ должен иметь возможность открыть список партнёров даже без выбранного контекста.
        if (!$partnerId) {
            if ($isSuperAdmin) {
                // allow-list: супер-админу нужно уметь выбрать партнёра, пройти 2FA и выйти,
                // даже если текущий партнёр ещё не выбран (или был сброшен).
                if ($request->routeIs('admin.partner.index', 'partner.switch', 'two-factor.*', 'logout')) {
                    return $next($request);
                }

                return redirect()
                    ->route('admin.partner.index')
                    ->withErrors(['partner' => 'Партнёр не выбран.']);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Ваша организация недоступна.']);
        }

        $partner = Partner::find($partnerId);

        // Если партнёра с таким ID не найден, возвращаем редирект с ошибкой
        if (!$partner) {
            if ($isSuperAdmin) {
                session()->forget('current_partner');

                return redirect()
                    ->route('admin.partner.index')
                    ->withErrors(['partner' => 'Текущий партнёр недоступен. Выберите другого.']);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Ваша организация недоступна.']);
        }

        // Регистрируем объект партнёра в контейнере приложения для дальнейшего удобного доступа
        app()->instance('current_partner', $partner);

        return $next($request);
    }

}


