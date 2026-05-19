<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Http\Requests\User\FilterRequest;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Models\Location;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerContext;
use Illuminate\Http\Request;
use App\Models\UserField;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\MyLog;
use App\Http\Requests\User\UpdateRequest;
use App\Models\UserFieldValue;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Support\BuildsLogTable;
use Intervention\Image\ImageManager;
use App\Services\UserService;

class UserController extends AdminBaseController
{
    public $service;

    use BuildsLogTable;


    public function __construct(UserService $service, PartnerContext $partnerContext)
    {
        parent::__construct($partnerContext); // <-- КРИТИЧЕСКИЙ МОМЕНТ
        $this->service = $service;
    }

    public function index(FilterRequest $request)
    {
        // 1) Контекст — берём из базового контроллера
        $partnerId   = $this->partnerId();          // int|null
        $currentUser = $this->currentUser();        // App\Models\User
        $user        = $currentUser;                // чтобы не ломать view
        $isSuperadmin = $this->isSuperAdmin();

        // 2) Валидация фильтров (пока не используем, но оставляем на будущее)
        $data = $request->validated();

        // 3) Роли
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

        // 4) Доп. поля: модель для модалки настройки полей + тот же состав с фильтром роли для форм create/edit
        $fields = UserField::with('roles')->where('partner_id', $partnerId)->get();
        $userFieldsPayload = $this->buildUserFieldsPayloadForCurrentPartner();

        // 5) Все команды партнёра
        $allTeams = Team::where('partner_id', $partnerId)
            ->orderBy('order_by', 'asc')
            ->get();

        // 6) Активные локации партнёра (фильтр, создание, редактирование)
        $activeLocations = Location::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderBy('name')
            ->get();

        // 7) Отдаём на view
        return view('admin.user', compact(
            'allTeams',
            'activeLocations',
            'fields',
            'userFieldsPayload',
            'currentUser',
            'roles',
            'user'
        ));
    }

