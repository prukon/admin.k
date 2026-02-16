<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevUsersSeeder extends Seeder
{
    public function run(): void
    {
        $partnerIds = Partner::pluck('id')->toArray();

        if (empty($partnerIds)) {
            return;
        }

        // Было: User::factory()->count(200)->create(['is_enabled' => 1])->each(...)
        User::factory()->count(200)->create([
            'is_enabled' => 1,
        ])->each(function (User $user) use ($partnerIds) {
            $user->partner_id = $partnerIds[array_rand($partnerIds)];
            $user->save();
        });
    }
}