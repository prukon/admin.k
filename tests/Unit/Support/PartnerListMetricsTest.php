<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PartnerListMetrics;
use Carbon\Carbon;
use Tests\TestCase;

final class PartnerListMetricsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_month_column_labels_are_current_previous_and_two_months_ago(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 21:00:00', 'Europe/Moscow'));

        $this->assertSame(
            [0 => 'Август', 1 => 'Июль', 2 => 'Июнь'],
            PartnerListMetrics::monthColumnLabels()
        );
    }

    public function test_month_column_labels_cross_year_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-05 10:00:00', 'Europe/Moscow'));

        $this->assertSame(
            [0 => 'Январь', 1 => 'Декабрь', 2 => 'Ноябрь'],
            PartnerListMetrics::monthColumnLabels()
        );
    }

    public function test_month_windows_are_half_open_by_operation_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 21:00:00', 'Europe/Moscow'));

        $this->assertSame([
            0 => ['start' => '2026-08-01 00:00:00', 'end' => '2026-09-01 00:00:00'],
            1 => ['start' => '2026-07-01 00:00:00', 'end' => '2026-08-01 00:00:00'],
            2 => ['start' => '2026-06-01 00:00:00', 'end' => '2026-07-01 00:00:00'],
        ], PartnerListMetrics::monthWindows());
    }

    public function test_cents_to_rubles_rounds_to_two_decimals(): void
    {
        $this->assertSame(1500.0, PartnerListMetrics::centsToRubles(150000));
        $this->assertSame(10.5, PartnerListMetrics::centsToRubles(1050));
        $this->assertSame(0.0, PartnerListMetrics::centsToRubles(0));
    }

    public function test_order_by_expression_maps_json_keys_to_sql_aliases(): void
    {
        $this->assertSame('active_users_count', PartnerListMetrics::orderByExpression('active_users_count'));
        $this->assertSame('signed_contracts_count', PartnerListMetrics::orderByExpression('signed_contracts_count'));
        $this->assertSame('turnover_all_cents', PartnerListMetrics::orderByExpression('turnover_all'));
        $this->assertSame('platform_commission_all_cents', PartnerListMetrics::orderByExpression('platform_commission_all'));
        $this->assertSame('turnover_month_0_cents', PartnerListMetrics::orderByExpression('turnover_month_0'));
        $this->assertSame('platform_commission_month_0_cents', PartnerListMetrics::orderByExpression('platform_commission_month_0'));
        $this->assertSame('turnover_month_1_cents', PartnerListMetrics::orderByExpression('turnover_month_1'));
        $this->assertSame('platform_commission_month_1_cents', PartnerListMetrics::orderByExpression('platform_commission_month_1'));
        $this->assertSame('turnover_month_2_cents', PartnerListMetrics::orderByExpression('turnover_month_2'));
        $this->assertSame('platform_commission_month_2_cents', PartnerListMetrics::orderByExpression('platform_commission_month_2'));
        $this->assertNull(PartnerListMetrics::orderByExpression('title'));
    }
}
