<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminBaseController;
use App\Enums\UserSex;
use App\Http\Requests\User\FilterRequest;
use App\Http\Requests\User\StoreRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Models\ParentProfile;
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
use App\Enums\AuditEvent;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Http\Requests\User\UpdateRequest;
use App\Models\UserFieldValue;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Support\BuildsLogTable;
use Intervention\Image\ImageManager;
use App\Services\SchoolLeads\LatestUserContractLookup;
use App\Services\TeamUserSyncService;
use App\Services\UserService;
use App\Services\Users\ClientWelcomeCredentialsService;
use App\Http\Controllers\Admin\Concerns\RendersUsersSectionTabs;

class UserController extends AdminBaseController
{
    public $service;

    use BuildsLogTable;
    use RendersUsersSectionTabs;


    public function __construct(
        UserService $service,
        PartnerContext $partnerContext,
        private readonly AuditLogger $auditLogger,
        private readonly TeamUserSyncService $teamUserSync,
        private readonly ClientWelcomeCredentialsService $welcomeCredentialsService,
    )
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

        // 3) Роли (superadmin нельзя назначать через CRM)
        $rolesQuery = Role::query()->exceptSuperadmin();

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

        $canViewContracts = $currentUser?->can('contracts.view') ?? false;
        $canViewUserSex = $currentUser?->can('users.sex') ?? false;
        $canViewUserComment = $currentUser?->can('users.comment') ?? false;
        $studentRoleId = (int) (Role::query()->where('name', 'user')->value('id') ?? 0);

