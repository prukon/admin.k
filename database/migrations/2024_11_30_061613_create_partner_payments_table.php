<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerPaymentsTable extends Migration
{
    public function up()
    {
        Schema::create('partner_payments', function (Blueprint $table) {
            $table->bigIncrements('id'); // Новый первичный ключ, автоинкремент

//            $table->string('payment_id', 255); // Изменен тип на varchar(255)
            $table->string('payment_id', 255)->unique(); // Добавлен уникальный индекс

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('user_id');

            $table->decimal('amount', 10, 2);
            $table->timestamp('payment_date');

            $table->string('payment_method');
            $table->enum('payment_status', ['pending', 'succeeded', 'canceled']); // статус платежа

            $table->text('description')->nullable();

            $table->timestamps(); // created_at и updated_at

            $table->softDeletes(); // deleted_at для безопасного удаления

            // Установка внешних ключей
            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');


            // Индексы
            $table->index('partner_id');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_payments');
    }
}
