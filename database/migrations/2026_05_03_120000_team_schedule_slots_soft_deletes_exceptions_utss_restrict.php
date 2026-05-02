<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_team_schedule_slots', function (Blueprint $table) {
            $table->dropForeign('utss_slot_fk');
        });

        Schema::table('user_team_schedule_slots', function (Blueprint $table) {
            $table->foreign('team_schedule_slot_id', 'utss_slot_fk')
                ->references('id')
                ->on('team_schedule_slots')
                ->restrictOnDelete();
        });

        Schema::table('team_schedule_slots', function (Blueprint $table) {
            $table->dropUnique('tss_partner_team_loc_week_time_date_unique');
        });

        Schema::table('team_schedule_slots', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(
                ['partner_id', 'team_id', 'location_id', 'weekday', 'time_start', 'time_end', 'date_start', 'date_end'],
                'tss_partner_team_loc_week_time_date_idx'
            );
        });

        Schema::create('team_schedule_slot_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('team_schedule_slot_id')->constrained('team_schedule_slots')->restrictOnDelete();
            $table->date('occurrence_date');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['partner_id', 'occurrence_date'], 'tss_exc_partner_date_idx');
            $table->index(['team_schedule_slot_id', 'occurrence_date'], 'tss_exc_slot_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_schedule_slot_exceptions');

        Schema::table('team_schedule_slots', function (Blueprint $table) {
            $table->dropIndex('tss_partner_team_loc_week_time_date_idx');
            $table->dropSoftDeletes();
        });

        Schema::table('team_schedule_slots', function (Blueprint $table) {
            $table->unique(
                ['partner_id', 'team_id', 'location_id', 'weekday', 'time_start', 'time_end', 'date_start', 'date_end'],
                'tss_partner_team_loc_week_time_date_unique'
            );
        });

        Schema::table('user_team_schedule_slots', function (Blueprint $table) {
            $table->dropForeign('utss_slot_fk');
        });

        Schema::table('user_team_schedule_slots', function (Blueprint $table) {
            $table->foreign('team_schedule_slot_id', 'utss_slot_fk')
                ->references('id')
                ->on('team_schedule_slots')
                ->cascadeOnDelete();
        });
    }
};
