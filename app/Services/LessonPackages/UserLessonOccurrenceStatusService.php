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
 * Сохранение статуса занятия в календаре школы / журнале и корректировка остатка абонемента / пробного баланса.
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
        ?int $trainerProfileId = null,
        ?string $comment = null,
    ): UserLessonOccurrenceStatusEvent {
        $run = function () use (
            $partnerId,
            $userId,
            $teamScheduleSlotId,
            $occurrenceDateYmd,
            $userLessonPackageId,
            $status,
            $createdByUserId,
            $trainerProfileId,
            $comment,
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
                $utss = UserTeamScheduleSlot::query()
                    ->where('partner_id', $partnerId)
                    ->where('user_id', $userId)
                    ->where('team_schedule_slot_id', $teamScheduleSlotId)
                    ->whereDate('starts_at', $occurrenceDateYmd)
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

                // Пробное — корректируем trial_lessons_remaining; постоплата (non-trial) — без списания ULP.
                if ($utss->is_trial_lesson) {
                    $prevConsumed = $prevEvent?->lessonOccurrenceStatus?->consumes_lesson;

                    $delta = UserLessonPackageConsumptionAdjuster::remainingLessonsDelta(
                        $prevConsumed,
                        (bool) $status->consumes_lesson
                    );

                    if ($delta !== 0) {
                        SchoolScheduleTrialLessonConsumptionAdjuster::applyRemainingLessonsDelta($utss, $delta);
                    }
                }
            }

            $attended = (string) $status->code === LessonOccurrenceStatus::CODE_ATTENDED;
            $resolvedTrainerId = $attended ? $trainerProfileId : null;
            $resolvedComment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

            return UserLessonOccurrenceStatusEvent::query()->create([
                'partner_id' => $partnerId,
                'user_id' => $userId,
                'team_schedule_slot_id' => $teamScheduleSlotId,
                'occurrence_date' => $occurrenceDateYmd,
                'user_lesson_package_id' => $userLessonPackageId,
                'lesson_occurrence_status_id' => (int) $status->id,
                'trainer_profile_id' => $resolvedTrainerId,
                'comment' => $resolvedComment,
                'created_by' => $createdByUserId,
            ]);
        };

        // Уже внутри внешней транзакции (раскладка из журнала) — без вложенного DB::transaction,
        // иначе на части окружений savepoint/nested commit даёт рассинхрон со строками utss.
        if (DB::transactionLevel() > 0) {
            return $run();
        }

        return DB::transaction($run);
    }
}
