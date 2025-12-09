<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

//use App\Http\Filters\UserFilter;
use App\Http\Requests\User\FilterRequest;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\UserField;
use Illuminate\Support\Facades\Auth;

// Модель для работы с таблицей тегов
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
use App\Support\BuildsLogTable;


use App\Servises\UserService;

use App\Models\UserTableSetting;


class UserController extends Controller
{
    public $service;

    use BuildsLogTable;


    public function __construct(UserService $service)
    {
        $this->service = $service;
    }


    public function index(FilterRequest $request)
    {
        // 1) Контекст (БЕЗ ИЗМЕНЕНИЙ)
        $partnerId = app('current_partner')->id;
        $user = Auth::user();
        $currentUser = Auth::user();
        $userRoleName = $currentUser->role?->name;
        $isSuperadmin = $userRoleName === 'superadmin';

        // 2) Валидация фильтров (есть, но дальше не используем напрямую)
        $data = $request->validated();

        // 3) Роли (как было)
        $rolesQuery = Role::query();

        if (!$isSuperadmin) {
            $rolesQuery->where('is_visible', 1);
        }

        $rolesQuery->where(function ($q) use ($partnerId) {
            $q->where('is_sistem', 1)
                ->orWhereHas('partners', function ($q2) use ($partnerId) {
                    $q2->where('partner_role.partner_id', $partnerId);
                });
        });

        $roles = $rolesQuery
            ->orderBy('order_by')
            ->get();

        // 4) Произвольные поля партнёра (как было)
        $fields = UserField::where('partner_id', $partnerId)->get();

        // !!! ИЗМЕНЕНИЕ: убираем выборку $allUsers и paginate()
        // Раньше здесь был код с User::...->paginate(20);
        // Теперь дата для таблицы идет отдельным AJAX-запросом в метод data().

        // 7) Все команды партнёра (без изменений)
        $allTeams = Team::where('partner_id', $partnerId)
            ->orderBy('order_by', 'asc')
            ->get();

        // 8) Отдаём на view (БЕЗ allUsers)
        return view('admin.user', compact(
            'allTeams',
            'fields',
            'currentUser',
            'roles',
            'user'
        ));
    }

    /**
     * DataTables серверный endpoint для списка пользователей.
     * Возвращает JSON в формате, понятном DataTables.
     */

