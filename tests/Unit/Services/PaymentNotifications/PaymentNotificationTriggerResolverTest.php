<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PaymentNotifications;

use App\Models\PaymentNotificationRule;
use App\Services\PaymentNotifications\PaymentNotificationTriggerResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class PaymentNotificationTriggerResolverTest extends TestCase
{
    private PaymentNotificationTriggerResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PaymentNotificationTriggerResolver();
    }

    public function test_day_of_month_current_month_fires_on_exact_day(): void
    {
        $rule = $this->makeRule([
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
        ]);

        $result = $this->resolver->resolve($rule, CarbonImmutable::parse('2026-09-05', 'Europe/Moscow'));

        $this->assertTrue($result['fires']);
        $this->assertSame('2026-09-01', $result['billing_month']);
    }

    public function test_day_of_month_previous_month_offset(): void
    {
        $rule = $this->makeRule([
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 1,
            'billing_month_offset' => -1,
        ]);

        $result = $this->resolver->resolve($rule, CarbonImmutable::parse('2026-10-01', 'Europe/Moscow'));

        $this->assertTrue($result['fires']);
        $this->assertSame('2026-09-01', $result['billing_month']);
    }

    public function test_day_of_month_does_not_fire_on_other_day(): void
    {
        $rule = $this->makeRule([
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
        ]);

        $result = $this->resolver->resolve($rule, CarbonImmutable::parse('2026-09-06', 'Europe/Moscow'));

        $this->assertFalse($result['fires']);
        $this->assertNull($result['billing_month']);
    }

    public function test_days_after_overdue_third_day_for_september_is_october_3(): void
    {
        $rule = $this->makeRule([
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAYS_AFTER_OVERDUE,
            'trigger_value' => 3,
            'billing_month_offset' => 0,
        ]);

        $result = $this->resolver->resolve($rule, CarbonImmutable::parse('2026-10-03', 'Europe/Moscow'));

        $this->assertTrue($result['fires']);
        $this->assertSame('2026-09-01', $result['billing_month']);
    }

    public function test_days_after_overdue_does_not_fire_on_wrong_calendar_day(): void
    {
        $rule = $this->makeRule([
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAYS_AFTER_OVERDUE,
            'trigger_value' => 3,
            'billing_month_offset' => 0,
        ]);

        $result = $this->resolver->resolve($rule, CarbonImmutable::parse('2026-10-04', 'Europe/Moscow'));

        $this->assertFalse($result['fires']);
        $this->assertSame('not_overdue_day_n', $result['reason']);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function makeRule(array $attrs): PaymentNotificationRule
    {
        $rule = new PaymentNotificationRule();
        $rule->forceFill(array_merge([
            'partner_id' => 1,
            'name' => 't',
            'is_enabled' => true,
            'schedule_types' => ['fixed'],
            'subject_template' => 's',
            'body_html_template' => 'b',
        ], $attrs));

        return $rule;
    }
}
