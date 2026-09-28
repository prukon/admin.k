<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Счётчик текущих пробных занятий ученика (users.school_schedule_trial_lessons_count).
 */
final class SchoolScheduleTrialLessonsCounter
{
    public function increment(int $userId): void
    {
        $this->adjust($userId, 1);
    }

    public function decrement(int $userId): void
    {
        $this->adjust($userId, -1);
    }

    private function adjust(int $userId, int $delta): void
    {
        if ($userId < 1 || $delta === 0) {
            return;
        }

        DB::transaction(function () use ($userId, $delta): void {
            /** @var User|null $user */
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            if ($user === null) {
                return;
            }

            $next = max(0, (int) $user->school_schedule_trial_lessons_count + $delta);
            if ($next === (int) $user->school_schedule_trial_lessons_count) {
                return;
            }

            $user->forceFill(['school_schedule_trial_lessons_count' => $next])->saveQuietly();
        });
    }
}
