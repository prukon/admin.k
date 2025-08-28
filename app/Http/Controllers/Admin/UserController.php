<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\UserFilter;
use App\Http\Requests\User\FilterRequest;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\UserField;
use Illuminate\Support\Facades\Auth; // Модель для работы с таблицей тегов
use Illuminate\Support\Facades\DB;
use App\Models\MyLog;
use App\Http\Requests\User\UpdateRequest;
//use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;


use App\Servises\UserService;


class UserController extends Controller
{
    public $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function index(FilterRequest $request)
    {
        // 1) Контекст
        $partnerId = app('current_partner')->id;
        $user = Auth::user();
        $currentUser = Auth::user();
        $userRoleName = $currentUser->role ?->name;
    $isSuperadmin = $userRoleName === 'superadmin';

    // 2) Валидация фильтров
    $data = $request->validated();







    $rolesQuery = Role::query();
// если не супер-админ — сразу фильтруем по видимости
if (!$isSuperadmin) {
    $rolesQuery->where('is_visible', 1);
}
// группируем логику системных ролей / ролей партнёра
$rolesQuery->where(function ($q) use ($partnerId) {
    $q->where('is_sistem', 1)
        ->orWhereHas('partners', function ($q2) use ($partnerId) {
            $q2->where('partner_role.partner_id', $partnerId);
        });
});
$roles = $rolesQuery
    ->orderBy('order_by')
    ->get();






    // 4) Произвольные поля партнёра
    $fields = UserField::where('partner_id', $partnerId)->get();

    // 5) Фабрика фильтра
    $filter = app()->make(UserFilter::class, [
        'queryParams' => array_filter($data),
    ]);

    // 6) Выборка пользователей текущего партнёра с фильтрацией и пагинацией
    $allUsers = User::where('partner_id', $partnerId)
        ->when(isset($data['id']), fn($q) => $q->where('id', $data['id']))
        ->filter($filter)
        ->orderBy('name', 'asc')
        ->paginate(20);

    // 7) Все команды партнёра
    $allTeams = Team::where('partner_id', $partnerId)->get();


//    dd($roles);

    // 8) Отдаём на view
    return view('admin.user', compact(
        'allUsers',
        'allTeams',
        'fields',
        'currentUser',
        'roles',
        'user'
    ));
}

    public function store(StoreRequest $request)
    {
        // 1) Валидируем и нормализуем входные данные
        $validatedData = $request->validated();

        $partnerId = app('current_partner')->id;
        $isEnabled = $request->boolean('is_enabled');               // чекбокс может не прийти — приводим к bool
        $teamId = $validatedData['team_id'] ?? null;             // поле опционально — может отсутствовать
        $roleId = $validatedData['role_id'];                     // обязателен по правилам

        // Собираем итоговый массив данных для сервиса
        $data = array_merge($validatedData, [
            'partner_id' => $partnerId,
            'is_enabled' => $isEnabled,
            'team_id' => $teamId, // может быть null
        ]);

        // 2) Создание пользователя + логирование в транзакции
        $user = null;

        DB::transaction(function () use (&$user, $data, $partnerId, $teamId) {
            // Создаём пользователя через доменный сервис
            $user = $this->service->store($data);

            $authorId = auth()->id();

            // Группа (может отсутствовать)
            $teamTitle = '-';
            if ($teamId) {
                $team = Team::find($teamId);
                $teamTitle = $team ?->title ?? '-';
            }

            // Роль (обязательна, но подстрахуемся)
            $role = Role::find($data['role_id']);
            $roleNameOrLabel = $role->label ?? $role->name ?? '-';

            // Форматирование дат для лога
            $formatDateForLog = function (?string $value): string {
                return $value ? Carbon::parse($value)->format('d.m.Y') : '-';
            };

            // Логирование (пишем данные из итоговых сущностей/нормализованных значений)
            MyLog::create([
                'type' => 2,   // юзер-лог
                'action' => 21,  // создание учётки
                'author_id' => $authorId,
                'description' => sprintf(
                    "Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s, Роль: %s",
                    $user->name,
                    $formatDateForLog($data['birthday'] ?? null),
                    $formatDateForLog($data['start_date'] ?? null),
                    $teamTitle,
                    $user->email,
                    ($data['is_enabled'] ?? false) ? 'Да' : 'Нет',
                    $roleNameOrLabel
                ),
                'partner_id' => $partnerId,
            ]);
        });

        // 3) Ответ для AJAX (без лишних повторных запросов и с безопасными доступами)
        if ($request->ajax()) {
            // Попробуем взять из связи, если есть; если нет — из team_id; иначе дефолт.
            $teamTitleForResponse = $user->team ?->title
            ?? ($teamId ? Team::find($teamId) ?->title : '-')
                ?? '-';

            $birthdayFormatted = $user->birthday ? Carbon::parse($user->birthday)->format('d.m.Y') : '-';
            $startDateFormatted = $user->start_date ? Carbon::parse($user->start_date)->format('d.m.Y') : '-';

            return response()->json([
                'message' => 'Пользователь создан успешно',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'birthday' => $birthdayFormatted,
                    'start_date' => $startDateFormatted,
                    'team' => $teamTitleForResponse,
                    'email' => $user->email,
                    'is_enabled' => $user->is_enabled ? 'Да' : 'Нет',
                ],
            ], 200);
        }

