<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\DB;

/**
 * Корректировка «остатка» пробного занятия на строке user_team_schedule_slots (trial_lessons_remaining).
 */
final class SchoolScheduleTrialLessonConsumptionAdjuster
{
    /**
     * @throws \DomainException
     */
    public static function applyRemainingLessonsDelta(UserTeamScheduleSlot $registration, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        DB::transaction(function () use ($registration, $delta): void {
            $row = UserTeamScheduleSlot::query()
                ->whereKey($registration->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $row->is_trial_lesson || $row->user_lesson_package_id !== null) {
                throw new \DomainException('Запись не является пробным занятием.');
            }

            $tot = (int) ($row->trial_lessons_total ?? 1);
            $rem = (int) ($row->trial_lessons_remaining ?? $tot);
            $next = $rem + $delta;

            if ($next < 0) {
                throw new \DomainException('Недостаточно оставшихся посещений для пробного занятия.');
            }

            if ($next > $tot) {
                throw new \DomainException('Остаток пробного занятия не может превышать объём записи.');
            }

            $row->forceFill([
                'trial_lessons_remaining' => $next,
                'trial_lessons_total' => $tot,
            ])->save();
        });
    }
}
