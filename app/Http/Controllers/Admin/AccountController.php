<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\AccountUpdateRequest;
use App\Http\Requests\User\UpdateRequest;

//use App\Http\Requests\Partner\UpdateRequest;
//use App\Http\Requests\User\UpdateRequest;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Servises\UserService;
use Carbon\Carbon;
use function Illuminate\Http\Client\dump;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;


//use App\Models\Log;
use App\Models\MyLog;

//use Illuminate\Support\Facades\Log;


class AccountController extends Controller
{
    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function user()
    {
        $partnerId = app('current_partner')->id;
        $allTeams = Team::All();
        $user = Auth::user();
        $partners = $user->partners;
        $currentUser = Auth::user();

        // Изменено: загружаем вместе с отношением roles
        $fields = UserField::where('partner_id', $partnerId)
            ->with('roles')
            ->get();


        $userFieldValues = UserFieldValue::where('user_id', $user->id)->pluck('value', 'field_id');


        // Определяем какие поля можно редактировать
        $editableFields = $fields->mapWithKeys(function ($field) use ($currentUser) {
            // Изменено: получаем допустимые роли из pivot
            $allowedRoleIds = $field->roles->pluck('id')->toArray();
            // Изменено: проверяем по role_id вместо JSON
            $isEditable = empty($allowedRoleIds) || in_array($currentUser->role_id, $allowedRoleIds);
            return [$field->id => $isEditable];
        });

        // контроллер, метод user()
        $editableFields = $fields->mapWithKeys(function ($field) use ($currentUser) {
            $allowedRoleIds = $field->roles->pluck('id')->toArray();
            $isEditable = in_array($currentUser->role_id, $allowedRoleIds);
            return [$field->id => $isEditable];
        });


        return view('account.index', ['activeTab' => 'user'], compact(
            'user',
            'partners',
            'allTeams',
            'fields',
            'userFieldValues',
            'editableFields',
            'currentUser' // Передаем информацию о редактируемых полях
        ));

    }


