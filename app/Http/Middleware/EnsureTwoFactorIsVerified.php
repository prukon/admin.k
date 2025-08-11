<?php
// app/Http/Middleware/EnsureTwoFactorIsVerified.php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\User;
use App\Servises\SmsRuService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EnsureTwoFactorIsVerified
{
    public function handle2(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        // Не вмешиваемся в страницы 2FA, чтобы не словить цикл
        if ($request->routeIs(['two-factor.*'])) {
            return $next($request);
        }

        /** @var User $user */
        $user = Auth::user();

        // 2FA нужна, если включена пользователем ИЛИ если он админ (id 10)
        $needs2fa = (int)$user->role_id === 10 || (bool)$user->two_factor_enabled;

        if (!$needs2fa) {
            // На всякий случай почистим маркеры
            session()->forget(['2fa:passed', '2fa:user:id', '2fa:last_sent_at']);
            return $next($request);
        }

        // Если уже подтверждал в этой сессии — пускаем
        if (session('2fa:passed') === true) {
            return $next($request);
        }

        // Если недавно не отправляли код — отправим
        $lastSent = session('2fa:last_sent_at');
        $tooSoon  = $lastSent && now()->diffInSeconds($lastSent) < 60;


        // ... внутри handle(), перед генерацией кода:
        $rawPhone = (string)($user->phone ?? '');
        $normalized = preg_replace('/\D+/', '', $rawPhone);

// 8XXXXXXXXXX -> 7XXXXXXXXXX
        if (strlen($normalized) === 11 && str_starts_with($normalized, '8')) {
            $normalized = '7' . substr($normalized, 1);
        }
// XXXXXXXXXX -> 7XXXXXXXXXX
        if (strlen($normalized) === 10) {
            $normalized = '7' . $normalized;
        }

// если номер невалиден — просим указать
        if (!$normalized || !preg_match('/^7\d{10}$/', $normalized)) {
            return redirect()
                ->route('two-factor.phone')
                ->with('error', 'Для 2FA укажите телефон в формате 79XXXXXXXXX.');
        }



        if (!$tooSoon) {
            $code = (string)random_int(100000, 999999);

            $user->forceFill([
                // Храним ХЭШ кода — безопаснее
                'two_factor_code'       => Hash::make($code),
                'two_factor_expires_at' => now()->addMinutes(10),
            ])->save();

            $sms = app(SmsRuService::class);
//            $result = $sms->send($user->phone, "Код для входа: {$code}. Действителен 10 минут.");

            $result = $sms->send($normalized, "Код для входа: {$code}. Действителен 10 минут.");


            \Log::info('2FA: результат отправки SMS', [
                'user_id' => $user->id,
                'phone'   => '***'.substr($normalized, -4),
                'result'  => $result === true ? 'OK' : $result,
            ]);

            // Можно логировать $result при неуспехе
            session([
                '2fa:user:id'    => $user->id,
                '2fa:last_sent_at' => now(),
            ]);
        }

        return redirect()->route('two-factor.challenge');
    }

    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $forceAdmin2fa = Setting::getBool('force_2fa_admins', false, null);

        $isAdminRole10     = ((int)$user->role_id === 10);
        $userTfaEnabled    = (bool)$user->two_factor_enabled;
        $needs2fa          = ($isAdminRole10 && $forceAdmin2fa) || $userTfaEnabled;
        $sessionPassed     = (bool)session('2fa:passed');

        \Log::info('2FA MW: decision point', [
            'user_id'          => $user->id,
            'role_id'          => $user->role_id,
            'forceAdmin2fa'    => $forceAdmin2fa,
            'user_tfa_enabled' => $userTfaEnabled,
            'needs2fa'         => $needs2fa,
            'session_passed'   => $sessionPassed,
            'phone'            => $user->phone ? '***'.substr($user->phone, -4) : null,
        ]);

        if ($needs2fa && !$sessionPassed) {
            \Log::info('2FA MW: redirect to challenge', ['route' => 'two-factor.challenge']);
            return redirect()->route('two-factor.challenge');
        }

        \Log::info('2FA MW: passed, continue');
        return $next($request);
    }


}


