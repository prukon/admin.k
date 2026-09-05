<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use App\Models\PartnerWalletTransaction;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * UX-баг: админ одной школы видел историю/баланс Partner::first(), а не своей.
 * Тесты падают на заглушке first() и проходят на PartnerContext.
 *
 * @see /docs/documentation/partner-wallet.html
 * @see /docs/documentation/partner-scope-guide.html
 */
final class PartnerWalletPartnerIsolationFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;
    use TbankAcquiringTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->useDummyYookassaCredentials();
    }

    public function test_wallet_page_shows_current_partner_balance_not_foreign(): void
    {
        $this->partner->forceFill(['wallet_balance_cents' => 12345])->save();
        $this->foreignPartner->forceFill(['wallet_balance_cents' => 99900])->save();

        $this->get(route('partner.wallet'))
            ->assertOk()
            ->assertSee('name="partner_id" value="'.$this->partner->id.'"', false)
            ->assertDontSee('name="partner_id" value="'.$this->foreignPartner->id.'"', false)
            ->assertSee('123,45', false)
            ->assertDontSee('999,00', false);
    }

    public function test_transactions_endpoint_returns_only_current_partner_rows(): void
    {
        $own = $this->makeWalletTx($this->partner->id, $this->user->id, 'own-wallet-tx');
        $foreign = $this->makeWalletTx($this->foreignPartner->id, $this->foreignUser->id, 'foreign-wallet-tx');

        $json = $this->getJson($this->walletTransactionsUrl())
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        $ids = $this->walletTxIds($json);

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
        $this->assertSame(1, (int) $json['recordsTotal']);
    }

    public function test_foreign_school_admin_does_not_see_first_partner_history(): void
    {
        $own = $this->makeWalletTx($this->partner->id, $this->user->id, 'first-partner-tx');
        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);
        $foreignTx = $this->makeWalletTx($this->foreignPartner->id, $foreignAdmin->id, 'second-partner-tx');

        $this->actingAs($foreignAdmin);
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $json = $this->getJson($this->walletTransactionsUrl())
            ->assertOk()
            ->json();

        $ids = $this->walletTxIds($json);

        $this->assertContains($foreignTx->id, $ids);
        $this->assertNotContains($own->id, $ids);
        $this->assertSame(1, (int) $json['recordsTotal']);
    }

    public function test_non_superadmin_cannot_switch_partner_via_session_to_see_foreign_wallet(): void
    {
        $own = $this->makeWalletTx($this->partner->id, $this->user->id, 'own-anti-leak');
        $foreign = $this->makeWalletTx($this->foreignPartner->id, $this->foreignUser->id, 'foreign-anti-leak');

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $json = $this->getJson($this->walletTransactionsUrl())
            ->assertOk()
            ->json();

        $ids = $this->walletTxIds($json);

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_superadmin_sees_wallet_of_selected_partner_only(): void
    {
        $own = $this->makeWalletTx($this->partner->id, $this->user->id, 'sa-own');
        $foreign = $this->makeWalletTx($this->foreignPartner->id, $this->foreignUser->id, 'sa-foreign');

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $json = $this->getJson($this->walletTransactionsUrl())
            ->assertOk()
            ->json();

        $ids = $this->walletTxIds($json);

        $this->assertContains($foreign->id, $ids);
        $this->assertNotContains($own->id, $ids);
    }

    public function test_topup_rejects_foreign_partner_id(): void
    {
        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->foreignPartner->id,
        ], $this->walletAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id'])
            ->assertJsonPath('errors.partner_id.0', 'Нельзя пополнить кошелёк другой школы.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_topup_rejects_invalid_amount_with_field_error(): void
    {
        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 0,
            'partner_id' => $this->partner->id,
        ], $this->walletAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'Сумма для СБП должна быть не меньше 10 ₽.');
    }

    public function test_foreign_admin_topup_creates_pending_tx_for_own_school_not_first_partner(): void
    {
        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);
        $this->actingAs($foreignAdmin);
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $this->grantNamedPermission($foreignAdmin, 'platformPayments.method.yookassa', (int) $this->foreignPartner->id);

        $response = $this->postJson(route('partner.wallet.topup'), [
            'amount' => 40,
            'partner_id' => $this->foreignPartner->id,
            'payment_method' => 'yookassa',
        ], $this->walletAjaxHeaders());

        $this->assertNotSame(422, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 500]);

        $tx = $this->latestWalletTx();
        $this->assertNotNull($tx);
        $this->assertSame((int) $this->foreignPartner->id, (int) $tx->partner_id);
        $this->assertSame(4000, (int) $tx->amount_cents);
        $this->assertSame(0, (int) PartnerWalletTransaction::query()
            ->where('partner_id', $this->partner->id)
            ->count());
    }

    public function test_superadmin_viewing_foreign_school_cannot_topup_own_school_id(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->partner->id,
        ], $this->walletAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id'])
            ->assertJsonPath('errors.partner_id.0', 'Нельзя пополнить кошелёк другой школы.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_foreign_admin_history_does_not_show_webhook_credit_written_to_first_partner(): void
    {
        $firstSchoolTx = $this->makeWalletTx($this->partner->id, $this->user->id, 'istok-credit', [
            'provider' => 'yookassa',
            'status' => 'succeeded',
            'amount_cents' => 7000,
        ]);
        $own = $this->makeWalletTx($this->foreignPartner->id, $this->foreignUser->id, 'own-credit', [
            'provider' => 'yookassa',
            'status' => 'succeeded',
            'amount_cents' => 7000,
        ]);

        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);
        $this->actingAs($foreignAdmin);
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $json = $this->getJson($this->walletTransactionsUrl())->assertOk()->json();
        $ids = $this->walletTxIds($json);

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($firstSchoolTx->id, $ids);
        $this->assertSame(1, (int) $json['recordsTotal']);
    }
}
