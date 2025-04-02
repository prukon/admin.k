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
//        $this->middleware('role:admin,superadmin');
    }

    public function index(FilterRequest $request)
    {
        $data = $request->validated();
//        dd($data);
        $query = User::query();
        $fields = UserField::all();
        $user = Auth::user();

        $roles = Role::all(); // Все роли


        if (isset($data['id'])) {
    $query->where('id', $data['id']);
}

        $filter = app()->make(UserFilter::class, ['queryParams'=> array_filter($data)]);

        $allUsers = User::filter($filter)
            ->orderBy('name', 'asc') // сортировка по полю name по возрастанию
            ->paginate(20);
        $allTeams = Team::all();

        return view("admin.user", compact(
            "allUsers" ,
            "allTeams",
            'fields',
            'user',
            'roles'

        ));
    }

    public function create()
    {
//        $allTeamsCount = Team::all()->count();
//        $allUsersCount  = User::all()->count();

        $allTeams = Team::All();
        $allRoles = Role::all();  // <-- Важно! Берём все роли из БД для формы

        return view("admin.user.create", compact("allTeams", 'allRoles'));     }

    public function store(StoreRequest $request)
    {
        // Валидация входных данных
        $data = $request->validated();
        $data['partner_id'] = auth()->user()->partner_id;

        // Создание пользователя и логгирование в транзакции
        $user = null; // Создаем переменную, чтобы хранить созданного пользователя
        DB::transaction(function () use (&$user, $data) {
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
            ]);
        });

        // Если запрос AJAX, возвращаем JSON-ответ
        if ($request->ajax()) {
            // Находим группу по ID, если она существует (повторно, чтобы передать в ответе)
            $team = Team::find($data['team_id']);
            $teamName = $team ? $team->title : '-';

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
        }

        // Обычный редирект при не-AJAX запросе
        return redirect()->route('admin.user.index');
    }

    public function edit(User $user)
    {
        $currentUser = auth()->user(); // или $request->user()

        $allTeams = Team::All();
        $fields = UserField::all(); // Получаем пользовательские поля (например, теги)
        // Загрузка связи fields
        $user->load('fields');
        $roles = Role::all();


        return response()->json([
            'user' => $user,
            'currentUser' => $currentUser,
            'fields' => $fields,
            'teams' => $allTeams, // Отправляем также список команд, если нужно
            'roles'
        ]);
    }

    public function update(AdminUpdateRequest $request, User $user)
    {
        $authorId = auth()->id(); // Авторизованный пользователь
        $oldData = User::where('id', $user->id)->first();
        $oldTeam = Team::find($user->team_id);
        $oldTeamName = $oldTeam ? $oldTeam->title : '-';
        $oldRoleName = $oldData->role ? $oldData->role->label : '-';

        $data = $request->validated();

        // Получаем старые значения пользовательских полей
        $oldCustomData = UserFieldValue::where('user_id', $user->id)->get()->keyBy('field_id')->toArray();

        DB::transaction(function () use ($user, $authorId, $data, $oldData, $oldTeamName, $oldCustomData, $oldRoleName) {
            // Обновление пользователя с помощью сервиса
            $this->service->update($user, $data);

            // Обработка команды для новой команды
            $team = Team::find($data['team_id']);
            $teamName = $team ? $team->title : '-';


            // Получаем новую роль
            $newRoleName = '-';
            if (isset($data['role_id'])) {
                $role = \App\Models\Role::find($data['role_id']);
                $newRoleName = $role ? $role->label : '-';
            }


            // Логирование изменений в кастомных полях
            $customLogDescription = '';
            if (array_key_exists('custom', $data) && is_array($data['custom'])) {
                foreach ($data['custom'] as $slug => $value) {
                    $userField = UserField::where('slug', $slug)->first();

                    if ($userField) {
                        $oldValue = $oldCustomData[$userField->id]['value'] ?? null;
                        if ($oldValue !== $value) {
                            $customLogDescription .= sprintf(
                                "\n%s: %s -> %s",
                                $userField->name, // Используем название поля
                                $oldValue ?? '-',
                                $value !== null ? $value : '-' // Новое значение или "-"
                            );
                        }
                    }
                }
            }

            // Создание лога обновления данных пользователя с добавлением изменений в кастомных полях
//            MyLog::create([
//                'type' => 2, // Лог для обновления юзеров
//                'action' => 22, // Лог для обновления учетной записи
//                'author_id' => $authorId,
//                'description' => sprintf(
//                    "Старые:\n Имя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s.\nНовые:\nИмя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s%s",
//                    $oldData->name,
//                    isset($oldData->birthday) ? Carbon::parse($oldData->birthday)->format('d.m.Y') : '-',
//                    isset($oldData->start_date) ? Carbon::parse($oldData->start_date)->format('d.m.Y') : '-',
//                    $oldTeamName,
//                    $oldData->email,
//                    $oldData->is_enabled ? 'Да' : 'Нет',
//                    $data['name'],
//                    isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-',
//                    isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-',
//                    $teamName,
//                    $data['email'],
//                    $data['is_enabled'] ? 'Да' : 'Нет',
//                    $customLogDescription // Добавляем описание изменений кастомных полей
//                ),
//                'created_at' => now(),
//            ]);

            // Создаём лог
            MyLog::create([
                'type' => 2,     // Лог для обновления юзеров
                'action' => 22,  // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => sprintf(
                    "Старые:\nИмя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s, Роль: %s.\n".
                    "Новые:\nИмя: %s, Д.р: %s, Начало: %s, Группа: %s, Email: %s, Активен: %s, Роль: %s%s",
                    $oldData->name,
                    isset($oldData->birthday) ? Carbon::parse($oldData->birthday)->format('d.m.Y') : '-',
                    isset($oldData->start_date) ? Carbon::parse($oldData->start_date)->format('d.m.Y') : '-',
                    $oldTeamName,
                    $oldData->email,
                    $oldData->is_enabled ? 'Да' : 'Нет',
                    $oldRoleName,   // <-- старая роль

                    $data['name'],
                    isset($data['birthday']) ? Carbon::parse($data['birthday'])->format('d.m.Y') : '-',
                    isset($data['start_date']) ? Carbon::parse($data['start_date'])->format('d.m.Y') : '-',
                    $teamName,
                    $data['email'],
                    $data['is_enabled'] ? 'Да' : 'Нет',
                    $newRoleName,   // <-- новая роль
                    $customLogDescription
                ),
                'created_at' => now(),
            ]);


        });

        return response()->json([
            'message' => 'Пользователь успешно обновлен',
            'data' => $data,
        ]);
    }

    public function delete(User $user)
    {


        // Проверяем, если пользователь не существует
        if (!$user) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        }

        $authorId = auth()->id(); // Авторизованный пользователь


        DB::transaction(function () use ($user, $authorId) {
            // Удаление пользователя
            $user->delete();

            // Логирование удаления
            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 24,
                'author_id' => $authorId,
                'description' => "Удален пользователь: {$user->name}  ID: {$user->id}.",
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => 'Пользователь успешно удалён']);

