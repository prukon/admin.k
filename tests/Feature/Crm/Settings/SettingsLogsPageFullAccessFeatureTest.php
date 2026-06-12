<?php

namespace Tests\Feature\Crm\Settings;

use App\Enums\AuditEvent;
use App\Enums\AuditLevel;
use App\Models\MyLog;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Раздел «Настройки → Логи» (/admin/settings/logs) и GET settings.logs.data:
 * доступ (viewing.all.logs + settings.view для страницы), DataTables, фильтры, SUPERADMIN_ALL_OR_FILTER.
 */
final class SettingsLogsPageFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_cannot_access_any_logs_section_endpoint(): void
    {
        Auth::logout();

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_viewing_all_logs_gets_403_on_all_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('viewing.all.logs', $this->partner);
        $this->grantSettingsView((int) $actor->role_id);
        $this->actingAs($actor);

        foreach ($this->allSectionRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без viewing.all.logs: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_settings_view_gets_403_on_page_but_logs_data_ok_with_viewing_all_logs(): void
    {
        $actor = $this->createUserWithoutPermission('settings.view', $this->partner);
        $this->grantViewingAllLogs((int) $actor->role_id);
        $this->actingAs($actor);

        $this->get(route('admin.setting.logs'))->assertForbidden();

        $this->getJson(route('settings.logs.data', $this->baseDataTableParams()))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_user_with_viewing_all_logs_and_settings_view_all_section_endpoints_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('viewing.all.logs', $this->partner);
        $this->grantViewingAllLogs((int) $actor->role_id);
        $this->grantSettingsView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser();
    }

    public function test_superadmin_all_section_endpoints_return_200(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');

        $this->assertAllSectionEndpointsSucceedForAuthorizedUser(isSuperadmin: true);
    }

    public function test_index_page_returns_200_with_logs_tab_and_toolbar(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->get(route('admin.setting.logs'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'logs')
            ->assertSee('payments-report-toolbar', false)
            ->assertSee('>Фильтры</span>', false)
            ->assertSee('id="settingsLogsTable"', false)
            ->assertSee('id="settings-logs-filters"', false)
            ->assertSee('KidsCrmDataTable.create', false);
    }

    public function test_superadmin_index_shows_partner_filter_and_table_column(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->get(route('admin.setting.logs'))
            ->assertOk()
            ->assertSee('id="settings-logs-filter-partner"', false)
            ->assertSee('value="all"', false)
            ->assertSee('>Партнёр</th>', false);
    }

    public function test_admin_index_hides_partner_filter_and_column(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $html = $this->get(route('admin.setting.logs'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="settings-logs-filter-partner"', $html);
        $this->assertStringNotContainsString('>Партнёр</th>', $html);
    }

    public function test_logs_data_returns_datatables_json_structure(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'structure-smoke');

        $this->getJson(route('settings.logs.data', $this->baseDataTableParams()))
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    '*' => [
                        'id',
                        'created_at',
                        'action',
                        'author',
                        'target_label',
                        'description',
                    ],
                ],
            ]);
    }

    public function test_superadmin_logs_data_includes_partner_title_column(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'with-partner-col');

        $row = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['filter_partner_id' => 'all']
        )))->json('data'))->first();

        $this->assertIsArray($row);
        $this->assertArrayHasKey('partner_title', $row);
        $this->assertSame($this->partner->title, $row['partner_title']);
    }

    public function test_superadmin_logs_data_sees_all_partners_when_filter_all(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'scope-all-home');
        $this->createPartnerLog($this->foreignPartner->id, 'scope-all-foreign');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['filter_partner_id' => 'all']
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('scope-all-home', $descriptions);
        $this->assertContains('scope-all-foreign', $descriptions);
    }

    public function test_superadmin_logs_data_filters_single_partner(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'filter-partner-home');
        $this->createPartnerLog($this->foreignPartner->id, 'filter-partner-foreign');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['filter_partner_id' => (string) $this->foreignPartner->id]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('filter-partner-foreign', $descriptions);
        $this->assertNotContains('filter-partner-home', $descriptions);
    }

    public function test_non_superadmin_logs_data_ignores_filter_all_and_scopes_to_current_partner(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'scope-admin-home');
        $this->createPartnerLog($this->foreignPartner->id, 'scope-admin-foreign');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['filter_partner_id' => 'all']
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('scope-admin-home', $descriptions);
        $this->assertNotContains('scope-admin-foreign', $descriptions);
    }

    public function test_logs_data_filters_by_created_date_range(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'date-old', '2024-03-01 10:00:00');
        $this->createPartnerLog($this->partner->id, 'date-new', '2025-08-15 12:00:00');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'created_from' => '2025-01-01',
                'created_to' => '2025-12-31',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('date-new', $descriptions);
        $this->assertNotContains('date-old', $descriptions);
    }

    public function test_logs_data_filters_by_action_author_and_target_label(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->user->forceFill([
            'name' => 'Уникальный',
            'lastname' => 'АвторЛогов',
        ])->save();

        MyLog::query()->create([
            'event' => AuditEvent::TeamCreated->value,
            'level' => AuditEvent::TeamCreated->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Team',
            'target_id' => 1,
            'target_label' => 'Группа Альфа',
            'description' => 'match-all-filters',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->partner->id,
            'target_label' => 'Другое',
            'description' => 'no-match-filters',
            'created_at' => now(),
        ]);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_action' => AuditEvent::TeamCreated->value,
                'filter_author' => 'АвторЛогов',
                'filter_target_label' => 'Альфа',
                'hide_superadmin' => '0',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('match-all-filters', $descriptions);
        $this->assertNotContains('no-match-filters', $descriptions);
    }

    public function test_logs_data_hides_superadmin_author_when_hide_superadmin_enabled(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $superadmin = $this->createUserWithRole('superadmin', $this->partner);

        $this->createPartnerLog($this->partner->id, 'regular-admin-log', null, 'HideSuperadminFilter');
        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'author_id' => $superadmin->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->partner->id,
            'target_label' => 'HideSuperadminFilter',
            'description' => 'superadmin-author-log',
            'created_at' => now(),
        ]);

        $hidden = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_target_label' => 'HideSuperadminFilter',
                'hide_superadmin' => '1',
            ]
        )));
        $hidden->assertOk();
        $hiddenDescriptions = collect($hidden->json('data'))->pluck('description')->all();
        $this->assertContains('regular-admin-log', $hiddenDescriptions);
        $this->assertNotContains('superadmin-author-log', $hiddenDescriptions);

        $visible = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_target_label' => 'HideSuperadminFilter',
                'hide_superadmin' => '0',
            ]
        )));
        $visible->assertOk();
        $visibleDescriptions = collect($visible->json('data'))->pluck('description')->all();
        $this->assertContains('regular-admin-log', $visibleDescriptions);
        $this->assertContains('superadmin-author-log', $visibleDescriptions);
    }

    public function test_logs_data_filters_by_level(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_label' => 'LevelFilter',
            'description' => 'security-login-log',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_label' => 'LevelFilter',
            'description' => 'info-settings-log',
            'created_at' => now(),
        ]);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_level' => AuditLevel::Security->value,
                'filter_target_label' => 'LevelFilter',
                'hide_superadmin' => '0',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('security-login-log', $descriptions);
        $this->assertNotContains('info-settings-log', $descriptions);
    }

    public function test_logs_data_hides_authorizations_when_hide_authorizations_enabled(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'settings-change-log', null, 'HideAuthFilter');
        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => null,
            'target_id' => null,
            'target_label' => 'HideAuthFilter',
            'description' => 'authorization-log-event',
            'created_at' => now(),
        ]);

        $hidden = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_target_label' => 'HideAuthFilter',
                'hide_superadmin' => '0',
                'hide_authorizations' => '1',
            ]
        )));
        $hidden->assertOk();
        $hiddenDescriptions = collect($hidden->json('data'))->pluck('description')->all();
        $this->assertContains('settings-change-log', $hiddenDescriptions);
        $this->assertNotContains('authorization-log-event', $hiddenDescriptions);
    }

    public function test_logs_data_filters_by_unknown_action_type(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        MyLog::query()->create([
            'event' => 'not.in.registry',
            'level' => AuditLevel::Info->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->partner->id,
            'target_label' => 'UnknownActionFilter',
            'description' => 'unknown-action-log',
            'created_at' => now(),
        ]);

        $this->createPartnerLog($this->partner->id, 'known-action-log', null, 'UnknownActionFilter');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_action' => 'unknown',
                'filter_target_label' => 'UnknownActionFilter',
                'hide_superadmin' => '0',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('unknown-action-log', $descriptions);
        $this->assertNotContains('known-action-log', $descriptions);
    }

    public function test_index_page_shows_new_log_filters(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->get(route('admin.setting.logs'))
            ->assertOk()
            ->assertSee('id="settings-logs-filter-hide-superadmin"', false)
            ->assertSee('>Скрыть суперадмина</label>', false)
            ->assertSee('id="settings-logs-filter-hide-authorizations"', false)
            ->assertSee('>Скрыть авторизации</label>', false)
            ->assertSee('id="settings-logs-filter-hide-integrations"', false)
            ->assertSee('>Скрыть интеграции</label>', false)
            ->assertSee('id="settings-logs-filter-level"', false)
            ->assertSee('value="unknown"', false)
            ->assertSee('value="'.AuditEvent::SettingsUpdated->value.'"', false)
            ->assertSee('>Неизвестный тип</option>', false);
    }

    public function test_superadmin_logs_data_order_by_partner_title_asc(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $partnerA = Partner::factory()->create(['title' => 'AAA Логи Партнёр']);
        $partnerZ = Partner::factory()->create(['title' => 'ZZZ Логи Партнёр']);

        $this->createPartnerLog($partnerZ->id, 'order-z-log', null, 'OrderPartnerSort');
        $this->createPartnerLog($partnerA->id, 'order-a-log', null, 'OrderPartnerSort');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->superadminDataTableParams(),
            [
                'filter_partner_id' => 'all',
                'filter_target_label' => 'OrderPartnerSort',
                'order' => [
                    ['column' => 2, 'dir' => 'asc'],
                ],
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->values()->all();
        $this->assertNotEmpty($descriptions);
        $this->assertSame('order-a-log', $descriptions[0]);
    }

    public function test_datatable_filter_query_variants_return_200(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'variants-smoke');

        foreach ($this->allLogsDataFilterParamVariants(isSuperadmin: true) as $params) {
            $this->getJson(route('settings.logs.data', $params))->assertOk();
        }
    }

    public function test_authorized_admin_all_logs_endpoints_and_filter_variants_return_200(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'access-smoke-admin');

        $this->get(route('admin.setting.logs'))->assertOk();

        $this->get(route('admin.setting.logs', [
            'created_from' => now()->subMonth()->toDateString(),
            'hide_superadmin' => '1',
            'hide_authorizations' => '0',
            'filter_action' => 'unknown',
        ]))->assertOk();

        foreach ($this->allLogsDataFilterParamVariants(isSuperadmin: false) as $params) {
            $this->getJson(route('settings.logs.data', $params))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_authorized_superadmin_all_logs_endpoints_and_filter_variants_return_200(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'access-smoke-superadmin');

        $this->get(route('admin.setting.logs'))->assertOk();

        foreach ($this->allLogsDataFilterParamVariants(isSuperadmin: true) as $params) {
            $this->getJson(route('settings.logs.data', $params))
                ->assertOk()
                ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_user_without_settings_view_logs_data_all_filter_variants_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('settings.view', $this->partner);
        $this->grantViewingAllLogs((int) $actor->role_id);
        $this->actingAs($actor);

        $this->createPartnerLog($this->partner->id, 'data-only-access');

        $this->get(route('admin.setting.logs'))->assertForbidden();

        foreach ($this->allLogsDataFilterParamVariants(isSuperadmin: false) as $params) {
            $this->getJson(route('settings.logs.data', $params))->assertOk();
        }
    }

    public function test_index_page_hide_superadmin_checked_by_default(): void
    {
        $this->grantPermissionToCurrentRole('settings.view');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $html = $this->get(route('admin.setting.logs'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="settings-logs-filter-hide-superadmin"[^>]*checked/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="settings-logs-filter-hide-authorizations"[^>]*checked/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="settings-logs-filter-hide-integrations"[^>]*checked/',
            $html
        );
    }

    public function test_logs_data_hides_integrations_when_hide_integrations_enabled(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'settings-change-log', null, 'HideIntegrationFilter');
        MyLog::query()->create([
            'event' => AuditEvent::PaymentReceived->value,
            'level' => AuditEvent::PaymentReceived->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_label' => 'HideIntegrationFilter',
            'description' => 'integration-log',
            'created_at' => now(),
        ]);

        $hidden = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_target_label' => 'HideIntegrationFilter',
                'hide_superadmin' => '0',
                'hide_integrations' => '1',
            ]
        )));
        $hidden->assertOk();
        $hiddenDescriptions = collect($hidden->json('data'))->pluck('description')->all();
        $this->assertContains('settings-change-log', $hiddenDescriptions);
        $this->assertNotContains('integration-log', $hiddenDescriptions);
    }

    public function test_logs_data_shows_authorizations_when_hide_authorizations_disabled(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'settings-change-visible', null, 'ShowAuthFilter');
        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => null,
            'target_id' => null,
            'target_label' => 'ShowAuthFilter',
            'description' => 'authorization-visible-log',
            'created_at' => now(),
        ]);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_target_label' => 'ShowAuthFilter',
                'hide_superadmin' => '0',
                'hide_authorizations' => '0',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('settings-change-visible', $descriptions);
        $this->assertContains('authorization-visible-log', $descriptions);
    }

    public function test_logs_data_without_hide_superadmin_param_does_not_hide_superadmin_author(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $superadmin = $this->createUserWithRole('superadmin', $this->partner);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'author_id' => $superadmin->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->partner->id,
            'target_label' => 'NoHideParamFilter',
            'description' => 'superadmin-visible-without-param',
            'created_at' => now(),
        ]);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['filter_target_label' => 'NoHideParamFilter']
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('superadmin-visible-without-param', $descriptions);
    }

    public function test_logs_data_combined_new_filters_apply_together(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $superadmin = $this->createUserWithRole('superadmin', $this->partner);

        $this->createPartnerLog($this->partner->id, 'combined-regular-log', null, 'CombinedNewFilters');
        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'author_id' => $superadmin->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->partner->id,
            'target_label' => 'CombinedNewFilters',
            'description' => 'combined-superadmin-log',
            'created_at' => now(),
        ]);
        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => null,
            'target_id' => null,
            'target_label' => 'CombinedNewFilters',
            'description' => 'combined-auth-log',
            'created_at' => now(),
        ]);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_target_label' => 'CombinedNewFilters',
                'hide_superadmin' => '1',
                'hide_authorizations' => '1',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('combined-regular-log', $descriptions);
        $this->assertNotContains('combined-superadmin-log', $descriptions);
        $this->assertNotContains('combined-auth-log', $descriptions);
    }

    public function test_non_superadmin_cannot_see_foreign_partner_logs_with_foreign_filter_partner_id(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'partner-scope-home');
        $this->createPartnerLog($this->foreignPartner->id, 'partner-scope-foreign');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_partner_id' => (string) $this->foreignPartner->id,
                'hide_superadmin' => '0',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('partner-scope-home', $descriptions);
        $this->assertNotContains('partner-scope-foreign', $descriptions);
    }

    public function test_foreign_session_admin_sees_only_foreign_partner_logs(): void
    {
        $this->createPartnerLog($this->partner->id, 'session-home-log');
        $this->createPartnerLog($this->foreignPartner->id, 'session-foreign-log');

        $this->asForeignUser();
        $this->grantViewingAllLogsForPartner((int) $this->foreignUser->role_id, $this->foreignPartner->id);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            ['hide_superadmin' => '0']
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('session-foreign-log', $descriptions);
        $this->assertNotContains('session-home-log', $descriptions);
    }

    public function test_new_filters_do_not_bypass_partner_scope_for_non_superadmin(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $superadmin = $this->createUserWithRole('superadmin', $this->foreignPartner);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->partner->id,
            'target_label' => 'ScopeNewFilters',
            'description' => 'scope-new-filters-home',
            'created_at' => now(),
        ]);
        MyLog::query()->create([
            'event' => 'not.in.registry',
            'level' => AuditLevel::Info->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->foreignPartner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->foreignPartner->id,
            'target_label' => 'ScopeNewFilters',
            'description' => 'scope-new-filters-foreign-unknown',
            'created_at' => now(),
        ]);
        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'author_id' => $superadmin->id,
            'partner_id' => $this->foreignPartner->id,
            'target_type' => null,
            'target_id' => null,
            'target_label' => 'ScopeNewFilters',
            'description' => 'scope-new-filters-foreign-auth',
            'created_at' => now(),
        ]);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_partner_id' => 'all',
                'filter_target_label' => 'ScopeNewFilters',
                'hide_superadmin' => '0',
                'hide_authorizations' => '0',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('scope-new-filters-home', $descriptions);
        $this->assertNotContains('scope-new-filters-foreign-unknown', $descriptions);
        $this->assertNotContains('scope-new-filters-foreign-auth', $descriptions);
    }

    public function test_superadmin_new_filters_work_across_partners(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $regularAdmin = $this->createUserWithRole('admin', $this->partner);
        $foreignSuperadmin = $this->createUserWithRole('superadmin', $this->foreignPartner);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'author_id' => $regularAdmin->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->partner->id,
            'target_label' => 'CrossPartnerNewFilters',
            'description' => 'cross-partner-home-regular',
            'created_at' => now(),
        ]);
        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'author_id' => $foreignSuperadmin->id,
            'partner_id' => $this->foreignPartner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->foreignPartner->id,
            'target_label' => 'CrossPartnerNewFilters',
            'description' => 'cross-partner-foreign-superadmin',
            'created_at' => now(),
        ]);
        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'author_id' => $regularAdmin->id,
            'partner_id' => $this->foreignPartner->id,
            'target_type' => null,
            'target_id' => null,
            'target_label' => 'CrossPartnerNewFilters',
            'description' => 'cross-partner-foreign-auth',
            'created_at' => now(),
        ]);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_partner_id' => 'all',
                'filter_target_label' => 'CrossPartnerNewFilters',
                'hide_superadmin' => '1',
                'hide_authorizations' => '1',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('cross-partner-home-regular', $descriptions);
        $this->assertNotContains('cross-partner-foreign-superadmin', $descriptions);
        $this->assertNotContains('cross-partner-foreign-auth', $descriptions);
    }

    public function test_superadmin_with_null_partner_id_and_filter_all_sees_all_partners(): void
    {
        $this->asSuperadmin();
        $this->user->partner_id = null;
        $this->user->save();
        $this->actingAs($this->user);
        $this->user->unsetRelation('role');
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        $this->createPartnerLog($this->partner->id, 'null-partner-home');
        $this->createPartnerLog($this->foreignPartner->id, 'null-partner-foreign');

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_partner_id' => 'all',
                'hide_superadmin' => '0',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('null-partner-home', $descriptions);
        $this->assertContains('null-partner-foreign', $descriptions);
    }

    public function test_unknown_action_filter_respects_partner_isolation(): void
    {
        $this->grantPermissionToCurrentRole('viewing.all.logs');

        MyLog::query()->create([
            'event' => 'not.in.registry',
            'level' => AuditLevel::Info->value,
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->partner->id,
            'target_label' => 'UnknownPartnerScope',
            'description' => 'unknown-home-log',
            'created_at' => now(),
        ]);
        MyLog::query()->create([
            'event' => 'legacy.unknown.event',
            'level' => AuditLevel::Info->value,
            'author_id' => $this->foreignUser->id,
            'partner_id' => $this->foreignPartner->id,
            'target_type' => 'App\Models\Setting',
            'target_id' => $this->foreignPartner->id,
            'target_label' => 'UnknownPartnerScope',
            'description' => 'unknown-foreign-log',
            'created_at' => now(),
        ]);

        $resp = $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_action' => 'unknown',
                'filter_target_label' => 'UnknownPartnerScope',
                'hide_superadmin' => '0',
            ]
        )));

        $resp->assertOk();
        $descriptions = collect($resp->json('data'))->pluck('description')->all();
        $this->assertContains('unknown-home-log', $descriptions);
        $this->assertNotContains('unknown-foreign-log', $descriptions);
    }

    public function test_guest_and_unauthorized_users_cannot_access_logs_with_new_filter_params(): void
    {
        $params = array_merge($this->baseDataTableParams(), [
            'hide_superadmin' => '1',
            'hide_authorizations' => '1',
            'hide_integrations' => '1',
            'filter_action' => 'unknown',
            'filter_partner_id' => 'all',
        ]);

        Auth::logout();

        $this->get(route('admin.setting.logs', [
            'hide_superadmin' => '1',
            'filter_action' => 'unknown',
        ]))->assertRedirect();

        $this->getJson(route('settings.logs.data', $params))
            ->assertStatus(401);

        $denied = $this->createUserWithoutPermission('viewing.all.logs', $this->partner);
        $this->grantSettingsView((int) $denied->role_id);
        $this->actingAs($denied);

        $this->get(route('admin.setting.logs', ['hide_superadmin' => '1']))->assertForbidden();
        $this->getJson(route('settings.logs.data', $params))->assertForbidden();
    }

    private function assertAllSectionEndpointsSucceedForAuthorizedUser(bool $isSuperadmin = false): void
    {
        $this->get(route('admin.setting.logs'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'logs');

        $dataParams = $this->baseDataTableParams();
        if ($isSuperadmin) {
            $dataParams['filter_partner_id'] = 'all';
        }

        $this->getJson(route('settings.logs.data', $dataParams))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('admin.setting.logs', [
            'created_from' => now()->subMonth()->toDateString(),
            'filter_partner_id' => $isSuperadmin ? 'all' : null,
        ]))->assertOk();

        $filterParams = array_merge($this->baseDataTableParams(), [
            'created_from' => now()->subMonth()->toDateString(),
            'created_to' => now()->toDateString(),
            'filter_action' => AuditEvent::SettingsUpdated->value,
            'filter_author' => 'Иван',
            'filter_target_label' => 'Test',
            'hide_superadmin' => '1',
            'hide_authorizations' => '0',
        ]);
        if ($isSuperadmin) {
            $filterParams['filter_partner_id'] = (string) $this->partner->id;
        }

        $this->getJson(route('settings.logs.data', $filterParams))->assertOk();

        $this->getJson(route('settings.logs.data', array_merge(
            $this->baseDataTableParams(),
            [
                'filter_action' => 'unknown',
                'hide_superadmin' => '0',
                'hide_authorizations' => '1',
                'filter_partner_id' => $isSuperadmin ? 'all' : null,
            ]
        )))->assertOk();
    }

    /**
     * Все варианты query-параметров DataTables для smoke 200 (включая новые фильтры).
     *
     * @return list<array<string, mixed>>
     */
    private function allLogsDataFilterParamVariants(bool $isSuperadmin = false): array
    {
        $defaults = $this->defaultLogsFilterParams($isSuperadmin);

        $variants = [
            $defaults,
            array_merge($defaults, ['hide_superadmin' => '0']),
            array_merge($defaults, ['hide_authorizations' => '1']),
            array_merge($defaults, ['hide_integrations' => '1']),
            array_merge($defaults, [
                'hide_superadmin' => '0',
                'hide_authorizations' => '1',
            ]),
            array_merge($defaults, [
                'hide_superadmin' => '0',
                'hide_integrations' => '1',
            ]),
            array_merge($defaults, ['filter_action' => 'unknown']),
            array_merge($defaults, ['filter_level' => AuditLevel::Integration->value]),
            array_merge($defaults, [
                'filter_action' => AuditEvent::SettingsUpdated->value,
                'filter_author' => 'test',
                'filter_target_label' => 'Test',
            ]),
            array_merge($defaults, [
                'created_from' => now()->subYear()->toDateString(),
                'created_to' => now()->toDateString(),
            ]),
            array_merge($defaults, [
                'search' => ['value' => 'smoke'],
            ]),
        ];

        if ($isSuperadmin) {
            $variants[] = array_merge($defaults, [
                'filter_partner_id' => (string) $this->partner->id,
            ]);
            $variants[] = array_merge($defaults, [
                'filter_partner_id' => (string) $this->foreignPartner->id,
                'hide_superadmin' => '0',
            ]);
        } else {
            $variants[] = array_merge($defaults, [
                'filter_partner_id' => 'all',
            ]);
            $variants[] = array_merge($defaults, [
                'filter_partner_id' => (string) $this->foreignPartner->id,
            ]);
        }

        return $variants;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLogsFilterParams(bool $isSuperadmin = false): array
    {
        $params = array_merge($this->baseDataTableParams(), [
            'hide_superadmin' => '1',
            'hide_authorizations' => '0',
            'hide_integrations' => '0',
        ]);

        if ($isSuperadmin) {
            $params['filter_partner_id'] = 'all';
        }

        return $params;
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function allSectionRoutesPayload(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.setting.logs'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('settings.logs.data', $this->baseDataTableParams()),
            ],
            [
                'method' => 'GET',
                'url' => route('settings.logs.data', array_merge(
                    $this->baseDataTableParams(),
                    [
                        'filter_partner_id' => 'all',
                        'created_from' => now()->subYear()->toDateString(),
                        'filter_action' => AuditEvent::SettingsUpdated->value,
                        'hide_superadmin' => '1',
                        'hide_authorizations' => '0',
                    ]
                )),
            ],
            [
                'method' => 'GET',
                'url' => route('settings.logs.data', array_merge(
                    $this->baseDataTableParams(),
                    [
                        'filter_action' => 'unknown',
                        'hide_superadmin' => '0',
                        'hide_authorizations' => '1',
                    ]
                )),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.setting.logs', [
                    'hide_superadmin' => '1',
                    'hide_authorizations' => '1',
                    'filter_action' => 'unknown',
                ]),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseDataTableParams(): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ];
    }

    private function grantViewingAllLogs(int $roleId, ?int $partnerId = null): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $partnerId ?? $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId('viewing.all.logs'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function grantViewingAllLogsForPartner(int $roleId, int $partnerId): void
    {
        $this->grantViewingAllLogs($roleId, $partnerId);
    }

    private function grantSettingsView(int $roleId): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId('settings.view'),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function grantPermissionToCurrentRole(string $permissionName): void
    {
        DB::table('permission_role')->updateOrInsert(
            [
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permissionName),
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function superadminDataTableParams(): array
    {
        return array_merge($this->baseDataTableParams(), [
            'columns' => [
                ['name' => 'id'],
                ['name' => 'created_at'],
                ['name' => 'partner_title'],
                ['name' => 'action'],
                ['name' => 'author'],
                ['name' => 'target_label'],
                ['name' => 'description'],
            ],
        ]);
    }

    private function createPartnerLog(
        int $partnerId,
        string $description,
        ?string $createdAt = null,
        string $targetLabel = 'Test',
    ): void {
        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'author_id' => $this->user->id,
            'partner_id' => $partnerId,
            'target_type' => 'App\Models\Setting',
            'target_id' => $partnerId,
            'target_label' => $targetLabel,
            'description' => $description,
            'created_at' => $createdAt ? Carbon::parse($createdAt) : now(),
        ]);
    }
}
