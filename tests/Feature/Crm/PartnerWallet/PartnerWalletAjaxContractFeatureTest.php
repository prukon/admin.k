<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use App\Models\PartnerWalletTransaction;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;

/**
 * AJAX JSON-контракт кошелька: DataTables, 422 errors[field], pending tx текущей школы.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see /docs/documentation/partner-wallet.html
 */
final class PartnerWalletAjaxContractFeatureTest extends CrmTestCase
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
        $this->useDummyYookassaCredentials();
    }

    public function test_transactions_ajax_returns_datatable_json_for_current_partner(): void
    {
        $own = $this->makeWalletTx($this->partner->id, $this->user->id, 'ajax-own');
        $this->makeWalletTx($this->foreignPartner->id, $this->foreignUser->id, 'ajax-foreign');

        $response = $this->getJson($this->walletTransactionsUrl(), $this->walletAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertSame(1, (int) $response->json('recordsTotal'));
        $this->assertContains($own->id, $this->walletTxIds($response->json()));
    }

    public function test_topup_ajax_without_amount_returns_422_amount_error(): void
    {
        $this->postJson(route('partner.wallet.topup'), [
            'partner_id' => $this->partner->id,
        ], $this->walletAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonPath('errors.amount.0', 'Укажите сумму.');

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_topup_ajax_with_amount_below_minimum_returns_422_amount_error(): void
    {
        $this->assertJsonHasFieldError(
            $this->postJson(route('partner.wallet.topup'), [
                'amount' => 0.5,
                'partner_id' => $this->partner->id,
            ], $this->walletAjaxHeaders()),
            'amount'
        );
        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_topup_ajax_with_foreign_partner_id_returns_422_partner_error(): void
    {
        $this->postJson(route('partner.wallet.topup'), [
            'amount' => 100,
            'partner_id' => $this->foreignPartner->id,
        ], $this->walletAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id'])
            ->assertJsonPath('errors.partner_id.0', 'Нельзя пополнить кошелёк другой школы.')
            ->assertJsonMissingValidationErrors(['amount']);

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_topup_ajax_with_too_long_description_returns_422_description_error(): void
    {
        $this->assertJsonHasFieldError(
            $this->postJson(route('partner.wallet.topup'), [
                'amount' => 100,
                'partner_id' => $this->partner->id,
                'description' => str_repeat('x', 256),
            ], $this->walletAjaxHeaders()),
            'description'
        );
        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_topup_ajax_creates_pending_tx_for_current_partner_even_if_yookassa_fails(): void
    {
        $response = $this->postJson(route('partner.wallet.topup'), [
            'amount' => 150.5,
            'partner_id' => $this->partner->id,
        ], $this->walletAjaxHeaders());

        $this->assertNotSame(422, $response->getStatusCode());
        $this->assertNotSame(403, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 500]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $tx = $this->latestWalletTx();
        $this->assertNotNull($tx);
        $this->assertSame((int) $this->partner->id, (int) $tx->partner_id);
        $this->assertSame((int) $this->user->id, (int) $tx->user_id);
        $this->assertSame(15050, (int) $tx->amount_cents);
        $this->assertSame('pending', $tx->status);
        $this->assertSame(0, (int) PartnerWalletTransaction::query()
            ->where('partner_id', $this->foreignPartner->id)
            ->count());

        if ($response->getStatusCode() === 200) {
            $response->assertJsonPath('ok', true);
            $this->assertNotSame('', (string) $response->json('redirect'));
        } else {
            $response->assertJsonPath('ok', false);
            $this->assertNotSame('', trim((string) $response->json('message')));
        }
    }
}
