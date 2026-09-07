<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Невидимое право messages.own.delete (группа mainMenu).
 * Ролям не выдаётся. Superadmin видит кнопку через Gate::before / @can,
 * но удаляет только свои сообщения (проверка автора в Form Request).
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'messages.own.delete';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'mainMenu')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name'                => self::PERMISSION_NAME,
                'description'         => 'Удаление своих сообщений в чате',
                'permission_group_id' => $groupId,
                'is_visible'          => 0,
                'sort_order'          => 112,
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
