<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Названия, зарезервированные в текущем batch factory (до save()).
     *
     * @var array<int, list<string>>
     */
    private array $reservedTitlesByPartner = [];

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
            'title' => fn (array $attributes) => $this->uniqueTeamTitle(
                $this->resolvePartnerId($attributes['partner_id'] ?? null)
            ),

            'image' => $this->faker->imageUrl(400, 400, 'sports'),

            'default_duration_minutes' => 60,

            'is_enabled' => 1,
            'order_by' => random_int(1, 100),
        ];
    }

    private function resolvePartnerId(mixed $partnerId): ?int
    {
        if (is_callable($partnerId) && ! is_string($partnerId) && ! is_array($partnerId)) {
            $partnerId = $partnerId();
        }

        if ($partnerId instanceof \Illuminate\Database\Eloquent\Model) {
            return (int) $partnerId->getKey();
        }

        if ($partnerId === null || $partnerId === '') {
            return null;
        }

        return is_numeric($partnerId) ? (int) $partnerId : null;
    }

    private function uniqueTeamTitle(?int $partnerId): string
    {
        if ($partnerId === null) {
            return $this->faker->randomElement($this->teamNames) . ' ' . strtoupper(substr(uniqid('', true), -6));
        }

        $existingTitles = Team::query()
            ->where('partner_id', $partnerId)
            ->pluck('title')
            ->all();

        $taken = array_flip([
            ...$existingTitles,
            ...($this->reservedTitlesByPartner[$partnerId] ?? []),
        ]);

        foreach ($this->teamNames as $name) {
            if (! isset($taken[$name])) {
                $this->reservedTitlesByPartner[$partnerId][] = $name;

                return $name;
            }
        }

        do {
            $title = $this->faker->randomElement($this->teamNames) . ' ' . $this->faker->unique()->numerify('###');
        } while (isset($taken[$title]));

        $this->reservedTitlesByPartner[$partnerId][] = $title;

        return $title;
    }
}
