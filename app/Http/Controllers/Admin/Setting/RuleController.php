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
use Illuminate\Support\Str;               // ← вот это


class RuleController extends Controller
{

    //ВКЛАДКА РОЛИ
    //Страница права пользователей

    public function showRules()
    {
        // 1) Контекст
        $partnerId    = app('current_partner')->id;
        $userRoleName = auth()->user()?->role?->name;

    $isSuperadmin = $userRoleName === 'superadmin';

    // 2) Роли:
    //    – все системные (is_sistem = 1)
    //    – + все роли, назначенные партнёру через partner_role
    //    – скрытые (is_visible = 0) — только для superadmin
    $roles = Role::with('permissions')
        ->where(function ($q) use ($partnerId) {
            $q->where('is_sistem', 1)
                ->orWhereHas('partners', fn($q2) =>
                  $q2->where('partner_role.partner_id', $partnerId)
              );
        })
        ->when(! $isSuperadmin, fn($q) => $q->where('is_visible', 1))
        ->orderBy('order_by')
        ->get();

    // 3) Права:
    //    – ВСЕ права берём из таблицы
    //    – скрытые (is_visible = 0) — только для superadmin
    $permissions = Permission::orderBy('sort_order')
        ->when(! $isSuperadmin, fn($q) => $q->where('is_visible', 1))
        ->get();

    return view('admin.setting.index', [
        'activeTab'   => 'rule',
        'roles'       => $roles,
        'permissions' => $permissions,
    ]);
}

    //Изменение прав пользователей
    public function togglePermission(Request $request)
    {
        $data = $request->validate([
            'role_id'       => 'required|integer|exists:roles,id',
            'permission_id' => 'required|integer|exists:permissions,id',
            'value'         => 'required|in:true,false',
        ]);

        $attach = $data['value'] === 'true';

        // Обновляем в транзакции
        DB::transaction(function () use ($data, $attach) {
            $role = Role::findOrFail($data['role_id']);

            if ($attach) {
                $role->permissions()->syncWithoutDetaching([$data['permission_id']]);
            } else {
                $role->permissions()->detach($data['permission_id']);
            }
        });

        return response()->json(['success' => true]);
    }


    //* Метод для создания новой роли (AJAX).
    public function createRole2(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'label'      => 'string|max:255',
            'is_sistem'  => 'sometimes|boolean',
//            'order_by'   => 'string|integer',
            'is_visible' => 'sometimes|boolean',
        ]);

        // Определим максимальное значение order_by
        $maxOrderBy = Role::max('order_by') ?? 0;

        $role = new Role();
        $role->name = $request->input('name');
        $role->label = $request->input('name');  // или другое
        $role->is_sistem = 0;                    // пользовательские роли
        $role->order_by = $maxOrderBy + 10;

        // Можно считать, что is_visible придёт, если хотим явно регулировать видимость,
        // например:
        if ($request->has('is_visible')) {
            $role->is_visible = $request->boolean('is_visible');
        } else {
            // по умолчанию пусть будет true
            $role->is_visible = true;
        }



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

    public function createRole3(Request $request)
    {
        // 1. Валидация входных данных
        $data = $request->validate([
            'name'       => 'required|string|max:255|unique:roles,name',
            'label'      => 'nullable|string|max:255',
            'is_sistem'  => 'sometimes|boolean',
            'is_visible' => 'sometimes|boolean',
        ]);

        // 2. Контекст: текущий партнёр
        $partnerId = app('current_partner')->id;

        // 3. Вычисляем порядок (order_by)
        $maxOrderBy = Role::max('order_by') ?? 0;

        // 4. Создаём новую роль, но пока не сохраняем
        $role = new Role([
            'name'       => $data['name'],
            'label'      => $data['label'] ?? $data['name'],
            'is_sistem'  => $data['is_sistem'] ?? 0,    // всегда 0 для пользовательских
            'is_visible' => $data['is_visible'] ?? true,
            'order_by'   => $maxOrderBy + 10,
        ]);

        // 5. Сохраняем роль и сразу привязываем её к партнёру в одной транзакции
        DB::transaction(function () use ($role, $partnerId) {
            $role->save();

            // Pivot partner_role: привязываем роль к текущему партнёру
            $role->partners()->attach($partnerId);

            // Логируем создание
            MyLog::create([
                'type'        => 700,
                'action'      => 710,
                'author_id'   => auth()->id(),
                'description' => sprintf("Создана роль: %s", $role->name),
                'created_at'  => now(),
            ]);
        });

        // 6. Возвращаем результат
        return response()->json([
            'success' => true,
            'role'    => $role,
        ]);
    }

    public function createRole4(Request $request)
    {
        // 1. Валидация
        $data = $request->validate([
            'name'       => 'required|string|max:255|unique:roles,name',
            'is_sistem'  => 'sometimes|boolean',
            'is_visible' => 'sometimes|boolean',
        ]);

        // 2. Текущий партнёр
        $partnerId = app('current_partner')->id;

        // 3. Транслитерация для label
        //    Используем Str::slug, разделитель — нижнее подчёркивание
        $translitLabel = Str::slug($data['name'], '_');

        // 4. Вычисляем новый порядок
        $maxOrderBy = Role::max('order_by') ?? 0;

        // 5. Создаём объект Role
        $role = new Role([
            'name'       => $data['name'],
            'label'      => $translitLabel,
            'description'=> null,
            'is_sistem'  => $data['is_sistem'] ?? 0,
            'is_visible' => $data['is_visible'] ?? true,
            'sort_order' => $maxOrderBy + 10,  // если колонка называется sort_order
        ]);

        // 6. Сохраняем и привязываем к партнёру в транзакции
        DB::transaction(function () use ($role, $partnerId) {
            $role->save();

            // pivot partner_role
            $role->partners()->attach($partnerId);

            // Логируем событие
            MyLog::create([
                'type'        => 700,
                'action'      => 710,
                'author_id'   => auth()->id(),
                'description' => "Создана роль: {$role->name}, label: {$role->label}",
                'created_at'  => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'role'    => $role,
        ]);
    }

    public function createRole(Request $request)
    {
        // 1) Валидация: у нас в форме <input name="name">
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'is_sistem'  => 'sometimes|boolean',
            'is_visible' => 'sometimes|boolean',
        ]);

        // 2) Контекст партнёра
        $partnerId = app('current_partner')->id;

        // 3) Сформируем поле label ровно из того, что ввёл пользователь
        $label = $data['name'];

        // 4) А машинное имя name — как транслит этой метки
        $baseSlug = Str::slug($label, '_');
        $machineName = $baseSlug;
        $i = 1;
        while (Role::where('name', $machineName)->exists()) {
            $machineName = "{$baseSlug}_{$i}";
            $i++;
        }

        // 5) Определяем максимальный order_by
        $maxOrderBy = Role::max('order_by') ?? 0;

        // 6) Создаем роль
        $role = new Role([
            'name'       => $machineName,
            'label'      => $label,
            'description'=> null,
            'is_sistem'  => $data['is_sistem'] ?? 0,
            'is_visible' => $data['is_visible'] ?? true,
            'order_by'   => $maxOrderBy + 10,
        ]);

        // 7) Сохраняем и привязываем к партнёру
        DB::transaction(function () use ($role, $partnerId) {
            $role->save();
            $role->partners()->attach($partnerId);

            MyLog::create([
                'type'        => 700,
                'action'      => 710,
                'author_id'   => auth()->id(),
                'description' => "Создана роль: {$role->label} (name={$role->name})",
                'created_at'  => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'role'    => $role,
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