<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Страница «Пользователи» (/admin/users): новый тулбар, фильтры, поиск DataTables, доступ к endpoint’ам.
 */
final class AdminUsersPageFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    private function grantUsersView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId('users.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

  // --- UI: тулбар, фильтры, таблица ---

    public function test_users_page_renders_toolbar_filter_panel_and_table(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewIs('admin.user')
            ->assertViewHas('activeTab', 'users')
            ->getContent();

        $this->assertStringContainsString('>Пользователи</h4>', $html);
        $this->assertStringContainsString('>Пользователи</h1>', $html);
        $this->assertStringContainsString('payments-report-surface', $html);
        $this->assertStringContainsString('admin-list-toolbar', $html);
        $this->assertStringContainsString('payments-report-toolbar-actions--many', $html);

        $this->assertStringContainsString('id="new-user"', $html);
        $this->assertStringContainsString('>Добавить</span>', $html);
        $this->assertStringContainsString('>Настройки</span>', $html);
        $this->assertStringContainsString('>История</span>', $html);
        $this->assertStringContainsString('id="usersReportFiltersToggle"', $html);
        $this->assertStringContainsString('>Фильтры</span>', $html);
        $this->assertStringContainsString('id="columnsDropdown"', $html);
        $this->assertStringContainsString('>Колонки</span>', $html);

        $this->assertStringContainsString('id="usersReportFiltersCollapse"', $html);
        $this->assertStringContainsString('id="users-report-filters"', $html);
        $this->assertStringContainsString('id="filter-name"', $html);
        $this->assertStringContainsString('id="filter-team"', $html);
        $this->assertStringContainsString('id="filter-status"', $html);
        $this->assertStringContainsString('value="active" selected', $html);
        $this->assertStringContainsString('id="filter-apply"', $html);
        $this->assertStringContainsString('id="filter-reset"', $html);

        $this->assertStringContainsString('id="users-table"', $html);
        $this->assertStringContainsString('KidsCrmDataTable.create', $html);
        $this->assertStringContainsString('js-dt-cell-ellipsis-tooltip', $html);
        $this->assertStringContainsString('id="createUserModal"', $html);
        $this->assertStringContainsString('id="fieldModal"', $html);
        $this->assertStringContainsString('id="historyModal"', $html);

        $addPos = strpos($html, '>Добавить</span>');
        $settingsPos = strpos($html, '>Настройки</span>');
        $historyPos = strpos($html, '>История</span>');
        $filtersPos = strpos($html, '>Фильтры</span>');
        $columnsPos = strpos($html, '>Колонки</span>');

        $this->assertNotFalse($addPos);
        $this->assertNotFalse($settingsPos);
        $this->assertNotFalse($historyPos);
        $this->assertNotFalse($filtersPos);
        $this->assertNotFalse($columnsPos);
        $this->assertLessThan($settingsPos, $addPos);
        $this->assertLessThan($historyPos, $settingsPos);
        $this->assertLessThan($filtersPos, $historyPos);
        $this->assertLessThan($columnsPos, $filtersPos);
    }

  // --- DataTables: фильтры панели и стандартный поиск ---

    public function test_users_data_filters_by_panel_name_parameter(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ПанельФильтр',
            'name'       => 'Уникальный',
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Другой',
            'name'       => 'Человек',
        ]);

        $response = $this->getJson('/admin/users/data?draw=1&start=0&length=50&name=ПанельФильтр');

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertSame($target->id, $response->json('data.0.id'));
    }

    public function test_users_data_filters_by_datatables_search_value(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'DtSearchUnique',
            'name'       => 'Студент',
            'email'      => 'dt-search-' . uniqid('', true) . '@example.test',
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Посторонний',
            'name'       => 'Человек',
        ]);

        $response = $this->getJson('/admin/users/data?draw=1&start=0&length=50&search[value]=DtSearchUnique');

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertSame($target->id, $response->json('data.0.id'));
    }

    public function test_users_data_panel_name_takes_precedence_over_datatables_search(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $byPanel = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ПриоритетПанели',
            'name'       => 'А',
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ПриоритетПоиска',
            'name'       => 'Б',
        ]);

        $response = $this->getJson(
            '/admin/users/data?draw=1&start=0&length=50&name=ПриоритетПанели&search[value]=ПриоритетПоиска'
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($byPanel->id, $ids);
        $this->assertSame(1, $response->json('recordsFiltered'));
    }

    public function test_users_data_filter_combinations_return_ok(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ]);

        $queries = [
            '/admin/users/data?draw=1&start=0&length=10&status=active',
            '/admin/users/data?draw=1&start=0&length=10&status=inactive',
            '/admin/users/data?draw=1&start=0&length=10&team_id=' . $team->id,
            '/admin/users/data?draw=1&start=0&length=10&team_id=none',
            '/admin/users/data?draw=1&start=0&length=10&status=active&team_id=' . $team->id,
        ];

        foreach ($queries as $url) {
            $this->getJson($url)->assertOk()->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
            ]);
        }
    }

    public function test_users_data_status_active_excludes_inactive_users(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $active = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $inactive = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 0,
        ]);

        $json = $this->getJson('/admin/users/data?draw=1&start=0&length=100&status=active')->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

  // --- Доступ: страница и все endpoint’ы раздела ---

    public function test_guest_cannot_access_users_page_and_data(): void
    {
        Auth::logout();

        $this->get(route('admin.user1'))->assertRedirect();
        $this->getJson('/admin/users/data?draw=1')->assertUnauthorized();
    }

    public function test_users_page_and_all_section_endpoints_return_ok_with_users_view(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $roleId = $this->studentRoleId();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $existingField = UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('page_smoke_field')
            ->create(['name' => 'Smoke поле']);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('activeTab', 'users');

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertOk();
        $this->getJson('/admin/users/data?draw=1&status=active&search[value]=test')->assertOk();
        $this->getJson('/admin/users/data?draw=1&team_id=' . $team->id)->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'avatar' => true,
                'name'   => true,
                'email'  => false,
            ],
        ])->assertOk();

        $this->getJson(route('logs.data.user', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $this->postJson(route('admin.field.store'), [
            'fields' => [
                [
                    'id'         => $existingField->id,
                    'name'       => $existingField->name,
                    'field_type' => 'string',
                    'roles'      => [],
                ],
            ],
        ])->assertOk();

        $store = $this->postJson(route('admin.user.store'), [
            'name'       => 'Страница',
            'lastname'   => 'Smoke',
            'email'      => 'page-smoke-' . uniqid('', true) . '@example.test',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $userId = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $userId);

        $this->getJson(route('admin.user.edit', $userId))->assertOk();
        $this->patchJson(route('admin.user.update', $userId), [
            'name'     => 'Страница',
            'lastname' => 'Обновлён',
        ])->assertOk();
    }

    public function test_users_page_and_all_section_endpoints_return_forbidden_without_users_view(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);

        $peer = User::factory()->create(['partner_id' => $this->partner->id]);
        $roleId = $this->studentRoleId();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.user1'))->assertForbidden();

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertForbidden();
        $this->getJson('/admin/users/data?draw=1&search[value]=test')->assertForbidden();
        $this->getJson('/admin/users/data?draw=1&status=active&team_id=' . $team->id)->assertForbidden();

        $this->getJson(route('admin.users.table-settings.get'))->assertForbidden();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['name' => true],
        ])->assertForbidden();

        $this->getJson(route('logs.data.user', ['draw' => 1]))->assertForbidden();

        $this->postJson(route('admin.field.store'), [
            'fields' => [],
        ])->assertForbidden();

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Нет',
            'lastname'   => 'Права',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ])->assertForbidden();

        $this->getJson(route('admin.user.edit', $peer->id))->assertForbidden();
        $this->patchJson(route('admin.user.update', $peer->id), [
            'name'     => 'Н',
            'lastname' => 'Т',
        ])->assertForbidden();
    }
}