    public function update(AccountUpdateRequest $request, User $user)
    {
        $partnerId = app('current_partner')->id;
        $authorId = Auth::id();
        $oldData = $user->replicate();
        $validated = $request->validated();

        // --- нормализация телефона
        $normalize = function (?string $phone): ?string {
            if (!$phone) return null;
            $digits = preg_replace('/\D+/', '', $phone);
            if (strlen($digits) === 11 && str_starts_with($digits, '8')) $digits = '7' . substr($digits, 1);
            if (strlen($digits) === 10) $digits = '7' . $digits;
            return $digits ?: null;
        };
        $incomingPhone = $normalize($validated['phone'] ?? null);
        $currentPhone = $normalize($user->phone);
        $isDeletePhone = array_key_exists('phone', $validated) && $incomingPhone === null;

        // --- 2FA: учитываем глобалку force_2fa_admins
        $targetRoleId = (int)($validated['role_id'] ?? $user->role_id);
        $isAdminRole = ($targetRoleId === 10);

        // В AdminUpdateRequest в prepareForValidation мы уже нормализуем two_factor_enabled:
        // если чекбокс не прислан — берём текущее значение пользователя.
        $requestedTwoFa = (int)$request->boolean('two_factor_enabled');

        $forceAdmin2fa = Setting::getBool('force_2fa_admins', false, null);

        // КЛЮЧЕВОЕ:
        // - если глобалка ON и это админ — 2FA обязательно;
        // - иначе берём то, что реально пришло (или текущее, если чекбокс не прислан).
        $twoFaEnabled = ($isAdminRole && $forceAdmin2fa) ? 1 : $requestedTwoFa;
        $validated['two_factor_enabled'] = $twoFaEnabled;

        \Log::info('Account update: 2FA decision', [
            'editor_id' => $authorId,
            'user_id' => $user->id,
            'is_admin_role' => $isAdminRole,
            'forceAdmin2fa' => $forceAdmin2fa,
            'requested_two_fa' => $requestedTwoFa,
            'final_two_fa' => $twoFaEnabled,
            'incoming_phone' => $incomingPhone,
            'current_phone' => $currentPhone,
        ]);

        // Если 2FA была включена и мы её выключаем (и это НЕ админ под глобалкой) — чистим служебные поля
        if (!$isAdminRole && $user->two_factor_enabled && $twoFaEnabled === 0) {
            $validated['two_factor_code'] = null;
            $validated['two_factor_expires_at'] = null;
        }

        // --- правила по телефону
        if ($isDeletePhone) {
            // удалять телефон разрешаем только когда 2FA не включена итогово
            if ($twoFaEnabled === 1) {
                \Log::info('Account update: phone delete blocked (2FA ON)', ['user_id' => $user->id]);
                return response()->json([
                    'message' => 'Нельзя удалить телефон при включённой 2FA.',
                    'errors' => ['phone' => ['Нельзя удалить телефон при включённой 2FA.']],
                ], 422);
            }
            $validated['phone'] = null;
            $validated['phone_verified_at'] = null;
            $validated['two_factor_phone_pending'] = null;
            $validated['phone_change_new_code'] = null;
            $validated['phone_change_new_expires_at'] = null;
            $validated['phone_change_old_code'] = null;
            $validated['phone_change_old_expires_at'] = null;
        } elseif ($incomingPhone !== null && $incomingPhone !== $currentPhone) {
            // запрет обхода SMS-подтверждения (смена номера только через форму подтверждения)
            \Log::info('Account update: direct phone change blocked', [
                'user_id' => $user->id,
                'current' => $currentPhone,
                'incoming' => $incomingPhone,
            ]);
            return response()->json([
                'message' => 'Смените телефон через подтверждение SMS-кодом (кнопка «Подтвердить»).',
                'errors' => ['phone' => ['Подтвердите новый номер через SMS перед сохранением.']],
            ], 422);
        }

        // Требуем телефон ТОЛЬКО если 2FA реально включена итогово
        if ($twoFaEnabled === 1) {
            $phoneFor2fa = $currentPhone; // номер менять здесь нельзя
            if (!$phoneFor2fa || !preg_match('/^7\d{10}$/', $phoneFor2fa)) {
                \Log::info('Account update: phone required because 2FA ON', [
                    'user_id' => $user->id,
                    'phone' => $phoneFor2fa,
                ]);
                return response()->json([
                    'message' => 'Укажите корректный номер телефона для SMS (формат 79XXXXXXXXX).',
                    'errors' => ['phone' => ['Телефон обязателен для включения 2FA и должен быть формата 79XXXXXXXXX']],
                ], 422);
            }
        }

        \Log::info('Начало обновления пользователя', [
            'author_id' => $authorId,
            'user_id' => $user->id,
            'partner_id' => $partnerId,
            'input' => $request->all(),
            'validated' => $validated,
        ]);

        DB::transaction(function () use ($user, $authorId, $partnerId, $validated, $oldData) {
//            \Log::debug('Старые данные пользователя', [
//                'name' => $oldData->name,
//                'birthday' => $oldData->birthday ?->format('Y-m-d'),
//            'email'    => $oldData->email,
//        ]);

        $this->service->update($user, $validated);

        // принудительно дочистим при удалении телефона (на случай, если сервис не тронет)
        if (array_key_exists('phone', $validated) && $validated['phone'] === null) {
            $user->forceFill([
                'phone' => null,
                'phone_verified_at' => null,
                'two_factor_phone_pending' => null,
                'phone_change_new_code' => null,
                'phone_change_new_expires_at' => null,
                'phone_change_old_code' => null,
                'phone_change_old_expires_at' => null,
            ])->save();
        }

        $user->refresh();

//        \Log::debug('Новые данные пользователя', [
//            'name' => $user->name,
//            'birthday' => $user->birthday ?->format('Y-m-d'),
//            'email'    => $user->email,
//        ]);

        // DIFF-лог (телефон/2FA)
        $mask = function (?string $p) {
            if (!$p) return 'null';
            $d = preg_replace('/\D+/', '', $p);
            return '***' . substr($d, -4);
        };
        $label = fn($v)=> (int)$v === 1 ? 'включена' : 'выключена';

        $diff = [];
        if ($oldData->phone !== $user->phone) {
            $diff[] = "Телефон: {$mask($oldData->phone)} → {$mask($user->phone)}";
        }
        $old2fa = (int)$oldData->two_factor_enabled; $new2fa = (int)$user->two_factor_enabled;
        if ($old2fa !== $new2fa) $diff[] = "2FA: {$label($old2fa)} → {$label($new2fa)}";
        if ($old2fa === 1 && $new2fa === 0) $diff[] = "Очищены служебные поля 2FA (код/срок).";

        $desc = $diff ? "Изменения (2FA/телефон):\n— " . implode("\n— ", $diff) : "Изменения (2FA/телефон): отсутствуют.";

        $authorName = Auth::user()->name;
//        MyLog::create([
//            'type' => 2,
//            'action' => 23,
//            'partner_id' => $partnerId,
//            'author_id' => $authorId,
//            'description' => "Автор: {$authorName} (ID {$authorId}).\n"
//                . "Старые: {$oldData->name}, "
//                . ($oldData->birthday ? \Carbon\Carbon::parse($oldData->birthday)->format('d.m.Y') : 'null')
//                . ", {$oldData->email}.\n"
//                . "Новые: {$user->name}, "
//                . ($user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('d.m.Y') : 'null')
//                . ", {$user->email}.\n"
//                . $desc,
//            'created_at' => now(),
//        ]);


        MyLog::create([
            'type' => 2,
            'action' => 23,
            'partner_id' => $partnerId,
            'author_id' => $authorId,
            'description' => "Автор: {$authorName} (ID {$authorId}).\n"
                . "Старые: " . trim(($oldData->lastname ?? '') . ' ' . ($oldData->name ?? '')) . ", "
                . ($oldData->birthday ? \Carbon\Carbon::parse($oldData->birthday)->format('d.m.Y') : 'null')
                . ", {$oldData->email}.\n"
                . "Новые: " . trim(($user->lastname ?? '') . ' ' . ($user->name ?? '')) . ", "
                . ($user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('d.m.Y') : 'null')
                . ", {$user->email}.\n"
                . $desc,
            'created_at' => now(),
        ]);



        \Log::info('MyLog-запись успешно создана');
    });

        \Log::info('Успешная транзакция обновления пользователя', ['user_id' => $user->id]);

        return response()->json(['success' => true, 'message' => 'Пользователь успешно обновлен']);
    }


