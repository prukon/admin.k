<?php

namespace Tests\Feature\Crm\Audit;

use App\Enums\AuditEvent;
use App\Enums\AuditLevel;
use App\Models\MyLog;
use App\Services\Audit\AuditLogger;
use App\Services\Audit\AuditContext;
use Tests\Feature\Crm\Audit\Concerns\InteractsWithMyLogsAudit;
use Tests\Feature\Crm\CrmTestCase;

/**
 * HTTP/feature-покрытие аудита my_logs только по колонкам event и level (без legacy type/action в фильтрах).
 */
final class AuditLogsEventOnlyFeatureTest extends CrmTestCase
{
    use InteractsWithMyLogsAudit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuditSession();
        $this->grantSettingsView();
        $this->grantViewingAllLogs();
    }

    public function test_settings_logs_data_action_column_shows_event_label_not_numeric_code(): void
    {
        $this->createEventLog(AuditEvent::TeamCreated, 'label-column-smoke');

        $row = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->auditLogsDataTableParams(),
            ['filter_action' => AuditEvent::TeamCreated->value]
        )))->json('data'))->firstWhere('description', 'label-column-smoke');

        $this->assertIsArray($row);
        $this->assertSame(AuditEvent::TeamCreated->label(), $row['action']);
    }

    public function test_settings_logs_numeric_filter_action_is_ignored_no_legacy_mapping(): void
    {
        $this->createLegacyOnlyLog(3, 31, 'legacy-team-without-event', [
            'target_label' => 'team-filter-smoke',
        ]);
        $this->createEventLog(AuditEvent::TeamCreated, 'event-team-created', [
            'target_label' => 'team-filter-smoke',
        ]);

        $response = $this->getJson(route('settings.logs.data', array_merge(
            $this->auditLogsDataTableParams(),
            [
                'filter_action' => '31',
                'filter_target_label' => 'team-filter-smoke',
            ]
        )))->assertOk();

        $descriptions = collect($response->json('data'))->pluck('description')->all();

        $this->assertContains('legacy-team-without-event', $descriptions);
        $this->assertContains('event-team-created', $descriptions);
    }

    public function test_settings_logs_filter_by_canonical_event_value(): void
    {
        $this->createEventLog(AuditEvent::PricingBulkApply, 'pricing-bulk-event');
        $this->createEventLog(AuditEvent::UserUpdated, 'user-updated-event');

        $descriptions = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->auditLogsDataTableParams(),
            ['filter_action' => AuditEvent::PricingBulkApply->value]
        )))->json('data'))->pluck('description')->all();

        $this->assertSame(['pricing-bulk-event'], $descriptions);
    }

    public function test_settings_logs_unknown_filter_matches_missing_and_invalid_event(): void
    {
        $this->createAuditLog([
            'event' => null,
            'description' => 'null-event-row',
        ]);
        $this->createAuditLog([
            'event' => 'custom.unknown.event',
            'level' => AuditLevel::Info->value,
            'description' => 'invalid-event-row',
        ]);
        $this->createEventLog(AuditEvent::SettingsUpdated, 'known-settings-event');

        $descriptions = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->auditLogsDataTableParams(),
            ['filter_action' => 'unknown']
        )))->json('data'))->pluck('description')->all();

        $this->assertContains('null-event-row', $descriptions);
        $this->assertContains('invalid-event-row', $descriptions);
        $this->assertNotContains('known-settings-event', $descriptions);
    }

    public function test_settings_logs_hide_authorizations_uses_auth_login_event_only(): void
    {
        $this->createEventLog(AuditEvent::SettingsUpdated, 'settings-visible', [
            'target_label' => 'HideAuthEventOnly',
        ]);
        $this->createEventLog(AuditEvent::AuthLogin, 'auth-hidden', [
            'target_label' => 'HideAuthEventOnly',
        ]);
        $this->createLegacyOnlyLog(4, 40, 'legacy-auth-without-event', [
            'target_label' => 'HideAuthEventOnly',
        ]);

        $descriptions = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->auditLogsDataTableParams(),
            [
                'filter_target_label' => 'HideAuthEventOnly',
                'hide_authorizations' => '1',
                'hide_superadmin' => '0',
            ]
        )))->json('data'))->pluck('description')->all();

        $this->assertContains('settings-visible', $descriptions);
        $this->assertNotContains('auth-hidden', $descriptions);
        $this->assertContains('legacy-auth-without-event', $descriptions);
    }

    public function test_settings_logs_hide_integrations_uses_event_and_level_only(): void
    {
        $this->createEventLog(AuditEvent::SettingsUpdated, 'info-visible', [
            'target_label' => 'HideIntegrationEventOnly',
        ]);
        $this->createEventLog(AuditEvent::PaymentReceived, 'payment-hidden', [
            'target_label' => 'HideIntegrationEventOnly',
        ]);
        $this->createLegacyOnlyLog(5, 50, 'legacy-payment-without-event', [
            'target_label' => 'HideIntegrationEventOnly',
        ]);

        $descriptions = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->auditLogsDataTableParams(),
            [
                'filter_target_label' => 'HideIntegrationEventOnly',
                'hide_integrations' => '1',
                'hide_superadmin' => '0',
            ]
        )))->json('data'))->pluck('description')->all();

        $this->assertContains('info-visible', $descriptions);
        $this->assertNotContains('payment-hidden', $descriptions);
        $this->assertContains('legacy-payment-without-event', $descriptions);
    }

    public function test_settings_logs_filter_level_uses_level_column(): void
    {
        $this->createEventLog(AuditEvent::AuthLogin, 'security-only', [
            'target_label' => 'LevelColumnOnly',
        ]);
        $this->createEventLog(AuditEvent::SettingsUpdated, 'info-other', [
            'target_label' => 'LevelColumnOnly',
        ]);

        $descriptions = collect($this->getJson(route('settings.logs.data', array_merge(
            $this->auditLogsDataTableParams(),
            [
                'filter_level' => AuditLevel::Security->value,
                'filter_target_label' => 'LevelColumnOnly',
            ]
        )))->json('data'))->pluck('description')->all();

        $this->assertSame(['security-only'], $descriptions);
    }

    public function test_audit_logger_writes_null_type_and_action(): void
    {
        $log = app(AuditLogger::class)->record(
            AuditEvent::SettingsUpdated,
            AuditContext::make('feature-test-null-legacy')
                ->withPartnerId((int) $this->partner->id)
                ->withAuthorId((int) $this->user->id)
        );

        $this->assertSame(AuditEvent::SettingsUpdated->value, $log->event);
        $this->assertNull($log->type);
        $this->assertNull($log->action);

        $this->assertDatabaseHas('my_logs', [
            'id' => $log->id,
            'event' => AuditEvent::SettingsUpdated->value,
            'type' => null,
            'action' => null,
        ]);
    }

    public function test_user_logs_data_includes_only_user_category_by_event(): void
    {
        $this->grantPermissionToRoleOnPartner('users.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::UserUpdated, 'user-category-event');
        $this->createEventLog(AuditEvent::TeamCreated, 'team-category-event');
        $this->createLegacyOnlyLog(2, 22, 'legacy-user-without-event');

        $descriptions = collect($this->getJson(route('logs.data.user', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('user-category-event', $descriptions);
        $this->assertNotContains('team-category-event', $descriptions);
        $this->assertNotContains('legacy-user-without-event', $descriptions);
    }

    public function test_team_logs_data_filters_by_team_category_event(): void
    {
        $this->grantPermissionToRoleOnPartner('groups.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::TeamCreated, 'team-modal-event');
        $this->createEventLog(AuditEvent::SettingsUpdated, 'settings-not-in-team-modal');
        $this->createLegacyOnlyLog(3, 31, 'legacy-team-modal');

        $descriptions = collect($this->getJson(route('logs.data.team', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('team-modal-event', $descriptions);
        $this->assertNotContains('settings-not-in-team-modal', $descriptions);
        $this->assertNotContains('legacy-team-modal', $descriptions);
    }

    public function test_location_logs_data_filters_by_location_category_event(): void
    {
        $this->grantPermissionToRoleOnPartner('locations.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::LocationCreated, 'location-modal-event');
        $this->createEventLog(AuditEvent::TeamCreated, 'team-not-in-location-modal');
        $this->createLegacyOnlyLog(87, 871, 'legacy-location-modal');

        $descriptions = collect($this->getJson(route('logs.data.location', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('location-modal-event', $descriptions);
        $this->assertNotContains('team-not-in-location-modal', $descriptions);
        $this->assertNotContains('legacy-location-modal', $descriptions);
    }

    public function test_district_logs_data_filters_by_district_category_event(): void
    {
        $this->grantPermissionToRoleOnPartner('districts.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::DistrictCreated, 'district-modal-event');
        $this->createEventLog(AuditEvent::LocationCreated, 'location-not-in-district-modal');
        $this->createLegacyOnlyLog(86, 861, 'legacy-district-modal');

        $descriptions = collect($this->getJson(route('logs.data.district', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('district-modal-event', $descriptions);
        $this->assertNotContains('location-not-in-district-modal', $descriptions);
        $this->assertNotContains('legacy-district-modal', $descriptions);
    }

    public function test_sport_type_logs_data_filters_by_sport_type_category_event(): void
    {
        $this->grantPermissionToRoleOnPartner('sport_types.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::SportTypeCreated, 'sport-type-modal-event');
        $this->createEventLog(AuditEvent::DistrictCreated, 'district-not-in-sport-type-modal');
        $this->createLegacyOnlyLog(88, 881, 'legacy-sport-type-modal');

        $descriptions = collect($this->getJson(route('logs.data.sport-type', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('sport-type-modal-event', $descriptions);
        $this->assertNotContains('district-not-in-sport-type-modal', $descriptions);
        $this->assertNotContains('legacy-sport-type-modal', $descriptions);
    }

    public function test_school_lead_logs_data_filters_by_school_lead_category_event(): void
    {
        $this->grantPermissionToRoleOnPartner('schoolLeads.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::SchoolLeadUpdated, 'school-lead-modal-event');
        $this->createEventLog(AuditEvent::SportTypeCreated, 'sport-type-not-in-school-lead-modal');
        $this->createLegacyOnlyLog(89, 891, 'legacy-school-lead-modal');

        $descriptions = collect($this->getJson(route('logs.data.school-lead', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('school-lead-modal-event', $descriptions);
        $this->assertNotContains('sport-type-not-in-school-lead-modal', $descriptions);
        $this->assertNotContains('legacy-school-lead-modal', $descriptions);
    }

    public function test_contract_template_logs_data_filters_by_contract_template_category_event(): void
    {
        $this->grantPermissionToRoleOnPartner('contracts.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::ContractTemplateCreated, 'contract-template-modal-event');
        $this->createEventLog(AuditEvent::SchoolLeadUpdated, 'school-lead-not-in-contract-template-modal');
        $this->createLegacyOnlyLog(501, 5011, 'legacy-contract-template-modal');

        $descriptions = collect($this->getJson(route('logs.data.contract-template', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('contract-template-modal-event', $descriptions);
        $this->assertNotContains('school-lead-not-in-contract-template-modal', $descriptions);
        $this->assertNotContains('legacy-contract-template-modal', $descriptions);
    }

    public function test_contract_logs_data_filters_by_contract_category_event(): void
    {
        $this->grantPermissionToRoleOnPartner('contracts.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::ContractCreated, 'contract-modal-event');
        $this->createEventLog(AuditEvent::ContractTemplateCreated, 'template-not-in-contract-modal');
        $this->createLegacyOnlyLog(500, 500, 'legacy-contract-modal');

        $descriptions = collect($this->getJson(route('logs.data.contract', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('contract-modal-event', $descriptions);
        $this->assertNotContains('template-not-in-contract-modal', $descriptions);
        $this->assertNotContains('legacy-contract-modal', $descriptions);
    }

    public function test_school_schedule_logs_data_filters_by_schedule_category_event(): void
    {
        $this->grantPermissionToRoleOnPartner('lessonPackages.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::ScheduleTrialRegistered, 'school-schedule-modal-event');
        $this->createEventLog(AuditEvent::ContractCreated, 'contract-not-in-school-schedule-modal');
        $this->createLegacyOnlyLog(60, 603, 'legacy-school-schedule-modal');

        $descriptions = collect($this->getJson(route('logs.data.school-schedule', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('school-schedule-modal-event', $descriptions);
        $this->assertNotContains('contract-not-in-school-schedule-modal', $descriptions);
        $this->assertNotContains('legacy-school-schedule-modal', $descriptions);
    }

    public function test_pricing_logs_data_filters_by_pricing_category_event(): void
    {
        $this->grantPermissionToRoleOnPartner('setPrices.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::PricingBulkApply, 'pricing-modal-event');
        $this->createEventLog(AuditEvent::UserUpdated, 'user-not-in-pricing-modal');
        $this->createLegacyOnlyLog(1, 11, 'legacy-pricing-modal');

        $descriptions = collect($this->getJson(route('logs.data.settingPrice', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('pricing-modal-event', $descriptions);
        $this->assertNotContains('user-not-in-pricing-modal', $descriptions);
        $this->assertNotContains('legacy-pricing-modal', $descriptions);
    }

    public function test_schedule_logs_data_returns_only_schedule_category_events(): void
    {
        $this->grantPermissionToRoleOnPartner('schedule.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::ScheduleUserRangeUpdated, 'schedule-modal-event');
        $this->createEventLog(AuditEvent::UserCreated, 'user-not-in-schedule-modal');

        $descriptions = collect($this->getJson(route('logs.data.schedule', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('schedule-modal-event', $descriptions);
        $this->assertNotContains('user-not-in-schedule-modal', $descriptions);
    }

    public function test_rules_logs_data_returns_only_role_category_events(): void
    {
        $this->grantPermissionToRoleOnPartner('settings.roles.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::RoleCreated, 'role-modal-event');
        $this->createEventLog(AuditEvent::SettingsUpdated, 'settings-not-in-role-modal');
        $this->createLegacyOnlyLog(700, 710, 'legacy-role-modal');

        $descriptions = collect($this->getJson(route('logs.data.rule', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('role-modal-event', $descriptions);
        $this->assertNotContains('settings-not-in-role-modal', $descriptions);
        $this->assertNotContains('legacy-role-modal', $descriptions);
    }

    public function test_partner_logs_data_returns_only_partner_category_events(): void
    {
        $this->asSuperadmin();
        $this->user->unsetRelation('role');
        $this->grantPermissionToRoleOnPartner('partner.view', (int) $this->user->role_id);

        $this->createEventLog(AuditEvent::PartnerUpdated, 'partner-modal-event', [
            'partner_id' => $this->partner->id,
        ]);
        $this->createEventLog(AuditEvent::TeamCreated, 'team-not-in-partner-modal');

        $descriptions = collect($this->getJson(route('logs.data.partner', $this->auditLogsDataTableParams()))
            ->json('data'))->pluck('description')->all();

        $this->assertContains('partner-modal-event', $descriptions);
        $this->assertNotContains('team-not-in-partner-modal', $descriptions);
    }
}
