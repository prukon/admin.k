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
use Intervention\Image\ImageManager;


use App\Models\MyLog;

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


//    Обновление юзера в учетной записи
    public function update(AccountUpdateRequest $request, User $user)
    {
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
        $originalPhone = $normalize($original['phone'] ?? null); // ✳ для корректного сравнения
        $incomingPhone = $normalize($validated['phone'] ?? null);
        $currentPhone  = $normalize($user->phone);
        $isDeletePhone = array_key_exists('phone', $validated) && $incomingPhone === null;

        // --- 2FA: глобалка force_2fa_admins
        $targetRoleId   = (int)($validated['role_id'] ?? $user->role_id);
        $isAdminRole    = ($targetRoleId === 10);
        $requestedTwoFa = (int)$request->boolean('two_factor_enabled');
        $forceAdmin2fa  = Setting::getBool('force_2fa_admins', false, null);

        // Итоговое состояние 2FA
        $twoFaEnabled = ($isAdminRole && $forceAdmin2fa) ? 1 : $requestedTwoFa;
        $validated['two_factor_enabled'] = $twoFaEnabled;



        // Если 2FA была включена и мы её выключаем (не админ под глобалкой) — чистим служебные поля
        if (!$isAdminRole && $user->two_factor_enabled && $twoFaEnabled === 0) {
            $validated['two_factor_code']       = null;
            $validated['two_factor_expires_at'] = null;
        }

        // --- правила по телефону
        if ($isDeletePhone) {
            if ($twoFaEnabled === 1) {
                \Log::info('User update: phone delete blocked (2FA ON)', ['user_id' => $user->id]);
                return response()->json([
                    'message' => 'Нельзя удалить телефон при включённой 2FA.',
                    'errors'  => ['phone' => ['Нельзя удалить телефон при включённой 2FA.']],
                ], 422);
            }
            // Удаление телефона допустимо
            $validated['phone']                       = null;
            $validated['phone_verified_at']           = null;
            $validated['two_factor_phone_pending']    = null;
            $validated['phone_change_new_code']       = null;
            $validated['phone_change_new_expires_at'] = null;
            $validated['phone_change_old_code']       = null;
            $validated['phone_change_old_expires_at'] = null;

            \Log::info('User update: phone cleared by request', ['user_id' => $user->id]);
        }
        // ✱ ИЗМЕНЕНО: новая логика смены телефона
        elseif ($incomingPhone !== null && $incomingPhone !== $currentPhone) {
            $isVerified = !is_null($user->phone_verified_at); // подтверждён ли текущий номер

            if ($isVerified) {
                // ✱ ИЗМЕНЕНО: запрещаем менять подтверждённый номер
                \Log::info('User update: phone change blocked (verified)', [
                    'user_id' => $user->id,
                    'from'    => $currentPhone,
                    'to'      => $incomingPhone,
                ]);
                return response()->json([
                    'message' => 'Нельзя менять подтверждённый номер. Сбросьте подтверждение или свяжитесь с администратором.',
                    'errors'  => ['phone' => ['Номер подтверждён. Смена запрещена.']],
                ], 422);
            } else {
                // ✱ ИЗМЕНЕНО: номер не подтверждён — разрешаем прямую смену и чистим служебные поля
                $validated['phone']                       = $incomingPhone;
                $validated['phone_verified_at']           = null; // остаётся неподтверждённым
                $validated['two_factor_phone_pending']    = null;
                $validated['phone_change_new_code']       = null;
                $validated['phone_change_new_expires_at'] = null;
                $validated['phone_change_old_code']       = null;
                $validated['phone_change_old_expires_at'] = null;

                \Log::info('User update: phone changed (unverified -> new value saved)', [
                    'user_id' => $user->id,
                    'from'    => $currentPhone,
                    'to'      => $incomingPhone,
                ]);
            }
        }
        // --- конец блока правил по телефону

        // Требуем телефон только если 2FA включена итогово
        if ($twoFaEnabled === 1) {
            $phoneFor2fa = $normalize($validated['phone'] ?? $user->phone); // ✱ ИЗМЕНЕНО: учитывать возможную смену выше
            if (!$phoneFor2fa || !preg_match('/^7\d{10}$/', $phoneFor2fa)) {
                \Log::info('User update: phone required because 2FA ON', [
                    'user_id' => $user->id,
                    'phone'   => $phoneFor2fa,
                ]);
                return response()->json([
                    'message' => 'Укажите корректный номер телефона для SMS (формат 79XXXXXXXXX).',
                    'errors'  => ['phone' => ['Телефон обязателен для включения 2FA и должен быть формата 79XXXXXXXXX']],
                ], 422);
            }
        }

        // --- ДОП. ПАРАМЕТРЫ (как было изначально): custom[slug] = value
        $custom = (array) $request->input('custom', []);
        \Log::info('Полученные кастомные поля', $custom);

        // Роль редактора (кто сохраняет)
        $editorRoleId = (int) auth()->user()->role_id;

        // Значения user_field_values до изменений
        $oldFieldValuesById = \App\Models\UserFieldValue::query()
            ->where('user_id', $user->id)
            ->pluck('value', 'field_id')
            ->toArray();

        // Для подписи в диффе: ЛОКАЛИЗАЦИЯ из user_fields.name
        $fieldNameById = []; // [field_id => name]

        DB::transaction(function () use (
            $user, $authorId, $validated, $original,
            $custom, $editorRoleId, &$fieldNameById, $oldFieldValuesById, $fieldLabel, $normalize, $originalPhone
        ) {
            // Обновление основной модели
            $this->service->update($user, $validated);

            // Дочистка служебных полей при удалении телефона (страховка)
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

            // Сохранение кастомных полей (через slug) + сбор локализованных имён
            foreach ($custom as $slug => $newValue) {
                $field = \App\Models\UserField::query()->where('slug', $slug)->first();
                if (!$field) {
                    \Log::warning("Неизвестное поле custom[{$slug}] — пропускаем");
                    continue;
                }

                // берём именно колонку name (локализованное имя поля)
                $fieldNameById[$field->id] = $field->name;

                $allowedRoleIds = \DB::table('user_field_role')
                    ->where('user_field_id', $field->id)
                    ->pluck('role_id')
                    ->map(fn($v) => (int)$v)
                    ->all();

                \Log::debug("UserField ID {$field->id}, allowed roles: " . json_encode($allowedRoleIds));
                $isEditable = in_array($editorRoleId, $allowedRoleIds, true);
                \Log::info("Обработка поля {$slug}: Редактируемое - " . ($isEditable ? 'Да' : 'Нет') . " ");

                if (!$isEditable) {
                    \Log::warning("Пользователь {$authorId} не может редактировать поле {$slug} ");
                    continue;
                }

                \App\Models\UserFieldValue::query()->updateOrCreate(
                    ['user_id' => $user->id, 'field_id' => $field->id],
                    ['value' => $newValue]
                );
            }

            $user->refresh();

            // --------- DIFF: основные поля (локализованные подписи) ---------
            // ✱ ИЗМЕНЕНО: показываем номер полностью (без маски)
            $showPhone = function (?string $p) {
                if (!$p) return 'null';
                $d = preg_replace('/\D+/', '', $p);
                return $d ?: 'null';
            };
            $labelOnOff = fn($v) => ((int)$v === 1 ? 'включена' : 'выключена');
            $labelYesNo = fn($v) => ((int)$v === 1 ? 'Да' : 'Нет');

            $watched = ['lastname','name','email','is_enabled','team_id','role_id','birthday'];
            $changes = [];

            // ✱ NEW: маппинг team_id -> title для человекочитаемых логов
            $teamTitle = function ($id): ?string {
                static $titles = null;
                if ($titles === null) {
                    // Подтягиваем все названия разом (кешируется в статике)
                    $titles = \App\Models\Team::query()
                        ->pluck('title', 'id')
                        ->map(fn($v) => (string)$v)
                        ->toArray();
                }
                if ($id === null) return null;
                return $titles[$id] ?? ('#'.$id); // если команда уже удалена — покажем #id
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

                // ✱ NEW: для team_id — логируем названия групп (teams.title), а не ID
                if ($field === 'team_id') {
                    $oldTitle = $teamTitle($original['team_id'] ?? null);
                    $newTitle = $teamTitle($user->team_id);
                    if (($original['team_id'] ?? null) != $user->team_id) {
                        $changes[] = $fieldLabel('team_id') . ': ' .
                            ($oldTitle === null ? 'null' : $oldTitle) . ' → ' .
                            ($newTitle === null ? 'null' : $newTitle);
                    }
                    continue; // важно: пропускаем общий пуш ниже
                }


                if ($old != $new) {
                    $changes[] = $fieldLabel($field) . ': ' . ($old === null ? 'null' : $old) . ' → ' . ($new === null ? 'null' : $new);
                }
            }

            // ✱ ИЗМЕНЕНО: явная проверка и логирование телефона — без маски
            $updatedPhone = $normalize($user->phone);
            if ($originalPhone !== $updatedPhone) {
                \Log::info('User update: phone changed', [
                    'user_id' => $user->id,
                    'from'    => $showPhone($originalPhone),
                    'to'      => $showPhone($updatedPhone),
                ]);
                $changes[] = $fieldLabel('phone') . ': ' . $showPhone($originalPhone) . ' → ' . $showPhone($updatedPhone);
            }

            $old2fa = (int)($original['two_factor_enabled'] ?? 0);
            $new2fa = (int)$user->two_factor_enabled;
            if ($old2fa !== $new2fa) {
                $changes[] = $fieldLabel('two_factor_enabled') . ': ' . $labelOnOff($old2fa) . ' → ' . $labelOnOff($new2fa);
            }
            if ($old2fa === 1 && $new2fa === 0) {
                $changes[] = '— очищены служебные поля 2FA (код/срок)';
            }

            // --------- DIFF: кастомные поля (имена из user_fields.name) ---------
            $newFieldValuesById = \App\Models\UserFieldValue::query()
                ->where('user_id', $user->id)
                ->pluck('value', 'field_id')
                ->toArray();

            $fieldDiffLines = [];
            $allIds = array_unique(array_merge(array_keys($oldFieldValuesById), array_keys($newFieldValuesById)));
            foreach ($allIds as $fid) {
                $oldV = $oldFieldValuesById[$fid] ?? null;
                $newV = $newFieldValuesById[$fid] ?? null;
                if ($oldV != $newV) {
                    if (!isset($fieldNameById[$fid])) {
                        $f = \App\Models\UserField::query()->where('id', $fid)->first(['id','name']);
                        $fieldNameById[$fid] = $f?->name ?? ("Поле #{$fid}");
                    }
                    $title = $fieldNameById[$fid];
                    $fieldDiffLines[] = $title . ': ' . ($oldV === null ? 'null' : (string)$oldV) . ' → ' . ($newV === null ? 'null' : (string)$newV);
                }
            }
            if ($fieldDiffLines) {
                $changes[] = "Изменения доп. полей:\n" . implode("\n", $fieldDiffLines);
            }

            $desc = $changes ? implode("\n", $changes) : "Изменения: отсутствуют.";

            // --------- запись в MyLog (безопасно к отсутствию action_name_ru) ---------
            $targetLabel = method_exists($user, 'getFullNameAttribute')
                ? ($user->full_name ?? trim(($user->lastname ?? '').' '.($user->name ?? '')))
                : trim(($user->lastname ?? '').' '.($user->name ?? ''));

            $logData = [
                'type'         => 2,
                'action'       => 23,
                'user_id'   => $user->id,
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
        $authorId = auth()->id(); // Авторизованный пользователь
        $user = User::findOrFail($authorId);

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
        $res = \Gate::inspect('verify-phone', $user);
        if ($res->denied()) {
            \Log::warning('phoneSendCode: denied', ['actor_id' => \Auth::id(), 'target_id' => $user->id]);
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
        $user->phone_change_new_code = \Hash::make($code);
        $user->phone_change_new_expires_at = $expires;
        // Чистим старый шаг на всякий случай
        $user->phone_change_old_code = null;
        $user->phone_change_old_expires_at = null;
        $user->save();

        \Log::debug('phoneSendCode: saved pending', [
            'user_id'   => $user->id,
            'pending'   => $user->two_factor_phone_pending,
            'digits'    => $digits,
            'expires'   => $expires->toDateTimeString(),
        ]);

        // Отправка SMS — шлюзу отдаем цифры (79…)
        try {
            app(\App\Servises\SmsRuService::class)->send($digits, "Код подтверждения: {$code}");
        } catch (\Throwable $e) {
            \Log::error('phoneSendCode: sms send failed', ['err' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Не удалось отправить SMS. Попробуйте позже.'], 500);
        }

        return response()->json(['success' => true]);
    }
    public function phoneConfirmCode(Request $request, User $user)
    {
        $res = \Gate::inspect('verify-phone', $user);
        if ($res->denied()) {
            \Log::warning('phoneConfirmCode: denied', ['actor_id' => \Auth::id(), 'target_id' => $user->id]);
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

        \Log::debug('phoneConfirmCode: compare', [
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
        if (!\Hash::check($code, $user->phone_change_new_code)) {
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

        \Log::info('phoneConfirmCode: success', [
            'target_id' => $user->id,
            'phone'     => $user->phone,
        ]);

        if (\Auth::id() === $user->id) {
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