    public function updatePassword(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $request->validate([
            'password' => 'required|min:8',
        ]);
//        $currentUser = Auth::user();
        $authorId = auth()->id(); // Авторизованный пользователь
//        $user = User::findOrFail($id);
        $user = User::findOrFail($authorId);

        DB::transaction(function () use ($user, $authorId, $request, $partnerId) {

            $user->password = Hash::make($request->password);
            $user->save();

            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 26, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'partner_id' => $partnerId,
                'description' => ($user->name . " изменил пароль."),
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => true]);
    }

    //обновление аватарки (Учетная запись)
//    public function uploadAvatar(Request $request)
//    {
//        // Если у тебя обязательно есть партнёр в middleware — оставляю как есть
//        $partnerId = app('current_partner')->id ?? null;
//
//        // Валидируем, что пришёл data URL картинки
//        $request->validate([
//            'croppedImage' => ['required','string','starts_with:data:image/'],
//        ], [
//            'croppedImage.required' => 'Изображение не передано',
//        ]);
//
//        $user = Auth::user();
//        if (!$user) {
//            return response()->json(['success' => false, 'message' => 'Не авторизован'], 401);
//        }
//
//        // 1) Парсим data URL вида: data:image/png;base64,AAAA...
//        $dataUrl = $request->input('croppedImage');
//
//        if (!preg_match('#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#i', $dataUrl, $m)) {
//            return response()->json(['success' => false, 'message' => 'Неверный формат изображения'], 422);
//        }
//
//        $mime   = strtolower($m[1]);     // png|jpeg|jpg|webp
//        $base64 = $m[2];
//
//        // 2) Определяем расширение ($ext)
//        //    Нормализуем jpeg -> jpg
//        $ext = $mime === 'jpeg' ? 'jpg' : ($mime === 'jpg' ? 'jpg' : ($mime === 'png' ? 'png' : 'webp'));
//
//        // 3) Декодируем в бинарь
//        $binary = base64_decode($base64, true);
//        if ($binary === false) {
//            return response()->json(['success' => false, 'message' => 'Не удалось декодировать изображение'], 422);
//        }
//
//        // (опционально) ограничим размер, например 2 МБ
//        if (strlen($binary) > 2 * 1024 * 1024) {
//            return response()->json(['success' => false, 'message' => 'Слишком большой файл (max 2 MB)'], 422);
//        }
//
//        // 4) Генерим имя файла
//        //    Можно сделать стабильно user_{id}.jpg и всегда перезаписывать.
//        //    Я оставлю вариант с random-хвостом, чтобы избежать жёсткого кэша.
//        $fileName = 'user_' . $user->id . '_' . Str::random(8) . '.' . $ext;
//
//        // 5) Папка назначения
//        $dir = public_path('img/avatars');
//        if (!is_dir($dir)) {
//            mkdir($dir, 0775, true);
//        }
//        $path = $dir . DIRECTORY_SEPARATOR . $fileName;
//
//        // 6) Сохраняем и обновляем пользователя в транзакции
//        DB::transaction(function () use ($path, $binary, $user, $fileName, $partnerId) {
//
//            // Сохраняем новый файл
//            file_put_contents($path, $binary);
//
//            // Удаляем старый, если был
//            if (!empty($user->image_crop)) {
//                @unlink(public_path('img/avatars/' . $user->image_crop));
//            }
//
//            // Обновляем запись пользователя
//            $user->image_crop = $fileName;
//            $user->save();
//
//            // Лог
//            MyLog::create([
//                'type'        => 2,   // обновление юзеров
//                'action'      => 28,  // обновление учётной записи
//                'author_id'   => $user->id,
//                'partner_id'  => $partnerId,
//                'description' => ($user->name . " изменил аватар."),
//                'created_at'  => now(),
//            ]);
//        });
//
//        // 7) Возвращаем публичный URL
//        return response()->json([
//            'success'   => true,
//            'image_url' => asset('img/avatars/' . $fileName),
//        ]);
//    }




