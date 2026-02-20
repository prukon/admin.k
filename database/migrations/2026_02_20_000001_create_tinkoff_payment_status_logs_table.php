<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tinkoff_payment_status_logs', function (Blueprint $t) {
            $t->id();

            // FK на нашу таблицу платежей (внутренний id)
            $t->unsignedBigInteger('tinkoff_payment_id')->index();

            // Scope/безопасность
            $t->unsignedBigInteger('partner_id')->index();

            // Откуда событие пришло
            $t->string('event_source', 20)->default('webhook')->index(); // webhook|get_state|manual_debug

            // Статусы (наша проекция + фактический статус банка)
            $t->string('from_status', 32)->nullable();
            $t->string('to_status', 32)->nullable();
            $t->string('bank_status', 32)->nullable();

            // Идентификаторы банка/заказа
            $t->string('bank_payment_id', 64)->nullable()->index(); // PaymentId от T‑Bank
            $t->string('order_id', 64)->nullable()->index();        // order_id из tinkoff_payments

            // Сырые данные события (webhook/ответ GetState)
            $t->json('payload')->nullable();

            // Время фиксации у нас
            $t->timestamps();

            // На случай наличия внешних FK — не навязываем cascade, чтобы не блокировать миграции.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tinkoff_payment_status_logs');
    }
};

