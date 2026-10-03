<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Tables;

use App\Models\Role;
use App\Models\Team;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контракт сохранённых фильтров списков: HTTP, права, разметка и значения по умолчанию.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PersistedListFiltersHttpContractFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_guest_is_sent_to_login_and_does_not_save_list_filters(): void
    {
        auth()->logout();

        foreach ($this->pages() as $page) {
            $this->get(route($page['page']))->assertStatus(302);
            $this->post(route($page['save']), $page['payload'])->assertStatus(302);
            $this->postJson(route($page['save']), $page['payload'])->assertStatus(401);
            $this->assertDatabaseMissing('user_table_settings', [
                'table_key' => $page['table'],
            ]);
        }
    }

    public function test_staff_without_section_permission_gets_403_on_the_list_and_on_save(): void
    {
        $cases = [
            ['users.view', 'admin.user1', 'admin.users.filters.save', ['name' => 'Чужой']],
            ['trainers.view', 'admin.trainers.index', 'admin.trainers.filters.save', ['name' => 'Чужой']],
            ['schoolLeads.view', 'admin.school-leads', 'admin.school-leads.filters.save', ['has_special_conditions' => 0]],
            ['contracts.view', 'contracts.index', 'contracts.filters.save', ['search_value' => 'Чужой']],
        ];

        foreach ($cases as [$permission, $page, $save, $payload]) {
            $actor = $this->createUserWithoutPermission($permission, $this->partner);
            $this->actingAs($actor);

            $this->get(route($page))->assertForbidden();
            $this->postJson(route($save), $payload)->assertForbidden();
            $this->post(route($save), $payload)->assertForbidden();
        }

        $noRoleUpdate = $this->createUserWithoutPermission('users.role.update', $this->partner);
        $this->grantPartnerRolePermission($noRoleUpdate, 'users.view');
        $this->actingAs($noRoleUpdate);

        $this->get(route('admin.administrators.index'))->assertForbidden();
        $this->postJson(route('admin.administrators.filters.save'), [
            'name' => 'Чужой',
        ])->assertForbidden();
    }

    public function test_ajax_apply_returns_json_and_plain_post_returns_to_the_section(): void
    {
        foreach ($this->pages() as $page) {
            $this->postJson(route($page['save']), $page['payload'], [
                'X-Requested-With' => 'XMLHttpRequest',
            ])->assertOk()
                ->assertExactJson(['success' => true]);

            $row = $this->filtersRow($page['table']);
            foreach ($page['expect'] as $key => $value) {
                $this->assertSame($value, $row->filters[$key], $page['table'].' '.$key);
            }

            UserTableSetting::query()
                ->where('user_id', $this->user->id)
                ->where('table_key', $page['table'])
                ->delete();

            $this->from(route($page['page']))
                ->post(route($page['save']), $page['payload'], ['HTTP_ACCEPT' => 'text/html'])
                ->assertStatus(302)
                ->assertRedirect(route($page['page']));

            $saved = $this->filtersRow($page['table']);
            $this->assertNotNull($saved, $page['table']);
            foreach ($page['expect'] as $key => $value) {
                $this->assertSame($value, $saved->filters[$key], $page['table'].' '.$key);
            }
        }
    }

    public function test_plain_post_with_a_bad_field_returns_to_the_list_and_ajax_names_the_field(): void
    {
        $this->postJson(route('admin.users.filters.save'), [
            'name' => 'Останется',
            'status' => 'inactive',
        ])->assertOk();

        $this->from(route('admin.user1'))
            ->post(route('admin.users.filters.save'), [
                'name' => str_repeat('Я', 256),
                'team_id' => '999999',
                'status' => 'nope',
                'contract' => 'maybe',
            ], ['HTTP_ACCEPT' => 'text/html'])
            ->assertStatus(302)
            ->assertRedirect(route('admin.user1'))
            ->assertSessionHasErrors(['name', 'team_id', 'status', 'contract']);

        $kept = $this->filtersRow('users_index');
        $this->assertSame('Останется', $kept->filters['name']);
        $this->assertSame('inactive', $kept->filters['status']);

        $this->postJson(route('admin.users.filters.save'), [
            'name' => str_repeat('Я', 256),
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => 'Имя не длиннее 255 символов.',
            ]);

        $this->postJson(route('admin.trainers.filters.save'), [
            'team_id' => 'none',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'team_id' => 'Выберите группу из списка.',
            ]);

        $this->postJson(route('admin.administrators.filters.save'), [
            'status' => 'nope',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'status' => 'Выберите статус: все, только активные или только неактивные.',
            ]);

        $invalidLead = $this->postJson(route('admin.school-leads.filters.save'), [
            'status_ids' => ['999999'],
            'district_id' => '999999',
            'location_ids' => ['999999'],
            'team_ids' => ['999999'],
            'has_special_conditions' => 'maybe',
        ]);
        $invalidLead->assertStatus(422);
        $errors = $invalidLead->json('errors');
        $this->assertSame('Выберите статус из списка.', $errors['status_ids.0'][0]);
        $this->assertSame('Выберите район из списка.', $errors['district_id'][0]);
        $this->assertSame('Выберите объект из списка.', $errors['location_ids.0'][0]);
        $this->assertSame('Выберите секцию из списка.', $errors['team_ids.0'][0]);
        $this->assertSame(
            'Отметьте «Есть особые условия» или снимите галочку.',
            $errors['has_special_conditions'][0]
        );

        $this->postJson(route('contracts.filters.save'), [
            'search_value' => str_repeat('Д', 256),
            'group_id' => '999999',
            'status' => 'generating_pdf',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'search_value' => 'Поиск не длиннее 255 символов.',
                'group_id' => 'Выберите группу из списка.',
                'status' => 'Выберите статус договора из списка.',
            ]);

        $this->assertSame('Останется', $this->filtersRow('users_index')->filters['name']);
    }

    public function test_filter_address_rejects_get_put_patch_and_delete(): void
    {
        foreach ($this->pages() as $page) {
            foreach (['get', 'put', 'patch', 'delete'] as $method) {
                $response = $this->{$method}(route($page['save']), $page['payload']);
                $this->assertNotContains($response->status(), [200, 500], $page['save'].' '.$method);
            }

            $this->assertDatabaseMissing('user_table_settings', [
                'user_id' => $this->user->id,
                'table_key' => $page['table'],
            ]);
        }
    }

    public function test_first_open_selects_active_people_and_keeps_the_panel_closed(): void
    {
        $users = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($users, 'usersReportFiltersCollapse', false);
        $this->assertStringContainsString('value="active" selected', $this->selectHtml($users, 'filter-status'));
        $this->assertStringNotContainsString('value="inactive" selected', $this->selectHtml($users, 'filter-status'));
        $usersForm = $this->formHtml($users, 'users-report-filters');
        $this->assertLessThan(strpos($usersForm, 'Группа'), strpos($usersForm, '>Имя<'));
        $this->assertLessThan(strpos($usersForm, '>Статус<'), strpos($usersForm, 'Группа'));
        $this->assertLessThan(strpos($usersForm, '>Договор<'), strpos($usersForm, '>Статус<'));
        $this->assertStringContainsString('value="none"', $this->selectHtml($users, 'filter-team'));

        $trainers = $this->get(route('admin.trainers.index'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($trainers, 'trainersReportFiltersCollapse', false);
        $this->assertStringContainsString('value="active" selected', $this->selectHtml($trainers, 'filter-status'));
        $this->assertStringNotContainsString('value="none"', $this->selectHtml($trainers, 'filter-team'));
        $this->assertStringNotContainsString('id="filter-contract"', $trainers);
        $trainersForm = $this->formHtml($trainers, 'trainers-report-filters');
        $this->assertLessThan(strpos($trainersForm, 'Группа'), strpos($trainersForm, '>Имя<'));
        $this->assertLessThan(strpos($trainersForm, '>Статус<'), strpos($trainersForm, 'Группа'));

        $admins = $this->get(route('admin.administrators.index'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($admins, 'roleStaffFiltersCollapse', false);
        $this->assertStringContainsString('value="active" selected', $this->selectHtml($admins, 'role-staff-filter-status'));
        $this->assertStringNotContainsString('id="filter-team"', $admins);
        $this->assertStringContainsString('persistPageLength: true', $admins);
        $this->assertStringContainsString('persistRoleStaffListFilters = true', $admins);

        $contracts = $this->get(route('contracts.index'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($contracts, 'contractsReportFiltersCollapse', false);
        $contractStatus = $this->selectHtml($contracts, 'filter-status');
        $this->assertStringContainsString('value="" selected', $contractStatus);
        $this->assertStringNotContainsString('value="signed" selected', $contractStatus);
        $this->assertStringContainsString('pageLength: 20', $contracts);
        $contractsForm = $this->formHtml($contracts, 'contracts-report-filters');
        $this->assertLessThan(strpos($contractsForm, 'Группа'), strpos($contractsForm, '>Поиск<'));
        $this->assertLessThan(strpos($contractsForm, '>Статус<'), strpos($contractsForm, 'Группа'));
    }

    public function test_choosing_all_people_does_not_force_active_and_opens_the_panel(): void
    {
        $cases = [
            ['admin.users.filters.save', 'admin.user1', 'usersReportFiltersCollapse', 'filter-status', ['status' => '']],
            ['admin.trainers.filters.save', 'admin.trainers.index', 'trainersReportFiltersCollapse', 'filter-status', ['status' => '']],
            ['admin.administrators.filters.save', 'admin.administrators.index', 'roleStaffFiltersCollapse', 'role-staff-filter-status', ['status' => '']],
        ];

        foreach ($cases as [$save, $page, $collapseId, $statusId, $payload]) {
            $this->postJson(route($save), $payload)->assertOk();
            $html = $this->get(route($page))->assertOk()->getContent();
            $this->assertFiltersPanelOpen($html, $collapseId, true);
            $status = $this->selectHtml($html, $statusId);
            $this->assertStringContainsString('value="" selected', $status);
            $this->assertStringNotContainsString('value="active" selected', $status);
        }
    }

    public function test_reset_brings_back_active_people_and_closes_the_panel_on_the_next_visit(): void
    {
        $cases = [
            ['admin.users.filters.save', 'admin.user1', 'usersReportFiltersCollapse', 'filter-status'],
            ['admin.trainers.filters.save', 'admin.trainers.index', 'trainersReportFiltersCollapse', 'filter-status'],
            ['admin.administrators.filters.save', 'admin.administrators.index', 'roleStaffFiltersCollapse', 'role-staff-filter-status'],
        ];

        foreach ($cases as [$save, $page, $collapseId, $statusId]) {
            $this->postJson(route($save), ['name' => 'Сбросить', 'status' => 'inactive'])->assertOk();
            $this->postJson(route($save), ['reset' => 1])->assertOk()->assertExactJson(['success' => true]);

            $html = $this->get(route($page))->assertOk()->getContent();
            $this->assertFiltersPanelOpen($html, $collapseId, false);
            $this->assertStringContainsString('value="active" selected', $this->selectHtml($html, $statusId));
            $this->assertStringNotContainsString('value="Сбросить"', $html);
        }
    }

    public function test_contract_filter_stays_hidden_and_is_not_stored_without_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.view', $this->partner);
        $this->grantPartnerRolePermission($actor, 'users.view');
        $this->actingAs($actor);

        $this->postJson(route('admin.users.filters.save'), [
            'name' => 'БезДоговора',
            'status' => 'inactive',
            'contract' => 'signed',
        ])->assertOk();

        $row = UserTableSetting::query()
            ->where('user_id', $actor->id)
            ->where('table_key', 'users_index')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('БезДоговора', $row->filters['name']);
        $this->assertSame('inactive', $row->filters['status']);
        $this->assertSame('', $row->filters['contract']);

        $html = $this->get(route('admin.user1'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="filter-contract"', $html);
        $this->assertStringContainsString('value="БезДоговора"', $html);
        $this->assertStringContainsString('value="inactive" selected', $this->selectHtml($html, 'filter-status'));
    }

    public function test_school_lead_first_open_selects_default_statuses_and_keeps_the_panel_closed(): void
    {
        $inFilter = $this->createPartnerSchoolLeadStatus([
            'name' => 'В фильтре',
            'is_default_in_filter' => true,
        ]);
        $outside = $this->createPartnerSchoolLeadStatus([
            'name' => 'Вне фильтра',
            'is_default_in_filter' => false,
        ]);

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($html, 'schoolLeadsFiltersCollapse', false);
        $status = $this->selectHtml($html, 'sl-filter-status');
        $this->assertStringContainsString('value="'.$inFilter->id.'" selected', $status);
        $this->assertStringNotContainsString('value="'.$outside->id.'" selected', $status);
        $this->assertStringNotContainsString('checked', $this->inputHtml($html, 'sl-filter-special-conditions'));

        $form = $this->formHtml($html, 'school-leads-filters');
        $this->assertLessThan(strpos($form, 'Район'), strpos($form, '>Статус<'));
        $this->assertLessThan(strpos($form, 'Объект'), strpos($form, 'Район'));
        $this->assertLessThan(strpos($form, 'Секция'), strpos($form, 'Объект'));
        $this->assertLessThan(strpos($form, 'Есть особые условия'), strpos($form, 'Секция'));
    }

    public function test_clearing_school_lead_statuses_does_not_restore_the_defaults(): void
    {
        $inFilter = $this->createPartnerSchoolLeadStatus([
            'name' => 'В фильтре',
            'is_default_in_filter' => true,
        ]);
        $picked = $this->createPartnerSchoolLeadStatus([
            'name' => 'Выбранный',
            'is_default_in_filter' => false,
        ]);

        $this->postJson(route('admin.school-leads.filters.save'), [
            'status_ids' => [(string) $picked->id],
            'has_special_conditions' => 0,
        ])->assertOk();

        $pickedHtml = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($pickedHtml, 'schoolLeadsFiltersCollapse', true);
        $pickedStatus = $this->selectHtml($pickedHtml, 'sl-filter-status');
        $this->assertStringContainsString('value="'.$picked->id.'" selected', $pickedStatus);
        $this->assertStringNotContainsString('value="'.$inFilter->id.'" selected', $pickedStatus);

        $this->postJson(route('admin.school-leads.filters.save'), [
            'has_special_conditions' => 0,
        ])->assertOk();

        $row = $this->filtersRow('school_leads_index');
        $this->assertSame([], $row->filters['status_ids']);
        $this->assertSame(0, (int) $row->filters['has_special_conditions']);

        $cleared = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($cleared, 'schoolLeadsFiltersCollapse', true);
        $clearedStatus = $this->selectHtml($cleared, 'sl-filter-status');
        $this->assertStringNotContainsString('value="'.$inFilter->id.'" selected', $clearedStatus);
        $this->assertStringNotContainsString('value="'.$picked->id.'" selected', $clearedStatus);

        $this->postJson(route('admin.school-leads.filters.save'), ['reset' => 1])->assertOk();
        $reset = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertFiltersPanelOpen($reset, 'schoolLeadsFiltersCollapse', false);
        $resetStatus = $this->selectHtml($reset, 'sl-filter-status');
        $this->assertStringContainsString('value="'.$inFilter->id.'" selected', $resetStatus);
        $this->assertStringNotContainsString('value="'.$picked->id.'" selected', $resetStatus);
    }

    public function test_special_conditions_stay_off_until_the_admin_checks_them(): void
    {
        $this->postJson(route('admin.school-leads.filters.save'), [
            'has_special_conditions' => 0,
        ])->assertOk();

        $off = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertStringNotContainsString('checked', $this->inputHtml($off, 'sl-filter-special-conditions'));

        $this->postJson(route('admin.school-leads.filters.save'), [
            'has_special_conditions' => 1,
        ])->assertOk();

        $on = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertStringContainsString('checked', $this->inputHtml($on, 'sl-filter-special-conditions'));
        $this->assertFiltersPanelOpen($on, 'schoolLeadsFiltersCollapse', true);
    }

    public function test_district_and_location_stay_hidden_and_are_not_stored_without_permission(): void
    {
        $actor = $this->createUserWithoutPermission('districts.view', $this->partner);
        $this->grantPartnerRolePermission($actor, 'schoolLeads.view');
        $this->forgetPartnerRolePermission($actor, 'locations.view');
        $this->actingAs($actor);

        $this->postJson(route('admin.school-leads.filters.save'), [
            'district_id' => '1',
            'location_ids' => ['1'],
            'has_special_conditions' => 1,
        ])->assertOk();

        $row = UserTableSetting::query()
            ->where('user_id', $actor->id)
            ->where('table_key', 'school_leads_index')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('', $row->filters['district_id']);
        $this->assertSame([], $row->filters['location_ids']);
        $this->assertSame(1, (int) $row->filters['has_special_conditions']);

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="sl-filter-district"', $html);
        $this->assertStringNotContainsString('id="sl-filter-location"', $html);
        $this->assertStringContainsString('checked', $this->inputHtml($html, 'sl-filter-special-conditions'));
    }

    public function test_custom_role_page_does_not_remember_filters_or_show_by(): void
    {
        $role = Role::query()->create([
            'name' => 'role_staff_custom_'.str_replace('.', '', uniqid('', true)),
            'label' => 'Кастом',
            'is_sistem' => 0,
            'is_visible' => 1,
            'order_by' => (Role::query()->max('order_by') ?? 0) + 10,
        ]);
        DB::table('partner_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id' => $role->id,
        ]);

        $this->postJson(route('admin.administrators.filters.save'), [
            'name' => 'АдминФильтр',
            'status' => 'inactive',
        ])->assertOk();

        $html = $this->get(route('admin.roles.users.index', ['role' => $role->name]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('value="АдминФильтр"', $html);
        $this->assertStringContainsString('value="active" selected', $this->selectHtml($html, 'role-staff-filter-status'));
        $this->assertStringContainsString('persistRoleStaffListFilters = false', $html);
        $this->assertStringContainsString('persistPageLength: false', $html);
        $this->assertStringNotContainsString('persistPageLength: true', $html);
        $this->assertFiltersPanelOpen($html, 'roleStaffFiltersCollapse', false);

        $tableKey = 'role_staff_'.$role->name;
        $this->postJson(route('admin.roles.users.columns-settings.save', ['role' => $role->name]).'?table_key='.$tableKey, [
            'columns' => [
                'avatar' => true,
                'full_name' => true,
                'email' => false,
                'phone' => true,
                'is_enabled' => true,
                'actions' => true,
            ],
            'page_length' => 50,
        ])->assertOk();

        $row = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', $tableKey)
            ->first();
        $this->assertNotNull($row);
        $this->assertNull($row->page_length);
        $this->assertFalse($row->columns['email']);
        $this->assertNull($row->filters);
    }

    public function test_show_by_does_not_wipe_filters_and_a_bad_number_stays_on_the_field(): void
    {
        $cases = [
            [
                'save' => 'admin.trainers.filters.save',
                'page' => 'admin.trainers.index',
                'table' => 'trainers_index',
                'columns' => 'admin.trainers.columns-settings.save',
                'columns_get' => 'admin.trainers.columns-settings.get',
                'payload' => ['name' => 'ТренерФильтр', 'status' => 'inactive'],
                'field' => 'name',
                'fallback' => 10,
                'json_error' => true,
            ],
            [
                'save' => 'admin.administrators.filters.save',
                'page' => 'admin.administrators.index',
                'table' => 'role_staff_admin',
                'columns' => 'admin.administrators.columns-settings.save',
                'columns_query' => '?table_key=role_staff_admin',
                'columns_get' => 'admin.administrators.columns-settings.get',
                'payload' => ['name' => 'АдминФильтр', 'status' => 'inactive'],
                'field' => 'name',
                'fallback' => 10,
                'json_error' => true,
            ],
            [
                'save' => 'admin.school-leads.filters.save',
                'page' => 'admin.school-leads',
                'table' => 'school_leads_index',
                'columns' => 'admin.school-leads.columns-settings.save',
                'columns_get' => 'admin.school-leads.columns-settings.get',
                'payload' => ['has_special_conditions' => 1],
                'field' => 'has_special_conditions',
                'fallback' => 10,
                'json_error' => true,
            ],
            [
                'save' => 'contracts.filters.save',
                'page' => 'contracts.index',
                'table' => 'contracts_index',
                'columns' => 'contracts.columns-settings.save',
                'columns_get' => 'contracts.columns-settings.get',
                'payload' => ['search_value' => 'ДоговорПоиск', 'status' => 'signed'],
                'field' => 'search_value',
                'fallback' => 20,
                'json_error' => false,
            ],
        ];

        foreach ($cases as $case) {
            $this->postJson(route($case['save']), $case['payload'])->assertOk();

            $saveUrl = route($case['columns']).($case['columns_query'] ?? '');
            $this->postJson($saveUrl, ['page_length' => 50])
                ->assertOk()
                ->assertJson(['success' => true]);

            $row = $this->filtersRow($case['table']);
            $this->assertSame($case['payload'][$case['field']], $row->filters[$case['field']]);
            $this->assertSame(50, (int) $row->page_length);

            $this->postJson(route($case['save']), $case['payload'])->assertOk();
            $this->assertSame(50, (int) $this->filtersRow($case['table'])->page_length);

            $this->postJson($saveUrl, ['page_length' => 7])
                ->assertStatus(422)
                ->assertJsonValidationErrors([
                    'page_length' => 'Можно показать 10, 20, 50 или 100 записей.',
                ]);
            $this->assertSame(50, (int) $this->filtersRow($case['table'])->page_length);

            $native = $this->from(route($case['page']))
                ->post($saveUrl, ['page_length' => 7], ['HTTP_ACCEPT' => 'text/html']);
            $this->assertNotSame(500, $native->status());
            $this->assertNotSame(200, $native->status());
            if ($case['json_error']) {
                $native->assertStatus(302)->assertSessionHasErrors(['page_length']);
            } else {
                $native->assertStatus(422)->assertJsonValidationErrors(['page_length']);
            }
            $this->assertSame(50, (int) $this->filtersRow($case['table'])->page_length);

            $getUrl = route($case['columns_get']).($case['columns_query'] ?? '');
            $this->assertArrayNotHasKey('page_length', $this->getJson($getUrl)->assertOk()->json());

            UserTableSetting::query()
                ->where('user_id', $this->user->id)
                ->where('table_key', $case['table'])
                ->update(['page_length' => 99]);

            $this->get(route($case['page']))
                ->assertOk()
                ->assertSee('pageLength: '.$case['fallback'], false);
        }
    }

    public function test_another_admin_does_not_see_the_saved_trainer_or_contract_filters(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ЧужаяГруппа',
        ]);

        $this->postJson(route('admin.trainers.filters.save'), [
            'name' => 'ТренерЧужой',
            'team_id' => (string) $team->id,
            'status' => 'inactive',
        ])->assertOk();
        $this->postJson(route('contracts.filters.save'), [
            'search_value' => 'ДоговорЧужой',
            'group_id' => 'none',
            'status' => 'signed',
        ])->assertOk();

        $other = $this->createUserWithRole('admin', $this->partner);
        $this->actingAs($other);

        $trainers = $this->get(route('admin.trainers.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('ТренерЧужой', $trainers);
        $this->assertStringContainsString('value="active" selected', $this->selectHtml($trainers, 'filter-status'));
        $this->assertFiltersPanelOpen($trainers, 'trainersReportFiltersCollapse', false);

        $contracts = $this->get(route('contracts.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('ДоговорЧужой', $contracts);
        $this->assertStringNotContainsString('value="signed" selected', $this->selectHtml($contracts, 'filter-status'));
        $this->assertFiltersPanelOpen($contracts, 'contractsReportFiltersCollapse', false);
    }

    /**
     * @return list<array{page: string, save: string, table: string, payload: array<string, mixed>, expect: array<string, mixed>}>
     */
    private function pages(): array
    {
        return [
            [
                'page' => 'admin.user1',
                'save' => 'admin.users.filters.save',
                'table' => 'users_index',
                'payload' => ['name' => 'Иванов', 'status' => 'inactive', 'team_id' => 'none'],
                'expect' => ['name' => 'Иванов', 'status' => 'inactive', 'team_id' => 'none'],
            ],
            [
                'page' => 'admin.trainers.index',
                'save' => 'admin.trainers.filters.save',
                'table' => 'trainers_index',
                'payload' => ['name' => 'Тренер', 'status' => 'inactive'],
                'expect' => ['name' => 'Тренер', 'status' => 'inactive'],
            ],
            [
                'page' => 'admin.administrators.index',
                'save' => 'admin.administrators.filters.save',
                'table' => 'role_staff_admin',
                'payload' => ['name' => 'Админ', 'status' => 'inactive'],
                'expect' => ['name' => 'Админ', 'status' => 'inactive'],
            ],
            [
                'page' => 'admin.school-leads',
                'save' => 'admin.school-leads.filters.save',
                'table' => 'school_leads_index',
                'payload' => ['has_special_conditions' => 1],
                'expect' => ['has_special_conditions' => 1],
            ],
            [
                'page' => 'contracts.index',
                'save' => 'contracts.filters.save',
                'table' => 'contracts_index',
                'payload' => ['search_value' => 'Договор', 'group_id' => 'none', 'status' => 'signed'],
                'expect' => ['search_value' => 'Договор', 'group_id' => 'none', 'status' => 'signed'],
            ],
        ];
    }

    private function filtersRow(string $tableKey): ?UserTableSetting
    {
        return UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', $tableKey)
            ->first();
    }

    private function forgetPartnerRolePermission(\App\Models\User $actor, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('partner_id', (int) ($actor->partner_id ?: $this->partner->id))
            ->where('role_id', (int) $actor->role_id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
        $actor->unsetRelation('role');
    }

    private function assertFiltersPanelOpen(string $html, string $id, bool $open): void
    {
        $this->assertSame(
            1,
            preg_match('/<div class="([^"]*)" id="'.preg_quote($id, '/').'">/', $html, $matches),
            'Не найден блок фильтров '.$id
        );
        $isOpen = preg_match('/(?:^|\s)show(?:\s|$)/', $matches[1]) === 1;
        $this->assertSame($open, $isOpen, $matches[0]);
    }

    private function selectHtml(string $html, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('/<select\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>.*?<\/select>/s', $html, $matches),
            'Не найден select #'.$id
        );

        return $matches[0];
    }

    private function inputHtml(string $html, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('/<input\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>/', $html, $matches),
            'Не найден input #'.$id
        );

        return $matches[0];
    }

    private function formHtml(string $html, string $id): string
    {
        $this->assertSame(
            1,
            preg_match('/<form\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>.*?<\/form>/s', $html, $matches),
            'Не найдена форма #'.$id
        );

        return $matches[0];
    }
}
