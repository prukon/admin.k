<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Classic;

use App\Models\TrainerProfile;
use App\Models\TrainerSalaryDraftLine;
use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySnapshot;
use App\Services\Schedule\TrainerSalary\TrainerSalaryScheme;
use App\Services\Schedule\TrainerWorkloadReportService;
use App\Support\Money;

final class ClassicTrainerSalaryScheme implements TrainerSalaryScheme
{
    public const CODE = 'classic';

    public const PERMISSION = 'schedule.trainerSalary.scheme.classic';

    public function __construct(
        private readonly TrainerWorkloadReportService $workloadReportService,
        private readonly ClassicTrainerSalaryCalculator $calculator,
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
        return 'Черновик за календарный месяц. Кол-во тренировок — как в отчёте «Нагрузка тренеров» (итог по строке).';
    }

    public function draftTableView(): string
    {
        return 'admin.schedule.trainer-salary.classic._table';
    }

    public function draftViewData(TrainerSalaryPeriod $period): array
    {
        return [];
    }

    public function sheetDetailTableView(): string
    {
        return 'admin.schedule.trainer-salary.classic._sheet_detail_table';
    }

    public function draftRules(): array
    {
        return [
            'base_salary' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'rate_per_training' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'bonuses' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'deductions' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function draftAttributes(): array
    {
        return [
            'base_salary' => 'оклад',
            'rate_per_training' => 'ставка за тренировку',
            'bonuses' => 'бонусы',
            'deductions' => 'вычеты',
            'comment' => 'комментарий',
        ];
    }

    public function draftMessages(): array
    {
        return [
            'base_salary.numeric' => 'Оклад должен быть числом (рубли, можно с копейками).',
            'base_salary.min' => 'Оклад не может быть отрицательным.',
            'rate_per_training.numeric' => 'Ставка должна быть числом (рубли, можно с копейками).',
            'rate_per_training.min' => 'Ставка не может быть отрицательной.',
            'bonuses.numeric' => 'Бонусы должны быть числом (рубли, можно с копейками).',
            'bonuses.min' => 'Бонусы не могут быть отрицательными.',
            'deductions.numeric' => 'Вычеты должны быть числом (рубли, можно с копейками).',
            'deductions.min' => 'Вычеты не могут быть отрицательными.',
            'comment.max' => 'Комментарий слишком длинный (максимум :max символов).',
        ];
    }

    public function draftFieldKeys(): array
    {
        return ['base_salary', 'rate_per_training', 'bonuses', 'deductions', 'comment'];
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
        $counts = $this->workloadReportService->trainerRowTrainingsTotals($partnerId, $dateFrom, $dateTo);

        $drafts = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->get();

        foreach ($drafts as $draft) {
            $trainerId = (int) $draft->trainer_profile_id;
            $draft->trainings_count = $counts[$trainerId] ?? 0;
            $this->compute($draft);
            $draft->save();
        }
    }

    public function createDraftLine(TrainerSalaryPeriod $period, TrainerProfile $profile): TrainerSalaryDraftLine
    {
        return new TrainerSalaryDraftLine([
            'trainer_salary_period_id' => $period->id,
            'trainer_profile_id' => $profile->id,
            'base_salary_cents' => (int) ($profile->default_base_salary_cents ?? 0),
            'rate_per_training_cents' => (int) ($profile->default_rate_per_training_cents ?? 0),
            'trainings_count' => 0,
            'trainings_amount_cents' => 0,
            'bonuses_cents' => 0,
            'deductions_cents' => 0,
            'comment' => null,
            'total_cents' => 0,
        ]);
    }

    public function draftInputRequiresAllTrainersRecompute(array $data): bool
    {
        return false;
    }

    public function prefersFullTableReload(): bool
    {
        return false;
    }

    public function discardUnlockedDraft(TrainerSalaryPeriod $period): void
    {
    }

    public function applyDraftInput(TrainerSalaryDraftLine $draft, array $data): void
    {
        if (array_key_exists('base_salary', $data)) {
            $draft->base_salary_cents = Money::toCentsOrFail($data['base_salary']);
        }
        if (array_key_exists('rate_per_training', $data)) {
            $draft->rate_per_training_cents = Money::toCentsOrFail($data['rate_per_training']);
        }
        if (array_key_exists('bonuses', $data)) {
            $draft->bonuses_cents = Money::toCentsOrFail($data['bonuses']);
        }
        if (array_key_exists('deductions', $data)) {
            $draft->deductions_cents = Money::toCentsOrFail($data['deductions']);
        }
        if (array_key_exists('comment', $data)) {
            $draft->comment = $data['comment'] !== null && trim((string) $data['comment']) !== ''
                ? trim((string) $data['comment'])
                : null;
        }
    }

    public function compute(TrainerSalaryDraftLine $draft): void
    {
        $computed = $this->calculator->compute(
            (int) $draft->trainings_count,
            (int) $draft->base_salary_cents,
            (int) $draft->rate_per_training_cents,
            (int) $draft->bonuses_cents,
            (int) $draft->deductions_cents,
        );

        $draft->trainings_amount_cents = $computed['trainings_amount_cents'];
        $draft->total_cents = $computed['total_cents'];
    }

    public function afterSnapshotCreated(TrainerSalarySnapshot $snapshot, TrainerSalaryDraftLine $draft): void
    {
    }

    public function snapshotAttributes(TrainerSalaryDraftLine $draft): array
    {
        return [
            'base_salary_cents' => $draft->base_salary_cents,
            'rate_per_training_cents' => $draft->rate_per_training_cents,
            'trainings_count' => $draft->trainings_count,
            'trainings_amount_cents' => $draft->trainings_amount_cents,
            'bonuses_cents' => $draft->bonuses_cents,
            'deductions_cents' => $draft->deductions_cents,
            'comment' => $draft->comment,
            'total_cents' => $draft->total_cents,
        ];
    }

    public function rowPayload(TrainerSalaryDraftLine $draft, string $trainerName): array
    {
        return [
            'trainer_profile_id' => (int) $draft->trainer_profile_id,
            'trainer_name' => $trainerName,
            'base_salary' => $this->formatMoney((int) $draft->base_salary_cents),
            'rate_per_training' => $this->formatMoney((int) $draft->rate_per_training_cents),
            'trainings_count' => (int) $draft->trainings_count,
            'trainings_amount' => $this->formatMoney((int) $draft->trainings_amount_cents),
            'bonuses' => $this->formatMoney((int) $draft->bonuses_cents),
            'deductions' => $this->formatMoney((int) $draft->deductions_cents),
            'comment' => $draft->comment,
            'total' => $this->formatMoney((int) $draft->total_cents),
        ];
    }

    public function snapshotRowPayload(TrainerSalarySnapshot $snapshot, string $trainerName): array
    {
        return [
            'trainer_profile_id' => (int) $snapshot->trainer_profile_id,
            'trainer_name' => $trainerName,
            'base_salary' => $this->formatSheetMoney((int) $snapshot->base_salary_cents),
            'rate_per_training' => $this->formatSheetMoney((int) $snapshot->rate_per_training_cents),
            'trainings_count' => (int) $snapshot->trainings_count,
            'trainings_amount' => $this->formatSheetMoney((int) $snapshot->trainings_amount_cents),
            'bonuses' => $this->formatSheetMoney((int) $snapshot->bonuses_cents),
            'deductions' => $this->formatSheetMoney((int) $snapshot->deductions_cents),
            'comment' => $snapshot->comment,
            'total' => $this->formatSheetMoney((int) $snapshot->total_cents),
            'version' => (int) $snapshot->version,
        ];
    }

    /**
     * Копейки → рублёвая строка "0.00" (dot, всегда 2 знака) для JS live-update.
     */
    private function formatMoney(int $cents): string
    {
        return $cents < 0 ? '-' . Money::fromCents(-$cents) : Money::fromCents($cents);
    }

    /**
     * Копейки → строка для read-only листа (канон Money::formatRub).
     */
    private function formatSheetMoney(int $cents): string
    {
        return $cents < 0 ? '-' . Money::formatRub(-$cents) : Money::formatRub($cents);
    }
}
