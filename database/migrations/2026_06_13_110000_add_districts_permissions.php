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
                    'name' => 'districts.view',
                    'description' => 'Справочники: районы (просмотр и редактирование)',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 36,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        DB::table('permissions')
            ->where('name', 'locations.view')
            ->update([
                'description' => 'Справочники: объекты (просмотр)',
                'sort_order' => 37,
                'updated_at' => $now,
            ]);

        DB::table('permissions')
            ->where('name', 'locations.manage')
            ->update([
                'description' => 'Справочники: объекты (создание/редактирование)',
                'sort_order' => 38,
                'updated_at' => $now,
            ]);

        if (! Schema::hasTable('partners') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'districts.view')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        if (! $permissionId || ! $adminRoleId) {
            return;
        }

        $partnerIds = DB::table('partners')->pluck('id');
        $rows = [];

        foreach ($partnerIds as $partnerId) {
            $rows[] = [
                'partner_id' => (int) $partnerId,
                'role_id' => (int) $adminRoleId,
                'permission_id' => (int) $permissionId,
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
        $now = Carbon::now();

        $permissionId = DB::table('permissions')->where('name', 'districts.view')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('permissions')
            ->where('name', 'locations.view')
            ->update([
                'description' => 'Страница "Локации"',
                'sort_order' => 37,
                'updated_at' => $now,
            ]);

        DB::table('permissions')
            ->where('name', 'locations.manage')
            ->update([
                'description' => 'Локации: создание/редактирование',
                'sort_order' => 38,
                'updated_at' => $now,
            ]);
    }
};
