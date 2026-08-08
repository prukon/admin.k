<?php

declare(strict_types=1);

namespace App\Services\SettingPrices;

use App\Models\LessonPackage;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\UserLessonPackageAssignmentDeletionService;
use App\Services\LessonPackages\UserLessonPackageAutoProlongGuard;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Синхронизация месячного начисления (users_prices) с назначением ULP.
 *
 * Postpay → ULP не создаём; при уходе с assignable на postpay/пусто — удаляем неразложенный ULP.
 * Assignable (fixed/flexible/no_schedule) → create/update ULP + FK + billing_month.
 */
final class UsersPriceLessonPackageSync
{
    public function __construct(
        private readonly UserLessonPackageAssignmentDeletionService $deletionService,
        private readonly UserLessonPackageAutoProlongGuard $autoProlongGuard,
    ) {
    }

    /**
     * После сохранения users_prices: привести связанный ULP в соответствие с lesson_package_id / price.
     *
     * @throws UsersPriceLessonPackageSyncException
     */
    public function syncForUserPrice(UserPrice $row, ?int $actorId = null): void
    {
        $row->refresh();

        $packageId = $row->lesson_package_id !== null ? (int) $row->lesson_package_id : null;
        $package = null;
        if ($packageId !== null && $packageId > 0) {
            $package = $row->relationLoaded('lessonPackage') && $row->lessonPackage
                ? $row->lessonPackage
                : LessonPackage::query()->find($packageId);
            if (! $package) {
                // Шаблон пропал — не трогаем ULP молча; вызывающий код обычно уже отфильтровал.
                return;
            }
        }

        $linked = $this->resolveLinkedUlp($row);

        // Нет пакета или postpay — ULP месячному начислению не нужен.
        if ($package === null || $package->isPostpay()) {
            $this->detachAndDeleteIfAllowed($row, $linked, $package !== null && $package->isPostpay()
                ? 'Нельзя сменить абонемент на постоплату: связанное назначение уже разложено в расписание.'
                : 'Нельзя снять абонемент: связанное назначение уже разложено в расписание.');

            return;
        }

        if (! in_array((string) $package->schedule_type, LessonPackage::ASSIGNMENT_SCHEDULE_TYPES, true)) {
            $this->detachAndDeleteIfAllowed(
                $row,
                $linked,
                'Нельзя сменить тип абонемента: связанное назначение уже разложено в расписание.'
            );

            return;
        }

        $billingMonth = $this->normalizeBillingMonth($row->new_month);
        $billingMonthStart = Carbon::parse($billingMonth)->startOfMonth()->format('Y-m-d');
        $billingMonthEnd = Carbon::parse($billingMonth)->endOfMonth()->format('Y-m-d');
        $feeAmountCents = (int) $row->price_cents;
        $lessons = max(0, (int) $package->lessons_count);
        $isFlexible = (string) $package->schedule_type === LessonPackage::SCHEDULE_TYPE_FLEXIBLE;
        // Гибкий из установки цен: период сразу на весь месяц (занятия ставят «на лету»).
        // Fixed / no_schedule: starts_at = null до раскладки; ends_at = конец месяца.
        $createStartsAt = $isFlexible ? $billingMonthStart : null;

        if ($linked === null) {
            if ($this->autoProlongGuard->isBlocked((int) $row->user_id)) {
                throw new UsersPriceLessonPackageSyncException(
                    'user_id',
                    UserLessonPackageAutoProlongGuard::BLOCK_REASON
                );
            }

            $ulp = UserLessonPackage::query()->create([
                'user_id' => (int) $row->user_id,
                'team_id' => (int) $row->team_id,
                'lesson_package_id' => (int) $package->id,
                'billing_month' => $billingMonth,
                'starts_at' => $createStartsAt,
                // Месячное назначение: конец периода = последний день billing_month (без duration_days).
                'ends_at' => $billingMonthEnd,
                'lessons_total' => $lessons,
                'lessons_remaining' => $lessons,
                'fee_amount_cents' => $feeAmountCents,
                'is_paid' => false,
                'created_by' => $actorId,
            ]);

            $row->user_lesson_package_id = (int) $ulp->id;
            $row->save();

            return;
        }

        $packageChanged = (int) $linked->lesson_package_id !== (int) $package->id;
        $placed = $linked->isLaidOutInSchedule();

        if ($packageChanged && $placed) {
            throw new UsersPriceLessonPackageSyncException(
                'lesson_package_id',
                'Нельзя сменить абонемент: назначение уже разложено в расписание. Сначала отмените раскладку или удалите занятия.'
            );
        }

        $payload = [];

        if ($packageChanged) {
            $payload['lesson_package_id'] = (int) $package->id;
            // Неразложенный ULP: объём как у нового шаблона (полный остаток).
            $payload['lessons_total'] = $lessons;
            $payload['lessons_remaining'] = $lessons;
        }

        if ($linked->billing_month === null
            || Carbon::parse((string) $linked->billing_month)->format('Y-m-d') !== $billingMonth
        ) {
            $payload['billing_month'] = $billingMonth;
        }

        if (! $placed) {
            $currentEnd = $linked->ends_at?->format('Y-m-d');
            if ($currentEnd !== $billingMonthEnd) {
                $payload['ends_at'] = $billingMonthEnd;
            }

            $desiredStartsAt = $isFlexible ? $billingMonthStart : null;
            $currentStart = $linked->starts_at?->format('Y-m-d');
            if ($currentStart !== $desiredStartsAt) {
                $payload['starts_at'] = $desiredStartsAt;
            }
        }

        if ((int) ($linked->team_id ?? 0) !== (int) $row->team_id) {
            $payload['team_id'] = (int) $row->team_id;
        }

        // Сумма: синхронизируем, если начисление и ULP не оплачены.
        if (! $row->effective_is_paid && ! $linked->effective_is_paid) {
            if ((int) $linked->fee_amount_cents !== $feeAmountCents) {
                $payload['fee_amount_cents'] = $feeAmountCents;
            }
        }

        if ($payload !== []) {
            $linked->update($payload);
        }

        if ((int) ($row->user_lesson_package_id ?? 0) !== (int) $linked->id) {
            $row->user_lesson_package_id = (int) $linked->id;
            $row->save();
        }
    }

