<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserTeamScheduleSlot;
use App\Services\Pricing\UserPercentDiscount;
use App\Services\TeamScheduleCalendarService;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Доступность действий привязки в модалке слота для выбранного ученика и даты.
 *
 * @phpstan-type ActionPayload array{
 *   allowed: bool,
 *   reason: string|null,
 *   mode?: 'bind_existing'|'create_new'|null,
 *   existing_assignments?: list<array{id: int, label: string}>,
 *   templates?: list<array{id: int, label: string, fee_amount_default: float, discount_percent: int|null, discount_comment: string|null, discount_tooltip: string|null}>,
 *   group_patterns?: list<array{id: int, weekday: int, time_start: string, time_end: string}>
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
        $fixed = $this->evaluateFixed($partnerId, $userId, $calendarRowExists, $calendarBlockReason, $slot, $occurrence);
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

        $autoProlongReason = app(UserLessonPackageAutoProlongGuard::class)->blockReasonForUser($userId);
        if ($autoProlongReason !== null) {
            return [
                'allowed' => false,
                'reason' => $autoProlongReason,
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
    private function evaluateFixed(
        int $partnerId,
        int $userId,
        bool $calendarRowExists,
        string $calendarBlockReason,
        TeamScheduleSlot $slot,
        CarbonImmutable $occurrence,
    ): array {
        $groupPatterns = $this->groupPatternsForSlot($slot, $occurrence);

        if ($calendarRowExists) {
            return [
                'allowed' => false,
                'reason' => $calendarBlockReason,
                'existing_assignments' => [],
                'group_patterns' => $groupPatterns,
            ];
        }

        $existingRows = $this->assignmentEligibility
            ->fixedAssignmentsQuery($partnerId)
            ->where('user_id', $userId)
            ->get();

        $blocking = app(UserLessonPackageAutoProlongGuard::class)->findBlockingAssignment($userId);
        if ($blocking !== null) {
            $existingRows = $existingRows
                ->filter(fn ($ulp) => (int) $ulp->id === (int) $blocking->id)
                ->values();

            if ($existingRows->isEmpty()) {
                return [
                    'allowed' => false,
                    'reason' => UserLessonPackageAutoProlongGuard::BLOCK_REASON,
                    'existing_assignments' => [],
                    'group_patterns' => $groupPatterns,
                ];
            }
        }

        if ($existingRows->isEmpty()) {
            return [
                'allowed' => false,
                'reason' => 'Нет фиксированного абонемента без привязки к календарю с доступным объёмом занятий.',
                'existing_assignments' => [],
                'group_patterns' => $groupPatterns,
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
            'group_patterns' => $groupPatterns,
        ];
    }

    /**
     * Остальные активные слоты этой же группы на том же объекте (включая якорный слот) —
     * источник для автоподстановки строк «Шаблон привязки» при фиксированном абонементе.
     *
     * Активность sibling-слота проверяется на ближайшую дату его дня недели
     * (не на дату якоря): у слотов ср/пт date_start часто = их первый день,
     * и проверка «активен ли в понедельник» ложно отсекала их из шаблона.
     *
     * @return list<array{id: int, weekday: int, time_start: string, time_end: string}>
     */
    private function groupPatternsForSlot(TeamScheduleSlot $slot, CarbonImmutable $occurrence): array
    {
        $rows = TeamScheduleSlot::query()
            ->where('partner_id', (int) $slot->partner_id)
            ->where('team_id', (int) $slot->team_id)
            ->when(
                $slot->location_id === null,
                fn ($q) => $q->whereNull('location_id'),
                fn ($q) => $q->where('location_id', (int) $slot->location_id),
            )
            ->orderBy('weekday')
            ->orderBy('time_start')
            ->get();

        return $rows
            ->filter(function (TeamScheduleSlot $row) use ($slot, $occurrence): bool {
                if ((int) $row->id === (int) $slot->id) {
                    return true;
                }

                $candidate = $this->nextWeekdayOnOrAfter($occurrence, (int) $row->weekday);

                return $this->calendarService->slotActiveOnDate($row, $candidate);
            })
            ->map(fn (TeamScheduleSlot $row) => [
                'id' => (int) $row->id,
                'weekday' => (int) $row->weekday,
                'time_start' => substr((string) $row->time_start, 0, 5),
                'time_end' => substr((string) $row->time_end, 0, 5),
            ])
            ->values()
            ->all();
    }

    /**
     * Ближайшая дата с ISO-днём недели $weekday (1=пн…7=вс) не раньше $from.
     */
    private function nextWeekdayOnOrAfter(CarbonImmutable $from, int $weekday): CarbonImmutable
    {
        $fromDow = (int) $from->format('N');
        $delta = ($weekday - $fromDow + 7) % 7;

        return $from->addDays($delta);
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

        $autoProlongReason = app(UserLessonPackageAutoProlongGuard::class)->blockReasonForUser($userId);
        if ($autoProlongReason !== null) {
            return [
                'allowed' => false,
                'reason' => $autoProlongReason,
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

        $user = User::query()->find($userId);
        $templates = $templateRows->map(function ($pkg) use ($user) {
            $catalogCents = (int) $pkg->price_cents;
            $payableCents = UserPercentDiscount::payableCentsForUser($catalogCents, $user);
            $snap = UserPercentDiscount::snapshotFromUser($user);

            return [
                'id' => (int) $pkg->id,
                'label' => (string) $pkg->name,
                'fee_amount_default' => (float) Money::fromCents($payableCents),
                'discount_percent' => $snap['discount_percent'],
                'discount_comment' => $snap['discount_comment'],
                'discount_tooltip' => UserPercentDiscount::tooltip(
                    $snap['discount_percent'],
                    $snap['discount_comment']
                ),
            ];
        })->values()->all();

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
            'group_patterns' => [],
        ];

        return [
            'flexible' => $flexBlocked,
            'fixed' => $fixedBlocked,
            'single_lesson' => $singleBlocked,
            'trial' => $simple,
        ];
    }
}
