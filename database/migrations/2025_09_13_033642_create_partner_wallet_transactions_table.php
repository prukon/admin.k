<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partner_wallet_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('user_id')->nullable();

            $table->enum('type', ['credit','debit'])->default('credit'); // пополнение/списание
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('RUB');

            $table->enum('provider', ['yookassa','manual','adjustment','refund'])->default('yookassa');
            $table->string('payment_id')->nullable(); // id платежа в YooKassa
            $table->enum('status', ['pending','succeeded','canceled','failed'])->default('pending');

            $table->string('description')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['partner_id','created_at']);
            $table->index(['provider','payment_id']);
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            // user_id указывать на users, если нужно:
            // $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('partner_wallet_transactions');
    }
};
