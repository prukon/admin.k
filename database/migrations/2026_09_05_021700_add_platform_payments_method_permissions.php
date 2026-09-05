<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Группа «Оплата платформы» и скрытые права T‑Bank СБП / ЮKassa для кошелька и абонплаты.
 * Без выдачи ролям: существующим школам права не проставляются.
 * Новые партнёры: T‑Bank СБП только у admin через config/role_base_permissions.php.
 */
return new class extends Migration
{
    private const GROUP_SLUG = 'platformPayments';

    private const PERM_TBANK = 'platformPayments.method.tbankSbp';

    private const PERM_YOOKASSA = 'platformPayments.method.yookassa';

    public function up(): void
    {
        $now = Carbon::now();

        DB::table('permission_groups')->upsert(
            [[
                'slug' => self::GROUP_SLUG,
                'name' => 'Оплата платформы',
                'description' => 'Способы оплаты кошелька школы и абонплаты KidsCRM (не витрина родителей)',
                'is_visible' => 1,
                'sort_order' => 34,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['slug'],
            ['name', 'description', 'is_visible', 'sort_order', 'updated_at']
        );

        $groupId = DB::table('permission_groups')->where('slug', self::GROUP_SLUG)->value('id');

        DB::table('permissions')->upsert(
            [
                [
                    'name' => self::PERM_TBANK,
                    'description' => 'T‑Bank СБП (кошелёк и абонплата)',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => self::PERM_YOOKASSA,
                    'description' => 'ЮKassa (кошелёк и абонплата)',
                    'permission_group_id' => $groupId,
                    'is_visible' => 0,
                    'sort_order' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', [self::PERM_TBANK, self::PERM_YOOKASSA])
            ->pluck('id')
            ->all();

        if ($permissionIds !== [] && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('name', [self::PERM_TBANK, self::PERM_YOOKASSA])->delete();
        DB::table('permission_groups')->where('slug', self::GROUP_SLUG)->delete();
    }
};
