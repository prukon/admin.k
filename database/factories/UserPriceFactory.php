<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use App\Support\UserPriceTeamMembership;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class UserPriceFactory extends Factory
{
    protected $model = UserPrice::class;

    public function configure(): static
    {
        return $this->afterMaking(function (UserPrice $price) {
            if ($price->team_id || ! $price->user_id) {
                return;
            }

            $user = User::query()->find($price->user_id);
            if (! $user) {
                return;
            }

            $teamId = UserPriceTeamMembership::primaryTeamIdForStudent(
                $user,
                (int) ($user->partner_id ?? 0)
            );

            if (! $teamId && (int) ($user->partner_id ?? 0) > 0) {
                $team = Team::factory()->create([
                    'partner_id' => (int) $user->partner_id,
                ]);
                app(TeamUserSyncService::class)->attachTeamForStudent($user, (int) $team->id);
                $teamId = (int) $team->id;
            }

            if ($teamId) {
                $price->team_id = $teamId;
            }
        });
    }

    public function definition(): array
    {
        $month = Carbon::now()
            ->subMonths(rand(0, 6))
            ->startOfMonth()
            ->format('Y-m-01');

        return [
            'sort'       => null,
            'user_id'    => null,
            'team_id'    => null,
            'price'      => '0',
            'is_paid'    => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'new_month'  => $month,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'is_paid' => 1,
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn () => [
            'is_paid' => 0,
        ]);
    }

    /**
     * Привязка к юзеру + месяцу + сумме.
     *
     * $monthYmd — строка 'YYYY-MM-01'
     */
    public function forUserAndMonth(
        int $userId,
        string $monthYmd,
        int|float $amount,
        bool $isPaid = true,
        ?int $teamId = null
    ): static {
        return $this->state(function (array $attributes) use ($userId, $monthYmd, $amount, $isPaid, $teamId) {
            $state = [
                'user_id'   => $userId,
                'new_month' => $monthYmd,
                'price'     => (string) (int) $amount,
                'is_paid'   => $isPaid ? 1 : 0,
            ];

            if ($teamId !== null && $teamId > 0) {
                $state['team_id'] = $teamId;
            }

            return $state;
        });
    }
}