<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeMonthNullableInTeamPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('team_prices', function (Blueprint $table) {
            // Делаем поле month nullable
            $table->string('month')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('team_prices', function (Blueprint $table) {
            // Возвращаем поле month обратно в обязательное состояние (если потребуется)
            $table->string('month')->nullable(false)->change();
        });
    }
}
