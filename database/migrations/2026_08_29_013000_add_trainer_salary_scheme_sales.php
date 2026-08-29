<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Схема sales («% от продаж»): скрытое право + изолированные таблицы ввода/расчёта.
 * Право не в базовых ролях, никому не выдаётся.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'schedule.trainerSalary.scheme.sales';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'schedule')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name'                => self::PERMISSION_NAME,
                'description'         => 'ЗП тренеров: схема «% от продаж» (оклад + процент от оплат учеников)',
                'permission_group_id' => $groupId,
                'is_visible'          => 0,
                'sort_order'          => 28,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        if (! Schema::hasTable('trainer_salary_sales_draft_trainers')) {
            Schema::create('trainer_salary_sales_draft_trainers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trainer_salary_draft_line_id');
                $table->unsignedTinyInteger('sales_percent')->default(0);
                $table->bigInteger('paid_months_cents')->default(0);
                $table->bigInteger('paid_packages_cents')->default(0);
                $table->bigInteger('sales_base_cents')->default(0);
                $table->bigInteger('commission_cents')->default(0);
                $table->timestamps();

                $table->unique('trainer_salary_draft_line_id', 'tss_draft_trainers_line_uq');
            });
        }

        if (! Schema::hasTable('trainer_salary_sales_snapshot_trainers')) {
            Schema::create('trainer_salary_sales_snapshot_trainers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trainer_salary_snapshot_id');
                $table->unsignedTinyInteger('sales_percent')->default(0);
                $table->bigInteger('paid_months_cents')->default(0);
                $table->bigInteger('paid_packages_cents')->default(0);
                $table->bigInteger('sales_base_cents')->default(0);
                $table->bigInteger('commission_cents')->default(0);
                $table->timestamps();

                $table->unique('trainer_salary_snapshot_id', 'tss_snap_trainers_snapshot_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_salary_sales_snapshot_trainers');
        Schema::dropIfExists('trainer_salary_sales_draft_trainers');

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
