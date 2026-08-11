<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Идемпотентность и служебный лог срабатываний правил уведомлений об оплате.
 * Фактические письма — в outgoing_email_logs (/admin/reports/emails).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('payment_notification_rule_id');
            $table->unsignedBigInteger('users_price_id');
            /** Календарная дата срабатывания (Europe/Moscow), без времени. */
            $table->date('trigger_date');
            /** pending | sent | failed | skipped */
            $table->string('status', 16)->default('pending');
            $table->string('skip_reason', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('outgoing_email_log_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->foreign('payment_notification_rule_id', 'pn_dispatch_rule_fk')
                ->references('id')->on('payment_notification_rules')->cascadeOnDelete();
            $table->foreign('users_price_id', 'pn_dispatch_up_fk')
                ->references('id')->on('users_prices')->cascadeOnDelete();

            $table->unique(
                ['payment_notification_rule_id', 'users_price_id', 'trigger_date'],
                'pn_dispatch_rule_up_date_uidx'
            );
            $table->index(['partner_id', 'trigger_date'], 'pn_dispatch_partner_date_idx');
            $table->index(['status'], 'pn_dispatch_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notification_dispatches');
    }
};