        // Если это не AJAX — дальше по твоей логике (редирект/вьюха и т.д.)
        // return redirect()->route(...)->with(...);
    }

    public function edit(User $user)
    {
        // 1) Контекст
        $partnerId = app('current_partner')->id;
        $currentUser = auth()->user();
        $userRoleName = $currentUser->role ?->name;
    $isSuperadmin = $userRoleName === 'superadmin';

    // 2) Загружаем UserField вместе их ролями
    $fieldsQuery = UserField::with('roles')
        ->where('partner_id', $partnerId);
    // Изменение: если не супер-админ, то подгружаем только те поля,
    // права на которые есть у роли текущего пользователя
    if (!$isSuperadmin) {
        $fieldsQuery->whereHas('roles', fn($q) =>
            $q->where('role_id', $currentUser->role_id)
        );
    }
    $fields = $fieldsQuery->get();

    // 3) Собираем payload для полей
    $fieldsPayload = $fields->map(function (UserField $f) use ($currentUser, $isSuperadmin) {
        $allowedRoles = $f->roles->pluck('id')->map(fn($i) => (int)$i);
        return [
            'id' => $f->id,
            'name' => $f->name,
            'slug' => $f->slug,
            'field_type' => $f->field_type,
            'roles' => $allowedRoles->all(),
            // Изменение: добавляем флаг 'editable', который фронтэнд сможет использовать
            // для включения/выключения возможности редактировать конкретное поле
            'editable' => $isSuperadmin || $allowedRoles->contains($currentUser->role_id),
        ];
    })->all();

    // 4) Системные + партнёрские роли (без изменений)
    $systemRoles = Role::where('is_sistem', 1)
        ->when(!$isSuperadmin, fn($q) => $q->where('is_visible', 1))
        ->get();
    $partnerRoles = Role::whereHas('partners', fn($q) =>
            $q->where('partner_role.partner_id', $partnerId)
        )
        ->when(!$isSuperadmin, fn($q) => $q->where('is_visible', 1))
        ->get();
    $allRoles = $systemRoles
        ->merge($partnerRoles)
        ->unique('id')
        ->sortBy('order_by')
        ->values();
    $rolesPayload = $allRoles->map(fn(Role $r) => [
        'id' => $r->id,
        'name' => $r->name,
        'label' => $r->label,
        'system' => (bool)$r->is_sistem,
    ])->all();

    // 5) Загружаем связи user->fields (pivot value) (без изменений)
    $user->load('fields');

    if (request()->ajax()) {
        // 1) Преобразуем модель в массив
        $userArray = $user->toArray();
        // 2) Переопределяем только birthday
        $userArray['birthday'] = $user->birthday
            ? $user->birthday->format('Y-m-d')
            : null;

        return response()->json([
            'user' => $userArray,
            'currentUser' => [
                'role_id' => $currentUser->role_id,
                'isSuperadmin' => $isSuperadmin,
            ],
            'fields' => $fieldsPayload,
            'roles' => $rolesPayload,
        ]);
    }
}

    public function update(UpdateRequest $request, User $user)
    {
        $partnerId = app('current_partner')->id;
        $authorId = auth()->id();

        // Снимок "старых" значений до обновления
        $oldName = $user->name;
        $oldEmail = $user->email;
        $oldIsEnabled = (bool)$user->is_enabled;
        $oldBirthday = $user->birthday; // может быть Carbon или строка
        $oldTeamName = $user->team ?->title ?: '-';
    $oldRoleName = $user->role ?->label ?: '-';

    // Валидные входные данные (могут НЕ содержать часть ключей — это нормально)
    $validatedData = $request->validated();



    $originalPhone = $user->phone;
$newPhone = $validated['phone'] ?? null;

if ($request->user()->can('users-phone-update') && $newPhone !== $originalPhone) {
    $user->phone = $newPhone;
    $user->phone_verified_at = null; // сбросить верификацию, т.к. номер поменяли
}



    // Текущее состояние кастом-полей пользователя (для сравнения в логе)
    $existingCustomValues = UserFieldValue::where('user_id', $user->id)
        ->get()
        ->keyBy('field_id')
        ->map(function (UserFieldValue $userFieldValue) {
            return $userFieldValue->value;
        })
        ->all();

    DB::transaction(function () use (
        $user,
        $validatedData,
        $oldName,
        $oldEmail,
        $oldIsEnabled,
        $oldBirthday,
        $oldTeamName,
        $oldRoleName,
        $existingCustomValues,
        $authorId,
        $partnerId
    ) {
        // 1) Обновляем пользователя доменным сервисом
        $this->service->update($user, $validatedData);
        $user->refresh(); // получаем актуальные значения и связи

        // 2) Готовим данные для лога
        $newTeamName = $user->team ?->title ?: '-';
        $newRoleName = $user->role ?->label ?: '-';

        $formatDateForLog = function ($value): string {
            if (empty($value)) {
                return '-';
            }
            if ($value instanceof \Carbon\CarbonInterface) {
                return $value->format('d.m.Y');
            }
            try {
                return \Carbon\Carbon::parse($value)->format('d.m.Y');
            } catch (\Throwable $e) {
                return '-';
            }
        };

        // 3) Составляем блок изменений по кастом-полям (по пришедшим данным)
        $customFieldsChangesLog = '';
        if (!empty($validatedData['custom']) && is_array($validatedData['custom'])) {
            $incomingSlugs = array_keys($validatedData['custom']);
            $userFieldsBySlug = UserField::whereIn('slug', $incomingSlugs)->get()->keyBy('slug');

            foreach ($validatedData['custom'] as $slug => $newValue) {
                $field = $userFieldsBySlug[$slug] ?? null;
                if (!$field) {
                    \Log::warning("User update: UserField not found by slug '{$slug}'");
                    continue;
                }
                $oldValue = $existingCustomValues[$field->id] ?? '-';
                if ((string)$oldValue !== (string)$newValue) {
                    $customFieldsChangesLog .= "\n{$field->name}: {$oldValue} -> {$newValue}";
                }
            }
        }

        // 4) Пишем лог: "старые" — из снятого снапшота, "новые" — из актуальной модели
        MyLog::create([
            'type' => 2,
            'action' => 22, // изменение учётной записи
            'author_id' => $authorId,
            'partner_id' => $partnerId,
            'description' => sprintf(
                "Старые:\nИмя: %s, Д.р: %s, Группа: %s, Email: %s, Активен: %s, Роль: %s.\n" .
                "Новые:\nИмя: %s, Д.р: %s, Группа: %s, Email: %s, Активен: %s, Роль: %s%s",
                $oldName,
                $formatDateForLog($oldBirthday),
                $oldTeamName,
                $oldEmail,
                $oldIsEnabled ? 'Да' : 'Нет',
                $oldRoleName,

                $user->name,
                $formatDateForLog($user->birthday),
                $newTeamName,
                $user->email,
                $user->is_enabled ? 'Да' : 'Нет',
                $newRoleName,
                $customFieldsChangesLog
            ),
        ]);

        // 5) Сохраняем кастом-поля (только если пришли)
        if (!empty($validatedData['custom']) && is_array($validatedData['custom'])) {
            $incomingSlugs = array_keys($validatedData['custom']);
            $userFieldsBySlug = UserField::whereIn('slug', $incomingSlugs)->get()->keyBy('slug');

            foreach ($validatedData['custom'] as $slug => $newValue) {
                $field = $userFieldsBySlug[$slug] ?? null;
                if (!$field) {
                    \Log::warning("User update: UserField not found by slug '{$slug}' (save stage)");
                    continue;
                }
                UserFieldValue::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'field_id' => $field->id,
                    ],
                    [
                        'value' => $newValue,
                    ]
                );

                \Log::info(
                    "User update: Saved custom field — user_id={$user->id}, " .
                    "field_id={$field->id}, value=" . json_encode($newValue)
                );
            }
        }
    });

    // 6) Безопасный ответ
