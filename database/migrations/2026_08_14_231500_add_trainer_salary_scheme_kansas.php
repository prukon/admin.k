<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Схема kansas: скрытое право + изолированные таблицы ввода/расчёта.
 * Право не в базовых ролях, никому не выдаётся.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'schedule.trainerSalary.scheme.kansas';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'schedule')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name'                => self::PERMISSION_NAME,
                'description'         => 'ЗП тренеров: схема «Канзас» (оклад за тренировку + премия от среднего)',
                'permission_group_id' => $groupId,
                'is_visible'          => 0,
                'sort_order'          => 29,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        if (! Schema::hasTable('trainer_salary_kansas_period_settings')) {
            Schema::create('trainer_salary_kansas_period_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trainer_salary_period_id');
                $table->bigInteger('premium_increment_cents')->default(0);
                $table->timestamps();

                $table->unique('trainer_salary_period_id', 'tsk_period_settings_period_uq');
            });
        }

        if (! Schema::hasTable('trainer_salary_kansas_group_baselines')) {
            Schema::create('trainer_salary_kansas_group_baselines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trainer_salary_period_id');
                $table->unsignedInteger('team_id')->default(0);
                $table->unsignedInteger('base_avg_students_tenths')->default(0);
                $table->timestamps();

                $table->unique(['trainer_salary_period_id', 'team_id'], 'tsk_group_baselines_period_team_uq');
            });
        }

        if (! Schema::hasTable('trainer_salary_kansas_draft_trainers')) {
            Schema::create('trainer_salary_kansas_draft_trainers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trainer_salary_draft_line_id');
                $table->bigInteger('rate_per_training_cents')->default(0);
                $table->bigInteger('base_premium_cents')->default(0);
                $table->timestamps();

                $table->unique('trainer_salary_draft_line_id', 'tsk_draft_trainers_line_uq');
            });
        }

        if (! Schema::hasTable('trainer_salary_kansas_draft_groups')) {
            Schema::create('trainer_salary_kansas_draft_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trainer_salary_draft_line_id');
                $table->unsignedInteger('team_id')->default(0);
                $table->string('team_title', 255)->default('');
                $table->unsignedInteger('trainings_count')->default(0);
                $table->unsignedInteger('students_visited_sum')->default(0);
                $table->integer('fact_avg_tenths')->default(0);
                $table->integer('base_avg_tenths')->default(0);
                $table->integer('diff_tenths')->default(0);
                $table->bigInteger('premium_cents')->default(0);
                $table->bigInteger('pay_per_training_cents')->default(0);
                $table->bigInteger('group_total_cents')->default(0);
                $table->timestamps();

                $table->unique(['trainer_salary_draft_line_id', 'team_id'], 'tsk_draft_groups_line_team_uq');
            });
        }

        if (! Schema::hasTable('trainer_salary_kansas_snapshot_trainers')) {
            Schema::create('trainer_salary_kansas_snapshot_trainers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trainer_salary_snapshot_id');
                $table->bigInteger('rate_per_training_cents')->default(0);
                $table->bigInteger('base_premium_cents')->default(0);
                $table->bigInteger('premium_increment_cents')->default(0);
                $table->timestamps();

                $table->unique('trainer_salary_snapshot_id', 'tsk_snap_trainers_snapshot_uq');
            });
        }

        if (! Schema::hasTable('trainer_salary_kansas_snapshot_groups')) {
            Schema::create('trainer_salary_kansas_snapshot_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trainer_salary_snapshot_id');
                $table->unsignedInteger('team_id')->default(0);
                $table->string('team_title', 255)->default('');
                $table->unsignedInteger('trainings_count')->default(0);
                $table->unsignedInteger('students_visited_sum')->default(0);
                $table->integer('fact_avg_tenths')->default(0);
                $table->integer('base_avg_tenths')->default(0);
                $table->integer('diff_tenths')->default(0);
                $table->bigInteger('premium_cents')->default(0);
                $table->bigInteger('pay_per_training_cents')->default(0);
                $table->bigInteger('group_total_cents')->default(0);
                $table->timestamps();

                $table->unique(['trainer_salary_snapshot_id', 'team_id'], 'tsk_snap_groups_snap_team_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_salary_kansas_snapshot_groups');
        Schema::dropIfExists('trainer_salary_kansas_snapshot_trainers');
        Schema::dropIfExists('trainer_salary_kansas_draft_groups');
        Schema::dropIfExists('trainer_salary_kansas_draft_trainers');
        Schema::dropIfExists('trainer_salary_kansas_group_baselines');
        Schema::dropIfExists('trainer_salary_kansas_period_settings');

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
