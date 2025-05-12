<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\UserFilter;
use App\Http\Requests\User\FilterRequest;
use App\Http\Requests\User\StoreRequest;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\UserField;
use Illuminate\Support\Facades\Auth; // Модель для работы с таблицей тегов
use Illuminate\Support\Facades\DB;
use App\Models\MyLog;
use App\Http\Requests\User\AdminUpdateRequest;
//use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

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




//    $rolesQuery = Role::query()
//        ->where('is_sistem', 1)// системные
//        ->orWhereHas('partners', function ($q) use ($partnerId) {
//            $q->where('partner_role.partner_id', $partnerId);
//        });
//    if (!$isSuperadmin) {
//        $rolesQuery->where('is_visible', 1);
//    }
//    $roles = $rolesQuery
//        ->orderBy('order_by')
//        ->get();




    $rolesQuery = Role::query();
// если не супер-админ — сразу фильтруем по видимости
if (! $isSuperadmin) {
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
        // Валидация входных данных
        $data = $request->validated();
        $partnerId = app('current_partner')->id;
        $data['partner_id'] = $partnerId;

        // Создание пользователя и логгирование в транзакции
        $user = null; // Создаем переменную, чтобы хранить созданного пользователя
        DB::transaction(function () use (&$user, $data, $partnerId) {
            // Сохраняем пользователя через сервис и получаем объект созданного пользователя
            $user = $this->service->store($data);

            // Получаем ID авторизованного пользователя
            $authorId = auth()->id(); // Авторизованный пользователь

            // Находим группу по ID, если она существует
            $team = Team::find($data['team_id']);
            $teamName = $team ? $team->title : '-';

            $role = \App\Models\Role::find($data['role_id']);
            $roleNameOrLabel = $role ? $role->label : '-'; // или $role->name, смотря что вы хотите логировать


            // Логируем создание пользователя
            MyLog::create([
                'type' => 2,    // Лог для юзеров
                'action' => 21, // Лог для создания учетной записи
                'author_id' => $authorId,
                'description' => sprintf(
                    "Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s, Роль: %s",
                    $data['name'],
                    isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-',
                    isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-',
                    $teamName,
                    $data['email'],
                    $data['is_enabled'] ? 'Да' : 'Нет',
                    $roleNameOrLabel
                ),
                'created_at' => now(),
                'partner_id' => $partnerId
            ]);
        });


        if ($request->ajax()) {
            try {
                // Основная логика создания пользователя
                // Например, создание записи в базе данных:
                // $user = User::create($data);

                // Находим группу по ID, если она существует (повторно, чтобы передать в ответе)
                $team = Team::find($data['team_id']);
                $teamName = $team ? $team->title : '-';

                // Если всё прошло успешно, возвращаем ответ с данными пользователя
                return response()->json([
                    'message' => 'Пользователь создан успешно',
                    'user' => [
                        'id' => $user->id,
                        'name' => $data['name'],
                        'birthday' => isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-',
                        'start_date' => isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-',
                        'team' => $teamName,
                        'email' => $data['email'],
                        'is_enabled' => $data['is_enabled'] ? 'Да' : 'Нет',
                    ]
                ], 200);
            } catch (\Exception $e) {
                // При возникновении ошибки возвращаем сообщение об ошибке
                return response()->json([
                    'message' => 'Ошибка: ' . $e->getMessage()
                ], 500);
            }
        }

    }

    public function edit(User $user)
    {
        // 1) Контекст
        $partnerId    = app('current_partner')->id;
        $currentUser  = auth()->user();
        $userRoleName = $currentUser->role?->name;
    $isSuperadmin = $userRoleName === 'superadmin';

    // 2) Загружаем UserField вместе их ролями
    $fields = UserField::with('roles')
        ->where('partner_id', $partnerId)
        ->get();

    // 3) Собираем payload для полей
    $fieldsPayload = $fields->map(function (UserField $f) {
        return [
            'id'         => $f->id,
            'name'       => $f->name,
            'slug'       => $f->slug,
            'field_type' => $f->field_type,
            'roles'      => $f->roles->pluck('id')->map(fn($i)=>(int)$i)->all(),
        ];
    })->all();

    // 4) Системные + партнёрские роли
    $systemRoles = Role::where('is_sistem', 1)
        ->when(! $isSuperadmin, fn($q)=> $q->where('is_visible',1))
        ->get();

    $partnerRoles = Role::whereHas('partners', fn($q)=>
            $q->where('partner_role.partner_id', $partnerId)
        )
        ->when(! $isSuperadmin, fn($q)=> $q->where('is_visible',1))
        ->get();

    $allRoles = $systemRoles
        ->merge($partnerRoles)
        ->unique('id')
        ->sortBy('order_by')
        ->values();

    $rolesPayload = $allRoles->map(fn(Role $r)=> [
        'id'     => $r->id,
        'name'   => $r->name,
        'label'  => $r->label,
        'system' => (bool)$r->is_sistem,
    ])->all();

    // 5) Загружаем связи user->fields (pivot value)
    $user->load('fields');

    // 6) Возвращаем JSON, добавив флаг isSuperadmin
    return response()->json([
        'user'         => $user,
        'currentUser'  => [
            'role_id'       => $currentUser->role_id,
            'isSuperadmin'  => $isSuperadmin,
        ],
        'fields'       => $fieldsPayload,
        'roles'        => $rolesPayload,
    ]);
}

    public function update(AdminUpdateRequest $request, User $user)
    {
        $partnerId   = app('current_partner')->id;
        $authorId    = auth()->id();
        $oldUser     = User::find($user->id);
        $oldTeam     = Team::find($oldUser->team_id);
        $oldTeamName = $oldTeam?->title ?: '-';
        $oldRoleName = $oldUser->role?->label ?: '-';

        // Забираем входные данные без поля start_date
        $data = $request->validated();
        // AdminUpdateRequest уже не будет валидировать start_date

        // Сохраним старые значения кастомных полей
        $oldCustom = UserFieldValue::where('user_id', $user->id)
            ->get()
            ->keyBy('field_id')
            ->map(fn($uv) => $uv->value)
            ->all();

        DB::transaction(function () use (
            $user, $data, $oldUser, $oldTeamName, $oldCustom,
            $oldRoleName, $authorId, $partnerId
        ) {
            // 1. Обновляем основные свойства пользователя (без start_date)
            $this->service->update($user, $data);

            // 2. Подготовка для логирования
            $newTeam     = Team::find($data['team_id']);
            $newTeamName = $newTeam?->title ?: '-';

            $newRole     = Role::find($data['role_id'] ?? 0);
            $newRoleName = $newRole?->label ?: '-';

            // 3. Логируем изменения профиля
            $customLog = '';
            if (!empty($data['custom']) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $val) {
                    $field = UserField::where('slug', $slug)->first();
                    if (!$field) {
                        \Log::warning("update(): UserField not found by slug '{$slug}'");
                        continue;
                    }
                    $oldVal = $oldCustom[$field->id] ?? '-';
                    if ((string)$oldVal !== (string)$val) {
                        $customLog .= "\n{$field->name}: {$oldVal} -> {$val}";
                    }
                }
            }

            MyLog::create([
                    'type'        => 2,
                    'action'      => 22,
                    'author_id'   => $authorId,
                    'partner_id'  => $partnerId,
                    'description' => sprintf(
                        "Старые:\nИмя: %s, Д.р: %s, Группа: %s, Email: %s, Активен: %s, Роль: %s.\n" .
                        "Новые:\nИмя: %s, Д.р: %s, Группа: %s, Email: %s, Активен: %s, Роль: %s%s",
                        $oldUser->name,
                        $oldUser->birthday?->format('d.m.Y') ?: '-',
                    $oldTeamName,
                    $oldUser->email,
                    $oldUser->is_enabled ? 'Да' : 'Нет',
                    $oldRoleName,

                    $data['name'],
                    $data['birthday']
                        ? Carbon::parse($data['birthday'])->format('d.m.Y')
                        : '-',
                    $newTeamName,
                    $data['email'],
                    $data['is_enabled'] ? 'Да' : 'Нет',
                    $newRoleName,
                    $customLog
                ),
                'created_at'  => now(),
            ]);

            // 4. Сохраняем значения custom‑полей
            if (!empty($data['custom']) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $val) {
                    $field = UserField::where('slug', $slug)->first();
                    if (!$field) {
                        \Log::warning("update(): UserField not found by slug '{$slug}'");
                        continue;
                    }
                    UserFieldValue::updateOrCreate(
                        [
                            'user_id'  => $user->id,
                            'field_id' => $field->id,
                        ],
                        ['value' => $val]
                    );
                    \Log::info(
                        "update(): Saved custom field — user_id={$user->id}, " .
                        "field_id={$field->id}, value=" . json_encode($val)
                    );
                }
            }
        });

        return response()->json([
            'message' => 'Пользователь успешно обновлен',
            'data'    => $data,
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

    public function updatePassword(Request $request, $id)
    {
        $partnerId = app('current_partner')->id;
        $request->validate([
            'password' => 'required|min:8',
        ]);
//        $currentUser = Auth::user();
        $authorId = auth()->id(); // Авторизованный пользователь
        $user = User::findOrFail($id);

        DB::transaction(function () use ($user, $authorId, $request, $partnerId) {

            $user->password = Hash::make($request->password);
            $user->save();

            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 26, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => ($user->name . " изменил пароль."),
                'created_at' => now(),
                'partner_id' => $partnerId
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

}