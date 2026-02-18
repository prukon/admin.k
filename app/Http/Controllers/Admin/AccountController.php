<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\User\AccountUpdateRequest;
use App\Http\Requests\User\UpdateRequest;

//use App\Http\Requests\Partner\UpdateRequest;
//use App\Http\Requests\User\UpdateRequest;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Gate;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use App\Models\Role;

use App\Models\MyLog;
use App\Services\PartnerContext;

class AccountController extends AdminBaseController
{
    protected UserService $service;

    public function __construct(UserService $service, PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext);
        $this->service = $service;
    }


//    Обновление юзера в учетной записи


    public function user()
    {
        $partnerId = $this->requirePartnerId();
        $allTeams = Team::all();

        $user = Auth::user();
        $partners = $user->partner ? collect([$user->partner]) : collect();
        $currentUser = Auth::user();

        // Загружаем поля текущего партнёра вместе с ролями (pivot user_field_role)
        $fields = UserField::where('partner_id', $partnerId)
            ->with('roles')
            ->get();

        $userFieldValues = UserFieldValue::where('user_id', $user->id)
            ->pluck('value', 'field_id');

        /**
         * ✅ FIX: editableFields не должен перезаписываться вторым блоком.
         * Как должно быть:
         * - если allowedRoleIds пустой => редактируемо всем
         * - иначе => только указанным ролям
         */
        $editableFields = $fields->mapWithKeys(function ($field) use ($currentUser) {
            $allowedRoleIds = $field->roles->pluck('id')->toArray();
            $isEditable = empty($allowedRoleIds) || in_array((int)$currentUser->role_id, $allowedRoleIds, true);
            return [$field->id => $isEditable];
        });

        return view('account.index', ['activeTab' => 'user'], compact(
            'user',
            'partners',
            'allTeams',
            'fields',
            'userFieldValues',
            'editableFields',
            'currentUser'
        ));
    }

    // Обновление юзера в учетной записи

    public function update(AccountUpdateRequest $request)
    {
        $user = $request->user();

        // ✅ Изоляция по партнёру (не даём править чужого)
        $currentPartnerId = $this->requirePartnerId();
        if ((int)$user->partner_id !== $currentPartnerId) {
            abort(404);
        }

        $authorId  = Auth::id();

        // Снимки до изменений (для дифф-логов)
        $original   = $user->getOriginal();
        $oldData    = $user->replicate();
        $validated  = $request->validated();

        // --- локализованные подписи полей для человекочитаемых логов
        $fieldLabel = function (string $key): string {
            static $map = [
                'lastname'            => 'Фамилия',
                'name'                => 'Имя',
                'email'               => 'Email',
                'is_enabled'          => 'Аккаунт активен',
                'team_id'             => 'Команда',
                'role_id'             => 'Роль',
                'birthday'            => 'Дата рождения',
                'phone'               => 'Телефон',
                'two_factor_enabled'  => 'Двухфакторная аутентификация (SMS)',
            ];
            return $map[$key] ?? $key;
        };

        // --- нормализация телефона
        $normalize = function (?string $phone): ?string {
            if (!$phone) return null;
            $digits = preg_replace('/\D+/', '', $phone);
            if (strlen($digits) === 11 && str_starts_with($digits, '8')) $digits = '7' . substr($digits, 1);
            if (strlen($digits) === 10) $digits = '7' . $digits;
            return $digits ?: null;
        };

        $originalPhone = $normalize($original['phone'] ?? null);

        // ✅ FIX: различаем "phone не пришёл" и "phone пришёл = null"
        $currentPhone  = $normalize($user->phone);
        $hasPhoneKey   = array_key_exists('phone', $validated);
        $incomingPhone = $hasPhoneKey ? $normalize($validated['phone']) : $currentPhone;

        $isDeletePhone = $hasPhoneKey && $incomingPhone === null;

        /**
         * ✅ role_id -> roles.name (кеш в пределах запроса)
         */
        $roleNameById = static function (?int $roleId): ?string {
            static $cache = [];
            if (!$roleId) return null;

            if (!array_key_exists($roleId, $cache)) {
                $cache[$roleId] = \App\Models\Role::query()
                    ->whereKey($roleId)
                    ->value('name'); // string|null
            }

            return $cache[$roleId];
        };

        // --- 2FA: глобалка force_2fa_admins
        $targetRoleId   = (int)($validated['role_id'] ?? $user->role_id);
        $targetRoleName = $roleNameById($targetRoleId);

        $isAdminRole    = ($targetRoleName === 'admin');
        $requestedTwoFa = (int)$request->boolean('two_factor_enabled');
        $forceAdmin2fa  = Setting::getBool('force_2fa_admins', false, null);

        $forcedForThisUser = ($isAdminRole && $forceAdmin2fa);

        // Итоговое состояние 2FA
        $twoFaEnabled = $forcedForThisUser ? 1 : $requestedTwoFa;
        $validated['two_factor_enabled'] = $twoFaEnabled;

        // Если 2FA была включена и мы её выключаем (не админ под глобалкой) — чистим служебные поля
        if (!$forcedForThisUser && $user->two_factor_enabled && $twoFaEnabled === 0) {
            $validated['two_factor_code']       = null;
            $validated['two_factor_expires_at'] = null;
        }

        // --- правила по телефону
        if ($isDeletePhone) {

            $isPhoneVerified = !is_null($user->phone_verified_at);

            // ✅ Правило:
            // - 2FA включена + телефон подтверждён -> запрещаем удаление
            // - 2FA принудительная -> запрещаем удаление
            if ($twoFaEnabled === 1 && ($forcedForThisUser || $isPhoneVerified)) {
                Log::info('User update: phone delete blocked (2FA ON + verified/forced)', [
                    'user_id'        => $user->id,
                    'forced_two_fa'  => $forcedForThisUser,
                    'phone_verified' => $isPhoneVerified,
                ]);

                return response()->json([
                    'message' => 'Нельзя удалить подтверждённый телефон при включённой 2FA.',
                    'errors'  => ['phone' => ['Нельзя удалить подтверждённый телефон при включённой 2FA.']],
                ], 422);
            }

            // ✅ 2FA включена, но телефон НЕ подтверждён → разрешаем удалить,
            // при этом выключаем 2FA, иначе ниже сработает "телефон обязателен для 2FA".
            if ($twoFaEnabled === 1 && !$isPhoneVerified) {
                $twoFaEnabled = 0;
                $validated['two_factor_enabled']    = 0;
                $validated['two_factor_code']       = null;
                $validated['two_factor_expires_at'] = null;

                Log::info('User update: phone cleared (2FA ON but phone unverified -> 2FA forced OFF)', [
                    'user_id' => $user->id,
                ]);
            }

            $validated['phone']                       = null;
            $validated['phone_verified_at']           = null;
            $validated['two_factor_phone_pending']    = null;
            $validated['phone_change_new_code']       = null;
            $validated['phone_change_new_expires_at'] = null;
            $validated['phone_change_old_code']       = null;
            $validated['phone_change_old_expires_at'] = null;

            Log::info('User update: phone cleared by request', ['user_id' => $user->id]);
        }
        elseif ($incomingPhone !== null && $incomingPhone !== $currentPhone) {
            $isVerified = !is_null($user->phone_verified_at);

            if ($isVerified) {
                Log::info('User update: phone change blocked (verified)', [
                    'user_id' => $user->id,
                    'from'    => $currentPhone,
                    'to'      => $incomingPhone,
                ]);
                return response()->json([
                    'message' => 'Нельзя менять подтверждённый номер. Сбросьте подтверждение или свяжитесь с администратором.',
                    'errors'  => ['phone' => ['Номер подтверждён. Смена запрещена.']],
                ], 422);
            } else {
                $validated['phone']                       = $incomingPhone;
                $validated['phone_verified_at']           = null;
                $validated['two_factor_phone_pending']    = null;
                $validated['phone_change_new_code']       = null;
                $validated['phone_change_new_expires_at'] = null;
                $validated['phone_change_old_code']       = null;
                $validated['phone_change_old_expires_at'] = null;

                Log::info('User update: phone changed (unverified -> new value saved)', [
                    'user_id' => $user->id,
                    'from'    => $currentPhone,
                    'to'      => $incomingPhone,
                ]);
            }
        }

        // Требуем телефон только если 2FA включена итогово
        if ($twoFaEnabled === 1) {
            $phoneFor2fa = $normalize($validated['phone'] ?? $user->phone);
            if (!$phoneFor2fa || !preg_match('/^7\d{10}$/', $phoneFor2fa)) {
                Log::info('User update: phone required because 2FA ON', [
                    'user_id' => $user->id,
                    'phone'   => $phoneFor2fa,
                ]);
                return response()->json([
                    'message' => 'Укажите корректный номер телефона для SMS (формат 79XXXXXXXXX).',
                    'errors'  => ['phone' => ['Телефон обязателен для включения 2FA и должен быть формата 79XXXXXXXXX']],
                ], 422);
            }
        }

        // --- ДОП. ПАРАМЕТРЫ: custom[slug] = value
        $custom = (array) $request->input('custom', []);
        Log::info('Полученные кастомные поля', $custom);

        $editorRoleId = (int) auth()->user()->role_id;

        $oldFieldValuesById = \App\Models\UserFieldValue::query()
            ->where('user_id', $user->id)
            ->pluck('value', 'field_id')
            ->toArray();

        $fieldNameById = [];

        DB::transaction(function () use (
            $user, $authorId, $validated, $original,
            $custom, $editorRoleId, &$fieldNameById, $oldFieldValuesById, $fieldLabel, $normalize, $originalPhone
        ) {
            $this->service->update($user, $validated);

            if (array_key_exists('phone', $validated) && $validated['phone'] === null) {
                $user->forceFill([
                    'phone'                       => null,
                    'phone_verified_at'           => null,
                    'two_factor_phone_pending'    => null,
                    'phone_change_new_code'       => null,
                    'phone_change_new_expires_at' => null,
                    'phone_change_old_code'       => null,
                    'phone_change_old_expires_at' => null,
                ])->save();
            }

            foreach ($custom as $slug => $newValue) {
                $field = \App\Models\UserField::query()->where('slug', $slug)->first();
                if (!$field) {
                    Log::warning("Неизвестное поле custom[{$slug}] — пропускаем");
                    continue;
                }

                $fieldNameById[$field->id] = $field->name;

                $allowedRoleIds = DB::table('user_field_role')
                    ->where('user_field_id', $field->id)
                    ->pluck('role_id')
                    ->map(fn($v) => (int)$v)
                    ->all();

                $isEditable = empty($allowedRoleIds) || in_array($editorRoleId, $allowedRoleIds, true);

                if (!$isEditable) {
                    Log::warning("Пользователь {$authorId} не может редактировать поле {$slug} ");
                    continue;
                }

                \App\Models\UserFieldValue::query()->updateOrCreate(
                    ['user_id' => $user->id, 'field_id' => $field->id],
                    ['value' => $newValue]
                );
            }

            $user->refresh();

            // ... DIFF-логика у тебя дальше без изменений (я не трогал) ...
            // ...
            $showPhone = function (?string $p) {
                if (!$p) return 'null';
                $d = preg_replace('/\D+/', '', $p);
                return $d ?: 'null';
            };
            $labelOnOff = fn($v) => ((int)$v === 1 ? 'включена' : 'выключена');
            $labelYesNo = fn($v) => ((int)$v === 1 ? 'Да' : 'Нет');

            $watched = ['lastname','name','email','is_enabled','team_id','role_id','birthday'];
            $changes = [];

            $teamTitle = function ($id): ?string {
                static $titles = null;
                if ($titles === null) {
                    $titles = \App\Models\Team::query()
                        ->pluck('title', 'id')
                        ->map(fn($v) => (string)$v)
                        ->toArray();
                }
                if ($id === null) return null;
                return $titles[$id] ?? ('#'.$id);
            };

            foreach ($watched as $field) {
                $old = $original[$field] ?? null;
                $new = $user->{$field};

                if ($field === 'birthday') {
                    $old = $old ? \Carbon\Carbon::parse($old)->format('d.m.Y') : null;
                    $new = $new ? \Carbon\Carbon::parse($new)->format('d.m.Y') : null;
                }
                if ($field === 'is_enabled') {
                    $old = $old === null ? null : $labelYesNo((int)$old);
                    $new = $new === null ? null : $labelYesNo((int)$new);
                }

                if ($field === 'team_id') {
                    $oldTitle = $teamTitle($original['team_id'] ?? null);
                    $newTitle = $teamTitle($user->team_id);
                    if (($original['team_id'] ?? null) != $user->team_id) {
                        $changes[] = $fieldLabel('team_id') . ': ' .
                            ($oldTitle === null ? 'null' : $oldTitle) . ' → ' .
                            ($newTitle === null ? 'null' : $newTitle);
                    }
                    continue;
                }

                if ($old != $new) {
                    $changes[] = $fieldLabel($field) . ': ' .
                        ($old === null ? 'null' : $old) . ' → ' .
                        ($new === null ? 'null' : $new);
                }
            }

            $updatedPhone = $normalize($user->phone);
            if ($originalPhone !== $updatedPhone) {
                $changes[] = $fieldLabel('phone') . ': ' .
                    $showPhone($originalPhone) . ' → ' . $showPhone($updatedPhone);
            }

            $old2fa = (int)($original['two_factor_enabled'] ?? 0);
            $new2fa = (int)$user->two_factor_enabled;
            if ($old2fa !== $new2fa) {
                $changes[] = $fieldLabel('two_factor_enabled') . ': ' .
                    $labelOnOff($old2fa) . ' → ' . $labelOnOff($new2fa);
            }
            if ($old2fa === 1 && $new2fa === 0) {
                $changes[] = '— очищены служебные поля 2FA (код/срок)';
            }

            $desc = $changes ? implode("\n", $changes) : "Изменения: отсутствуют.";

            $targetLabel = method_exists($user, 'getFullNameAttribute')
                ? ($user->full_name ?? trim(($user->lastname ?? '').' '.($user->name ?? '')))
                : trim(($user->lastname ?? '').' '.($user->name ?? ''));

            $logData = [
                'type'         => 2,
                'action'       => 23,
                'user_id'      => $user->id,
                'target_type'  => \App\Models\User::class,
                'target_id'    => $user->id,
                'target_label' => $targetLabel,
                'description'  => $desc,
                'created_at'   => now(),
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('my_logs', 'action_name_ru')) {
                $logData['action_name_ru'] = 'Обновление учётной записи пользователя';
            }

            MyLog::create($logData);
        });

        return response()->json(['success' => true, 'message' => 'Пользователь успешно обновлен']);
    }
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:8',
        ]);
        $user = $request->user(); // текущий пользователь
        $authorId = (int) $user->id;

        DB::transaction(function () use ($user, $authorId, $request) {

            $user->password = Hash::make($request->password);
            $user->save();

            $targetLabel = trim(($user->lastname ? ($user->lastname.' ') : '').($user->name ?? ''));

            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 26, // Лог для обновления учетной записи
                'user_id'   => $user->id,
                'target_type'  => \App\Models\User::class,
                'target_id'    => $user->id,
                'target_label' => $targetLabel !== '' ? $targetLabel : ($user->name ?? "user#{$user->id}"),


                'description' => ($user->name . " изменил пароль."),
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => true]);
    }
    public function phoneSendCode(Request $request, User $user)
    {
        // Права: сам себе или админ/супер
        $res = Gate::inspect('verify-phone', $user);
        if ($res->denied()) {
            Log::warning('phoneSendCode: denied', ['actor_id' => Auth::id(), 'target_id' => $user->id]);
            abort(403, $res->message() ?: 'Недостаточно прав.');
        }

        // Нормализация входа -> цифры 79XXXXXXXXX
        $raw = (string)$request->input('phone', '');
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) $digits = '7' . substr($digits, 1);
        if (strlen($digits) === 10) $digits = '7' . $digits;

        if (!preg_match('/^7\d{10}$/', $digits)) {
            return response()->json(['success' => false, 'message' => 'Некорректный номер. Формат 79XXXXXXXXX.'], 422);
        }

        // Если номер не меняется и уже подтверждён — не шлём код
        $currentDigits = preg_replace('/\D+/', '', (string)$user->phone);
        if ($user->phone_verified_at && $currentDigits === $digits) {
            return response()->json(['success' => true, 'alreadyVerified' => true]);
        }

        // Генерим код
        $code = (string)random_int(100000, 999999);
        $expires = now()->addMinutes(10);

        // ✅ Храним pending в E.164 (+7…)
        $user->two_factor_phone_pending = '+' . $digits;
        $user->phone_change_new_code = Hash::make($code);
        $user->phone_change_new_expires_at = $expires;
        // Чистим старый шаг на всякий случай
        $user->phone_change_old_code = null;
        $user->phone_change_old_expires_at = null;
        $user->save();

        Log::debug('phoneSendCode: saved pending', [
            'user_id'   => $user->id,
            'pending'   => $user->two_factor_phone_pending,
            'digits'    => $digits,
            'expires'   => $expires->toDateTimeString(),
        ]);

        // Отправка SMS — шлюзу отдаем цифры (79…)
        try {
            app(\App\Services\SmsRuService::class)->send($digits, "Код подтверждения: {$code}");
        } catch (\Throwable $e) {
            Log::error('phoneSendCode: sms send failed', ['err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Не удалось отправить SMS. Попробуйте позже.'], 500);
        }

        return response()->json(['success' => true]);
    }
    public function phoneConfirmCode(Request $request, User $user)
    {
        $res = Gate::inspect('verify-phone', $user);
        if ($res->denied()) {
            Log::warning('phoneConfirmCode: denied', ['actor_id' => Auth::id(), 'target_id' => $user->id]);
            abort(403, $res->message() ?: 'Недостаточно прав.');
        }

        // Вход -> цифры 79…
        $rawPhone = (string)$request->input('phone', '');
        $digits = preg_replace('/\D+/', '', $rawPhone);
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) $digits = '7' . substr($digits, 1);
        if (strlen($digits) === 10) $digits = '7' . $digits;

        $code = trim((string)$request->input('code', ''));

        if (!preg_match('/^7\d{10}$/', $digits) || !preg_match('/^\d{4,8}$/', $code)) {
            return response()->json(['success' => false, 'message' => 'Неверные данные.'], 422);
        }

        // ✅ Сравниваем pending по цифрам (в БД он теперь хранится как +7…)
        $pendingDigits = preg_replace('/\D+/', '', (string)$user->two_factor_phone_pending);

        Log::debug('phoneConfirmCode: compare', [
            'user_id'       => $user->id,
            'pending_raw'   => $user->two_factor_phone_pending,
            'pendingDigits' => $pendingDigits,
            'inputDigits'   => $digits,
        ]);

        if (!$pendingDigits || $pendingDigits !== $digits) {
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

        // ✅ Применяем номер в E.164 (+7…)
        $user->phone = '+' . $digits;
        $user->phone_verified_at = now();
        $user->two_factor_phone_pending = null;
        $user->phone_change_new_code = null;
        $user->phone_change_new_expires_at = null;
        $user->two_factor_phone_changed_at = now();
        $user->save();

        Log::info('phoneConfirmCode: success', [
            'target_id' => $user->id,
            'phone'     => $user->phone,
        ]);

        if (Auth::id() === $user->id) {
            session(['2fa:passed' => true]);
            session()->forget(['2fa:last_sent_at']);
        }

        return response()->json([
            'success' => true,
            'verified_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }
//    новая загрузка аватарки
    public function store(Request $request)
    {

        // Принимаем ДВЕ картинки: big и crop (квадрат)
        // Важно: принимаем только реальные изображения (без SVG/HTML) и перекодируем перед сохранением.
        $request->validate([
            'image_big' => ['required', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/webp'],  // 5MB
            'image_crop' => ['required', 'file', 'max:4096', 'mimetypes:image/jpeg,image/png,image/webp'], // 4MB
        ]);

        $user = $request->user();

//         Сгенерим рандомные имена
        $bigName = Str::uuid()->toString() . '.jpg';
        $cropName = Str::uuid()->toString() . '.jpg';

        // Папка /public/storage/avatars (disk=public)
        $bigPath = "avatars/{$bigName}";
        $cropPath = "avatars/{$cropName}";


        // Перекодируем и ограничим размеры
        try {
            $manager = ImageManager::gd();
            $bigImage = $manager->read($request->file('image_big')->getRealPath())->scaleDown(1600, 1600);
            $cropImage = $manager->read($request->file('image_crop')->getRealPath())->coverDown(300, 300);
            $bigBytes = (string) $bigImage->toJpeg(85);
            $cropBytes = (string) $cropImage->toJpeg(90);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Не удалось обработать изображение.'], 422);
        }

        DB::transaction(function () use ($user, $bigPath, $cropPath, $bigName, $cropName, $bigBytes, $cropBytes) {

            // Сохраняем только перекодированные байты
            Storage::disk('public')->put($bigPath, $bigBytes);
            Storage::disk('public')->put($cropPath, $cropBytes);

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
            $targetLabel = trim(($user->lastname ? ($user->lastname.' ') : '').($user->name ?? ''));

            // Лог
            MyLog::create([
                'type' => 2,   // обновление юзеров
                'action' => 28,  // обновление учётной записи
                'user_id'   => $user->id,
                'target_type'  => \App\Models\User::class,
                'target_id'    => $user->id,
                'target_label' => $targetLabel !== '' ? $targetLabel : ($user->name ?? "user#{$user->id}"),

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
            $targetLabel = trim(($user->lastname ? ($user->lastname.' ') : '').($user->name ?? ''));

            // Логируем удаление аватарки
            MyLog::create([
                'type' => 2,
                'action' => 29,
                'user_id'   => $user->id,
                'target_type'  => \App\Models\User::class,
                'target_id'    => $user->id,
                'target_label' => $targetLabel !== '' ? $targetLabel : ($user->name ?? "user#{$user->id}"),

                'description' => $user->name . " удалил аватар.",
                'created_at' => now(),
            ]);
        });


        return response()->json(['message' => 'Фото удалено']);
    }

}