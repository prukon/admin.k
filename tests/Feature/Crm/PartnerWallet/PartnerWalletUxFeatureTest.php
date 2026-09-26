<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use App\Models\PartnerLegalEntity;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;

/**
 * Разметка /partner-wallet и правила «если X, то по умолчанию Y»:
 * баланс текущей школы, GET на checkout, пустая сумма, ошибки под полями, @can в сайдбаре.
 * Hidden partner_id и CSRF — на POST-формах /partner-wallet/checkout.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/partner-wallet.html
 */
final class PartnerWalletUxFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_wallet_page_shows_cents_balance_and_empty_amount_for_current_partner(): void
    {
        $this->asAdmin();
        $this->partner->forceFill(['wallet_balance_cents' => 12345])->save();
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 99900])->save();

        $html = $this->get(route('partner.wallet'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="walletTopupForm"', $html);
        $this->assertStringContainsString('id="walletTopupAmount"', $html);
        $this->assertStringContainsString('/partner-wallet/history', $html);
        $this->assertStringNotContainsString('id="walletTxTable"', $html);
        $history = $this->get(route('partner.wallet.history'))->assertOk()->getContent();
        $this->assertStringContainsString('id="walletTxTable"', $history);
        $this->assertWalletAmountFormGoesToCheckout($html);
        $this->assertStringContainsString('123,45', $html);
        $this->assertStringNotContainsString('999,00', $html);
        $this->assertStringNotContainsString('wallet_balance ??', $html);

        $amountPos = strpos($html, 'id="walletTopupAmount"');
        $submitPos = strpos($html, 'id="topupBtn"');
        $this->assertNotFalse($amountPos);
        $this->assertNotFalse($submitPos);
        $this->assertTrue($amountPos < $submitPos, 'Поле суммы должно быть выше кнопки «Перейти к оплате»');

        $checkout = $this->get(route('partner.wallet.checkout', ['amount' => 100]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('name="partner_id" value="'.$this->partner->id.'"', $checkout);
        $this->assertStringNotContainsString('name="partner_id" value="'.$this->foreignPartner->id.'"', $checkout);

        $amountChunk = substr($html, $amountPos, 220);
        $this->assertDoesNotMatchRegularExpression('/\bvalue="[^"]+"/', $amountChunk);
        $this->assertStringContainsString('data-error-for="amount"', $html);
        $this->assertStringContainsString('data-error-for="partner_id"', $html);
        $this->assertStringNotContainsString('Укажите сумму.', $html);
    }

    public function test_foreign_school_admin_sees_own_partner_id_and_balance_not_first_partner(): void
    {
        $this->partner->forceFill(['wallet_balance_cents' => 11100])->save();
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 22200])->save();

        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);
        $this->actingAs($foreignAdmin);
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('partner.wallet'))
            ->assertOk()
            ->getContent();

        $this->assertWalletAmountFormGoesToCheckout($html);
        $this->assertStringContainsString('222,00', $html);
        $this->assertStringNotContainsString('111,00', $html);

        $checkout = $this->get(route('partner.wallet.checkout', ['amount' => 100]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('name="partner_id" value="'.$this->foreignPartner->id.'"', $checkout);
        $this->assertStringNotContainsString('name="partner_id" value="'.$this->partner->id.'"', $checkout);
    }

    public function test_wallet_page_does_not_prefill_amount_when_reopened_as_html(): void
    {
        $this->asAdmin();

        $first = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $second = $this->get(route('partner.wallet'))->assertOk()->getContent();

        foreach ([$first, $second] as $html) {
            $amountPos = strpos($html, 'id="walletTopupAmount"');
            $this->assertNotFalse($amountPos);
            $chunk = substr($html, $amountPos, 220);
            $this->assertDoesNotMatchRegularExpression('/\bvalue="[^"]+"/', $chunk);
        }
    }

    public function test_sidebar_topup_link_is_visible_with_permission_and_hidden_without(): void
    {
        $this->asAdmin();
        $with = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $this->assertStringContainsString('href="/partner-wallet"', $with);
        $this->assertStringContainsString('(пополнить)', $with);

        $denied = $this->createUserWithoutPermission('partnerWallet.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $without = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('(пополнить)', $without);
        $this->assertStringNotContainsString('href="/partner-wallet"', $without);
    }

    public function test_success_page_points_back_to_wallet(): void
    {
        $this->asAdmin();

        $html = $this->get(route('partner.wallet.success'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Платёж обрабатывается', $html);
        $this->assertStringContainsString('href="/partner-wallet"', $html);
        $this->assertStringContainsString('Вернуться в кошелёк', $html);
    }

    public function test_sidebar_balance_for_superadmin_is_selected_partner_not_user_partner(): void
    {
        $this->partner->forceFill(['wallet_balance_cents' => 246800])->save();
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 975300])->save();

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('partner.wallet'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('9 753', $html);
        $this->assertStringNotContainsString('2 468', $html);
    }

    public function test_appservice_provider_wallet_balance_uses_partner_context(): void
    {
        $src = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString('PartnerContext::class)->partnerId()', $src);
        $this->assertStringNotContainsString("session('partner_id')", $src);
        $this->assertStringNotContainsString('auth()->user()->partner_id ??', $src);
    }

    public function test_amount_input_has_min_one_and_csrf_token(): void
    {
        $this->asAdmin();

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*min="1"[^>]*id="walletTopupAmount"[^>]*>/',
            $html
        );
        $this->assertWalletAmountFormGoesToCheckout($html);

        $checkout = $this->get(route('partner.wallet.checkout', ['amount' => 100]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('name="_token"', $checkout);
    }

    private function assertWalletAmountFormGoesToCheckout(string $html): void
    {
        $formPos = strpos($html, 'id="walletTopupForm"');
        $this->assertNotFalse($formPos);
        $formEnd = strpos($html, '</form>', $formPos);
        $this->assertNotFalse($formEnd);
        $form = substr($html, $formPos, $formEnd - $formPos);

        $this->assertStringContainsString('method="get"', $form);
        $this->assertStringContainsString('/partner-wallet/checkout', $form);
        $this->assertStringNotContainsString('name="partner_id"', $form);
        $this->assertStringNotContainsString('name="_token"', $form);
    }

    public function test_sidebar_does_not_say_partner_not_selected_when_school_is_chosen(): void
    {
        $this->asAdmin();

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Партнёр не выбран', $html);
        $this->assertStringContainsString('(пополнить)', $html);
    }

    public function test_balance_page_shows_school_contacts_and_sole_enabled_legal_entity(): void
    {
        $this->asAdmin();
        $this->partner->forceFill([
            'title' => 'Школа Солнышко',
            'phone' => '+7 (900) 111-22-33',
            'email' => 'wallet-school-'.$this->partner->id.'@example.test',
            'website' => 'school.example.test',
        ])->save();
        $this->foreignPartner->forceFill([
            'title' => 'Чужая школа кошелька',
            'phone' => '+7 (900) 000-00-00',
        ])->save();

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $this->assertStringContainsString('id="walletSchoolCard"', $html);
        $this->assertStringContainsString('Школа Солнышко', $html);
        $this->assertStringContainsString('href="tel:+79001112233"', $html);
        $this->assertStringContainsString('href="mailto:wallet-school-'.$this->partner->id.'@example.test"', $html);
        $this->assertStringContainsString('href="https://school.example.test"', $html);
        $this->assertStringNotContainsString('Чужая школа кошелька', $html);
        $this->assertStringNotContainsString('id="walletLegalEntity"', $html);

        $history = $this->get(route('partner.wallet.history'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="walletSchoolCard"', $history);

        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Ромашка',
            'title' => 'ООО Ромашка',
            'tax_id' => '7701234567',
            'is_enabled' => false,
            'is_default' => false,
        ]);
        $enabled = PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Включенное',
            'title' => 'ООО Включенное',
            'tax_id' => '7701234568',
            'is_enabled' => true,
            'is_default' => true,
        ]);
        $deleted = PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Удаленное',
            'title' => 'ООО Удаленное',
            'tax_id' => '7701234569',
            'is_enabled' => true,
            'is_default' => false,
        ]);
        $deleted->delete();

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $this->assertStringContainsString('id="walletLegalEntity"', $html);
        $this->assertStringContainsString('ООО Включенное', $html);
        $this->assertStringContainsString('7701234568', $html);
        $this->assertStringNotContainsString('ООО Ромашка', $html);
        $this->assertStringNotContainsString('7701234567', $html);
        $this->assertStringNotContainsString('ООО Удаленное', $html);
        $this->assertStringNotContainsString('7701234569', $html);

        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'organization_name' => 'ООО Второе',
            'title' => 'ООО Второе',
            'tax_id' => '7701234570',
            'is_enabled' => true,
            'is_default' => false,
        ]);

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();
        $this->assertStringContainsString('Школа Солнышко', $html);
        $this->assertStringNotContainsString('id="walletLegalEntity"', $html);
        $this->assertStringNotContainsString('7701234568', $html);
        $this->assertStringNotContainsString('7701234570', $html);
        $this->assertNotNull($enabled->id);
    }

    public function test_balance_page_hides_placeholder_dash_contacts(): void
    {
        $this->asAdmin();
        $this->partner->forceFill([
            'title' => 'Школа без контактов',
            'phone' => '-',
            'website' => '—',
            'email' => 'wallet-dash-'.$this->partner->id.'@example.test',
        ])->save();

        $html = $this->get(route('partner.wallet'))->assertOk()->getContent();

        $this->assertStringContainsString('Школа без контактов', $html);
        $this->assertStringContainsString('href="mailto:wallet-dash-'.$this->partner->id.'@example.test"', $html);
        $this->assertStringNotContainsString('href="tel:', $html);
        $this->assertStringNotContainsString('>−<', $html);
        $this->assertStringNotContainsString('>-</a>', $html);
        $this->assertStringNotContainsString('>—</a>', $html);
    }
}
