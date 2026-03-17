<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_systems', function (Blueprint $table) {
            $table->boolean('is_enabled')
                ->default(true)
                ->after('test_mode')
                ->comment('Включена ли платёжная система для партнёра');
        });
    }

    public function down(): void
    {
        Schema::table('payment_systems', function (Blueprint $table) {
            $table->dropColumn('is_enabled');
        });
    }
};
