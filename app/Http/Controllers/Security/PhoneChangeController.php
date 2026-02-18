<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SmsRuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PhoneChangeController extends Controller
{
    private function normalize(?string $phone): ?string
    {
        if (!$phone) return null;
        $d = preg_replace('/\D+/', '', $phone);
        if (strlen($d) === 11 && str_starts_with($d, '8')) $d = '7'.substr($d,1);
        if (strlen($d) === 10) $d = '7'.$d;
        return $d ?: null;
    }

    public function showForm(Request $request)
    {
        $u = $request->user();

        // кулдауны
        $cooldownOld = 0;
        if ($ts = session('phone_change:old_last_sent_at')) {
            try { $cooldownOld = max(0, 60 - now()->diffInSeconds(\Illuminate\Support\Carbon::parse($ts))); } catch (\Throwable) {}
        }
        $cooldownNew = 0;
        if ($ts = session('phone_change:new_last_sent_at')) {
            try { $cooldownNew = max(0, 60 - now()->diffInSeconds(\Illuminate\Support\Carbon::parse($ts))); } catch (\Throwable) {}
        }

        // Обязательна ли 2FA для админов (роль 10) по глобальной настройке
        $forceAdmin2fa = Setting::getBool('force_2fa_admins', false);

        // Если 2FA включена у юзера или (он админ 10 и флаг включён) и есть текущий phone — сначала подтверждаем СТАРЫЙ
        $mustVerifyOld = ((((int)$u->role_id === 10) && $forceAdmin2fa) || (bool)$u->two_factor_enabled) && !empty($u->phone);

        // Стейдж из сессии (основной источник правды), иначе вычисляем по БД
        $stage = session('phone_change:stage');
        if (!in_array($stage, ['start','old','new'], true)) {
            if ($mustVerifyOld) {
                if ($u->phone_change_old_code && $u->phone_change_old_expires_at && now()->lessThan($u->phone_change_old_expires_at)) {
                    $stage = 'old';
                } elseif ($u->two_factor_phone_pending) {
                    $stage = 'new';
                } else {
                    $stage = 'start';
                }
            } else {
                $stage = $u->two_factor_phone_pending ? 'new' : 'start';
            }
            session(['phone_change:stage' => $stage]);
        }

        return view('auth.phone-change-strong', [
            'currentPhone' => $u->phone,
            'pendingPhone' => $u->two_factor_phone_pending,
            'stage'        => $stage,         // start | old | new
            'cooldownOld'  => $cooldownOld,
            'cooldownNew'  => $cooldownNew,
            'mustVerifyOld'=> $mustVerifyOld,
            'oldPretty'    => $this->formatRuPhonePretty($u->phone),
            'newPretty'    => $this->formatRuPhonePretty($u->two_factor_phone_pending),
        ]);
    }

    // Шаг 1: пароль + новый номер → если нужно, код на СТАРЫЙ; иначе код на НОВЫЙ
    public function start(Request $request, SmsRuService $sms)
    {
        $u = $request->user();

        $request->validate([
            'current_password' => ['required','string'],
            'new_phone'        => ['required','string'],
        ],[
            'current_password.required' => 'Введите текущий пароль',
            'new_phone.required'        => 'Укажите новый номер телефона',
        ]);

        if (!Hash::check($request->input('current_password'), $u->password)) {
            return back()->withErrors(['current_password' => 'Неверный текущий пароль'])->withInput();
        }

        $new = $this->normalize($request->input('new_phone'));
        if (!$new || !preg_match('/^7\d{10}$/', $new)) {
            return back()->withErrors(['new_phone' => 'Телефон должен быть формата 79XXXXXXXXX'])->withInput();
        }
        if ($new === $u->phone) {
            return back()->withErrors(['new_phone' => 'Новый номер совпадает с текущим'])->withInput();
        }

        // антиспам (в час не более 5)
        $key = 'phone_change:cnt:'.$u->id;
        $cnt = cache()->get($key, 0);
        if ($cnt >= 5) {
            return back()->withErrors(['resend' => 'Превышен лимит отправок. Попробуйте позже.']);
        }

        // учитывать глобальную настройку
        $forceAdmin2fa = Setting::getBool('force_2fa_admins', false);
        $mustVerifyOld = ((((int)$u->role_id === 10) && $forceAdmin2fa) || (bool)$u->two_factor_enabled) && !empty($u->phone);

        if ($mustVerifyOld) {
            $last = session('phone_change:old_last_sent_at');
            if ($last && now()->diffInSeconds($last) < 60) {
                return back()->withErrors(['resend_old' => 'Повторная отправка на старый номер доступна через минуту.'])->withInput();
            }

            $code = (string)random_int(100000, 999999);
            $u->forceFill([
//                'two_factor_phone_pending'    => $new,
                'two_factor_phone_pending'    => str_starts_with($new, '+') ? $new : ('+'.$new),
                'phone_change_old_code'       => Hash::make($code),
                'phone_change_old_expires_at' => now()->addMinutes(10),
            ])->save();

            $result = $sms->send($u->phone, "Подтверждение смены номера: код {$code}. Действителен 10 минут.");

            Log::info('PhoneChangeStrong: start -> OLD code sent', [
                'user_id' => $u->id,
                'old'     => '***'.substr((string)$u->phone, -4),
                'pending' => '***'.substr($new, -4),
                'result'  => $result === true ? 'OK' : $result,
            ]);

            session(['phone_change:old_last_sent_at' => now(), 'phone_change:stage' => 'old']);
            cache()->put($key, $cnt + 1, now()->addHour());

            if ($result !== true) {
                return back()->withErrors(['resend_old' => is_string($result) ? $result : 'Не удалось отправить SMS на старый номер.']);
            }

            return back()->with('status', 'Код отправлен на старый номер. Подтвердите его, затем подтвердим новый.');
        }

        // 2FA не обязательна — сразу код на НОВЫЙ
        $last = session('phone_change:new_last_sent_at');
        if ($last && now()->diffInSeconds($last) < 60) {
            return back()->withErrors(['resend_new' => 'Повторная отправка на новый номер доступна через минуту.'])->withInput();
        }

        $code = (string)random_int(100000, 999999);
        $u->forceFill([
//            'two_factor_phone_pending'    => $new,
            'two_factor_phone_pending'    => str_starts_with($new, '+') ? $new : ('+'.$new),
            'phone_change_new_code'       => Hash::make($code),
            'phone_change_new_expires_at' => now()->addMinutes(10),
        ])->save();

        Log::debug('PC.start: after-save (model snapshot)', [
            'user_id'       => $u->id,
            'pending_attr'  => $u->getAttributes()['two_factor_phone_pending'] ?? null, // что в "сырых" атрибутах модели
            'pending_prop'  => $u->two_factor_phone_pending,                             // что вернёт аксессор/каст (если есть)
        ]);

        $directDbPending = DB::table('users')->where('id', $u->id)->value('two_factor_phone_pending');
        Log::debug('PC.start: after-save (db readback)', [
            'user_id'      => $u->id,
            'db_pending'   => $directDbPending,
        ]);


        $result = $sms->send($new, "Подтверждение номера: код {$code}. Действителен 10 минут.");

        Log::info('PhoneChangeStrong: start -> NEW code sent (no old verify)', [
            'user_id' => $u->id,
            'pending' => '***'.substr($new, -4),
            'result'  => $result === true ? 'OK' : $result,
        ]);

        session(['phone_change:new_last_sent_at' => now(), 'phone_change:stage' => 'new']);
        cache()->put($key, $cnt + 1, now()->addHour());

        if ($result !== true) {
            return back()->withErrors(['resend_new' => is_string($result) ? $result : 'Не удалось отправить SMS на новый номер.']);
        }

        return back()->with('status', 'Код отправлен на новый номер. Введите код для завершения.');
    }

    // Шаг 2: подтверждаем СТАРЫЙ, после успеха → Шаг 3
    public function verifyOld(Request $request, SmsRuService $sms)
    {
        $u = $request->user();

        $forceAdmin2fa = Setting::getBool('force_2fa_admins', false);
        $mustVerifyOld = ((((int)$u->role_id === 10) && $forceAdmin2fa) || (bool)$u->two_factor_enabled) && !empty($u->phone);

        if (!$mustVerifyOld) {
            return back()->withErrors(['code_old' => 'Старый номер не требует подтверждения.']);
        }
        if (!$u->phone_change_old_code || !$u->phone_change_old_expires_at) {
            return back()->withErrors(['code_old' => 'Нет активного кода для старого номера.']);
        }
        if (now()->greaterThan($u->phone_change_old_expires_at)) {
            return back()->withErrors(['code_old' => 'Срок действия кода истёк. Запросите код заново.']);
        }

        $request->validate([
            'code_old' => ['required','digits:6'],
        ],[
            'code_old.required' => 'Введите код со старого номера',
            'code_old.digits'   => 'Код состоит из 6 цифр',
        ]);

        if (!Hash::check($request->input('code_old'), $u->phone_change_old_code)) {
            return back()->withErrors(['code_old' => 'Неверный код со старого номера.']);
        }

        if (!$u->two_factor_phone_pending) {
            return back()->withErrors(['resend_new' => 'Новый номер не указан. Начните процесс заново.']);
        }

        // генерим код на НОВЫЙ и переводим в stage=new
        $last = session('phone_change:new_last_sent_at');
        if ($last && now()->diffInSeconds($last) < 60) {
            return back()->withErrors(['resend_new' => 'Повторная отправка на новый номер доступна через минуту.']);
        }

        $code = (string)random_int(100000, 999999);
        $u->forceFill([
            'phone_change_old_code'       => null,
            'phone_change_old_expires_at' => null,
            'phone_change_new_code'       => Hash::make($code),
            'phone_change_new_expires_at' => now()->addMinutes(10),
        ])->save();

        $result = $sms->send($u->two_factor_phone_pending, "Подтверждение нового номера: код {$code}. Действителен 10 минут.");

        Log::info('PhoneChangeStrong: verifyOld -> NEW code sent', [
            'user_id' => $u->id,
            'pending' => '***'.substr($u->two_factor_phone_pending, -4),
            'result'  => $result === true ? 'OK' : $result,
        ]);

        session(['phone_change:new_last_sent_at' => now(), 'phone_change:stage' => 'new']);

        if ($result !== true) {
            return back()->withErrors(['resend_new' => is_string($result) ? $result : 'Не удалось отправить SMS на новый номер.']);
        }

        return back()->with('status', 'Старый номер подтверждён. Введите код с нового номера.');
    }

    // Шаг 3: подтверждаем НОВЫЙ → меняем phone и уводим в /cabinet
    public function verifyNew(Request $request)
    {
        $u = $request->user();

        if (!$u->two_factor_phone_pending) {
            return back()->withErrors(['code_new' => 'Нет номера в ожидании подтверждения.']);
        }
        if (!$u->phone_change_new_code || !$u->phone_change_new_expires_at) {
            return back()->withErrors(['code_new' => 'Нет активного кода для нового номера.']);
        }
        if (now()->greaterThan($u->phone_change_new_expires_at)) {
            return back()->withErrors(['code_new' => 'Срок действия кода истёк. Запросите код заново.']);
        }

        $request->validate([
            'code_new' => ['required','digits:6'],
        ],[
            'code_new.required' => 'Введите код с нового номера',
            'code_new.digits'   => 'Код состоит из 6 цифр',
        ]);

        if (!Hash::check($request->input('code_new'), $u->phone_change_new_code)) {
            return back()->withErrors(['code_new' => 'Неверный код с нового номера.']);
        }

        $old = $u->phone;
        $new = $u->two_factor_phone_pending;

        $u->forceFill([
//            'phone'                        => $new,
            'phone'                       => str_starts_with($new, '+') ? $new : ('+'.$new),
            'two_factor_phone_pending'    => null,
            'phone_change_new_code'       => null,
            'phone_change_new_expires_at' => null,
            'two_factor_phone_changed_at' => now(),
            'phone_verified_at'           => now(),
        ])->save();

        Log::debug('PC.start: after-save (model snapshot)', [
            'user_id'       => $u->id,
            'pending_attr'  => $u->getAttributes()['two_factor_phone_pending'] ?? null, // что в "сырых" атрибутах модели
            'pending_prop'  => $u->two_factor_phone_pending,                             // что вернёт аксессор/каст (если есть)
        ]);

        $directDbPending = DB::table('users')->where('id', $u->id)->value('two_factor_phone_pending');
        Log::debug('PC.start: after-save (db readback)', [
            'user_id'      => $u->id,
            'db_pending'   => $directDbPending,
        ]);



        Log::info('PhoneChangeStrong: verifyNew -> CHANGED', [
            'user_id' => $u->id,
            'old'     => '***'.substr((string)$old, -4),
            'new'     => '***'.substr($new, -4),
        ]);

        // Чистим стейт процесса и помечаем «2FA пройдена»
        session()->forget([
            'phone_change:old_last_sent_at',
            'phone_change:new_last_sent_at',
            'phone_change:stage',
        ]);
        session(['2fa:passed' => true]);

        // 👉 уводим в кабинет
        return redirect('/cabinet')->with('status', 'Номер телефона успешно изменён.');
    }

    // RESEND старого (остаёмся на шаге 2)
    public function resendOld(Request $request, SmsRuService $sms)
    {
        $u = $request->user();

        $forceAdmin2fa = Setting::getBool('force_2fa_admins', false);
        $mustVerifyOld = ((((int)$u->role_id === 10) && $forceAdmin2fa) || (bool)$u->two_factor_enabled) && !empty($u->phone);

        if (!$mustVerifyOld) {
            return back()->withErrors(['resend_old' => 'Старый номер не требует подтверждения.']);
        }
        if (!$u->phone) {
            return back()->withErrors(['resend_old' => 'У аккаунта нет текущего номера.']);
        }

        $last = session('phone_change:old_last_sent_at');
        if ($last && now()->diffInSeconds($last) < 60) {
            return back()->withErrors(['resend_old' => 'Повторная отправка доступна через минуту.']);
        }

        $key = 'phone_change:cnt:'.$u->id;
        $cnt = cache()->get($key, 0);
        if ($cnt >= 5) {
            return back()->withErrors(['resend_old' => 'Превышен лимит отправок. Попробуйте позже.']);
        }

        $code = (string)random_int(100000, 999999);
        $u->forceFill([
            'phone_change_old_code'       => Hash::make($code),
            'phone_change_old_expires_at' => now()->addMinutes(10),
        ])->save();

        $result = $sms->send($u->phone, "Подтверждение смены номера: код {$code}. Действителен 10 минут.");

        Log::info('PhoneChangeStrong: resendOld', [
            'user_id' => $u->id,
            'old'     => '***'.substr((string)$u->phone, -4),
            'result'  => $result === true ? 'OK' : $result,
        ]);

        session(['phone_change:old_last_sent_at' => now(), 'phone_change:stage' => 'old']);
        cache()->put($key, $cnt + 1, now()->addHour());

        if ($result !== true) {
            return back()->withErrors(['resend_old' => is_string($result) ? $result : 'Не удалось отправить SMS на старый номер.']);
        }

        return back()->with('status', 'Код отправлен на старый номер.');
    }

    // RESEND нового (остаёмся на шаге 3)
    public function resendNew(Request $request, SmsRuService $sms)
    {
        $u = $request->user();

        if (!$u->two_factor_phone_pending) {
            return back()->withErrors(['resend_new' => 'Нет номера в ожидании подтверждения.']);
        }

        $last = session('phone_change:new_last_sent_at');
        if ($last && now()->diffInSeconds($last) < 60) {
            return back()->withErrors(['resend_new' => 'Повторная отправка доступна через минуту.']);
        }

        $key = 'phone_change:cnt:'.$u->id;
        $cnt = cache()->get($key, 0);
        if ($cnt >= 5) {
            return back()->withErrors(['resend_new' => 'Превышен лимит отправок. Попробуйте позже.']);
        }

        $code = (string)random_int(100000, 999999);
        $u->forceFill([
            'phone_change_new_code'       => Hash::make($code),
            'phone_change_new_expires_at' => now()->addMinutes(10),
        ])->save();

        $result = $sms->send($u->two_factor_phone_pending, "Подтверждение нового номера: код {$code}. Действителен 10 минут.");

        Log::info('PhoneChangeStrong: resendNew', [
            'user_id' => $u->id,
            'pending' => '***'.substr($u->two_factor_phone_pending, -4),
            'result'  => $result === true ? 'OK' : $result,
        ]);

        session(['phone_change:new_last_sent_at' => now(), 'phone_change:stage' => 'new']);
        cache()->put($key, $cnt + 1, now()->addHour());

        if ($result !== true) {
            return back()->withErrors(['resend_new' => is_string($result) ? $result : 'Не удалось отправить SMS на новый номер.']);
        }

        return back()->with('status', 'Код отправлен на новый номер.');
    }

    private function formatRuPhonePretty(?string $phone): string
    {
        if (!$phone) return '—';
        $d = preg_replace('/\D+/', '', $phone);
        if (strlen($d) === 11 && $d[0] === '8') $d = '7'.substr($d,1);
        if (strlen($d) !== 11 || $d[0] !== '7') return $phone;

        $a = substr($d, 1, 3); // код
        $b = substr($d, 4, 3); // XXX
        $c = substr($d, 7, 2); // XX
        $e = substr($d, 9, 2); // XX
        return "+7 ({$a}) {$b} {$c}-{$e}";
    }
}
