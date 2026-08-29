<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary\Schemes\Sales;

use App\Models\TrainerProfile;
use App\Models\TrainerSalaryDraftLine;
use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySalesDraftTrainer;
use App\Models\TrainerSalarySalesSnapshotTrainer;
use App\Models\TrainerSalarySnapshot;
use App\Services\Schedule\TrainerSalary\TrainerSalaryScheme;
use App\Support\Money;

final class SalesTrainerSalaryScheme implements TrainerSalaryScheme
{
    public const CODE = 'sales';

    public const PERMISSION = 'schedule.trainerSalary.scheme.sales';

    public function __construct(
        private readonly SalesPaidSalesAggregator $paidSalesAggregator,
        private readonly SalesTrainerSalaryCalculator $calculator,
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
        return 'Черновик за календарный месяц. % — от оплаченных месяцев этого периода и абонементов по дате оплаты.';
    }

    public function draftTableView(): string
    {
        return 'admin.schedule.trainer-salary.sales._table';
    }

    public function sheetDetailTableView(): string
    {
        return 'admin.schedule.trainer-salary.sales._sheet_detail_table';
    }

    public function draftViewData(TrainerSalaryPeriod $period): array
    {
        return [];
    }

    public function draftRules(): array
    {
        return [
            'base_salary' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'sales_percent' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'bonuses' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'deductions' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function draftAttributes(): array
    {
        return [
            'base_salary' => 'оклад',
            'sales_percent' => 'процент от продаж',
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
            'sales_percent.integer' => 'Процент должен быть целым числом от 0 до 100.',
            'sales_percent.min' => 'Процент не может быть отрицательным.',
            'sales_percent.max' => 'Процент не может быть больше 100.',
            'bonuses.numeric' => 'Бонусы должны быть числом (рубли, можно с копейками).',
            'bonuses.min' => 'Бонусы не могут быть отрицательными.',
            'deductions.numeric' => 'Вычеты должны быть числом (рубли, можно с копейками).',
            'deductions.min' => 'Вычеты не могут быть отрицательными.',
            'comment.max' => 'Комментарий слишком длинный (максимум :max символов).',
        ];
    }

    public function draftFieldKeys(): array
    {
        return ['base_salary', 'sales_percent', 'bonuses', 'deductions', 'comment'];
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
        $totals = $this->paidSalesAggregator->trainerPaidTotals($partnerId, $dateFrom, $dateTo);

        $drafts = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->get();

        foreach ($drafts as $draft) {
            $trainerId = (int) $draft->trainer_profile_id;
            $paid = $totals[$trainerId] ?? [
                'paid_months_cents' => 0,
                'paid_packages_cents' => 0,
            ];
            $settings = $this->ensureSalesSettings($draft);
            $settings->paid_months_cents = (int) $paid['paid_months_cents'];
            $settings->paid_packages_cents = (int) $paid['paid_packages_cents'];
            $settings->save();
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
            'rate_per_training_cents' => 0,
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
        $lineIds = TrainerSalaryDraftLine::query()
            ->where('trainer_salary_period_id', $period->id)
            ->pluck('id')
            ->all();

        if ($lineIds === []) {
            return;
        }

        TrainerSalarySalesDraftTrainer::query()
            ->whereIn('trainer_salary_draft_line_id', $lineIds)
            ->delete();
    }

    public function applyDraftInput(TrainerSalaryDraftLine $draft, array $data): void
    {
        if (array_key_exists('base_salary', $data)) {
            $draft->base_salary_cents = Money::toCentsOrFail($data['base_salary']);
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

        $settings = $this->ensureSalesSettings($draft);
        if (array_key_exists('sales_percent', $data)) {
            $settings->sales_percent = (int) $data['sales_percent'];
            $settings->save();
        }
    }

    public function compute(TrainerSalaryDraftLine $draft): void
    {
        $settings = $this->ensureSalesSettings($draft);
        $computed = $this->calculator->compute(
            (int) $draft->base_salary_cents,
            (int) $settings->paid_months_cents,
            (int) $settings->paid_packages_cents,
            (int) $settings->sales_percent,
            (int) $draft->bonuses_cents,
            (int) $draft->deductions_cents,
        );

        $settings->sales_base_cents = $computed['sales_base_cents'];
        $settings->commission_cents = $computed['commission_cents'];
        $settings->save();

        $draft->trainings_count = 0;
        $draft->trainings_amount_cents = $computed['commission_cents'];
        $draft->rate_per_training_cents = 0;
        $draft->total_cents = $computed['total_cents'];
    }

    public function snapshotAttributes(TrainerSalaryDraftLine $draft): array
    {
        $settings = $this->ensureSalesSettings($draft);

        return [
            'base_salary_cents' => (int) $draft->base_salary_cents,
            'rate_per_training_cents' => 0,
            'trainings_count' => 0,
            'trainings_amount_cents' => (int) $settings->commission_cents,
            'bonuses_cents' => (int) $draft->bonuses_cents,
            'deductions_cents' => (int) $draft->deductions_cents,
            'comment' => $draft->comment,
            'total_cents' => (int) $draft->total_cents,
        ];
    }

    public function afterSnapshotCreated(TrainerSalarySnapshot $snapshot, TrainerSalaryDraftLine $draft): void
    {
        $settings = $this->ensureSalesSettings($draft);

        TrainerSalarySalesSnapshotTrainer::query()->updateOrCreate(
            ['trainer_salary_snapshot_id' => $snapshot->id],
            [
                'sales_percent' => (int) $settings->sales_percent,
                'paid_months_cents' => (int) $settings->paid_months_cents,
                'paid_packages_cents' => (int) $settings->paid_packages_cents,
                'sales_base_cents' => (int) $settings->sales_base_cents,
                'commission_cents' => (int) $settings->commission_cents,
            ],
        );
    }

    public function rowPayload(TrainerSalaryDraftLine $draft, string $trainerName): array
    {
        $settings = $this->ensureSalesSettings($draft);

        return [
            'trainer_profile_id' => (int) $draft->trainer_profile_id,
            'trainer_name' => $trainerName,
            'base_salary' => $this->formatMoney((int) $draft->base_salary_cents),
            'sales_percent' => (int) $settings->sales_percent,
            'paid_months' => $this->formatMoney((int) $settings->paid_months_cents),
            'paid_packages' => $this->formatMoney((int) $settings->paid_packages_cents),
            'sales_base' => $this->formatMoney((int) $settings->sales_base_cents),
            'commission' => $this->formatMoney((int) $settings->commission_cents),
            'bonuses' => $this->formatMoney((int) $draft->bonuses_cents),
            'deductions' => $this->formatMoney((int) $draft->deductions_cents),
            'comment' => $draft->comment,
            'total' => $this->formatMoney((int) $draft->total_cents),
        ];
    }

    public function snapshotRowPayload(TrainerSalarySnapshot $snapshot, string $trainerName): array
    {
        $salesSnap = TrainerSalarySalesSnapshotTrainer::query()
            ->where('trainer_salary_snapshot_id', $snapshot->id)
            ->first();

        $percent = (int) ($salesSnap?->sales_percent ?? 0);
        $paidMonths = (int) ($salesSnap?->paid_months_cents ?? 0);
        $paidPackages = (int) ($salesSnap?->paid_packages_cents ?? 0);
        $salesBase = (int) ($salesSnap?->sales_base_cents ?? 0);
        $commission = (int) ($salesSnap?->commission_cents ?? $snapshot->trainings_amount_cents ?? 0);

        return [
            'trainer_profile_id' => (int) $snapshot->trainer_profile_id,
            'trainer_name' => $trainerName,
            'base_salary' => $this->formatSheetMoney((int) $snapshot->base_salary_cents),
            'sales_percent' => $percent,
            'paid_months' => $this->formatSheetMoney($paidMonths),
            'paid_packages' => $this->formatSheetMoney($paidPackages),
            'sales_base' => $this->formatSheetMoney($salesBase),
            'commission' => $this->formatSheetMoney($commission),
            'bonuses' => $this->formatSheetMoney((int) $snapshot->bonuses_cents),
            'deductions' => $this->formatSheetMoney((int) $snapshot->deductions_cents),
            'comment' => $snapshot->comment,
            'total' => $this->formatSheetMoney((int) $snapshot->total_cents),
            'version' => (int) $snapshot->version,
        ];
    }

    private function ensureSalesSettings(TrainerSalaryDraftLine $draft): TrainerSalarySalesDraftTrainer
    {
        if (! $draft->exists) {
            $draft->save();
        }

        $existing = TrainerSalarySalesDraftTrainer::query()
            ->where('trainer_salary_draft_line_id', $draft->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return TrainerSalarySalesDraftTrainer::query()->create([
            'trainer_salary_draft_line_id' => $draft->id,
            'sales_percent' => 0,
            'paid_months_cents' => 0,
            'paid_packages_cents' => 0,
            'sales_base_cents' => 0,
            'commission_cents' => 0,
        ]);
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
