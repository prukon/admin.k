<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Services\TeamTrainerSyncService;
use Database\Seeders\Concerns\GuardsDevSeedData;
use Illuminate\Database\Seeder;

class DevTrainersSeeder extends Seeder
{
    use GuardsDevSeedData;

    private const TRAINERS_COUNT = 10;

    public function run(): void
    {
        if (! $this->abortUnlessDevSeedEnabled()) {
            return;
        }

        $partnerIds = Partner::query()->pluck('id')->all();

        if ($partnerIds === []) {
            return;
        }

        /** @var TeamTrainerSyncService $teamTrainerSync */
        $teamTrainerSync = app(TeamTrainerSyncService::class);

        for ($i = 0; $i < self::TRAINERS_COUNT; $i++) {
            $partnerId = (int) $partnerIds[array_rand($partnerIds)];

            $profile = TrainerProfile::factory()->create([
                'partner_id' => $partnerId,
                'is_enabled' => true,
            ]);

            $teamIds = Team::query()
                ->where('partner_id', $partnerId)
                ->inRandomOrder()
                ->limit($this->randomTeamLinkCount($partnerId))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($teamIds !== []) {
                $teamTrainerSync->syncTeamsForTrainer($profile, $teamIds);
            }
        }
    }

    private function randomTeamLinkCount(int $partnerId): int
    {
        $available = Team::query()->where('partner_id', $partnerId)->count();

        if ($available === 0) {
            return 0;
        }

        return random_int(1, min(3, $available));
    }
}
