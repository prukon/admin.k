<?php

namespace App\Services;

use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\DB;

/**
 * Корректировка остатка занятий абонемента при смене статуса события.
 * Дельта считается по паре «предыдущий статус списывал / новый списывает» на момент операции.
 */
final class UserLessonPackageConsumptionAdjuster
{
    /**
     * Изменение поля lessons_remaining: +1 возврат, −1 списание, 0 без изменений.
     *
     * @param  bool|null  $previousConsumed  флаг предыдущего статуса (null — как «не списывал»)
     */
    public static function remainingLessonsDelta(?bool $previousConsumed, bool $newConsumesLesson): int
    {
        $prev = (bool) $previousConsumed;
        $next = $newConsumesLesson;

        if ($prev === $next) {
            return 0;
        }

        return ($prev && ! $next) ? 1 : -1;
    }

    /**
     * @throws \DomainException если остаток выходит за пределы [0, lessons_total]
     */
    public static function applyRemainingLessonsDelta(UserLessonPackage $assignment, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        DB::transaction(function () use ($assignment, $delta) {
            $row = UserLessonPackage::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $next = $row->lessons_remaining + $delta;

            if ($next < 0) {
                throw new \DomainException('Недостаточно оставшихся занятий на абонементе.');
            }

            if ($next > $row->lessons_total) {
                throw new \DomainException('Остаток занятий не может превышать объём абонемента.');
            }

            $row->forceFill(['lessons_remaining' => $next])->save();
        });
    }
}
