<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Период действия назначения ({@see UserLessonPackage::starts_at} / {@see UserLessonPackage::ends_at}).
 *
 * Классика: при первой привязке к календарю — starts_at = якорь, ends_at = якорь + duration_days шаблона.
 * Из установки цен (billing_month): ends_at задаётся при создании ULP (конец месяца);
 * при раскладке дописывается только starts_at (без duration_days).
 */
final class UserLessonPackageCalendarPeriodService
{
    /**
     * Выставляет / дополняет период при первой привязке к календарю.
     *
     * @throws InvalidArgumentException
     */
    public function applyFirstCalendarAnchor(UserLessonPackage $ulp, CarbonImmutable $periodStart): void
    {
        $ulp->loadMissing('lessonPackage:id,duration_days');

        if ($ulp->starts_at !== null && $ulp->ends_at !== null) {
            return;
        }

        $start = $periodStart->startOfDay();

        // Месячное назначение из установки цен.
        if ($ulp->isFromSettingPrices()) {
            $this->assertAnchorInsideBillingMonth($ulp, $start);

            if ($ulp->starts_at !== null) {
                return;
            }

            $monthEndYmd = $ulp->billingMonthEndDate();
            if ($monthEndYmd === null) {
                throw new InvalidArgumentException('Не задан месяц начисления назначения.');
            }

            $ulp->starts_at = $start->toDateString();
            if ($ulp->ends_at === null || $ulp->ends_at->format('Y-m-d') !== $monthEndYmd) {
                $ulp->ends_at = $monthEndYmd;
            }
            $ulp->save();

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

        $end = $start->addDays($days)->startOfDay();

        $ulp->starts_at = $start->toDateString();
        $ulp->ends_at = $end->toDateString();
        $ulp->save();
    }

    /**
     * Конец периода для построения цепочки fixed (place / preview).
     *
     * @throws InvalidArgumentException
     */
    public function resolvePlacementPeriodEnd(UserLessonPackage $ulp, CarbonImmutable $anchorDate): CarbonImmutable
    {
        $ulp->loadMissing('lessonPackage:id,duration_days');
        $anchor = $anchorDate->startOfDay();

        if ($ulp->isFromSettingPrices()) {
            $this->assertAnchorInsideBillingMonth($ulp, $anchor);
            if ($ulp->ends_at !== null) {
                return CarbonImmutable::parse($ulp->ends_at->format('Y-m-d'))->startOfDay();
            }
            $monthEndYmd = $ulp->billingMonthEndDate();
            if ($monthEndYmd === null) {
                throw new InvalidArgumentException('Не задан месяц начисления назначения.');
            }

            return CarbonImmutable::parse($monthEndYmd)->startOfDay();
        }

        $days = (int) ($ulp->lessonPackage?->duration_days ?? 0);
        if ($days < 1) {
            throw new InvalidArgumentException('У шаблона абонемента не задан срок действия (duration_days).');
        }

        return $anchor->addDays($days)->startOfDay();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertAnchorInsideBillingMonth(UserLessonPackage $ulp, CarbonImmutable $anchorDate): void
    {
        if ($ulp->billing_month === null) {
            return;
        }

        $monthStart = CarbonImmutable::parse($ulp->billing_month->format('Y-m-d'))->startOfMonth();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $day = $anchorDate->startOfDay();

        if ($day->lt($monthStart) || $day->gt($monthEnd)) {
            throw new InvalidArgumentException(sprintf(
                'Дата начала должна быть в пределах месяца начисления (%s–%s).',
                $monthStart->format('d.m.Y'),
                $monthEnd->format('d.m.Y')
            ));
        }
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
