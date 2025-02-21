<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('user_fields', function (Blueprint $table) {
            $table->json('permissions')->default(json_encode([]))->after('field_type');
        });
    }

    public function down()
    {
        Schema::table('user_fields', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
