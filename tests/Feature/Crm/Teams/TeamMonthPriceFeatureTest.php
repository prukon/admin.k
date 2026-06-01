<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\Team;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Поле teams.month_price — хранение, валидация, API таблицы/модалок, логи.
 */
final class TeamMonthPriceFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_teams_index_shows_month_price_field_in_ui(): void
    {
        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('Стоимость в месяц', false)
            ->assertSee('id="month_price"', false)
            ->assertSee('id="edit-month_price"', false)
            ->assertSee('data-column-key="month_price"', false);
    }

    public function test_store_saves_month_price_as_integer_rubles(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Группа с ценой',
            'default_duration_minutes' => 60,
            'month_price'              => 3500,
            'order_by'                 => 10,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id'  => $this->partner->id,
            'title'       => 'Группа с ценой',
            'month_price' => 3500,
        ]);
    }

    public function test_store_with_empty_month_price_saves_null(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Группа без цены',
            'default_duration_minutes' => 60,
            'month_price'              => '',
            'order_by'                 => 10,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $team = Team::query()->where('title', 'Группа без цены')->firstOrFail();
        $this->assertNull($team->month_price);
    }

    public function test_store_without_month_price_saves_null(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Группа без поля цены',
            'default_duration_minutes' => 60,
            'order_by'                 => 10,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $team = Team::query()->where('title', 'Группа без поля цены')->firstOrFail();
        $this->assertNull($team->month_price);
    }

    public function test_store_with_zero_month_price_saves_zero(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Бесплатная группа',
            'default_duration_minutes' => 60,
            'month_price'              => 0,
            'order_by'                 => 10,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id'  => $this->partner->id,
            'title'       => 'Бесплатная группа',
            'month_price' => 0,
        ]);
    }

    public function test_store_rejects_negative_month_price(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Отрицательная цена',
            'default_duration_minutes' => 60,
            'month_price'              => -100,
            'order_by'                 => 10,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month_price']);

        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->partner->id,
            'title'      => 'Отрицательная цена',
        ]);
    }

    public function test_store_rejects_decimal_month_price(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Дробная цена',
            'default_duration_minutes' => 60,
            'month_price'              => '3500.50',
            'order_by'                 => 10,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month_price']);

        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->partner->id,
            'title'      => 'Дробная цена',
        ]);
    }

    public function test_store_logs_month_price_in_creation_log(): void
    {
        $this->postJson(route('admin.team.store'), [
            'title'                    => 'Группа для лога цены',
            'default_duration_minutes' => 60,
            'month_price'              => 4200,
            'order_by'                 => 10,
            'is_enabled'               => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $team = Team::query()->where('title', 'Группа для лога цены')->firstOrFail();

        $log = MyLog::query()
            ->where('target_type', Team::class)
            ->where('target_id', $team->id)
            ->where('event', AuditEvent::TeamCreated->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Стоимость в месяц:', $log->description);
        $this->assertStringContainsString('4200', $log->description);
    }

    public function test_edit_returns_month_price_in_json(): void
    {
        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Группа edit price',
            'month_price' => 5000,
        ]);

        $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->assertJson([
                'id'          => $team->id,
                'month_price' => 5000,
            ]);
    }

    public function test_edit_returns_null_month_price_when_not_set(): void
    {
        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Группа без цены edit',
            'month_price' => null,
        ]);

        $json = $this->getJson(route('admin.team.edit', ['id' => $team->id]))
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('month_price', $json);
        $this->assertNull($json['month_price']);
    }

    public function test_update_changes_month_price_and_logs(): void
    {
        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Обновление цены',
            'month_price' => 3000,
            'order_by'    => 5,
            'is_enabled'  => 1,
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => 'Обновление цены',
            'default_duration_minutes' => 60,
            'month_price'              => 4500,
            'order_by'                 => 5,
            'is_enabled'               => 1,
        ])->assertOk();

        $team->refresh();
        $this->assertSame(4500, $team->month_price);

        $log = MyLog::query()
            ->where('target_type', Team::class)
            ->where('target_id', $team->id)
            ->where('event', AuditEvent::TeamUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Стоимость в месяц:', $log->description);
        $this->assertStringContainsString('3000', $log->description);
        $this->assertStringContainsString('4500', $log->description);
    }

    public function test_update_clears_month_price_and_logs(): void
    {
        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Сброс цены',
            'month_price' => 2500,
            'order_by'    => 5,
            'is_enabled'  => 1,
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => 'Сброс цены',
            'default_duration_minutes' => 60,
            'month_price'              => '',
            'order_by'                 => 5,
            'is_enabled'               => 1,
        ])->assertOk();

        $team->refresh();
        $this->assertNull($team->month_price);

        $log = MyLog::query()
            ->where('target_type', Team::class)
            ->where('target_id', $team->id)
            ->where('event', AuditEvent::TeamUpdated->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Стоимость в месяц:', $log->description);
        $this->assertStringContainsString('2500', $log->description);
        $this->assertStringContainsString('не указана', $log->description);
    }

    public function test_update_rejects_negative_month_price(): void
    {
        $team = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Валидация update price',
            'month_price' => 1000,
            'order_by'    => 5,
            'is_enabled'  => 1,
        ]);

        $this->patchJson(route('admin.team.update', ['id' => $team->id]), [
            'title'                    => 'Валидация update price',
            'default_duration_minutes' => 60,
            'month_price'              => -1,
            'order_by'                 => 5,
            'is_enabled'               => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month_price']);

        $team->refresh();
        $this->assertSame(1000, $team->month_price);
    }

    public function test_data_includes_month_price_in_row(): void
    {
        $withPrice = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Data month price set',
            'month_price' => 6000,
        ]);

        $withoutPrice = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Data month price null',
            'month_price' => null,
        ]);

        $json = $this->getJson('/admin/teams/data?draw=1&start=0&length=100&title=Data month price')
            ->assertOk()
            ->json();

        $rowWithPrice = collect($json['data'])->firstWhere('id', $withPrice->id);
        $rowWithoutPrice = collect($json['data'])->firstWhere('id', $withoutPrice->id);

        $this->assertNotNull($rowWithPrice);
        $this->assertArrayHasKey('month_price', $rowWithPrice);
        $this->assertSame(6000, $rowWithPrice['month_price']);

        $this->assertNotNull($rowWithoutPrice);
        $this->assertArrayHasKey('month_price', $rowWithoutPrice);
        $this->assertNull($rowWithoutPrice['month_price']);
    }

    public function test_data_sorts_by_month_price_ascending(): void
    {
        $expensive = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Sort price expensive',
            'month_price' => 9000,
            'order_by'    => 1,
        ]);

        $cheap = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Sort price cheap',
            'month_price' => 1000,
            'order_by'    => 2,
        ]);

        $query = http_build_query([
            'draw'    => 1,
            'start'   => 0,
            'length'  => 100,
            'title'   => 'Sort price',
            'order'   => [['column' => 0, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'month_price'],
            ],
        ]);

        $json = $this->getJson('/admin/teams/data?' . $query)
            ->assertOk()
            ->json();

        $ids = collect($json['data'])->pluck('id')->values()->all();
        $cheapIndex = array_search($cheap->id, $ids, true);
        $expensiveIndex = array_search($expensive->id, $ids, true);

        $this->assertNotFalse($cheapIndex);
        $this->assertNotFalse($expensiveIndex);
        $this->assertLessThan($expensiveIndex, $cheapIndex);
    }

    public function test_data_sorts_by_month_price_descending(): void
    {
        $expensive = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Sort desc expensive',
            'month_price' => 8000,
            'order_by'    => 1,
        ]);

        $cheap = Team::factory()->create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Sort desc cheap',
            'month_price' => 2000,
            'order_by'    => 2,
        ]);

        $query = http_build_query([
            'draw'    => 1,
            'start'   => 0,
            'length'  => 100,
            'title'   => 'Sort desc',
            'order'   => [['column' => 0, 'dir' => 'desc']],
            'columns' => [
                ['name' => 'month_price'],
            ],
        ]);

        $json = $this->getJson('/admin/teams/data?' . $query)
            ->assertOk()
            ->json();

        $ids = collect($json['data'])->pluck('id')->values()->all();
        $cheapIndex = array_search($cheap->id, $ids, true);
        $expensiveIndex = array_search($expensive->id, $ids, true);

        $this->assertNotFalse($cheapIndex);
        $this->assertNotFalse($expensiveIndex);
        $this->assertLessThan($cheapIndex, $expensiveIndex);
    }

    public function test_columns_settings_accepts_month_price_column_key(): void
    {
        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'title'       => true,
                'month_price' => false,
                'status_label' => true,
                'actions'     => true,
            ],
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson('/admin/teams/columns-settings')
            ->assertOk()
            ->assertJsonFragment(['month_price' => false]);
    }
}
