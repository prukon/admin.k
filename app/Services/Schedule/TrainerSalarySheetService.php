<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\TrainerProfile;
use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class TrainerSalarySheetService
{
    public function __construct(
        private readonly TrainerSalaryCalculator $calculator,
    ) {
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     sheets: list<array<string, mixed>>,
     *     latest_by_trainer: list<array<string, mixed>>
     * }
     */
    public function listSheets(int $partnerId, int $year, int $month, bool $latestOnly = false): array
    {
        $period = TrainerSalaryPeriod::query()
            ->where('partner_id', $partnerId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $monthLabel = mb_ucfirst(Carbon::createFromDate($year, $month, 1)->locale('ru')->translatedFormat('F Y'), 'UTF-8');

        if ($period === null) {
            return [
                'year' => $year,
                'month' => $month,
                'month_label' => $monthLabel,
                'sheets' => [],
                'latest_by_trainer' => [],
            ];
        }

        $snapshots = TrainerSalarySnapshot::query()
            ->where('trainer_salary_period_id', $period->id)
            ->with(['trainerProfile.user', 'formedBy'])
            ->orderByDesc('formed_at')
            ->orderByDesc('id')
            ->get();

        $latestVersionByTrainer = $this->latestVersionByTrainer($snapshots);
        $latestBatchId = $this->latestBatchId($snapshots);

        $sheets = [];

        foreach ($this->groupBatchSnapshots($snapshots) as $batchId => $batchSnapshots) {
            /** @var Collection<int, TrainerSalarySnapshot> $batchSnapshots */
            $first = $batchSnapshots->sortBy('trainer_profile_id')->first();
            if ($first === null) {
                continue;
            }

            $isLatestFullBatch = $latestBatchId !== null && (string) $batchId === (string) $latestBatchId;

            if ($latestOnly && ! $isLatestFullBatch) {
                continue;
            }

            $sheets[] = [
                'kind' => 'batch',
                'batch_id' => (string) $batchId,
                'snapshot_id' => null,
                'formed_at' => $first->formed_at?->toIso8601String(),
                'formed_at_display' => $this->formatDateTime($first->formed_at),
                'formed_by_name' => $this->userDisplayName($first->formedBy),
                'month_label' => $monthLabel,
                'type_label' => 'Полный лист',
                'trainer_name' => null,
                'version_label' => 'Пакет',
                'trainers_count' => $batchSnapshots->count(),
                'grand_total' => $this->formatMoney($this->sumTotals($batchSnapshots)),
                'is_latest_for_trainer' => false,
                'is_latest_full_batch' => $isLatestFullBatch,
                'show_url' => route('schedule.trainer-salary-sheets.batch.show', ['batchId' => $batchId]),
            ];
        }

        foreach ($snapshots->whereNull('batch_id') as $snapshot) {
            $trainerId = (int) $snapshot->trainer_profile_id;
            $isLatest = ($latestVersionByTrainer[$trainerId] ?? 0) === (int) $snapshot->version;

            if ($latestOnly && ! $isLatest) {
                continue;
            }

            $sheets[] = [
                'kind' => 'snapshot',
                'batch_id' => null,
                'snapshot_id' => (int) $snapshot->id,
                'formed_at' => $snapshot->formed_at?->toIso8601String(),
                'formed_at_display' => $this->formatDateTime($snapshot->formed_at),
                'formed_by_name' => $this->userDisplayName($snapshot->formedBy),
                'month_label' => $monthLabel,
                'type_label' => 'По тренеру',
                'trainer_name' => $this->trainerDisplayName($snapshot->trainerProfile),
                'version_label' => 'v' . (int) $snapshot->version,
                'trainers_count' => 1,
                'grand_total' => $this->formatMoney($snapshot->total),
                'is_latest_for_trainer' => $isLatest,
                'is_latest_full_batch' => false,
                'show_url' => route('schedule.trainer-salary-sheets.snapshot.show', ['snapshot' => $snapshot->id]),
            ];
        }

        usort($sheets, function (array $a, array $b): int {
            return strcmp((string) ($b['formed_at'] ?? ''), (string) ($a['formed_at'] ?? ''));
        });

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => $monthLabel,
            'sheets' => $sheets,
            'latest_by_trainer' => $this->buildLatestByTrainerSummary($snapshots, $latestVersionByTrainer),
        ];
    }

    /**
     * @return array{
     *     kind: string,
     *     month_label: string,
     *     formed_at_display: string,
     *     formed_by_name: string,
     *     type_label: string,
     *     version_label: string,
     *     trainers_count: int,
     *     grand_total: string,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function showBatch(int $partnerId, string $batchId): array
    {
        $snapshots = TrainerSalarySnapshot::query()
            ->where('batch_id', $batchId)
            ->whereHas('period', fn ($q) => $q->where('partner_id', $partnerId))
            ->with(['trainerProfile.user', 'formedBy', 'period'])
            ->get();

        if ($snapshots->isEmpty()) {
            abort(404);
        }

        $first = $snapshots->sortByDesc('formed_at')->first();
        $period = $first->period;
        $monthLabel = mb_ucfirst(
            Carbon::createFromDate((int) $period->year, (int) $period->month, 1)->locale('ru')->translatedFormat('F Y'),
            'UTF-8',
        );

        $rows = $snapshots
            ->sortBy(fn (TrainerSalarySnapshot $s) => $this->trainerSortKey($s->trainerProfile))
            ->map(fn (TrainerSalarySnapshot $s) => $this->snapshotToRow($s))
            ->values()
            ->all();

        return [
            'kind' => 'batch',
            'batch_id' => $batchId,
            'month_label' => $monthLabel,
            'year' => (int) $period->year,
            'month' => (int) $period->month,
            'formed_at_display' => $this->formatDateTime($first->formed_at),
            'formed_by_name' => $this->userDisplayName($first->formedBy),
            'type_label' => 'Полный лист',
            'version_label' => 'Пакет',
            'trainers_count' => count($rows),
            'grand_total' => $this->formatMoney($this->sumTotals($snapshots)),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showSnapshot(int $partnerId, TrainerSalarySnapshot $snapshot): array
    {
        $snapshot->loadMissing(['trainerProfile.user', 'formedBy', 'period']);

        if ((int) $snapshot->period?->partner_id !== $partnerId) {
            abort(404);
        }

        $period = $snapshot->period;
        $monthLabel = mb_ucfirst(
            Carbon::createFromDate((int) $period->year, (int) $period->month, 1)->locale('ru')->translatedFormat('F Y'),
            'UTF-8',
        );

        return [
            'kind' => 'snapshot',
            'snapshot_id' => (int) $snapshot->id,
            'batch_id' => $snapshot->batch_id,
            'month_label' => $monthLabel,
            'year' => (int) $period->year,
            'month' => (int) $period->month,
            'formed_at_display' => $this->formatDateTime($snapshot->formed_at),
            'formed_by_name' => $this->userDisplayName($snapshot->formedBy),
            'type_label' => 'По тренеру',
            'version_label' => 'v' . (int) $snapshot->version,
            'trainer_name' => $this->trainerDisplayName($snapshot->trainerProfile),
            'trainers_count' => 1,
            'grand_total' => $this->formatMoney($snapshot->total),
            'rows' => [$this->snapshotToRow($snapshot)],
        ];
    }

    /**
     * @param Collection<int, TrainerSalarySnapshot> $snapshots
     * @return array<int, int>
     */
    private function latestVersionByTrainer(Collection $snapshots): array
    {
        $map = [];

        foreach ($snapshots as $snapshot) {
            $trainerId = (int) $snapshot->trainer_profile_id;
            $version = (int) $snapshot->version;
            if (! isset($map[$trainerId]) || $version > $map[$trainerId]) {
                $map[$trainerId] = $version;
            }
        }

        return $map;
    }

    /**
     * @param Collection<int, TrainerSalarySnapshot> $snapshots
     */
    private function latestBatchId(Collection $snapshots): ?string
    {
        $latest = $snapshots
            ->whereNotNull('batch_id')
            ->sortByDesc(fn (TrainerSalarySnapshot $s) => $s->formed_at?->timestamp ?? 0)
            ->first();

        return $latest?->batch_id !== null ? (string) $latest->batch_id : null;
    }

    /**
     * @param Collection<int, TrainerSalarySnapshot> $snapshots
     * @return Collection<string, Collection<int, TrainerSalarySnapshot>>
     */
    private function groupBatchSnapshots(Collection $snapshots): Collection
    {
        return $snapshots
            ->whereNotNull('batch_id')
            ->groupBy(fn (TrainerSalarySnapshot $s) => (string) $s->batch_id);
    }

    /**
     * @param Collection<int, TrainerSalarySnapshot> $snapshots
     * @param array<int, int> $latestVersionByTrainer
     * @return list<array<string, mixed>>
     */
    private function buildLatestByTrainerSummary(Collection $snapshots, array $latestVersionByTrainer): array
    {
        $summary = [];

        foreach ($latestVersionByTrainer as $trainerId => $version) {
            $snapshot = $snapshots
                ->first(fn (TrainerSalarySnapshot $s) => (int) $s->trainer_profile_id === (int) $trainerId
                    && (int) $s->version === (int) $version);

            if ($snapshot === null) {
                continue;
            }

            $summary[] = [
                'trainer_profile_id' => (int) $trainerId,
                'trainer_name' => $this->trainerDisplayName($snapshot->trainerProfile),
                'version' => (int) $version,
                'formed_at_display' => $this->formatDateTime($snapshot->formed_at),
                'grand_total' => $this->formatMoney($snapshot->total),
                'show_url' => $snapshot->batch_id !== null
                    ? route('schedule.trainer-salary-sheets.batch.show', ['batchId' => $snapshot->batch_id])
                    : route('schedule.trainer-salary-sheets.snapshot.show', ['snapshot' => $snapshot->id]),
            ];
        }

        usort($summary, fn (array $a, array $b) => strcasecmp($a['trainer_name'], $b['trainer_name']));

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotToRow(TrainerSalarySnapshot $snapshot): array
    {
        return [
            'trainer_profile_id' => (int) $snapshot->trainer_profile_id,
            'trainer_name' => $this->trainerDisplayName($snapshot->trainerProfile),
            'base_salary' => $this->formatMoney($snapshot->base_salary),
            'rate_per_training' => $this->formatMoney($snapshot->rate_per_training),
            'trainings_count' => (int) $snapshot->trainings_count,
            'trainings_amount' => $this->formatMoney($snapshot->trainings_amount),
            'bonuses' => $this->formatMoney($snapshot->bonuses),
            'deductions' => $this->formatMoney($snapshot->deductions),
            'comment' => $snapshot->comment,
            'total' => $this->formatMoney($snapshot->total),
            'version' => (int) $snapshot->version,
        ];
    }

    /**
     * @param Collection<int, TrainerSalarySnapshot> $snapshots
     */
    private function sumTotals(Collection $snapshots): string
    {
        $sum = '0.00';
        foreach ($snapshots as $snapshot) {
            $sum = bcadd($sum, $this->calculator->normalizeMoney($snapshot->total), 2);
        }

        return $sum;
    }

    private function trainerSortKey(?TrainerProfile $profile): string
    {
        if ($profile === null) {
            return 'zzz';
        }

        return str_pad((string) (int) $profile->sort_order, 6, '0', STR_PAD_LEFT)
            . '_' . str_pad((string) (int) $profile->id, 8, '0', STR_PAD_LEFT);
    }

    private function trainerDisplayName(?TrainerProfile $profile): string
    {
        $name = trim($profile?->user?->full_name ?? '');

        return $name !== '' ? $name : 'Без имени';
    }

    private function userDisplayName(?User $user): string
    {
        $name = trim($user?->full_name ?? '');

        return $name !== '' ? $name : '—';
    }

    private function formatMoney(string|float|int|null $value): string
    {
        return $this->calculator->normalizeMoney($value);
    }

    private function formatDateTime(?Carbon $value): string
    {
        return $value?->format('d.m.Y H:i') ?? '—';
    }
}
