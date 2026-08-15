<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Kansas;

use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\TrainerSalaryDraftLine;
use App\Models\TrainerSalaryKansasDraftGroup;
use App\Models\TrainerSalaryKansasDraftTrainer;
use App\Models\TrainerSalaryKansasGroupBaseline;
use App\Models\TrainerSalaryKansasPeriodSetting;
use App\Models\TrainerSalaryKansasSnapshotGroup;
use App\Models\TrainerSalaryKansasSnapshotTrainer;
use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySnapshot;
use App\Services\Schedule\TrainerSalary\TrainerSalaryScheme;
use App\Services\Trainers\TrainerTypeCatalog;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

final class KansasTrainerSalaryScheme implements TrainerSalaryScheme
{
    public const CODE = 'kansas';

    public const PERMISSION = 'schedule.trainerSalary.scheme.kansas';

    public function __construct(
        private readonly KansasAttendanceAggregator $attendanceAggregator,
        private readonly KansasTrainerSalaryCalculator $calculator,
        private readonly TrainerTypeCatalog $trainerTypes,
    ) {
    }

    public function code(): string
    {
        return self::CODE;
    }

    public function permissionName(): string
    {
        return self::PERMISSION;
    }

    public function draftSubtitle(): string
    {
        return 'Черновик за календарный месяц. Тренировка — занятие (слот + дата) с хотя бы одним «Посетил» у этого тренера. Средние до десятых входят в расчёт.';
    }

    public function draftTableView(): string
    {
        return 'admin.schedule.trainer-salary.kansas._table';
    }

    public function sheetDetailTableView(): string
    {
        return 'admin.schedule.trainer-salary.kansas._sheet_detail_table';
    }

    public function draftViewData(TrainerSalaryPeriod $period): array
    {
        $incrementCents = $this->premiumIncrementCents($period);

        return [
            'premium_increment' => $this->formatMoney($incrementCents),
            'premium_increment_display' => $this->formatSheetMoney($incrementCents),
        ];
    }

    public function draftRules(): array
    {
        return [
            'rate_per_training' => ['prohibited'],
            'base_premium' => ['prohibited'],
            'premium_increment' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'team_id' => ['required_with:base_avg_students', 'integer', 'min:0'],
            'base_avg_students' => [
                'required_with:team_id',
                'numeric',
                'min:0',
                'max:999.9',
                'decimal:0,1',
            ],
        ];
    }

    public function draftAttributes(): array
    {
        return [
            'rate_per_training' => 'оклад за тренировку',
            'base_premium' => 'базовая премия',
            'premium_increment' => 'базовая надбавка к премии',
            'team_id' => 'группа',
            'base_avg_students' => 'базовое среднее учеников',
        ];
    }

    public function draftMessages(): array
    {
        return [
            'rate_per_training.prohibited' => 'Оклад за тренировку задаётся в типе тренера.',
            'base_premium.prohibited' => 'Базовая премия задаётся в типе тренера.',
            'premium_increment.numeric' => 'Базовая надбавка должна быть числом (рубли, можно с копейками).',
            'premium_increment.min' => 'Базовая надбавка не может быть отрицательной.',
            'base_avg_students.numeric' => 'Базовое среднее должно быть числом с не более чем одной десятой.',
            'base_avg_students.min' => 'Базовое среднее не может быть отрицательным.',
            'base_avg_students.max' => 'Базовое среднее слишком большое.',
            'base_avg_students.decimal' => 'Базовое среднее — число с одной десятой (например 16 или 16.5).',
            'team_id.required_with' => 'Для базового среднего укажите группу.',
            'base_avg_students.required_with' => 'Укажите базовое среднее учеников.',
        ];
    }

    public function draftFieldKeys(): array
    {
        return ['premium_increment', 'team_id', 'base_avg_students'];
    }

    public function draftInputRequiresAllTrainersRecompute(array $data): bool
    {
        return array_key_exists('premium_increment', $data)
            || array_key_exists('base_avg_students', $data);
    }

    public function prefersFullTableReload(): bool
    {
        return true;
    }

    public function discardUnlockedDraft(TrainerSalaryPeriod $period): void
    {
        $lineIds = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->pluck('id')
            ->all();

        if ($lineIds !== []) {
            TrainerSalaryKansasDraftGroup::query()
                ->whereIn('trainer_salary_draft_line_id', $lineIds)
                ->delete();
            TrainerSalaryKansasDraftTrainer::query()
                ->whereIn('trainer_salary_draft_line_id', $lineIds)
                ->delete();
        }

        TrainerSalaryKansasGroupBaseline::query()
            ->where('trainer_salary_period_id', $period->id)
            ->delete();
        TrainerSalaryKansasPeriodSetting::query()
            ->where('trainer_salary_period_id', $period->id)
            ->delete();
    }

