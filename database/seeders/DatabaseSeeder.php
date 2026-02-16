<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */

    public function run(): void
    {
        $this->call([
            WeekdaysSeeder::class,
            RolesSeeder::class,
            PermissionGroupsSeeder::class,
            PermissionSeeder::class,
        ]);

        if (env('SEED_DEV_DATA', false)) {
            $this->call([
                DevPartnersSeeder::class,
                UserRoleBasePermissionsSeeder::class,
                AdminRoleBasePermissionsSeeder::class,
                DevTeamsSeeder::class,
                DevAdminsSeeder::class,
                DevUsersSeeder::class,
                DevPricesSeeder::class,
                DevPaymentSystemsSeeder::class,
                IstokMenuSeeder::class,
            ]);
        }
    }
}