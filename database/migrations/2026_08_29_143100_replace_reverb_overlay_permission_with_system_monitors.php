<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Скрытое settings.systemMonitors.view вместо settings.reverbOverlay.manage.
 * Глобальный флаг cabinet_diagnostics больше не используется.
 */
return new class extends Migration
{
    private const OLD_PERMISSION = 'settings.reverbOverlay.manage';

    private const NEW_PERMISSION = 'settings.systemMonitors.view';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'settings')->value('id');

        $oldId = DB::table('permissions')->where('name', self::OLD_PERMISSION)->value('id');
        if ($oldId) {
            DB::table('permission_role')->where('permission_id', $oldId)->delete();
            DB::table('permissions')->where('id', $oldId)->delete();
        }

        DB::table('permissions')->upsert(
            [[
                'name' => self::NEW_PERMISSION,
                'description' => 'Системные мониторы (оверлеи)',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 224,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        DB::table('settings')->where('name', 'cabinet_diagnostics')->delete();
    }

    public function down(): void
    {
        $newId = DB::table('permissions')->where('name', self::NEW_PERMISSION)->value('id');
        if ($newId) {
            DB::table('permission_role')->where('permission_id', $newId)->delete();
            DB::table('permissions')->where('id', $newId)->delete();
        }

        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'settings')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name' => self::OLD_PERMISSION,
                'description' => 'Оверлей статуса Reverb',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 224,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );
    }
};
