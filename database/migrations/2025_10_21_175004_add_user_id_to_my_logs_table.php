<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляем поле user_id в my_logs.
     */
    public function up(): void
    {
        Schema::table('my_logs', function (Blueprint $table) {
            // Добавляем nullable user_id после partner_id для логического порядка
            $table->unsignedBigInteger('user_id')->nullable()->after('partner_id');

            // Индекс для ускоренного поиска по пользователю
            $table->index('user_id', 'idx_my_logs_user_id');
        });
    }

    /**
     * Откат изменений.
     */
    public function down(): void
    {
        Schema::table('my_logs', function (Blueprint $table) {
            $table->dropIndex('idx_my_logs_user_id');
            $table->dropColumn('user_id');
        });
    }
};
