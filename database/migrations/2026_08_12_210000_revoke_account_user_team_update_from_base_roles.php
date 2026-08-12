<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * account.user.team.update: актуализация описания и снятие с ролей.
 * Право остаётся в каталоге (is_visible=0), выдаётся вручную.
 * Ранее попадало в base admin — для существующих партнёров снимаем со всех ролей.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'account.user.team.update';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'account')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name' => self::PERMISSION_NAME,
                'description' => 'ЛК: добавление группы (сайдбар)',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');
        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }
    }

    public function down(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'account')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name' => self::PERMISSION_NAME,
                'description' => 'Изменение своей группы',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        // Не восстанавливаем выдачу ролям: право опциональное.
    }
};
