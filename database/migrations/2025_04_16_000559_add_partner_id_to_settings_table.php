<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPartnerIdToSettingsTable extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            // Добавляем поле partner_id для привязки настроек к конкретному партнеру.
            // Поле сделано nullable, чтобы не нарушать существующие записи.
            $table->unsignedBigInteger('partner_id')->nullable()->after('text');
        });
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            // Удаляем поле partner_id при откате миграции.
            $table->dropColumn('partner_id');
        });
    }
}
