<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultValueToLinkInMenuItemsTable extends Migration
{
    public function up()
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('link')->default('#')->change();
        });
    }

    public function down()
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('link')->default(null)->change();
        });
    }
}