    private function resolveLinkedUlp(UserPrice $row): ?UserLessonPackage
    {
        $fk = $row->user_lesson_package_id !== null ? (int) $row->user_lesson_package_id : 0;
        if ($fk <= 0) {
            return null;
        }

        return UserLessonPackage::query()->find($fk);
    }

    /**
     * @throws UsersPriceLessonPackageSyncException
     */
    private function detachAndDeleteIfAllowed(UserPrice $row, ?UserLessonPackage $linked, string $placedMessage): void
    {
        if ($linked === null) {
            if ($row->user_lesson_package_id !== null) {
                $row->user_lesson_package_id = null;
                $row->save();
            }

            return;
        }

        if ($linked->isLaidOutInSchedule()) {
            throw new UsersPriceLessonPackageSyncException('lesson_package_id', $placedMessage);
        }

        // Сначала снимаем FK, затем физическое удаление назначения.
        $row->user_lesson_package_id = null;
        $row->save();

        try {
            $this->deletionService->deleteOrAbort($linked);
        } catch (HttpException $e) {
            // Назначение «использовалось» (остаток ≠ total) — нельзя молча удалить.
            throw new UsersPriceLessonPackageSyncException(
                'lesson_package_id',
                $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Нельзя удалить связанное назначение абонемента.'
            );
        }
    }

    private function normalizeBillingMonth(mixed $newMonth): string
    {
        return Carbon::parse((string) $newMonth)->startOfMonth()->format('Y-m-d');
    }
}
