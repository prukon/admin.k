<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionGroupsSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Твои модули: добавь/переименуй под проект
        $map = [
            'mainMenu'     => 'Главное меню',
            'account'  => 'Учетная запись',
//            'teams'     => 'Команды',
//            'roles'     => 'Роли',
//            'permissions'=> 'Права',
//            'reports'   => 'Отчеты',
//            'settings'  => 'Настройки',
            // добавляй по мере появления модулей
        ];

        DB::transaction(function () use ($map) {
            // 2) Создаём/обновляем группы
            $groups = collect($map)->mapWithKeys(function ($name, $slug) {
                $g = PermissionGroup::query()->updateOrCreate(
                    ['slug' => $slug],
                    ['name' => $name, 'is_visible' => true]
                );
                return [$slug => $g->id];
            });

            // Группа по умолчанию для «непоименованных» ресурсов
            $misc = PermissionGroup::query()->updateOrCreate(
                ['slug' => 'misc'],
                ['name' => 'Разное', 'is_visible' => true, 'sort_order' => 999]
            );

            // 3) Пробегаем все permissions и назначаем группу по префиксу
            Permission::query()->orderBy('id')->chunkById(500, function ($perms) use ($groups, $misc) {
                foreach ($perms as $perm) {
                    // ожидаем формат resource.action[.scope]
                    $resource = Str::before($perm->name, '.');
                    $groupId  = $groups[$resource] ?? $misc->id;

                    if ($perm->permission_group_id !== $groupId) {
                        $perm->permission_group_id = $groupId;
                        $perm->save();
                    }
                }
            });
        });
    }
}
