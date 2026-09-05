<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tinkoff_payments', function (Blueprint $table) {
            $table->string('channel', 32)->default('multisplit')->after('method');
        });

        DB::statement("ALTER TABLE partner_wallet_transactions MODIFY COLUMN provider VARCHAR(32) NOT NULL DEFAULT 'yookassa'");

        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->string('source', 32)->default('marketplace')->after('provider');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->index()->after('payable_id');
            $table->unsignedBigInteger('partner_payment_id')->nullable()->index()->after('wallet_transaction_id');

            $table->foreign('wallet_transaction_id')
                ->references('id')
                ->on('partner_wallet_transactions')
                ->nullOnDelete();
            $table->foreign('partner_payment_id')
                ->references('id')
                ->on('partner_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->dropForeign(['wallet_transaction_id']);
            $table->dropForeign(['partner_payment_id']);
            $table->dropColumn(['source', 'wallet_transaction_id', 'partner_payment_id']);
        });

        DB::statement("ALTER TABLE partner_wallet_transactions MODIFY COLUMN provider ENUM('yookassa','manual','adjustment','refund') NOT NULL DEFAULT 'yookassa'");

        Schema::table('tinkoff_payments', function (Blueprint $table) {
            $table->dropColumn('channel');
        });
    }
};
