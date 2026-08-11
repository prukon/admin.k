<?php

declare(strict_types=1);

namespace App\Services\PaymentNotifications;

use App\Jobs\SendPaymentNotificationDispatchJob;
use App\Models\PaymentNotificationDispatch;
use App\Models\PaymentNotificationRule;
use App\Models\UserPrice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ежедневный прогон правил: создаёт записи идемпотентности и ставит отправку в очередь.
 */
final class PaymentNotificationDispatchService
{
    public function __construct(
        private readonly PaymentNotificationTriggerResolver $triggerResolver,
        private readonly PaymentNotificationAudienceQuery $audienceQuery,
    ) {
    }

    /**
     * @return array{rules_checked: int, rules_fired: int, dispatches_created: int, skipped_existing: int}
     */
    public function dispatchForDate(?CarbonImmutable $dateMsk = null): array
    {
        $today = ($dateMsk ?? CarbonImmutable::now(PaymentNotificationTriggerResolver::TIMEZONE))
            ->timezone(PaymentNotificationTriggerResolver::TIMEZONE)
            ->startOfDay();

        $stats = [
            'rules_checked' => 0,
            'rules_fired' => 0,
            'dispatches_created' => 0,
            'skipped_existing' => 0,
        ];

        $rules = PaymentNotificationRule::query()
            ->where('is_enabled', true)
            ->orderBy('partner_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            $stats['rules_checked']++;

            $resolved = $this->triggerResolver->resolve($rule, $today);
            if (! $resolved['fires'] || $resolved['billing_month'] === null) {
                continue;
            }

            $stats['rules_fired']++;
            $scheduleTypes = $rule->normalizedScheduleTypes();
            if ($scheduleTypes === []) {
                continue;
            }

            $rows = $this->audienceQuery->unpaidForPartnerMonth(
                (int) $rule->partner_id,
                $resolved['billing_month'],
                $scheduleTypes
            );

            foreach ($rows as $userPrice) {
                $created = $this->createDispatchAndQueue($rule, $userPrice, $today);
                if ($created) {
                    $stats['dispatches_created']++;
                } else {
                    $stats['skipped_existing']++;
                }
            }
        }

        return $stats;
    }

    private function createDispatchAndQueue(
        PaymentNotificationRule $rule,
        UserPrice $userPrice,
        CarbonImmutable $triggerDate,
    ): bool {
        $email = trim((string) ($userPrice->user?->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->createSkippedDispatch(
                $rule,
                $userPrice,
                $triggerDate,
                'missing_or_invalid_email'
            );

            return true;
        }

        try {
            $dispatch = null;

            DB::transaction(function () use ($rule, $userPrice, $triggerDate, &$dispatch): void {
                $existing = PaymentNotificationDispatch::query()
                    ->where('payment_notification_rule_id', $rule->id)
                    ->where('users_price_id', $userPrice->id)
                    ->whereDate('trigger_date', $triggerDate->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return;
                }

                $dispatch = PaymentNotificationDispatch::query()->create([
                    'partner_id' => (int) $rule->partner_id,
                    'payment_notification_rule_id' => (int) $rule->id,
                    'users_price_id' => (int) $userPrice->id,
                    'trigger_date' => $triggerDate->toDateString(),
                    'status' => PaymentNotificationDispatch::STATUS_PENDING,
                ]);
            });

            if ($dispatch === null) {
                return false;
            }

            SendPaymentNotificationDispatchJob::dispatch((int) $dispatch->id);

            return true;
        } catch (Throwable $e) {
            // Гонка уникального индекса — уже есть запись.
            if ($this->isUniqueConstraintViolation($e)) {
                return false;
            }

            Log::error('[PaymentNotificationDispatchService] create failed', [
                'rule_id' => $rule->id,
                'users_price_id' => $userPrice->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function createSkippedDispatch(
        PaymentNotificationRule $rule,
        UserPrice $userPrice,
        CarbonImmutable $triggerDate,
        string $reason,
    ): void {
        try {
            PaymentNotificationDispatch::query()->firstOrCreate(
                [
                    'payment_notification_rule_id' => (int) $rule->id,
                    'users_price_id' => (int) $userPrice->id,
                    'trigger_date' => $triggerDate->toDateString(),
                ],
                [
                    'partner_id' => (int) $rule->partner_id,
                    'status' => PaymentNotificationDispatch::STATUS_SKIPPED,
                    'skip_reason' => $reason,
                ]
            );
        } catch (Throwable $e) {
            if (! $this->isUniqueConstraintViolation($e)) {
                Log::warning('[PaymentNotificationDispatchService] skip-record failed', [
                    'rule_id' => $rule->id,
                    'users_price_id' => $userPrice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function isUniqueConstraintViolation(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'pn_dispatch_rule_up_date_uidx')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed');
    }
}
