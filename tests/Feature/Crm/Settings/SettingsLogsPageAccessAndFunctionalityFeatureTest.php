<?php

namespace Tests\Feature\Crm\Settings;

use App\Enums\AuditEvent;
use App\Enums\AuditLevel;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Audit\Concerns\InteractsWithMyLogsAudit;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа вкладки «Настройки → Логи» и smoke HTTP 200 для всего функционала страницы.
 */
final class SettingsLogsPageAccessAndFunctionalityFeatureTest extends CrmTestCase
{
    use InteractsWithMyLogsAudit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuditSession();
    }

    public function test_guest_cannot_access_settings_logs_page_or_data(): void
    {
        Auth::logout();

        foreach ($this->settingsLogsSectionRoutes() as $route) {
            $response = $this->call(
                $route['method'],
                $route['url'],
                [],
                [],
                [],
                $route['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Guest: {$route['method']} {$route['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_viewing_all_logs_gets_403_on_page_and_data(): void
    {
        $actor = $this->createUserWithoutPermission('viewing.all.logs', $this->partner);
        $this->grantSettingsView((int) $actor->role_id);
        $this->actingAs($actor);

        foreach ($this->settingsLogsSectionRoutes() as $route) {
            $this->call(
                $route['method'],
                $route['url'],
                [],
                [],
                [],
                $route['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            )->assertForbidden();
        }
    }

    public function test_user_without_settings_view_gets_403_on_page_but_logs_data_ok(): void
    {
        $actor = $this->createUserWithoutPermission('settings.view', $this->partner);
        $this->grantViewingAllLogs((int) $actor->role_id);
        $this->actingAs($actor);

        $this->get(route('admin.setting.logs'))->assertForbidden();

        $this->getJson(route('settings.logs.data', $this->auditLogsDataTableParams()))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_admin_with_both_permissions_page_and_all_section_routes_return_200(): void
    {
        $actor = $this->createUserWithoutPermission('viewing.all.logs', $this->partner);
        $this->grantViewingAllLogs((int) $actor->role_id);
        $this->grantSettingsView((int) $actor->role_id);
        $this->actingAs($actor);

        $this->assertSettingsLogsSectionReturnsOk(isSuperadmin: false);
    }

    public function test_superadmin_with_permissions_page_and_all_section_routes_return_200(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantSettingsView();
        $this->grantViewingAllLogs();

        $this->assertSettingsLogsSectionReturnsOk(isSuperadmin: true);
    }

    public function test_admin_every_logs_filter_variant_returns_200(): void
    {
        $this->grantSettingsView();
        $this->grantViewingAllLogs();

        $this->createEventLog(AuditEvent::SettingsUpdated, 'filter-variant-smoke');

        foreach ($this->settingsLogsPageFilterVariants(isSuperadmin: false) as $index => $params) {
            $response = $this->getJson(route('settings.logs.data', $params));
            $response->assertOk("filter variant #{$index} must return 200");
            $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_superadmin_every_logs_filter_variant_returns_200(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantSettingsView();
        $this->grantViewingAllLogs();

        $this->createEventLog(AuditEvent::SettingsUpdated, 'superadmin-filter-smoke', [
            'partner_id' => $this->partner->id,
        ]);

        foreach ($this->settingsLogsPageFilterVariants(isSuperadmin: true) as $index => $params) {
            $response = $this->getJson(route('settings.logs.data', $params));
            $response->assertOk("superadmin filter variant #{$index} must return 200");
            $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
        }
    }

    public function test_logs_page_renders_all_audit_filter_controls(): void
    {
        $this->grantSettingsView();
        $this->grantViewingAllLogs();

        $html = $this->get(route('admin.setting.logs'))->assertOk()->getContent();

        $this->assertStringContainsString('id="settings-logs-filter-action"', $html);
        $this->assertStringContainsString('id="settings-logs-filter-level"', $html);
        $this->assertStringContainsString('id="settings-logs-filter-hide-authorizations"', $html);
        $this->assertStringContainsString('id="settings-logs-filter-hide-integrations"', $html);
        $this->assertStringContainsString('id="settings-logs-filter-hide-superadmin"', $html);
        $this->assertStringContainsString('value="unknown"', $html);
        $this->assertStringContainsString('value="'.AuditEvent::SettingsUpdated->value.'"', $html);
        $this->assertStringContainsString('value="'.AuditLevel::Info->value.'"', $html);
        $this->assertStringContainsString('value="'.AuditLevel::Security->value.'"', $html);
        $this->assertStringContainsString('value="'.AuditLevel::Integration->value.'"', $html);
        $this->assertStringContainsString('id="settingsLogsTable"', $html);
    }

    public function test_logs_page_with_query_string_prefill_returns_200(): void
    {
        $this->grantSettingsView();
        $this->grantViewingAllLogs();

        $this->get(route('admin.setting.logs', [
            'created_from' => now()->subMonth()->toDateString(),
            'created_to' => now()->toDateString(),
            'filter_action' => AuditEvent::SettingsUpdated->value,
            'filter_level' => AuditLevel::Info->value,
            'hide_superadmin' => '1',
            'hide_authorizations' => '1',
            'hide_integrations' => '1',
            'filter_author' => 'Иван',
            'filter_target_label' => 'Test',
        ]))->assertOk();
    }

    public function test_registration_activity_creates_log_with_null_legacy_columns(): void
    {
        $this->grantSettingsView();
        $this->grantPermissionToRoleOnPartner('settings.registration.manage', (int) $this->user->role_id);

        $this->patchJson(route('registrationActivity'), [
            'isRegistrationActivity' => false,
        ])->assertOk();

        $this->assertDatabaseHas('my_logs', [
            'partner_id' => $this->partner->id,
            'event' => AuditEvent::SettingsUpdated->value,
            'type' => null,
            'action' => null,
        ]);
    }

    private function assertSettingsLogsSectionReturnsOk(bool $isSuperadmin): void
    {
        foreach ($this->settingsLogsSectionRoutes($isSuperadmin) as $route) {
            $response = $this->call(
                $route['method'],
                $route['url'],
                [],
                [],
                [],
                $route['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $response->assertOk();
        }

        $this->get(route('admin.setting.logs'))
            ->assertOk()
            ->assertViewIs('admin.setting.index')
            ->assertViewHas('activeTab', 'logs');
    }
}
