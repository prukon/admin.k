<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserTeamScheduleSlot;
use App\Services\TeamScheduleCalendarService;
use Carbon\CarbonImmutable;

/**
 * Проверка возможности пробной записи на слот/дату (календарь школы).
 *
 * @phpstan-type TrialEligibilityPayload array{allowed: bool, reason: string|null}
 */
final class SchoolCalendarTrialEligibilityService
{
    public function __construct(
        private readonly TeamScheduleCalendarService $calendarService,
    ) {
    }

    /**
     * @return TrialEligibilityPayload
     */
    public function evaluate(int $partnerId, int $userId, int $slotId, CarbonImmutable $occurrence): array
    {
        $userOk = User::query()
            ->where('partner_id', $partnerId)
            ->whereKey($userId)
            ->where('is_enabled', 1)
            ->exists();

        if (! $userOk) {
            return [
                'allowed' => false,
                'reason' => 'Ученик не найден или недоступен.',
            ];
        }

        /** @var TeamScheduleSlot|null $slot */
        $slot = TeamScheduleSlot::query()->whereKey($slotId)->first();

        if (! $slot || (int) $slot->partner_id !== $partnerId) {
            return [
                'allowed' => false,
                'reason' => 'Слот расписания не найден.',
            ];
        }

        if ((int) $slot->weekday !== (int) $occurrence->format('N')) {
            return [
                'allowed' => false,
                'reason' => 'Дата не соответствует дню недели слота.',
            ];
        }

        if (! $this->calendarService->slotActiveOnDate($slot, $occurrence)) {
            return [
                'allowed' => false,
                'reason' => 'Слот недействителен на эту дату.',
            ];
        }

        if ($this->calendarService->isOccurrenceSkipped((int) $slot->id, $occurrence)) {
            return [
                'allowed' => false,
                'reason' => 'На эту дату занятие исключено из расписания школы.',
            ];
        }

        /** @var UserTeamScheduleSlot|null $existing */
        $existing = UserTeamScheduleSlot::query()
            ->where('user_id', $userId)
            ->where('team_schedule_slot_id', $slotId)
            ->whereDate('starts_at', $occurrence->toDateString())
            ->first();

        if ($existing === null) {
            return ['allowed' => true, 'reason' => null];
        }

        if ($existing->user_lesson_package_id !== null) {
            return [
                'allowed' => false,
                'reason' => 'На это занятие у ученика уже есть запись по абонементу. Пробное занятие недоступно.',
            ];
        }

        if ($existing->is_trial_lesson) {
            return [
                'allowed' => false,
                'reason' => 'Пробная запись на это занятие уже добавлена.',
            ];
        }

        return [
            'allowed' => false,
            'reason' => 'На это занятие уже есть запись в календаре.',
        ];
    }
}
