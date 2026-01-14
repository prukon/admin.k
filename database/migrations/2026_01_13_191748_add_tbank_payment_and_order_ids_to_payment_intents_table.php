<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            // Эти колонки могли быть добавлены вручную/другой миграцией.
            // Делаем миграцию идемпотентной, чтобы прод и тест не разъехались.
            if (!Schema::hasColumn('payment_intents', 'tbank_payment_id')) {
                $table->unsignedBigInteger('tbank_payment_id')->nullable()->after('provider_inv_id');
            }

            if (!Schema::hasColumn('payment_intents', 'tbank_order_id')) {
                $table->string('tbank_order_id', 128)->nullable()->after('tbank_payment_id');
            }

            // Индексы тоже стараемся добавить, но не падаем, если они уже есть.
            try {
                $table->index(['tbank_payment_id'], 'pi_tbank_payment_id_idx');
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $table->index(['tbank_order_id'], 'pi_tbank_order_id_idx');
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            try {
                $table->dropIndex('pi_tbank_payment_id_idx');
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $table->dropIndex('pi_tbank_order_id_idx');
            } catch (\Throwable $e) {
                // ignore
            }

            if (Schema::hasColumn('payment_intents', 'tbank_order_id')) {
                $table->dropColumn('tbank_order_id');
            }

            if (Schema::hasColumn('payment_intents', 'tbank_payment_id')) {
                $table->dropColumn('tbank_payment_id');
            }
        });
    }
};

