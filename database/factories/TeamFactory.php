<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Названия спортивных команд
     */
    protected array $teamNames = [
        'Сокол',
        'Динамо',
        'Спартак',
        'Зенит',
        'Локомотив',
        'Торпедо',
        'Рубин',
        'Метеор',
        'Вымпел',
        'Юность',
        'Олимп',
        'Старт',
        'Искра',
        'Виктория',
        'Феникс',
        'Атлант',
        'Штурм',
        'Буревестник',
        'Смена',
        'Луч',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 👇 ВМЕСТО faker->name
            'title' => $this->faker->randomElement($this->teamNames),

            'image' => $this->faker->imageUrl(400, 400, 'sports'),

            'is_enabled' => 1,
            'order_by' => random_int(1, 100),
        ];
    }
}