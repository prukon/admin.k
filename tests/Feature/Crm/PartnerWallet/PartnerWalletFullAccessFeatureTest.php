<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;

/**
 * Полный smoke страницы кошелька: страница, история, success, сайдбар, JSON DataTables.
 *
 * @see /docs/documentation/partner-wallet.html
 */
final class PartnerWalletFullAccessFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->partner->forceFill(['wallet_balance_cents' => 4500])->save();
    }

    public function test_admin_can_open_wallet_surfaces_with_matching_sidebar_balance(): void
    {
        $own = $this->makeWalletTx($this->partner->id, $this->user->id, 'full-access-own');

        $page = $this->get(route('partner.wallet'))
            ->assertOk()
            ->assertSee('id="walletTopupForm"', false)
            ->assertSee('id="walletTxTable"', false)
            ->assertSee('45,00', false)
            ->getContent();

        $this->assertStringContainsString('(пополнить)', $page);
        $this->assertStringContainsString('href="/partner-wallet"', $page);

        $this->get(route('partner.wallet.success'))
            ->assertOk()
            ->assertSee('Платёж обрабатывается', false);

        $json = $this->getJson($this->walletTransactionsUrl())
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        $this->assertContains($own->id, $this->walletTxIds($json));
        $this->assertGreaterThan(0, (int) $json['recordsTotal']);
    }

    public function test_superadmin_switched_to_foreign_school_sees_that_school_wallet(): void
    {
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 8800])->save();
        $foreignTx = $this->makeWalletTx($this->foreignPartner->id, $this->foreignUser->id, 'full-sa-foreign');
        $ownTx = $this->makeWalletTx($this->partner->id, $this->user->id, 'full-sa-own');

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('partner.wallet'))
            ->assertOk()
            ->assertSee('name="partner_id" value="'.$this->foreignPartner->id.'"', false)
            ->assertSee('88,00', false);

        $json = $this->getJson($this->walletTransactionsUrl())->assertOk()->json();
        $ids = $this->walletTxIds($json);
        $this->assertContains($foreignTx->id, $ids);
        $this->assertNotContains($ownTx->id, $ids);
    }
}
