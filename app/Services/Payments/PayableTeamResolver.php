<?php

namespace App\Services\Payments;

use App\Models\Payable;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Support\PartnerLegalEntityMode;
use App\Support\UserPriceTeamMembership;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Группа платежа для payable / выплат / журнала payments.
 * Без группы оплата не инициируется (выплата не уходит на уровень партнёра).
 */
final class PayableTeamResolver
{
    /**
     * @return Collection<int, Team>
     */
    public function studentTeams(User $user, int $partnerId): Collection
    {
        if ($user->relationLoaded('teams')) {
            return $user->teams
                ->filter(fn (Team $team) => (int) $team->partner_id === $partnerId && $team->deleted_at === null)
                ->values();
        }

        return Team::query()
            ->where('teams.partner_id', $partnerId)
            ->whereNull('teams.deleted_at')
            ->whereHas('students', fn ($q) => $q->where('users.id', $user->id))
            ->orderBy('teams.order_by')
            ->orderBy('teams.title')
            ->get();
    }

    public function resolveOrAbort(
        string $payableType,
        int $partnerId,
        User $user,
        ?int $requestTeamId = null,
        ?UserCustomPayment $customPayment = null,
        ?UserLessonPackage $lessonPackage = null,
    ): int {
        return match ((string) $payableType) {
            'club_fee', 'uniform', 'camp' => $this->resolveClubLike($user, $partnerId, $requestTeamId),
            'custom_payment_fee' => $this->resolveCustomPaymentFee($user, $partnerId, $customPayment),
            'lesson_package_fee' => $this->resolveLessonPackageFee($user, $partnerId, $lessonPackage),
            default => $this->resolveExplicitOrPrimary($user, $partnerId, $requestTeamId),
        };
    }

    public function resolveFromPayable(Payable $payable, User $user): ?int
    {
        $metaTeamId = $payable->meta['team_id'] ?? null;
        if (is_numeric($metaTeamId) && (int) $metaTeamId > 0) {
            return (int) $metaTeamId;
        }

        $partnerId = (int) $payable->partner_id;
        $type = (string) $payable->type;

        if ($type === 'custom_payment_fee') {
            $customPayment = $this->loadCustomPaymentFromPayable($payable, $partnerId);
            if ($customPayment !== null && $customPayment->team_id) {
                return (int) $customPayment->team_id;
            }
        }

        if ($type === 'lesson_package_fee') {
            $ulp = $this->loadLessonPackageFromPayable($payable, $partnerId, (int) $user->id);
            if ($ulp !== null) {
                return $this->resolveLessonPackageTeamId($ulp, $partnerId, $user, false);
            }
        }

        if ($type === 'monthly_fee') {
            return UserPriceTeamMembership::primaryTeamIdForStudent($user, $partnerId);
        }

        return null;
    }

    private function resolveClubLike(User $user, int $partnerId, ?int $requestTeamId): int
    {
        $teams = $this->studentTeams($user, $partnerId);

        if ($teams->isEmpty()) {
            throw new AccessDeniedHttpException('Оплата недоступна: вы не состоите ни в одной группе. Обратитесь в школу.');
        }

        if ($teams->count() === 1) {
            return (int) $teams->first()->id;
        }

        if ($requestTeamId === null || $requestTeamId <= 0) {
            throw new UnprocessableEntityHttpException('Укажите группу для оплаты.');
        }

        $this->assertStudentInTeam($user, $partnerId, $requestTeamId);

        return $requestTeamId;
    }

    private function resolveCustomPaymentFee(User $user, int $partnerId, ?UserCustomPayment $customPayment): int
    {
        if ($customPayment === null) {
            throw new AccessDeniedHttpException('Дополнительный платеж не найден.');
        }

        if ((int) $customPayment->user_id !== (int) $user->id || (int) $customPayment->partner_id !== $partnerId) {
            throw new AccessDeniedHttpException('Дополнительный платеж не найден.');
        }

        $teamId = $customPayment->team_id ? (int) $customPayment->team_id : 0;
        if ($teamId <= 0) {
            throw new UnprocessableEntityHttpException('Для дополнительного платежа не указана группа. Обратитесь в школу.');
        }

        $this->assertStudentInTeam($user, $partnerId, $teamId);

        return $teamId;
    }

