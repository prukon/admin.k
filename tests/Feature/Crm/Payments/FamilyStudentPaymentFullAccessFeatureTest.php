<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Payments;

use App\Models\UserPrice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * HTTP-матрица семейной оплаты: guest / без прав / с правами, без 500 и пустого 200.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html
 * @see /docs/documentation/payments.html#vitrina-payment
 */
final class FamilyStudentPaymentFullAccessFeatureTest extends CrmTestCase
{
    use FamilyStudentPaymentFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->createSiblingStudents();
        $team = $this->makeTeam('FullAccess-family');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 150000, false, (int) $team->id)->create();
        $this->familyTeamId = (int) $team->id;
    }

    private int $familyTeamId;

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function familyPaymentEndpoints(): array
    {
        $payload = $this->monthlyPayload($this->familyTeamId, '2026-09-01');

        return [
            ['method' => 'POST', 'url' => route('payment'), 'data' => $payload],
            ['method' => 'POST', 'url' => route('payment.pay'), 'data' => $payload],
            ['method' => 'POST', 'url' => route('payment.tinkoff.pay'), 'data' => $payload],
            ['method' => 'POST', 'url' => route('payment.tinkoff.sbp'), 'data' => $payload],
            ['method' => 'GET', 'url' => '/payment'],
            ['method' => 'GET', 'url' => route('payment.pay')],
            ['method' => 'PATCH', 'url' => route('payment'), 'data' => $payload],
            ['method' => 'DELETE', 'url' => route('payment')],
            ['method' => 'POST', 'url' => route('cabinet.active-student.switch'), 'data' => [
                'student_user_id' => $this->brother2->id,
            ]],
        ];
    }

    public function test_guest_is_denied_on_family_payment_endpoints(): void
    {
        Auth::logout();

        foreach ($this->familyPaymentEndpoints() as $item) {
            $response = $this->call($item['method'], $item['url'], $item['data'] ?? []);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 404, 405, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_user_without_paying_classes_gets_403_on_vitrina_after_switch(): void
    {
        DB::table('permission_role')
            ->where('role_id', $this->brother1->role_id)
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId('paying.classes'))
            ->delete();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $response = $this->post(route('payment'), $this->monthlyPayload($this->familyTeamId, '2026-09-01'));
        $response->assertForbidden();
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_user_without_robokassa_method_gets_403_on_pay_after_switch(): void
    {
        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment.pay'), $this->monthlyPayload($this->familyTeamId, '2026-09-01'))
            ->assertForbidden();
    }

    public function test_user_without_tbank_card_gets_403_on_card_init_after_switch(): void
    {
        DB::table('permission_role')
            ->where('role_id', $this->brother1->role_id)
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId('payment.method.tbankCard'))
            ->delete();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment.tinkoff.pay'), $this->monthlyPayload($this->familyTeamId, '2026-09-01'))
            ->assertForbidden();
    }

    public function test_user_without_tbank_sbp_gets_403_on_sbp_init_after_switch(): void
    {
        DB::table('permission_role')
            ->where('role_id', $this->brother1->role_id)
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId('payment.method.tbankSBP'))
            ->delete();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment.tinkoff.sbp'), $this->monthlyPayload($this->familyTeamId, '2026-09-01'))
            ->assertForbidden();
    }

    public function test_authorized_parent_gets_vitrina_after_switch_not_empty_200(): void
    {
        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $response = $this->post(route('payment'), $this->monthlyPayload($this->familyTeamId, '2026-09-01'));

        $response->assertOk()
            ->assertViewIs('payment.paymentUser')
            ->assertViewHas('outSum', '1500.00');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertDontSee('Доступ запрещён', false);
    }

    public function test_switch_non_ajax_redirects_and_stores_active_student(): void
    {
        $this->actingAs($this->brother1);

        $response = $this->from(route('dashboard'))
            ->post(route('cabinet.active-student.switch'), [
                'student_user_id' => $this->brother2->id,
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame($this->brother2->id, session(\App\Services\Users\FamilyStudentContextService::SESSION_KEY));
    }

    public function test_wrong_methods_on_payment_do_not_return_500(): void
    {
        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        foreach (['GET', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $response = $this->call($method, route('payment'));
            $this->assertNotSame(500, $response->getStatusCode(), $method.' /payment');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' /payment empty 200');
        }
    }

    public function test_tinkoff_sbp_json_without_lesson_package_id_returns_field_error(): void
    {
        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->postJson(route('payment.tinkoff.sbp'), [
            'payment_kind' => 'lesson_package',
        ], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_lesson_package_id']);
    }
}
