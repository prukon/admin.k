<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Http\Filters\TeamFilter;
use App\Http\Requests\Team\FilterRequest;
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
use App\Models\PermissionGroup;


class RuleController extends Controller
{
    //ВКЛАДКА РОЛИ

    public function showRules()
    {
        // 1) Контекст партнёра и текущая роль
        $partnerId    = app('current_partner')->id;
        $userRoleName = auth()->user()?->role?->name;
    $isSuperadmin = $userRoleName === 'superadmin';

    // 2) Роли с правами для текущего партнёра
    $roles = Role::with([
        'permissions' => function ($q) use ($partnerId) {
            $q->wherePivot('partner_id', $partnerId);
        }
    ])
        ->where(function ($q) use ($partnerId) {
            $q->where('is_sistem', 1)
                ->orWhereHas('partners', fn($q2) => $q2->where('partner_role.partner_id', $partnerId));
        })
        ->when(!$isSuperadmin, fn($q) => $q->where('is_visible', 1))
        ->orderBy('order_by')
        ->get();

    // 3) Все права (для подсчётов/поиска) + их группы
    $permissions = Permission::with('group')
        ->orderBy('sort_order')
        ->when(!$isSuperadmin, fn($q) => $q->where('is_visible', 1))
        ->get();

    // 4) Явные группы с подгруженными правами
    $groups = PermissionGroup::with([
        'permissions' => function ($q) use ($isSuperadmin) {
            $q->orderBy('sort_order')
                ->when(!$isSuperadmin, fn($q) => $q->where('is_visible', 1));
            }
    ])
        ->when(!$isSuperadmin, fn($q) => $q->where('is_visible', 1))
        ->orderBy('sort_order')
        ->get();

    // 5) «Прочее» — права без группы
    $ungrouped = $permissions->whereNull('permission_group_id');
    if ($ungrouped->isNotEmpty()) {
        $misc = new PermissionGroup([
            'id'          => 0,
            'slug'        => 'misc',
            'name'        => 'Прочее',
            'description' => null,
            'is_visible'  => true,
            'sort_order'  => 999,
        ]);
        $misc->setRelation('permissions', $ungrouped->values());
        $groups->push($misc);
    }

    return view('admin.setting.index', [
        'activeTab'   => 'rule',
        'roles'       => $roles,
        'permissions' => $permissions,
        'groups'      => $groups, // ← добавили группы
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

        $attach    = $data['value'] === 'true';
        $roleId    = (int) $data['role_id'];
        $permId    = (int) $data['permission_id'];
        $partnerId = app('current_partner')->id;
        $authorId  = auth()->id();

        DB::transaction(function () use ($roleId, $permId, $partnerId, $attach, $authorId) {
            // Заберём сущности для лога
            /** @var \App\Models\Role $role */
            $role = Role::select('id','name','label')->findOrFail($roleId);
            /** @var \App\Models\Permission $perm */
            $perm = Permission::select('id','name','description')->findOrFail($permId);

            // Текущее состояние (чтобы не логировать «ничего не поменялось»)
            $exists = DB::table('permission_role')
                ->where('role_id', $role->id)
                ->where('permission_id', $perm->id)
                ->where('partner_id', $partnerId)
                ->exists();

            if ($attach) {
                if (!$exists) {
                    DB::table('permission_role')->insert([
                        'role_id'       => $role->id,
                        'permission_id' => $perm->id,
                        'partner_id'    => $partnerId,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);

                    // ЛОГ: назначено право
                    MyLog::create([
                        'type'        => 700,
                        'action'      => 741, // «Назначение права роли»
                        'author_id'   => $authorId,
                        'partner_id'  => $partnerId,
                        'description' => sprintf(
                            'Назначено право "%s" (%s) роли "%s" (name=%s)',
                            $perm->description ?? $perm->name,
                            $perm->name,
                            $role->label ?? $role->name,
                            $role->name
                        ),
                        'created_at'  => now(),
                    ]);
                }
                // если уже было — просто молча выходим без лишнего лога
            } else {
                if ($exists) {
                    DB::table('permission_role')
                        ->where('role_id',       $role->id)
                        ->where('permission_id', $perm->id)
                        ->where('partner_id',    $partnerId)
                        ->delete();

                    // ЛОГ: снято право
                    MyLog::create([
                        'type'        => 700,
                        'action'      => 742, // «Снятие права у роли»
                        'author_id'   => $authorId,
                        'partner_id'  => $partnerId,
                        'description' => sprintf(
                            'Снято право "%s" (%s) с роли "%s" (name=%s)',
                            $perm->description ?? $perm->name,
                            $perm->name,
                            $role->label ?? $role->name,
                            $role->name
                        ),
                        'created_at'  => now(),
                    ]);
                }
                // если и так не было — тоже без лога
            }
        });

        return response()->json(['success' => true]);
    }

    //* Метод для создания новой роли (AJAX).
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
                'partner_id'  => $partnerId,
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
        $partnerId = app('current_partner')->id;

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

        DB::transaction(function () use ($role, $defaultRole, $partnerId) {
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
                'partner_id'  => $partnerId,
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
        $partnerId = app('current_partner')->id;

        $logs = MyLog::with('author')
            ->where('type', 700) // Настройки логи
            ->where('partner_id', $partnerId)        // ИЗМЕНЕНИЕ #2: добавляем фильтр по partner_id

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