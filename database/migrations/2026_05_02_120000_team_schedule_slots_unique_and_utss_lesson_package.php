<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_schedule_slots', function (Blueprint $table) {
            $table->dropUnique('tss_partner_week_time_date_unique');
        });

        Schema::table('team_schedule_slots', function (Blueprint $table) {
            $table->unique(
                ['partner_id', 'team_id', 'location_id', 'weekday', 'time_start', 'time_end', 'date_start', 'date_end'],
                'tss_partner_team_loc_week_time_date_unique'
            );
        });

        Schema::table('user_team_schedule_slots', function (Blueprint $table) {
            $table->foreignId('user_lesson_package_id')
                ->nullable()
                ->after('user_id')
                ->constrained('user_lesson_packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_team_schedule_slots', function (Blueprint $table) {
            $table->dropForeign(['user_lesson_package_id']);
        });

        Schema::table('team_schedule_slots', function (Blueprint $table) {
            $table->dropUnique('tss_partner_team_loc_week_time_date_unique');
        });

        Schema::table('team_schedule_slots', function (Blueprint $table) {
            $table->unique(
                ['partner_id', 'weekday', 'time_start', 'time_end', 'date_start', 'date_end'],
                'tss_partner_week_time_date_unique'
            );
        });

        Schema::table('user_team_schedule_slots', function (Blueprint $table) {
            $table->dropColumn('user_lesson_package_id');
        });
    }
};
