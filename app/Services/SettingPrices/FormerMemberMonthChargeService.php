<?php

declare(strict_types=1);

namespace App\Services\SettingPrices;

use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\Postpay\PostpayAmountCalculator;
use Illuminate\Validation\ValidationException;

/**
 * Снятие неоплаченного месячного начисления у бывшего участника группы.
 */
final class FormerMemberMonthChargeService
{
    public const REASON_PAID = 'Нельзя удалить: начисление уже оплачено.';

    public const REASON_POSTPAY_VISITS = 'Нельзя удалить: по постоплате уже есть посещения.';

    public const REASON_LAID_OUT = 'Нельзя удалить: связанные занятия уже стоят в расписании.';

    public const REASON_USED = 'Нельзя удалить: по абонементу уже были занятия.';

    public const ANNULLED_PAY_MESSAGE = 'Этот абонемент был аннулирован. Если у вас остались вопросы, свяжитесь с клубом.';

    public function __construct(
        private readonly UsersPriceLessonPackageSync $ulpSync,
        private readonly PostpayAmountCalculator $postpayCalculator,
    ) {
    }

    /**
     * @return array{can_clear: bool, block_reason: string}
     */
    public function assess(UserPrice $row): array
    {
        if ($row->effective_is_paid) {
            return $this->blocked(self::REASON_PAID);
        }

        $row->loadMissing(['lessonPackage', 'userLessonPackage']);

        if ($row->lessonPackage?->isPostpay() === true) {
            $visits = (int) ($this->postpayCalculator->forUserPrice($row, $row->lessonPackage)['visits'] ?? 0);
            if ($visits > 0) {
                return $this->blocked(self::REASON_POSTPAY_VISITS);
            }
        }

        $ulp = $this->linkedUlp($row);
        if ($ulp !== null) {
            if ($ulp->isLaidOutInSchedule()) {
                return $this->blocked(self::REASON_LAID_OUT);
            }
            if ((int) $ulp->lessons_remaining !== (int) $ulp->lessons_total) {
                return $this->blocked(self::REASON_USED);
            }
        }

        return [
            'can_clear' => true,
            'block_reason' => '',
        ];
    }

    public function decorate(UserPrice $row): void
    {
        $assessment = $this->assess($row);
        $row->setAttribute('can_clear_former_charge', $assessment['can_clear']);
        $row->setAttribute('former_charge_clear_block_reason', $assessment['block_reason']);
    }

    /**
     * Обнуляет начисление и снимает неразложенный неиспользованный ULP.
     *
     * @throws ValidationException
     */
    public function clear(UserPrice $row, ?int $actorId = null): void
    {
        $assessment = $this->assess($row);
        if (! $assessment['can_clear']) {
            $reason = $assessment['block_reason'] !== ''
                ? $assessment['block_reason']
                : 'Нельзя удалить это начисление.';

            throw ValidationException::withMessages([
                'charge' => [$reason],
            ]);
        }

        $row->price_cents = 0;
        $row->lesson_package_id = null;
        $row->discount_percent = null;
        $row->discount_comment = null;
        $row->save();

        try {
            $this->ulpSync->syncForUserPrice($row, $actorId);
        } catch (UsersPriceLessonPackageSyncException $e) {
            throw ValidationException::withMessages([
                $e->field() => [$e->getMessage()],
            ]);
        }
    }

    /**
     * @return array{can_clear: bool, block_reason: string}
     */
    private function blocked(string $reason): array
    {
        return [
            'can_clear' => false,
            'block_reason' => $reason,
        ];
    }

    private function linkedUlp(UserPrice $row): ?UserLessonPackage
    {
        if ($row->relationLoaded('userLessonPackage') && $row->userLessonPackage instanceof UserLessonPackage) {
            return $row->userLessonPackage;
        }

        $fk = $row->user_lesson_package_id !== null ? (int) $row->user_lesson_package_id : 0;
        if ($fk <= 0) {
            return null;
        }

        return UserLessonPackage::query()->find($fk);
    }
}
