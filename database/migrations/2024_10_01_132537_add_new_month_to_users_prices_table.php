<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddNewMonthToUsersPricesTable extends Migration

{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users_prices', function (Blueprint $table) {
            // Добавляем новое поле для хранения даты
            $table->date('new_month')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users_prices', function (Blueprint $table) {
            // Удаляем поле при откате миграции
            $table->dropColumn('new_month');
        });
    }
}

