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
            [
                [
                    'name' => 'groups.training_base.view',
                    'description' => 'Группы: поле «Тренировочная база»',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 51,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'groups.address.view',
                    'description' => 'Группы: поле «Адрес»',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 52,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['groups.training_base.view', 'groups.address.view'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
