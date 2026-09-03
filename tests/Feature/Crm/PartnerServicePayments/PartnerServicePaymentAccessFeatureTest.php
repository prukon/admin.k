<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerServicePayments;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к /partner-payment: guest, без права, с правом, не 500 / не пустой 200.
 *
 * @see /docs/documentation/partner-service-payments.html
 */
final class PartnerServicePaymentAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_guest_is_denied_on_recharge_and_history(): void
    {
        Auth::logout();

        $this->get(route('partner.payment.recharge'))->assertRedirect();
        $this->get(route('partner.payment.history'))->assertRedirect();
        $this->getJson(route('partner.payment.data'))->assertStatus(401);
    }

    public function test_admin_without_permission_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('servicePayments.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('partner.payment.recharge'))->assertForbidden();
        $this->get(route('partner.payment.history'))->assertForbidden();
        $this->getJson(route('partner.payment.data'))->assertForbidden();
        $this->postJson(route('createPaymentYookassa'), [
            'amount' => 2500,
            'days' => 29,
            'partner_id' => $this->partner->id,
            'description' => 'Учет до 200 пользователей',
        ])->assertForbidden();
    }

    public function test_admin_with_permission_gets_ok_pages(): void
    {
        $this->asAdmin();
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => (int) $this->user->role_id,
            'permission_id' => $this->permissionId('servicePayments.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recharge = $this->get(route('partner.payment.recharge'))->assertOk();
        $this->assertNotSame('', trim((string) $recharge->getContent()));

        $history = $this->get(route('partner.payment.history'))->assertOk();
        $this->assertNotSame('', trim((string) $history->getContent()));

        $this->getJson(route('partner.payment.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]))->assertOk();
    }
}
