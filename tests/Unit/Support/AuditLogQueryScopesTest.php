<?php

namespace Tests\Unit\Support;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Support\AuditLogQueryScopes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Crm\CrmTestCase;

class AuditLogQueryScopesTest extends CrmTestCase
{
    use RefreshDatabase;

    #[Test]
    public function hide_authorizations_excludes_legacy_and_event_login_rows(): void
    {
        MyLog::query()->create([
            'type' => 4,
            'action' => 40,
            'partner_id' => $this->partner->id,
            'description' => 'legacy-login',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::AuthLogin->value,
            'level' => AuditEvent::AuthLogin->level()->value,
            'type' => 4,
            'action' => 40,
            'partner_id' => $this->partner->id,
            'description' => 'event-login',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'type' => 1,
            'action' => 70,
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
        $this->assertNotContains('legacy-login', $visible);
        $this->assertNotContains('event-login', $visible);
    }

    #[Test]
    public function filter_action_unknown_includes_unmapped_action_and_invalid_event(): void
    {
        MyLog::query()->create([
            'type' => 1,
            'action' => 99999,
            'partner_id' => $this->partner->id,
            'description' => 'unknown-action',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => 'not.in.registry',
            'level' => 'info',
            'type' => 1,
            'action' => 70,
            'partner_id' => $this->partner->id,
            'description' => 'invalid-event',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'type' => 1,
            'action' => 70,
            'partner_id' => $this->partner->id,
            'description' => 'known-event',
            'created_at' => now(),
        ]);

        $descriptions = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->tap(fn ($q) => AuditLogQueryScopes::applyFilterAction($q, 'unknown'))
            ->pluck('description')
            ->all();

        $this->assertContains('unknown-action', $descriptions);
        $this->assertContains('invalid-event', $descriptions);
        $this->assertNotContains('known-event', $descriptions);
    }

    #[Test]
    public function filter_action_legacy_code_matches_backfilled_event_column(): void
    {
        MyLog::query()->create([
            'event' => AuditEvent::TeamCreated->value,
            'level' => AuditEvent::TeamCreated->level()->value,
            'type' => 3,
            'action' => 31,
            'partner_id' => $this->partner->id,
            'description' => 'team-created',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'type' => 1,
            'action' => 70,
            'partner_id' => $this->partner->id,
            'description' => 'settings-only',
            'created_at' => now(),
        ]);

        $descriptions = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->tap(fn ($q) => AuditLogQueryScopes::applyFilterAction($q, '31'))
            ->pluck('description')
            ->all();

        $this->assertSame(['team-created'], $descriptions);
    }

    #[Test]
    public function filter_action_event_value_matches_canonical_column(): void
    {
        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'type' => 1,
            'action' => 70,
            'partner_id' => $this->partner->id,
            'description' => 'by-event-value',
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
    public function hide_integrations_excludes_payment_and_contract_webhook_rows(): void
    {
        MyLog::query()->create([
            'event' => AuditEvent::PaymentReceived->value,
            'level' => AuditEvent::PaymentReceived->level()->value,
            'type' => 5,
            'action' => 50,
            'partner_id' => $this->partner->id,
            'description' => 'payment',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'type' => 5,
            'action' => 50,
            'partner_id' => $this->partner->id,
            'description' => 'legacy-payment',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'type' => 1,
            'action' => 70,
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
        $this->assertNotContains('legacy-payment', $visible);
    }

    #[Test]
    public function filter_action_legacy_numeric_code_still_supported(): void
    {
        MyLog::query()->create([
            'type' => 3,
            'action' => 31,
            'partner_id' => $this->partner->id,
            'description' => 'legacy-team-created',
            'created_at' => now(),
        ]);

        $descriptions = MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->tap(fn ($q) => AuditLogQueryScopes::applyFilterAction($q, '31'))
            ->pluck('description')
            ->all();

        $this->assertSame(['legacy-team-created'], $descriptions);
    }

    #[Test]
    public function category_scope_includes_event_only_rows_for_pricing_modal(): void
    {
        MyLog::query()->create([
            'event' => AuditEvent::PricingBulkApply->value,
            'level' => AuditEvent::PricingBulkApply->level()->value,
            'description' => 'pricing-event-only',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'type' => 1,
            'action' => 70,
            'description' => 'settings-legacy-same-type',
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'type' => 2,
            'action' => 22,
            'description' => 'user-legacy-other-category',
            'created_at' => now(),
        ]);

        $descriptions = MyLog::query()
            ->tap(fn ($q) => AuditLogQueryScopes::applyCategoryScope($q, 'pricing'))
            ->pluck('description')
            ->all();

        $this->assertContains('pricing-event-only', $descriptions);
        $this->assertNotContains('settings-legacy-same-type', $descriptions);
        $this->assertNotContains('user-legacy-other-category', $descriptions);
    }
}
