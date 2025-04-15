<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPartnerIdToRolesTable extends Migration
{
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            // Добавляем поле partner_id, чтобы можно было привязать роль к конкретному партнеру.
            // Делает поле nullable для обеспечения совместимости с уже существующими записями.
            $table->unsignedBigInteger('partner_id')->nullable()->after('order_by');
        });
    }

    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('partner_id');
        });
    }
}
