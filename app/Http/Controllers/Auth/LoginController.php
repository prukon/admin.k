<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Support\OpsMonitor;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Throwable;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
//    protected $redirectTo = RouteServiceProvider::HOME;
    protected function redirectTo()
    {
        return '/cabinet'; // Перенаправление на главную страницу
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm()
    {
        // Ваши данные, которые нужно передать в представление

        $setting = Setting::where('name', 'registrationActivity')->first();
        $isRegistrationActivity = $setting ? $setting->status : null;


        // Возвращаем view с данными
//        return view('auth.login',
//            ['customData' => $customData]);

        return view("auth.login", compact(
            "isRegistrationActivity",
        ));
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');
        $user = User::query()->where('email', $request->email)->first();

        OpsMonitor::recordFailedLogin([
            'email' => $email,
            'password' => $password,
            'ip' => $request->ip(),
            'user_found' => $user !== null,
        ]);
        $this->recordFailedLoginAudit($request, $user, $email);

        if (! $user) {
            return back()->withInput()->withErrors([
                'email' => 'Такой email не найден.',
            ]);
        }

        return back()->withInput()->withErrors([
            'password' => 'Неправильный пароль.',
        ]);
    }

    private function recordFailedLoginAudit(Request $request, ?User $user, string $email): void
    {
        try {
            $reason = $user === null ? 'email не найден' : 'неверный пароль';
            $emailLabel = $email !== '' ? $email : '—';
            $ip = (string) ($request->ip() ?? '—');
            $ua = trim((string) $request->userAgent());
            if (mb_strlen($ua) > 200) {
                $ua = mb_substr($ua, 0, 199).'…';
            }
            if ($ua === '') {
                $ua = '—';
            }

            $description = "Неуспешный вход ({$reason}). Email: {$emailLabel}. IP: {$ip}. UA: {$ua}";
            if ($user !== null) {
                $description .= '. Пользователь #'.$user->id;
            }

            $context = AuditContext::make($description);
            if ($user !== null) {
                $context = $context
                    ->withUser($user)
                    ->withPartnerId($user->partner_id !== null ? (int) $user->partner_id : null);
            }

            app(AuditLogger::class)->record(AuditEvent::AuthLoginFailed, $context);
        } catch (Throwable) {
        }
    }


//    public function logout(Request $request)
//    {
//        Auth::logout();
//
//        $request->session()->invalidate();
//        $request->session()->regenerateToken();
//
//        return redirect('/');
//    }

}