//        return redirect()->route('admin.user.index');

    }

    public function storeFields(Request $request)
    {
        // Получаем все поля, которые были отправлены
        $fields = $request->input('fields', []);
        $authorId = auth()->id(); // Получаем ID текущего пользователя

        DB::transaction(function () use ($fields, $request, $authorId) {
            // Сначала найдём все существующие ID полей в БД
            $existingFieldIds = UserField::pluck('id')->toArray();
            // Из входящих данных достанем ID, которые есть (если поле 'id' не пустое)
            $submittedFieldIds = array_filter(array_column((array)$fields, 'id'));

            // Находим все ID, которых нет в запросе (значит, их удалили)
            $fieldsToDelete = array_diff($existingFieldIds, $submittedFieldIds);

            $fieldsToDeleteRecords = UserField::whereIn('id', $fieldsToDelete)->get();
            if (!empty($fieldsToDelete)) {
                UserField::whereIn('id', $fieldsToDelete)->delete();

                // Логируем удаление
                foreach ($fieldsToDeleteRecords as $deletedField) {
                    MyLog::create([
                        'type' => 2,
                        'action' => 210,
                        'author_id' => $authorId,
                        'description' => "Удалено поле: {$deletedField->name}. ID: {$deletedField->id}",
                        'created_at' => now(),
                    ]);
                }
            }

            // Теперь обрабатываем те поля, которые пришли
            foreach ($fields as $key => $field) {
                $fieldId = $field['id'] ?? null;
                $fieldName = $field['name'] ?? null;
                $fieldType = $field['field_type'] ?? null;

                // Собираем роли
                // Ожидаем, что во входных данных это будет fields[$key]['permissions_id'],
                // где хранится массив ID (например [1, 3])
                $permissionsId = $field['permissions_id'] ?? [];

                // Для валидации проверим существование таких ролей
                // Пример: каждая роль должна существовать в таблице roles
                $request->validate([
                    "fields.$key.name" => 'required|string|max:255',
                    "fields.$key.field_type" => 'required|string|in:string,text,select',
                    "fields.$key.permissions_id" => 'array',
                    "fields.$key.permissions_id.*" => 'exists:roles,id'
                ]);

                // Генерируем slug
                $slug = Str::slug($fieldName);

                // Если есть $fieldId, то это редактирование
                if ($fieldId) {
                    $userField = UserField::findOrFail($fieldId);

                    // Смотрим, какие изменения были
                    $changes = [];

                    if ($userField->name !== $fieldName) {
                        $changes[] = "имя с '{$userField->name}' на '{$fieldName}'";
                    }
                    if ($userField->field_type !== $fieldType) {
                        $changes[] = "тип с '{$userField->field_type}' на '{$fieldType}'";
                    }
                    // Сравниваем старые и новые permissions_id
                    $oldPermissionsId = $userField->permissions_id ?? [];
                    $newPermissionsId = $permissionsId;

                    if ($oldPermissionsId != $newPermissionsId) {
                        $changes[] = "разрешения с '"
                            . implode(',', $oldPermissionsId)
                            . "' на '"
                            . implode(',', $newPermissionsId)
                            . "'";
                    }

                    // Если что-то изменилось, обновляем
                    if (!empty($changes)) {
                        $userField->update([
                            'name' => $fieldName,
                            'slug' => $slug,
                            'field_type' => $fieldType,
                            'permissions_id' => $permissionsId,
                        ]);

                        // Логируем
                        MyLog::create([
                            'type' => 2,
                            'action' => 210,
                            'author_id' => $authorId,
                            'description' => "Обновлено поле: {$userField->name}. ID: {$fieldId}. Изменения: "
                                . implode(', ', $changes),
                            'created_at' => now(),
                        ]);
                    }
                } else {
                    // Создание нового поля
                    $newField = UserField::create([
                        'name' => $fieldName,
                        'slug' => $slug,
                        'field_type' => $fieldType,
                        'permissions_id' => $permissionsId
                    ]);

                    // Логируем
                    MyLog::create([
                        'type' => 2,
                        'action' => 210,
                        'author_id' => $authorId,
                        'description' => "Создано новое поле {$fieldName}. ID: {$newField->id}",
                        'created_at' => now(),
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Поля успешно сохранены']);
    }

    public function updatePassword(Request $request, $id)
    {

        $request->validate([
            'password' => 'required|min:8',
        ]);
//        $currentUser = Auth::user();
        $authorId = auth()->id(); // Авторизованный пользователь
        $user = User::findOrFail($id);

        DB::transaction(function () use ($user, $authorId, $request) {

            $user->password = Hash::make($request->password);
            $user->save();

            MyLog::create([
                'type' => 2, // Лог для обновления юзеров
                'action' => 26, // Лог для обновления учетной записи
                'author_id' => $authorId,
                'description' => ($user->name . " изменил пароль."),
                'created_at' => now(),
            ]);
        });
        return response()->json(['success' => true]);
    }

    public function log(FilterRequest $request)
    {
        $logs = MyLog::with('author')
            ->where('type', 2) // User логи
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