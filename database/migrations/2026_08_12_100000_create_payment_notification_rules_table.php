<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Правила email-уведомлений об оплате месячных начислений (users_prices).
 * Конструктор на вкладке «Установка цен → Уведомления».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_notification_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->string('name', 160);
            $table->boolean('is_enabled')->default(true);
            /** day_of_month | days_after_overdue */
            $table->string('trigger_type', 32);
            /** День месяца (1–31) или N дней после старта просрочки (1–90). */
            $table->unsignedSmallInteger('trigger_value');
            /**
             * Смещение биллинг-месяца для day_of_month: 0 = текущий календарный месяц (MSK),
             * -1 = предыдущий. Для days_after_overdue игнорируется (месяц вычисляется).
             */
            $table->tinyInteger('billing_month_offset')->default(0);
            /** JSON-массив типов: fixed, flexible, postpay */
            $table->json('schedule_types');
            $table->string('subject_template', 255);
            $table->mediumText('body_html_template');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
            $table->index(['partner_id', 'is_enabled'], 'pnr_partner_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notification_rules');
    }
};