    public function phoneSendCode(Request $request, User $user)
    {
        // Права: сам себе или админ/супер
        $res = \Gate::inspect('verify-phone', $user);
        if ($res->denied()) {
            \Log::warning('phoneSendCode: denied', ['actor_id' => \Auth::id(), 'target_id' => $user->id]);
            abort(403, $res->message() ?: 'Недостаточно прав.');
        }

        $raw = (string)$request->input('phone', '');
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) === 10 && $digits[0] !== '7') {
            $digits = '7' . $digits;
        }
        if (!preg_match('/^7\d{10}$/', $digits)) {
            return response()->json(['success' => false, 'message' => 'Некорректный номер. Формат 79XXXXXXXXX.'], 422);
        }

        // Если номер не меняется и уже подтверждён — бессмысленно слать код
        if ($user->phone_verified_at && $user->phone === $digits) {
            return response()->json(['success' => true, 'alreadyVerified' => true]);
        }

        // Генерим код, сохраняем "ожидаемый" номер и дедлайны
        $code = (string)random_int(100000, 999999);
        $expires = now()->addMinutes(10);

        $user->two_factor_phone_pending = $digits;
        $user->phone_change_new_code = Hash::make($code);
        $user->phone_change_new_expires_at = $expires;
        // на всякий пожарный очистим старые значения другого шага
        $user->phone_change_old_code = null;
        $user->phone_change_old_expires_at = null;
        $user->save();

        \Log::info('phoneSendCode: code generated', [
            'target_id' => $user->id,
            'to' => '***' . substr($digits, -4),
            'expires' => $expires->toDateTimeString(),
        ]);

        // Отправка SMS (замени на свой сервис)
        try {
            if (class_exists(\App\Servises\SmsRuService::class)) {
                app(\App\Servises\SmsRuService::class)->send($digits, "Код подтверждения: {$code}");
            } else {
                // заглушка
                \Log::warning('SmsRuService not found, code (debug): ' . $code);
            }
        } catch (\Throwable $e) {
            \Log::error('phoneSendCode: sms send failed', ['err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Не удалось отправить SMS. Попробуйте позже.'], 500);
        }

        return response()->json(['success' => true]);
    }

    public function phoneConfirmCode2(Request $request, User $user)
    {
        $res = \Gate::inspect('verify-phone', $user);
        if ($res->denied()) {
            \Log::warning('phoneConfirmCode: denied', ['actor_id' => \Auth::id(), 'target_id' => $user->id]);
            abort(403, $res->message() ?: 'Недостаточно прав.');
        }

        $rawPhone = (string)$request->input('phone', '');
        $digits = preg_replace('/\D+/', '', $rawPhone);
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) === 10 && $digits[0] !== '7') {
            $digits = '7' . $digits;
        }
        $code = trim((string)$request->input('code', ''));

        if (!preg_match('/^7\d{10}$/', $digits) || !preg_match('/^\d{4,8}$/', $code)) {
            return response()->json(['success' => false, 'message' => 'Неверные данные.'], 422);
        }

        // Проверяем, что ожидаем этот номер
        if (!$user->two_factor_phone_pending || $user->two_factor_phone_pending !== $digits) {
            return response()->json(['success' => false, 'message' => 'Этот номер не ожидается к подтверждению.'], 422);
        }

        if (!$user->phone_change_new_code || !$user->phone_change_new_expires_at) {
            return response()->json(['success' => false, 'message' => 'Код не запрошен.'], 422);
        }
        if (now()->greaterThan($user->phone_change_new_expires_at)) {
            return response()->json(['success' => false, 'message' => 'Код истёк. Запросите новый.'], 422);
        }

        if (!Hash::check($code, $user->phone_change_new_code)) {
            return response()->json(['success' => false, 'message' => 'Неверный код.'], 422);
        }

        // Всё ок — применяем номер
        $user->phone = $digits;
        $user->phone_verified_at = now();
        $user->two_factor_phone_pending = null;
        $user->phone_change_new_code = null;
        $user->phone_change_new_expires_at = null;
        $user->two_factor_phone_changed_at = now();
        $user->save();

        \Log::info('phoneConfirmCode: success', [
            'target_id' => $user->id,
            'phone' => '***' . substr($digits, -4),
        ]);

        return response()->json(['success' => true, 'verified_at' => now()->format('Y-m-d H:i:s')]);
    }

    public function phoneConfirmCode(Request $request, User $user)
    {
        $res = \Gate::inspect('verify-phone', $user);
        if ($res->denied()) {
            \Log::warning('phoneConfirmCode: denied', ['actor_id' => \Auth::id(), 'target_id' => $user->id]);
            abort(403, $res->message() ?: 'Недостаточно прав.');
        }

        $rawPhone = (string)$request->input('phone', '');
        $digits = preg_replace('/\D+/', '', $rawPhone);
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) === 10 && $digits[0] !== '7') {
            $digits = '7' . $digits;
        }
        $code = trim((string)$request->input('code', ''));

        if (!preg_match('/^7\d{10}$/', $digits) || !preg_match('/^\d{4,8}$/', $code)) {
            return response()->json(['success' => false, 'message' => 'Неверные данные.'], 422);
        }

        if (!$user->two_factor_phone_pending || $user->two_factor_phone_pending !== $digits) {
            return response()->json(['success' => false, 'message' => 'Этот номер не ожидается к подтверждению.'], 422);
        }

        if (!$user->phone_change_new_code || !$user->phone_change_new_expires_at) {
            return response()->json(['success' => false, 'message' => 'Код не запрошен.'], 422);
        }
        if (now()->greaterThan($user->phone_change_new_expires_at)) {
            return response()->json(['success' => false, 'message' => 'Код истёк. Запросите новый.'], 422);
        }

        if (!\Hash::check($code, $user->phone_change_new_code)) {
            return response()->json(['success' => false, 'message' => 'Неверный код.'], 422);
        }

        // Применяем номер
        $user->phone = $digits;
        $user->phone_verified_at = now();
        $user->two_factor_phone_pending = null;
        $user->phone_change_new_code = null;
        $user->phone_change_new_expires_at = null;
        $user->two_factor_phone_changed_at = now();
        $user->save();

        \Log::info('phoneConfirmCode: success', [
            'target_id' => $user->id,
            'phone' => '***' . substr($digits, -4),
        ]);

        // ✅ Считаем, что текущая сессия прошла 2FA (мы же только что подтвердили телефон по SMS)
        if (\Auth::id() === $user->id) {
            session(['2fa:passed' => true]);
            session()->forget(['2fa:last_sent_at']);
            \Log::info('phoneConfirmCode: marked session 2fa:passed', ['user_id' => $user->id]);
        }

        return response()->json([
            'success' => true,
            'verified_at' => now()->format('Y-m-d H:i:s'),
            // можно отдать redirect, если хочешь сразу увезти в кабинет:
            // 'redirect' => route('cabinet'),
        ]);
    }


