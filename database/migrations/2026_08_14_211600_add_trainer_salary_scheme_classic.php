<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Схема classic: скрытое право + scheme_code на периоде и слепке.
 * Право не в базовых ролях, никому не выдаётся.
 */
return new class extends Migration
{
    private const PERMISSION_NAME = 'schedule.trainerSalary.scheme.classic';

    private const SCHEME_CODE = 'classic';

    public function up(): void
    {
        $now = Carbon::now();
        $groupId = DB::table('permission_groups')->where('slug', 'schedule')->value('id');

        DB::table('permissions')->upsert(
            [[
                'name'                => self::PERMISSION_NAME,
                'description'         => 'ЗП тренеров: схема «Классическая» (оклад + ставка за тренировку)',
                'permission_group_id' => $groupId,
                'is_visible'          => 0,
                'sort_order'          => 30,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]],
            ['name'],
            ['description', 'permission_group_id', 'is_visible', 'sort_order', 'updated_at']
        );

        if (Schema::hasTable('trainer_salary_periods') && ! Schema::hasColumn('trainer_salary_periods', 'scheme_code')) {
            Schema::table('trainer_salary_periods', function (Blueprint $table) {
                $table->string('scheme_code', 32)->default(self::SCHEME_CODE)->after('month');
            });
        }

        if (Schema::hasTable('trainer_salary_snapshots') && ! Schema::hasColumn('trainer_salary_snapshots', 'scheme_code')) {
            Schema::table('trainer_salary_snapshots', function (Blueprint $table) {
                $table->string('scheme_code', 32)->default(self::SCHEME_CODE)->after('trainer_profile_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('trainer_salary_snapshots') && Schema::hasColumn('trainer_salary_snapshots', 'scheme_code')) {
            Schema::table('trainer_salary_snapshots', function (Blueprint $table) {
                $table->dropColumn('scheme_code');
            });
        }

        if (Schema::hasTable('trainer_salary_periods') && Schema::hasColumn('trainer_salary_periods', 'scheme_code')) {
            Schema::table('trainer_salary_periods', function (Blueprint $table) {
                $table->dropColumn('scheme_code');
            });
        }

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION_NAME)->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
