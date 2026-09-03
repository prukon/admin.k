<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\PartnerServicePayments;

use App\Models\PartnerPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * /partner-payment: форма и история только текущей школы, не хардкод partner_id=1.
 *
 * @see /docs/documentation/partner-service-payments.html
 */
final class PartnerServicePaymentPartnerIsolationFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantServicePaymentsView($this->user, (int) $this->partner->id);
    }

    public function test_recharge_form_hidden_partner_id_is_current_not_hardcoded_one(): void
    {
        $html = $this->get(route('partner.payment.recharge'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="partner_id" value="'.$this->partner->id.'"', $html);
        $this->assertStringNotContainsString('name="partner_id" value="'.$this->foreignPartner->id.'"', $html);
        $this->assertStringContainsString('data-error-for="partner_id"', $html);
        $this->assertStringContainsString('data-error-for="amount"', $html);
    }

    public function test_foreign_school_admin_sees_own_partner_id_on_recharge_form(): void
    {
        $foreignAdmin = $this->createUserWithRole('admin', $this->foreignPartner);
        $this->grantServicePaymentsView($foreignAdmin, (int) $this->foreignPartner->id);
        $this->actingAs($foreignAdmin);
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('partner.payment.recharge'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="partner_id" value="'.$this->foreignPartner->id.'"', $html);
        $this->assertStringNotContainsString('name="partner_id" value="'.$this->partner->id.'"', $html);
    }

    public function test_history_endpoint_returns_only_current_partner_rows(): void
    {
        $own = $this->makeServicePayment((int) $this->partner->id, (int) $this->user->id);
        $foreign = $this->makeServicePayment((int) $this->foreignPartner->id, (int) $this->foreignUser->id);

        $json = $this->getJson($this->servicePaymentsDataUrl())
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        $ids = $this->paymentIds($json);

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
        $this->assertSame(1, (int) $json['recordsTotal']);
    }

    public function test_non_superadmin_cannot_switch_partner_via_session_to_see_foreign_history(): void
    {
        $own = $this->makeServicePayment((int) $this->partner->id, (int) $this->user->id);
        $foreign = $this->makeServicePayment((int) $this->foreignPartner->id, (int) $this->foreignUser->id);

        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $json = $this->getJson($this->servicePaymentsDataUrl())
            ->assertOk()
            ->json();

        $ids = $this->paymentIds($json);

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_superadmin_sees_history_of_selected_partner_only(): void
    {
        $own = $this->makeServicePayment((int) $this->partner->id, (int) $this->user->id);
        $foreign = $this->makeServicePayment((int) $this->foreignPartner->id, (int) $this->foreignUser->id);

        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $json = $this->getJson($this->servicePaymentsDataUrl())
            ->assertOk()
            ->json();

        $ids = $this->paymentIds($json);

        $this->assertContains($foreign->id, $ids);
        $this->assertNotContains($own->id, $ids);
    }

    public function test_create_rejects_foreign_partner_id(): void
    {
        $this->from(route('partner.payment.recharge'))
            ->post(route('createPaymentYookassa'), [
                'amount' => 2500,
                'days' => 29,
                'partner_id' => $this->foreignPartner->id,
                'description' => 'Учет до 200 пользователей',
            ])
            ->assertRedirect(route('partner.payment.recharge'))
            ->assertSessionHasErrors(['partner_id']);

        $this->assertSame(
            'Нельзя оплатить сервис за другую школу.',
            session('errors')->first('partner_id')
        );
        $this->assertSame(0, (int) PartnerPayment::query()->count());
    }

    public function test_create_json_rejects_foreign_partner_id_with_field_error(): void
    {
        $this->postJson(route('createPaymentYookassa'), [
            'amount' => 2500,
            'days' => 29,
            'partner_id' => $this->foreignPartner->id,
            'description' => 'Учет до 200 пользователей',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id'])
            ->assertJsonPath('errors.partner_id.0', 'Нельзя оплатить сервис за другую школу.');

        $this->assertSame(0, (int) PartnerPayment::query()->count());
    }

    private function grantServicePaymentsView(User $actor, int $partnerId): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $partnerId,
            'role_id' => (int) $actor->role_id,
            'permission_id' => $this->permissionId('servicePayments.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeServicePayment(int $partnerId, int $userId): PartnerPayment
    {
        return PartnerPayment::query()->create([
            'payment_id' => (string) Str::uuid(),
            'partner_id' => $partnerId,
            'user_id' => $userId,
            'amount_cents' => 250000,
            'payment_date' => now(),
            'payment_method' => 'yookassa',
            'payment_status' => 'succeeded',
            'description' => 'test-service-payment',
        ]);
    }

    private function servicePaymentsDataUrl(): string
    {
        return route('partner.payment.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ]);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<int>
     */
    private function paymentIds(array $json): array
    {
        return collect($json['data'] ?? [])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
