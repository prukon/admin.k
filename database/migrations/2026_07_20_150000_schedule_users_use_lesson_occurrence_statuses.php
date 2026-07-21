<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Журнал /schedule переходит на единый справочник lesson_occurrence_statuses.
 * Обратная совместимость со statuses не нужна: старые данные schedule_users очищаются.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_users')) {
            return;
        }

        // Без BC: очищаем ячейки журнала до смены FK.
        DB::table('schedule_users')->delete();

        if (Schema::hasColumn('schedule_users', 'status_id')) {
            Schema::table('schedule_users', function (Blueprint $table) {
                $table->dropForeign(['status_id']);
            });

            Schema::table('schedule_users', function (Blueprint $table) {
                $table->dropColumn('status_id');
            });
        }

        if (! Schema::hasColumn('schedule_users', 'lesson_occurrence_status_id')) {
            Schema::table('schedule_users', function (Blueprint $table) {
                $table->unsignedBigInteger('lesson_occurrence_status_id')
                    ->nullable()
                    ->after('user_id');
            });
        }

        if (Schema::hasColumn('schedule_users', 'lesson_occurrence_status_id')
            && Schema::hasTable('lesson_occurrence_statuses')) {
            Schema::table('schedule_users', function (Blueprint $table) {
                $table->foreign('lesson_occurrence_status_id', 'schedule_users_los_fk')
                    ->references('id')
                    ->on('lesson_occurrence_statuses')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedule_users')) {
            return;
        }

        DB::table('schedule_users')->delete();

        if (Schema::hasColumn('schedule_users', 'lesson_occurrence_status_id')) {
            Schema::table('schedule_users', function (Blueprint $table) {
                $table->dropForeign('schedule_users_los_fk');
            });

            Schema::table('schedule_users', function (Blueprint $table) {
                $table->dropColumn('lesson_occurrence_status_id');
            });
        }

        if (! Schema::hasColumn('schedule_users', 'status_id')) {
            Schema::table('schedule_users', function (Blueprint $table) {
                $table->unsignedBigInteger('status_id')->nullable()->after('user_id');
            });

            if (Schema::hasTable('statuses')) {
                Schema::table('schedule_users', function (Blueprint $table) {
                    $table->foreign('status_id')
                        ->references('id')
                        ->on('statuses')
                        ->nullOnDelete();
                });
            }
        }
    }
};
