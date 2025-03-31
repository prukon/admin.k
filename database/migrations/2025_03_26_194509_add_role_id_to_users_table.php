<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('id');

            // Либо так ( Laravel 8+ ):
            // $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete()->after('id');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('set null'); // при удалении роли поле role_id станет null
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // нужно сначала дропнуть внешний ключ, потом столбец
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
