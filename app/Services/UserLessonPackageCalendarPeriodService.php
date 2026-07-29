<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
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

    /**
     * Меняет только {@code ends_at} уже заданного периода. {@code starts_at} не трогает.
     * Синхронизирует {@code ends_at} у связанных строк календаря; новые занятия не создаёт и не удаляет.
     *
     * @throws InvalidArgumentException если период не задан или новая дата раньше начала / последнего занятия
     */
    public function updateEndsAt(UserLessonPackage $ulp, CarbonImmutable $newEndsAt): void
    {
        if ($ulp->starts_at === null || $ulp->ends_at === null) {
            throw new InvalidArgumentException(
                'Период назначения ещё не задан — дату окончания можно менять после привязки к календарю.'
            );
        }

        $start = CarbonImmutable::parse($ulp->starts_at->format('Y-m-d'))->startOfDay();
        $end = $newEndsAt->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException(
                'Дата окончания не может быть раньше даты начала.'
            );
        }

        $lastLessonRaw = UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', (int) $ulp->id)
            ->max('starts_at');

        if ($lastLessonRaw !== null && $lastLessonRaw !== '') {
            $lastLesson = CarbonImmutable::parse((string) $lastLessonRaw)->startOfDay();
            if ($end->lt($lastLesson)) {
                throw new InvalidArgumentException(
                    'Дата окончания не может быть раньше последнего занятия в календаре ('.$lastLesson->format('d.m.Y').').'
                );
            }
        }

        $endYmd = $end->toDateString();
        if ($ulp->ends_at->format('Y-m-d') === $endYmd) {
            return;
        }

        $ulp->ends_at = $endYmd;
        $ulp->save();

        UserTeamScheduleSlot::query()
            ->where('user_lesson_package_id', (int) $ulp->id)
            ->update(['ends_at' => $endYmd]);
    }
}
