<?php

namespace Tests\Unit\Enums;

use App\Enums\AuditEvent;
use App\Enums\AuditLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditEventTest extends TestCase
{
    #[Test]
    public function every_case_has_unique_event_value(): void
    {
        $values = array_map(static fn (AuditEvent $case) => $case->value, AuditEvent::cases());

        $this->assertSame(count($values), count(array_unique($values)));
    }

    #[Test]
    public function legacy_action_labels_include_recently_missing_codes(): void
    {
        $labels = AuditEvent::legacyActionLabels();

        $this->assertArrayHasKey(741, $labels);
        $this->assertArrayHasKey(742, $labels);
        $this->assertArrayHasKey(602, $labels);
        $this->assertArrayHasKey(93, $labels);
        $this->assertArrayHasKey(461, $labels);
        $this->assertArrayHasKey(61, $labels);
    }

    #[Test]
    public function my_log_action_labels_delegates_to_registry(): void
    {
        $this->assertSame(
            AuditEvent::legacyActionLabels(),
            \App\Models\MyLog::actionLabels()
        );
    }

    #[Test]
    #[DataProvider('legacyDisambiguationProvider')]
    public function it_disambiguates_legacy_type_and_action_pairs(
        ?int $type,
        int $action,
        AuditEvent $expected,
    ): void {
        $this->assertSame($expected, AuditEvent::fromLegacy($type, $action));
    }

    /**
     * @return array<string, array{0: ?int, 1: int, 2: AuditEvent}>
     */
    public static function legacyDisambiguationProvider(): array
    {
        return [
            'partner settings (type 2, action 80)' => [2, 80, AuditEvent::PartnerSettingsUpdated],
            'partner updated (type 80, action 80)' => [80, 80, AuditEvent::PartnerUpdated],
            'role permission granted' => [700, 741, AuditEvent::RolePermissionGranted],
            'auth login' => [4, 40, AuditEvent::AuthLogin],
        ];
    }

    #[Test]
    #[DataProvider('legacyActionMappingProvider')]
    public function it_maps_legacy_actions_to_events(int $action, AuditEvent $expected): void
    {
        $this->assertSame($expected, AuditEvent::fromLegacy(null, $action));
    }

    /**
     * @return array<string, array{0: int, 1: AuditEvent}>
     */
    public static function legacyActionMappingProvider(): array
    {
        return [
            'user created' => [21, AuditEvent::UserCreated],
            'settings updated' => [70, AuditEvent::SettingsUpdated],
            'contract signed' => [520, AuditEvent::ContractSigned],
            'schedule slot skipped' => [461, AuditEvent::ScheduleSlotOccurrenceSkipped],
            'payout schedule' => [61, AuditEvent::PaymentPayoutScheduleChanged],
        ];
    }

    #[Test]
    public function resolve_label_prefers_event_column(): void
    {
        $label = AuditEvent::resolveLabel('user.created', 999, 999);

        $this->assertSame('Создание пользователя', $label);
    }

    #[Test]
    public function resolve_label_falls_back_to_legacy(): void
    {
        $label = AuditEvent::resolveLabel(null, 700, 741);

        $this->assertSame('Назначение права роли', $label);
    }

    #[Test]
    public function resolve_label_returns_unknown_for_unmapped_legacy(): void
    {
        $label = AuditEvent::resolveLabel(null, 1, 99999);

        $this->assertSame('Неизвестное событие', $label);
    }

    #[Test]
    public function auth_login_is_security_and_login_event(): void
    {
        $event = AuditEvent::AuthLogin;

        $this->assertSame(AuditLevel::Security, $event->level());
        $this->assertTrue($event->isLoginEvent());
        $this->assertSame('auth', $event->category());
    }

    #[Test]
    public function payment_received_is_integration(): void
    {
        $this->assertSame(AuditLevel::Integration, AuditEvent::PaymentReceived->level());
    }

    #[Test]
    public function event_values_for_legacy_type_include_pricing_and_settings_for_type_one(): void
    {
        $events = AuditEvent::eventValuesForLegacyType(1);

        $this->assertContains(AuditEvent::PricingBulkApply->value, $events);
        $this->assertContains(AuditEvent::SettingsUpdated->value, $events);
    }

    #[Test]
    public function category_pricing_excludes_settings_event(): void
    {
        $pricing = AuditEvent::eventValuesForCategory('pricing');
        $settings = AuditEvent::eventValuesForCategory('settings');

        $this->assertContains(AuditEvent::PricingBulkApply->value, $pricing);
        $this->assertNotContains(AuditEvent::SettingsUpdated->value, $pricing);
        $this->assertContains(AuditEvent::SettingsUpdated->value, $settings);
    }

    #[Test]
    public function legacy_type_and_action_match_design_for_user_updated(): void
    {
        $event = AuditEvent::UserUpdated;

        $this->assertSame(2, $event->legacyType());
        $this->assertSame(22, $event->legacyAction());
        $this->assertSame('user.updated', $event->value);
    }
}
