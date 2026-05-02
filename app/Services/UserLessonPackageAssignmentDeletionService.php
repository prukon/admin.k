<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserLessonPackage;
use App\Models\UserLessonPackageFreeze;
use App\Models\UserLessonPackageTimeSlot;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Удаление назначения абонемента и связанных строк без «хвостов» в БД.
 */
final class UserLessonPackageAssignmentDeletionService
{
    /**
     * Разрешено только пока не было списаний занятий (полный остаток).
     */
    public function deleteOrAbort(UserLessonPackage $assignment): void
    {
        if ((int) $assignment->lessons_remaining !== (int) $assignment->lessons_total) {
            throw new HttpException(422, 'Удаление возможно только при полном остатке занятий (назначение не использовалось).');
        }

        DB::transaction(function () use ($assignment) {
            UserLessonPackageFreeze::query()
                ->where('user_lesson_package_id', (int) $assignment->id)
                ->delete();

            UserLessonPackageTimeSlot::query()
                ->where('user_lesson_package_id', (int) $assignment->id)
                ->delete();

            $assignment->delete();
        });
    }
}
