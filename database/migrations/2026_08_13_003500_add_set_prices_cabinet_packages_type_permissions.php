<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Права консоли на отображение абонементов по типу (fixed / flexible / single / postpay).
 * Ролям не выдаются — opt-in через матрицу прав.
 */
return new class extends Migration
{
    /** @var list<array{name: string, description: string, sort_order: int}> */
    private const PERMISSIONS = [
        [
            'name' => 'setPrices.cabinetPackages.fixed.view',
            'description' => 'Консоль: фиксированный абонемент',
            'sort_order' => 25,
        ],
        [
            'name' => 'setPrices.cabinetPackages.flexible.view',
            'description' => 'Консоль: гибкий абонемент',
            'sort_order' => 26,
        ],
        [
            'name' => 'setPrices.cabinetPackages.single.view',
            'description' => 'Консоль: разовое занятие',
            'sort_order' => 27,
        ],
        [
            'name' => 'setPrices.cabinetPackages.postpay.view',
            'description' => 'Консоль: постоплата',
            'sort_order' => 28,
        ],
    ];

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'setPrices')->value('id');

        $rows = [];
        foreach (self::PERMISSIONS as $permission) {
            $rows[] = [
                'name' => $permission['name'],
                'description' => $permission['description'],
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => $permission['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('permissions')->upsert(
            $rows,
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );
    }

    public function down(): void
    {
        $names = array_column(self::PERMISSIONS, 'name');
        $permissionIds = DB::table('permissions')->whereIn('name', $names)->pluck('id');
        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
