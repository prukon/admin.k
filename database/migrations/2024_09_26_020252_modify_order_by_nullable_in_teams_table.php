<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyOrderByNullableInTeamsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->unsignedBigInteger('author_id')->change();
            // $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade'); // Опционально
        });
    }

    public function down()
    {
        Schema::table('logs', function (Blueprint $table) {
            // $table->dropForeign(['author_id']); // Опционально
            $table->integer('author_id')->change();
        });
    }
}
