<?php

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayment;
use App\Services\Tinkoff\TinkoffPayoutsService;
use Tests\Feature\Crm\CrmTestCase;

class TbankCommissionRulePriorityTest extends CrmTestCase
{
    public function test_default_platform_percent_is_2_when_rule_is_missing(): void
    {
        $svc = app(TinkoffPayoutsService::class);

        $payment = TinkoffPayment::create([
            'order_id' => 'order-comm-1',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
        ]);

        $b = $svc->breakdownForPayment($payment);

        // платформа 2% от 100.00 = 2.00 => 200 коп.
        $this->assertSame(200, (int) ($b['platformFee'] ?? 0));
    }

    public function test_rule_priority_partner_and_method_over_global(): void
    {
        $svc = app(TinkoffPayoutsService::class);

        // глобальное правило (platform 9%)
        TinkoffCommissionRule::create([
            'partner_id' => null,
            'method' => null,
            'acquiring_percent' => 2.49,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 0.10,
            'payout_min_fixed' => 0,
            'platform_percent' => 9.0,
            'platform_min_fixed' => 0,
            'is_enabled' => 1,
        ]);

        // правило для партнёра (platform 3%)
        TinkoffCommissionRule::create([
            'partner_id' => $this->partner->id,
            'method' => null,
            'acquiring_percent' => 2.49,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 0.10,
            'payout_min_fixed' => 0,
            'platform_percent' => 3.0,
            'platform_min_fixed' => 0,
            'is_enabled' => 1,
        ]);

        // правило для партнёра + метода (platform 1%)
        TinkoffCommissionRule::create([
            'partner_id' => $this->partner->id,
            'method' => 'sbp',
            'acquiring_percent' => 2.49,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 0.10,
            'payout_min_fixed' => 0,
            'platform_percent' => 1.0,
            'platform_min_fixed' => 0,
            'is_enabled' => 1,
        ]);

        $payment = TinkoffPayment::create([
            'order_id' => 'order-comm-2',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'sbp',
            'status' => 'CONFIRMED',
        ]);

        $b = $svc->breakdownForPayment($payment);

        // ожидание: взято правило partner+method => 1% от 100.00 = 1.00 => 100 коп.
        $this->assertSame(100, (int) ($b['platformFee'] ?? 0));
    }
}