    public function data2(Request $request)
    {
        $partnerId = app('current_partner')->id;
        $currentUser = Auth::user();
        $userRoleName = $currentUser->role?->name;
        $isSuperadmin = $userRoleName === 'superadmin';

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'name' => 'nullable|string',
            'team_id' => 'nullable|string',   // id или 'none'
            'status' => 'nullable|string',   // active / inactive

            'draw' => 'nullable|integer',
            'start' => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $teamFilter = $validated['team_id'] ?? null;

        // Базовый запрос по партнёру
        $baseQuery = User::where('partner_id', $partnerId);

        // Фильтр по ID
        if (!empty($validated['id'])) {
            $baseQuery->where('id', $validated['id']);
        }

        // Фильтр по имени
//        if (!empty($validated['name'])) {
//            $name = $validated['name'];
//
//            $baseQuery->where(function ($q) use ($name) {
//                // подставь сюда реальные поля: name, firstname, lastname, full_name — как у тебя в БД
//                $q->where('name', 'like', '%' . $name . '%')
//                    ->orWhere('lastname', 'like', '%' . $name . '%');
//            });
//        }


        // Фильтр по имени / email / телефону / дате рождения
        if (!empty($validated['name'])) {
            $value = $validated['name'];

            $baseQuery->where(function ($q) use ($value) {
                $like = '%' . $value . '%';

                $q->where('name', 'like', $like)
                    ->orWhere('lastname', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('birthday', 'like', $like); // YYYY-MM-DD тоже ищется по подстроке
            });
        }


        // Фильтр по группе: id / none / пусто
        if ($teamFilter !== null && $teamFilter !== '') {
            if ($teamFilter === 'none') {
                $baseQuery->whereNull('team_id');
            } else {
                $baseQuery->where('team_id', $teamFilter);
            }
        }

        // Фильтр по статусу
        if (!empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('is_enabled', 0);
            }
        }

        // Общее количество записей по партнёру (без фильтров)
        $totalRecords = User::where('partner_id', $partnerId)->count();

        // Количество записей с учётом фильтров
        $filteredQuery = clone $baseQuery;
        $recordsFiltered = $filteredQuery->count();

        // Пагинация DataTables
        $start = $validated['start'] ?? 0;
        $length = $validated['length'] ?? 20;

        // Сортировка (как раньше, по фамилии)
        $baseQuery->orderBy('lastname', 'asc');

        // Подтягиваем ОДНУ команду
        $users = $baseQuery
            ->with('team')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $users->map(function (User $user) {

            $avatar = $user->image_crop
                ? asset('storage/avatars/' . $user->image_crop)
                : asset('img/default-avatar.png');

            return [
                'id'           => $user->id,
                'avatar'       => $avatar,
                'name'         => $user->full_name ?: 'Без имени',
                'teams'        => $user->team ? $user->team->title : '',
                'birthday'     => $user->birthday
                    ? Carbon::parse($user->birthday)->format('d.m.Y')
                    : '',
                'email'        => $user->email,
                'phone'        => $user->phone,
                'status_label' => $user->is_enabled ? 'Активен' : 'Неактивен',
                'is_enabled'   => (int) $user->is_enabled,
            ];

        })->toArray();

        return response()->json([
            'draw' => (int)($validated['draw'] ?? 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function data(Request $request)
    {
        $partnerId   = app('current_partner')->id;
        $currentUser = Auth::user();
        $userRoleName = $currentUser->role?->name;
        $isSuperadmin = $userRoleName === 'superadmin';

        $validated = $request->validate([
            'id'      => 'nullable|integer',
            'name'    => 'nullable|string',
            'team_id' => 'nullable|string',   // id или 'none'
            'status'  => 'nullable|string',   // active / inactive

            'draw'   => 'nullable|integer',
            'start'  => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $teamFilter = $validated['team_id'] ?? null;

        // Базовый запрос по партнёру
        $baseQuery = User::query()
            ->where('users.partner_id', $partnerId);

        // Фильтр по ID
        if (!empty($validated['id'])) {
            $baseQuery->where('users.id', $validated['id']);
        }

        // Фильтр по имени / email / телефону / дате рождения
        if (!empty($validated['name'])) {
            $value = $validated['name'];
            $like  = '%' . $value . '%';

            $baseQuery->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)
                    ->orWhere('users.lastname', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.phone', 'like', $like)
                    ->orWhere('users.birthday', 'like', $like);
            });
        }

        // Фильтр по группе: id / none / пусто
        if ($teamFilter !== null && $teamFilter !== '') {
            if ($teamFilter === 'none') {
                $baseQuery->whereNull('users.team_id');
            } else {
                $baseQuery->where('users.team_id', $teamFilter);
            }
        }

        // Фильтр по статусу
        if (!empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('users.is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('users.is_enabled', 0);
            }
        }

        // Общее количество записей по партнёру (без фильтров)
        $totalRecords = User::where('partner_id', $partnerId)->count();

        // Количество записей с учётом фильтров
        $filteredQuery    = clone $baseQuery;
        $recordsFiltered  = $filteredQuery->count();

        // --- СОРТИРОВКА ДЛЯ DataTables ---

        // индекс колонки (0..7) и направление asc|desc
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc');


        if ($orderColumnIndex !== null) {
            switch ((int)$orderColumnIndex) {
                case 0:
                    // 0 – нумерация, сортировку игнорируем, ставим дефолт
                    $baseQuery->orderBy('users.lastname', 'asc')
                        ->orderBy('users.name', 'asc');
                    break;

                case 1: // avatar -> image_crop
                    $baseQuery->orderBy('users.image_crop', $orderDir);
                    break;

                case 2: // name
                    $baseQuery
                        ->orderBy('users.lastname', $orderDir)
                        ->orderBy('users.name', $orderDir);
                    break;

                case 3: // teams.title
                    $baseQuery
                        ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
                        ->select('users.*')
                        ->orderBy('teams.title', $orderDir);
                    break;

                case 4: // birthday
                    $baseQuery->orderBy('users.birthday', $orderDir);
                    break;

                case 5: // email
                    $baseQuery->orderBy('users.email', $orderDir);
                    break;

                case 6: // phone
                    $baseQuery->orderBy('users.phone', $orderDir);
                    break;

                case 7: // status_label -> is_enabled
                    $baseQuery->orderBy('users.is_enabled', $orderDir);
                    break;

                case 8: // actions — не сортируем, дефолт
                default:
                    $baseQuery->orderBy('users.lastname', 'asc')
                        ->orderBy('users.name', 'asc');
                    break;
            }
        } else {
            $baseQuery->orderBy('users.lastname', 'asc')
                ->orderBy('users.name', 'asc');
        }


        // Пагинация DataTables
        $start  = $validated['start']  ?? 0;
        $length = $validated['length'] ?? 20;

        // Подтягиваем команду (отдельным запросом, как и раньше)
        $users = $baseQuery
            ->with('team')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $users->map(function (User $user) {
            $avatar = $user->image_crop
                ? asset('storage/avatars/' . $user->image_crop)
                : asset('img/default-avatar.png');

            return [
                'id'           => $user->id,
                'avatar'       => $avatar,
                'name'         => $user->full_name ?: 'Без имени',
                'teams'        => $user->team ? $user->team->title : '',
                'birthday'     => $user->birthday
                    ? Carbon::parse($user->birthday)->format('d.m.Y')
                    : '',
                'email'        => $user->email,
                'phone'        => $user->phone,
                'status_label' => $user->is_enabled ? 'Активен' : 'Неактивен',
                'is_enabled'   => (int) $user->is_enabled,
            ];
        })->toArray();

        return response()->json([
            'draw'            => (int)($validated['draw'] ?? 0),
            'recordsTotal'    => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }


    /**
     * Вернуть настройки колонок для текущего пользователя
     * для таблицы "users_index".
     */
    public function getColumnsSettings()
    {
        $userId = Auth::id();

        $settings = UserTableSetting::where('user_id', $userId)
            ->where('table_key', 'users_index')
            ->first();

        // 👉 ВАЖНО: возвращаем ЧИСТЫЙ массив columns или пустой объект
        $columns = $settings?->columns;

        if (!is_array($columns)) {
            $columns = [];
        }

        return response()->json($columns);
    }

    /**
     * Сохранить настройки колонок для текущего пользователя.
     * Ожидает в запросе: columns: { avatar: true, name: false, ... }
     */
    public function saveColumnsSettings(Request $request)
    {
        $userId = Auth::id();

        // валидируем только, что это массив
        $data = $request->validate([
            'columns' => 'required|array',
        ]);

        $rawColumns = $data['columns'];

        // аккуратно нормализуем к boolean
        $normalized = [];

        foreach ($rawColumns as $key => $value) {
            // в запрос может прилететь 1/0, "1"/"0", true/false, "true"/"false"
            $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            // если вдруг ничего не распознали — считаем false
            if ($bool === null) {
                $bool = false;
            }

            $normalized[$key] = $bool;
        }

        UserTableSetting::updateOrCreate(
            [
                'user_id'   => $userId,
                'table_key' => 'users_index',
            ],
            [
                'columns' => $normalized,
            ]
        );

        return response()->json([
            'success' => true,
        ]);
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


            // Группа (может отсутствовать)
            $teamTitle = '-';
            if ($teamId) {
                $team = Team::find($teamId);
                $teamTitle = $team?->title ?? '-';
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
                'target_type' => \App\Models\User::class,
                'target_id' => $user->id,
                'user_id' => $user->id,
                'target_label' => $user->full_name ?: "user#{$user->id}",
                'description' => sprintf(
                    "Имя: %s\nД.р: %s\nНачало: %s\nГруппа: %s\nEmail: %s\nАктивен: %s\nРоль: %s",
                    $user->full_name ?: "user#{$user->id}",
                    $formatDateForLog($data['birthday'] ?? null),
                    $formatDateForLog($data['start_date'] ?? null),
                    $teamTitle,
                    $user->email,
                    ($data['is_enabled'] ?? false) ? 'Да' : 'Нет',
                    $roleNameOrLabel
                ),
            ]);
        });

        // 3) Ответ для AJAX (без лишних повторных запросов и с безопасными доступами)
        if ($request->ajax()) {
            // Попробуем взять из связи, если есть; если нет — из team_id; иначе дефолт.
            $teamTitleForResponse = $user->team?->title
                ?? ($teamId ? Team::find($teamId)?->title : '-')
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
        $userRoleName = $currentUser->role?->name;
        $isSuperadmin = $userRoleName === 'superadmin';

        // 2) Загружаем UserField вместе их ролями
        $fieldsQuery = UserField::with('roles')
            ->where('partner_id', $partnerId);
        // Изменение: если не супер-админ, то подгружаем только те поля,
        // права на которые есть у роли текущего пользователя
        if (!$isSuperadmin) {
            $fieldsQuery->whereHas('roles', fn($q) => $q->where('role_id', $currentUser->role_id)
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
        $partnerRoles = Role::whereHas('partners', fn($q) => $q->where('partner_role.partner_id', $partnerId)
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

        // Снимок старых значений (только то, что потенциально логируем)
        $old = [
            'name' => (string)($user->name ?? ''),
            'lastname' => (string)($user->lastname ?? ''),
            'email' => (string)($user->email ?? ''),
            'is_enabled' => (bool)($user->is_enabled ?? false),
            'birthday' => $user->birthday, // Carbon|string|null — отформатируем ниже
            'team' => (string)($user->team?->title ?: '-'),
            'role' => (string)($user->role?->label ?: '-'),
            'phone' => (string)($user->phone ?? ''),
        ];

        // Валидные входные данные
        $validatedData = $request->validated();

        // Текущее состояние кастом-полей: field_id => value
        $existingCustomValues = UserFieldValue::where('user_id', $user->id)
            ->get()
            ->keyBy('field_id')
            ->map(fn(UserFieldValue $v) => $v->value)
            ->all();

        DB::transaction(function () use ($request, $user, $validatedData, $existingCustomValues, $old) {
            // 1) Телефон: менять и логировать только при наличии права
            if (array_key_exists('phone', $validatedData)) {
                $newPhoneIncoming = (string)$validatedData['phone'];
                if ($request->user()->can('users-phone-update') && $newPhoneIncoming !== (string)$old['phone']) {
                    $user->phone = $newPhoneIncoming;
                    $user->phone_verified_at = null; // сброс верификации при смене номера
                }
            }

            // 2) Обновляем остальные поля/связи доменным сервисом
            $this->service->update($user, $validatedData);

            // 3) Кастом-поля: сохраняем только реальные изменения + готовим строки для лога
            $customChanges = [];
            if (!empty($validatedData['custom']) && is_array($validatedData['custom'])) {
                $incomingSlugs = array_keys($validatedData['custom']);
                $fieldsBySlug = UserField::whereIn('slug', $incomingSlugs)->get()->keyBy('slug');

                foreach ($validatedData['custom'] as $slug => $newValue) {
                    $field = $fieldsBySlug[$slug] ?? null;
                    if (!$field) {
                        \Log::warning("User update: UserField not found by slug '{$slug}'");
                        continue;
                    }
                    $oldValue = $existingCustomValues[$field->id] ?? null;

                    if ((string)$oldValue !== (string)$newValue) {
                        UserFieldValue::updateOrCreate(
                            ['user_id' => $user->id, 'field_id' => $field->id],
                            ['value' => $newValue]
                        );

                        $oldTxt = ((string)$oldValue === '') ? '-' : (string)$oldValue;
                        $newTxt = ((string)$newValue === '') ? '-' : (string)$newValue;
                        $customChanges[] = "{$field->name}: {$oldTxt} → {$newTxt}";
                    }
                }
            }

            // 4) Обновили модель — теперь собираем diff по основным полям
            $user->refresh();

            $formatDate = function ($val): string {
                if (empty($val)) return '-';
                if ($val instanceof \Carbon\CarbonInterface) return $val->format('d.m.Y');
                try {
                    return \Carbon\Carbon::parse($val)->format('d.m.Y');
                } catch (\Throwable $e) {
                    return '-';
                }
            };

            $new = [
                'name' => (string)($user->name ?? ''),
                'lastname' => (string)($user->lastname ?? ''),
                'email' => (string)($user->email ?? ''),
                'is_enabled' => (bool)($user->is_enabled ?? false),
                'birthday' => $user->birthday,
                'team' => (string)($user->team?->title ?: '-'),
                'role' => (string)($user->role?->label ?: '-'),
                'phone' => (string)($user->phone ?? ''),
            ];

            $changes = [];

            if ($old['name'] !== $new['name']) {
                $changes[] = "Имя: {$old['name']} → {$new['name']}";
            }
            if ($old['lastname'] !== $new['lastname']) {
                $changes[] = "Фамилия: {$old['lastname']} → {$new['lastname']}";
            }
            if ($old['email'] !== $new['email']) {
                $changes[] = "Email: {$old['email']} → {$new['email']}";
            }
            if ($old['is_enabled'] !== $new['is_enabled']) {
                $changes[] = "Активен: " . ($old['is_enabled'] ? 'Да' : 'Нет') . " → " . ($new['is_enabled'] ? 'Да' : 'Нет');
            }
            if ($formatDate($old['birthday']) !== $formatDate($new['birthday'])) {
                $changes[] = "Д.р: " . $formatDate($old['birthday']) . " → " . $formatDate($new['birthday']);
            }
            if ($old['team'] !== $new['team']) {
                $changes[] = "Группа: {$old['team']} → {$new['team']}"; // названия, не id
            }
            if ($old['role'] !== $new['role']) {
                $changes[] = "Роль: {$old['role']} → {$new['role']}";
            }
            if ($old['phone'] !== $new['phone'] && $request->user()->can('users-phone-update')) {
                // Телефон без маски
                $oldPhone = $old['phone'] !== '' ? $old['phone'] : '-';
                $newPhone = $new['phone'] !== '' ? $new['phone'] : '-';
                $changes[] = "Телефон: {$oldPhone} → {$newPhone}";
            }

            // Приклеиваем изменения по кастом-полям
            foreach ($customChanges as $line) {
                $changes[] = $line;
            }

            // 5) Пишем ОДИН лог, только если реально есть изменения
            if (!empty($changes)) {
                // target_label — без аксессора: фамилия + имя (или имя, если фамилии нет)
                $targetLabel = trim(($user->lastname ? ($user->lastname . ' ') : '') . ($user->name ?? ''));

                MyLog::create([
                    'type' => 2,
                    'action' => 22, // изменение учётной записи
                    'user_id' => $user->id,
                    'target_type' => \App\Models\User::class,
                    'target_id' => $user->id,
                    'target_label' => $targetLabel !== '' ? $targetLabel : ($user->name ?? "user#{$user->id}"),
                    'description' => implode("\n", $changes),
                ]);
            }

        });

        return response()->json([
            'message' => 'Пользователь успешно обновлён'
        ], 200);
    }

    public function delete(User $user)
    {
        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        DB::transaction(function () use ($user) {

            $user->delete();

            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 24,
                'user_id' => $user->id,
                'target_type' => \App\Models\User::class,
                'target_id' => $user->id,
                'target_label' => $user->full_name ?: "user#{$user->id}",
                'description' => "Удален пользователь: {$user->name}  ID: {$user->id}.",
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => 'Пользователь успешно удалён']);
    }

    //TODO: Сделать логирование только доп. полей, в которых были изменения. Сейчас в лог попадают все доп. поля.
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

        // ХЕЛПЕР для генерации уникального slug
        $makeUniqueSlug = function (string $baseName, int $partnerId, ?int $ignoreId = null): string {
            $base = Str::slug($baseName . '-' . $partnerId);
            $slug = $base;
            $i = 1;

            while (
            UserField::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
            ) {
                $slug = $base . '-' . $i;
                $i++;
            }

            return $slug;
        };

        DB::transaction(function () use ($data, $partnerId, $makeUniqueSlug) {
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
                // Получаем удаляемые поля заранее (до удаления)
                $fieldsToDelete = UserField::whereIn('id', $toDelete)->get(['id', 'name']);

                // Удаляем поля
                UserField::whereIn('id', $toDelete)->delete();

                // Логируем каждое удалённое поле
                foreach ($fieldsToDelete as $field) {
                    // 🧾 УДАЛЕНИЕ ДОП. ПОЛЯ
                    MyLog::create([
                        'type' => 2,
                        'action' => 210,
                        'target_type' => \App\Models\UserField::class,
                        'target_id' => $field->id,
                        'target_label' => $field->name,
                        'description' => "Удалено поле '{$field->name}' (ID: {$field->id})",
                        'created_at' => now(),
                    ]);
                }
            }

            // Обрабатываем новые и существующие поля
            foreach ($data['fields'] as $item) {
                $fieldId = $item['id'] ?? null;
                $name = $item['name'];
                $type = $item['field_type'];
                $roles = $item['roles'] ?? [];

                // Генерируем уникальный slug
                $slug = $makeUniqueSlug($name, $partnerId, $fieldId);

                if ($fieldId) {
                    // === Обновление существующего поля ===
                    $field = UserField::where('partner_id', $partnerId)
                        ->findOrFail($fieldId);

                    $changes = [];

                    if ($field->name !== $name) {
                        $changes[] = "Название: '{$field->name}' → '{$name}'";
                    }
                    if ($field->field_type !== $type) {
                        $changes[] = "Тип: '{$field->field_type}' → '{$type}'";
                    }


                    // Обновляем основные поля, если есть изменения
                    if ($changes) {
                        $field->update([
                            'name' => $name,
                            'slug' => $slug,
                            'field_type' => $type,
                        ]);
                    }

                    // --- Сравниваем и логируем изменения ролей ---
                    $oldRoleIds = $field->roles()->pluck('roles.id')->all();
                    $field->roles()->sync($roles);

                    $allIds = array_values(array_unique(array_merge($oldRoleIds, $roles)));
//                    $nameMap  = Role::whereIn('id', $allIds)->pluck('name', 'id')->toArray();
                    $nameMap = Role::whereIn('id', $allIds)->pluck('label', 'id')->toArray(); // <-- изменено


                    $oldNames = collect($oldRoleIds)->map(fn($id) => $nameMap[$id] ?? (string)$id)->unique()->sort()->values()->all();
                    $newNames = collect($roles)->map(fn($id) => $nameMap[$id] ?? (string)$id)->unique()->sort()->values()->all();

                    if ($oldNames !== $newNames) {
                        $changes[] = "Роли: [" . (implode(', ', $oldNames) ?: '-') . "] → [" . (implode(', ', $newNames) ?: '-') . "]";
                    }


                    $description = !empty($changes)
                        ? implode(";\n", $changes) . "\n"   // ; уходит в конец строки, затем перенос
                        : '';

//               ИЗМЕНЕНИЯ ДОП ПОЛЯ
                    MyLog::create([
                        'type' => 2,
                        'action' => 210,
                        'target_type' => \App\Models\UserField::class,
                        'target_id' => $field->id,
                        'target_label' => $field->name,
                        'description' => $description,
                        'created_at' => now(),
                    ]);
                } else {
                    // === Создание нового поля ===
                    $field = UserField::create([
                        'name' => $name,
                        'slug' => $slug,
                        'field_type' => $type,
                        'partner_id' => $partnerId,
                    ]);

                    $field->roles()->sync($roles);

                    $newNames = Role::whereIn('id', $roles)->pluck('name')->sort()->values()->all();

                    //               СОЗДАНИЕ ДОП ПОЛЯ
                    MyLog::create([
                        'type' => 2,
                        'action' => 210,
                        'target_type' => \App\Models\UserField::class,
                        'target_id' => $field->id,
                        'target_label' => $field->name,
                        'description' =>
                            "Создано поле '{$field->name}' (ID: {$field->id})\n" .
                            "Роли: [-] → [" . (implode(', ', $newNames) ?: '-') . "]",
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
            $targetLabel = trim(($user->lastname ? ($user->lastname . ' ') : '') . ($user->name ?? ''));

            \App\Models\MyLog::create([
                'type' => 2,
                'action' => 26,
                'user_id' => $user->id,
                'target_type' => \App\Models\User::class,
                'target_id' => $user->id,
                'target_label' => $targetLabel !== '' ? $targetLabel : ($user->name ?? "user#{$user->id}"),

                'description' => sprintf('Пароль пользователя "%s" изменён администратором "%s".',
                    $user->name, $request->user()->name),
            ]);
        });

        return response()->json(['success' => true]);
    }

    protected function isSuperAdmin(\App\Models\User $actor): bool
    {
        // Если используете Spatie\Permission:
        // return $actor->hasRole('superadmin');

        // Своя ролевая модель (role_id/slug) — пример:
        return ($actor->role->name ?? null) === 'superadmin'; // подставьте ваш slug/проверку
    }

    //Удаление аватарки юзера в пользователях
    public function destroyUserAvatar($id)
    {
        $user = User::findOrFail($id);

        DB::transaction(function () use ($user) {

            $targetLabel = $user->full_name ?: "user#{$user->id}";

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
                'target_type' => \App\Models\User::class,
                'user_id' => $user->id,
                'target_id' => $user->id,
                'target_label' => $targetLabel,
                'description' => ("Пользователю " . $targetLabel . " удален аватар."),
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Аватар удалён',
        ]);
    }

    //Загрузка аватарки юзеру  в пользователях
    public function uploadUserAvatar(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $result = DB::transaction(function () use ($request, $user) {
            $targetLabel = $user->full_name ?: "user#{$user->id}";

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
                'user_id' => $user->id,
                'target_type' => \App\Models\User::class,
                'target_id' => $user->id,
                'target_label' => $targetLabel,
                'description' => "Пользователю {$targetLabel} изменён аватар.",
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

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable(2);
    }



}

