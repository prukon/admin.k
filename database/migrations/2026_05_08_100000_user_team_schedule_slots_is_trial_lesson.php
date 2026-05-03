<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_team_schedule_slots', function (Blueprint $table) {
            $table->boolean('is_trial_lesson')->default(false)->after('user_lesson_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_team_schedule_slots', function (Blueprint $table) {
            $table->dropColumn('is_trial_lesson');
        });
    }
};
