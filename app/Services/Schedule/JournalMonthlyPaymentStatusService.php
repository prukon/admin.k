<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\UserPrice;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Колонка оплаты месяца в журнале /schedule: по users_prices в разрезе группы.
 */
final class JournalMonthlyPaymentStatusService
{
    public const STATE_NONE = 'none';

    public const STATE_PAID = 'paid';

    public const STATE_PARTIAL = 'partial';

    public const STATE_DUE = 'due';

    public const ICON_PAID = 'fas fa-circle-check text-success';

    public const ICON_PARTIAL = 'fas fa-circle-check text-warning';

    public const HOVER_ALL_GROUPS_PAID = 'Все группы оплачены';

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{state: string, icon_class: string, hover: string, amount_cents: int, amount_label: string}>
     */
    public function statusesByUser(
        int $partnerId,
        array $userIds,
        string $monthFirstYmd,
        string|int $teamFilter = 'all',
    ): array {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $empty = [];
        foreach ($userIds as $userId) {
            $empty[$userId] = $this->emptyStatus();
        }

        if ($userIds === [] || (string) $teamFilter === 'none') {
            return $empty;
        }

        $query = UserPrice::query()
            ->with([
                'team:id,title,order_by',
                'lessonPackage:id,schedule_type',
            ])
            ->whereIn('user_id', $userIds)
            ->whereDate('new_month', $monthFirstYmd)
            ->where('price_cents', '>', 0)
            ->whereHas('user', static function ($q) use ($partnerId) {
                $q->where('partner_id', $partnerId);
            });

        if (is_numeric($teamFilter) && (int) $teamFilter > 0) {
            $query->where('team_id', (int) $teamFilter);
        }

        /** @var Collection<int, UserPrice> $rows */
        $rows = $query->get();
        $byUser = $rows->groupBy(static fn (UserPrice $row) => (int) $row->user_id);
        $isAllFilter = (string) $teamFilter === 'all';

        foreach ($userIds as $userId) {
            /** @var Collection<int, UserPrice> $group */
            $group = $byUser->get($userId, collect());
            $empty[$userId] = $this->presentForUser($group, $isAllFilter);
        }

        return $empty;
    }

    /**
     * Подпись суммы к оплате в колонке: «3600₽» (без пробела тысяч).
     */
    public static function dueAmountLabel(int $amountCents): string
    {
        $amount = str_replace(' ', '', Money::formatRub($amountCents));

        return $amount.'₽';
    }

    /**
     * @param  Collection<int, UserPrice>  $rows
     * @return array{state: string, icon_class: string, hover: string, amount_cents: int, amount_label: string}
     */
    private function presentForUser(Collection $rows, bool $isAllFilter): array
    {
        if ($rows->isEmpty()) {
            return $this->emptyStatus();
        }

        $sorted = $rows->sortBy(function (UserPrice $row) {
            $order = (int) ($row->team?->order_by ?? 0);
            $title = mb_strtolower($this->teamTitle($row));

            return sprintf('%010d-%s-%010d', $order, $title, (int) $row->id);
        })->values();

        $paidTitles = [];
        $unpaidTitles = [];
        $unpaidPostpayCents = 0;
        foreach ($sorted as $row) {
            $title = $this->teamTitle($row);
            if ($row->effective_is_paid) {
                $paidTitles[] = $title;

                continue;
            }
            $unpaidTitles[] = $title;
            if ($this->isPostpayRow($row)) {
                $unpaidPostpayCents += max(0, (int) ($row->price_cents ?? 0));
            }
        }

        $paidCount = count($paidTitles);
        $unpaidCount = count($unpaidTitles);

        if ($paidCount > 0 && $unpaidCount === 0) {
            $hover = ($isAllFilter && $paidCount > 1)
                ? self::HOVER_ALL_GROUPS_PAID
                : '';

            return $this->statusPayload(self::STATE_PAID, self::ICON_PAID, $hover);
        }

        if ($unpaidPostpayCents > 0) {
            $hover = '';
            if ($paidCount > 0) {
                $hover = $this->partialHover($paidTitles, $unpaidTitles);
            } elseif ($isAllFilter && $unpaidCount > 1) {
                $hover = 'Не оплачено: '.implode(', ', $unpaidTitles);
            }

            return $this->statusPayload(
                self::STATE_DUE,
                '',
                $hover,
                $unpaidPostpayCents,
            );
        }

        if ($paidCount === 0) {
            return $this->emptyStatus();
        }

        return $this->statusPayload(
            self::STATE_PARTIAL,
            self::ICON_PARTIAL,
            $this->partialHover($paidTitles, $unpaidTitles),
        );
    }

    /**
     * @param  list<string>  $paidTitles
     * @param  list<string>  $unpaidTitles
     */
    public function partialHover(array $paidTitles, array $unpaidTitles): string
    {
        return 'Оплачено: '.implode(', ', $paidTitles)."\n".'Не оплачено: '.implode(', ', $unpaidTitles);
    }

    /**
     * @return array{state: string, icon_class: string, hover: string, amount_cents: int, amount_label: string}
     */
    private function emptyStatus(): array
    {
        return $this->statusPayload(self::STATE_NONE, '', '');
    }

    /**
     * @return array{state: string, icon_class: string, hover: string, amount_cents: int, amount_label: string}
     */
    private function statusPayload(string $state, string $iconClass, string $hover, int $amountCents = 0): array
    {
        return [
            'state' => $state,
            'icon_class' => $iconClass,
            'hover' => $hover,
            'amount_cents' => $amountCents,
            'amount_label' => $amountCents > 0 ? self::dueAmountLabel($amountCents) : '',
        ];
    }

    private function isPostpayRow(UserPrice $row): bool
    {
        return $row->lessonPackage?->isPostpay() === true;
    }

    private function teamTitle(UserPrice $row): string
    {
        $title = trim((string) ($row->team?->title ?? ''));

        return $title !== '' ? $title : 'Группа';
    }
}
