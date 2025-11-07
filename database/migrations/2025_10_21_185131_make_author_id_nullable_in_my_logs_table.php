<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Сделать author_id nullable в таблице my_logs.
     */
    public function up(): void
    {
        Schema::table('my_logs', function (Blueprint $table) {
            // Меняем существующий столбец author_id на nullable
            $table->unsignedBigInteger('author_id')->nullable()->change();
        });
    }

    /**
     * Откат изменений.
     */
    public function down(): void
    {
        Schema::table('my_logs', function (Blueprint $table) {
            // Возвращаем обратно в NOT NULL
            $table->unsignedBigInteger('author_id')->nullable(false)->change();
        });
    }
};
