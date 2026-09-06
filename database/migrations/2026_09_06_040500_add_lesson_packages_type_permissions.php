<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Скрытые права lessonPackages.type.fixed / flexible / no_schedule.
 * Ролям не выдаются — opt-in через матрицу superadmin.
 * sort_order postpay сдвигается на 39, чтобы типы шли по порядку.
 */
return new class extends Migration
{
    /** @var list<array{name: string, description: string, sort_order: int}> */
    private const PERMISSIONS = [
        [
            'name' => 'lessonPackages.type.fixed',
            'description' => 'Абонементы, тип «Фиксированный»',
            'sort_order' => 36,
        ],
        [
            'name' => 'lessonPackages.type.flexible',
            'description' => 'Абонементы, тип «Предоплата»',
            'sort_order' => 37,
        ],
        [
            'name' => 'lessonPackages.type.no_schedule',
            'description' => 'Абонементы, тип «Разовое занятие»',
            'sort_order' => 38,
        ],
    ];

    private const POSTPAY_NAME = 'lessonPackages.type.postpay';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'lessonPackages')->value('id');

        $rows = [];
        foreach (self::PERMISSIONS as $meta) {
            $rows[] = [
                'name' => $meta['name'],
                'description' => $meta['description'],
                'permission_group_id' => $groupId,
                'is_visible' => 0,
                'sort_order' => $meta['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('permissions')->upsert(
            $rows,
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        DB::table('permissions')
            ->where('name', self::POSTPAY_NAME)
            ->update([
                'sort_order' => 39,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $names = array_column(self::PERMISSIONS, 'name');
        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id')->all();
        if ($ids !== []) {
            DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        DB::table('permissions')
            ->where('name', self::POSTPAY_NAME)
            ->update([
                'sort_order' => 38,
                'updated_at' => Carbon::now(),
            ]);
    }
};
