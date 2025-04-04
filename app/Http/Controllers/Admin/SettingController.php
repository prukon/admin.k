<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
//use App\Models\Log;
use App\Models\MyLog;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\SocialItem;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\UserPrice;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use App\Models\Role;
use App\Models\Permission;

class SettingController extends Controller
{

    //ВКЛАДКА НАСТРОЙКИ
    //Страница Настройки
    public function showSettings()
    {
        $setting = Setting::where('name', 'textForUsers')->first();
        $textForUsers = $setting ? $setting->text : null;

        return view('admin.setting.setting',
            ['activeTab' => 'setting'],
            compact(
                "textForUsers")
        );


    }

    //AJAX Активность регистрации
    public function registrationActivity(Request $request)
    {
        $isRegistrationActivity = $request->query('isRegistrationActivity');
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($isRegistrationActivity, $authorId) {


//        if ($isRegistrationActivity) {
            $isRegistrationActivity = filter_var($isRegistrationActivity, FILTER_VALIDATE_BOOLEAN);
            // Обновляем или создаем запись в таблице team_prices
            Setting::updateOrCreate(
                [
                    'name' => "registrationActivity",
                ],
                [
                    'status' => $isRegistrationActivity
                ]
            );
//        }
            if ($isRegistrationActivity == 1) {
                $isRegistrationActivityValue = "Вкл.";
            } else {
                $isRegistrationActivityValue = "Выкл.";
            }

            // Логирование изменения пароля
            MyLog::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,
                'description' => ("Включение регистрации в сервисе: " . $isRegistrationActivityValue),
                'created_at' => now(),
            ]);

        });
        return response()->json([
            'success' => true,
            'isRegistrationActivity' => $isRegistrationActivity,

        ]);
    }

    //AJAX Текст сообщения для юзеров
    public function textForUsers(Request $request)
    {
        // Получаем данные из тела запроса
        $data = json_decode($request->getContent(), true);
        $textForUsers = $data['textForUsers'] ?? null;
        $authorId = auth()->id(); // Авторизованный пользователь

        DB::transaction(function () use ($textForUsers, $authorId) {

            Setting::updateOrCreate(
                [
                    'name' => "textForUsers",
                ],
                [
                    'text' => $textForUsers
                ]
            );

            MyLog::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,
                'description' => ("Изменение текста уведомления: " . $textForUsers),
                'created_at' => now(),
            ]);
        });
        return response()->json([
            'success' => true,
            'textForUsers' => $textForUsers,
        ]);
    }

    //Сохранение меню в шапке
    public function saveMenuItems(Request $request)
    {
        $errors = [];
        $validatedData = [];
        $authorId = auth()->id(); // Авторизованный пользователь
        $oldItems = []; // Старые данные пунктов меню
        $newItems = []; // Новые данные пунктов меню

        foreach ($request->input('menu_items', []) as $key => $data) {
            // Создаем валидатор для каждого элемента меню
            $validator = \Validator::make($data, [
                'name' => ['required', 'max:20', 'regex:/^[\pL\pN\s]+$/u'], // Буквы, цифры и пробелы
                'link' => ['nullable', 'regex:/^(\/[\S]*|https?:\/\/[^\s]+)$/'],
            ], [
                'name.required' => 'Заполните название.',
                'name.max' => 'Название не может быть длиннее 20 символов.',
                'name.regex' => 'Название не может содержать спецсимволы.',
                'link.regex' => 'Введите корректный URL.',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["menu_items[$key][$field]"] = $messages;
                }
            } else {
                // Приведение target_blank к числовому значению
                $data['target_blank'] = !empty($data['target_blank']) ? 1 : 0;
                $validatedData[$key] = $data;
            }
        }

        if (!empty($errors)) {
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($validatedData, $authorId, $request, &$oldItems, &$newItems) {

            foreach ($validatedData as $key => $data) {
                if (is_numeric($key)) {
                    $menuItem = MenuItem::find($key);
                    if ($menuItem) {
                        // Формируем старое значение с учетом target_blank
                        $oldTargetBlank = $menuItem->target_blank ? ", Открывать в новой вкладке" : "";
                        $oldItems[] = "\"{$menuItem->name}, {$menuItem->link}{$oldTargetBlank}\"";

                        // Обновляем элемент и формируем новое значение
                        $menuItem->update([
                            'name' => $data['name'],
                            'link' => $data['link'] ?: '',
                            'target_blank' => $data['target_blank'],
                        ]);

                        $newTargetBlank = $data['target_blank'] ? ", Открывать в новой вкладке" : "";
                        $newItems[] = "\"{$data['name']}, {$data['link']}{$newTargetBlank}\"";
                    }
                } else {
                    $newItem = MenuItem::create([
                        'name' => $data['name'],
                        'link' => $data['link'] ?: '',
                        'target_blank' => $data['target_blank'],
                    ]);

                    // Для новых элементов добавляем только новое значение
                    $newTargetBlank = $data['target_blank'] ? ", Открывать в новой вкладке" : "";
                    $newItems[] = "\"{$data['name']}, {$data['link']}{$newTargetBlank}\"";
                }
            }

            if ($request->has('deleted_items')) {
                MenuItem::whereIn('id', $request->input('deleted_items'))->delete();
                foreach ($request->input('deleted_items') as $deletedId) {
                    $oldItems[] = "Удалён пункт меню с ID: $deletedId";
                }
            }

            // Формируем читаемый лог
            $description = "Изменены пункты меню:\n" . implode("\n", $oldItems) . "\nна:\n" . implode("\n", $newItems);

            MyLog::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    //Сохранение соц. меню в шапке
    public function saveSocialItems(Request $request)
    {
        $errors = [];
        $validatedData = [];
        $authorId = auth()->id(); // Авторизованный пользователь
        $oldItems = []; // Массив для хранения старых значений
        $newItems = []; // Массив для хранения новых значений

        foreach ($request->input('social_items', []) as $key => $data) {
            // Валидация поля `link` как URL
            $validator = \Validator::make($data, [
                'link' => ['nullable', 'regex:/^(\/[\S]*|https?:\/\/[^\s]+)$/'],
            ], [
                'link.regex' => 'Введите корректный URL.',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["social_items[$key][$field]"] = $messages;
                }
            } else {
                $validatedData[$key] = $data;
            }
        }

        if (!empty($errors)) {
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($authorId, $validatedData, &$oldItems, &$newItems) {
            foreach ($validatedData as $key => $data) {
                $socialItem = SocialItem::find($key);
                if ($socialItem) {
                    // Формируем старое значение
                    $oldItems[] = "\"{$socialItem->name}, {$socialItem->link}\"";

                    // Обновляем элемент и формируем новое значение
                    $socialItem->update([
                        'name' => $data['name'], // Поле `name` сохраняется без валидации
                        'link' => $data['link'] ?: '',
                    ]);

                    $newItems[] = "\"{$data['name']}, {$data['link']}\"";
                }
            }

            // Формируем читаемый лог
            $description = "Изменены социальные элементы:\n" . implode("\n", $oldItems) . "\nна:\n" . implode("\n", $newItems);

            MyLog::create([
                'type' => 1,
                'action' => 70,
                'author_id' => $authorId,
                'description' => $description,
                'created_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    //Журнал логов
    public function logsAllData(Request $request)
    {
        $logs = MyLog::with('author')
//            ->where('type', 1) // Добавляем условие для фильтрации по type
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

                    11 => 'Изм. цен во всех группах (Применить слева)', //Применить слева
                    12 => 'Инд. изм. цен (Применить справа)', //Применить справа
                    13 => 'Изм. цен в одной группе  (ок)', //Кнопка "ок"

                    21 => 'Создание пользователя',
                    22 => 'Обновление учетной записи в пользователях',
                    23 => 'Обновление учетной записи',
                    24 => 'Удаление пользователя в пользователях',
                    25 => 'Изменение пароля (админ)',
                    26 => 'Изменение пароля',
                    27 => 'Изменение аватара (админ)',
                    28 => 'Изменение аватара',
                    29 => 'Удаление аватара',

                    210 => 'Изменение доп полей пользователя',


                    31 => 'Создание группы',
                    32 => 'Изменение группы',
                    33 => 'Удаление группы',

                    40 => 'Авторизация',

                    50 => 'Платежи',

                    60 => 'Расписание',

                    70 => 'Изменение настроек',

                    710 => 'Создание роли',
                    720 => 'Изменение роли',
                    730 => 'Удаление роли',

                    80 => 'Изменение партнера',

                    90 => 'Создание статуса расписания',
                    91 => 'Изменение статуса расписания',
                    92 => 'Удаление статуса расписания',


                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип (setting)';
            })
            ->make(true);
    }


    //ВКЛАДКА РОЛИ
    //Страница права пользователей
    public function showRules()
    {

        // Получаем все роли
        $roles = Role::all();
        $roles = Role::with('permissions')->get();

        // Получаем все права (permissions) с сортировкой по id или как вам удобнее
        $permissions = Permission::with('roles')->orderBy('sort_order')->get();


        // Какую вкладку активной отображать (исходя из вашего кода)
        $activeTab = 'rule';

        return view('admin.setting.rule', compact('roles', 'permissions', 'activeTab'));

    }

    //Изменение прав пользователей
    public function togglePermission(Request $request)
    {
        $roleId = $request->input('role_id');
        $permissionId = $request->input('permission_id');
        $value = $request->input('value'); // true/false

        /** @var Role $role */
        $role = Role::findOrFail($roleId);
        /** @var Permission $permission */
        $permission = Permission::findOrFail($permissionId);

        if ($value == 'true') {
            // Если чекбокс включили, значит нужно добавить право роли
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        } else {
            // Если чекбокс выключили, удаляем право у роли
            $role->permissions()->detach($permission->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Обновление прошло успешно',
        ]);
    }

    //* Метод для создания новой роли (AJAX).
    public function createRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Определим максимальное значение order_by
        $maxOrderBy = Role::max('order_by') ?? 0;

        $role = new Role();
        $role->name = $request->input('name');
        $role->label = $request->input('name');  // или другое
        $role->is_sistem = 0;                    // пользовательские роли
        $role->order_by = $maxOrderBy + 10;
//        $role->save();

        DB::transaction(function () use ($request, $role) {
            // Определим максимальное значение order_by

            $role->save();

            $authorId = auth()->id(); // Авторизованный пользователь

            // Логируем создание пользователя
            MyLog::create([
                'type' => 700,    // Лог для ролей
                'action' => 710, // Лог для создания роли
                'author_id' => $authorId,
                'description' => sprintf(
                    "Название: %s",
                    $role->name
                    ),
                'created_at' => now(),
            ]);

        });

        return response()->json([
            'success' => true,
            'role' => $role
        ]);

    }

    /**
     * Метод для удаления роли (AJAX).
     * При удалении:
     *  1) Проверяем, что is_sistem = 0
     *  2) Удаляем связь permission_role
     *  3) Пользователям, у которых эта роль, задаём роль "по умолчанию"
     *  4) Удаляем саму роль
     */
    public function deleteRole(Request $request)
    {
        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        // Получаем роль
        $role = Role::findOrFail($request->role_id);

        // Проверяем, что она не системная
        if ($role->is_sistem == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалять системную роль!',
            ], 400);
        }

        // Роль по умолчанию:
        // Предположим, что у вас есть некая роль 'guest' / 'user' / 'default'
        // (можно хранить в config или где-то ещё).
        $defaultRole = Role::where('name', 'user')->first();
        if (!$defaultRole) {
            // На крайний случай создадим "guest"
            $defaultRole = Role::create([
                'name' => 'user',
                'label' => 'Пользователь',
                'is_sistem' => 1,       // Чтобы нельзя было удалить
                'order_by' => 0,
            ]);
        }

        DB::beginTransaction();
        try {
            // 1) Удаляем связи в permission_role
            DB::table('permission_role')
                ->where('role_id', $role->id)
                ->delete();

            // 2) Обновляем пользователей, у которых была эта роль
            User::where('role_id', $role->id)
                ->update(['role_id' => $defaultRole->id]);

            // 3) Удаляем роль
            $role->delete();

            DB::commit();
            return response()->json([
                'success' => true
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении роли: ' . $e->getMessage(),
            ], 500);
        }
    }

    //Журнал логов на вкладке права
    public function logRules(FilterRequest $request)
    {
        $logs = MyLog::with('author')
            ->where('type', 700) // Настройки логи
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
                    710 => 'Создание роли',
                    720 => 'Изменение роли',
                    730 => 'Удаление роли',

                ];
                return $typeLabels[$log->action] ?? 'Неизвестный тип(user)';
            })
            ->make(true);
    }
}