//    return response()->json([
//        'message' => 'Пользователь успешно обновлен',
//        'user' => [
//            'id' => $user->id,
//            'name' => $user->name,
//            'birthday' => $user->birthday instanceof \Carbon\CarbonInterface
//                ? $user->birthday->format('d.m.Y')
//                : ($user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('d.m.Y') : '-'),
//            'team' => $user->team ?->title ?: '-',
//            'email'      => $user->email,
//            'is_enabled' => $user->is_enabled ? 'Да' : 'Нет',
//            'role'       => $user->role ?->label ?: '-',
//        ],
//    ], 200);

                return response()->json([
                    'message' => 'Пользователь успешно обновлен'
//                    'data' => $data,
                ], 200);
}

    public function delete(User $user)
    {

        // Проверяем, если пользователь не существует
        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }
        $partnerId = app('current_partner')->id;

        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($user, $authorId, $partnerId) {
            // Удаление пользователя
            $user->delete();

            // Логирование удаления
            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 24,
                'author_id' => $authorId,
                'description' => "Удален пользователь: {$user->name}  ID: {$user->id}.",
                'created_at' => now(),
                'partner_id' => $partnerId

            ]);
        });
        return response()->json(['success' => 'Пользователь успешно удалён']);

//        return redirect()->route('admin.user.index');

    }

    public function storeFields(Request $request)
    {
        $data = $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'nullable|integer|exists:user_fields,id',
            'fields.*.name' => 'required|string|max:255',
            'fields.*.field_type' => 'required|in:string,text,select',
            'fields.*.roles' => 'nullable|array',
            'fields.*.roles.*' => 'integer|exists:roles,id',
        ]);
        $partnerId = app('current_partner')->id;
        $authorId = auth()->id();

        DB::transaction(function () use ($data, $partnerId, $authorId) {
            $submittedIds = collect($data['fields'])
                ->pluck('id')
                ->filter()
                ->all();

            // Удаляем поля, которых нет в запросе
            $toDelete = UserField::where('partner_id', $partnerId)
                ->pluck('id')
                ->diff($submittedIds)
                ->all();

            if ($toDelete) {
                UserField::whereIn('id', $toDelete)->delete();
                foreach ($toDelete as $deletedId) {
                    MyLog::create([
                        'type' => 2,
                        'action' => 210,
                        'author_id' => $authorId,
                        'description' => "Удалено поле ID: {$deletedId}",
                        'partner_id' => $partnerId,
                        'created_at' => now(),
                    ]);
                }
            }

            // Обрабатываем новые и существующие
            foreach ($data['fields'] as $item) {
                $fieldId = $item['id'] ?? null;
                $name = $item['name'];
                $type = $item['field_type'];
                $roles = $item['roles'] ?? [];

                // Генерируем slug
                $slug = Str::slug($name . $partnerId);

                if ($fieldId) {
                    // Обновление
                    $field = UserField::where('partner_id', $partnerId)
                        ->findOrFail($fieldId);

                    $changes = [];

                    if ($field->name !== $name) {
                        $changes[] = "name: '{$field->name}' → '{$name}'";
                    }
                    if ($field->field_type !== $type) {
                        $changes[] = "type: '{$field->field_type}' → '{$type}'";
                    }

                    // Обновляем основные поля
                    if ($changes) {
                        $field->update([
                            'name' => $name,
                            'slug' => $slug,
                            'field_type' => $type,
                        ]);
                    }

                    // Синхронизируем роли через pivot
                    $field->roles()->sync($roles);

                    // Логируем, если были изменения
                    if ($changes || true) {
                        MyLog::create([
                            'type' => 2,
                            'action' => 210,
                            'author_id' => $authorId,
                            'description' => "Обновлено поле '{$name}' (ID: {$fieldId}), изменения: "
                                . implode('; ', $changes)
                                . ", роли: [" . implode(',', $roles) . "]",
                            'partner_id' => $partnerId,
                            'created_at' => now(),
                        ]);
                    }
                } else {
                    // Создание нового поля
                    $field = UserField::create([
                        'name' => $name,
                        'slug' => $slug,
                        'field_type' => $type,
                        'partner_id' => $partnerId,
                    ]);

                    // Синхронизируем роли через pivot
                    $field->roles()->sync($roles);

                    MyLog::create([
                        'type' => 2,
                        'action' => 210,
                        'author_id' => $authorId,
                        'description' => "Создано поле '{$name}' (ID: {$field->id}), роли: ["
                            . implode(',', $roles) . "]",
                        'partner_id' => $partnerId,
                        'created_at' => now(),
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Поля успешно сохранены']);
    }

    public function updatePassword(UpdatePasswordRequest $request, \App\Models\User $user)
    {
        $partnerId = app('current_partner')->id ?? null;
        $actor = $request->user();

//        \Log::info('[users.password.update] partner check', [
//            'actor_id'         => $actor->id,
//            'actor_role'       => $actor->role->slug ?? $actor->role->name ?? null,
//            'actor_partner_id' => $actor->partner_id,
//            'current_partner'  => $partnerId,
//            'target_user_id'   => $user->id,
//            'target_partner'   => $user->partner_id,
//            'is_superadmin'    => $this->isSuperAdmin($actor),
//        ]);

        if (!$this->isSuperAdmin($actor) && $partnerId !== null) {
            abort_if((int)$user->partner_id !== (int)$partnerId, 403, 'Доступ запрещён.');
        }

        $newPassword = $request->validated()['password'];

        $stored = $user->getAuthPassword() ?? $user->password;
        if (is_string($stored) && $stored !== '' && password_verify($newPassword, $stored)) {
            return response()->json(['message' => 'Новый пароль совпадает с текущим.'], 422);
        }

        \DB::transaction(function () use ($user, $newPassword, $request, $partnerId) {
            $user->password = \Hash::make($newPassword);
            $user->save();

            \App\Models\MyLog::create([
                'type' => 2,
                'action' => 26,
                'author_id' => $request->user()->id,
                'description' => sprintf('Пароль пользователя "%s" изменён администратором "%s".',
                    $user->name, $request->user()->name),
                'partner_id' => $partnerId,
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function log(FilterRequest $request)
    {
        $partnerId = app('current_partner')->id;
        $logs = MyLog::with('author')
            ->where('type', 2)// User логи
            ->where('partner_id', $partnerId)
            ->select('my_logs.*');
        return DataTables::of($logs)
            ->addColumn('author', function ($log) {
                return $log->author ? $log->author->name : 'Неизвестно';
            })
            ->editColumn('created_at', function ($log) {
                return $log->created_at->format('d.m.Y / H:i:s');
            })
            ->editColumn('action', function ($log) {
                // Логика для преобразования типа
                $typeLabels = [
                    21 => 'Создание пользователя',
                    22 => 'Обновление учетной записи в пользователях',
                    23 => 'Обновление учетной записи (админ)',
                    24 => 'Удаление пользователя в пользователях',
                    25 => 'Изменение пароля (админ)',
                    26 => 'Изменение пароля',
                    27 => 'Изменение аватара (админ)',
                    28 => 'Изменение аватара',
                    29 => 'Изменение данных партнера',
                    210 => 'Изменение доп полей пользователя',


                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип(user)';
            })
            ->make(true);
    }

    protected function isSuperAdmin(\App\Models\User $actor): bool
    {
        // Если используете Spatie\Permission:
        // return $actor->hasRole('superadmin');

        // Своя ролевая модель (role_id/slug) — пример:
        return ($actor->role->name ?? null) === 'superadmin'; // подставьте ваш slug/проверку
    }

    //Удаление аватарки юзера
    public function destroyUserAvatar($id)
    {
        $user = User::findOrFail($id);
        $partnerId = app('current_partner')->id;
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($user, $authorId, $partnerId) {

            // Удаляем файлы если есть
            if ($user->image) {
                Storage::disk('public')->delete('avatars/' . $user->image);
            }
            if ($user->image_crop) {
                Storage::disk('public')->delete('avatars/' . $user->image_crop);
            }

            // Чистим поля
            $user->update([
                'image' => null,
                'image_crop' => null,
            ]);

            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 299, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'partner_id' => $partnerId,
                'description' => ("Пользователю " . $user->name . " удален аватар."),
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Аватар удалён',
        ]);
    }

    //Загрузка аватарки юзеру
    public function uploadUserAvatar(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $partnerId = app('current_partner')->id;
        $authorId = auth()->id(); // Авторизованный пользователь

        $result = DB::transaction(function () use ($request, $user, $authorId, $partnerId) {

            // проверим файлы
            if (!$request->hasFile('image_big') || !$request->hasFile('image_crop')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Файлы не загружены',
                ], 422);
            }

            // удаляем старые файлы
            if ($user->image) {
                Storage::disk('public')->delete('avatars/' . $user->image);
            }
            if ($user->image_crop) {
                Storage::disk('public')->delete('avatars/' . $user->image_crop);
            }

            // сохраняем новые
            $bigFile = $request->file('image_big');
            $cropFile = $request->file('image_crop');

            $bigName = Str::uuid() . '.' . $bigFile->getClientOriginalExtension();
            $cropName = Str::uuid() . '.' . $cropFile->getClientOriginalExtension();

            $bigFile->storeAs('avatars', $bigName, 'public');
            $cropFile->storeAs('avatars', $cropName, 'public');

            // обновляем БД
            $user->update([
                'image' => $bigName,
                'image_crop' => $cropName,
            ]);


            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 27, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'partner_id' => $partnerId,
                'description' => ("Пользователю " . $user->name . " изменен аватар."),
                'created_at' => now(),
            ]);
            return compact('bigName', 'cropName');
        });


        return response()->json([
            'success' => true,
            'message' => 'Аватар обновлён',
            'image_url' => asset('storage/avatars/' . $result['bigName']),
            'image_crop_url' => asset('storage/avatars/' . $result['cropName']),
        ]);
    }

}