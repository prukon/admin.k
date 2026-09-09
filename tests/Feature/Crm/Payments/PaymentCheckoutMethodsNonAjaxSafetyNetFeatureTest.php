<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Payments\Concerns\PaymentCheckoutMethodsTestHelpers;

/**
 * Native GET/POST без X-Requested-With: HTML-витрина, не пустой 200, не JSON.
 *
 * @see PaymentCheckoutServiceProviderFeatureTest
 * @see PaymentClubFeePageFullAccessFeatureTest
 */
final class PaymentCheckoutMethodsNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    use PaymentCheckoutMethodsTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_post_payment_is_not_500(): void
    {
        Auth::logout();

        $response = $this->post(route('payment'), $this->monthlyCheckoutPayload(1));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_guest_get_and_post_club_fee_are_not_500(): void
    {
        Auth::logout();

        $get = $this->get(route('clubFee'));
        $this->assertContains($get->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $get->getStatusCode());

        $post = $this->post(route('clubFee'), ['outSum' => '100']);
        $this->assertContains($post->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $post->getStatusCode());
    }

    public function test_payment_index_non_ajax_only_sbp_returns_html_view_not_empty_200(): void
    {
        $teamId = $this->seedReadyMonthlyCheckout();
        $this->revokeCheckoutMethodPermissions(['payment.method.tbankCard', 'payment.method.robokassa']);

        $response = $this->post(route('payment'), $this->monthlyCheckoutPayload($teamId));

        $response->assertOk();
        $response->assertViewIs('payment.paymentUser');
        $this->assertHtmlIsNonEmptyPage($response);

        $html = $response->getContent();
        $this->assertStringContainsString('class="payment-layout payment-layout--sbp-only"', $html);
        $this->assertStringNotContainsString('Другие способы оплаты', $html);
        $this->assertStringNotContainsString('Выберите ниже', $html);
    }

    public function test_club_fee_get_and_post_non_ajax_only_sbp_return_html_view_not_empty_200(): void
    {
        $this->seedReadyClubFeeCheckout();
        $this->revokeCheckoutMethodPermissions(['payment.method.tbankCard', 'payment.method.robokassa']);

        foreach (['GET', 'POST'] as $method) {
            $response = $this->call($method, route('clubFee'), $method === 'POST' ? ['outSum' => '500'] : []);

            $response->assertOk();
            $response->assertViewIs('payment.clubFee');
            $this->assertHtmlIsNonEmptyPage($response);

            $html = $response->getContent();
            $this->assertStringContainsString('class="payment-layout payment-layout--sbp-only"', $html);
            $this->assertStringNotContainsString('Другие способы оплаты', $html);
            $this->assertStringNotContainsString('id="clubFeeOtherMethodsColumn"', $html);
        }
    }
}
