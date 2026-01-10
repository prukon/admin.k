<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payables', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('partner_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            // monthly_fee | club_fee | uniform | camp | ...
            $table->string('type', 50)->index();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('RUB');

            // pending | paid | cancelled | refunded | partially_refunded
            $table->string('status', 30)->default('pending')->index();

            // Для monthly_fee — фиксируем месяц (и также дублируем в meta['month'])
            $table->date('month')->nullable()->index();

            // meta (JSON в text), например: month, размер формы, смена лагеря, комментарии и т.п.
            $table->text('meta')->nullable();

            $table->timestamp('paid_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payables');
    }
};


