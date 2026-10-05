<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use App\Models\PartnerLegalEntity;
use App\Models\PartnerWalletInvoice;
use App\Models\PartnerWalletTransaction;
use App\Services\PartnerWallet\PartnerWalletInvoiceService;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Payments\Platform\Concerns\PlatformPaymentsMethodTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * Счёт от ИП на /partner-wallet/checkout: право, юрлицо, PDF, баланс не меняется.
 *
 * @see /docs/documentation/partner-wallet.html#tbank-sbp
 */
final class PartnerWalletInvoiceFeatureTest extends CrmTestCase
{
    use PlatformPaymentsMethodTestHelpers;
    use TbankAcquiringTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
    }

    public function test_checkout_shows_invoice_only_when_partner_has_enabled_legal_entity(): void
    {
        $without = $this->get(route('partner.wallet.checkout', ['amount' => 1000]))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="walletCheckoutInvoiceIp"', $without);
        $this->assertStringContainsString('id="walletCheckoutSbp"', $without);

        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Выключенное',
            'tax_id' => '7701000001',
            'is_enabled' => false,
            'is_default' => false,
        ]);

        $disabled = $this->get(route('partner.wallet.checkout', ['amount' => 1000]))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="walletCheckoutInvoiceIp"', $disabled);

        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Покупатель',
            'tax_id' => '7701000002',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        $html = $this->get(route('partner.wallet.checkout', ['amount' => 1000]))->assertOk()->getContent();
        $this->assertStringContainsString('id="walletCheckoutInvoiceIp"', $html);
        $this->assertStringContainsString('value="invoice_ip"', $html);
        $this->assertStringContainsString('ООО Покупатель', $html);
        $this->assertStringContainsString('НДС не облагается', $html);
        $this->assertStringContainsString(route('partner.wallet.invoice'), $html);

        $this->grantNamedPermission($this->user, 'servicePayments.view');
        $service = $this->get(route('partner.payment.recharge'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="walletCheckoutInvoiceIp"', $service);
        $this->assertStringNotContainsString('value="invoice_ip"', $service);
    }

    public function test_invoice_pdf_is_saved_and_does_not_change_wallet_balance(): void
    {
        $this->partner->forceFill(['wallet_balance_cents' => 4242])->save();
        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Основное',
            'tax_id' => '7701000011',
            'kpp' => '770101001',
            'is_enabled' => true,
            'is_default' => true,
        ]);
        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Другое',
            'tax_id' => '7701000012',
            'is_enabled' => true,
            'is_default' => false,
        ]);

        $response = $this->post(route('partner.wallet.invoice'), [
            '_token' => csrf_token(),
            'amount' => 1000,
            'partner_id' => $this->partner->id,
            'payment_method' => 'invoice_ip',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));

        $invoice = PartnerWalletInvoice::query()->first();
        $this->assertNotNull($invoice);
        $this->assertSame((string) $invoice->id, (string) $invoice->number);
        $this->assertSame(100000, (int) $invoice->amount_cents);
        $this->assertSame('Пополнение баланса KidsCRM', (string) $invoice->item_name);
        $this->assertSame('НДС не облагается', (string) $invoice->vat_note);
        $this->assertSame('ООО Основное', (string) ($invoice->buyer['name'] ?? ''));
        $this->assertSame('7701000011', (string) ($invoice->buyer['inn'] ?? ''));
        $this->assertSame('770101001', (string) ($invoice->buyer['kpp'] ?? ''));
        $this->assertSame('40802810200010115063', (string) ($invoice->seller['account'] ?? ''));
        $this->assertSame('044525974', (string) ($invoice->seller['bik'] ?? ''));
        $this->assertStringContainsString('Пополнение баланса KidsCRM', (string) $invoice->payment_purpose);
        $this->assertStringContainsString('НДС не облагается', (string) $invoice->payment_purpose);
        $this->assertStringContainsString('№ '.$invoice->number, (string) $invoice->payment_purpose);
        $this->assertSame(4242, (int) $this->partner->fresh()->wallet_balance_cents);
        $this->assertSame(0, PartnerWalletTransaction::query()->where('partner_id', $this->partner->id)->count());
    }

    public function test_invoice_without_legal_entity_returns_field_error_and_saves_nothing(): void
    {
        $html = $this->from(route('partner.wallet.checkout', ['amount' => 1000]))
            ->followingRedirects()
            ->post(route('partner.wallet.invoice'), [
                '_token' => csrf_token(),
                'amount' => 1000,
                'partner_id' => $this->partner->id,
                'payment_method' => 'invoice_ip',
            ])
            ->assertOk()
            ->getContent();

        $this->assertFieldSlotContains($html, 'payment_method', PartnerWalletInvoiceService::NO_LEGAL_ENTITY_MESSAGE);
        $this->assertSame(0, PartnerWalletInvoice::query()->count());
    }

    public function test_invoice_without_permission_returns_field_error(): void
    {
        $actor = $this->userWithOnlyPermissions(['partnerWallet.view']);
        $this->actingAs($actor);
        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Без права',
            'tax_id' => '7701000021',
            'is_enabled' => true,
        ]);

        $checkout = $this->get(route('partner.wallet.checkout', ['amount' => 1000]))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="walletCheckoutInvoiceIp"', $checkout);

        $html = $this->from(route('partner.wallet.checkout', ['amount' => 1000]))
            ->followingRedirects()
            ->post(route('partner.wallet.invoice'), [
                '_token' => csrf_token(),
                'amount' => 1000,
                'partner_id' => $this->partner->id,
                'payment_method' => 'invoice_ip',
            ])
            ->assertOk()
            ->getContent();

        $this->assertFieldSlotContains($html, 'payment_method', 'Нет доступного способа оплаты.');
        $this->assertSame(0, PartnerWalletInvoice::query()->count());
    }

    public function test_invoice_for_other_partner_and_small_amount_show_field_errors(): void
    {
        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Своё',
            'tax_id' => '7701000031',
            'is_enabled' => true,
        ]);

        $foreign = $this->from(route('partner.wallet.checkout', ['amount' => 1000]))
            ->followingRedirects()
            ->post(route('partner.wallet.invoice'), [
                '_token' => csrf_token(),
                'amount' => 1000,
                'partner_id' => $this->foreignPartner->id,
                'payment_method' => 'invoice_ip',
            ])
            ->assertOk()
            ->getContent();

        $this->assertFieldSlotContains($foreign, 'partner_id', 'Нельзя выставить счёт для другой школы.');
        $this->assertSame(0, PartnerWalletInvoice::query()->count());

        $small = $this->from(route('partner.wallet.checkout', ['amount' => 1000]))
            ->followingRedirects()
            ->post(route('partner.wallet.invoice'), [
                '_token' => csrf_token(),
                'amount' => 0.5,
                'partner_id' => $this->partner->id,
                'payment_method' => 'invoice_ip',
            ])
            ->assertOk()
            ->getContent();

        $this->assertFieldSlotContains($small, 'amount', 'Сумма должна быть не меньше 1 ₽.');
        $this->assertSame(0, PartnerWalletInvoice::query()->count());
    }

    public function test_topup_does_not_accept_invoice_method(): void
    {
        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Счёт',
            'tax_id' => '7701000041',
            'is_enabled' => true,
        ]);

        $html = $this->from(route('partner.wallet.checkout', ['amount' => 100]))
            ->followingRedirects()
            ->post(route('partner.wallet.topup'), [
                '_token' => csrf_token(),
                'amount' => 100,
                'partner_id' => $this->partner->id,
                'payment_method' => 'invoice_ip',
            ])
            ->assertOk()
            ->getContent();

        $this->assertFieldSlotContains($html, 'payment_method', 'Некорректный способ оплаты.');
        $this->assertSame(0, PartnerWalletInvoice::query()->count());
        $this->assertSame(0, PartnerWalletTransaction::query()->where('partner_id', $this->partner->id)->count());
    }
}
