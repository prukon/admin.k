<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Payments\Concerns\PaymentCheckoutMethodsTestHelpers;

/**
 * Витрина POST /payment и клубный взнос: подписи способов и скрытие «Других способов».
 *
 * @see /docs/documentation/payments.html#vitrina-payment
 * @see /docs/documentation/payments.html#club-fee-page
 */
final class PaymentCheckoutMethodsUxFeatureTest extends CrmTestCase
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

    public function test_payment_page_hides_other_methods_when_only_sbp_is_allowed(): void
    {
        $teamId = $this->seedReadyMonthlyCheckout();
        $this->revokeCheckoutMethodPermissions(['payment.method.tbankCard', 'payment.method.robokassa']);

        $html = $this->post(route('payment'), $this->monthlyCheckoutPayload($teamId))
            ->assertOk()
            ->assertViewIs('payment.paymentUser')
            ->assertViewHas('tbankSbpAvailable', true)
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('robokassaAvailable', false)
            ->getContent();

        $this->assertStringContainsString('Оплата через СБП', $html);
        $this->assertStringContainsString('recommend-badge">Способ оплаты', $html);
        $this->assertStringContainsString('class="payment-layout payment-layout--sbp-only"', $html);
        $this->assertStringNotContainsString('Другие способы оплаты', $html);
        $this->assertStringNotContainsString('Выберите ниже', $html);
        $this->assertStringNotContainsString('Рекомендуемый способ', $html);
        $this->assertStringNotContainsString('Оплатить картой', $html);
        $this->assertStringNotContainsString('pay-card-name">Робокасса', $html);
    }

    public function test_payment_page_shows_other_methods_when_card_is_allowed(): void
    {
        $teamId = $this->seedReadyMonthlyCheckout();

        $html = $this->post(route('payment'), $this->monthlyCheckoutPayload($teamId))
            ->assertOk()
            ->assertViewHas('tbankSbpAvailable', true)
            ->assertViewHas('tbankAvailable', true)
            ->getContent();

        $this->assertStringContainsString('Другие способы оплаты', $html);
        $this->assertStringContainsString('Оплатить картой', $html);
        $this->assertStringContainsString('recommend-badge">Способ оплаты', $html);
        $this->assertStringContainsString('class="payment-layout"', $html);
        $this->assertStringNotContainsString('class="payment-layout payment-layout--sbp-only"', $html);
        $this->assertStringNotContainsString('Выберите ниже', $html);
        $this->assertStringNotContainsString('Рекомендуемый способ', $html);
    }

    public function test_payment_page_shows_other_methods_when_only_robokassa_is_extra(): void
    {
        $teamId = $this->seedReadyMonthlyCheckout();
        $this->revokeCheckoutMethodPermissions(['payment.method.tbankCard']);
        $this->grantCheckoutMethodPermissions(['payment.method.robokassa']);
        $this->seedRobokassaForCurrentPartner();

        $html = $this->post(route('payment'), $this->monthlyCheckoutPayload($teamId))
            ->assertOk()
            ->assertViewHas('tbankSbpAvailable', true)
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('robokassaAvailable', true)
            ->getContent();

        $this->assertStringContainsString('Другие способы оплаты', $html);
        $this->assertStringContainsString('pay-card-name">Робокасса', $html);
        $this->assertStringNotContainsString('Оплатить картой', $html);
        $this->assertStringNotContainsString('class="payment-layout payment-layout--sbp-only"', $html);
        $this->assertStringNotContainsString('Выберите ниже', $html);
    }

    public function test_club_fee_hides_other_methods_when_only_sbp_is_allowed(): void
    {
        $this->seedReadyClubFeeCheckout();
        $this->revokeCheckoutMethodPermissions(['payment.method.tbankCard', 'payment.method.robokassa']);

        $html = $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewIs('payment.clubFee')
            ->assertViewHas('tbankSbpAvailable', true)
            ->assertViewHas('tbankAvailable', false)
            ->assertViewHas('robokassaAvailable', false)
            ->getContent();

        $this->assertStringContainsString('Оплата через СБП', $html);
        $this->assertStringContainsString('recommend-badge">Способ оплаты', $html);
        $this->assertStringContainsString('class="payment-layout payment-layout--sbp-only"', $html);
        $this->assertStringNotContainsString('Другие способы оплаты', $html);
        $this->assertStringNotContainsString('Рекомендуемый способ', $html);
        $this->assertStringNotContainsString('Оплатить картой', $html);
        $this->assertStringNotContainsString('id="clubFeeOtherMethodsColumn"', $html);
    }

    public function test_club_fee_post_matches_get_when_only_sbp_is_allowed(): void
    {
        $this->seedReadyClubFeeCheckout();
        $this->revokeCheckoutMethodPermissions(['payment.method.tbankCard', 'payment.method.robokassa']);

        $html = $this->post(route('clubFee'), ['outSum' => '500'])
            ->assertOk()
            ->assertViewIs('payment.clubFee')
            ->assertViewHas('tbankSbpAvailable', true)
            ->getContent();

        $this->assertStringContainsString('recommend-badge">Способ оплаты', $html);
        $this->assertStringContainsString('class="payment-layout payment-layout--sbp-only"', $html);
        $this->assertStringNotContainsString('Другие способы оплаты', $html);
        $this->assertStringNotContainsString('id="clubFeeOtherMethodsColumn"', $html);
        $this->assertStringNotContainsString('Рекомендуемый способ', $html);
    }

    public function test_club_fee_shows_other_methods_when_card_is_allowed(): void
    {
        $this->seedReadyClubFeeCheckout();

        $html = $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewHas('tbankAvailable', true)
            ->getContent();

        $this->assertStringContainsString('Другие способы оплаты', $html);
        $this->assertStringContainsString('id="clubFeeOtherMethodsColumn"', $html);
        $this->assertStringContainsString('Оплатить картой', $html);
        $this->assertStringContainsString('recommend-badge">Способ оплаты', $html);
        $this->assertStringContainsString('updateClubFeeOtherMethodsVisibility', $html);
        $this->assertStringNotContainsString('class="payment-layout payment-layout--sbp-only"', $html);
        $this->assertStringNotContainsString('Рекомендуемый способ', $html);
    }

    public function test_club_fee_shows_other_methods_when_only_robokassa_is_extra(): void
    {
        $this->seedReadyClubFeeCheckout();
        $this->revokeCheckoutMethodPermissions(['payment.method.tbankCard']);
        $this->grantCheckoutMethodPermissions(['payment.method.robokassa']);
        $this->seedRobokassaForCurrentPartner();

        $html = $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewHas('robokassaAvailable', true)
            ->assertViewHas('tbankAvailable', false)
            ->getContent();

        $this->assertStringContainsString('Другие способы оплаты', $html);
        $this->assertStringContainsString('id="clubFeeOtherMethodsColumn"', $html);
        $this->assertStringContainsString('data-other-method="robokassa"', $html);
        $this->assertStringNotContainsString('Оплатить картой', $html);
        $this->assertStringNotContainsString('class="payment-layout payment-layout--sbp-only"', $html);
    }

    public function test_club_fee_multi_team_hides_other_methods_column_until_team_chosen(): void
    {
        $this->seedGlobalTbank();
        $this->grantCheckoutMethodPermissions(['payment.clubfee']);

        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-CLUB-UX-A')
            ->create(['is_default' => true]);
        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-CLUB-UX-B')
            ->create(['is_default' => false]);

        $teamBound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $entity->id,
        ]);
        $teamUnbound = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => null,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($this->user, (int) $teamBound->id);
        $sync->attachTeamForStudent($this->user, (int) $teamUnbound->id);

        $html = $this->get(route('clubFee'))
            ->assertOk()
            ->assertViewHas('clubFeeRequiresTeamChoice', true)
            ->assertViewHas('canTbankCard', true)
            ->assertViewHas('robokassaAvailable', false)
            ->getContent();

        $this->assertStringContainsString('Другие способы оплаты', $html);
        $this->assertStringContainsString('id="clubFeeOtherMethodsColumn"', $html);
        $this->assertMatchesRegularExpression(
            '/id="clubFeeOtherMethodsColumn"[^>]*style="display:none"/',
            $html
        );
        $this->assertStringContainsString('class="payment-layout payment-layout--sbp-only"', $html);
        $this->assertStringContainsString('updateClubFeeOtherMethodsVisibility', $html);
        $this->assertStringNotContainsString('Рекомендуемый способ', $html);
    }
}
