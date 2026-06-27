<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Models\TeamScheduleSlot;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamScheduleCalendarService;
use Carbon\CarbonImmutable;

/**
 * Доступность действий привязки в модалке слота для выбранного ученика и даты.
 *
 * @phpstan-type ActionPayload array{
 *   allowed: bool,
 *   reason: string|null,
 *   mode?: 'bind_existing'|'create_new'|null,
 *   existing_assignments?: list<array{id: int, label: string}>,
 *   templates?: list<array{id: int, label: string, fee_amount_default: float}>
 * }
 * @phpstan-type SlotUserBindActionsPayload array{
 *   flexible: ActionPayload,
 *   fixed: ActionPayload,
 *   single_lesson: ActionPayload,
 *   trial: ActionPayload,
 * }
 */
final class SchoolCalendarSlotUserBindActionsService
{
    public function __construct(
        private readonly TeamScheduleCalendarService $calendarService,
        private readonly SchoolCalendarAssignmentEligibilityService $assignmentEligibility,
        private readonly SchoolCalendarTrialEligibilityService $trialEligibility,
    ) {
    }

    /**
     * @return SlotUserBindActionsPayload
     */
    public function evaluate(int $partnerId, int $userId, int $teamScheduleSlotId, string $occurrenceDateYmd): array
    {
        if (! $this->assignmentEligibility->userBelongsToPartnerAndEnabled($partnerId, $userId)) {
            $reason = 'Ученик не найден или недоступен.';

            return $this->allBlocked($reason);
        }

        try {
            $occurrence = CarbonImmutable::createFromFormat('Y-m-d', $occurrenceDateYmd)->startOfDay();
        } catch (\Throwable) {
            return $this->allBlocked('Некорректная дата.');
        }

        /** @var TeamScheduleSlot|null $slot */
        $slot = TeamScheduleSlot::query()->whereKey($teamScheduleSlotId)->first();

        if (! $slot || (int) $slot->partner_id !== $partnerId) {
            return $this->allBlocked('Слот расписания не найден.');
        }

        if ((int) $slot->weekday !== (int) $occurrence->format('N')) {
            return $this->allBlocked('Дата не соответствует дню недели слота.');
        }

        if (! $this->calendarService->slotActiveOnDate($slot, $occurrence)) {
            return $this->allBlocked('Слот недействителен на эту дату.');
        }

        if ($this->calendarService->isOccurrenceSkipped((int) $slot->id, $occurrence)) {
            return $this->allBlocked('На эту дату занятие исключено из расписания школы.');
        }

        $calendarRowExists = UserTeamScheduleSlot::query()
            ->where('user_id', $userId)
            ->where('team_schedule_slot_id', $slot->id)
            ->whereDate('starts_at', $occurrence->toDateString())
            ->exists();

        $calendarBlockReason = 'На это занятие у ученика уже есть запись в календаре на выбранную дату.';

        $flexible = $this->evaluateFlexible($partnerId, $userId, $occurrence, $calendarRowExists, $calendarBlockReason);
        $fixed = $this->evaluateFixed($partnerId, $userId, $calendarRowExists, $calendarBlockReason);
        $single = $this->evaluateSingleLesson($partnerId, $userId, $calendarRowExists, $calendarBlockReason);
        $trial = $this->trialEligibility->evaluate($partnerId, $userId, (int) $slot->id, $occurrence);

        return [
            'flexible' => $flexible,
            'fixed' => $fixed,
            'single_lesson' => $single,
            'trial' => [
                'allowed' => $trial['allowed'],
                'reason' => $trial['reason'],
            ],
        ];
    }

