<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Partner;
use App\Models\Team;
use App\Models\Weekday;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Models\UserPrice;

use App\Models\Payable;


class DevFakeDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Партнеры рандом
//        $partners = Partner::factory()->count(30)->create();
        $partners = Partner::factory()->count(3)->create();
        $partnerIds = Partner::pluck('id')->toArray();

        // 3. Команды рандом
        Team::factory()->count(20)->create()->each(function ($team) use ($partnerIds) {
            $team->partner_id = $partnerIds[array_rand($partnerIds)];
            $team->save();
        });

        // 4. Связи teams ↔ weekdays (рандомно, для демо)
        $this->attachWeekdaysToTeams();

        // 5. Создание админов
        $this->createSystemUser(
            'SYSTEM_USER_EMAIL',
            'SYSTEM_USER_PASSWORD',
            'user',
            'User',
            'System'
        );
        $this->createSystemUser(
            'SYSTEM_ADMIN_EMAIL',
            'SYSTEM_ADMIN_PASSWORD',
            'admin',
            'Admin',
            'System'
        );
        $this->createSystemUser(
            'SYSTEM_SUPERADMIN_EMAIL',
            'SYSTEM_SUPERADMIN_PASSWORD',
            'superadmin',
            'Superadmin',
            'System'
        );


        // 6. Пользователи рандом
//        User::factory()->count(20000)->create([
//            'is_enabled' => 1,
//        ])->each(function ($user) use ($partnerIds) {
//            $user->partner_id = $partnerIds[array_rand($partnerIds)];
//            $user->save();
//        });

        User::factory()->count(200)->create([
            'is_enabled' => 1,
        ])->each(function ($user) use ($partnerIds) {
            $user->partner_id = $partnerIds[array_rand($partnerIds)];
            $user->save();
        });



        // 7. Создание установленных цен
        // 7.1. Сначала создаём оплаченные месяцы (0–6 месяцев назад)
//        Payable::factory()->count(100000)->paidMonthlyWithAllRelations()->create();
        Payable::factory()->count(1000)->paidMonthlyWithAllRelations()->create();

        // 7.2. Генерим задолженности (7–12 месяцев назад, is_paid = 0)
        $users = User::whereNotNull('partner_id')->get();

        if ($users->isEmpty()) {
            return;
        }

        // 7.3 Например, 300 неоплаченных месяцев
        UserPrice::factory()
            ->count(30)
            ->unpaid()
            ->state(function () use ($users) {
                $user = $users->random();

                // Месяц долга: от 7 до 12 месяцев назад
                $monthsAgo = rand(7, 12);

                $month = Carbon::now()
                    ->subMonths($monthsAgo)
                    ->startOfMonth()
                    ->format('Y-m-01');

                return [
                    'user_id'   => $user->id,
                    'new_month' => $month,
                    // Ровные суммы, без копеек
                    'price'     => (string) rand(500, 10000),
                ];
            })
            ->create();
    }


        // клубный взнос
//        Payable::factory()
//            ->clubFee()
//            ->paid()
//            ->count(100)
//            ->create();

// форму/лагерь можешь добить по аналогии


    //Привязка случайных дней недели к командам.
    protected function attachWeekdaysToTeams(): void
    {
        $teams = Team::all();
        $weekdays = Weekday::all();

        if ($teams->isEmpty() || $weekdays->isEmpty()) {
            return;
        }

        foreach ($teams as $team) {
            $count = min(3, $weekdays->count());

            $weekdayIds = $weekdays
                ->random($count)
                ->pluck('id')
                ->toArray();

            $team->weekdays()->syncWithoutDetaching($weekdayIds);
        }
    }

    //Создание админов
    private function createSystemUser($emailEnv, $passwordEnv, $role, $name, $lastname): void
    {
        $email = env($emailEnv);
        $password = env($passwordEnv);

        if (!$email || !$password) {
            return;
        }

        /** @var \App\Models\User $user */
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'lastname' => $lastname,
                'password' => Hash::make($password),
                'is_enabled' => 1,
                'partner_id' => 1,
                'team_id' => 1,

            ]
        );

        $roleModel = Role::where('name', $role)->first();

        if ($roleModel) {
            $user->role_id = $roleModel->id;
            $user->save();
        } else {
            \Log::warning("System user: роль '{$role}' не найдена");
        }
    }

}
