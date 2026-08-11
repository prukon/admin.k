<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Скрытое право users.full_name_genitive (ФИО ученика в родительном падеже).
 * Ролям не выдаётся — по умолчанию никому недоступно (в т.ч. для существующих партнёров).
 * В матрице прав is_visible=0 (только superadmin может выдать вручную).
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'users.full_name_genitive';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'users')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name'                => self::PERMISSION_NAME,
                'description'         => 'ФИО ученика в родительном падеже (CRM, ЛК, импорт Excel)',
                'permission_group_id' => $groupId,
                'is_visible'          => 0,
                'sort_order'          => 6,
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