    /**
     * @return ActionPayload
     */
    private function evaluateFlexible(
        int $partnerId,
        int $userId,
        CarbonImmutable $occurrence,
        bool $calendarRowExists,
        string $calendarBlockReason,
    ): array {
        if ($calendarRowExists) {
            return [
                'allowed' => false,
                'reason' => $calendarBlockReason,
                'existing_assignments' => [],
            ];
        }

        $d = $occurrence->toDateString();
        $occurrenceAware = $this->assignmentEligibility
            ->flexibleAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->where(function ($q) use ($d) {
                $q->where(function ($q2) {
                    $q2->whereNull('starts_at')->whereNull('ends_at');
                })->orWhere(function ($q2) use ($d) {
                    $q2->whereDate('starts_at', '<=', $d)
                        ->whereDate('ends_at', '>=', $d);
                });
            });

        $existingRows = $occurrenceAware->get();

        if ($existingRows->isEmpty()) {
            return [
                'allowed' => false,
                'reason' => 'Нет гибкого абонемента со свободной записью в календаре (лимит занятий или период), подходящего для этой даты.',
                'existing_assignments' => [],
            ];
        }

        $existing = $existingRows->map(fn ($ulp) => [
            'id' => (int) $ulp->id,
            'label' => $this->assignmentEligibility->formatFlexibleAssignmentLabel($ulp),
        ])->values()->all();

        return [
            'allowed' => true,
            'reason' => null,
            'existing_assignments' => $existing,
        ];
    }

    /**
     * @return ActionPayload
     */
    private function evaluateFixed(int $partnerId, int $userId, bool $calendarRowExists, string $calendarBlockReason): array
    {
        if ($calendarRowExists) {
            return [
                'allowed' => false,
                'reason' => $calendarBlockReason,
                'existing_assignments' => [],
            ];
        }

        $existingRows = $this->assignmentEligibility
            ->fixedAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->get();

        if ($existingRows->isEmpty()) {
            return [
                'allowed' => false,
                'reason' => 'Нет фиксированного абонемента без привязки к календарю с доступным объёмом занятий.',
                'existing_assignments' => [],
            ];
        }

        $existing = $existingRows->map(fn ($ulp) => [
            'id' => (int) $ulp->id,
            'label' => $this->assignmentEligibility->formatFixedAssignmentLabel($ulp),
        ])->values()->all();

        return [
            'allowed' => true,
            'reason' => null,
            'existing_assignments' => $existing,
        ];
    }

    /**
     * @return ActionPayload
     */
    private function evaluateSingleLesson(int $partnerId, int $userId, bool $calendarRowExists, string $calendarBlockReason): array
    {
        if ($calendarRowExists) {
            return [
                'allowed' => false,
                'reason' => $calendarBlockReason,
                'mode' => null,
                'existing_assignments' => [],
                'templates' => [],
            ];
        }

        $existingRows = $this->assignmentEligibility
            ->singleLessonAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->get();

        if ($existingRows->isNotEmpty()) {
            $existing = $existingRows->map(fn ($ulp) => [
                'id' => (int) $ulp->id,
                'label' => $this->assignmentEligibility->formatSingleLessonAssignmentLabel($ulp),
            ])->values()->all();

            return [
                'allowed' => true,
                'reason' => null,
                'mode' => 'bind_existing',
                'existing_assignments' => $existing,
                'templates' => [],
            ];
        }

        $templateRows = $this->assignmentEligibility
            ->singleLessonTemplatesQuery($partnerId)
            ->get(['id', 'name', 'price_cents']);

        if ($templateRows->isEmpty()) {
            return [
                'allowed' => false,
                'reason' => 'Нет шаблонов разового занятия. Создайте абонемент с типом «Разовое занятие».',
                'mode' => 'create_new',
                'existing_assignments' => [],
                'templates' => [],
            ];
        }

        $templates = $templateRows->map(fn ($pkg) => [
            'id' => (int) $pkg->id,
            'label' => (string) $pkg->name,
            'fee_amount_default' => round(((int) $pkg->price_cents) / 100, 2),
        ])->values()->all();

        return [
            'allowed' => true,
            'reason' => null,
            'mode' => 'create_new',
            'existing_assignments' => [],
            'templates' => $templates,
        ];
    }

    /**
     * @return SlotUserBindActionsPayload
     */
    private function allBlocked(string $reason): array
    {
        $simple = ['allowed' => false, 'reason' => $reason];
        $flexBlocked = [
            'allowed' => false,
            'reason' => $reason,
            'existing_assignments' => [],
        ];
        $singleBlocked = [
            'allowed' => false,
            'reason' => $reason,
            'mode' => null,
            'existing_assignments' => [],
            'templates' => [],
        ];

        $fixedBlocked = [
            'allowed' => false,
            'reason' => $reason,
            'existing_assignments' => [],
        ];

        return [
            'flexible' => $flexBlocked,
            'fixed' => $fixedBlocked,
            'single_lesson' => $singleBlocked,
            'trial' => $simple,
        ];
    }
}
