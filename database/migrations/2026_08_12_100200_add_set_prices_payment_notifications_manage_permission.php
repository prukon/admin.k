<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Право setPrices.paymentNotifications.manage — вкладка «Уведомления» в установке цен.
 * Ролям не выдаётся автоматически (opt-in через матрицу прав).
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'setPrices.paymentNotifications.manage';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'setPrices')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name'                => self::PERMISSION_NAME,
                'description'         => 'Уведомления об оплате абонементов',
                'permission_group_id' => $groupId,
                'is_visible'          => 0,
                'sort_order'          => 24,
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
