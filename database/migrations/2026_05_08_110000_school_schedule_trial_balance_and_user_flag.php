<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'has_used_school_schedule_trial')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('has_used_school_schedule_trial')->default(false);
            });
        }

        if (! Schema::hasColumn('user_team_schedule_slots', 'trial_lessons_remaining')) {
            Schema::table('user_team_schedule_slots', function (Blueprint $table) {
                $table->unsignedTinyInteger('trial_lessons_remaining')->nullable()->after('is_trial_lesson');
            });
        }

        if (! Schema::hasColumn('user_team_schedule_slots', 'trial_lessons_total')) {
            Schema::table('user_team_schedule_slots', function (Blueprint $table) {
                $table->unsignedTinyInteger('trial_lessons_total')->nullable()->after('trial_lessons_remaining');
            });
        }

        if (Schema::hasColumn('user_team_schedule_slots', 'is_trial_lesson')) {
            DB::table('user_team_schedule_slots')
                ->where('is_trial_lesson', true)
                ->whereNull('trial_lessons_remaining')
                ->update([
                    'trial_lessons_remaining' => 1,
                    'trial_lessons_total' => 1,
                ]);
        }

        $driver = Schema::getConnection()->getDriverName();
        if (($driver === 'mysql' || $driver === 'mariadb') && Schema::hasColumn('users', 'has_used_school_schedule_trial')) {
            DB::statement(
                'UPDATE users u INNER JOIN (
                    SELECT DISTINCT user_id FROM user_team_schedule_slots
                    WHERE is_trial_lesson = 1 AND user_lesson_package_id IS NULL
                ) t ON u.id = t.user_id
                SET u.has_used_school_schedule_trial = 1'
            );
        } elseif (Schema::hasColumn('users', 'has_used_school_schedule_trial')) {
            $ids = DB::table('user_team_schedule_slots')
                ->where('is_trial_lesson', true)
                ->whereNull('user_lesson_package_id')
                ->distinct()
                ->pluck('user_id');
            foreach ($ids as $uid) {
                DB::table('users')->where('id', $uid)->update(['has_used_school_schedule_trial' => true]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_team_schedule_slots', 'trial_lessons_remaining')
            || Schema::hasColumn('user_team_schedule_slots', 'trial_lessons_total')) {
            Schema::table('user_team_schedule_slots', function (Blueprint $table) {
                if (Schema::hasColumn('user_team_schedule_slots', 'trial_lessons_remaining')) {
                    $table->dropColumn('trial_lessons_remaining');
                }
                if (Schema::hasColumn('user_team_schedule_slots', 'trial_lessons_total')) {
                    $table->dropColumn('trial_lessons_total');
                }
            });
        }

        if (Schema::hasColumn('users', 'has_used_school_schedule_trial')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('has_used_school_schedule_trial');
            });
        }
    }
};
