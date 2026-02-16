<?php

namespace Database\Seeders;

use App\Models\Payable;
use App\Models\User;
use App\Models\UserPrice;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DevPricesSeeder extends Seeder
{
    public function run(): void
    {
        // 7.1. Оплаченные месяцы (0–6 месяцев назад)
        Payable::factory()
            ->count(1000)
            ->paidMonthlyWithAllRelations()
            ->create();

        // 7.2. Долги (7–12 месяцев назад, is_paid = 0)
        $users = User::whereNotNull('partner_id')->get();

        if ($users->isEmpty()) {
            return;
        }

        // 7.3 Например, 30 неоплаченных месяцев
        UserPrice::factory()
            ->count(30)
            ->unpaid()
            ->state(function () use ($users) {
                $user = $users->random();

                $monthsAgo = rand(7, 12);

                $month = Carbon::now()
                    ->subMonths($monthsAgo)
                    ->startOfMonth()
                    ->format('Y-m-01');

                return [
                    'user_id' => $user->id,
                    'new_month' => $month,
                    'price' => (string) rand(500, 10000),
                ];
            })
            ->create();
    }
}