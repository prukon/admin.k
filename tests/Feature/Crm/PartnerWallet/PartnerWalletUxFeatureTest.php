<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;

/**
 * Разметка /partner-wallet и правила «если X, то по умолчанию Y»:
 * баланс текущей школы, hidden partner_id, пустая сумма, ошибки под полями, @can в сайдбаре.
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
        $this->assertStringContainsString('id="walletTxTable"', $html);
        $this->assertStringContainsString('name="partner_id" value="'.$this->partner->id.'"', $html);
        $this->assertStringNotContainsString('name="partner_id" value="'.$this->foreignPartner->id.'"', $html);
        $this->assertStringContainsString('123,45', $html);
        $this->assertStringNotContainsString('999,00', $html);
        $this->assertStringNotContainsString('wallet_balance ??', $html);

        $amountPos = strpos($html, 'id="walletTopupAmount"');
        $submitPos = strpos($html, 'id="topupBtn"');
        $this->assertNotFalse($amountPos);
        $this->assertNotFalse($submitPos);
        $this->assertTrue($amountPos < $submitPos, 'Поле суммы должно быть выше кнопки «Оплатить»');

        $amountChunk = substr($html, $amountPos, 220);
        $this->assertDoesNotMatchRegularExpression('/\bvalue="[^"]+"/', $amountChunk);
        $this->assertStringContainsString('data-error-for="amount"', $html);
        $this->assertStringContainsString('data-error-for="partner_id"', $html);
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

        $this->assertStringContainsString('name="partner_id" value="'.$this->foreignPartner->id.'"', $html);
        $this->assertStringNotContainsString('name="partner_id" value="'.$this->partner->id.'"', $html);
        $this->assertStringContainsString('222,00', $html);
        $this->assertStringNotContainsString('111,00', $html);
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
}
