<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogsTable extends Migration
{
    public function up()
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->bigIncrements('id'); // Первичный ключ, автоинкремент
            $table->integer('type'); // Целочисленное поле для типа лога
//            $table->integer('action')->nullable()->after('type'); // Добавление нового столбца после столбца 'type'
            $table->integer('author_id'); // Целочисленное поле для идентификатора автора
            $table->text('description'); // Текстовое поле для описания
            $table->timestamp('created_at')->useCurrent(); // Время создания
        });
    }

    public function down()
    {
        Schema::dropIfExists('logs');
    }
}
