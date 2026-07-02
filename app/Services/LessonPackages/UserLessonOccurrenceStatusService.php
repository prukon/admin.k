<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Models\LessonOccurrenceStatus;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Services\SchoolScheduleTrialLessonConsumptionAdjuster;
use App\Services\UserLessonPackageConsumptionAdjuster;
use Illuminate\Support\Facades\DB;

/**
 * Сохранение статуса занятия в календаре школы и корректировка остатка абонемента / пробного баланса.
 */
final class UserLessonOccurrenceStatusService
{
    /**
     * @throws \DomainException
     */
    public function apply(
        int $partnerId,
        int $userId,
        int $teamScheduleSlotId,
        string $occurrenceDateYmd,
        ?int $userLessonPackageId,
        LessonOccurrenceStatus $status,
        ?int $createdByUserId,
    ): UserLessonOccurrenceStatusEvent {
        return DB::transaction(function () use (
            $partnerId,
            $userId,
            $teamScheduleSlotId,
            $occurrenceDateYmd,
            $userLessonPackageId,
            $status,
            $createdByUserId,
        ): UserLessonOccurrenceStatusEvent {
            if ($userLessonPackageId !== null) {
                $prevEvent = UserLessonOccurrenceStatusEvent::query()
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->where('team_schedule_slot_id', $teamScheduleSlotId)
                    ->whereDate('occurrence_date', $occurrenceDateYmd)
                    ->where('user_lesson_package_id', $userLessonPackageId)
                    ->with(['lessonOccurrenceStatus:id,consumes_lesson'])
                    ->orderByDesc('id')
                    ->first();

                $prevConsumed = $prevEvent?->lessonOccurrenceStatus?->consumes_lesson;

                $delta = UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(
                    $prevConsumed,
                    (bool) $status->consumes_lesson
                );

                if ($delta !== 0) {
                    $ulp = UserLessonPackage::query()->whereKey($userLessonPackageId)->firstOrFail();
                    UserLessonPackageConsumptionAdjuster::applyRemainingLessonsDelta($ulp, $delta);
                }
            } else {
                $trialUtss = UserTeamScheduleSlot::query()
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->where('team_schedule_slot_id', $teamScheduleSlotId)
                    ->whereDate('starts_at', $occurrenceDateYmd)
                    ->where('is_trial_lesson', true)
                    ->whereNull('user_lesson_package_id')
                    ->firstOrFail();

                $prevEvent = UserLessonOccurrenceStatusEvent::query()
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->where('team_schedule_slot_id', $teamScheduleSlotId)
                    ->whereDate('occurrence_date', $occurrenceDateYmd)
                    ->whereNull('user_lesson_package_id')
                    ->with(['lessonOccurrenceStatus:id,consumes_lesson'])
                    ->orderByDesc('id')
                    ->first();

                $prevConsumed = $prevEvent?->lessonOccurrenceStatus?->consumes_lesson;

                $delta = UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(
                    $prevConsumed,
                    (bool) $status->consumes_lesson
                );

                if ($delta !== 0) {
                    SchoolScheduleTrialLessonConsumptionAdjuster::applyRemainingLessonsDelta($trialUtss, $delta);
                }
            }

            return UserLessonOccurrenceStatusEvent::query()->create([
                'partner_id' => $partnerId,
                'user_id' => $userId,
                'team_schedule_slot_id' => $teamScheduleSlotId,
                'occurrence_date' => $occurrenceDateYmd,
                'user_lesson_package_id' => $userLessonPackageId,
                'lesson_occurrence_status_id' => (int) $status->id,
                'created_by' => $createdByUserId,
            ]);
        });
    }
}
