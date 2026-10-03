<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Tables;

use App\Models\Role;
use App\Models\SchoolLeadStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Фильтры и «Показать N» списков: своя строка user_table_settings на админа.
 */
final class PersistedListFiltersFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_users_apply_stores_filters_and_reopen_prefills_the_form(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ФильтрГруппа',
        ]);

        UserTableSetting::query()->updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'users_index'],
            ['columns' => ['phone' => false], 'page_length' => 50]
        );

        $this->postJson(route('admin.users.filters.save'), [
            'name' => 'Иванов',
            'team_id' => (string) $team->id,
            'status' => 'inactive',
            'contract' => 'signed',
        ])->assertOk()->assertExactJson(['success' => true]);

        $row = $this->filtersRow('users_index');
        $this->assertSame('Иванов', $row->filters['name']);
        $this->assertSame((string) $team->id, $row->filters['team_id']);
        $this->assertSame('inactive', $row->filters['status']);
        $this->assertSame('signed', $row->filters['contract']);
        $this->assertSame(['phone' => false], $row->columns);
        $this->assertSame(50, $row->page_length);

        $html = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertStringContainsString('value="Иванов"', $html);
        $this->assertStringContainsString('value="inactive" selected', $html);
        $this->assertStringContainsString('value="signed" selected', $html);
        $this->assertStringContainsString('collapse show mb-2 mb-md-3" id="usersReportFiltersCollapse"', $html);

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
        ]);
        $this->actingAs($other);

        $otherHtml = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertStringNotContainsString('value="Иванов"', $otherHtml);
        $this->assertStringContainsString('value="active" selected', $otherHtml);
    }

    public function test_users_reset_stores_defaults_and_rejects_unknown_team(): void
    {
        $this->postJson(route('admin.users.filters.save'), [
            'name' => 'Сброс',
            'status' => 'inactive',
            'team_id' => '',
        ])->assertOk();

        $this->postJson(route('admin.users.filters.save'), [
            'reset' => 1,
        ])->assertOk();

        $filters = $this->filtersRow('users_index')->filters;
        $this->assertSame('', $filters['name']);
        $this->assertSame('active', $filters['status']);
        $this->assertSame('', $filters['team_id']);

        $this->postJson(route('admin.users.filters.save'), [
            'name' => str_repeat('я', 256),
            'team_id' => '999999',
            'status' => 'nope',
        ])->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Имя не длиннее 255 символов.')
            ->assertJsonPath('errors.team_id.0', 'Выберите группу из списка.')
            ->assertJsonPath('errors.status.0', 'Выберите статус: все, только активные или только неактивные.');

        $this->assertSame('active', $this->filtersRow('users_index')->filters['status']);
    }

    public function test_users_contract_filter_is_not_stored_without_permission(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.view');
        $this->grantPartnerRolePermission($actor, 'users.view');
        $this->actingAs($actor);

        $this->postJson(route('admin.users.filters.save'), [
            'name' => 'БезДоговора',
            'team_id' => '',
            'status' => 'active',
            'contract' => 'signed',
        ])->assertOk();

        $this->assertSame('', $this->filtersRow('users_index', (int) $actor->id)->filters['contract']);
        $this->assertSame('БезДоговора', $this->filtersRow('users_index', (int) $actor->id)->filters['name']);
    }

    public function test_trainers_filters_and_page_length_do_not_wipe_each_other(): void
    {
        $this->postJson(route('admin.trainers.filters.save'), [
            'name' => 'ТренерФильтр',
            'team_id' => '',
            'status' => '',
        ])->assertOk();

        $this->postJson(route('admin.trainers.columns-settings.save'), [
            'page_length' => 100,
        ])->assertOk()->assertExactJson(['success' => true]);

        $row = $this->filtersRow('trainers_index');
        $this->assertSame('ТренерФильтр', $row->filters['name']);
        $this->assertSame('', $row->filters['status']);
        $this->assertSame(100, $row->page_length);
        $this->assertNull($row->columns);

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertViewHas('trainersPageLength', 100)
            ->assertSee('value="ТренерФильтр"', false);
    }

    public function test_administrators_persist_filters_and_custom_roles_do_not(): void
    {
        $this->postJson(route('admin.administrators.filters.save'), [
            'name' => 'АдминФильтр',
            'status' => 'inactive',
        ])->assertOk();

        $html = $this->get(route('admin.administrators.index'))->assertOk()->getContent();
        $this->assertStringContainsString('value="АдминФильтр"', $html);
        $this->assertStringContainsString('persistPageLength: true', $html);
        $this->assertSame('inactive', $this->filtersRow('role_staff_admin')->filters['status']);

        $this->postJson(route('admin.administrators.columns-settings.save').'?table_key=role_staff_custom_x', [
            'columns' => ['full_name' => true],
            'page_length' => 50,
        ])->assertOk();

        $custom = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'role_staff_custom_x')
            ->firstOrFail();
        $this->assertNull($custom->page_length);
        $this->assertNull($custom->filters);

        $role = Role::query()->create([
            'name' => 'custom_filters_'.str_replace('.', '', uniqid('', true)),
            'label' => 'Кастом без фильтров',
            'is_sistem' => 0,
            'is_visible' => 1,
            'order_by' => (int) (Role::query()->max('order_by') ?? 0) + 10,
        ]);
        DB::table('partner_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id' => $role->id,
        ]);

        $customHtml = $this->get(route('admin.roles.users.index', ['role' => $role->name]))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('persistPageLength: true', $customHtml);
        $this->assertStringNotContainsString('value="АдминФильтр"', $customHtml);
    }

    public function test_school_leads_save_status_and_special_conditions(): void
    {
        $status = SchoolLeadStatus::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'ФильтрСтатус',
            'color' => '#112233',
            'sort_order' => 40,
            'is_default_in_filter' => false,
            'is_system' => false,
        ]);

        $this->postJson(route('admin.school-leads.filters.save'), [
            'status_ids' => [(string) $status->id],
            'team_ids' => [],
            'location_ids' => [],
            'district_id' => '',
            'has_special_conditions' => 1,
        ])->assertOk();

        $filters = $this->filtersRow('school_leads_index')->filters;
        $this->assertSame([(string) $status->id], $filters['status_ids']);
        $this->assertSame(1, $filters['has_special_conditions']);

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertStringContainsString('value="'.$status->id.'" selected', $html);
        $this->assertStringContainsString('id="sl-filter-special-conditions"', $html);
        $this->assertMatchesRegularExpression(
            '/id="sl-filter-special-conditions"[^>]*checked/',
            $html
        );

        $invalid = $this->postJson(route('admin.school-leads.filters.save'), [
            'status_ids' => ['999999'],
        ])->assertStatus(422);

        $this->assertSame(
            'Выберите статус из списка.',
            $invalid->json('errors')['status_ids.0'][0] ?? null
        );
    }

    public function test_contracts_keep_page_length_20_until_changed_and_store_filters(): void
    {
        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertViewHas('contractsPageLength', 20);

        $this->postJson(route('contracts.filters.save'), [
            'search_value' => 'ДоговорПоиск',
            'group_id' => 'none',
            'status' => 'signed',
        ])->assertOk();

        $this->postJson(route('contracts.columns-settings.save'), [
            'page_length' => 50,
        ])->assertOk();

        $row = $this->filtersRow('contracts_index');
        $this->assertSame('ДоговорПоиск', $row->filters['search_value']);
        $this->assertSame('none', $row->filters['group_id']);
        $this->assertSame('signed', $row->filters['status']);
        $this->assertSame(50, $row->page_length);

        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertViewHas('contractsPageLength', 50)
            ->assertSee('value="ДоговорПоиск"', false);

        UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'contracts_index')
            ->update(['page_length' => 99]);

        $this->get(route('contracts.index'))
            ->assertOk()
            ->assertViewHas('contractsPageLength', 20);
    }

    private function filtersRow(string $tableKey, ?int $userId = null): UserTableSetting
    {
        return UserTableSetting::query()
            ->where('user_id', $userId ?? $this->user->id)
            ->where('table_key', $tableKey)
            ->firstOrFail();
    }
}
