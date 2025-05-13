<?php
// database/migrations/2025_05_14_000000_make_partner_id_not_nullable_and_add_index.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('permission_role', function (Blueprint $table) {
            // делаем partner_id обязательным
            $table->unsignedBigInteger('partner_id')->nullable(false)->change();
            // уникальный индекс на тройку (partner, role, permission)
            $table->unique(['partner_id', 'role_id', 'permission_id'], 'pr_partner_unique');
        });
    }

    public function down()
    {
        Schema::table('permission_role', function (Blueprint $table) {
            $table->dropUnique('pr_partner_unique');
            $table->unsignedBigInteger('partner_id')->nullable()->change();
        });
    }
};
