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
        $groupId = DB::table('permission_groups')->where('slug', 'users')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name' => 'users.other.update',
                'description' => 'Прочие сведения об ученике (мед./особенности)',
                'permission_group_id' => $groupId,
                'is_visible' => 1,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        if (!Schema::hasTable('partners') || !Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', 'users.other.update')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        if (!$permissionId || !$adminRoleId) {
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
        $permissionId = DB::table('permissions')->where('name', 'users.other.update')->value('id');
        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
