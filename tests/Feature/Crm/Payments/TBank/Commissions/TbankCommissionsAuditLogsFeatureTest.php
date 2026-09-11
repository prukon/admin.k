<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank\Commissions;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\Setting;
use App\Models\TinkoffCommissionRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * @see TbankCommissionsPageFullAccessFeatureTest
 */
final class TbankCommissionsAuditLogsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_logs_data_returns_200_with_settings_commission(): void
    {
        $this->asSuperadmin();

        $this->getJson(route('logs.data.tbank-commission', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_settings_commission(): void
    {
        $actor = $this->createUserWithoutPermission('settings.commission', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('logs.data.tbank-commission', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_index_renders_history_button_and_modal(): void
    {
        $this->asSuperadmin();

        $this->get(route('admin.setting.tbankCommissions'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('>История</span>', false)
            ->assertSee('fa-clock-rotate-left', false)
            ->assertSee('showLogModal', false);
    }

    public function test_store_writes_tbank_commission_created_log(): void
    {
        $this->asSuperadmin();

        $this->post(route('admin.setting.tbankCommissions.store'), $this->validPayload([
            'method' => 'sbp',
            'auto_payout_enabled' => 1,
            'auto_payout_delay_hours' => 12,
        ]))->assertRedirect(route('admin.setting.tbankCommissions'));

        $log = $this->latestLog(AuditEvent::TbankCommissionCreated);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::TbankCommissionCreated->level(), $log->level);
        $this->assertSame($this->partner->id, (int) $log->partner_id);
        $this->assertStringContainsString('Партнёр: '.$this->partner->title, (string) $log->description);
        $this->assertStringContainsString('Метод: СБП', (string) $log->description);
        $this->assertStringContainsString('Эквайринг: 2.5% / мин. 0', (string) $log->description);
        $this->assertStringContainsString('Автовыплата: да, 12 ч', (string) $log->description);
        $this->assertSame($this->partner->title.' / СБП', $log->target_label);
    }

    public function test_update_writes_tbank_commission_updated_log_with_field_diffs(): void
    {
        $this->asSuperadmin();

        $rule = TinkoffCommissionRule::create($this->validPayload([
            'platform_percent' => 3.0,
            'is_enabled' => true,
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 0,
        ]));

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $this->validPayload([
            'platform_percent' => 8.1,
            'is_enabled' => 0,
            'auto_payout_enabled' => 1,
            'auto_payout_delay_hours' => 6,
        ]))->assertRedirect(route('admin.setting.tbankCommissions'));

        $log = $this->latestLog(AuditEvent::TbankCommissionUpdated);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Комиссия платформы: 3% / мин. 0 → 8.1% / мин. 0', (string) $log->description);
        $this->assertStringContainsString('Автовыплата: нет → да, 6 ч', (string) $log->description);
        $this->assertStringContainsString('Активность: да → нет', (string) $log->description);
    }

    public function test_update_without_changes_does_not_write_log(): void
    {
        $this->asSuperadmin();

        $rule = TinkoffCommissionRule::create($this->validPayload([
            'is_enabled' => true,
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 0,
        ]));

        $beforeCount = MyLog::query()
            ->where('event', AuditEvent::TbankCommissionUpdated->value)
            ->count();

        $this->put(route('admin.setting.tbankCommissions.update', ['id' => $rule->id]), $this->validPayload([
            'is_enabled' => 1,
            'auto_payout_enabled' => 0,
            'auto_payout_delay_hours' => 0,
        ]))->assertRedirect(route('admin.setting.tbankCommissions'));

        $afterCount = MyLog::query()
            ->where('event', AuditEvent::TbankCommissionUpdated->value)
            ->count();

        $this->assertSame($beforeCount, $afterCount);
    }

    public function test_destroy_writes_tbank_commission_deleted_log(): void
    {
        $this->asSuperadmin();

        $rule = TinkoffCommissionRule::create($this->validPayload([
            'method' => 'tpay',
        ]));

        $this->delete(route('admin.setting.tbankCommissions.destroy', ['id' => $rule->id]))
            ->assertRedirect();

        $log = $this->latestLog(AuditEvent::TbankCommissionDeleted);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::TbankCommissionDeleted->level(), $log->level);
        $this->assertStringContainsString('Правило удалено.', (string) $log->description);
        $this->assertStringContainsString('Метод: T‑Pay', (string) $log->description);
        $this->assertSame($this->partner->title.' / T‑Pay', $log->target_label);
    }

    public function test_payout_settings_write_log_only_when_interval_changes(): void
    {
        $this->asSuperadmin();
        Setting::setTinkoffPayoutScheduledIntervalMinutes(10);

        $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_scheduled_interval_minutes' => 10,
        ])->assertRedirect(route('admin.setting.tbankCommissions'));

        $this->assertSame(0, MyLog::query()
            ->where('event', AuditEvent::TbankCommissionPayoutSettingsUpdated->value)
            ->count());

        $this->post(route('admin.setting.tbankCommissions.payoutSettings'), [
            'payout_scheduled_interval_minutes' => 15,
        ])->assertRedirect(route('admin.setting.tbankCommissions'));

        $log = MyLog::query()
            ->where('event', AuditEvent::TbankCommissionPayoutSettingsUpdated->value)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Интервал запуска джобы (мин): 10 → 15', (string) $log->description);
    }

    public function test_columns_settings_do_not_write_commission_audit_log(): void
    {
        $this->asSuperadmin();

        $before = MyLog::query()
            ->whereIn('event', AuditEvent::eventValuesForCategory('tbank_commission'))
            ->count();

        $this->postJson(route('admin.setting.tbankCommissions.columns-settings.save'), [
            'columns' => ['partner_title' => true, 'method' => false],
        ])->assertOk();

        $after = MyLog::query()
            ->whereIn('event', AuditEvent::eventValuesForCategory('tbank_commission'))
            ->count();

        $this->assertSame($before, $after);
    }

    public function test_logs_data_returns_written_event_and_excludes_other_categories(): void
    {
        $this->asSuperadmin();

        $this->post(route('admin.setting.tbankCommissions.store'), $this->validPayload([
            'method' => 'card',
        ]))->assertRedirect();

        MyLog::query()->create([
            'event' => AuditEvent::SettingsUpdated->value,
            'level' => AuditEvent::SettingsUpdated->level()->value,
            'description' => 'settings-not-in-tbank-modal',
            'author_id' => $this->user->id,
            'partner_id' => $this->partner->id,
            'created_at' => now(),
        ]);

        $descriptions = collect($this->getJson(route('logs.data.tbank-commission', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'Метод: Карты')),
            'Ожидалась запись tbank_commission.created в logs-data.'
        );
        $this->assertNotContains('settings-not-in-tbank-modal', $descriptions);
    }

    public function test_admin_with_permission_sees_only_current_partner_logs(): void
    {
        $actor = $this->createUserWithoutPermission('settings.commission', $this->partner);
        $this->grantSettingsCommission($actor);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->post(route('admin.setting.tbankCommissions.store'), $this->validPayload([
            'method' => 'sbp',
        ]))->assertRedirect();

        MyLog::query()->create([
            'event' => AuditEvent::TbankCommissionCreated->value,
            'level' => AuditEvent::TbankCommissionCreated->level()->value,
            'description' => 'foreign-partner-commission-log',
            'author_id' => $this->foreignUser->id,
            'partner_id' => $this->foreignPartner->id,
            'created_at' => now(),
        ]);

        $descriptions = collect($this->getJson(route('logs.data.tbank-commission', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'Метод: СБП')),
            'Ожидалась запись текущего партнёра в logs-data.'
        );
        $this->assertNotContains('foreign-partner-commission-log', $descriptions);

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $superadminDescriptions = collect($this->getJson(route('logs.data.tbank-commission', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertContains('foreign-partner-commission-log', $superadminDescriptions);
    }

    private function grantSettingsCommission(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('settings.commission'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function latestLog(AuditEvent $event): ?MyLog
    {
        return MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', $event->value)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'partner_id' => $this->partner->id,
            'method' => 'card',
            'acquiring_percent' => 2.5,
            'acquiring_min_fixed' => 0,
            'payout_percent' => 1.2,
            'payout_min_fixed' => 0,
            'platform_percent' => 3.0,
            'platform_min_fixed' => 0,
            'min_fixed' => 0,
            'is_enabled' => 1,
            'auto_payout_enabled' => 0,
            'auto_payout_delay_hours' => 0,
        ], $overrides);
    }
}
