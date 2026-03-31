<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tinkoff_commission_rules', function (Blueprint $t) {
            // Комиссия банка за прием платежа (эквайринг)
            if (!Schema::hasColumn('tinkoff_commission_rules', 'acquiring_percent')) {
                $t->decimal('acquiring_percent', 5, 2)->nullable()->after('method');
            }
            if (!Schema::hasColumn('tinkoff_commission_rules', 'acquiring_min_fixed')) {
                $t->decimal('acquiring_min_fixed', 10, 2)->nullable()->after('acquiring_percent');
            }

            // Комиссия банка за выплату партнёру (payout)
            if (!Schema::hasColumn('tinkoff_commission_rules', 'payout_percent')) {
                $t->decimal('payout_percent', 5, 2)->nullable()->after('acquiring_min_fixed');
            }
            if (!Schema::hasColumn('tinkoff_commission_rules', 'payout_min_fixed')) {
                $t->decimal('payout_min_fixed', 10, 2)->nullable()->after('payout_percent');
            }

            // Комиссия платформы
            if (!Schema::hasColumn('tinkoff_commission_rules', 'platform_percent')) {
                $t->decimal('platform_percent', 5, 2)->nullable()->after('payout_min_fixed');
            }
            if (!Schema::hasColumn('tinkoff_commission_rules', 'platform_min_fixed')) {
                $t->decimal('platform_min_fixed', 10, 2)->nullable()->after('platform_percent');
            }
        });

        // Backfill (бережно): переносим старые percent/min_fixed в platform_*,
        // а acquiring/payout выставляем дефолтами, если они пустые.
        $rows = DB::table('tinkoff_commission_rules')->get(['id', 'percent', 'min_fixed', 'acquiring_percent', 'acquiring_min_fixed', 'payout_percent', 'payout_min_fixed', 'platform_percent', 'platform_min_fixed']);
        foreach ($rows as $r) {
            $upd = [];

            if ($r->platform_percent === null && $r->percent !== null) {
                $upd['platform_percent'] = $r->percent;
            }
            if ($r->platform_min_fixed === null && $r->min_fixed !== null) {
                $upd['platform_min_fixed'] = $r->min_fixed;
            }

            if ($r->acquiring_percent === null) {
                $upd['acquiring_percent'] = 2.49;
            }
            if ($r->acquiring_min_fixed === null) {
                $upd['acquiring_min_fixed'] = 3.49;
            }

            if ($r->payout_percent === null) {
                $upd['payout_percent'] = 0.10;
            }
            if ($r->payout_min_fixed === null) {
                $upd['payout_min_fixed'] = 0.00;
            }

            if ($upd) {
                DB::table('tinkoff_commission_rules')->where('id', $r->id)->update($upd);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tinkoff_commission_rules', function (Blueprint $t) {
            foreach ([
                'acquiring_percent',
                'acquiring_min_fixed',
                'payout_percent',
                'payout_min_fixed',
                'platform_percent',
                'platform_min_fixed',
            ] as $col) {
                if (Schema::hasColumn('tinkoff_commission_rules', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};

