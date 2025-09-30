<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tinkoff_payments', function (Blueprint $t) {
            $t->id();
            $t->string('order_id')->unique();
            $t->unsignedBigInteger('partner_id')->index();
            $t->bigInteger('amount'); // копейки
            $t->enum('method', ['card','sbp','tpay'])->nullable();
            $t->enum('status', ['NEW','FORM','CONFIRMED','REJECTED','CANCELED'])->default('NEW');
            $t->string('tinkoff_payment_id')->nullable()->index();
            $t->string('deal_id')->nullable()->index(); // SpAccumulationId
            $t->text('payment_url')->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('canceled_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('tinkoff_payments');
    }
};
