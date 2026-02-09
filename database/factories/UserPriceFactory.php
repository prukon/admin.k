<?php

namespace Database\Factories;

use App\Models\UserPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class UserPriceFactory extends Factory
{
    protected $model = UserPrice::class;

    public function definition(): array
    {
        $month = Carbon::now()
            ->subMonths(rand(0, 6))
            ->startOfMonth()
            ->format('Y-m-01');

        return [
            'sort'       => null,
            'user_id'    => null, // всегда задаём явно
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
        bool $isPaid = true
    ): static {
        return $this->state(function (array $attributes) use ($userId, $monthYmd, $amount, $isPaid) {
            return [
                'user_id'   => $userId,
                'new_month' => $monthYmd,
                'price'     => (string) (int) $amount, // целое значение
                'is_paid'   => $isPaid ? 1 : 0,
            ];
        });
    }
}