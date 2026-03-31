<?php

namespace Database\Factories;

use App\Models\UserFieldValue;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserFieldValue>
 */
class UserFieldValueFactory extends Factory
{
    /**
     * Модель, для которой фабрика.
     *
     * @var string
     */
    protected $model = UserFieldValue::class;

    /**
     * Определение значений по умолчанию.
     */
    public function definition(): array
    {
        return [
            // В тестах эти поля часто переопределяются вручную, но дефолтные нужны
            'user_id'  => User::factory(),
            'field_id' => UserField::factory(),
            'value'    => $this->faker->word(),
        ];
    }
}