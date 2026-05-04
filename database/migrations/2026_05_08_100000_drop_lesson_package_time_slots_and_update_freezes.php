<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_lesson_package_freezes', function (Blueprint $table) {
            $table->dropForeign('ulp_freezes_lpts_fk');
        });

        Schema::table('user_lesson_package_freezes', function (Blueprint $table) {
            $table->dropUnique('ulp_freezes_unique_fixed');
        });

        Schema::table('user_lesson_package_freezes', function (Blueprint $table) {
            $table->dropColumn('lesson_package_time_slot_id');
        });

        Schema::table('user_lesson_package_freezes', function (Blueprint $table) {
            $table->unsignedBigInteger('team_schedule_slot_id')->nullable()->after('date');
            $table->foreign('team_schedule_slot_id', 'ulp_freezes_tss_fk')
                ->references('id')
                ->on('team_schedule_slots')
                ->nullOnDelete();
            $table->unique(
                ['user_lesson_package_id', 'date', 'team_schedule_slot_id'],
                'ulp_freezes_unique_tss'
            );
        });

        Schema::dropIfExists('lesson_package_time_slots');
    }

    public function down(): void
    {
        Schema::create('lesson_package_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_package_id')
                ->constrained('lesson_packages')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('time_start');
            $table->time('time_end');
            $table->timestamps();
            $table->unique(
                ['lesson_package_id', 'weekday', 'time_start', 'time_end'],
                'lesson_package_time_slots_unique_slot'
            );
        });

        Schema::table('user_lesson_package_freezes', function (Blueprint $table) {
            $table->dropForeign('ulp_freezes_tss_fk');
        });

        Schema::table('user_lesson_package_freezes', function (Blueprint $table) {
            $table->dropUnique('ulp_freezes_unique_tss');
        });

        Schema::table('user_lesson_package_freezes', function (Blueprint $table) {
            $table->dropColumn('team_schedule_slot_id');
        });

        Schema::table('user_lesson_package_freezes', function (Blueprint $table) {
            $table->unsignedBigInteger('lesson_package_time_slot_id')->nullable()->after('date');
            $table->foreign('lesson_package_time_slot_id', 'ulp_freezes_lpts_fk')
                ->references('id')
                ->on('lesson_package_time_slots')
                ->nullOnDelete();
            $table->unique(
                ['user_lesson_package_id', 'date', 'lesson_package_time_slot_id'],
                'ulp_freezes_unique_fixed'
            );
        });
    }
};
