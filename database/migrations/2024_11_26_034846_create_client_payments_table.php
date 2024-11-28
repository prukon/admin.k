<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientPaymentsTable extends Migration
{
    /**
     * Запуск миграции.
     *
     * @return void
     */
        public function up()
        {
            Schema::create('client_payments', function (Blueprint $table) {
                $table->id(); // id (PK)
                $table->unsignedBigInteger('client_id'); // ссылка на клиента
                $table->unsignedBigInteger('user_id'); // ссылка на пользователя, который провёл платеж
                $table->string('payment_id'); // ID платежа от платёжной системы
                $table->decimal('amount', 10, 2); // сумма платежа
                $table->dateTime('payment_date'); // дата и время платежа
                $table->string('payment_method'); // способ оплаты
                $table->enum('payment_status', ['pending', 'succeeded', 'canceled']); // статус платежа
                $table->timestamps(); // created_at и updated_at
                $table->softDeletes(); // мягкое удаление (deleted_at)

                // Внешние ключи и индексы
                $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

                // Индексы для ускорения запросов
                $table->index('payment_id');
                $table->index('payment_status');
            });
        }

    /**
     * Откат миграции.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client_payments');
    }
}
