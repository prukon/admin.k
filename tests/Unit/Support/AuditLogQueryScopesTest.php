<?php

namespace Tests\Unit\Support;

use App\Enums\AuditEvent;
use App\Enums\AuditLevel;
use App\Models\MyLog;
use App\Support\AuditLogQueryScopes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Crm\CrmTestCase;

class AuditLogQueryScopesTest extends CrmTestCase
{
    use RefreshDatabase;

    #[Test]
    public function hide_authorizations_excludes_auth_login_event_rows(): void
    {
        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'partner_id' => $this->partner->id,
            'description' => 'event-login',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'partner_id' => $this->partner->id,
            'description' => 'settings-log',
            'created_at' => now(),
        ]);

        $visible = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->tap(fn ($q) => AuditLogQueryScopes::applyHideAuthorizations($q))
            ->pluck('description')
            ->all();

        $this->assertContains('settings-log', $visible);
        $this->assertNotContains('event-login', $visible);
    }

    #[Test]
    public function filter_action_unknown_includes_rows_without_or_with_invalid_event(): void
    {
        MyLog::query()->create([
            'event' => null,
            'partner_id' => $this->partner->id,
            'description' => 'missing-event',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => 'not.in.registry',
            'level' => AuditLevel::Info->value,
            'partner_id' => $this->partner->id,
            'description' => 'invalid-event',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'partner_id' => $this->partner->id,
            'description' => 'known-event',
            'created_at' => now(),
        ]);

        $descriptions = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->tap(fn ($q) => AuditLogQueryScopes::applyFilterAction($q, 'unknown'))
            ->pluck('description')
            ->all();

        $this->assertContains('missing-event', $descriptions);
        $this->assertContains('invalid-event', $descriptions);
        $this->assertNotContains('known-event', $descriptions);
    }

    #[Test]
    public function filter_action_event_value_matches_canonical_column(): void
    {
        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'partner_id' => $this->partner->id,
            'description' => 'by-event-value',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::TeamCreated->value,
            'level' => AuditEvent::TeamCreated->level()->value,
            'partner_id' => $this->partner->id,
            'description' => 'other-event',
            'created_at' => now(),
        ]);

        $descriptions = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->tap(fn ($q) => AuditLogQueryScopes::applyFilterAction($q, AuditEvent::SettingsUpdated->value))
            ->pluck('description')
            ->all();

        $this->assertSame(['by-event-value'], $descriptions);
    }

    #[Test]
    public function hide_integrations_excludes_integration_level_events(): void
    {
        MyLog::query()->create([
            'event' => AuditEvent::PaymentReceived->value,
            'level' => AuditEvent::PaymentReceived->level()->value,
            'partner_id' => $this->partner->id,
            'description' => 'payment',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'partner_id' => $this->partner->id,
            'description' => 'info-log',
            'created_at' => now(),
        ]);

        $visible = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->tap(fn ($q) => AuditLogQueryScopes::applyHideIntegrations($q))
            ->pluck('description')
            ->all();

        $this->assertContains('info-log', $visible);
        $this->assertNotContains('payment', $visible);
    }

    #[Test]
    public function category_scope_includes_only_events_for_category(): void
    {
        MyLog::query()->create([
            'event' => AuditEvent::PricingBulkApply->value,
            'level' => AuditEvent::PricingBulkApply->level()->value,
            'description' => 'pricing-event',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'description' => 'settings-event',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::UserUpdated->value,
            'level' => AuditEvent::UserUpdated->level()->value,
            'description' => 'user-event',
            'created_at' => now(),
        ]);

        $descriptions = MyLog::query()
            ->tap(fn ($q) => AuditLogQueryScopes::applyCategoryScope($q, 'pricing'))
            ->pluck('description')
            ->all();

        $this->assertContains('pricing-event', $descriptions);
        $this->assertNotContains('settings-event', $descriptions);
        $this->assertNotContains('user-event', $descriptions);
    }

    #[Test]
    public function filter_level_matches_level_column_only(): void
    {
        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditLevel::Security->value,
            'partner_id' => $this->partner->id,
            'description' => 'security-log',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditLevel::Info->value,
            'partner_id' => $this->partner->id,
            'description' => 'info-log',
            'created_at' => now(),
        ]);

        $descriptions = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->tap(fn ($q) => AuditLogQueryScopes::applyFilterLevel($q, AuditLevel::Security->value))
            ->pluck('description')
            ->all();

        $this->assertSame(['security-log'], $descriptions);
    }
}
