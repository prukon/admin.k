<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPartnerIdToMenuItemsTable extends Migration
{
    public function up()
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Добавляем поле partner_id для привязки пунктов меню к партнёру
            // Делает поле nullable, чтобы не нарушать существующие записи
            $table->unsignedBigInteger('partner_id')->nullable()->after('target_blank');
        });
    }

    public function down()
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Откат: удаляем колонку partner_id
            $table->dropColumn('partner_id');
        });
    }
}
