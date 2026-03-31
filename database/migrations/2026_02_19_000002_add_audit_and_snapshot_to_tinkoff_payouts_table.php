<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tinkoff_payouts')) {
            return;
        }

        Schema::table('tinkoff_payouts', function (Blueprint $t) {
            // Источник создания выплаты:
            // - auto: создано автоматикой после CONFIRMED
            // - manual: "Выплатить сейчас"
            // - delayed: "Отложить до…"
            // - scheduled: системный запуск (если когда-то появится отдельный планировщик создания)
            if (!Schema::hasColumn('tinkoff_payouts', 'source')) {
                $t->string('source', 20)->nullable()->index()->after('status');
            }

            if (!Schema::hasColumn('tinkoff_payouts', 'initiated_by_user_id')) {
                $t->unsignedBigInteger('initiated_by_user_id')->nullable()->index()->after('source');
            }

            if (!Schema::hasColumn('tinkoff_payouts', 'payer_user_id')) {
                $t->unsignedBigInteger('payer_user_id')->nullable()->index()->after('initiated_by_user_id');
            }

            // Snapshot расчёта на момент создания выплаты (в копейках)
            if (!Schema::hasColumn('tinkoff_payouts', 'gross_amount')) {
                $t->bigInteger('gross_amount')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('tinkoff_payouts', 'bank_accept_fee')) {
                $t->bigInteger('bank_accept_fee')->nullable()->after('gross_amount');
            }
            if (!Schema::hasColumn('tinkoff_payouts', 'bank_payout_fee')) {
                $t->bigInteger('bank_payout_fee')->nullable()->after('bank_accept_fee');
            }
            if (!Schema::hasColumn('tinkoff_payouts', 'platform_fee')) {
                $t->bigInteger('platform_fee')->nullable()->after('bank_payout_fee');
            }
            if (!Schema::hasColumn('tinkoff_payouts', 'net_amount')) {
                $t->bigInteger('net_amount')->nullable()->after('platform_fee');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tinkoff_payouts')) {
            return;
        }

        Schema::table('tinkoff_payouts', function (Blueprint $t) {
            foreach ([
                'source',
                'initiated_by_user_id',
                'payer_user_id',
                'gross_amount',
                'bank_accept_fee',
                'bank_payout_fee',
                'platform_fee',
                'net_amount',
            ] as $col) {
                if (Schema::hasColumn('tinkoff_payouts', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};

