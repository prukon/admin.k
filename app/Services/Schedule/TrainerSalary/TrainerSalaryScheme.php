<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary;

use App\Models\TrainerProfile;
use App\Models\TrainerSalaryDraftLine;
use App\Models\TrainerSalaryPeriod;
use App\Models\TrainerSalarySnapshot;

interface TrainerSalaryScheme
{
    public function code(): string;

    public function permissionName(): string;

    public function draftSubtitle(): string;

    public function draftTableView(): string;

    public function sheetDetailTableView(): string;

    /**
     * Доп. данные для черновика схемы (поля на весь период и т.п.).
     *
     * @return array<string, mixed>
     */
    public function draftViewData(TrainerSalaryPeriod $period): array;

    /**
     * @return array<string, list<string>>
     */
    public function draftRules(): array;

    /**
     * @return array<string, string>
     */
    public function draftAttributes(): array;

    /**
     * @return array<string, string>
     */
    public function draftMessages(): array;

    /**
     * @return list<string>
     */
    public function draftFieldKeys(): array;

    public function syncDraftLines(TrainerSalaryPeriod $period, int $partnerId): void;

    public function refreshComputedInputs(
        TrainerSalaryPeriod $period,
        int $partnerId,
        string $dateFrom,
        string $dateTo,
    ): void;

    public function createDraftLine(TrainerSalaryPeriod $period, TrainerProfile $profile): TrainerSalaryDraftLine;

    /**
     * @param array<string, mixed> $data
     */
    public function applyDraftInput(TrainerSalaryDraftLine $draft, array $data): void;

    /**
     * Общие для школы поля (X, базовое среднее группы) требуют пересчёта всех тренеров периода.
     *
     * @param array<string, mixed> $data
     */
    public function draftInputRequiresAllTrainersRecompute(array $data): bool;

    public function prefersFullTableReload(): bool;

    /**
     * Сброс черновика схемы при смене схемы месяца (только если слепков ещё нет).
     */
    public function discardUnlockedDraft(TrainerSalaryPeriod $period): void;

    public function compute(TrainerSalaryDraftLine $draft): void;

    /**
     * @return array<string, mixed>
     */
    public function snapshotAttributes(TrainerSalaryDraftLine $draft): array;

    public function afterSnapshotCreated(TrainerSalarySnapshot $snapshot, TrainerSalaryDraftLine $draft): void;

    /**
     * @return array<string, mixed>
     */
    public function rowPayload(TrainerSalaryDraftLine $draft, string $trainerName): array;

    /**
     * @return array<string, mixed>
     */
    public function snapshotRowPayload(TrainerSalarySnapshot $snapshot, string $trainerName): array;
}
