<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMyLogsTable extends Migration
{
    /**
     * Запускаем миграцию.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('my_logs', function (Blueprint $table) {
            $table->bigIncrements('id');        // Первичный ключ, автоинкремент
            $table->integer('type');            // Целочисленное поле для типа лога
            $table->integer('action')->nullable(); // Добавление нового столбца после столбца 'type'
            $table->integer('author_id');       // ID автора (пользователя)
            $table->text('description');        // Текстовое поле для описания
            $table->timestamp('created_at')->useCurrent(); // Время создания
        });
    }

    /**
     * Откат миграции (удаляем таблицу).
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('my_logs');
    }
}