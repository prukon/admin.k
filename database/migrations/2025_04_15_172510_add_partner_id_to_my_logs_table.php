<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPartnerIdToMyLogsTable extends Migration
{
    public function up()
    {
        Schema::table('my_logs', function (Blueprint $table) {
            // Добавляем поле partner_id.
            // Поле nullable, чтобы существующие записи не нарушались.
            // Позиция поля после author_id для наглядности.
            $table->unsignedBigInteger('partner_id')->nullable()->after('author_id');
        });
    }

    public function down()
    {
        Schema::table('my_logs', function (Blueprint $table) {
            // Удаляем поле partner_id при откате миграции.
            $table->dropColumn('partner_id');
        });
    }
}
