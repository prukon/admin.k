<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Право reports.ltv.teams.view: вкладка «Платежи по группам».
 * Видимое. Существующим партнёрам не выдаётся — только новым через role_base_permissions.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'reports.ltv.teams.view';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'reports')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name' => self::PERMISSION_NAME,
                'description' => 'Отчёт «Платежи по группам»',
                'permission_group_id' => $groupId,
                'is_visible' => 1,
                'sort_order' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');
        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
