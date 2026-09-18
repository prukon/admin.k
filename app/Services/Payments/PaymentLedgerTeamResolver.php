<?php

namespace App\Services\Payments;

use App\Models\Payable;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;

/**
 * Снимок группы и объекта для журнала payments (monthly_fee — конкретная оплаченная группа).
 * location_id берётся с teams.location_id той же группы, что попала в team_id.
 */
final class PaymentLedgerTeamResolver
{
    public function __construct(
        private readonly TeamUserSyncService $teamUserSync,
    ) {
    }

    /**
     * @return array{team_id: int|null, team_title: string, location_id: int|null}
     */
    public function resolveFromPayable(Payable $payable, User $user): array
    {
        $teamId = app(PayableTeamResolver::class)->resolveFromPayable($payable, $user);
        $team = $this->findTeam($teamId);

        return [
            'team_id' => $teamId,
            'team_title' => $this->resolveTeamTitle($team, $user),
            'location_id' => $this->resolveLocationId($team),
        ];
    }

    private function findTeam(?int $teamId): ?Team
    {
        if ($teamId === null || $teamId <= 0) {
            return null;
        }

        return Team::query()
            ->whereKey($teamId)
            ->whereNull('deleted_at')
            ->first(['id', 'title', 'location_id']);
    }

    private function resolveTeamTitle(?Team $team, User $user): string
    {
        if ($team !== null) {
            $title = is_string($team->title) ? trim($team->title) : '';
            if ($title !== '') {
                return $title;
            }
        }

        return $this->fallbackTeamTitle($user);
    }

    private function resolveLocationId(?Team $team): ?int
    {
        if ($team === null) {
            return null;
        }

        $raw = $team->location_id;
        if ($raw === null || $raw === '') {
            return null;
        }

        $locationId = (int) $raw;

        return $locationId > 0 ? $locationId : null;
    }

    private function fallbackTeamTitle(User $user): string
    {
        $label = $this->teamUserSync->teamTitlesLabel($user);

        return $label !== '' ? $label : 'Без команды';
    }
}
