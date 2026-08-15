<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_table_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('page_length')->nullable()->after('columns');
        });
    }

    public function down(): void
    {
        Schema::table('user_table_settings', function (Blueprint $table) {
            $table->dropColumn('page_length');
        });
    }
};
