<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Chat\TeamGroupChatService;
use Illuminate\Support\Facades\DB;

class TeamTrainerSyncService
{
    public function __construct(
        private readonly TeamGroupChatService $teamGroupChat,
    ) {
    }

    /**
     * Назначить одного тренера группе (BC: делегирует в syncTrainersForTeam).
     */
    public function syncTrainerForTeam(Team $team, ?int $trainerProfileId): void
    {
        $this->syncTrainersForTeam(
            $team,
            $trainerProfileId ? [(int) $trainerProfileId] : [],
        );
    }

    /**
     * Полная синхронизация тренеров группы (many-to-many).
     * Пустой массив — снять всех тренеров с группы.
     *
     * @param  int[]  $trainerProfileIds
     */
    public function syncTrainersForTeam(Team $team, array $trainerProfileIds): void
    {
        $partnerId = (int) $team->partner_id;

        $trainerProfileIds = array_values(array_unique(array_filter(
            array_map('intval', $trainerProfileIds),
            fn (int $id) => $id > 0,
        )));

        $validIds = $trainerProfileIds === []
            ? []
            : TrainerProfile::query()
                ->where('partner_id', $partnerId)
                ->whereIn('id', $trainerProfileIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

        // Сохраняем порядок, переданный из UI (валидные id).
        $orderedValidIds = [];
        foreach ($trainerProfileIds as $id) {
            if (in_array($id, $validIds, true) && ! in_array($id, $orderedValidIds, true)) {
                $orderedValidIds[] = $id;
            }
        }

        DB::table('team_trainer')
            ->where('team_id', $team->id)
            ->where('partner_id', $partnerId)
            ->when($orderedValidIds !== [], fn ($q) => $q->whereNotIn('trainer_profile_id', $orderedValidIds))
            ->delete();

        foreach ($orderedValidIds as $profileId) {
            DB::table('team_trainer')->updateOrInsert(
                [
                    'team_id' => $team->id,
                    'trainer_profile_id' => $profileId,
                ],
                [
                    'partner_id' => $partnerId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $profile = TrainerProfile::query()->whereKey($profileId)->first();
            if ($profile) {
                $this->addTrainerToTeamChat($team, $profile);
            }
        }
    }

    /**
     * Привязать тренера к нескольким группам (группа может иметь нескольких тренеров).
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

            $linked = Team::query()->whereKey($teamId)->first();
            if ($linked) {
                $this->addTrainerToTeamChat($linked, $profile);
            }
        }
    }

    private function addTrainerToTeamChat(Team $team, TrainerProfile $profile): void
    {
        $user = $profile->relationLoaded('user')
            ? $profile->user
            : User::query()->whereKey((int) $profile->user_id)->first();

        if ($user) {
            $this->teamGroupChat->addUserToTeamChat($team, $user);
        }
    }
}
