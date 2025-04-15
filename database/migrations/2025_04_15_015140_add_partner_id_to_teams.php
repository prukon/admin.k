<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPartnerIdToTeams extends Migration
{
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            // Добавляем поле partner_id после order_by, поле может быть NULL,
            // чтобы избежать проблем с уже существующими данными.
            $table->unsignedBigInteger('partner_id')->nullable()->after('order_by');

            // Если существует таблица partners, можно добавить внешний ключ:
            // $table->foreign('partner_id')->references('id')->on('partners')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            // Если добавляли внешний ключ, сначала удалите его:
            // $table->dropForeign(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
}
