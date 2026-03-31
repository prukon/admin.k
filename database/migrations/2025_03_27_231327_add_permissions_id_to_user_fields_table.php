<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_fields', function (Blueprint $table) {
            // Добавляем поле для хранения массива ID ролей:
            $table->json('permissions_id')->nullable()->after('permissions');
        });
    }

    public function down()
    {
        Schema::table('user_fields', function (Blueprint $table) {
            $table->dropColumn('permissions_id');
        });
    }
};
