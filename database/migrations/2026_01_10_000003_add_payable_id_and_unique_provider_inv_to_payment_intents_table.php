<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_intents', 'payable_id')) {
                $table->unsignedBigInteger('payable_id')->nullable()->after('user_id');
                $table->index(['payable_id'], 'pi_payable_id_idx');
            }
        });

        // provider+provider_inv_id должен быть уникальным (идемпотентность вебхуков).
        // Предполагаем, что индекс pi_provider_inv_idx был создан предыдущей миграцией.
        Schema::table('payment_intents', function (Blueprint $table) {
            // dropIndex безопасен, если индекс существует. Если будет проблема на чистой базе — поправим.
            try {
                $table->dropIndex('pi_provider_inv_idx');
            } catch (\Throwable $e) {
                // ignore
            }
            $table->unique(['provider', 'provider_inv_id'], 'pi_provider_inv_uq');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            try {
                $table->dropUnique('pi_provider_inv_uq');
            } catch (\Throwable $e) {
                // ignore
            }
            // попытка вернуть обычный индекс
            try {
                $table->index(['provider', 'provider_inv_id'], 'pi_provider_inv_idx');
            } catch (\Throwable $e) {
                // ignore
            }

            if (Schema::hasColumn('payment_intents', 'payable_id')) {
                $table->dropIndex('pi_payable_id_idx');
                $table->dropColumn('payable_id');
            }
        });
    }
};