//    новая загрузка аватарки
    public function store(Request $request)
    {
        $partnerId = app('current_partner')->id;

        // Принимаем ДВЕ картинки: big и crop (квадрат), обе — Blob (file)
        $request->validate([
            'image_big' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:5120',   // 5 МБ
            'image_crop' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:4096',   // 4 МБ
        ]);

        $user = $request->user();

//         Сгенерим рандомные имена
        $bigName = Str::uuid()->toString() . '.jpg';
        $cropName = Str::uuid()->toString() . '.jpg';

        // Папка /public/storage/avatars (disk=public)
        $bigPath = "avatars/{$bigName}";
        $cropPath = "avatars/{$cropName}";


        DB::transaction(function () use ($user, $partnerId, $request, $bigPath, $cropPath, $bigName, $cropName) {

            // Сохраняем как есть (клиент уже прислал кроп и сжатие)
            Storage::disk('public')->put($bigPath, file_get_contents($request->file('image_big')->getRealPath()));
            Storage::disk('public')->put($cropPath, file_get_contents($request->file('image_crop')->getRealPath()));

            // Удалим старые, если были
            if ($user->image && Storage::disk('public')->exists("avatars/{$user->image}")) {
                Storage::disk('public')->delete("avatars/{$user->image}");
            }
            if ($user->image_crop && Storage::disk('public')->exists("avatars/{$user->image_crop}")) {
                Storage::disk('public')->delete("avatars/{$user->image_crop}");
            }

            // Сохраняем новые имена в БД
            $user->image = $bigName;
            $user->image_crop = $cropName;
            $user->save();

            // Лог
            MyLog::create([
                'type' => 2,   // обновление юзеров
                'action' => 28,  // обновление учётной записи
                'author_id' => $user->id,
                'partner_id' => $partnerId,
                'description' => ($user->name . " изменил аватар."),
                'created_at' => now(),
            ]);
        });


        return response()->json([
            'message' => 'Аватар обновлён',
            'image_url' => asset('storage/' . $bigPath),
            'image_crop_url' => asset('storage/' . $cropPath),
            'image' => $bigName,
            'image_crop' => $cropName,
        ]);
    }

//    новое удаление аватарки
    public function destroy(Request $request)
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {

            $partnerId = app('current_partner')->id;

            // Удаляем файлы
            if ($user->image && Storage::disk('public')->exists("avatars/{$user->image}")) {
                Storage::disk('public')->delete("avatars/{$user->image}");
            }
            if ($user->image_crop && Storage::disk('public')->exists("avatars/{$user->image_crop}")) {
                Storage::disk('public')->delete("avatars/{$user->image_crop}");
            }

            $user->image = null;
            $user->image_crop = null;
            $user->save();

            // Логируем удаление аватарки
            MyLog::create([
                'type' => 2,
                'action' => 29,
                'author_id' => auth()->id(),
                'partner_id' => $partnerId,
                'description' => $user->name . " удалил аватар.",
                'created_at' => now(),
            ]);
        });


        return response()->json(['message' => 'Фото удалено']);
    }


}