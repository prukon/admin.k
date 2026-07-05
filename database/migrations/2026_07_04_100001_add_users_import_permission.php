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
        $groupId = DB::table('permission_groups')->where('slug', 'users')->value('id')
            ?? DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');

        DB::table('permissions')->upsert(
            [
                [
                    'name' => 'users.import',
                    'description' => 'Пользователи: импорт учеников из Excel',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 15,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        if (! Schema::hasTable('permission_role')) {
            return;
        }

        $importPermissionId = (int) DB::table('permissions')->where('name', 'users.import')->value('id');
        $usersViewPermissionId = (int) DB::table('permissions')->where('name', 'users.view')->value('id');

        if ($importPermissionId <= 0 || $usersViewPermissionId <= 0) {
            return;
        }

        $rows = DB::table('permission_role')
            ->where('permission_id', $usersViewPermissionId)
            ->select('partner_id', 'role_id')
            ->get();

        $grantRows = [];
        foreach ($rows as $row) {
            $grantRows[] = [
                'partner_id' => (int) $row->partner_id,
                'role_id' => (int) $row->role_id,
                'permission_id' => $importPermissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($grantRows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'users.import')->value('id');

        if ($permissionId && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('name', 'users.import')->delete();
    }
};
