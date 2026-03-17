<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Удаляем дубли по (team_id, weekday_id), иначе UNIQUE не встанет
        // Оставляем запись с минимальным id
        DB::statement("
            DELETE tw1
            FROM team_weekdays tw1
            INNER JOIN team_weekdays tw2
                ON tw1.team_id = tw2.team_id
               AND tw1.weekday_id = tw2.weekday_id
               AND tw1.id > tw2.id
        ");

        // 2) СНАЧАЛА убираем колонку id (вместе с AUTO_INCREMENT и PRIMARY KEY)
        // Это корректно для твоей таблицы, и не требует DROP PRIMARY KEY отдельно.
        DB::statement("ALTER TABLE team_weekdays DROP COLUMN id");

        // 3) Добавляем уникальный составной индекс
        Schema::table('team_weekdays', function (Blueprint $table) {
            $table->unique(['team_id', 'weekday_id'], 'team_weekdays_team_id_weekday_id_unique');
        });

        // (Опционально) Отдельные индексы обычно уже есть (у тебя MUL на team_id/weekday_id),
        // поэтому повторно их добавлять не надо.
    }

    public function down(): void
    {
        // 1) Снимаем UNIQUE
        Schema::table('team_weekdays', function (Blueprint $table) {
            $table->dropUnique('team_weekdays_team_id_weekday_id_unique');
        });

        // 2) Возвращаем id как AUTO_INCREMENT PRIMARY KEY
        // Важно: AUTO_INCREMENT должен быть ключом в том же ALTER, иначе MySQL ругнётся.
        DB::statement("
            ALTER TABLE team_weekdays
            ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST
        ");
    }
};