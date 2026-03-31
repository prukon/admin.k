<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerAccessesTable extends Migration
{
    public function up()
    {
        Schema::create('partner_accesses', function (Blueprint $table) {
            $table->bigIncrements('id'); // Новый первичный ключ, автоинкремент

            $table->string('payment_id', 255); // Обновлен тип на varchar(255)

            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Внешний ключ
            $table->foreign('payment_id')->references('payment_id')->on('partner_payments')->onDelete('cascade');

            // Индексы
            $table->index('payment_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('partner_accesses');
    }
}
