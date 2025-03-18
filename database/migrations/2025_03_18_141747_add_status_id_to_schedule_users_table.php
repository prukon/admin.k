<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusIdToScheduleUsersTable extends Migration
{
    /**
     * Добавляем поле status_id и связываем его с таблицей statuses.
     */
    public function up()
    {
        Schema::table('schedule_users', function (Blueprint $table) {
            $table->unsignedBigInteger('status_id')->nullable()->after('user_id');
            $table->foreign('status_id')
                ->references('id')
                ->on('statuses')
                ->nullOnDelete();
        });
    }

    /**
     * Убираем поле status_id и соответствующий внешний ключ.
     */
    public function down()
    {
        Schema::table('schedule_users', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });
    }
}
