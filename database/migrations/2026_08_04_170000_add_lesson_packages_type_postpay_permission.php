<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Добавляет невидимое право lessonPackages.type.postpay (группа lessonPackages).
 * Право в группе lessonPackages, is_visible=0, без выдачи ролям.
 * UI/валидация: StoreLessonPackageRequest + SettingPrices requests + packages.blade.php.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'lessonPackages.type.postpay';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'lessonPackages')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name'                => self::PERMISSION_NAME,
                'description'         => 'Абонементы, тип «Постоплата»',
                'permission_group_id' => $groupId,
                'is_visible'          => 0,
                'sort_order'          => 38,
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