    private function resolveLessonPackageFee(User $user, int $partnerId, ?UserLessonPackage $lessonPackage): int
    {
        if ($lessonPackage === null) {
            throw new AccessDeniedHttpException('Назначение абонемента не найдено.');
        }

        if ((int) $lessonPackage->user_id !== (int) $user->id) {
            throw new AccessDeniedHttpException('Назначение абонемента не найдено.');
        }

        $teamId = $this->resolveLessonPackageTeamId($lessonPackage, $partnerId, $user, true);
        if ($teamId === null || $teamId <= 0) {
            throw new UnprocessableEntityHttpException('Для оплаты абонемента не удалось определить группу. Обратитесь в школу.');
        }

        return $teamId;
    }

    private function resolveLessonPackageTeamId(
        UserLessonPackage $lessonPackage,
        int $partnerId,
        User $user,
        bool $strict,
    ): ?int {
        if (PartnerLegalEntityMode::isMultiEntity($partnerId)) {
            $teamId = $lessonPackage->team_id ? (int) $lessonPackage->team_id : 0;
            if ($teamId <= 0) {
                if ($strict) {
                    throw new UnprocessableEntityHttpException('Для абонемента не указана группа. Обратитесь в школу.');
                }

                return null;
            }

            $this->assertStudentInTeam($user, $partnerId, $teamId);

            return $teamId;
        }

        if ($lessonPackage->team_id) {
            $teamId = (int) $lessonPackage->team_id;
            if (UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
                return $teamId;
            }
        }

        $fromCalendar = $this->teamIdFromLessonPackageCalendar((int) $lessonPackage->id, $partnerId);
        if ($fromCalendar !== null) {
            return $fromCalendar;
        }

        $primary = UserPriceTeamMembership::primaryTeamIdForStudent($user, $partnerId);

        return $primary !== null && $primary > 0 ? $primary : null;
    }

    private function teamIdFromLessonPackageCalendar(int $ulpId, int $partnerId): ?int
    {
        $teamIds = UserTeamScheduleSlot::query()
            ->where('user_team_schedule_slots.user_lesson_package_id', $ulpId)
            ->where('user_team_schedule_slots.partner_id', $partnerId)
            ->join('team_schedule_slots as tss', 'tss.id', '=', 'user_team_schedule_slots.team_schedule_slot_id')
            ->whereNotNull('tss.team_id')
            ->distinct()
            ->orderBy('tss.team_id')
            ->pluck('tss.team_id');

        if ($teamIds->isEmpty()) {
            return null;
        }

        return (int) $teamIds->first();
    }

    private function resolveExplicitOrPrimary(User $user, int $partnerId, ?int $requestTeamId): int
    {
        if ($requestTeamId !== null && $requestTeamId > 0) {
            $this->assertStudentInTeam($user, $partnerId, $requestTeamId);

            return $requestTeamId;
        }

        $primary = UserPriceTeamMembership::primaryTeamIdForStudent($user, $partnerId);
        if ($primary === null || $primary <= 0) {
            throw new AccessDeniedHttpException('Оплата недоступна: вы не состоите ни в одной группе. Обратитесь в школу.');
        }

        return $primary;
    }

    private function assertStudentInTeam(User $user, int $partnerId, int $teamId): void
    {
        if (! UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
            throw new AccessDeniedHttpException('Указанная группа недоступна для оплаты.');
        }
    }

    private function loadCustomPaymentFromPayable(Payable $payable, int $partnerId): ?UserCustomPayment
    {
        $pid = $payable->meta['user_period_price_id'] ?? null;
        $pidInt = is_numeric($pid) ? (int) $pid : 0;
        if ($pidInt <= 0) {
            return null;
        }

        return UserCustomPayment::query()
            ->whereKey($pidInt)
            ->where('partner_id', $partnerId)
            ->first();
    }

    private function loadLessonPackageFromPayable(Payable $payable, int $partnerId, int $userId): ?UserLessonPackage
    {
        $ulpId = $payable->meta['user_lesson_package_id'] ?? null;
        $ulpInt = is_numeric($ulpId) ? (int) $ulpId : 0;
        if ($ulpInt <= 0) {
            return null;
        }

        return UserLessonPackage::query()
            ->with('user:id,partner_id')
            ->whereKey($ulpInt)
            ->whereHas('user', fn ($q) => $q->where('partner_id', $partnerId)->where('id', $userId))
            ->first();
    }
}
