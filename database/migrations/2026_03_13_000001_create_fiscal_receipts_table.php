<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_receipts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id')->index();
            $table->unsignedBigInteger('payment_intent_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('payable_id')->nullable()->index();

            // cloudkassir
            $table->string('provider', 50)->default('cloudkassir')->index();

            // income / income_return
            $table->string('type', 30)->index();

            // pending / queued / processed / error
            $table->string('status', 30)->default('pending')->index();

            $table->decimal('amount', 15, 2);

            // твои внутренние идентификаторы / трассировка
            $table->string('invoice_id')->nullable()->index();
            $table->string('account_id')->nullable()->index();

            // id чека в CloudKassir
            $table->string('external_id')->nullable()->unique();

            // полезно для дедупликации на уровне бизнеса:
            // один payable -> один income чек
            // один payment -> один income_return чек
            $table->string('idempotency_key')->nullable()->unique();

            // служебные payload
            $table->longText('request_payload')->nullable();
            $table->longText('response_payload')->nullable();
            $table->longText('webhook_payload')->nullable();

            // фискальные реквизиты
            $table->string('receipt_url')->nullable();
            $table->string('qr_code_url')->nullable();
            $table->string('document_number')->nullable();
            $table->string('session_number')->nullable();
            $table->string('number')->nullable();
            $table->string('fiscal_number')->nullable();
            $table->string('fiscal_sign')->nullable();
            $table->string('device_number')->nullable();
            $table->string('reg_number')->nullable();
            $table->string('ofd')->nullable();
            $table->dateTime('receipt_datetime')->nullable();

            // ошибка / warning
            $table->integer('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->text('warning_message')->nullable();

            $table->dateTime('queued_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('failed_at')->nullable();

            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->foreign('payment_intent_id')->references('id')->on('payment_intents')->nullOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->foreign('payable_id')->references('id')->on('payables')->nullOnDelete();

            $table->index(['partner_id', 'type', 'status'], 'fiscal_receipts_partner_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_receipts');
    }
};