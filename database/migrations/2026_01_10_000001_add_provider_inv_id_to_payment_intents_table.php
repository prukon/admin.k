<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_intents', 'provider_inv_id')) {
                // Внешний InvId у провайдера (Robokassa). Нужен, чтобы избежать конфликтов с историческими InvId.
                $table->unsignedBigInteger('provider_inv_id')->nullable()->after('provider');
                $table->index(['provider', 'provider_inv_id'], 'pi_provider_inv_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            if (Schema::hasColumn('payment_intents', 'provider_inv_id')) {
                $table->dropIndex('pi_provider_inv_idx');
                $table->dropColumn('provider_inv_id');
            }
        });
    }
};


