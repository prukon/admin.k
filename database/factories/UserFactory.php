<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Partner;
use App\Models\Team;
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
        // аккуратно получаем partner_id
        $partnerIds = Partner::pluck('id');
        $partnerId = $partnerIds->isNotEmpty()
            ? $partnerIds->random()
            : null;

        // аккуратно получаем team_id
        $teamIds = Team::pluck('id');
        $teamId = $teamIds->isNotEmpty()
            ? $teamIds->random()
            : null;

        return [
            'partner_id' => $partnerId,
            'team_id'    => $teamId,

            'name'     => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),

            'email'      => $this->faker->unique()->safeEmail(),
            'phone'      => $this->faker->optional()->phoneNumber(),

            'email_verified_at' => now(),
            'phone_verified_at' => null,

            'password' => static::$password ??= Hash::make('password'),

            'is_enabled' => 1,

            'start_date' => $this->faker->optional()->date(),
            'birthday'   => $this->faker->optional()->date(),

            'image'       => $this->faker->optional()->imageUrl(400, 400, 'people'),
            'image_crop'  => null,

            'two_factor_enabled' => 0,

            'offer_accepted'    => 1,
            'offer_accepted_at' => now(),

            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Юзер без партнёра (на случай edge-кейсов / спец-тестов)
     */
    public function withoutPartner(): static
    {
        return $this->state(fn() => [
            'partner_id' => null,
        ]);
    }

    /**
     * Юзер без команды
     */
    public function withoutTeam(): static
    {
        return $this->state(fn() => [
            'team_id' => null,
        ]);
    }

    /**
     * Отключённый юзер
     */
    public function disabled(): static
    {
        return $this->state(fn() => [
            'is_enabled' => 0,
        ]);
    }

    /**
     * Не подтверждён офер
     */
    public function withoutOffer(): static
    {
        return $this->state(fn() => [
            'offer_accepted'    => 0,
            'offer_accepted_at' => null,
        ]);
    }


}
