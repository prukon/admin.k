<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToScheduleUsersTable extends Migration
{
    public function up()
    {
        Schema::table('schedule_users', function (Blueprint $table) {
            // Добавляем колонку 'status' с возможными значениями N, Z, R.
            // Можно использовать ENUM, но для переносимости часто используют varchar.
            $table->string('status', 1)->default('N')->after('user_id')
                ->comment('N - отсутствует, Z - заморозка, R - рабочий день');
        });
    }

    public function down()
    {
        Schema::table('schedule_users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
