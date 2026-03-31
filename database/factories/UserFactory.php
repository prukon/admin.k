<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Partner;
use App\Models\Team;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Общий пароль для всех фабричных юзеров (ускоряет тесты)
     */
    protected static ?string $password = null;

    public function definition(): array
    {
        // 1. Получаем случайного партнёра (если есть)
        $partnerId = Partner::query()->inRandomOrder()->value('id');

        // 2. Команду выбираем ТОЛЬКО этого партнёра
        $teamId = null;

        if ($partnerId) {
            $teamId = Team::query()
                ->where('partner_id', $partnerId)
                ->inRandomOrder()
                ->value('id');
        }

        // 3. Получаем роль "user"
        $roleId = Role::query()
            ->where('name', 'user')
            ->value('id');

        return [
            'partner_id' => $partnerId,
            'team_id'    => $teamId,
            'role_id'    => $roleId, // ← теперь роль всегда user (если существует)

            'name'     => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),

            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),

            'email_verified_at' => now(),
            'phone_verified_at' => null,

            'password' => static::$password ??= Hash::make('password'),

            'is_enabled' => 1,

            'start_date' => $this->faker->optional()->date(),
            'birthday'   => $this->faker->optional()->date(),

            'image'      => $this->faker->optional()->imageUrl(400, 400, 'people'),
            'image_crop' => null,

            'two_factor_enabled' => 0,

            'offer_accepted'    => 1,
            'offer_accepted_at' => now(),

            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Юзер без партнёра
     */
    public function withoutPartner(): static
    {
        return $this->state(fn () => [
            'partner_id' => null,
            'team_id'    => null,
        ]);
    }

    /**
     * Юзер без команды
     */
    public function withoutTeam(): static
    {
        return $this->state(fn () => [
            'team_id' => null,
        ]);
    }

    /**
     * Отключённый юзер
     */
    public function disabled(): static
    {
        return $this->state(fn () => [
            'is_enabled' => 0,
        ]);
    }

    /**
     * Не подтверждён офер
     */
    public function withoutOffer(): static
    {
        return $this->state(fn () => [
            'offer_accepted'    => 0,
            'offer_accepted_at' => null,
        ]);
    }

    /**
     * Администратор
     */
    public function asAdmin(): static
    {
        return $this->state(function () {
            $adminRoleId = Role::query()
                ->where('name', 'admin')
                ->value('id');

            return [
                'role_id' => $adminRoleId,
            ];
        });
    }
}