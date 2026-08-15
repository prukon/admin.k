<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\TrainerProfile;
use App\Models\TrainerSalaryDraftLine;
use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySnapshot;
use App\Models\User;
use App\Services\Schedule\TrainerSalary\TrainerSalaryScheme;
use App\Services\Schedule\TrainerSalary\TrainerSalarySchemeResolver;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TrainerSalaryService
{
    public function __construct(
        private readonly TrainerSalarySchemeResolver $schemeResolver,
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
     *     scheme: TrainerSalaryScheme,
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
        $scheme = $this->schemeResolver->schemeForPeriod($period);
        $scheme->syncDraftLines($period, $partnerId);
        $scheme->refreshComputedInputs($period, $partnerId, $dateFrom, $dateTo);

        $trainers = $this->activeTrainersForPartner($partnerId);
        $draftByTrainer = TrainerSalaryDraftLine::query()
            ->with(['trainerProfile.trainerType'])
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

            $rows[] = $this->draftLinePayload($scheme, $draft, $trainer['name'], $latestSnapshots[$trainerId] ?? null);
        }

        $monthStart = Carbon::createFromDate($year, $month, 1)->locale('ru');

        return [
            'period' => $period,
            'scheme' => $scheme,
            'year' => $year,
            'month' => $month,
            'month_label' => mb_ucfirst($monthStart->translatedFormat('F Y'), 'UTF-8'),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateDraftLine(
        TrainerSalaryPeriod $period,
        TrainerProfile $trainerProfile,
        int $partnerId,
        array $data,
    ): array {
        $this->assertTrainerBelongsToPartner($trainerProfile, $partnerId);

        $scheme = $this->schemeResolver->schemeForPeriod($period);

        $draft = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->where('trainer_profile_id', $trainerProfile->id)
            ->first();

        if ($draft === null) {
            $draft = $scheme->createDraftLine($period, $trainerProfile);
        }

        return DB::transaction(function () use ($period, $trainerProfile, $partnerId, $data, $scheme, $draft): array {
            $scheme->applyDraftInput($draft, $data);

            if ($scheme->draftInputRequiresAllTrainersRecompute($data)) {
                if (! $draft->exists) {
                    $draft->save();
                }
                [$dateFrom, $dateTo] = $this->monthPeriodStrings((int) $period->year, (int) $period->month);
                $scheme->refreshComputedInputs($period, $partnerId, $dateFrom, $dateTo);
                $draft = $draft->fresh() ?? $draft;
            } else {
                $scheme->compute($draft);
                $draft->save();
            }

            $latest = $this->latestSnapshotForTrainer($period->id, (int) $trainerProfile->id);
            $name = $this->trainerDisplayName($trainerProfile);

            return $this->draftLinePayload($scheme, $draft->fresh() ?? $draft, $name, $latest);
        });
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

        $scheme = $this->schemeResolver->schemeForPeriod($period);
        [$dateFrom, $dateTo] = $this->monthPeriodStrings((int) $period->year, (int) $period->month);
        $scheme->refreshComputedInputs($period, $partnerId, $dateFrom, $dateTo);

        $draft = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->where('trainer_profile_id', $trainerProfile->id)
            ->first();

        if ($draft === null) {
            $draft = $scheme->createDraftLine($period, $trainerProfile);
            $scheme->compute($draft);
            $draft->save();
        } else {
            $scheme->compute($draft);
            $draft->save();
        }

        $snapshot = $this->insertSnapshot($period, $scheme, $draft, $actor, null);

        return [
            'snapshot' => $this->snapshotPayload($snapshot, $actor),
            'row' => $this->draftLinePayload(
                $scheme,
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
        $scheme = $this->schemeResolver->schemeForPeriod($period);
        [$dateFrom, $dateTo] = $this->monthPeriodStrings((int) $period->year, (int) $period->month);
        $scheme->syncDraftLines($period, $partnerId);
        $scheme->refreshComputedInputs($period, $partnerId, $dateFrom, $dateTo);

        $batchId = (string) Str::uuid();
        $trainers = $this->activeTrainersForPartner($partnerId);
        $rows = [];

        DB::transaction(function () use ($period, $partnerId, $actor, $batchId, $trainers, $scheme, &$rows): void {
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
                    $draft = $scheme->createDraftLine($period, $profile);
                }

                $scheme->compute($draft);
                $draft->save();

                $snapshot = $this->insertSnapshot($period, $scheme, $draft, $actor, $batchId);
                $rows[] = $this->draftLinePayload(
                    $scheme,
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

    public function schemeForPeriod(TrainerSalaryPeriod $period): TrainerSalaryScheme
    {
        return $this->schemeResolver->schemeForPeriod($period);
    }

    public function ensurePeriod(int $partnerId, int $year, int $month): TrainerSalaryPeriod
    {
        $scheme = $this->schemeResolver->requireActiveScheme($partnerId);

        $period = TrainerSalaryPeriod::query()->firstOrCreate(
            [
                'partner_id' => $partnerId,
                'year' => $year,
                'month' => $month,
            ],
            [
                'scheme_code' => $scheme->code(),
            ],
        );

        return $this->rebaseUnlockedPeriodToActiveScheme($period, $scheme);
    }

    /**
     * Пока по месяцу нет слепков («Расчет» / «Сформировать всех»), период следует
     * текущей схеме партнёра. Черновик старой схемы сбрасывается, поля не переносятся.
     */
    private function rebaseUnlockedPeriodToActiveScheme(
        TrainerSalaryPeriod $period,
        TrainerSalaryScheme $activeScheme,
    ): TrainerSalaryPeriod {
        $currentCode = trim((string) ($period->scheme_code ?? ''));
        if ($currentCode === $activeScheme->code()) {
            return $period;
        }

        if ($period->snapshots()->exists()) {
            return $period;
        }

        return DB::transaction(function () use ($period, $activeScheme): TrainerSalaryPeriod {
            $this->schemeResolver->schemeForPeriod($period)->discardUnlockedDraft($period);

            TrainerSalaryDraftLine::query()
                ->where('trainer_salary_period_id', $period->id)
                ->delete();

            $period->scheme_code = $activeScheme->code();
            $period->save();

            return $period->refresh();
        });
    }

    private function insertSnapshot(
        TrainerSalaryPeriod $period,
        TrainerSalaryScheme $scheme,
        TrainerSalaryDraftLine $draft,
        User $actor,
        ?string $batchId,
    ): TrainerSalarySnapshot {
        $nextVersion = (int) TrainerSalarySnapshot::query()
            ->where('trainer_salary_period_id', $period->id)
            ->where('trainer_profile_id', $draft->trainer_profile_id)
            ->max('version') + 1;

        $formedAt = now();

        $snapshot = TrainerSalarySnapshot::query()->create(array_merge(
            $scheme->snapshotAttributes($draft),
            [
                'trainer_salary_period_id' => $period->id,
                'trainer_profile_id' => $draft->trainer_profile_id,
                'scheme_code' => $scheme->code(),
                'version' => $nextVersion,
                'batch_id' => $batchId,
                'formed_by_user_id' => $actor->id,
                'formed_at' => $formedAt,
            ],
        ));

        $scheme->afterSnapshotCreated($snapshot, $draft);

        return $snapshot;
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
            ->with(['user', 'trainerType'])
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
        TrainerSalaryScheme $scheme,
        TrainerSalaryDraftLine $draft,
        string $trainerName,
        ?TrainerSalarySnapshot $latestSnapshot,
    ): array {
        $payload = $scheme->rowPayload($draft, $trainerName);
        $payload['latest_snapshot'] = $latestSnapshot !== null
            ? $this->snapshotPayload($latestSnapshot)
            : null;

        return $payload;
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
            'scheme_code' => (string) ($snapshot->scheme_code ?: ''),
            'formed_at' => $snapshot->formed_at?->toIso8601String(),
            'formed_by_name' => $formedBy ? trim($formedBy->full_name ?? '') : '',
            'total' => $this->formatMoney((int) $snapshot->total_cents),
        ];
    }

    private function formatMoney(int $cents): string
    {
        return $cents < 0 ? '-' . Money::fromCents(-$cents) : Money::fromCents($cents);
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
