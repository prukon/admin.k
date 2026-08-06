<?php

declare(strict_types=1);

namespace App\Services\Postpay;

use App\Models\LessonPackage;
use App\Models\UserPrice;
use App\Support\Money;

/**
 * Синхронизация users_prices.price_cents для строк с шаблоном postpay.
 */
final class PostpayUsersPriceSync
{
    public function __construct(
        private readonly PostpayAmountCalculator $calculator,
    ) {
    }

    public function isLocked(UserPrice $row): bool
    {
        return (bool) $row->effective_is_paid;
    }

    /**
     * Обновить price_cents по журналу, если строка — postpay и не оплачена.
     *
     * @return array{synced: bool, locked: bool, visits: int, amount_cents: int}|null null если не postpay
     */
    public function syncRow(UserPrice $row): ?array
    {
        $package = $row->relationLoaded('lessonPackage')
            ? $row->lessonPackage
            : LessonPackage::query()->find($row->lesson_package_id);

        if (! $package || ! $package->isPostpay()) {
            return null;
        }

        $calc = $this->calculator->forUserPrice($row, $package);

        if ($this->isLocked($row)) {
            return [
                'synced' => false,
                'locked' => true,
                'visits' => $calc['visits'],
                'amount_cents' => (int) ($row->price_cents ?? 0),
            ];
        }

        $amountCents = $calc['amount_cents'];
        if ((int) ($row->price_cents ?? 0) !== $amountCents) {
            $row->price_cents = $amountCents;
            $row->save();
        }

        return [
            'synced' => true,
            'locked' => false,
            'visits' => $calc['visits'],
            'amount_cents' => $amountCents,
        ];
    }

    /**
     * После смены статуса в журнале: найти postpay-строки ученика за месяц и пересчитать.
     */
    public function syncAfterOccurrenceChange(
        int $partnerId,
        int $userId,
        string $occurrenceDateYmd,
        ?int $teamId = null
    ): void {
        $month = PostpayMonth::firstDayFromDate($occurrenceDateYmd);

        $query = UserPrice::query()
            ->where('user_id', $userId)
            ->whereDate('new_month', $month)
            ->whereNotNull('lesson_package_id')
            ->with(['lessonPackage']);

        if ($teamId !== null && $teamId > 0) {
            $query->where('team_id', $teamId);
        }

        $rows = $query->get()->filter(
            static fn (UserPrice $row) => $row->lessonPackage && $row->lessonPackage->isPostpay()
        );

        foreach ($rows as $row) {
            $this->syncRow($row);
        }
    }

    /**
     * Применить postpay-пакет к строке: lesson_package_id + пересчёт суммы.
     */
    public function applyPackageToRow(UserPrice $row, LessonPackage $package): bool
    {
        if (! $package->isPostpay()) {
            return false;
        }

        if ($this->isLocked($row)) {
            return false;
        }

        $row->lesson_package_id = (int) $package->id;
        $row->save();
        $row->setRelation('lessonPackage', $package);
        $this->syncRow($row);

        return true;
    }

    /**
     * @param  list<UserPrice>  $rows
     * @return list<UserPrice>
     */
    public function syncRows(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! $row instanceof UserPrice) {
                continue;
            }
            if (! $row->relationLoaded('lessonPackage')) {
                $row->load('lessonPackage');
            }
            $this->syncRow($row);
            $row->refresh();
            if (! $row->relationLoaded('lessonPackage')) {
                $row->load('lessonPackage');
            }
            $out[] = $row;
        }

        return $out;
    }

    public function appendVisitMeta(UserPrice $row): UserPrice
    {
        if (! $row->relationLoaded('lessonPackage')) {
            $row->load('lessonPackage');
        }

        $package = $row->lessonPackage;
        if (! $package || ! $package->isPostpay()) {
            $row->setAttribute('is_postpay', false);
            $row->setAttribute('postpay_visits', null);

            return $row;
        }

        $calc = $this->calculator->forUserPrice($row, $package);
        $row->setAttribute('is_postpay', true);
        $row->setAttribute('postpay_visits', $calc['visits']);
        // UI по-прежнему в рублях
        $row->setAttribute('postpay_price_per_lesson', (float) Money::fromCents($calc['price_per_lesson_cents']));

        return $row;
    }

    public function resyncUnpaidForPackage(int $lessonPackageId): int
    {
        $package = LessonPackage::query()->find($lessonPackageId);
        if (! $package || ! $package->isPostpay()) {
            return 0;
        }

        $count = 0;
        UserPrice::query()
            ->where('lesson_package_id', $lessonPackageId)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($package, &$count) {
                foreach ($rows as $row) {
                    $row->setRelation('lessonPackage', $package);
                    if ($this->isLocked($row)) {
                        continue;
                    }
                    $this->syncRow($row);
                    $count++;
                }
            });

        return $count;
    }
}
