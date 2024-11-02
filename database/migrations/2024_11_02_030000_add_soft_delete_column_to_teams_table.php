<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeleteColumnToTeamsTable extends Migration
{
    /**
     * Запускает миграцию.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Откатывает миграцию.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
