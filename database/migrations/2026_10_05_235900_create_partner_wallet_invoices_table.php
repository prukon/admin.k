<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_wallet_invoices')) {
            return;
        }

        Schema::create('partner_wallet_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('legal_entity_id')->nullable();
            $table->string('number', 64);
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('RUB');
            $table->string('item_name');
            $table->string('payment_purpose', 500);
            $table->string('vat_note');
            $table->date('issued_on');
            $table->json('buyer');
            $table->json('seller');
            $table->timestamps();

            $table->unique('number');
            $table->index(['partner_id', 'issued_on']);
            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('legal_entity_id')->references('id')->on('partner_legal_entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_wallet_invoices');
    }
};
