<?php

namespace App\Http\Controllers\Admin\Setting;

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

class RuleController extends Controller
{

    //ВКЛАДКА РОЛИ
    //Страница права пользователей
    public function showRules()
    {

        // Получаем все роли
        $roles = Role::all();
        $roles = Role::with('permissions')->get();

        // Получаем все права (permissions) с сортировкой по id или как вам удобнее
        $permissions = Permission::with('roles')->orderBy('sort_order')->get();

        if (auth()->user()?->role?->name === 'superadmin') {
        $permissions = Permission::with('roles')->orderBy('sort_order')->get();
    } else {
        $permissions = Permission::with('roles')->where('is_visible', true)->orderBy('sort_order')->get();
    }



        // Какую вкладку активной отображать (исходя из вашего кода)
        $activeTab = 'rule';

//        return view('admin.setting.rule', compact('roles', 'permissions', 'activeTab'));


        return view('admin.setting.index',
            ['activeTab' => 'rule'],
            compact(
                "roles",
                "permissions"
                )
        );



    }

    //Изменение прав пользователей
    public function togglePermission2(Request $request)
    {
        $roleId = $request->input('role_id');
        $permissionId = $request->input('permission_id');
        $value = $request->input('value'); // true/false
        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);

        DB::transaction(function () use ($permission, $value, $role) {
            // Определим максимальное значение order_by

            $authorId = auth()->id(); // Авторизованный пользователь

            if ($value == 'true') {
                // Если чекбокс включили, значит нужно добавить право роли
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            } else {
                // Если чекбокс выключили, удаляем право у роли
                $role->permissions()->detach($permission->id);
            }

            // Логируем создание пользователя
            MyLog::create([
                'type' => 700,    // Лог для ролей
                'action' => 720, // Лог для изменения прав
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
            'message' => 'Обновление прошло успешно',
        ]);
    }

    public function togglePermission(Request $request)
    {
        // Валидация (можно использовать FormRequest или встроенный метод validate)
        $data = $request->validate([
            'role_id'       => 'required|integer',
            'permission_id' => 'required|integer',
            'value'         => 'required',
        ]);

        // Преобразуем 'true'/'false' (строки) к настоящему булевому значению.
        // Например, 'true' -> true, 'false' -> false.
        // Можно также использовать касты, если хочется изящнее.
        $value = filter_var($data['value'], FILTER_VALIDATE_BOOLEAN);

        DB::transaction(function () use ($data, $value) {
            // Находим роль и разрешение
            $role = Role::findOrFail($data['role_id']);
            $permission = Permission::findOrFail($data['permission_id']);

            // В зависимости от значения value – даём или убираем право
            if ($value) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            } else {
                $role->permissions()->detach($permission->id);
            }

            // Логируем действие
            MyLog::create([
                'type'       => 700,   // условный тип для ролей
                'action'     => 720,   // условный код для изменения прав
                'author_id'  => auth()->id(),
                // Формат:
                // Роль: Администратор
                // Разрешено: "Имя_разрешения"
                // или
                // Роль: Пользователь
                // Запрещено: "Имя_разрешения"
                'description' => sprintf(
                    "Роль: %s\n%s: \"%s\"",
                    $role->label,
                    $value ? 'Разрешено' : 'Запрещено',
                    $permission->description // или любое нужное вам поле
                ),
                'created_at' => now(),
            ]);
        });

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

    //* Метод для удаления  роли (AJAX).
    public function deleteRole(Request $request)
    {
        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $role = Role::findOrFail($request->role_id);

        if ($role->is_sistem == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя удалять системную роль!',
            ], 400);
        }

        $defaultRole = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'label' => 'Пользователь',
                'is_sistem' => 1,
                'order_by' => 0,
            ]
        );

        DB::transaction(function () use ($role, $defaultRole) {
            DB::table('permission_role')
                ->where('role_id', $role->id)
                ->delete();

            User::where('role_id', $role->id)
                ->update(['role_id' => $defaultRole->id]);

            $role->delete();

            $authorId = auth()->id(); // Авторизованный пользователь

            // Логируем создание пользователя
            MyLog::create([
                'type' => 700,    // Лог для ролей
                'action' => 730, // Лог для удаления роли
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
        ]);
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