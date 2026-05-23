<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\TrainerProfile;
use App\Models\TrainerSalaryDraftLine;
use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TrainerSalaryService
{
    public function __construct(
        private readonly TrainerWorkloadReportService $workloadReportService,
        private readonly TrainerSalaryCalculator $calculator,
    ) {
    }

    /**
     * @return array{0: string, 1: string} [date_from, date_to]
     */
    public function monthPeriodStrings(int $year, int $month): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [$start->toDateString(), $end->toDateString()];
    }

    /**
     * @return array{
     *     period: TrainerSalaryPeriod,
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     date_from: string,
     *     date_to: string,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function buildReport(int $partnerId, int $year, int $month): array
    {
        [$dateFrom, $dateTo] = $this->monthPeriodStrings($year, $month);

        $period = $this->ensurePeriod($partnerId, $year, $month);
        $this->syncDraftLinesForActiveTrainers($period, $partnerId);
        $this->refreshTrainingsCounts($period, $partnerId, $dateFrom, $dateTo);

        $trainers = $this->activeTrainersForPartner($partnerId);
        $draftByTrainer = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->get()
            ->keyBy('trainer_profile_id');

        $latestSnapshots = $this->latestSnapshotsByTrainer($period->id);

        $rows = [];
        foreach ($trainers as $trainer) {
            $trainerId = (int) $trainer['id'];
            $draft = $draftByTrainer->get($trainerId);
            if ($draft === null) {
                continue;
            }

            $rows[] = $this->draftLinePayload($draft, $trainer['name'], $latestSnapshots[$trainerId] ?? null);
        }

        $monthStart = Carbon::createFromDate($year, $month, 1)->locale('ru');

        return [
            'period' => $period,
            'year' => $year,
            'month' => $month,
            'month_label' => mb_ucfirst($monthStart->translatedFormat('F Y'), 'UTF-8'),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows' => $rows,
        ];
    }

    /**
     * @param array{
     *     base_salary?: string|float|int|null,
     *     rate_per_training?: string|float|int|null,
     *     bonuses?: string|float|int|null,
     *     deductions?: string|float|int|null,
     *     comment?: string|null
     * } $data
     * @return array<string, mixed>
     */
    public function updateDraftLine(
        TrainerSalaryPeriod $period,
        TrainerProfile $trainerProfile,
        int $partnerId,
        array $data,
    ): array {
        $this->assertTrainerBelongsToPartner($trainerProfile, $partnerId);

        $draft = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->where('trainer_profile_id', $trainerProfile->id)
            ->first();

        if ($draft === null) {
            $draft = $this->createDraftLineFromProfile($period, $trainerProfile);
        }

        if (array_key_exists('base_salary', $data)) {
            $draft->base_salary = $this->calculator->normalizeMoney($data['base_salary']);
        }
        if (array_key_exists('rate_per_training', $data)) {
            $draft->rate_per_training = $this->calculator->normalizeMoney($data['rate_per_training']);
        }
        if (array_key_exists('bonuses', $data)) {
            $draft->bonuses = $this->calculator->normalizeMoney($data['bonuses']);
        }
        if (array_key_exists('deductions', $data)) {
            $draft->deductions = $this->calculator->normalizeMoney($data['deductions']);
        }
        if (array_key_exists('comment', $data)) {
            $draft->comment = $data['comment'] !== null && trim($data['comment']) !== ''
                ? trim($data['comment'])
                : null;
        }

        $this->applyComputedAmounts($draft);
        $draft->save();

        $latest = $this->latestSnapshotForTrainer($period->id, (int) $trainerProfile->id);
        $name = $this->trainerDisplayName($trainerProfile);

        return $this->draftLinePayload($draft->fresh(), $name, $latest);
    }

    /**
     * @return array<string, mixed>
     */
    public function formSnapshotForTrainer(
        TrainerSalaryPeriod $period,
        TrainerProfile $trainerProfile,
        int $partnerId,
        User $actor,
    ): array {
        $this->assertTrainerBelongsToPartner($trainerProfile, $partnerId);

        [$dateFrom, $dateTo] = $this->monthPeriodStrings((int) $period->year, (int) $period->month);
        $this->refreshTrainingsCounts($period, $partnerId, $dateFrom, $dateTo);

        $draft = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->where('trainer_profile_id', $trainerProfile->id)
            ->first();

        if ($draft === null) {
            $draft = $this->createDraftLineFromProfile($period, $trainerProfile);
            $this->applyComputedAmounts($draft);
            $draft->save();
        } else {
            $this->applyComputedAmounts($draft);
            $draft->save();
        }

        $snapshot = $this->insertSnapshot($period, $draft, $actor, null);

        return [
            'snapshot' => $this->snapshotPayload($snapshot, $actor),
            'row' => $this->draftLinePayload(
                $draft->fresh(),
                $this->trainerDisplayName($trainerProfile),
                $snapshot,
            ),
        ];
    }

    /**
     * @return array{
     *     batch_id: string,
     *     snapshots_count: int,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function formSnapshotsForAllTrainers(
        TrainerSalaryPeriod $period,
        int $partnerId,
        User $actor,
    ): array {
        [$dateFrom, $dateTo] = $this->monthPeriodStrings((int) $period->year, (int) $period->month);
        $this->syncDraftLinesForActiveTrainers($period, $partnerId);
        $this->refreshTrainingsCounts($period, $partnerId, $dateFrom, $dateTo);

        $batchId = (string) Str::uuid();
        $trainers = $this->activeTrainersForPartner($partnerId);
        $rows = [];

        DB::transaction(function () use ($period, $partnerId, $actor, $batchId, $trainers, &$rows): void {
            foreach ($trainers as $trainerMeta) {
                $profile = TrainerProfile::query()
                    ->where('partner_id', $partnerId)
                    ->whereKey($trainerMeta['id'])
                    ->first();

                if ($profile === null) {
                    continue;
                }

                $draft = TrainerSalaryDraftLine::query()
                    ->where('trainer_salary_period_id', $period->id)
                    ->where('trainer_profile_id', $profile->id)
                    ->first();

                if ($draft === null) {
                    $draft = $this->createDraftLineFromProfile($period, $profile);
                }

                $this->applyComputedAmounts($draft);
                $draft->save();

                $snapshot = $this->insertSnapshot($period, $draft, $actor, $batchId);
                $rows[] = $this->draftLinePayload(
                    $draft->fresh(),
                    $trainerMeta['name'],
                    $snapshot,
                );
            }
        });

        return [
            'batch_id' => $batchId,
            'snapshots_count' => count($rows),
            'rows' => $rows,
        ];
    }

    public function findPeriodForPartner(int $partnerId, int $year, int $month): ?TrainerSalaryPeriod
    {
        return TrainerSalaryPeriod::query()
            ->where('partner_id', $partnerId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    public function ensurePeriod(int $partnerId, int $year, int $month): TrainerSalaryPeriod
    {
        return TrainerSalaryPeriod::query()->firstOrCreate(
            [
                'partner_id' => $partnerId,
                'year' => $year,
                'month' => $month,
            ],
        );
    }

    private function syncDraftLinesForActiveTrainers(TrainerSalaryPeriod $period, int $partnerId): void
    {
        $profiles = TrainerProfile::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('is_enabled', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $existingTrainerIds = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->pluck('trainer_profile_id')
            ->all();

        $existingSet = array_fill_keys(array_map('intval', $existingTrainerIds), true);

        foreach ($profiles as $profile) {
            if (isset($existingSet[(int) $profile->id])) {
                continue;
            }

            $draft = $this->createDraftLineFromProfile($period, $profile);
            $this->applyComputedAmounts($draft);
            $draft->save();
        }
    }

    private function refreshTrainingsCounts(
        TrainerSalaryPeriod $period,
        int $partnerId,
        string $dateFrom,
        string $dateTo,
    ): void {
        $counts = $this->workloadReportService->trainerRowTrainingsTotals($partnerId, $dateFrom, $dateTo);

        $drafts = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->get();

        foreach ($drafts as $draft) {
            $trainerId = (int) $draft->trainer_profile_id;
            $draft->trainings_count = $counts[$trainerId] ?? 0;
            $this->applyComputedAmounts($draft);
            $draft->save();
        }
    }

    private function createDraftLineFromProfile(
        TrainerSalaryPeriod $period,
        TrainerProfile $profile,
    ): TrainerSalaryDraftLine {
        $draft = new TrainerSalaryDraftLine([
            'trainer_salary_period_id' => $period->id,
            'trainer_profile_id' => $profile->id,
            'base_salary' => $this->calculator->normalizeMoney($profile->default_base_salary),
            'rate_per_training' => $this->calculator->normalizeMoney($profile->default_rate_per_training),
            'trainings_count' => 0,
            'trainings_amount' => '0.00',
            'bonuses' => '0.00',
            'deductions' => '0.00',
            'comment' => null,
            'total' => '0.00',
        ]);

        return $draft;
    }

    private function applyComputedAmounts(TrainerSalaryDraftLine $draft): void
    {
        $computed = $this->calculator->compute(
            (int) $draft->trainings_count,
            (string) $draft->base_salary,
            (string) $draft->rate_per_training,
            (string) $draft->bonuses,
            (string) $draft->deductions,
        );

        $draft->trainings_amount = $computed['trainings_amount'];
        $draft->total = $computed['total'];
    }

    private function insertSnapshot(
        TrainerSalaryPeriod $period,
        TrainerSalaryDraftLine $draft,
        User $actor,
        ?string $batchId,
    ): TrainerSalarySnapshot {
        $nextVersion = (int) TrainerSalarySnapshot::query()
            ->where('trainer_salary_period_id', $period->id)
            ->where('trainer_profile_id', $draft->trainer_profile_id)
            ->max('version') + 1;

        $formedAt = now();

        return TrainerSalarySnapshot::query()->create([
            'trainer_salary_period_id' => $period->id,
            'trainer_profile_id' => $draft->trainer_profile_id,
            'version' => $nextVersion,
            'batch_id' => $batchId,
            'base_salary' => $draft->base_salary,
            'rate_per_training' => $draft->rate_per_training,
            'trainings_count' => $draft->trainings_count,
            'trainings_amount' => $draft->trainings_amount,
            'bonuses' => $draft->bonuses,
            'deductions' => $draft->deductions,
            'comment' => $draft->comment,
            'total' => $draft->total,
            'formed_by_user_id' => $actor->id,
            'formed_at' => $formedAt,
        ]);
    }

    /**
     * @return array<int, TrainerSalarySnapshot>
     */
    private function latestSnapshotsByTrainer(int $periodId): array
    {
        $snapshots = TrainerSalarySnapshot::query()
            ->where('trainer_salary_period_id', $periodId)
            ->orderByDesc('version')
            ->get();

        $byTrainer = [];
        foreach ($snapshots as $snapshot) {
            $trainerId = (int) $snapshot->trainer_profile_id;
            if (! isset($byTrainer[$trainerId])) {
                $byTrainer[$trainerId] = $snapshot;
            }
        }

        return $byTrainer;
    }

    private function latestSnapshotForTrainer(int $periodId, int $trainerProfileId): ?TrainerSalarySnapshot
    {
        return TrainerSalarySnapshot::query()
            ->where('trainer_salary_period_id', $periodId)
            ->where('trainer_profile_id', $trainerProfileId)
            ->orderByDesc('version')
            ->first();
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
     * @return array<string, mixed>
     */
    private function draftLinePayload(
        TrainerSalaryDraftLine $draft,
        string $trainerName,
        ?TrainerSalarySnapshot $latestSnapshot,
    ): array {
        return [
            'trainer_profile_id' => (int) $draft->trainer_profile_id,
            'trainer_name' => $trainerName,
            'base_salary' => $this->formatMoney($draft->base_salary),
            'rate_per_training' => $this->formatMoney($draft->rate_per_training),
            'trainings_count' => (int) $draft->trainings_count,
            'trainings_amount' => $this->formatMoney($draft->trainings_amount),
            'bonuses' => $this->formatMoney($draft->bonuses),
            'deductions' => $this->formatMoney($draft->deductions),
            'comment' => $draft->comment,
            'total' => $this->formatMoney($draft->total),
            'latest_snapshot' => $latestSnapshot !== null
                ? $this->snapshotPayload($latestSnapshot)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotPayload(TrainerSalarySnapshot $snapshot, ?User $actor = null): array
    {
        $snapshot->loadMissing('formedBy');
        $formedBy = $actor ?? $snapshot->formedBy;

        return [
            'id' => (int) $snapshot->id,
            'version' => (int) $snapshot->version,
            'batch_id' => $snapshot->batch_id,
            'formed_at' => $snapshot->formed_at?->toIso8601String(),
            'formed_by_name' => $formedBy ? trim($formedBy->full_name ?? '') : '',
            'total' => $this->formatMoney($snapshot->total),
        ];
    }

    private function formatMoney(string|float|int|null $value): string
    {
        return $this->calculator->normalizeMoney($value);
    }

    private function trainerDisplayName(TrainerProfile $profile): string
    {
        $name = trim($profile->user?->full_name ?? '');

        return $name !== '' ? $name : 'Без имени';
    }

    private function assertTrainerBelongsToPartner(TrainerProfile $profile, int $partnerId): void
    {
        if ((int) $profile->partner_id !== $partnerId) {
            abort(404);
        }
    }
}
