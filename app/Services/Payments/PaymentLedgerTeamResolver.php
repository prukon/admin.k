<?php

namespace App\Services\Payments;

use App\Models\Payable;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use App\Support\UserPriceTeamMembership;

/**
 * Снимок группы для журнала payments (monthly_fee — конкретная оплаченная группа).
 */
final class PaymentLedgerTeamResolver
{
    public function __construct(
        private readonly TeamUserSyncService $teamUserSync,
    ) {
    }

    /**
     * @return array{team_id: int|null, team_title: string}
     */
    public function resolveFromPayable(Payable $payable, User $user): array
    {
        if ((string) $payable->type !== 'monthly_fee') {
            return [
                'team_id' => null,
                'team_title' => $this->fallbackTeamTitle($user),
            ];
        }

        $teamId = $this->resolveMonthlyTeamId($payable, $user);
        $teamTitle = $this->resolveTeamTitle($teamId, $user);

        return [
            'team_id' => $teamId,
            'team_title' => $teamTitle,
        ];
    }

    private function resolveMonthlyTeamId(Payable $payable, User $user): ?int
    {
        $metaTeamId = $payable->meta['team_id'] ?? null;
        if (is_numeric($metaTeamId) && (int) $metaTeamId > 0) {
            return (int) $metaTeamId;
        }

        $partnerId = (int) $payable->partner_id;
        if ($partnerId <= 0) {
            return null;
        }

        $primaryTeamId = UserPriceTeamMembership::primaryTeamIdForStudent($user, $partnerId);

        return $primaryTeamId !== null && $primaryTeamId > 0 ? $primaryTeamId : null;
    }

    private function resolveTeamTitle(?int $teamId, User $user): string
    {
        if ($teamId !== null && $teamId > 0) {
            $title = Team::query()
                ->whereKey($teamId)
                ->whereNull('deleted_at')
                ->value('title');

            if (is_string($title) && trim($title) !== '') {
                return trim($title);
            }
        }

        return $this->fallbackTeamTitle($user);
    }

    private function fallbackTeamTitle(User $user): string
    {
        $label = $this->teamUserSync->teamTitlesLabel($user);

        return $label !== '' ? $label : 'Без команды';
    }
}
