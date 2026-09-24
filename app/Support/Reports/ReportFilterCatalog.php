<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\TrainerOwnTeamsScope;
use Illuminate\Support\Collection;

final class ReportFilterCatalog
{
    /**
     * @return Collection<int, Team>
     */
    public static function activeTeams(int $partnerId, ?User $actor): Collection
    {
        $teams = Team::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->orderBy('title');
        app(TrainerOwnTeamsScope::class)->restrictTeamsQuery($teams, $actor, $partnerId);

        return $teams->get(['id', 'title']);
    }

    /**
     * @return Collection<int, TrainerProfile>
     */
    public static function activeTrainers(int $partnerId, bool $canView): Collection
    {
        if (! $canView) {
            return collect();
        }

        return TrainerProfile::query()
            ->with('user:id,name,lastname')
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereHas('user', fn ($q) => $q->where('is_enabled', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'user_id', 'sort_order']);
    }
}
