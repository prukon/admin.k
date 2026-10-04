<?php

declare(strict_types=1);

namespace App\Services\SettingPrices;

use App\Enums\AuditEvent;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Services\Postpay\PostpayAmountCalculator;
use App\Support\Money;
use Carbon\Carbon;
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
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @return array{can_clear: bool, block_reason: string}
     */
    public function assess(UserPrice $row): array
    {
        if ($row->amountIsFrozen()) {
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
     * Снимает неоплаченные начисления ученика по группам, из которых его убрали.
     * Оплаченные и те, что нельзя снять (занятия уже были, раскладка, постоплата с посещениями), остаются.
     * Смена группы при этом не блокируется.
     *
     * @param  list<int>  $teamIds
     */
    public function clearUnpaidOnTeamLeave(User $user, array $teamIds, ?int $actorId = null): void
    {
        $teamIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $teamIds),
            static fn (int $id) => $id > 0
        )));
        if ($teamIds === []) {
            return;
        }

        $titles = Team::query()
            ->whereIn('id', $teamIds)
            ->pluck('title', 'id');

        $studentLabel = trim((string) $user->lastname.' '.(string) $user->name);
        if ($studentLabel === '') {
            $studentLabel = trim((string) $user->name);
        }
        if ($studentLabel === '') {
            $studentLabel = 'ученик';
        }

        $rows = UserPrice::query()
            ->where('user_id', (int) $user->id)
            ->whereIn('team_id', $teamIds)
            ->where('price_cents', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            if (! $this->assess($row)['can_clear']) {
                continue;
            }

            $oldCents = (int) $row->price_cents;
            $oldPackageId = $row->lesson_package_id !== null ? (int) $row->lesson_package_id : null;
            $teamId = (int) $row->team_id;
            $teamTitle = trim((string) ($titles->get($teamId) ?? ''));
            if ($teamTitle === '') {
                $teamTitle = 'Группа';
            }

            $this->clear($row, $actorId);

            $this->auditLogger->record(
                AuditEvent::PricingFormerChargeCleared,
                AuditContext::make($this->clearedAuditDescription(
                    $oldCents,
                    $oldPackageId,
                    $this->periodLabel($row->new_month),
                    $teamTitle,
                    $studentLabel,
                    (int) $user->id,
                ))
                    ->withUser($user)
                    ->withPartnerId((int) $user->partner_id > 0 ? (int) $user->partner_id : null)
                    ->withTargetReference(UserPrice::class, (int) $row->id, $studentLabel)
                    ->withCreatedAt(now())
            );
        }
    }

    public function clearedAuditDescription(
        int $cents,
        ?int $packageId,
        string $periodLabel,
        string $teamTitle,
        string $studentLabel,
        int $userId,
    ): string {
        $amount = str_replace(' ', '', Money::formatRub($cents));
        $packageBit = $packageId !== null && $packageId > 0 ? ' Абонемент #'.$packageId.'.' : '';
        $student = trim($studentLabel) !== '' ? trim($studentLabel) : 'ученик';

        return sprintf(
            'Снято начисление бывшего участника: %s руб.%s Период: %s. Группа: %s. Ученик: %s (#%d).',
            $amount,
            $packageBit,
            $periodLabel,
            $teamTitle,
            $student,
            $userId,
        );
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

    private function periodLabel(mixed $newMonth): string
    {
        if ($newMonth === null || $newMonth === '') {
            return '—';
        }

        $date = Carbon::parse((string) $newMonth);
        $names = [
            1 => 'Январь',
            2 => 'Февраль',
            3 => 'Март',
            4 => 'Апрель',
            5 => 'Май',
            6 => 'Июнь',
            7 => 'Июль',
            8 => 'Август',
            9 => 'Сентябрь',
            10 => 'Октябрь',
            11 => 'Ноябрь',
            12 => 'Декабрь',
        ];

        return ($names[(int) $date->month] ?? '').' '.$date->year;
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
