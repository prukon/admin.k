<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropPartnerIdFromRolesTable extends Migration
{
    public function up()
    {
        // Отключаем проверку FK, чтобы не падать, если констрейнтов нет
        Schema::disableForeignKeyConstraints();

        if (Schema::hasColumn('roles', 'partner_id')) {
            Schema::table('roles', function (Blueprint $table) {
                // Сам метод dropColumn автоматически уберёт FK
                $table->dropColumn('partner_id');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_id')->nullable()->after('is_visible');
            $table->foreign('partner_id')
                ->references('id')
                ->on('partners')
                ->onDelete('cascade');
        });
    }
}
