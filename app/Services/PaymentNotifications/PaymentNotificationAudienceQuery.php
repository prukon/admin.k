<?php

declare(strict_types=1);

namespace App\Services\PaymentNotifications;

use App\Models\PaymentNotificationRule;
use App\Models\UserPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Выбор строк users_prices под правило уведомления.
 */
final class PaymentNotificationAudienceQuery
{
    /**
     * Неоплаченные начисления партнёра за биллинг-месяц с нужными типами абонементов.
     *
     * @param  list<string>  $scheduleTypes
     * @return Collection<int, UserPrice>
     */
    public function unpaidForPartnerMonth(
        int $partnerId,
        string $billingMonthYmd,
        array $scheduleTypes,
    ): Collection {
        if ($scheduleTypes === []) {
            return collect();
        }

        return $this->baseQuery($partnerId, $billingMonthYmd, $scheduleTypes)->get();
    }

    /**
     * @param  list<string>  $scheduleTypes
     * @return Builder<UserPrice>
     */
    public function baseQuery(int $partnerId, string $billingMonthYmd, array $scheduleTypes): Builder
    {
        $scheduleTypes = array_values(array_intersect(
            $scheduleTypes,
            PaymentNotificationRule::ALLOWED_SCHEDULE_TYPES
        ));

        return UserPrice::query()
            ->with([
                'user:id,partner_id,email,name,lastname,is_enabled',
                'team:id,partner_id,title',
                'lessonPackage:id,partner_id,name,schedule_type',
            ])
            ->select('users_prices.*')
            ->join('users', 'users.id', '=', 'users_prices.user_id')
            ->join('lesson_packages', 'lesson_packages.id', '=', 'users_prices.lesson_package_id')
            ->where('users.partner_id', $partnerId)
            ->whereDate('users_prices.new_month', $billingMonthYmd)
            ->where('users_prices.price_cents', '>', 0)
            ->whereRaw($this->effectiveUnpaidSql())
            ->whereIn('lesson_packages.schedule_type', $scheduleTypes)
            ->whereNotNull('users_prices.lesson_package_id');
    }

    public function effectiveUnpaidSql(): string
    {
        return '(CASE WHEN users_prices.is_manual_paid IS NOT NULL'
            .' THEN users_prices.is_manual_paid ELSE users_prices.is_paid END) = 0';
    }
}
