<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerWallet;

use App\Models\PartnerWalletTransaction;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;

/**
 * Non-AJAX safety-net: без X-Requested-With валидация → 302 + session errors[field];
 * вызов ЮKassa — JSON 200/500 (как SMS send), pending tx текущей школы, не пустой 200 без записи.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackageAssignmentPaySmsNonAjaxSafetyNetFeatureTest
 * @see /docs/documentation/partner-wallet.html
 */
final class PartnerWalletNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_non_ajax_topup_without_amount_redirects_with_amount_error_and_does_not_create_tx(): void
    {
        $response = $this->from(route('partner.wallet'))
            ->post(route('partner.wallet.topup'), [
                '_token' => csrf_token(),
                'partner_id' => $this->partner->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация не должна давать пустой/успешный 200');
        $response->assertStatus(302);
        $response->assertRedirect(route('partner.wallet'));
        $response->assertSessionHasErrors(['amount']);

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_non_ajax_topup_with_foreign_partner_id_redirects_with_partner_error(): void
    {
        $response = $this->from(route('partner.wallet'))
            ->post(route('partner.wallet.topup'), [
                '_token' => csrf_token(),
                'amount' => 100,
                'partner_id' => $this->foreignPartner->id,
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertRedirect(route('partner.wallet'));
        $response->assertSessionHasErrors(['partner_id']);

        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_non_ajax_topup_with_zero_amount_redirects_with_amount_error(): void
    {
        $response = $this->from(route('partner.wallet'))
            ->post(route('partner.wallet.topup'), [
                '_token' => csrf_token(),
                'amount' => 0,
                'partner_id' => $this->partner->id,
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['amount']);
        $this->assertTopupDidNotCreateTransaction();
    }

    public function test_non_ajax_topup_creates_pending_tx_for_current_partner_and_is_not_empty_200_without_row(): void
    {
        $response = $this->from(route('partner.wallet'))
            ->post(route('partner.wallet.topup'), [
                '_token' => csrf_token(),
                'amount' => 80,
                'partner_id' => $this->partner->id,
            ]);

        $this->assertNotSame(422, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302, 500]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $tx = $this->latestWalletTx();
        $this->assertNotNull($tx, 'Native POST должен создать pending-транзакцию текущей школы');
        $this->assertSame((int) $this->partner->id, (int) $tx->partner_id);
        $this->assertSame(8000, (int) $tx->amount_cents);
        $this->assertSame('pending', $tx->status);
        $this->assertSame(0, (int) PartnerWalletTransaction::query()
            ->where('partner_id', $this->foreignPartner->id)
            ->count());
    }

    public function test_non_ajax_wallet_page_returns_html_not_empty_200(): void
    {
        $response = $this->get(route('partner.wallet'));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertSee('id="walletTopupForm"', false);
    }

    public function test_non_ajax_success_page_returns_html_not_empty_200(): void
    {
        $response = $this->get(route('partner.wallet.success'));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertSee('Платёж обрабатывается', false);
        $response->assertSee('/partner-wallet', false);
    }
}
