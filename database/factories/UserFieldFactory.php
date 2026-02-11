<?php

namespace Database\Factories;

use App\Models\UserField;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFieldFactory extends Factory
{
    protected $model = UserField::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);

        return [
            'partner_id' => Partner::factory(),
            'name'       => $name,
            'slug'       => Str::slug($name) . '-' . Str::random(5),
            'field_type' => 'string', // или text / number — для тестов не принципиально
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Явно указать партнёра
     */
    public function forPartner(int $partnerId): self
    {
        return $this->state(fn () => [
            'partner_id' => $partnerId,
        ]);
    }

    /**
     * Указать конкретный slug
     */
    public function withSlug(string $slug): self
    {
        return $this->state(fn () => [
            'slug' => $slug,
        ]);
    }
}