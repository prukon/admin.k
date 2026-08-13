<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\UserPrice;
use Illuminate\Support\Collection;

/**
 * Колонка оплаты месяца в журнале /schedule: по users_prices в разрезе группы.
 */
final class JournalMonthlyPaymentStatusService
{
    public const STATE_NONE = 'none';

    public const STATE_PAID = 'paid';

    public const STATE_PARTIAL = 'partial';

    public const ICON_PAID = 'fas fa-circle-check text-success';

    public const ICON_PARTIAL = 'fas fa-circle-check text-warning';

    public const HOVER_ALL_GROUPS_PAID = 'Все группы оплачены';

    /**
     * @param  list<int>  $userIds
     * @return array<int, array{state: string, icon_class: string, hover: string}>
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
            ->with(['team:id,title,order_by'])
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
     * @param  Collection<int, UserPrice>  $rows
     * @return array{state: string, icon_class: string, hover: string}
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
        foreach ($sorted as $row) {
            $title = $this->teamTitle($row);
            if ($row->effective_is_paid) {
                $paidTitles[] = $title;
            } else {
                $unpaidTitles[] = $title;
            }
        }

        $paidCount = count($paidTitles);
        $unpaidCount = count($unpaidTitles);

        if ($paidCount === 0) {
            return $this->emptyStatus();
        }

        if ($unpaidCount === 0) {
            $hover = ($isAllFilter && ($paidCount + $unpaidCount) > 1)
                ? self::HOVER_ALL_GROUPS_PAID
                : '';

            return [
                'state' => self::STATE_PAID,
                'icon_class' => self::ICON_PAID,
                'hover' => $hover,
            ];
        }

        return [
            'state' => self::STATE_PARTIAL,
            'icon_class' => self::ICON_PARTIAL,
            'hover' => $this->partialHover($paidTitles, $unpaidTitles),
        ];
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
     * @return array{state: string, icon_class: string, hover: string}
     */
    private function emptyStatus(): array
    {
        return [
            'state' => self::STATE_NONE,
            'icon_class' => '',
            'hover' => '',
        ];
    }

    private function teamTitle(UserPrice $row): string
    {
        $title = trim((string) ($row->team?->title ?? ''));

        return $title !== '' ? $title : 'Группа';
    }
}
