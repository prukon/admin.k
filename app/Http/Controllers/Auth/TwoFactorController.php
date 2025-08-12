<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Servises\SmsRuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    /**
     * Страница ввода кода 2FA.
     * Если кода нет/истёк и кулдаун не активен — автоматически сгенерим и отправим SMS.
     */
    public function showChallenge(Request $request, SmsRuService $sms)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        if (session('2fa:passed') === true) {
            return redirect()->intended('/');
        }

        $user = $request->user();

        // Определяем, нужна ли 2FA (включена у юзера ИЛИ включена принудительно для админов)
        $forceAdmin2fa = Setting::getBool('force_2fa_admins', false);
        $needs2fa = (((int)$user->role_id === 10) && $forceAdmin2fa) || (bool)$user->two_factor_enabled;

        \Log::info('2FA showChallenge: entry', [
            'user_id'          => $user->id,
            'role_id'          => $user->role_id,
            'forceAdmin2fa'    => $forceAdmin2fa,
            'user_tfa_enabled' => (bool)$user->two_factor_enabled,
            'needs2fa'         => $needs2fa,
            'session_passed'   => (bool)session('2fa:passed'),
        ]);

        if (!$needs2fa) {
            session(['2fa:passed' => true]);
            \Log::info('2FA showChallenge: no 2fa needed -> pass', ['user_id' => $user->id]);
            return redirect()->intended('/');
        }

        if (!$user->phone) {
            \Log::warning('2FA showChallenge: no phone set', ['user_id' => $user->id]);
            return redirect()->route('two-factor.phone')->with('error', 'В профиле не указан телефон. Укажите телефон для получения кода.');
        }

        // Кулдаун до повторной отправки
        $cooldownSec = 0;
        if ($last = session('2fa:last_sent_at')) {
            try {
                $diff = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($last));
                $cooldownSec = max(0, 60 - $diff);
            } catch (\Throwable) {}
        }

        // Если активного кода нет или он истёк — отправим автоматически (если кулдаун не висит)
        $hasActiveCode = !empty($user->two_factor_code) && !empty($user->two_factor_expires_at) && now()->lessThan($user->two_factor_expires_at);

        \Log::info('2FA showChallenge: code status', [
            'user_id'        => $user->id,
            'hasActiveCode'  => $hasActiveCode,
            'expires_at'     => $user->two_factor_expires_at,
            'cooldownSec'    => $cooldownSec,
        ]);

        if (!$hasActiveCode && $cooldownSec === 0) {
            $code = (string) random_int(100000, 999999);
            $user->forceFill([
                'two_factor_code'       => \Hash::make($code),
                'two_factor_expires_at' => now()->addMinutes(10),
            ])->save();

            $result = $sms->send($user->phone, "Код для входа: {$code}. Действителен 10 минут.");

            \Log::info('2FA showChallenge: auto-send result', [
                'user_id' => $user->id,
                'phone'   => '***' . substr($user->phone, -4),
                'result'  => $result === true ? 'OK' : $result,
            ]);

            session(['2fa:last_sent_at' => now()]);
            // обновим кулдаун для фронта
            $cooldownSec = 60;

            if ($result !== true) {
                // Не блокируем отображение страницы — пусть пользователь сможет нажать «Отправить повторно»
                // Ошибку видно в логах; при желании можно добавить flash с ошибкой
                // session()->flash('status', is_string($result) ? $result : 'Не удалось отправить SMS.');
            } else {
                // Можем показать подсказку
                // session()->flash('status', 'Код отправлен автоматически.');
            }
        }

        // Красивый номер
        $formattedPhone = $this->formatRuPhoneForDisplay($user->phone);

        return view('auth.two-factor', compact('cooldownSec', 'formattedPhone'));
    }

    /**
     * Проверка введённого кода
     */
    public function verify(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required','digits:6'],
        ],[
            'code.required' => 'Введите код из SMS',
            'code.digits'   => 'Код должен состоять из 6 цифр',
        ]);

        $user = $request->user();

        $forceAdmin2fa = Setting::getBool('force_2fa_admins', false);
        $needs2fa = (((int)$user->role_id === 10) && $forceAdmin2fa) || (bool)$user->two_factor_enabled;

        \Log::info('2FA verify: before check', [
            'user_id'          => $user->id,
            'role_id'          => $user->role_id,
            'forceAdmin2fa'    => $forceAdmin2fa,
            'user_tfa_enabled' => (bool)$user->two_factor_enabled,
            'needs2fa'         => $needs2fa,
            'has_code'         => (bool)$user->two_factor_code,
            'expires_at'       => $user->two_factor_expires_at,
        ]);

        if (!$needs2fa) {
            session(['2fa:passed' => true]);
            \Log::info('2FA verify: needs2fa=false -> pass', ['user_id' => $user->id]);
            return redirect()->intended('/');
        }

        if (empty($user->two_factor_code) || empty($user->two_factor_expires_at)) {
            return back()->withErrors(['code' => 'Код не был сгенерирован. Нажмите «Отправить повторно».']);
        }
        if (now()->greaterThan($user->two_factor_expires_at)) {
            return back()->withErrors(['code' => 'Срок действия кода истёк. Нажмите «Отправить повторно».']);
        }
        if (!Hash::check($request->input('code'), $user->two_factor_code)) {
            \Log::warning('2FA verify: wrong code', ['user_id' => $user->id]);
            return back()->withErrors(['code' => 'Неверный код.']);
        }

        // успех
        $user->forceFill([
            'two_factor_code'       => null,
            'two_factor_expires_at' => null,
            'phone_verified_at'     => $user->phone_verified_at ?: now(),
        ])->save();

        \Log::info('2FA: verify success', [
            'user_id' => $user->id,
            'phone'   => $user->phone ? '***'.substr($user->phone, -4) : null,
        ]);

        session(['2fa:passed' => true]);
        session()->forget(['2fa:last_sent_at', '2fa:user:id']);

        return redirect()->intended('/');
    }

    /**
     * Ручная повторная отправка кода
     */
    public function resend(Request $request, SmsRuService $sms)
    {
        if (!Auth::check()) return redirect()->route('login');

        $user = $request->user();
        if (!$user->phone) {
            return back()->withErrors(['resend' => 'В профиле не указан телефон.']);
        }

        $last = session('2fa:last_sent_at');
        if ($last && now()->diffInSeconds($last) < 60) {
            return back()->withErrors(['resend' => 'Повторная отправка доступна через минуту.']);
        }

        $code = (string) random_int(100000, 999999);
        $user->forceFill([
            'two_factor_code'       => \Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ])->save();

        $result = $sms->send($user->phone, "Код для входа: {$code}. Действителен 10 минут.");

        \Log::info('2FA: resend result', [
            'user_id' => $user->id,
            'phone'   => '***'.substr($user->phone, -4),
            'result'  => $result === true ? 'OK' : $result,
        ]);

        session(['2fa:last_sent_at' => now()]);

        if ($result !== true) {
            return back()->withErrors(['resend' => is_string($result) ? $result : 'Не удалось отправить SMS.']);
        }

        return back()->with('status', 'Код отправлен повторно.');
    }

    private function formatRuPhoneForDisplay(?string $phone): string
    {
        if (!$phone) return '—';
        $d = preg_replace('/\D+/', '', $phone);
        if (strlen($d) === 11 && str_starts_with($d, '7')) {
            $a  = substr($d, 1, 3);
            $p1 = substr($d, 4, 3);
            $p2 = substr($d, 7, 2);
            $p3 = substr($d, 9, 2);
            return "+7 ({$a}) {$p1}-{$p2}-{$p3}";
        }
        return $phone;
    }
}
