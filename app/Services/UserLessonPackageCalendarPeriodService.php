<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserLessonPackage;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Период действия назначения ({@see UserLessonPackage::starts_at} / {@see UserLessonPackage::ends_at})
 * задаётся при первой привязке к календарю расписания школы, а не при создании записи на странице назначений.
 */
final class UserLessonPackageCalendarPeriodService
{
    /**
     * Если период ещё не задан — выставляет {@code starts_at} (дата якоря) и {@code ends_at} = starts_at + duration_days шаблона.
     * Если обе даты уже заданы — ничего не делает.
     *
     * @throws InvalidArgumentException при частично заполненном периоде или при duration_days &lt; 1
     */
    public function applyFirstCalendarAnchor(UserLessonPackage $ulp, CarbonImmutable $periodStart): void
    {
        $ulp->loadMissing('lessonPackage:id,duration_days');

        if ($ulp->starts_at !== null && $ulp->ends_at !== null) {
            return;
        }

        if ($ulp->starts_at !== null || $ulp->ends_at !== null) {
            throw new InvalidArgumentException(
                'Неконсистентный период назначения: заполнена только одна из дат начала/окончания.'
            );
        }

        $days = (int) ($ulp->lessonPackage?->duration_days ?? 0);
        if ($days < 1) {
            throw new InvalidArgumentException(
                'У шаблона абонемента не задан срок действия (duration_days).'
            );
        }

        $start = $periodStart->startOfDay();
        $end = $start->addDays($days)->startOfDay();

        $ulp->starts_at = $start->toDateString();
        $ulp->ends_at = $end->toDateString();
        $ulp->save();
    }
}
