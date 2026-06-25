<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\PaymentSystem;
use App\Services\Tinkoff\TbankTerminalConfig;
use RuntimeException;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Глобальный терминал T‑Bank: чтение конфигурации без fallback на .env.
 */
final class TbankTerminalConfigFeatureTest extends CrmTestCase
{
    public function test_global_record_returns_null_when_not_configured(): void
    {
        PaymentSystem::query()->whereNull('partner_id')->where('name', 'tbank')->delete();

        $this->assertNull(TbankTerminalConfig::globalRecord());
    }

    public function test_is_globally_active_false_when_missing_keys(): void
    {
        PaymentSystem::query()->updateOrCreate(
            ['partner_id' => null, 'name' => 'tbank'],
            [
                'is_enabled' => true,
                'test_mode' => true,
                'settings' => [
                    'terminal_key' => 'only-eacq',
                    'token_password' => 'pwd',
                ],
            ]
        );

        $this->assertFalse(TbankTerminalConfig::isGloballyActive());
    }

    public function test_is_globally_active_false_when_disabled(): void
    {
        $this->seedGlobalTbank([], ['is_enabled' => false]);

        $this->assertFalse(TbankTerminalConfig::isGloballyActive());
    }

    public function test_is_globally_active_true_when_fully_configured_and_enabled(): void
    {
        $this->seedGlobalTbank();

        $this->assertTrue(TbankTerminalConfig::isGloballyActive());
    }

    public function test_payment_config_returns_eacq_credentials_and_urls(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'EACQ_KEY',
            'token_password' => 'EACQ_PWD',
        ], ['test_mode' => true]);

        $cfg = TbankTerminalConfig::paymentConfig();

        $this->assertSame('EACQ_KEY', $cfg['terminal_key']);
        $this->assertSame('EACQ_PWD', $cfg['password']);
        $this->assertSame('https://rest-api-test.tinkoff.ru', $cfg['base_url']);
        $this->assertStringContainsString('/webhooks/tinkoff/payments', $cfg['notify_url']);
    }

    public function test_e2c_config_returns_payout_credentials(): void
    {
        $this->seedGlobalTbank([
            'e2c_terminal_key' => 'E2C_KEY',
            'e2c_token_password' => 'E2C_PWD',
        ]);

        $cfg = TbankTerminalConfig::e2cConfig();

        $this->assertSame('E2C_KEY', $cfg['terminal_key']);
        $this->assertSame('E2C_PWD', $cfg['password']);
    }

    public function test_payment_config_throws_when_terminal_not_configured(): void
    {
        PaymentSystem::query()->whereNull('partner_id')->where('name', 'tbank')->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('T‑Bank terminal is not configured');

        TbankTerminalConfig::paymentConfig();
    }

    public function test_try_payment_config_returns_null_instead_of_throwing(): void
    {
        PaymentSystem::query()->whereNull('partner_id')->where('name', 'tbank')->delete();

        $this->assertNull(TbankTerminalConfig::tryPaymentConfig());
        $this->assertNull(TbankTerminalConfig::tryE2cConfig());
    }

    public function test_per_partner_tbank_record_does_not_affect_global_config(): void
    {
        PaymentSystem::factory()->tbank()->create([
            'partner_id' => $this->partner->id,
            'settings' => [
                'terminal_key' => 'PARTNER_TERM',
                'token_password' => 'PARTNER_PWD',
                'e2c_terminal_key' => 'PARTNER_E2C',
                'e2c_token_password' => 'PARTNER_E2C_PWD',
            ],
        ]);

        PaymentSystem::query()->whereNull('partner_id')->where('name', 'tbank')->delete();

        $this->assertNull(TbankTerminalConfig::globalRecord());
        $this->assertFalse(TbankTerminalConfig::isGloballyActive());
    }
}
