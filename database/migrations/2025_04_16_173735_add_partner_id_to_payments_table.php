<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPartnerIdToPaymentsTable extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            // Добавляем поле partner_id для привязки платежей к конкретному партнеру.
            // Делая поле nullable, мы обеспечиваем совместимость с уже существующими записями.
            $table->unsignedBigInteger('partner_id')->nullable()->after('payment_number');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            // При откате миграции удаляем поле partner_id.
            $table->dropColumn('partner_id');
        });
    }
}
