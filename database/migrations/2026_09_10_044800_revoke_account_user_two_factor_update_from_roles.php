<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * account.user.two_factor.update: снять выдачу со всех ролей.
 * Право остаётся в каталоге (is_visible=0), по умолчанию выкл. при создании партнёра.
 * Ранее миграция 2026_09_10_034100 бэкфиллила user/admin/trainer.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'account.user.two_factor.update';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'account')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name' => self::PERMISSION_NAME,
                'description' => 'ЛК: двухфакторная аутентификация (SMS)',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 62,
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
        // Не восстанавливаем выдачу: право опциональное, по умолчанию выкл.
    }
};
