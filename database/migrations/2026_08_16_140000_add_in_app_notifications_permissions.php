<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * In-app уведомления CRM: группа, права view/manage.
 * view — в базовые роли user/admin/trainer (backfill на существующих партнёров).
 * manage — скрытое, без автовыдачи (форма партнёрского админа — позже).
 */
return new class extends Migration
{
    private const GROUP_SLUG = 'inAppNotifications';

    private const VIEW = 'inAppNotifications.view';

    private const MANAGE = 'inAppNotifications.manage';

    /** @var list<string> */
    private const BACKFILL_ROLE_NAMES = ['user', 'admin', 'trainer'];

    public function up(): void
    {
        $now = Carbon::now();

        DB::table('permission_groups')->upsert(
            [[
                'slug' => self::GROUP_SLUG,
                'name' => 'Уведомления CRM',
                'description' => 'Колокольчик и лента in-app уведомлений (не email «Установка цен → Уведомления»)',
                'is_visible' => 1,
                'sort_order' => 36,
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
                    'name' => self::VIEW,
                    'description' => 'Колокольчик и лента уведомлений',
                    'permission_group_id' => $groupId,
                    'is_visible' => 1,
                    'sort_order' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => self::MANAGE,
                    'description' => 'Создание уведомлений (админ школы, позже)',
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

        if (! Schema::hasTable('partners') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $permissionId = (int) DB::table('permissions')->where('name', self::VIEW)->value('id');
        if ($permissionId <= 0) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', self::BACKFILL_ROLE_NAMES)
            ->pluck('id', 'name');

        $partnerIds = DB::table('partners')->pluck('id');
        $rows = [];

        foreach ($partnerIds as $partnerId) {
            foreach (self::BACKFILL_ROLE_NAMES as $roleName) {
                $roleId = $roleIds[$roleName] ?? null;
                if (! $roleId) {
                    continue;
                }

                $rows[] = [
                    'partner_id' => (int) $partnerId,
                    'role_id' => (int) $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('permission_role')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', [self::VIEW, self::MANAGE])
            ->pluck('id')
            ->all();

        if ($permissionIds !== [] && Schema::hasTable('permission_role')) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('name', [self::VIEW, self::MANAGE])->delete();
        DB::table('permission_groups')->where('slug', self::GROUP_SLUG)->delete();
    }
};
