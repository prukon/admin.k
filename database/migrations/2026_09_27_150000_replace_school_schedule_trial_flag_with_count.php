<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'school_schedule_trial_lessons_count')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('school_schedule_trial_lessons_count')->default(0);
            });
        }

        $this->backfillTrialLessonsCount();

        if (Schema::hasColumn('users', 'has_used_school_schedule_trial')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('has_used_school_schedule_trial');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'has_used_school_schedule_trial')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('has_used_school_schedule_trial')->default(false);
            });
        }

        if (Schema::hasColumn('users', 'has_used_school_schedule_trial')
            && Schema::hasColumn('users', 'school_schedule_trial_lessons_count')) {
            DB::table('users')
                ->where('school_schedule_trial_lessons_count', '>', 0)
                ->update(['has_used_school_schedule_trial' => true]);
        }

        if (Schema::hasColumn('users', 'school_schedule_trial_lessons_count')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('school_schedule_trial_lessons_count');
            });
        }
    }

    private function backfillTrialLessonsCount(): void
    {
        if (! Schema::hasColumn('users', 'school_schedule_trial_lessons_count')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                'UPDATE users u
                LEFT JOIN (
                    SELECT user_id, COUNT(*) AS cnt
                    FROM user_team_schedule_slots
                    WHERE is_trial_lesson = 1 AND user_lesson_package_id IS NULL
                    GROUP BY user_id
                ) t ON t.user_id = u.id
                SET u.school_schedule_trial_lessons_count = COALESCE(t.cnt, 0)'
            );

            return;
        }

        DB::table('users')->update(['school_schedule_trial_lessons_count' => 0]);

        $counts = DB::table('user_team_schedule_slots')
            ->select('user_id', DB::raw('COUNT(*) AS cnt'))
            ->where('is_trial_lesson', true)
            ->whereNull('user_lesson_package_id')
            ->groupBy('user_id')
            ->get();

        foreach ($counts as $row) {
            DB::table('users')
                ->where('id', $row->user_id)
                ->update(['school_schedule_trial_lessons_count' => (int) $row->cnt]);
        }
    }
};
