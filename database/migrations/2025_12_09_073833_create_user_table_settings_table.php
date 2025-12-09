<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_table_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // ключ таблицы, чтобы ты мог хранить настройки и для других таблиц
            $table->string('table_key'); // например: 'users_index'
            $table->json('columns')->nullable(); // здесь JSON с видимостью колонок
            $table->timestamps();

            $table->unique(['user_id', 'table_key']);

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_table_settings');
    }
};

