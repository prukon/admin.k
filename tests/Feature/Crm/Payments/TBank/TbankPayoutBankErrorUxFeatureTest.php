<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments\TBank;

use App\Models\PartnerLegalEntity;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Ошибки банка на карточке выплаты/платежа: REJECTED виден, COMPLETED не пугает, @can на ссылках.
 */
final class TbankPayoutBankErrorUxFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(User $actor, array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rejectedBankPayload(): array
    {
        return [
            'Success' => false,
            'ErrorCode' => '-937',
            'Message' => 'Возмещения партнера заблокированы',
            'Details' => 'Операция отклонена из-за блокировки возмещений партнера.',
        ];
    }

    private function actorWithPaymentManageWithoutPayouts(): User
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPermissions($actor, ['manage.payment.method.tbank']);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        return $actor;
    }

    public function test_completed_payout_does_not_show_bank_rejection_alert(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPermissions($actor, ['tbank.payouts.manage']);
        $this->actingAs($actor);

        $payout = TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'deal_id' => 'ui-completed-' . uniqid(),
            'amount' => 9500,
            'is_final' => true,
            'status' => 'COMPLETED',
            'payload_init' => [
                'Success' => true,
                'ErrorCode' => '0',
                'PaymentId' => 9001,
            ],
            'payload_state' => [
                'Success' => true,
                'ErrorCode' => '0',
                'Status' => 'COMPLETED',
            ],
            'completed_at' => now(),
        ]);

        $this->get('/admin/tinkoff/payouts/' . $payout->id)
            ->assertOk()
            ->assertDontSee('id="payout-bank-error"', false)
            ->assertDontSee('Банк отклонил выплату', false)
            ->assertDontSee('Код -937', false);
    }

    public function test_initiated_payout_does_not_show_bank_rejection_alert(): void
    {
        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPermissions($actor, ['tbank.payouts.manage']);
        $this->actingAs($actor);

        $payout = TinkoffPayout::query()->create([
            'payment_id' => null,
            'partner_id' => $this->partner->id,
            'deal_id' => 'ui-initiated-' . uniqid(),
            'amount' => 9500,
            'is_final' => false,
            'status' => 'INITIATED',
            'when_to_run' => now()->addHours(48),
        ]);

        $this->get('/admin/tinkoff/payouts/' . $payout->id)
            ->assertOk()
            ->assertDontSee('id="payout-bank-error"', false)
            ->assertDontSee('Банк отклонил выплату', false);
    }

    public function test_payment_without_payouts_manage_still_shows_bank_error_but_not_payout_link(): void
    {
        $this->actorWithPaymentManageWithoutPayouts();

        $payment = TinkoffPayment::create([
            'order_id' => 'order-bank-err-no-link',
            'partner_id' => $this->partner->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-bank-err-no-link',
            'confirmed_at' => now(),
        ]);
        $payout = TinkoffPayout::create([
            'payment_id' => $payment->id,
            'partner_id' => $this->partner->id,
            'deal_id' => $payment->deal_id,
            'amount' => 9500,
            'status' => 'REJECTED',
            'source' => 'auto',
            'payload_init' => $this->rejectedBankPayload(),
        ]);

        $html = $this->get('/admin/tinkoff/payments/' . $payment->id)
            ->assertOk()
            ->assertSee('Выплата отклонена: Код -937', false)
            ->assertSee('Возмещения партнера заблокированы', false)
            ->getContent();

        $this->assertStringNotContainsString('/admin/tinkoff/payouts/' . $payout->id, $html);
        $this->assertStringContainsString((string) $payout->id, $html);
    }

    public function test_organization_is_a_link_only_with_sm_register_permission(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Краткое',
            'organization_name' => 'ООО Ссылка На Юрлицо',
        ]);
        $payment = TinkoffPayment::create([
            'order_id' => 'order-org-link',
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $entity->id,
            'amount' => 10000,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => 'deal-org-link',
            'confirmed_at' => now(),
        ]);

        $this->actorWithPaymentManageWithoutPayouts();

        $withoutLink = $this->get('/admin/tinkoff/payments/' . $payment->id)
            ->assertOk()
            ->assertSee('ООО Ссылка На Юрлицо', false)
            ->getContent();
        $this->assertStringNotContainsString('/admin/legal-entities/' . $entity->id, $withoutLink);

        $actor = $this->createUserWithoutPermission('tbank.payouts.manage', $this->partner);
        $this->grantPermissions($actor, ['manage.payment.method.tbank', 'legal_entities.sm_register']);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get('/admin/tinkoff/payments/' . $payment->id)
            ->assertOk()
            ->assertSee('/admin/legal-entities/' . $entity->id, false)
            ->assertSee('ООО Ссылка На Юрлицо', false);
    }
}
