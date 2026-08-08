<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Models\UserLessonPackage;
use Illuminate\Validation\ValidationException;

/**
 * Жёсткий запрет новых назначений/привязок ученику с активной автопролонгацией фикс-абона.
 */
final class UserLessonPackageAutoProlongGuard
{
    public const BLOCK_REASON = 'У ученика включена автопролонгация абонемента — новые назначения недоступны. Сначала отключите автопролонгацию.';

    public function findBlockingAssignment(int $userId): ?UserLessonPackage
    {
        if ($userId < 1) {
            return null;
        }

        return UserLessonPackage::query()
            ->where('user_id', $userId)
            ->where('auto_prolong_enabled', true)
            ->orderByDesc('id')
            ->first();
    }

    public function isBlocked(int $userId): bool
    {
        return $this->findBlockingAssignment($userId) !== null;
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, true> map user_id => true
     */
    public function blockedUserIds(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $blocked = UserLessonPackage::query()
            ->whereIn('user_id', $ids)
            ->where('auto_prolong_enabled', true)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $map = [];
        foreach ($blocked as $userId) {
            $map[$userId] = true;
        }

        return $map;
    }

    public function blockReasonForUser(int $userId): ?string
    {
        return $this->isBlocked($userId) ? self::BLOCK_REASON : null;
    }

    /**
     * Новое назначение / пробное / разовое create_new — нельзя, пока есть любой ULP с автопролонгом.
     *
     * @throws ValidationException
     */
    public function assertCanCreateAssignmentForUser(int $userId, string $errorKey = 'user_id'): void
    {
        $reason = $this->blockReasonForUser($userId);
        if ($reason === null) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => [$reason],
        ]);
    }

    /**
     * Привязка существующего ULP к календарю/журналу: разрешена только для самого автопролонг-назначения.
     *
     * @throws ValidationException
     */
    public function assertCanBindAssignment(UserLessonPackage $ulp, string $errorKey = 'user_id'): void
    {
        $blocking = $this->findBlockingAssignment((int) $ulp->user_id);
        if ($blocking === null) {
            return;
        }

        if ((int) $blocking->id === (int) $ulp->id) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => [self::BLOCK_REASON],
        ]);
    }

    /**
     * Пробное и прочие операции без ULP.
     *
     * @throws ValidationException
     */
    public function assertCanRegisterTrialOrCalendarEntryForUser(int $userId, string $errorKey = 'user_id'): void
    {
        $this->assertCanCreateAssignmentForUser($userId, $errorKey);
    }

    /**
     * Включение флага: только fixed без billing_month; не более одного на ученика.
     *
     * @throws ValidationException
     */
    public function assertCanEnableAutoProlong(UserLessonPackage $ulp, string $errorKey = 'auto_prolong_enabled'): void
    {
        $ulp->loadMissing('lessonPackage:id,schedule_type');

        if ($ulp->isFromSettingPrices()) {
            throw ValidationException::withMessages([
                $errorKey => ['Автопролонгация недоступна для абонементов из установки цен.'],
            ]);
        }

        $scheduleType = (string) ($ulp->lessonPackage?->schedule_type ?? '');
        if ($scheduleType !== 'fixed') {
            throw ValidationException::withMessages([
                $errorKey => ['Автопролонгация доступна только для фиксированных абонементов.'],
            ]);
        }

        $other = UserLessonPackage::query()
            ->where('user_id', (int) $ulp->user_id)
            ->where('auto_prolong_enabled', true)
            ->where('id', '!=', (int) $ulp->id)
            ->exists();

        if ($other) {
            throw ValidationException::withMessages([
                $errorKey => ['У ученика уже есть абонемент с автопролонгацией. Сначала отключите его.'],
            ]);
        }
    }
}
