<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Servises\SmsRuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function showChallenge(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        if (session('2fa:passed') === true) {
            return redirect()->intended('/');
        }

        // Кулдаун до повторной отправки
        $cooldownSec = 0;
        if ($last = session('2fa:last_sent_at')) {
            try {
                $diff = now()->diffInSeconds(\Illuminate\Support\Carbon::parse($last));
                $cooldownSec = max(0, 60 - $diff);
            } catch (\Throwable) {}
        }

        // Красивый номер
        $formattedPhone = $this->formatRuPhoneForDisplay($request->user()->phone);

        return view('auth.two-factor', compact('cooldownSec', 'formattedPhone'));
    }

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

        // кому нужна 2FA
        $needs2fa = ((int)$user->role_id === 10) || (bool)$user->two_factor_enabled;
        if (!$needs2fa) {
            session(['2fa:passed' => true]);
            return redirect()->intended('/');
        }

        if (empty($user->two_factor_code) || empty($user->two_factor_expires_at)) {
            return back()->withErrors(['code' => 'Код не был сгенерирован. Нажмите «Отправить повторно».']);
        }
        if (now()->greaterThan($user->two_factor_expires_at)) {
            return back()->withErrors(['code' => 'Срок действия кода истёк. Нажмите «Отправить повторно».']);
        }
        if (!Hash::check($request->input('code'), $user->two_factor_code)) {
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

        $code = (string)random_int(100000, 999999);
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
            $a = substr($d, 1, 3);
            $p1 = substr($d, 4, 3);
            $p2 = substr($d, 7, 2);
            $p3 = substr($d, 9, 2);
            return "+7 ({$a}) {$p1}-{$p2}-{$p3}";
        }
        return $phone;
    }
}
