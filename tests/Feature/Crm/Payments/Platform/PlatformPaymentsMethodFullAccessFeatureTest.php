<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\Platform;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\PartnerWallet\Concerns\PartnerWalletTestHelpers;
use Tests\Feature\Crm\Payments\Platform\Concerns\PlatformPaymentsMethodTestHelpers;
use Tests\Feature\Crm\Payments\TBank\Concerns\TbankAcquiringTestHelpers;

/**
 * Smoke всех HTTP-методов кошелька и абонплаты: не 500, не пустой бессмысленный 200.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PlatformPaymentsMethodFullAccessFeatureTest extends CrmTestCase
{
    use PartnerWalletTestHelpers;
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
        $this->partner->forceFill([
            'email' => 'school@example.com',
            'activity_start_date' => '2026-01-01',
        ])->save();
        $this->grantNamedPermission($this->user, 'servicePayments.view');
    }

    public function test_admin_with_page_and_tbank_rights_gets_ok_or_validation_not_500(): void
    {
        $this->assertWalletEndpointsStatus(
            $this->platformPaymentSurfaceEndpointsWithoutGateway(),
            [200, 201, 302, 422],
            'Admin JSON',
            true
        );
        $this->assertWalletEndpointsStatus(
            $this->platformPaymentSurfaceEndpointsWithoutGateway(),
            [200, 201, 302, 422],
            'Admin web',
            false
        );
    }

    public function test_wallet_and_service_get_pages_are_not_empty_200(): void
    {
        foreach ([
            route('partner.wallet'),
            route('partner.wallet.success'),
            route('partner.payment.recharge'),
            route('partner.payment.history'),
            route('partner.payment.success'),
        ] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertNotSame('', trim((string) $response->getContent()), 'Пустой 200: GET '.$url);
        }

        $this->getJson($this->walletTransactionsUrl())
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('partner.payment.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]))->assertOk();
    }
}
