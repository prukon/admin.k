<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_salary_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->timestamps();

            $table->unique(['partner_id', 'year', 'month'], 'tsalary_period_partner_ym_unique');
            $table->index(['partner_id', 'year', 'month'], 'tsalary_period_partner_ym_idx');
        });

        Schema::create('trainer_salary_draft_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_salary_period_id');
            $table->unsignedBigInteger('trainer_profile_id');
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('rate_per_training', 12, 2)->default(0);
            $table->unsignedInteger('trainings_count')->default(0);
            $table->decimal('trainings_amount', 12, 2)->default(0);
            $table->decimal('bonuses', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->text('comment')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['trainer_salary_period_id', 'trainer_profile_id'], 'tsalary_draft_period_trainer_uq');
            $table->index('trainer_profile_id', 'tsalary_draft_trainer_idx');
        });

        Schema::create('trainer_salary_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trainer_salary_period_id');
            $table->unsignedBigInteger('trainer_profile_id');
            $table->unsignedInteger('version');
            $table->uuid('batch_id')->nullable();
            $table->decimal('base_salary', 12, 2);
            $table->decimal('rate_per_training', 12, 2);
            $table->unsignedInteger('trainings_count');
            $table->decimal('trainings_amount', 12, 2);
            $table->decimal('bonuses', 12, 2);
            $table->decimal('deductions', 12, 2);
            $table->text('comment')->nullable();
            $table->decimal('total', 12, 2);
            $table->unsignedBigInteger('formed_by_user_id');
            $table->timestamp('formed_at');
            $table->timestamps();

            $table->unique(
                ['trainer_salary_period_id', 'trainer_profile_id', 'version'],
                'tsalary_snap_period_trainer_ver_uq',
            );
            $table->index(['trainer_salary_period_id', 'trainer_profile_id'], 'tsalary_snap_period_trainer_idx');
            $table->index('batch_id', 'tsalary_snap_batch_idx');
            $table->index('formed_at', 'tsalary_snap_formed_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_salary_snapshots');
        Schema::dropIfExists('trainer_salary_draft_lines');
        Schema::dropIfExists('trainer_salary_periods');
    }
};
