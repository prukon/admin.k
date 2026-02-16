<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevAdminsSeeder extends Seeder
{
    public function run(): void
    {
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
    }

    // Создание админов
    private function createSystemUser(string $emailEnv, string $passwordEnv, string $role, string $name, string $lastname): void
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