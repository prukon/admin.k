<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $groupId = DB::table('permission_groups')
            ->where('slug', 'mainMenu')
            ->value('id');

        // 1) Создаём/обновляем само право
        DB::table('permissions')->upsert([
            [
                'name' => 'blog.view',
                'description' => 'Страница "Блог"',
                'permission_group_id' => $groupId,
                'is_visible' => 1,
                'sort_order' => 74,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['name'], [
            'description',
            'permission_group_id',
            'is_visible',
            'sort_order',
            'updated_at',
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'blog.view')
            ->value('id');

        if (!$permissionId) {
            return;
        }

        // 2) Выдаём право роли admin для всех партнёров (чтобы пункт меню появился сразу)
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (!$adminRoleId) {
            return;
        }

        $partnerIds = DB::table('partners')->pluck('id');
        if ($partnerIds->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($partnerIds as $partnerId) {
            $rows[] = [
                'partner_id' => $partnerId,
                'role_id' => $adminRoleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'blog.view')
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};

