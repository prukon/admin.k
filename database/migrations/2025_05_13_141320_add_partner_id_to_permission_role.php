<?php
// database/migrations/2025_05_13_000000_add_partner_id_to_permission_role.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('permission_role', function (Blueprint $table) {
            // делаем partner_id nullable, чтобы не сломать existing данные
            $table->unsignedBigInteger('partner_id')->nullable()->after('permission_id');
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('permission_role', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
};
