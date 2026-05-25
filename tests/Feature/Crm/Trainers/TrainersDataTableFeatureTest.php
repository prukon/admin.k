<?php

namespace Tests\Feature\Crm\Trainers;

use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * DataTables, фильтры, колонки, формат зарплаты и UI страницы тренеров.
 */
final class TrainersDataTableFeatureTest extends CrmTestCase
{
    private ?int $trainerRoleId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');
    }

    private function grantTrainersView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('trainers.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTrainerProfile(array $userOverrides = [], array $profileOverrides = []): TrainerProfile
    {
        $user = User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => $this->trainerRoleId,
            'team_id' => null,
        ], $userOverrides));

        return TrainerProfile::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'user_id' => $user->id,
        ], $profileOverrides));
    }

    public function test_index_renders_datatables_ui_and_default_active_filter(): void
    {
        $this->grantTrainersView();

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа для фильтра',
        ]);

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertViewHas('activeTab', 'trainers')
            ->assertSee('id="trainers-table"', false)
            ->assertSee('trainers-report-filters', false)
            ->assertSee('filter-name', false)
            ->assertSee('filter-team', false)
            ->assertSee('filter-status', false)
            ->assertSee('value="active" selected', false)
            ->assertSee('pageLength: 10', false)
            ->assertSee('defaultFilterStatus = \'active\'', false)
            ->assertSee('Группа для фильтра', false);
    }

    public function test_data_returns_expected_row_structure(): void
    {
        $this->grantTrainersView();

        $profile = $this->createTrainerProfile(
            ['lastname' => 'Структура', 'name' => 'Строки', 'email' => 'row-structure@example.test'],
            [
                'is_enabled' => true,
                'sort_order' => 7,
                'default_base_salary' => 12345.67,
                'default_rate_per_training' => 890.12,
            ],
        );

        $json = $this->getJson(route('admin.trainers.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'name' => 'Структура',
        ]))->assertOk()->json();

        $row = collect($json['data'])->firstWhere('id', $profile->id);
        $this->assertNotNull($row);
        $this->assertSame('Структура Строки', $row['full_name']);
        $this->assertSame('row-structure@example.test', $row['email']);
        $this->assertSame(7, $row['sort_order']);
        $this->assertSame(1, $row['is_enabled']);
        $this->assertSame('Да', $row['status_label']);
        $this->assertStringContainsString('default-avatar.png', $row['avatar_url']);
        $this->assertSame('12 346 руб', $row['default_base_salary']);
        $this->assertSame('890 руб', $row['default_rate_per_training']);
    }

    public function test_data_search_value_fallback_when_name_param_empty(): void
    {
        $this->grantTrainersView();

        $profile = $this->createTrainerProfile([
            'lastname' => 'Поиск',
            'name' => 'DataTables',
            'email' => 'dt-search-' . uniqid('', true) . '@example.test',
        ]);

        $json = $this->getJson(route('admin.trainers.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'search' => ['value' => 'DataTables'],
        ]))->assertOk()->json();

        $this->assertSame(1, $json['recordsFiltered']);
        $this->assertSame($profile->id, $json['data'][0]['id']);
    }

    public function test_data_status_inactive_filter(): void
    {
        $this->grantTrainersView();

        $inactive = $this->createTrainerProfile([
            'email' => 'inactive-only-' . uniqid('', true) . '@example.test',
        ], ['is_enabled' => false]);

        $active = $this->createTrainerProfile([
            'email' => 'active-only-' . uniqid('', true) . '@example.test',
        ], ['is_enabled' => true]);

        $json = $this->getJson(route('admin.trainers.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'status' => 'inactive',
        ]))->assertOk()->json();

        $ids = collect($json['data'])->pluck('id')->all();
        $this->assertContains($inactive->id, $ids);
        $this->assertNotContains($active->id, $ids);
    }

    public function test_data_sorts_by_sort_order_desc(): void
    {
        $this->grantTrainersView();

        $low = $this->createTrainerProfile([
            'email' => 'sort-low-' . uniqid('', true) . '@example.test',
        ], ['sort_order' => 1]);

        $high = $this->createTrainerProfile([
            'email' => 'sort-high-' . uniqid('', true) . '@example.test',
        ], ['sort_order' => 99]);

        $query = http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'order' => [['column' => 7, 'dir' => 'desc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'avatar_url'],
                ['name' => 'full_name'],
                ['name' => 'teams_label'],
                ['name' => 'email'],
                ['name' => 'default_base_salary'],
                ['name' => 'default_rate_per_training'],
                ['name' => 'sort_order'],
                ['name' => 'is_enabled'],
                ['name' => 'actions'],
            ],
            'name' => 'sort-',
        ]);

        $ids = collect($this->get('/admin/trainers/data?' . $query)->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $lowPos = array_search($low->id, $ids, true);
        $highPos = array_search($high->id, $ids, true);
        $this->assertNotFalse($lowPos);
        $this->assertNotFalse($highPos);
        $this->assertLessThan($lowPos, $highPos);
    }

    public function test_data_sorts_by_full_name_asc(): void
    {
        $this->grantTrainersView();

        $alpha = $this->createTrainerProfile([
            'lastname' => 'Ааа',
            'name' => 'Сорт',
            'email' => 'sort-alpha-' . uniqid('', true) . '@example.test',
        ]);

        $omega = $this->createTrainerProfile([
            'lastname' => 'Яяя',
            'name' => 'Сорт',
            'email' => 'sort-omega-' . uniqid('', true) . '@example.test',
        ]);

        $query = http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'order' => [['column' => 2, 'dir' => 'asc']],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'avatar_url'],
                ['name' => 'full_name'],
                ['name' => 'teams_label'],
                ['name' => 'email'],
                ['name' => 'default_base_salary'],
                ['name' => 'default_rate_per_training'],
                ['name' => 'sort_order'],
                ['name' => 'is_enabled'],
                ['name' => 'actions'],
            ],
            'name' => 'sort-',
        ]);

        $ids = collect($this->get('/admin/trainers/data?' . $query)->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $alphaPos = array_search($alpha->id, $ids, true);
        $omegaPos = array_search($omega->id, $ids, true);
        $this->assertNotFalse($alphaPos);
        $this->assertNotFalse($omegaPos);
        $this->assertLessThan($omegaPos, $alphaPos);
    }

    public function test_store_without_is_enabled_defaults_to_active(): void
    {
        $this->grantTrainersView();

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Без',
            'name' => 'Флага',
            'email' => 'no-enabled-flag-' . uniqid('', true) . '@example.test',
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('trainer_profiles', [
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'description' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'partner_id' => $this->partner->id,
            'lastname' => 'Без',
            'name' => 'Флага',
            'is_enabled' => 1,
        ]);
    }

    public function test_store_and_update_normalize_salary_rubles(): void
    {
        $this->grantTrainersView();

        $email = 'salary-rubles-' . uniqid('', true) . '@example.test';

        $this->postJson(route('admin.trainers.store'), [
            'lastname' => 'Зарплата',
            'name' => 'Рубли',
            'email' => $email,
            'password' => 'password123',
            'default_base_salary' => 10000.6,
            'default_rate_per_training' => 500.4,
        ])->assertOk();

        $profileId = (int) TrainerProfile::query()
            ->where('partner_id', $this->partner->id)
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->value('id');

        $this->assertDatabaseHas('trainer_profiles', [
            'id' => $profileId,
            'default_base_salary' => '10001.00',
            'default_rate_per_training' => '500.00',
        ]);

        $this->putJson(route('admin.trainers.update', $profileId), [
            'lastname' => 'Зарплата',
            'name' => 'Рубли',
            'email' => $email,
            'is_enabled' => 1,
            'default_base_salary' => 20000.9,
            'default_rate_per_training' => 750.1,
        ])->assertOk();

        $this->assertDatabaseHas('trainer_profiles', [
            'id' => $profileId,
            'default_base_salary' => '20001.00',
            'default_rate_per_training' => '750.00',
        ]);
    }

    public function test_show_returns_salary_as_integer_rubles_for_form(): void
    {
        $this->grantTrainersView();

        $profile = $this->createTrainerProfile(
            ['email' => 'show-salary-' . uniqid('', true) . '@example.test'],
            [
                'default_base_salary' => 15000.75,
                'default_rate_per_training' => 999.49,
            ],
        );

        $this->getJson(route('admin.trainers.show', $profile->id))
            ->assertOk()
            ->assertJsonPath('default_base_salary', '15001')
            ->assertJsonPath('default_rate_per_training', '999');
    }

    public function test_columns_settings_roundtrip_for_trainers_index(): void
    {
        $this->grantTrainersView();

        $payload = [
            'avatar' => false,
            'full_name' => true,
            'teams_label' => true,
            'email' => false,
            'default_base_salary' => true,
            'default_rate_per_training' => true,
            'sort_order' => true,
            'is_enabled' => true,
            'actions' => true,
        ];

        $this->postJson(route('admin.trainers.columns-settings.save'), [
            'columns' => $payload,
        ])->assertOk();

        $this->getJson(route('admin.trainers.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('avatar', false)
            ->assertJsonPath('full_name', true)
            ->assertJsonPath('email', false);
    }
}
