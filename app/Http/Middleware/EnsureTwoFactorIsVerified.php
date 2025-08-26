<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnsureTwoFactorIsVerified
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Роуты процесса 2FA/телефона
        $allowed = [
            'two-factor.challenge',
            'two-factor.verify',
            'two-factor.resend',
            'two-factor.phone',
            'two-factor.phone.save',
        ];
        $routeName = optional($request->route())->getName();
        if (in_array($routeName, $allowed, true)) {
            return $next($request);
        }

        // force_2fa_admins (глобально, partner_id=NULL)
        $forceAdmin2fa = false;
        try {
            if (method_exists(Setting::class, 'getBool')) {
                $forceAdmin2fa = Setting::getBool('force_2fa_admins', false, null);
            } else {
                $row = DB::table('settings')
                    ->where('name', 'force_2fa_admins')
                    ->whereNull('partner_id')
                    ->first(['status']);
                $forceAdmin2fa = $row ? (bool)$row->status : false;
            }
        } catch (\Throwable $e) {
            Log::error('2FA MW: failed to read force_2fa_admins', ['error' => $e->getMessage()]);
            $forceAdmin2fa = false;
        }

        $isAdmin  = ((int)$user->role_id === 10);
        $needs2fa = (bool)$user->two_factor_enabled || ($isAdmin && $forceAdmin2fa);

        $sessionPassed = (bool)session('2fa:passed');

//        Log::info('2FA MW decision', [
//            'user_id'          => $user->id,
//            'role_id'          => $user->role_id,
//            'user_tfa_enabled' => (bool)$user->two_factor_enabled,
//            'forceAdmin2fa'    => $forceAdmin2fa,
//            'needs2fa'         => $needs2fa,
//            'session_passed'   => $sessionPassed,
//            'route'            => $routeName,
//            'has_phone'        => (bool)$user->phone,
//        ]);

        if ($needs2fa && !$sessionPassed) {
            if (empty($user->phone)) {
                Log::info('2FA MW: 2FA required, phone empty -> two-factor.phone', ['user_id' => $user->id]);
                return redirect()->route('two-factor.phone');
            }
            Log::info('2FA MW: 2FA required -> two-factor.challenge', ['user_id' => $user->id]);
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
