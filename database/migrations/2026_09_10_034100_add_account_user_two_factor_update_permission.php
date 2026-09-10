<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Скрытое право account.user.two_factor.update (группа account).
 * Не входит в base roles: при создании партнёра выключено у всех.
 * Superadmin включает в матрице. Уже включённая 2FA при отсутствии права не сбрасывается.
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
                'name'                => self::PERMISSION_NAME,
                'description'         => 'ЛК: двухфакторная аутентификация (SMS)',
                'permission_group_id' => $groupId,
                'is_visible'          => 0,
                'sort_order'          => 62,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