    public function data(Request $request)
    {
        // Валидация входных параметров DataTables
        $validated = $request->validate([
            'id'      => 'nullable|integer',
            'name'    => 'nullable|string',
            'team_id'     => 'nullable|string',   // id или 'none'
            'location_id' => 'nullable|string',   // id или 'none'
            'status'      => 'nullable|string',   // active / inactive

            'draw'   => 'nullable|integer',
            'start'  => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $teamFilter     = $validated['team_id'] ?? null;
        $locationFilter = $validated['location_id'] ?? null;
        $canViewLocations = $this->currentUser()?->can('locations.view') ?? false;

        // Базовый запрос по партнёру через базовый контроллер
        $baseQuery = $this->scopeByPartner(
            User::query(),
            'users.partner_id'
        );

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

        // Фильтр по локации: id / none / пусто
        if ($canViewLocations && $locationFilter !== null && $locationFilter !== '') {
            if ($locationFilter === 'none') {
                $baseQuery->whereNull('users.location_id');
            } else {
                $baseQuery->where('users.location_id', $locationFilter);
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
        $totalRecords = $this->scopeByPartner(User::query())->count();

        // Количество записей с учётом фильтров
        $recordsFiltered = (clone $baseQuery)->count();

        // --- СОРТИРОВКА ДЛЯ DataTables ---

        // индекс колонки (0..8) и направление asc|desc
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc');

        if ($orderColumnIndex !== null) {
            switch ((int)$orderColumnIndex) {
                case 0:
                    // 0 – нумерация, сортировку игнорируем, ставим дефолт
                    $baseQuery
                        ->orderBy('users.lastname', 'asc')
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

                case 4: // locations.name
                    $baseQuery
                        ->leftJoin('locations', 'locations.id', '=', 'users.location_id')
                        ->select('users.*')
                        ->orderBy('locations.name', $orderDir);
                    break;

                case 5: // birthday
                    $baseQuery->orderBy('users.birthday', $orderDir);
                    break;

                case 6: // email
                    $baseQuery->orderBy('users.email', $orderDir);
                    break;

                case 7: // phone
                    $baseQuery->orderBy('users.phone', $orderDir);
                    break;

                case 8: // status_label -> is_enabled
                    $baseQuery->orderBy('users.is_enabled', $orderDir);
                    break;

                case 9: // actions — не сортируем, дефолт
                default:
                    $baseQuery
                        ->orderBy('users.lastname', 'asc')
                        ->orderBy('users.name', 'asc');
                    break;
            }
        } else {
            $baseQuery
                ->orderBy('users.lastname', 'asc')
                ->orderBy('users.name', 'asc');
        }

        // Пагинация DataTables
        $start  = $validated['start']  ?? 0;
        $length = $validated['length'] ?? 20;

        // Подтягиваем команду и локацию
        $users = $baseQuery
            ->with(['team', 'location'])
            ->skip($start)
            ->take($length)
            ->get();

        $data = $users->map(function (User $user) use ($canViewLocations) {
            $avatar = $user->image_crop
                ? asset('storage/avatars/' . $user->image_crop)
                : asset('img/default-avatar.png');

            return [
                'id'           => $user->id,
                'avatar'       => $avatar,
                'name'         => $user->full_name ?: 'Без имени',
                'teams'        => $user->team ? $user->team->title : '',
                'location'     => $canViewLocations && $user->location
                    ? $user->location->name
                    : '',
                'birthday'     => $user->birthday
                    ? Carbon::parse($user->birthday)->format('d.m.Y')
                    : '',
                'email'        => $user->email ?? '',
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
     * Доп. поля пользователя для текущего партнёра (как в edit): фильтр по роли админа и флаг editable.
     *
     * @return array<int, array{id:int,name:string,slug:string,field_type:string,roles:array<int,int>,editable:bool}>
     */
    protected function buildUserFieldsPayloadForCurrentPartner(): array
    {
        $partnerId = $this->partnerId();
        $currentUser = $this->currentUser();
        $isSuperadmin = $this->isSuperAdmin();

        $fieldsQuery = UserField::with('roles')
            ->where('partner_id', $partnerId);

        if (!$isSuperadmin && $currentUser?->role_id) {
            $fieldsQuery->whereHas('roles', fn ($q) =>
                $q->where('role_id', $currentUser->role_id)
            );
        }

        $fields = $fieldsQuery->get();

        return $fields->map(function (UserField $f) use ($currentUser, $isSuperadmin) {
            $allowedRoles = $f->roles->pluck('id')->map(fn ($i) => (int) $i);

            return [
                'id' => $f->id,
                'name' => $f->name,
                'slug' => $f->slug,
                'field_type' => $f->field_type,
                'roles' => $allowedRoles->all(),
                'editable' => $isSuperadmin || $allowedRoles->contains($currentUser?->role_id),
            ];
        })->all();
    }

    public function store(StoreRequest $request)
    {
        // 1) Валидируем и нормализуем входные данные
        $validatedData = $request->validated();

        $partnerId = $this->partnerId();
        if (!$partnerId) {
            abort(400, 'Текущий партнёр не определён.');
        }

        $customInput = $validatedData['custom'] ?? [];
        unset($validatedData['custom']);

        $schoolLeadId = isset($validatedData['school_lead_id'])
            ? (int) $validatedData['school_lead_id']
            : null;
        unset($validatedData['school_lead_id']);

        $isEnabled  = $request->boolean('is_enabled');          // чекбокс может не прийти — приводим к bool
        $teamId     = $validatedData['team_id'] ?? null;        // поле опционально — может отсутствовать
        $locationId = $validatedData['location_id'] ?? null;

        // Собираем итоговый массив данных для сервиса
        $data = array_merge($validatedData, [
            'partner_id'  => $partnerId,
            'is_enabled'  => $isEnabled,
            'team_id'     => $teamId, // может быть null
            'location_id' => $locationId,
        ]);

        $fieldsPayload = $this->buildUserFieldsPayloadForCurrentPartner();
        $editableSlugSet = collect($fieldsPayload)
            ->filter(fn (array $row) => !empty($row['editable']))
            ->pluck('slug')
            ->flip()
            ->all();

        // 2) Создание пользователя + логирование в транзакции
        $user            = null;
        $teamTitleForLog = '-';

        DB::transaction(function () use (
            &$user,
            &$teamTitleForLog,
            $data,
            $teamId,
            $locationId,
            $partnerId,
            $schoolLeadId,
            $customInput,
            $editableSlugSet
        ) {
            // Создаём пользователя через доменный сервис
            $user = $this->service->store($data);

            if ($schoolLeadId) {
                SchoolLead::query()
                    ->where('id', $schoolLeadId)
                    ->where('partner_id', $partnerId)
                    ->whereNull('user_id')
                    ->update(['user_id' => $user->id]);
            }

            if (is_array($customInput) && $customInput !== []) {
                foreach ($customInput as $slug => $newValue) {
                    if (!isset($editableSlugSet[$slug])) {
                        continue;
                    }

                    /** @var UserField|null $field */
                    $field = UserField::query()
                        ->where('partner_id', $partnerId)
                        ->where('slug', $slug)
                        ->first();

                    if (!$field) {
                        continue;
                    }

                    $valueStr = $newValue === null ? '' : (string) $newValue;

                    UserFieldValue::updateOrCreate(
                        ['user_id' => $user->id, 'field_id' => $field->id],
                        ['value' => $valueStr]
                    );
                }
            }

            // Группа (может отсутствовать)
            if ($teamId) {
                $teamTitleForLog = Team::find($teamId)?->title ?? '-';
            }

            // Роль (обязательна, но подстрахуемся)
            $role            = Role::find($data['role_id']);
            $roleNameOrLabel = $role->label ?? $role->name ?? '-';

            // Форматирование дат для лога
            $formatDateForLog = function (?string $value): string {
                return $value ? Carbon::parse($value)->format('d.m.Y') : '-';
            };

            // Логирование (пишем данные из итоговых сущностей/нормализованных значений)
            MyLog::create([
                'type'         => 2,   // юзер-лог
                'action'       => 21,  // создание учётки
                'target_type'  => \App\Models\User::class,
                'target_id'    => $user->id,
                'user_id'      => $user->id,
                'target_label' => $user->full_name ?: "user#{$user->id}",
                'description'  => sprintf(
                    "Имя: %s\nД.р: %s\nНачало: %s\nГруппа: %s\nEmail: %s\nАктивен: %s\nРоль: %s",
                    $user->full_name ?: "user#{$user->id}",
                    $formatDateForLog($data['birthday']   ?? null),
                    $formatDateForLog($data['start_date'] ?? null),
                    $teamTitleForLog,
                    $user->email,
                    ($data['is_enabled'] ?? false) ? 'Да' : 'Нет',
                    $roleNameOrLabel
                ),
            ]);
        });

        if (!$user) {
            abort(500, 'Не удалось создать пользователя.');
        }

        // 3) Ответ для AJAX (без лишних повторных запросов и с безопасными доступами)
        if ($request->ajax()) {
            $birthdayFormatted   = $user->birthday
                ? Carbon::parse($user->birthday)->format('d.m.Y')
                : '-';
            $startDateFormatted  = $user->start_date
                ? Carbon::parse($user->start_date)->format('d.m.Y')
                : '-';

            return response()->json([
                'message' => 'Пользователь создан успешно',
                'user'    => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'birthday'   => $birthdayFormatted,
                    'start_date' => $startDateFormatted,
                    'team'       => $teamTitleForLog,
                    'email'      => $user->email ?? '',
                    'is_enabled' => $user->is_enabled ? 'Да' : 'Нет',
                ],
            ], 200);
        }
    }

    public function edit(User $user)
    {
        // 1) Контекст через AdminBaseController
        $partnerId   = $this->partnerId();
        $currentUser = $this->currentUser();
        $isSuperadmin = $this->isSuperAdmin();

        // 2) Доп. поля (тот же состав, что на странице списка / в модалке создания)
        $fieldsPayload = $this->buildUserFieldsPayloadForCurrentPartner();

        // 3) Системные + партнёрские роли
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

        $rolesPayload = $allRoles->map(fn (Role $r) => [
            'id'     => $r->id,
            'name'   => $r->name,
            'label'  => $r->label,
            'system' => (bool) $r->is_sistem,
        ])->all();

        // 4) Загружаем связи user->fields (pivot value) и локацию
        $user->load(['fields', 'location']);

        if (request()->ajax()) {
            // Преобразуем модель в массив
            $userArray = $user->toArray();

            // Нормализуем birthday под <input type="date">
            $userArray['birthday'] = $user->birthday
                ? $user->birthday->format('Y-m-d')
                : null;

            $userArray['location'] = $user->location
                ? [
                    'id'         => $user->location->id,
                    'name'       => $user->location->name,
                    'is_enabled' => (bool) $user->location->is_enabled,
                ]
                : null;

            return response()->json([
                'user' => $userArray,
                'currentUser' => [
                    'role_id'      => $currentUser?->role_id,
                    'isSuperadmin' => $isSuperadmin,
                ],
                'fields' => $fieldsPayload,
                'roles'  => $rolesPayload,
            ]);
        }

        // если когда-нибудь захочешь не-AJAX — сюда можно добавить view/redirect
    }

    public function update(UpdateRequest $request, User $user)
    {
        // Актор (кто редактирует) через базовый контроллер
        $actor = $this->currentUser();
        $partnerId = $this->partnerId();

        $user->load(['team', 'role', 'location']);

        // Снимок старых значений (только то, что потенциально логируем)
        $old = [
            'name'       => (string) ($user->name ?? ''),
            'lastname'   => (string) ($user->lastname ?? ''),
            'email'      => (string) ($user->email ?? ''),
            'is_enabled' => (bool)   ($user->is_enabled ?? false),
            'birthday'   => $user->birthday, // Carbon|string|null — отформатируем ниже
            'team'       => (string) ($user->team?->title ?: '-'),
            'location'   => (string) ($user->location?->name ?: '-'),
            'role'       => (string) ($user->role?->label ?: '-'),
            'phone'      => (string) ($user->phone ?? ''),
        ];

        // Валидные входные данные
        $validatedData = $request->validated();

        // Текущее состояние кастом-полей: field_id => value
        $existingCustomValues = UserFieldValue::where('user_id', $user->id)
            ->get()
            ->keyBy('field_id')
            ->map(fn (UserFieldValue $v) => $v->value)
            ->all();

        DB::transaction(function () use ($user, $validatedData, $existingCustomValues, $old, $actor, $partnerId) {
            // 1) Телефон: менять и логировать только при наличии права
            if (array_key_exists('phone', $validatedData)) {
                $newPhoneIncoming = (string) $validatedData['phone'];

                if (
                    $actor
                    && $actor->can('users.phone.update')
                    && $newPhoneIncoming !== (string) $old['phone']
                ) {
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
                $fieldsBySlug  = UserField::whereIn('slug', $incomingSlugs)
                    ->when($partnerId, fn ($q) => $q->where('partner_id', $partnerId))
                    ->get()
                    ->keyBy('slug');

                foreach ($validatedData['custom'] as $slug => $newValue) {
                    /** @var UserField|null $field */
                    $field = $fieldsBySlug[$slug] ?? null;
                    if (!$field) {
                        Log::warning("User update: UserField not found by slug '{$slug}'");
                        continue;
                    }

                    $oldValue = $existingCustomValues[$field->id] ?? null;

                    if ((string) $oldValue !== (string) $newValue) {
                        UserFieldValue::updateOrCreate(
                            ['user_id' => $user->id, 'field_id' => $field->id],
                            ['value'   => $newValue]
                        );

                        $oldTxt = ((string) $oldValue === '') ? '-' : (string) $oldValue;
                        $newTxt = ((string) $newValue === '') ? '-' : (string) $newValue;

                        $customChanges[] = "{$field->name}: {$oldTxt} → {$newTxt}";
                    }
                }
            }

            // 4) Обновили модель — теперь собираем diff по основным полям
            $user->refresh();

            $formatDate = function ($val): string {
                if (empty($val)) {
                    return '-';
                }

                if ($val instanceof \Carbon\CarbonInterface) {
                    return $val->format('d.m.Y');
                }

                try {
                    return \Carbon\Carbon::parse($val)->format('d.m.Y');
                } catch (\Throwable $e) {
                    return '-';
                }
            };

            $user->load(['team', 'role', 'location']);

            $new = [
                'name'       => (string) ($user->name ?? ''),
                'lastname'   => (string) ($user->lastname ?? ''),
                'email'      => (string) ($user->email ?? ''),
                'is_enabled' => (bool)   ($user->is_enabled ?? false),
                'birthday'   => $user->birthday,
                'team'       => (string) ($user->team?->title ?: '-'),
                'location'   => (string) ($user->location?->name ?: '-'),
                'role'       => (string) ($user->role?->label ?: '-'),
                'phone'      => (string) ($user->phone ?? ''),
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
                $changes[] = "Активен: " . ($old['is_enabled'] ? 'Да' : 'Нет')
                    . " → " . ($new['is_enabled'] ? 'Да' : 'Нет');
            }
            if ($formatDate($old['birthday']) !== $formatDate($new['birthday'])) {
                $changes[] = "Д.р: " . $formatDate($old['birthday'])
                    . " → " . $formatDate($new['birthday']);
            }
            if ($old['team'] !== $new['team']) {
                $changes[] = "Группа: {$old['team']} → {$new['team']}"; // названия, не id
            }
            if (
                $actor
                && $actor->can('locations.view')
                && $old['location'] !== $new['location']
            ) {
                $changes[] = "Локация: {$old['location']} → {$new['location']}";
            }
            if ($old['role'] !== $new['role']) {
                $changes[] = "Роль: {$old['role']} → {$new['role']}";
            }

            if (
                $old['phone'] !== $new['phone']
                && $actor
                && $actor->can('users.phone.update')
            ) {
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
                $targetLabel = trim(
                    ($user->lastname ? ($user->lastname . ' ') : '') . ($user->name ?? '')
                );

                MyLog::create([
                    'type'         => 2,
                    'action'       => 22, // изменение учётной записи
                    'user_id'      => $user->id,
                    'target_type'  => \App\Models\User::class,
                    'target_id'    => $user->id,
                    'target_label' => $targetLabel !== ''
                        ? $targetLabel
                        : ($user->name ?? "user#{$user->id}"),
                    'description'  => implode("\n", $changes),
                ]);
            }
        });

        return response()->json([
            'message' => 'Пользователь успешно обновлён',
        ], 200);
    }

    public function delete(User $user)
    {
        // Проверяем, имеет ли актор право трогать этого юзера
        $partnerId = $this->partnerId();
        $isSuper   = $this->isSuperAdmin();

        if ($partnerId && !$isSuper && (int) $user->partner_id !== (int) $partnerId) {
            abort(403, 'Доступ запрещён.');
        }

        DB::transaction(function () use ($user) {
            $targetLabel = $user->full_name ?: "user#{$user->id}";

            $user->delete();

            MyLog::create([
                'type'         => 2, // Лог для обновления юзеров
                'action'       => 24,
                'user_id'      => $user->id,
                'target_type'  => \App\Models\User::class,
                'target_id'    => $user->id,
                'target_label' => $targetLabel,
                'description'  => "Удален пользователь: {$user->name}  ID: {$user->id}.",
                'created_at'   => now(),
            ]);
        });

        return response()->json([
            'success' => 'Пользователь успешно удалён',
        ]);
    }

    //TODO: Сделать логирование только доп. полей, в которых были изменения. Сейчас в лог попадают все доп. поля.

    public function updatePassword(UpdatePasswordRequest $request, User $user)
    {
        $partnerId = $this->partnerId();
        $actor     = $this->currentUser();
        $isSuper   = $this->isSuperAdmin();

        // Ограничение по партнёру: не супер-админ → можно менять пароль только своим
        if ($partnerId !== null && !$isSuper && (int) $user->partner_id !== (int) $partnerId) {
            abort(403, 'Доступ запрещён.');
        }

        $newPassword = $request->validated()['password'];

        // Не даём поставить тот же самый пароль
        $stored = $user->getAuthPassword() ?? $user->password;
        if (is_string($stored) && $stored !== '' && password_verify($newPassword, $stored)) {
            return response()->json([
                'message' => 'Новый пароль совпадает с текущим.',
            ], 422);
        }

        DB::transaction(function () use ($user, $newPassword, $actor) {
            $user->password = Hash::make($newPassword);
            $user->save();

            $targetLabel = trim(
                ($user->lastname ? ($user->lastname . ' ') : '') . ($user->name ?? '')
            );

            MyLog::create([
                'type'         => 2,
                'action'       => 26,
                'user_id'      => $user->id,
                'target_type'  => User::class,
                'target_id'    => $user->id,
                'target_label' => $targetLabel !== ''
                    ? $targetLabel
                    : ($user->name ?? "user#{$user->id}"),
                'description'  => sprintf(
                    'Пароль пользователя "%s" изменён администратором "%s".',
                    $user->name,
                    $actor?->name ?? 'system'
                ),
            ]);
        });

        return response()->json(['success' => true]);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable(2);
    }

}