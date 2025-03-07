<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMonthColumnInUsersPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users_prices', function (Blueprint $table) {
            // Разрешаем NULL для столбца month
            $table->string('month')->nullable()->change();
            // Или устанавливаем значение по умолчанию
            // $table->string('month')->default('')->change();
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
            // Отменяем изменения (сделать поле NOT NULL снова, если нужно)
            $table->string('month')->nullable(false)->change();
        });
    }
}
