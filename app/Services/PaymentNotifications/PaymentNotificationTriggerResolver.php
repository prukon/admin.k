<?php

declare(strict_types=1);

namespace App\Services\PaymentNotifications;

use App\Models\PaymentNotificationRule;
use Carbon\CarbonImmutable;

/**
 * Определяет, срабатывает ли правило в календарный день (Europe/Moscow)
 * и какой биллинг-месяц (new_month = YYYY-MM-01) при этом целевой.
 */
final class PaymentNotificationTriggerResolver
{
    public const TIMEZONE = 'Europe/Moscow';

    /**
     * @return array{fires: bool, billing_month: ?string, reason: ?string}
     */
    public function resolve(PaymentNotificationRule $rule, CarbonImmutable $todayMsk): array
    {
        $today = $todayMsk->timezone(self::TIMEZONE)->startOfDay();

        if ($rule->usesDayOfMonthTrigger()) {
            return $this->resolveDayOfMonth($rule, $today);
        }

        if ($rule->usesDaysAfterOverdueTrigger()) {
            return $this->resolveDaysAfterOverdue($rule, $today);
        }

        return [
            'fires' => false,
            'billing_month' => null,
            'reason' => 'unknown_trigger_type',
        ];
    }

    /**
     * @return array{fires: bool, billing_month: ?string, reason: ?string}
     */
    private function resolveDayOfMonth(PaymentNotificationRule $rule, CarbonImmutable $today): array
    {
        $day = (int) $rule->trigger_value;
        if ($day < 1 || $day > 31) {
            return ['fires' => false, 'billing_month' => null, 'reason' => 'invalid_trigger_value'];
        }

        // В коротких месяцах день 29–31 просто не наступает — правило не срабатывает.
        if ((int) $today->day !== $day) {
            return ['fires' => false, 'billing_month' => null, 'reason' => 'day_mismatch'];
        }

        $offset = (int) $rule->billing_month_offset;
        if (! in_array($offset, [0, -1], true)) {
            return ['fires' => false, 'billing_month' => null, 'reason' => 'invalid_billing_month_offset'];
        }

        $billingMonth = $today->startOfMonth()->addMonths($offset)->toDateString();

        return [
            'fires' => true,
            'billing_month' => $billingMonth,
            'reason' => null,
        ];
    }

    /**
     * Просрочка для биллинг-месяца M начинается с 1-го числа следующего месяца.
     * N-й день просрочки = (1-е число M+1) + (N-1) дней.
     *
     * @return array{fires: bool, billing_month: ?string, reason: ?string}
     */
    private function resolveDaysAfterOverdue(PaymentNotificationRule $rule, CarbonImmutable $today): array
    {
        $n = (int) $rule->trigger_value;
        if ($n < 1 || $n > 90) {
            return ['fires' => false, 'billing_month' => null, 'reason' => 'invalid_trigger_value'];
        }

        // Дата, которая должна быть 1-м числом месяца старта просрочки.
        $overdueStartCandidate = $today->subDays($n - 1);
        if ((int) $overdueStartCandidate->day !== 1) {
            return ['fires' => false, 'billing_month' => null, 'reason' => 'not_overdue_day_n'];
        }

        $billingMonth = $overdueStartCandidate->subMonthNoOverflow()->startOfMonth()->toDateString();

        return [
            'fires' => true,
            'billing_month' => $billingMonth,
            'reason' => null,
        ];
    }
}
