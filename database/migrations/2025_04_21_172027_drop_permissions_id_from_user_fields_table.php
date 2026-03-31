<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Запуск миграции.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_fields', function (Blueprint $table) {
            // Удаляем столбцы permissions_id и permissions, если они существуют
            if (Schema::hasColumn('user_fields', 'permissions_id')) {
                $table->dropColumn('permissions_id');
            }
            if (Schema::hasColumn('user_fields', 'permissions')) {
                $table->dropColumn('permissions');
            }
        });
    }

    /**
     * Откат миграции.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_fields', function (Blueprint $table) {
            // Восстанавливаем столбцы permissions_id и permissions, если их нет
            if (! Schema::hasColumn('user_fields', 'permissions_id')) {
                $table->unsignedBigInteger('permissions_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('user_fields', 'permissions')) {
                $table->string('permissions')->nullable()->after('permissions_id');
            }
        });
    }
};