    public function syncDraftLines(TrainerSalaryPeriod $period, int $partnerId): void
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

            $draft = $this->createDraftLine($period, $profile);
            $this->compute($draft);
            $draft->save();
        }
    }

    public function refreshComputedInputs(
        TrainerSalaryPeriod $period,
        int $partnerId,
        string $dateFrom,
        string $dateTo,
    ): void {
        $stats = $this->attendanceAggregator->trainerGroupStats($partnerId, $dateFrom, $dateTo);
        $incrementCents = $this->premiumIncrementCents($period);
        $baselines = $this->baselinesByTeam($period);

        $drafts = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->get();

        foreach ($drafts as $draft) {
            $trainerId = (int) $draft->trainer_profile_id;
            $this->syncTrainerGroupLines(
                $draft,
                $stats[$trainerId] ?? [],
                $baselines,
                $incrementCents,
            );
            $this->compute($draft);
            $draft->save();
        }
    }

    public function createDraftLine(TrainerSalaryPeriod $period, TrainerProfile $profile): TrainerSalaryDraftLine
    {
        $rates = $this->trainerTypes->ratesForProfile($profile);

        return new TrainerSalaryDraftLine([
            'trainer_salary_period_id' => $period->id,
            'trainer_profile_id' => $profile->id,
            'base_salary_cents' => 0,
            'rate_per_training_cents' => $rates['rate_per_training_cents'],
            'trainings_count' => 0,
            'trainings_amount_cents' => 0,
            'bonuses_cents' => 0,
            'deductions_cents' => 0,
            'comment' => null,
            'total_cents' => 0,
        ]);
    }

    public function applyDraftInput(TrainerSalaryDraftLine $draft, array $data): void
    {
        $this->persistDraftLine($draft);
        $this->ensureTrainerSettings($draft);

        if (array_key_exists('premium_increment', $data)) {
            $period = $this->periodOf($draft);
            $periodSettings = $this->ensurePeriodSettings($period);
            $periodSettings->premium_increment_cents = Money::toCentsOrFail($data['premium_increment']);
            $periodSettings->save();
        }

        if (array_key_exists('base_avg_students', $data)) {
            $period = $this->periodOf($draft);
            $teamId = (int) ($data['team_id'] ?? -1);
            if ($teamId < 0) {
                throw ValidationException::withMessages([
                    'team_id' => ['Для базового среднего укажите группу.'],
                ]);
            }
            $this->assertTeamBelongsToPeriodPartner($period, $teamId);

            $tenths = KansasQuantity::toTenthsOrFail($data['base_avg_students']);
            TrainerSalaryKansasGroupBaseline::query()->updateOrCreate(
                [
                    'trainer_salary_period_id' => $period->id,
                    'team_id' => $teamId,
                ],
                [
                    'base_avg_students_tenths' => $tenths,
                ],
            );
        }
    }

    public function compute(TrainerSalaryDraftLine $draft): void
    {
        $this->persistDraftLine($draft);
        $settings = $this->ensureTrainerSettings($draft);
        $period = $this->periodOf($draft);
        $incrementCents = $this->premiumIncrementCents($period);
        $baselines = $this->baselinesByTeam($period);

        $groups = TrainerSalaryKansasDraftGroup::query()
            ->where('trainer_salary_draft_line_id', $draft->id)
            ->orderBy('team_title')
            ->orderBy('team_id')
            ->get();

        $totalCents = 0;
        $trainingsCount = 0;

        foreach ($groups as $group) {
            $teamId = (int) $group->team_id;
            $baseAvgTenths = $baselines[$teamId] ?? (int) $group->base_avg_tenths;
            $computed = $this->calculator->computeGroup(
                (int) $group->trainings_count,
                (int) $group->students_visited_sum,
                $baseAvgTenths,
                (int) $settings->rate_per_training_cents,
                (int) $settings->base_premium_cents,
                $incrementCents,
            );

            $group->base_avg_tenths = $baseAvgTenths;
            $group->fact_avg_tenths = $computed['fact_avg_tenths'];
            $group->diff_tenths = $computed['diff_tenths'];
            $group->premium_cents = $computed['premium_cents'];
            $group->pay_per_training_cents = $computed['pay_per_training_cents'];
            $group->group_total_cents = $computed['group_total_cents'];
            $group->save();

            $totalCents += $computed['group_total_cents'];
            $trainingsCount += (int) $group->trainings_count;
        }

        $draft->rate_per_training_cents = (int) $settings->rate_per_training_cents;
        $draft->trainings_count = $trainingsCount;
        $draft->trainings_amount_cents = $totalCents;
        $draft->total_cents = $totalCents;
        $draft->base_salary_cents = 0;
        $draft->bonuses_cents = 0;
        $draft->deductions_cents = 0;
        $draft->comment = null;
    }

    public function snapshotAttributes(TrainerSalaryDraftLine $draft): array
    {
        return [
            'base_salary_cents' => 0,
            'rate_per_training_cents' => (int) $draft->rate_per_training_cents,
            'trainings_count' => (int) $draft->trainings_count,
            'trainings_amount_cents' => (int) $draft->trainings_amount_cents,
            'bonuses_cents' => 0,
            'deductions_cents' => 0,
            'comment' => null,
            'total_cents' => (int) $draft->total_cents,
        ];
    }

    public function afterSnapshotCreated(TrainerSalarySnapshot $snapshot, TrainerSalaryDraftLine $draft): void
    {
        $settings = $this->ensureTrainerSettings($draft);
        $period = $this->periodOf($draft);

        TrainerSalaryKansasSnapshotTrainer::query()->updateOrCreate(
            ['trainer_salary_snapshot_id' => $snapshot->id],
            [
                'rate_per_training_cents' => (int) $settings->rate_per_training_cents,
                'base_premium_cents' => (int) $settings->base_premium_cents,
                'premium_increment_cents' => $this->premiumIncrementCents($period),
            ],
        );

        TrainerSalaryKansasSnapshotGroup::query()
            ->where('trainer_salary_snapshot_id', $snapshot->id)
            ->delete();

        $groups = TrainerSalaryKansasDraftGroup::query()
            ->where('trainer_salary_draft_line_id', $draft->id)
            ->orderBy('team_title')
            ->orderBy('team_id')
            ->get();

        foreach ($groups as $group) {
            TrainerSalaryKansasSnapshotGroup::query()->create([
                'trainer_salary_snapshot_id' => $snapshot->id,
                'team_id' => (int) $group->team_id,
                'team_title' => (string) $group->team_title,
                'trainings_count' => (int) $group->trainings_count,
                'students_visited_sum' => (int) $group->students_visited_sum,
                'fact_avg_tenths' => (int) $group->fact_avg_tenths,
                'base_avg_tenths' => (int) $group->base_avg_tenths,
                'diff_tenths' => (int) $group->diff_tenths,
                'premium_cents' => (int) $group->premium_cents,
                'pay_per_training_cents' => (int) $group->pay_per_training_cents,
                'group_total_cents' => (int) $group->group_total_cents,
            ]);
        }
    }

    public function rowPayload(TrainerSalaryDraftLine $draft, string $trainerName): array
    {
        $settings = $this->ensureTrainerSettings($draft);
        $groups = TrainerSalaryKansasDraftGroup::query()
            ->where('trainer_salary_draft_line_id', $draft->id)
            ->orderBy('team_title')
            ->orderBy('team_id')
            ->get();

        return [
            'trainer_profile_id' => (int) $draft->trainer_profile_id,
            'trainer_name' => $trainerName,
            'rate_per_training' => $this->formatMoney((int) $settings->rate_per_training_cents),
            'base_premium' => $this->formatMoney((int) $settings->base_premium_cents),
            'trainings_count' => (int) $draft->trainings_count,
            'total' => $this->formatMoney((int) $draft->total_cents),
            'groups' => $groups->map(fn (TrainerSalaryKansasDraftGroup $group) => $this->groupPayload($group, false))->all(),
        ];
    }

    public function snapshotRowPayload(TrainerSalarySnapshot $snapshot, string $trainerName): array
    {
        $trainerSnap = TrainerSalaryKansasSnapshotTrainer::query()
            ->where('trainer_salary_snapshot_id', $snapshot->id)
            ->first();

        $groups = TrainerSalaryKansasSnapshotGroup::query()
            ->where('trainer_salary_snapshot_id', $snapshot->id)
            ->orderBy('team_title')
            ->orderBy('team_id')
            ->get();

        $rateCents = (int) ($trainerSnap?->rate_per_training_cents ?? $snapshot->rate_per_training_cents ?? 0);
        $premiumCents = (int) ($trainerSnap?->base_premium_cents ?? 0);
        $incrementCents = (int) ($trainerSnap?->premium_increment_cents ?? 0);

        return [
            'trainer_profile_id' => (int) $snapshot->trainer_profile_id,
            'trainer_name' => $trainerName,
            'rate_per_training' => $this->formatSheetMoney($rateCents),
            'base_premium' => $this->formatSheetMoney($premiumCents),
            'premium_increment' => $this->formatSheetMoney($incrementCents),
            'trainings_count' => (int) $snapshot->trainings_count,
            'total' => $this->formatSheetMoney((int) $snapshot->total_cents),
            'version' => (int) $snapshot->version,
            'groups' => $groups->map(fn (TrainerSalaryKansasSnapshotGroup $group) => $this->snapshotGroupPayload($group))->all(),
        ];
    }

    /**
     * @param array<int, array{team_id: int, team_title: string, trainings_count: int, students_visited_sum: int}> $statsByTeam
     * @param array<int, int> $baselines
     */
    private function syncTrainerGroupLines(
        TrainerSalaryDraftLine $draft,
        array $statsByTeam,
        array $baselines,
        int $incrementCents,
    ): void {
        $this->persistDraftLine($draft);
        $settings = $this->ensureTrainerSettings($draft);
        $keepTeamIds = [];

        uasort($statsByTeam, function (array $a, array $b): int {
            $titleCmp = strcasecmp($a['team_title'], $b['team_title']);
            if ($titleCmp !== 0) {
                return $titleCmp;
            }

            return $a['team_id'] <=> $b['team_id'];
        });

        foreach ($statsByTeam as $teamId => $stats) {
            $teamId = (int) $teamId;
            $keepTeamIds[] = $teamId;
            $baseAvgTenths = $baselines[$teamId] ?? 0;
            $computed = $this->calculator->computeGroup(
                (int) $stats['trainings_count'],
                (int) $stats['students_visited_sum'],
                $baseAvgTenths,
                (int) $settings->rate_per_training_cents,
                (int) $settings->base_premium_cents,
                $incrementCents,
            );

            TrainerSalaryKansasDraftGroup::query()->updateOrCreate(
                [
                    'trainer_salary_draft_line_id' => $draft->id,
                    'team_id' => $teamId,
                ],
                [
                    'team_title' => (string) $stats['team_title'],
                    'trainings_count' => (int) $stats['trainings_count'],
                    'students_visited_sum' => (int) $stats['students_visited_sum'],
                    'fact_avg_tenths' => $computed['fact_avg_tenths'],
                    'base_avg_tenths' => $baseAvgTenths,
                    'diff_tenths' => $computed['diff_tenths'],
                    'premium_cents' => $computed['premium_cents'],
                    'pay_per_training_cents' => $computed['pay_per_training_cents'],
                    'group_total_cents' => $computed['group_total_cents'],
                ],
            );
        }

        $deleteQuery = TrainerSalaryKansasDraftGroup::query()
            ->where('trainer_salary_draft_line_id', $draft->id);
        if ($keepTeamIds !== []) {
            $deleteQuery->whereNotIn('team_id', $keepTeamIds);
        }
        $deleteQuery->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function groupPayload(TrainerSalaryKansasDraftGroup $group, bool $sheet): array
    {
        $format = $sheet
            ? fn (int $cents) => $this->formatSheetMoney($cents)
            : fn (int $cents) => $this->formatMoney($cents);

        return [
            'team_id' => (int) $group->team_id,
            'team_title' => (string) $group->team_title,
            'base_avg_students' => KansasQuantity::formatTenths((int) $group->base_avg_tenths),
            'fact_avg_students' => KansasQuantity::formatTenths((int) $group->fact_avg_tenths),
            'diff_students' => KansasQuantity::formatTenths((int) $group->diff_tenths),
            'premium' => $format((int) $group->premium_cents),
            'pay_per_training' => $format((int) $group->pay_per_training_cents),
            'trainings_count' => (int) $group->trainings_count,
            'group_total' => $format((int) $group->group_total_cents),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotGroupPayload(TrainerSalaryKansasSnapshotGroup $group): array
    {
        return [
            'team_id' => (int) $group->team_id,
            'team_title' => (string) $group->team_title,
            'base_avg_students' => KansasQuantity::formatTenths((int) $group->base_avg_tenths),
            'fact_avg_students' => KansasQuantity::formatTenths((int) $group->fact_avg_tenths),
            'diff_students' => KansasQuantity::formatTenths((int) $group->diff_tenths),
            'premium' => $this->formatSheetMoney((int) $group->premium_cents),
            'pay_per_training' => $this->formatSheetMoney((int) $group->pay_per_training_cents),
            'trainings_count' => (int) $group->trainings_count,
            'group_total' => $this->formatSheetMoney((int) $group->group_total_cents),
        ];
    }

    private function persistDraftLine(TrainerSalaryDraftLine $draft): void
    {
        if (! $draft->exists) {
            $draft->save();
        }
    }

    private function ensureTrainerSettings(TrainerSalaryDraftLine $draft): TrainerSalaryKansasDraftTrainer
    {
        $this->persistDraftLine($draft);

        $existing = TrainerSalaryKansasDraftTrainer::query()
            ->where('trainer_salary_draft_line_id', $draft->id)
            ->first();

        $rates = $this->typeRatesForDraft($draft);

        if ($existing !== null) {
            $existing->rate_per_training_cents = $rates['rate_per_training_cents'];
            $existing->base_premium_cents = $rates['base_premium_cents'];
            if ($existing->isDirty()) {
                $existing->save();
            }
            $draft->rate_per_training_cents = $rates['rate_per_training_cents'];

            return $existing;
        }

        $created = TrainerSalaryKansasDraftTrainer::query()->create([
            'trainer_salary_draft_line_id' => $draft->id,
            'rate_per_training_cents' => $rates['rate_per_training_cents'],
            'base_premium_cents' => $rates['base_premium_cents'],
        ]);
        $draft->rate_per_training_cents = $rates['rate_per_training_cents'];

        return $created;
    }

    /**
     * @return array{rate_per_training_cents: int, base_premium_cents: int}
     */
    private function typeRatesForDraft(TrainerSalaryDraftLine $draft): array
    {
        $profile = $draft->relationLoaded('trainerProfile')
            ? $draft->trainerProfile
            : $draft->trainerProfile()->first();

        if ($profile === null) {
            $profile = TrainerProfile::query()->find($draft->trainer_profile_id);
        }

        if ($profile === null) {
            return [
                'rate_per_training_cents' => 0,
                'base_premium_cents' => 0,
            ];
        }

        return $this->trainerTypes->ratesForProfile($profile);
    }

    private function ensurePeriodSettings(TrainerSalaryPeriod $period): TrainerSalaryKansasPeriodSetting
    {
        $existing = TrainerSalaryKansasPeriodSetting::query()
            ->where('trainer_salary_period_id', $period->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return TrainerSalaryKansasPeriodSetting::query()->create([
            'trainer_salary_period_id' => $period->id,
            'premium_increment_cents' => 0,
        ]);
    }

    private function premiumIncrementCents(TrainerSalaryPeriod $period): int
    {
        return (int) $this->ensurePeriodSettings($period)->premium_increment_cents;
    }

    /**
     * @return array<int, int>
     */
    private function baselinesByTeam(TrainerSalaryPeriod $period): array
    {
        return TrainerSalaryKansasGroupBaseline::query()
            ->where('trainer_salary_period_id', $period->id)
            ->get()
            ->mapWithKeys(fn (TrainerSalaryKansasGroupBaseline $row) => [
                (int) $row->team_id => (int) $row->base_avg_students_tenths,
            ])
            ->all();
    }

    private function periodOf(TrainerSalaryDraftLine $draft): TrainerSalaryPeriod
    {
        $period = $draft->relationLoaded('period') ? $draft->period : $draft->period()->first();
        if ($period === null) {
            $period = TrainerSalaryPeriod::query()->find($draft->trainer_salary_period_id);
        }

        if ($period === null) {
            throw new \RuntimeException('Период ЗП для строки черновика не найден.');
        }

        return $period;
    }

    private function assertTeamBelongsToPeriodPartner(TrainerSalaryPeriod $period, int $teamId): void
    {
        if ($teamId === 0) {
            return;
        }

        $exists = Team::query()
            ->where('partner_id', (int) $period->partner_id)
            ->whereKey($teamId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'team_id' => ['Группа не найдена.'],
                'base_avg_students' => ['Группа не найдена.'],
            ]);
        }
    }

    private function formatMoney(int $cents): string
    {
        return $cents < 0 ? '-' . Money::fromCents(-$cents) : Money::fromCents($cents);
    }

    private function formatSheetMoney(int $cents): string
    {
        return $cents < 0 ? '-' . Money::formatRub(-$cents) : Money::formatRub($cents);
    }
}
