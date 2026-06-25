<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Models\TinkoffCommissionRule;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Автовыплата в правилах комиссий: pickForPartner и сводка для UI.
 */
final class TinkoffCommissionRuleAutoPayoutModelTest extends CrmTestCase
{
    public function test_pick_for_partner_uses_method_specific_auto_payout_settings(): void
    {
        TinkoffCommissionRule::create($this->baseRule([
            'partner_id' => $this->partner->id,
            'method' => null,
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 24,
        ]));

        TinkoffCommissionRule::create($this->baseRule([
            'partner_id' => $this->partner->id,
            'method' => 'sbp',
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 0,
        ]));

        $rule = TinkoffCommissionRule::pickForPartner((int) $this->partner->id, 'sbp');

        $this->assertFalse((bool) $rule->auto_payout_enabled);
        $this->assertSame(0, (int) $rule->auto_payout_delay_hours);
    }

    public function test_pick_for_partner_falls_back_to_partner_wide_rule_without_method(): void
    {
        TinkoffCommissionRule::create($this->baseRule([
            'partner_id' => $this->partner->id,
            'method' => null,
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 48,
        ]));

        $rule = TinkoffCommissionRule::pickForPartner((int) $this->partner->id, 'card');

        $this->assertTrue((bool) $rule->auto_payout_enabled);
        $this->assertSame(48, (int) $rule->auto_payout_delay_hours);
    }

    public function test_pick_for_partner_returns_defaults_when_no_rules(): void
    {
        $rule = TinkoffCommissionRule::pickForPartner((int) $this->partner->id, 'card');

        $this->assertFalse((bool) $rule->auto_payout_enabled);
        $this->assertSame(0, (int) $rule->auto_payout_delay_hours);
        $this->assertSame(2.0, (float) $rule->platform_percent);
    }

    public function test_auto_payout_summary_for_partner_lists_all_rules_by_method(): void
    {
        TinkoffCommissionRule::create($this->baseRule([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'auto_payout_enabled' => true,
            'auto_payout_delay_hours' => 12,
        ]));
        TinkoffCommissionRule::create($this->baseRule([
            'partner_id' => $this->partner->id,
            'method' => 'sbp',
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 0,
        ]));

        $summary = TinkoffCommissionRule::autoPayoutSummaryForPartner((int) $this->partner->id);

        $this->assertCount(2, $summary);
        $this->assertSame('карта', $summary[0]['method']);
        $this->assertTrue($summary[0]['enabled']);
        $this->assertSame(12, $summary[0]['delay_hours']);
        $this->assertSame('СБП', $summary[1]['method']);
        $this->assertFalse($summary[1]['enabled']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function baseRule(array $overrides = []): array
    {
        return array_merge([
            'acquiring_percent' => 2.5,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 0.1,
            'payout_min_fixed' => 0,
            'platform_percent' => 2,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ], $overrides);
    }
}
