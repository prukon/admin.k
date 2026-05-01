<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_schedule_slots', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('location_id')->nullable();

            // 1..7 (ПН..ВС)
            $table->unsignedTinyInteger('weekday');
            $table->time('time_start');
            $table->time('time_end');

            // Период действия слота (на год вперёд и изменения "с даты")
            $table->date('date_start');
            // 9999-12-31 = "без окончания"
            $table->date('date_end')->default('9999-12-31');

            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            // Защита от точных дублей (с учётом дат)
            $table->unique(
                ['partner_id', 'weekday', 'time_start', 'time_end', 'date_start', 'date_end'],
                'tss_partner_week_time_date_unique'
            );

            $table->index(['partner_id', 'weekday', 'time_start'], 'tss_partner_week_time_idx');
            $table->index(['team_id'], 'tss_team_idx');
            $table->index(['location_id'], 'tss_location_idx');

            $table->foreign('partner_id', 'tss_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('team_id', 'tss_team_fk')
                ->references('id')
                ->on('teams')
                ->cascadeOnDelete();

            $table->foreign('location_id', 'tss_location_fk')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_schedule_slots');
    }
};

