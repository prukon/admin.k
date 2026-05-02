<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name' => 'scheduleSlots.table',
                'description' => 'Расписание школы: вкладка «Таблица занятий»',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 33,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        $tablePermId = DB::table('permissions')->where('name', 'scheduleSlots.table')->value('id');
        $viewPermId = DB::table('permissions')->where('name', 'scheduleSlots.view')->value('id');

        if (!$tablePermId || !$viewPermId) {
            return;
        }

        $pairs = DB::table('permission_role')
            ->where('permission_id', $viewPermId)
            ->select(['partner_id', 'role_id'])
            ->distinct()
            ->get();

        $rows = [];
        foreach ($pairs as $p) {
            $rows[] = [
                'partner_id' => $p->partner_id,
                'role_id' => $p->role_id,
                'permission_id' => $tablePermId,
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
        $permissionId = DB::table('permissions')->where('name', 'scheduleSlots.table')->value('id');
        if ($permissionId === null) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
