<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Группа матрицы «Расписание школы» (slug schoolSchedule).
 * Переносит существующие права без смены имён, Gate и permission_role.
 */
return new class extends Migration
{
    private const GROUP_SLUG = 'schoolSchedule';

    /**
     * @var array<string, array{from_slug: string, from_sort: int, to_sort: int}>
     */
    private const PERMISSIONS = [
        'scheduleSlots.view' => ['from_slug' => 'mainMenu', 'from_sort' => 52, 'to_sort' => 10],
        'scheduleSlots.manage' => ['from_slug' => 'schedule', 'from_sort' => 32, 'to_sort' => 20],
        'scheduleSlots.table' => ['from_slug' => 'schedule', 'from_sort' => 33, 'to_sort' => 30],
        'lessonPackages.export' => ['from_slug' => 'lessonPackages', 'from_sort' => 37, 'to_sort' => 40],
        'setPrices.packageAssignments.view' => ['from_slug' => 'setPrices', 'from_sort' => 22, 'to_sort' => 50],
        'lessonPackages.manualPaid.manage' => ['from_slug' => 'lessonPackages', 'from_sort' => 36, 'to_sort' => 60],
    ];

    /**
     * @var array<string, int>
     */
    private const GROUP_SORT_AFTER = [
        'directories' => 14,
        'lessonPackages' => 15,
        'setPrices' => 16,
        'contracts' => 17,
        'leads' => 18,
        'partner' => 19,
    ];

    /**
     * @var array<string, int>
     */
    private const GROUP_SORT_BEFORE = [
        'directories' => 13,
        'lessonPackages' => 14,
        'setPrices' => 15,
        'contracts' => 16,
        'leads' => 17,
        'partner' => 18,
    ];

    public function up(): void
    {
        $now = Carbon::now();

        DB::table('permission_groups')->upsert(
            [[
                'slug' => self::GROUP_SLUG,
                'name' => 'Расписание школы',
                'description' => 'Календарь школы, слоты, назначения абонементов и выгрузка Excel',
                'is_visible' => 1,
                'sort_order' => 13,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['slug'],
            ['name', 'description', 'is_visible', 'sort_order', 'updated_at']
        );

        foreach (self::GROUP_SORT_AFTER as $slug => $sortOrder) {
            DB::table('permission_groups')
                ->where('slug', $slug)
                ->update([
                    'sort_order' => $sortOrder,
                    'updated_at' => $now,
                ]);
        }

        $groupId = (int) DB::table('permission_groups')->where('slug', self::GROUP_SLUG)->value('id');
        if ($groupId < 1) {
            return;
        }

        foreach (self::PERMISSIONS as $name => $meta) {
            DB::table('permissions')
                ->where('name', $name)
                ->update([
                    'permission_group_id' => $groupId,
                    'sort_order' => $meta['to_sort'],
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        $now = Carbon::now();
        $groupIdBySlug = DB::table('permission_groups')->pluck('id', 'slug')->all();

        foreach (self::PERMISSIONS as $name => $meta) {
            $oldGroupId = $groupIdBySlug[$meta['from_slug']] ?? null;
            if ($oldGroupId === null) {
                continue;
            }

            DB::table('permissions')
                ->where('name', $name)
                ->update([
                    'permission_group_id' => (int) $oldGroupId,
                    'sort_order' => $meta['from_sort'],
                    'updated_at' => $now,
                ]);
        }

        foreach (self::GROUP_SORT_BEFORE as $slug => $sortOrder) {
            DB::table('permission_groups')
                ->where('slug', $slug)
                ->update([
                    'sort_order' => $sortOrder,
                    'updated_at' => $now,
                ]);
        }

        DB::table('permission_groups')->where('slug', self::GROUP_SLUG)->delete();
    }
};
