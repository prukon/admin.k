<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');

        DB::table('permissions')->upsert(
            [
                [
                    'name' => 'sport_types.view',
                    'description' => 'Страница "Виды спорта"',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 36,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'sport_types.manage',
                    'description' => 'Виды спорта: создание/редактирование',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 37,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        if (!Schema::hasTable('partners') || !Schema::hasTable('permission_role')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (!$adminRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['sport_types.view', 'sport_types.manage'])
            ->pluck('id', 'name');

        $partnerIds = DB::table('partners')->pluck('id');
        $rows = [];

        foreach ($partnerIds as $partnerId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [
                    'partner_id' => (int) $partnerId,
                    'role_id' => (int) $adminRoleId,
                    'permission_id' => (int) $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['sport_types.view', 'sport_types.manage'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
