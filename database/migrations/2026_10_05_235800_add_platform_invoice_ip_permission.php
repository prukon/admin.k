<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Скрытое право «Счёт от ИП» для кошелька.
 * Выдаётся роли admin всех существующих школ и новым школам через role_base_permissions.
 */
return new class extends Migration
{
    private const GROUP_SLUG = 'platformPayments';

    private const PERMISSION_NAME = 'platformPayments.method.invoiceIp';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', self::GROUP_SLUG)->value('id');

        DB::table('permissions')->upsert(
            [[
                'name' => self::PERMISSION_NAME,
                'description' => 'Счёт от ИП (кошелёк)',
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => 25,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        if (! Schema::hasTable('partners') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');
        if ($adminRoleId === null || $permissionId === null) {
            return;
        }

        $rows = [];
        foreach (DB::table('partners')->pluck('id') as $partnerId) {
            $rows[] = [
                'partner_id' => (int) $partnerId,
                'role_id' => (int) $adminRoleId,
                'permission_id' => (int) $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');
        if ($permissionId !== null && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('name', self::PERMISSION_NAME)->delete();
    }
};
