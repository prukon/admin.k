<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\Status;
use App\Models\TrainerProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TrainerWorkloadReportService
{
    private const TEAM_TITLE_WITHOUT_GROUP = 'Без группы';

    /**
     * @return array{
     *     date_from: string,
     *     date_to: string,
     *     weekdays: array<int, string>,
     *     trainers: list<array{id: int, name: string}>,
     *     cells: array<int, array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>>,
     *     row_totals: array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>,
     *     column_totals: array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>,
     *     show_groups: bool,
     *     grand_total: list<array{team_id: int|null, team_title: string, dates_count: int}>
     * }
     */
    public function build(int $partnerId, string $dateFrom, string $dateTo, bool $showGroups = false): array
    {
        $weekdays = $this->weekdayLabels();
        $weekdayKeys = array_keys($weekdays);
        $trainers = $this->activeTrainersForPartner($partnerId);

        $visitedStatusId = Status::globalVisitedId();
        $cells = $this->emptyCellsMatrix($trainers, $weekdayKeys);

        if ($visitedStatusId === null) {
            return $this->reportPayload($dateFrom, $dateTo, $weekdays, $trainers, $cells, $showGroups);
        }

        $aggregates = $this->fetchAggregates($partnerId, $dateFrom, $dateTo, $visitedStatusId);

        foreach ($aggregates as $row) {
            $trainerId = (int) $row->trainer_profile_id;
            $weekday = (int) $row->iso_weekday;

            if (! isset($cells[$trainerId][$weekday])) {
                continue;
            }

            $cells[$trainerId][$weekday][] = [
                'team_id' => $row->team_id !== null ? (int) $row->team_id : null,
                'team_title' => (string) $row->team_title,
                'dates_count' => (int) $row->dates_count,
            ];
        }

        foreach ($cells as $trainerId => $byWeekday) {
            foreach ($byWeekday as $weekday => $items) {
                $cells[$trainerId][$weekday] = $this->sortCellItems($items);
            }
        }

        return $this->reportPayload($dateFrom, $dateTo, $weekdays, $trainers, $cells, $showGroups);
    }

    /**
     * Итог по строке тренера за период (режим сумм, как в «Нагрузке» без групп).
     * Один календарный день с несколькими группами может учитываться несколько раз.
     *
     * @return array<int, int> trainer_profile_id => trainings_count
     */
    public function trainerRowTrainingsTotals(int $partnerId, string $dateFrom, string $dateTo): array
    {
        $report = $this->build($partnerId, $dateFrom, $dateTo, false);
        $totals = [];

        foreach ($report['trainers'] as $trainer) {
            $trainerId = (int) $trainer['id'];
            $items = $report['row_totals'][$trainerId] ?? [];
            $sum = 0;

            foreach ($items as $item) {
                $sum += (int) $item['dates_count'];
            }

            $totals[$trainerId] = $sum;
        }

        return $totals;
    }

    /**
     * @param list<array{id: int, name: string}> $trainers
     * @param array<int, array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>> $cells
     * @return array{
     *     date_from: string,
     *     date_to: string,
     *     weekdays: array<int, string>,
     *     trainers: list<array{id: int, name: string}>,
     *     show_groups: bool,
     *     cells: array<int, array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>>,
     *     row_totals: array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>,
     *     column_totals: array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>,
     *     grand_total: list<array{team_id: int|null, team_title: string, dates_count: int}>
     * }
     */
    private function reportPayload(
        string $dateFrom,
        string $dateTo,
        array $weekdays,
        array $trainers,
        array $cells,
        bool $showGroups,
    ): array {
        $weekdayKeys = array_keys($weekdays);

        if (! $showGroups) {
            $cells = $this->collapseCellsMatrixToSums($cells);
        }

        [$rowTotals, $columnTotals, $grandTotal] = $this->computeTotals($cells, $trainers, $weekdayKeys);

        if (! $showGroups) {
            $rowTotals = $this->collapseTotalsMap($rowTotals);
            $columnTotals = $this->collapseTotalsMap($columnTotals);
            $grandTotal = $this->collapseItemsToSum($grandTotal);
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'weekdays' => $weekdays,
            'trainers' => $trainers,
            'show_groups' => $showGroups,
            'cells' => $cells,
            'row_totals' => $rowTotals,
            'column_totals' => $columnTotals,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * @param array<int, array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>> $cells
     * @return array<int, array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>>
     */
    private function collapseCellsMatrixToSums(array $cells): array
    {
        foreach ($cells as $trainerId => $byWeekday) {
            foreach ($byWeekday as $weekday => $items) {
                $cells[$trainerId][$weekday] = $this->collapseItemsToSum($items);
            }
        }

        return $cells;
    }

    /**
     * @param array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>> $totals
     * @return array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>
     */
    private function collapseTotalsMap(array $totals): array
    {
        foreach ($totals as $key => $items) {
            $totals[$key] = $this->collapseItemsToSum($items);
        }

        return $totals;
    }

    /**
     * @param list<array{team_id: int|null, team_title: string, dates_count: int}> $items
     * @return list<array{team_id: int|null, team_title: string, dates_count: int}>
     */
    private function collapseItemsToSum(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $sum = 0;
        foreach ($items as $item) {
            $sum += (int) $item['dates_count'];
        }

        if ($sum === 0) {
            return [];
        }

        return [
            [
                'team_id' => null,
                'team_title' => '',
                'dates_count' => $sum,
            ],
        ];
    }

    /**
     * @param list<array{id: int, name: string}> $trainers
     * @param list<int> $weekdayKeys
     * @param array<int, array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>> $cells
     * @return array{
     *     0: array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>,
     *     1: array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>,
     *     2: list<array{team_id: int|null, team_title: string, dates_count: int}>
     * }
     */
    private function computeTotals(array $cells, array $trainers, array $weekdayKeys): array
    {
        $rowTotals = [];
        $columnTotals = [];

        foreach ($weekdayKeys as $weekday) {
            $columnTotals[(int) $weekday] = [];
        }

        $grandBuckets = [];

        foreach ($trainers as $trainer) {
            $trainerId = (int) $trainer['id'];
            $rowBuckets = [];

            foreach ($weekdayKeys as $weekday) {
                $weekday = (int) $weekday;
                $items = $cells[$trainerId][$weekday] ?? [];

                foreach ($items as $item) {
                    $this->mergeTeamCount($rowBuckets, $item);
                    $this->mergeTeamCount($columnTotals[$weekday], $item);
                    $this->mergeTeamCount($grandBuckets, $item);
                }
            }

            $rowTotals[$trainerId] = $this->sortCellItems($this->bucketsToItems($rowBuckets));
        }

        foreach ($weekdayKeys as $weekday) {
            $weekday = (int) $weekday;
            $columnTotals[$weekday] = $this->sortCellItems($this->bucketsToItems($columnTotals[$weekday]));
        }

        return [
            $rowTotals,
            $columnTotals,
            $this->sortCellItems($this->bucketsToItems($grandBuckets)),
        ];
    }

    /**
     * @param array<string, array{team_id: int|null, team_title: string, dates_count: int}> $buckets
     * @param array{team_id: int|null, team_title: string, dates_count: int} $item
     */
    private function mergeTeamCount(array &$buckets, array $item): void
    {
        $key = $this->teamBucketKey($item['team_id']);

        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'team_id' => $item['team_id'],
                'team_title' => $item['team_title'],
                'dates_count' => 0,
            ];
        }

        $buckets[$key]['dates_count'] += (int) $item['dates_count'];
    }

    private function teamBucketKey(?int $teamId): string
    {
        return $teamId === null ? 'team:null' : 'team:' . $teamId;
    }

    /**
     * @param array<string, array{team_id: int|null, team_title: string, dates_count: int}> $buckets
     * @return list<array{team_id: int|null, team_title: string, dates_count: int}>
     */
    private function bucketsToItems(array $buckets): array
    {
        return array_values($buckets);
    }

    /**
     * @return array<int, string>
     */
    private function weekdayLabels(): array
    {
        return [
            1 => 'Пн',
            2 => 'Вт',
            3 => 'Ср',
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб',
            7 => 'Вс',
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function activeTrainersForPartner(int $partnerId): array
    {
        return TrainerProfile::query()
            ->with('user')
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('is_enabled', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (TrainerProfile $profile) => [
                'id' => (int) $profile->id,
                'name' => $this->trainerDisplayName($profile),
            ])
            ->values()
            ->all();
    }

    /**
     * @param list<array{id: int, name: string}> $trainers
     * @param list<int> $weekdayKeys
     * @return array<int, array<int, list<array{team_id: int|null, team_title: string, dates_count: int}>>>
     */
    private function emptyCellsMatrix(array $trainers, array $weekdayKeys): array
    {
        $cells = [];

        foreach ($trainers as $trainer) {
            $cells[(int) $trainer['id']] = [];
            foreach ($weekdayKeys as $weekday) {
                $cells[(int) $trainer['id']][(int) $weekday] = [];
            }
        }

        return $cells;
    }

    private function fetchAggregates(
        int $partnerId,
        string $dateFrom,
        string $dateTo,
        int $visitedStatusId,
    ): Collection {
        $withoutGroup = self::TEAM_TITLE_WITHOUT_GROUP;

        return DB::table('schedule_users as su')
            ->join('users as u', 'u.id', '=', 'su.user_id')
            ->join('trainer_profiles as tp', function ($join) use ($partnerId): void {
                $join->on('tp.id', '=', 'su.trainer_profile_id')
                    ->where('tp.partner_id', '=', $partnerId);
            })
            ->leftJoin('teams as t', 't.id', '=', 'u.team_id')
            ->where('u.partner_id', $partnerId)
            ->where('u.is_enabled', 1)
            ->where('su.status_id', $visitedStatusId)
            ->whereNotNull('su.trainer_profile_id')
            ->whereBetween('su.date', [$dateFrom, $dateTo])
            ->selectRaw(
                'su.trainer_profile_id as trainer_profile_id,
                (WEEKDAY(su.date) + 1) as iso_weekday,
                u.team_id as team_id,
                COALESCE(MAX(t.title), ?) as team_title,
                COUNT(DISTINCT DATE(su.date)) as dates_count',
                [$withoutGroup],
            )
            ->groupByRaw('su.trainer_profile_id, (WEEKDAY(su.date) + 1), u.team_id')
            ->orderBy('team_title')
            ->get();
    }

    /**
     * @param list<array{team_id: int|null, team_title: string, dates_count: int}> $items
     * @return list<array{team_id: int|null, team_title: string, dates_count: int}>
     */
    private function sortCellItems(array $items): array
    {
        usort($items, function (array $a, array $b): int {
            $countCmp = $b['dates_count'] <=> $a['dates_count'];
            if ($countCmp !== 0) {
                return $countCmp;
            }

            return strcasecmp($a['team_title'], $b['team_title']);
        });

        return $items;
    }

    private function trainerDisplayName(TrainerProfile $profile): string
    {
        $name = trim($profile->user?->full_name ?? '');

        return $name !== '' ? $name : 'Без имени';
    }
}
