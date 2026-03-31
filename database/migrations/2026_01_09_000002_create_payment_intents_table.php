<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('partner_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Пока используем для Robokassa; позже можно расширить на других провайдеров.
            $table->string('provider', 50)->default('robokassa')->index();

            // pending|paid|failed|cancelled
            $table->string('status', 20)->default('pending')->index();

            // Сумма, которую мы ожидали получить по intent.
            $table->decimal('out_sum', 15, 2);

            // Значение, которое отправляем в Robokassa как Shp_paymentDate (может быть датой или строкой, напр. "Клубный взнос").
            $table->string('payment_date')->nullable();

            // Свободные метаданные (например, сырой receipt, описание, любые служебные поля).
            $table->text('meta')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};


