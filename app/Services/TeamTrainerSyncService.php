<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TrainerProfile;
use Illuminate\Support\Facades\DB;

class TeamTrainerSyncService
{
    /**
     * Назначить одного тренера группе (в UI пока один; таблица many-to-many).
     */
    public function syncTrainerForTeam(Team $team, ?int $trainerProfileId): void
    {
        $partnerId = (int) $team->partner_id;

        DB::table('team_trainer')
            ->where('team_id', $team->id)
            ->where('partner_id', $partnerId)
            ->delete();

        if (!$trainerProfileId) {
            return;
        }

        $profile = TrainerProfile::query()
            ->where('partner_id', $partnerId)
            ->whereKey($trainerProfileId)
            ->first();

        if (!$profile) {
            return;
        }

        $this->detachOtherTrainersFromTeam($team->id, $partnerId, $profile->id);

        DB::table('team_trainer')->updateOrInsert(
            [
                'team_id' => $team->id,
                'trainer_profile_id' => $profile->id,
            ],
            [
                'partner_id' => $partnerId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * Привязать тренера к нескольким группам (на группу — не более одного тренера).
     *
     * @param  int[]  $teamIds
     */
    public function syncTeamsForTrainer(TrainerProfile $profile, array $teamIds): void
    {
        $partnerId = (int) $profile->partner_id;

        $teamIds = array_values(array_unique(array_map('intval', $teamIds)));
        $teamIds = array_filter($teamIds, fn (int $id) => $id > 0);

        $validTeamIds = $teamIds === []
            ? []
            : Team::query()
                ->where('partner_id', $partnerId)
                ->whereIn('id', $teamIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        DB::table('team_trainer')
            ->where('trainer_profile_id', $profile->id)
            ->where('partner_id', $partnerId)
            ->when($validTeamIds !== [], fn ($q) => $q->whereNotIn('team_id', $validTeamIds))
            ->delete();

        foreach ($validTeamIds as $teamId) {
            $this->detachOtherTrainersFromTeam($teamId, $partnerId, $profile->id);

            DB::table('team_trainer')->updateOrInsert(
                [
                    'team_id' => $teamId,
                    'trainer_profile_id' => $profile->id,
                ],
                [
                    'partner_id' => $partnerId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function detachOtherTrainersFromTeam(int $teamId, int $partnerId, int $exceptTrainerProfileId): void
    {
        DB::table('team_trainer')
            ->where('team_id', $teamId)
            ->where('partner_id', $partnerId)
            ->where('trainer_profile_id', '!=', $exceptTrainerProfileId)
            ->delete();
    }
}
