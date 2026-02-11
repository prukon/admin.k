<?php

namespace Tests\Feature\Crm;

use App\Models\Role;
use App\Models\User;
use App\Models\Partner;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\WeekdaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

abstract class CrmTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        /**
         * 1. Системные сидеры
         * (они будут выполняться перед КАЖДЫМ тестом, но в рамках транзакции это ок)
         */
        $this->seed(WeekdaysSeeder::class);          // ДНИ НЕДЕЛИ
        $this->seed(RolesSeeder::class);             // СИСТЕМНЫЕ РОЛИ
        $this->seed(PermissionGroupsSeeder::class);  // ГРУППЫ ПРАВ
        $this->seed(PermissionSeeder::class);        // ПРАВА (НАИМЕНОВАНИЯ)

        /**
         * 2. Системные пользователи (если нужны именно в тестах)
         * Если env-переменные не заданы — метод сам тихо выйдет.
         */
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

        /**
         * 3. Партнёр и текущий пользователь для теста
         */
        $this->partner = Partner::factory()->create();

        $this->user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        // Авторизация
        $this->actingAs($this->user);

        // Базовые permissions (мокаем Gate, чтобы не завязываться на реальные права)
        Gate::define('leads-view', fn () => true);
        Gate::define('dashboard-view', fn () => true);
    }

    /**
     * Хелпер для создания системного пользователя по env-переменным.
     *
     * ВАЖНО: здесь остаётся твоя логика partner_id = 1, team_id = 1,
     * чтобы не плодить зависимостей от $this->partner.
     */
    protected function createSystemUser(
        string $emailEnv,
        string $passwordEnv,
        string $roleName,
        string $name,
        string $lastname
    ): void {
        $email = env($emailEnv);
        $password = env($passwordEnv);

        if (!$email || !$password) {
            return;
        }

        /** @var \App\Models\User $user */
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'       => $name,
                'lastname'   => $lastname,
                'password'   => Hash::make($password),
                'is_enabled' => 1,
                'partner_id' => 1,
                'team_id'    => 1,
            ]
        );

        $role = Role::where('name', $roleName)->first();

        if ($role) {
            $user->role_id = $role->id;
            $user->save();
        } else {
            Log::warning("System user: роль '{$roleName}' не найдена");
        }
    }
}