        // 6) Отдаём на view
        return view('admin.user', compact(
            'allTeams',
            'fields',
            'userFieldsPayload',
            'currentUser',
            'roles',
            'user',
            'canViewContracts',
            'canViewUserSex',
            'canViewUserComment',
            'studentRoleId'
        ) + $this->usersSectionViewData('users'));
    }

    public function data(Request $request)
    {
        // Валидация входных параметров DataTables
        $validated = $request->validate([
            'id'      => 'nullable|integer',
            'name'    => 'nullable|string',
            'team_id'     => 'nullable|string',   // id или 'none'
            'status'      => 'nullable|string',   // active / inactive
            'contract'    => 'nullable|string|in:with,without,signed,unsigned',

            'draw'   => 'nullable|integer',
            'start'  => 'nullable|integer',
            'length' => 'nullable|integer',
        ]);

        $canViewContracts = Auth::user()?->can('contracts.view') ?? false;
        $canViewUserSex = Auth::user()?->can('users.sex') ?? false;
        $canViewUserComment = Auth::user()?->can('users.comment') ?? false;
        $contractFilter   = $canViewContracts ? trim((string) ($validated['contract'] ?? '')) : '';

        $teamFilter = $validated['team_id'] ?? null;

        $nameSearch = trim((string) ($validated['name'] ?? ''));
        if ($nameSearch === '' && $request->filled('search.value')) {
            $nameSearch = trim((string) $request->input('search.value'));
        }

        // Базовый запрос по партнёру через базовый контроллер
        $baseQuery = $this->scopeByPartner(
            User::query(),
            'users.partner_id'
        );

        $studentRoleId = (int) (Role::query()->where('name', 'user')->value('id') ?? 0);
        if ($studentRoleId > 0) {
            $baseQuery->where('users.role_id', $studentRoleId);
        }

        // Фильтр по ID
        if (!empty($validated['id'])) {
            $baseQuery->where('users.id', $validated['id']);
        }

        // Фильтр по имени / email / телефону / дате рождения (панель фильтров или поиск DataTables)
        if ($nameSearch !== '') {
            $like = '%' . $nameSearch . '%';

            $baseQuery->where(function ($q) use ($like) {
                $q->where('users.name', 'like', $like)
                    ->orWhere('users.lastname', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.phone', 'like', $like)
                    ->orWhere('users.birthday', 'like', $like)
                    ->orWhereHas('parentProfile', function ($parentQuery) use ($like) {
                        $parentQuery->where('lastname', 'like', $like)
                            ->orWhere('firstname', 'like', $like)
                            ->orWhere('middlename', 'like', $like);
                    });
            });
        }

        // Фильтр по группе: id / none / пусто
        if ($teamFilter !== null && $teamFilter !== '') {
            $baseQuery->filterByStudentTeam((int) $this->partnerId(), $teamFilter);
        }

        // Фильтр по статусу
        if (!empty($validated['status'])) {
            if ($validated['status'] === 'active') {
                $baseQuery->where('users.is_enabled', 1);
            } elseif ($validated['status'] === 'inactive') {
                $baseQuery->where('users.is_enabled', 0);
            }
        }

        if ($canViewContracts && $contractFilter !== '') {
            app(LatestUserContractLookup::class)->applyUsersListContractFilter(
                $baseQuery,
                (int) $this->partnerId(),
                $contractFilter
            );
        }

        // Общее количество записей по партнёру (без фильтров)
        $totalRecordsQuery = $this->scopeByPartner(User::query());
        if ($studentRoleId > 0) {
            $totalRecordsQuery->where('users.role_id', $studentRoleId);
        }
        $totalRecords = $totalRecordsQuery->count();

        // Количество записей с учётом фильтров
        $recordsFiltered = (clone $baseQuery)->count();

        // --- СОРТИРОВКА ДЛЯ DataTables ---

        $contractLookup = app(LatestUserContractLookup::class);
        $partnerId      = (int) $this->partnerId();

        // индекс колонки и направление asc|desc
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir         = $request->input('order.0.dir', 'asc');

        if ($orderColumnIndex !== null) {
            $columnKeys = $this->usersListColumnKeys(
                Auth::user(),
                $canViewContracts,
                $canViewUserSex,
                $canViewUserComment
            );
            $columnKey = $columnKeys[(int) $orderColumnIndex] ?? null;

            if ($columnKey !== null && $columnKey !== 'rownum' && $columnKey !== 'actions') {
                $this->applyUsersListSort(
                    $baseQuery,
                    $columnKey,
                    $orderDir,
                    $partnerId,
                    $contractLookup
                );
            } else {
                $baseQuery
                    ->orderBy('users.lastname', 'asc')
                    ->orderBy('users.name', 'asc');
            }
        } else {
            $baseQuery
                ->orderBy('users.lastname', 'asc')
                ->orderBy('users.name', 'asc');
        }

        // Пагинация DataTables
        $start  = $validated['start']  ?? 0;
        $length = $validated['length'] ?? 10;

        $users = $baseQuery
            ->with(['teams', 'parentProfile'])
            ->skip($start)
            ->take($length)
            ->get();

        $latestContractsByUser = collect();
        if ($canViewContracts) {
            $partnerId = (int) $this->partnerId();
            $userIds   = $users->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $latestContractsByUser = app(LatestUserContractLookup::class)
                ->forUserIds($partnerId, $userIds);
        }

        $data = $users->map(function (User $user) use ($canViewContracts, $canViewUserSex, $canViewUserComment, $latestContractsByUser) {
            $avatar = $user->image_crop
                ? asset('storage/avatars/' . $user->image_crop)
                : asset('img/default-avatar.png');

            $row = [
                'id'           => $user->id,
                'avatar'       => $avatar,
                'name'         => $user->full_name ?: 'Без имени',
                'parent'       => $user->parent_full_name,
                'teams'        => $this->teamUserSync->teamTitlesLabel($user) ?: '',
                'birthday'     => $user->birthday
                    ? Carbon::parse($user->birthday)->format('d.m.Y')
                    : '',
                'email'        => $user->email ?? '',
                'phone'        => $user->phone,
                'status_label' => $user->is_enabled ? 'Активен' : 'Неактивен',
                'is_enabled'   => (int) $user->is_enabled,
            ];

            if ($canViewUserSex) {
                $row['sex'] = UserSex::labelFor($user->sex);
            }

            if ($canViewUserComment) {
                $row['comment'] = (string) ($user->comment ?? '');
            }

            if ($canViewContracts) {
                $contract = $latestContractsByUser->get($user->id);

                if ($contract) {
                    $row['latest_contract'] = [
                        'url'          => route('contracts.show', $contract->id),
                        'status'       => $contract->status,
                        'status_label' => $contract->status_ru,
                    ];
                }
            }

            return $row;
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

        $isEnabled  = $request->boolean('is_enabled');
        $teamIds    = $validatedData['team_ids'] ?? [];
        unset($validatedData['team_ids'], $validatedData['team_id']);

        // Собираем итоговый массив данных для сервиса
        $data = array_merge($validatedData, [
            'partner_id'  => $partnerId,
            'is_enabled'  => $isEnabled,
            'team_ids'    => $teamIds,
        ]);

        $data = $this->applySchoolLeadHealthFlags($data, $schoolLeadId, $partnerId);

        $welcomeCredentialsPlainPassword = null;
        if ($schoolLeadId) {
            $welcomeCredentialsPlainPassword = $this->welcomeCredentialsService->generatePassword();
            $data['password'] = $welcomeCredentialsPlainPassword;
        }

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
            $partnerId,
            $schoolLeadId,
            $customInput,
            $editableSlugSet
        ) {
            // Создаём пользователя через доменный сервис
            $user = $this->service->store($data);
            $user->load('teams');

            $teamTitleForLog = $this->teamUserSync->teamTitlesLabel($user) ?: '-';

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

            // Роль (обязательна, но подстрахуемся)
            $role            = Role::find($data['role_id']);
            $roleNameOrLabel = $role->label ?? $role->name ?? '-';

            // Форматирование дат для лога
            $formatDateForLog = function (?string $value): string {
                return $value ? Carbon::parse($value)->format('d.m.Y') : '-';
            };

            $this->auditLogger->record(
                AuditEvent::UserCreated,
                AuditContext::make(sprintf(
                    "Имя: %s\nД.р: %s\nНачало: %s\nГруппа: %s\nEmail: %s\nАктивен: %s\nРоль: %s",
                    $user->full_name ?: "user#{$user->id}",
                    $formatDateForLog($data['birthday']   ?? null),
                    $formatDateForLog($data['start_date'] ?? null),
                    $teamTitleForLog,
                    $user->email,
                    ($data['is_enabled'] ?? false) ? 'Да' : 'Нет',
                    $roleNameOrLabel
                ))
                    ->withUser($user)
                    ->withTarget($user, $user->full_name ?: "user#{$user->id}")
            );
        });

        if (!$user) {
            abort(500, 'Не удалось создать пользователя.');
        }

        $responseMessage = 'Пользователь создан успешно';
        $mailResult = ['sent' => false, 'error' => null];
        if ($schoolLeadId && $welcomeCredentialsPlainPassword !== null) {
            $mailResult = $this->welcomeCredentialsService->send(
                $user,
                $welcomeCredentialsPlainPassword,
                $partnerId,
            );

            $recipientEmail = trim((string) ($user->email ?? ''));
            if ($mailResult['sent']) {
                $responseMessage = $recipientEmail !== ''
                    ? "Клиент создан. Письмо с данными для входа отправлено на {$recipientEmail}."
                    : 'Клиент создан. Письмо с данными для входа отправлено.';
            } else {
                $responseMessage = $recipientEmail !== ''
                    ? "Клиент создан, но не удалось отправить письмо на {$recipientEmail}."
                    : 'Клиент создан, но не удалось отправить письмо с данными для входа.';

                if (!empty($mailResult['error'])) {
                    Log::warning('[UserController@store] welcome credentials email failed', [
                        'user_id'    => $user->id,
                        'partner_id' => $partnerId,
                        'error'      => $mailResult['error'],
                    ]);
                }
            }
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
                'message' => $responseMessage,
                'user'    => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'birthday'   => $birthdayFormatted,
                    'start_date' => $startDateFormatted,
                    'team'       => $teamTitleForLog,
                    'email'      => $user->email ?? '',
                    'is_enabled' => $user->is_enabled ? 'Да' : 'Нет',
                ],
                'welcome_email_sent' => ($schoolLeadId && $mailResult['sent']) ? true : false,
            ], 200);
        }

        return redirect()->route('admin.user1');
    }

    public function sendWelcomeCredentials(Request $request, User $user)
    {
        $partnerId = $this->requirePartnerId();
        $user = $this->scopeByPartner(
            User::query()->with('role'),
            'users.partner_id'
        )
            ->whereKey($user->id)
            ->firstOrFail();

        $respondError = function (string $message, int $status = 422) use ($request) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['message' => $message], $status);
            }

            return redirect()
                ->route('admin.user1')
                ->withErrors(['welcome_credentials' => $message]);
        };

        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return $respondError('У ученика не указан email.');
        }

        if ($user->role?->name !== 'user') {
            return $respondError('Отправка доступна только для учеников.');
        }

        $mailResult = $this->welcomeCredentialsService->regenerateAndSend($user, $partnerId);

        if (!$mailResult['sent']) {
            $message = 'Не удалось отправить письмо'
                . (!empty($mailResult['error']) ? ': ' . $mailResult['error'] : '.');

            return $respondError($message, 500);
        }

        $targetLabel = $user->full_name ?: "user#{$user->id}";

        $this->auditLogger->record(
            AuditEvent::UserPasswordChangedByAdmin,
            AuditContext::make(sprintf(
                "Отправлен новый пароль на email: %s\nУченик: %s",
                $email,
                $targetLabel,
            ))
                ->withUser($user)
                ->withTarget($user, $targetLabel)
        );

        $message = "Новый пароль отправлен на {$email}.";

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()
            ->route('admin.user1')
            ->with('success', $message);
    }

    public function edit(User $user)
    {
        $partnerId = $this->requirePartnerId();
        $user = $this->scopeByPartner(
            User::query()->with(['fields', 'role', 'trainerProfile.teams', 'teams', 'parentProfile']),
            'users.partner_id'
        )
            ->whereKey($user->id)
            ->firstOrFail();

        $currentUser = $this->currentUser();
        $isSuperadmin = $this->isSuperAdmin();

        // 2) Доп. поля (тот же состав, что на странице списка / в модалке создания)
        $fieldsPayload = $this->buildUserFieldsPayloadForCurrentPartner();

        // 3) Системные + партнёрские роли (superadmin нельзя назначать через CRM)
        $systemRoles = Role::query()
            ->exceptSuperadmin()
            ->where('is_sistem', 1)
            ->when(!$isSuperadmin, fn($q) => $q->where('is_visible', 1))
            ->get();

        $partnerRoles = Role::query()
            ->exceptSuperadmin()
            ->whereHas('partners', fn($q) =>
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

        if (request()->ajax()) {
            $canViewUserSex = $currentUser?->can('users.sex') ?? false;
            $canViewUserComment = $currentUser?->can('users.comment') ?? false;

            // Преобразуем модель в массив
            $userArray = $user->toArray();

            // Нормализуем birthday под <input type="date">
            $userArray['birthday'] = $user->birthday
                ? $user->birthday->format('Y-m-d')
                : null;

            if (!$canViewUserSex) {
                unset($userArray['sex']);
            }

            if (!$canViewUserComment) {
                unset($userArray['comment']);
            }

            $trainerTeamIds = [];
            if ($user->role?->name === 'trainer' && $user->trainerProfile) {
                $trainerTeamIds = $user->trainerProfile->teams
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            }
            $userArray['trainer_team_ids'] = $trainerTeamIds;
            $userArray['team_ids'] = $this->teamUserSync->teamIdsForStudent($user);
            $userArray = array_merge($userArray, $user->parentFormFields());

            return response()->json([
                'user' => $userArray,
                'currentUser' => [
                    'role_id'      => $currentUser?->role_id,
                    'isSuperadmin' => $isSuperadmin,
                ],
                'targetIsSuperadmin' => $user->role?->name === 'superadmin',
                'fields' => $fieldsPayload,
                'roles'  => $rolesPayload,
                'ui' => [
                    'canViewUserSex' => $canViewUserSex,
                    'canViewUserComment' => $canViewUserComment,
                ],
            ]);
        }

        // если когда-нибудь захочешь не-AJAX — сюда можно добавить view/redirect
    }

    public function update(UpdateRequest $request, User $user)
    {
        $partnerId = $this->requirePartnerId();
        $user = $this->scopeByPartner(User::query(), 'users.partner_id')
            ->whereKey($user->id)
            ->firstOrFail();

        $actor = $this->currentUser();

        $user->load(['teams', 'role', 'parentProfile']);

        // Снимок старых значений (только то, что потенциально логируем)
        $old = [
            'name'       => (string) ($user->name ?? ''),
            'lastname'   => (string) ($user->lastname ?? ''),
            'email'      => (string) ($user->email ?? ''),
            'is_enabled' => (bool)   ($user->is_enabled ?? false),
            'birthday'   => $user->birthday, // Carbon|string|null — отформатируем ниже
            'team'       => $this->teamUserSync->teamTitlesLabel($user) ?: '-',
            'role'       => (string) ($user->role?->label ?: '-'),
            'phone'      => (string) ($user->phone ?? ''),
            'parent'     => $user->parent_full_name ?: '-',
            'is_individual_traits' => $user->is_individual_traits,
            'is_on_medical_register' => $user->is_on_medical_register,
            'is_with_disability' => $user->is_with_disability,
            'sex' => $user->sex,
            'comment' => $user->comment,
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

            $user->load(['teams', 'role', 'parentProfile']);

            $new = [
                'name'       => (string) ($user->name ?? ''),
                'lastname'   => (string) ($user->lastname ?? ''),
                'email'      => (string) ($user->email ?? ''),
                'is_enabled' => (bool)   ($user->is_enabled ?? false),
                'birthday'   => $user->birthday,
                'team'       => $this->teamUserSync->teamTitlesLabel($user) ?: '-',
                'role'       => (string) ($user->role?->label ?: '-'),
                'phone'      => (string) ($user->phone ?? ''),
                'parent'     => $user->parent_full_name ?: '-',
                'is_individual_traits' => $user->is_individual_traits,
                'is_on_medical_register' => $user->is_on_medical_register,
                'is_with_disability' => $user->is_with_disability,
                'sex' => $user->sex,
                'comment' => $user->comment,
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
                $changes[] = "Группы: {$old['team']} → {$new['team']}";
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

            if ($old['parent'] !== $new['parent']) {
                $changes[] = "Родитель: {$old['parent']} → {$new['parent']}";
            }

            if ($actor && $actor->can('users.other.update')) {
                $healthLabels = [
                    'is_individual_traits' => 'Индивидуальные особенности',
                    'is_on_medical_register' => 'Учёт у медспециалистов',
                    'is_with_disability' => 'Инвалидность',
                ];

                foreach ($healthLabels as $field => $label) {
                    if ($old[$field] !== $new[$field]) {
                        $changes[] = $label . ': '
                            . $this->formatTriStateBool($old[$field])
                            . ' → '
                            . $this->formatTriStateBool($new[$field]);
                    }
                }
            }

            if ($actor && $actor->can('users.sex') && ($old['sex'] ?? null) !== ($new['sex'] ?? null)) {
                $changes[] = 'Пол: '
                    . UserSex::labelFor($old['sex'] ?? null)
                    . ' → '
                    . UserSex::labelFor($new['sex'] ?? null);
            }

            if ($actor && $actor->can('users.comment') && (string) ($old['comment'] ?? '') !== (string) ($new['comment'] ?? '')) {
                $oldComment = trim((string) ($old['comment'] ?? ''));
                $newComment = trim((string) ($new['comment'] ?? ''));
                $changes[] = 'Комментарий: '
                    . ($oldComment !== '' ? $oldComment : '-')
                    . ' → '
                    . ($newComment !== '' ? $newComment : '-');
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

                $this->auditLogger->record(
                    AuditEvent::UserUpdated,
                    AuditContext::make(implode("\n", $changes))
                        ->withUser($user)
                        ->withTarget($user, $targetLabel !== ''
                            ? $targetLabel
                            : ($user->name ?? "user#{$user->id}"))
                );
            }
        });

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'message' => 'Пользователь успешно обновлён',
            ], 200);
        }

        return redirect()->route('admin.user1');
    }

    public function delete(User $user)
    {
        $user = $this->scopeByPartner(User::query(), 'users.partner_id')
            ->whereKey($user->id)
            ->firstOrFail();

        DB::transaction(function () use ($user) {
            $targetLabel = $user->full_name ?: "user#{$user->id}";

            $user->delete();

            $this->auditLogger->record(
                AuditEvent::UserDeleted,
                AuditContext::make("Удален пользователь: {$user->name}  ID: {$user->id}.")
                    ->withUser($user)
                    ->withTarget($user, $targetLabel)
                    ->withCreatedAt(now())
            );
        });

        return response()->json([
            'success' => 'Пользователь успешно удалён',
        ]);
    }

    //TODO: Сделать логирование только доп. полей, в которых были изменения. Сейчас в лог попадают все доп. поля.

    public function updatePassword(UpdatePasswordRequest $request, User $user)
    {
        $user = $this->scopeByPartner(User::query(), 'users.partner_id')
            ->whereKey($user->id)
            ->firstOrFail();

        $actor = $this->currentUser();

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

            $this->auditLogger->record(
                AuditEvent::UserPasswordChanged,
                AuditContext::make(sprintf(
                    'Пароль пользователя "%s" изменён администратором "%s".',
                    $user->name,
                    $actor?->name ?? 'system'
                ))
                    ->withUser($user)
                    ->withTarget($user, $targetLabel !== ''
                        ? $targetLabel
                        : ($user->name ?? "user#{$user->id}"))
            );
        });

        return response()->json(['success' => true]);
    }

    public function log(FilterRequest $request)
    {
        return $this->buildLogDataTable('user');
    }

    /**
     * Поиск родителей текущего партнёра (Select2 AJAX).
     */
    public function searchParents(Request $request)
    {
        $validated = $request->validate([
            'q'     => 'nullable|string|max:100',
            'id'    => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $partnerId = $this->requirePartnerId();
        $limit = (int) ($validated['limit'] ?? 20);

        $query = ParentProfile::query()
            ->where('partner_id', $partnerId);

        if (!empty($validated['id'])) {
            $query->whereKey((int) $validated['id']);
        } else {
            $term = trim((string) ($validated['q'] ?? ''));
            if ($term !== '') {
                $like = '%' . $term . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('lastname', 'like', $like)
                        ->orWhere('firstname', 'like', $like)
                        ->orWhere('middlename', 'like', $like);
                });
            }
        }

        $parents = $query
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->limit($limit)
            ->get();

        return response()->json([
            'results' => $parents->map(static function (ParentProfile $parent) {
                $label = trim($parent->full_name);

                return [
                    'id'                => $parent->id,
                    'text'              => $label !== '' ? $label : ('Родитель #' . $parent->id),
                    'parent_lastname'   => $parent->lastname,
                    'parent_firstname'  => $parent->firstname,
                    'parent_middlename' => $parent->middlename,
                    'parent_passport'   => $parent->passport,
                    'parent_passport_issued' => $parent->passport_issued,
                    'parent_address'    => $parent->address,
                    'parent_phone'      => $parent->phone,
                    'parent_email'      => $parent->email,
                ];
            })->values(),
        ]);
    }

    private function formatTriStateBool(?bool $value): string
    {
        if ($value === null) {
            return 'Не указано';
        }

        return $value ? 'Да' : 'Нет';
    }

    /**
     * @return list<string>
     */
    private function usersListColumnKeys(
        ?User $actor,
        bool $canViewContracts,
        bool $canViewUserSex,
        bool $canViewUserComment,
    ): array {
        $keys = ['rownum', 'avatar', 'name', 'parent'];

        if ($canViewContracts) {
            $keys[] = 'contract';
        }

        $keys[] = 'teams';
        $keys[] = 'birthday';

        if ($canViewUserSex) {
            $keys[] = 'sex';
        }

        if ($canViewUserComment) {
            $keys[] = 'comment';
        }

        return array_merge($keys, ['email', 'phone', 'status_label', 'actions']);
    }

    private function applyUsersListSort(
        $query,
        string $columnKey,
        string $orderDir,
        int $partnerId,
        LatestUserContractLookup $contractLookup,
    ): void {
        match ($columnKey) {
            'avatar' => $query->orderBy('users.image_crop', $orderDir),
            'name' => $query
                ->orderBy('users.lastname', $orderDir)
                ->orderBy('users.name', $orderDir),
            'parent' => $query
                ->leftJoin('parents', 'parents.id', '=', 'users.parent_id')
                ->select('users.*')
                ->orderBy('parents.lastname', $orderDir)
                ->orderBy('parents.firstname', $orderDir),
            'contract' => $contractLookup->applyUsersListSortByLatestContractStatus(
                $query,
                $partnerId,
                $orderDir
            ),
            'teams' => $query
                ->leftJoinSub(
                    DB::table('team_user')
                        ->join('teams', 'teams.id', '=', 'team_user.team_id')
                        ->select(
                            'team_user.user_id',
                            DB::raw('MIN(teams.title) as teams_sort_title')
                        )
                        ->where('team_user.partner_id', $partnerId)
                        ->groupBy('team_user.user_id'),
                    'user_teams_sort',
                    'user_teams_sort.user_id',
                    '=',
                    'users.id'
                )
                ->select('users.*')
                ->orderBy('user_teams_sort.teams_sort_title', $orderDir),
            'birthday' => $query->orderBy('users.birthday', $orderDir),
            'sex' => $query->orderBy('users.sex', $orderDir),
            'comment' => $query->orderBy('users.comment', $orderDir),
            'email' => $query->orderBy('users.email', $orderDir),
            'phone' => $query->orderBy('users.phone', $orderDir),
            'status_label' => $query->orderBy('users.is_enabled', $orderDir),
            default => $query
                ->orderBy('users.lastname', 'asc')
                ->orderBy('users.name', 'asc'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applySchoolLeadHealthFlags(array $data, ?int $schoolLeadId, int $partnerId): array
    {
        if (!$schoolLeadId) {
            return $data;
        }

        $studentRoleId = Role::query()->where('name', 'user')->value('id');
        if (!$studentRoleId || (int) ($data['role_id'] ?? 0) !== (int) $studentRoleId) {
            return $data;
        }

        $lead = SchoolLead::query()
            ->where('id', $schoolLeadId)
            ->where('partner_id', $partnerId)
            ->whereNull('user_id')
            ->first();

        if (!$lead) {
            return $data;
        }

        foreach (['is_individual_traits', 'is_on_medical_register', 'is_with_disability'] as $field) {
            if (!array_key_exists($field, $data)) {
                $data[$field] = $lead->{$field};
            }
        }

        if (
            empty($data['team_ids'])
            && $lead->team_id
            && empty($data['team_id'])
        ) {
            $data['team_ids'] = [(int) $lead->team_id];
        }

        return $data;
    }

}