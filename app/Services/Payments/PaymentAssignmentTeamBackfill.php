<?php

namespace App\Services\Payments;

use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Support\UserPriceTeamMembership;

/**
 * Одноразовое заполнение team_id у доп. платежей и назначений абонементов.
 */
final class PaymentAssignmentTeamBackfill
{
    /**
     * @return array{custom_payments_updated: int, lesson_packages_updated: int}
     */
    public function run(): array
    {
        $customUpdated = 0;
        $ulpUpdated = 0;

        UserCustomPayment::query()
            ->whereNull('team_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$customUpdated) {
                foreach ($rows as $row) {
                    $teamId = $this->primaryTeamIdForUser((int) $row->user_id, (int) $row->partner_id);
                    if ($teamId === null) {
                        continue;
                    }

                    UserCustomPayment::query()
                        ->whereKey($row->id)
                        ->whereNull('team_id')
                        ->update(['team_id' => $teamId]);

                    $customUpdated++;
                }
            });

        UserLessonPackage::query()
            ->whereNull('team_id')
            ->with('user:id,partner_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$ulpUpdated) {
                foreach ($rows as $ulp) {
                    $user = $ulp->user;
                    if (! $user) {
                        continue;
                    }

                    $partnerId = (int) $user->partner_id;
                    if ($partnerId <= 0) {
                        continue;
                    }

                    $teamId = $this->teamIdForLessonPackageBackfill($ulp, $user, $partnerId);
                    if ($teamId === null) {
                        continue;
                    }

                    UserLessonPackage::query()
                        ->whereKey($ulp->id)
                        ->whereNull('team_id')
                        ->update(['team_id' => $teamId]);

                    $ulpUpdated++;
                }
            });

        return [
            'custom_payments_updated' => $customUpdated,
            'lesson_packages_updated' => $ulpUpdated,
        ];
    }

    private function teamIdForLessonPackageBackfill(UserLessonPackage $ulp, User $user, int $partnerId): ?int
    {
        $fromCalendar = UserTeamScheduleSlot::query()
            ->where('user_team_schedule_slots.user_lesson_package_id', (int) $ulp->id)
            ->where('user_team_schedule_slots.partner_id', $partnerId)
            ->join('team_schedule_slots as tss', 'tss.id', '=', 'user_team_schedule_slots.team_schedule_slot_id')
            ->whereNotNull('tss.team_id')
            ->orderBy('tss.team_id')
            ->value('tss.team_id');

        if ($fromCalendar) {
            $teamId = (int) $fromCalendar;
            if (UserPriceTeamMembership::studentBelongsToTeam($user, $teamId, $partnerId)) {
                return $teamId;
            }
        }

        return $this->primaryTeamIdForUser((int) $user->id, $partnerId);
    }

    private function primaryTeamIdForUser(int $userId, int $partnerId): ?int
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return null;
        }

        $teamId = UserPriceTeamMembership::primaryTeamIdForStudent($user, $partnerId);

        return $teamId !== null && $teamId > 0 ? $teamId : null;
    }
}
