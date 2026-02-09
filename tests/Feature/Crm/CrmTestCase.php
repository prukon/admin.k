<?php

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

abstract class CrmTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        // Партнёр
        $this->partner = Partner::factory()->create();

        // Пользователь, привязанный к партнёру
        $this->user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        // Авторизация
        $this->actingAs($this->user);

        // Открываем базовые permissions
        Gate::define('leads-view', fn () => true);

        // >>> ИЗМЕНЕНИЕ: добавили разрешение для дашборда <<<
        Gate::define('dashboard-view', fn () => true);

        // Если у тебя здесь ещё есть логика типа app()->instance('current_partner', ...),
        // она остаётся без изменений.
    }
}