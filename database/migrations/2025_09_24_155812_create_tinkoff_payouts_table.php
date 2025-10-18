<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tinkoff_payouts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('payment_id')->nullable()->index(); // если 1:1
            $t->unsignedBigInteger('partner_id')->index();
            $t->string('deal_id')->index();
            $t->bigInteger('amount'); // копейки к выплате
            $t->boolean('is_final')->default(false);
            $t->enum('status', ['INITIATED','CREDIT_CHECKING','COMPLETED','REJECTED','CHECKED'])->default('INITIATED');
            $t->string('tinkoff_payout_payment_id')->nullable()->index(); // из e2c/v2/Init
            $t->dateTime('when_to_run')->nullable(); // «Отложить до…»
            $t->json('payload_init')->nullable();
            $t->json('payload_payment')->nullable();
            $t->json('payload_state')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('tinkoff_payouts');
    }
};
