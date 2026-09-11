<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Право groups.own: тренер видит только свои группы (фильтры, списки, подписи ученика).
 * Ролям не выдаётся. Школа включает его роли trainer в матрице.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'groups.own';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'users')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name'                => self::PERMISSION_NAME,
                'description'         => 'Только свои группы (тренер)',
                'permission_group_id' => $groupId,
                'is_visible'          => 1,
                'sort_order'          => 42,
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
