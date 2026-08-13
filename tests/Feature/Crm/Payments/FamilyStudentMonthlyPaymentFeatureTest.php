<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\LessonPackage;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\Tinkoff\TinkoffPaymentsService;
use App\Support\CabinetLessonPackagePermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Семейный кабинет: месячная оплата списывается активному ученику, права остаются у учётки входа.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html
 * @see /docs/documentation/payments.html
 */
final class FamilyStudentMonthlyPaymentFeatureTest extends CrmTestCase
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
    }

    public function test_vitrina_after_switch_to_sibling_in_other_team_uses_sibling_price(): void
    {
        $team1 = $this->makeTeam('Группа брата 1');
        $team2 = $this->makeTeam('Группа брата 2');
        $this->attachStudent($this->brother1, $team1);
        $this->attachStudent($this->brother2, $team2);

        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-09-01', 100000, false, (int) $team1->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 250000, false, (int) $team2->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment'), $this->monthlyPayload((int) $team2->id, '2026-09-01'))
            ->assertOk()
            ->assertViewHas('outSum', '2500.00')
            ->assertViewHas('monthlyTeamId', (int) $team2->id)
            ->assertViewHas('monthlyTeamTitle', 'Группа брата 2');
    }

    public function test_vitrina_without_switch_rejects_sibling_team(): void
    {
        $team1 = $this->makeTeam('Группа брата 1');
        $team2 = $this->makeTeam('Группа брата 2');
        $this->attachStudent($this->brother1, $team1);
        $this->attachStudent($this->brother2, $team2);

        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-09-01', 100000, false, (int) $team1->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 250000, false, (int) $team2->id)->create();

        $this->actingAs($this->brother1);

        $this->post(route('payment'), $this->monthlyPayload((int) $team2->id, '2026-09-01'))
            ->assertForbidden();
    }

    public function test_same_team_vitrina_after_switch_uses_brother2_price_not_brother1(): void
    {
        $team = $this->makeTeam('Общая группа');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);

        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-10-01', 100000, false, (int) $team->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-10-01', 250000, false, (int) $team->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment'), $this->monthlyPayload((int) $team->id, '2026-10-01'))
            ->assertOk()
            ->assertViewHas('outSum', '2500.00')
            ->assertViewHas('monthlyTeamId', (int) $team->id);
    }

    public function test_same_team_robokassa_init_after_switch_binds_payable_to_brother2(): void
    {
        $this->grantPermission($this->brother1, 'payment.method.robokassa');
        PaymentSystem::factory()->robokassa()->create(['partner_id' => $this->partner->id]);

        $team = $this->makeTeam('Общая группа');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);

        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-10-01', 100000, false, (int) $team->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-10-01', 250000, false, (int) $team->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $response = $this->post(route('payment.pay'), $this->monthlyPayload((int) $team->id, '2026-10-01'));
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('OutSum=2500.00', $location);
        $this->assertStringContainsString('Shp_userId='.$this->brother2->id, $location);

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame((int) $this->brother2->id, (int) $payable->user_id);
        $this->assertSame(250000, (int) $payable->amount_cents);
        $this->assertSame((int) $team->id, (int) ($payable->meta['team_id'] ?? 0));

        $intent = PaymentIntent::query()->latest('id')->first();
        $this->assertNotNull($intent);
        $this->assertSame((int) $this->brother2->id, (int) $intent->user_id);
        $meta = json_decode((string) $intent->meta, true);
        $this->assertSame((int) $this->brother1->id, (int) ($meta['actor_user_id'] ?? 0));
        $this->assertSame((int) $team->id, (int) ($meta['team_id'] ?? 0));
    }

    public function test_same_team_tbank_webhook_marks_brother2_paid_not_brother1(): void
    {
        Queue::fake();
        $this->grantPermission($this->brother1, 'payment.method.tbankCard');
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_FAM',
            'token_password' => 'PWD_FAM',
            'e2c_terminal_key' => 'E2C',
            'e2c_token_password' => 'E2CP',
        ]);

        $team = $this->makeTeam('Общая группа');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);
        $entity = $this->seedRegisteredLegalEntityForPartner(shopCode: 'SHOP-FAM');
        $this->bindTeamsToLegalEntity($entity, $team);

        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-11-01', 100000, false, (int) $team->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-11-01', 250000, false, (int) $team->id)->create();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return Http::response([
                    'Success' => true,
                    'PaymentId' => 901001,
                    'PaymentURL' => 'https://example.test/pay-family',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment.tinkoff.pay'), $this->monthlyPayload((int) $team->id, '2026-11-01'))
            ->assertRedirect();

        $intent = PaymentIntent::query()->latest('id')->first();
        $this->assertNotNull($intent);
        $this->assertSame((int) $this->brother2->id, (int) $intent->user_id);
        $this->assertSame(250000, (int) $intent->out_sum_cents);
        $intentMeta = json_decode((string) $intent->meta, true);
        $this->assertSame((int) $this->brother1->id, (int) ($intentMeta['actor_user_id'] ?? 0));

        app(TinkoffPaymentsService::class)->handleWebhook([
            'TerminalKey' => 'TERM_FAM',
            'OrderId' => (string) $intent->tbank_order_id,
            'Success' => true,
            'Status' => 'CONFIRMED',
            'PaymentId' => 901001,
            'Data' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $intent->payable_id,
                'user_id' => (string) $this->brother2->id,
                'month' => '2026-11-01',
                'team_id' => (string) $team->id,
            ],
            'Token' => 'skip',
        ], true);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->brother2->id,
            'team_id' => $team->id,
            'new_month' => '2026-11-01',
            'is_paid' => 1,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->brother1->id,
            'team_id' => $team->id,
            'new_month' => '2026-11-01',
            'is_paid' => 0,
        ]);

        $payment = Payment::query()->where('payment_number', '901001')->first();
        $this->assertNotNull($payment);
        $this->assertSame((int) $this->brother2->id, (int) $payment->user_id);
        $this->assertSame((int) $team->id, (int) $payment->team_id);
    }

    public function test_actor_without_paying_classes_gets_403_after_switch(): void
    {
        DB::table('permission_role')
            ->where('role_id', $this->brother1->role_id)
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId('paying.classes'))
            ->delete();

        $team = $this->makeTeam('Группа без права');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 100000, false, (int) $team->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment'), $this->monthlyPayload((int) $team->id, '2026-09-01'))
            ->assertForbidden();
    }

    public function test_club_fee_init_after_switch_stays_on_login_account(): void
    {
        $this->grantPermission($this->brother1, 'payment.method.robokassa');
        PaymentSystem::factory()->robokassa()->create(['partner_id' => $this->partner->id]);

        $team = $this->makeTeam('Общая группа');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment.pay'), [
            'outSum' => '500.00',
            'team_id' => $team->id,
        ])->assertRedirect();

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame('club_fee', (string) $payable->type);
        $this->assertSame((int) $this->brother1->id, (int) $payable->user_id);
    }

    public function test_parent_opens_cabinet_after_switch_and_posts_sibling_season_payload_without_403(): void
    {
        $team1 = $this->makeTeam('Группа брата 1');
        $team2 = $this->makeTeam('Группа брата 2');
        $this->attachStudent($this->brother1, $team1);
        $this->attachStudent($this->brother2, $team2);

        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-09-01', 100000, false, (int) $team1->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 250000, false, (int) $team2->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('family-active-student', $html);
        $this->assertStringContainsString('"user_id":'.$this->brother2->id, $html);
        $this->assertStringNotContainsString('"user_id":'.$this->brother1->id, $html);
        $this->assertStringContainsString('"team_id":'.$team2->id, $html);
        $this->assertStringContainsString('var dashboardStudentId = '.$this->brother2->id, $html);

        $this->post(route('payment'), $this->monthlyPayload((int) $team2->id, '2026-09-01'))
            ->assertOk()
            ->assertViewIs('payment.paymentUser')
            ->assertViewHas('outSum', '2500.00')
            ->assertDontSee('Доступ запрещён', false);
    }

    public function test_get_payment_after_switch_is_not_server_error(): void
    {
        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $response = $this->get('/payment');

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [403, 404, 405]);
    }

    public function test_viewing_brother2_cannot_pay_brother1_other_team(): void
    {
        $team1 = $this->makeTeam('Группа брата 1');
        $team2 = $this->makeTeam('Группа брата 2');
        $this->attachStudent($this->brother1, $team1);
        $this->attachStudent($this->brother2, $team2);

        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-09-01', 100000, false, (int) $team1->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 250000, false, (int) $team2->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment'), $this->monthlyPayload((int) $team1->id, '2026-09-01'))
            ->assertForbidden();
    }

    public function test_custom_payment_after_switch_opens_sibling_invoice_not_login_account(): void
    {
        $this->grantPermission($this->brother1, 'setPrices.customPayments.view');
        $team = $this->makeTeam('Общая группа');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);

        $own = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->brother1->id,
            'team_id' => $team->id,
            'date_start' => '2026-09-01',
            'date_end' => '2026-09-30',
            'amount_cents' => 11100,
            'is_paid' => 0,
        ]);
        $sibling = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->brother2->id,
            'team_id' => $team->id,
            'date_start' => '2026-09-01',
            'date_end' => '2026-09-30',
            'amount_cents' => 22200,
            'is_paid' => 0,
        ]);

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment'), [
            'payment_kind' => 'custom_payment',
            'custom_payment_id' => $sibling->id,
            'outSum' => '1.00',
        ])
            ->assertOk()
            ->assertViewHas('outSum', '222.00')
            ->assertViewHas('userPeriodPriceId', (int) $sibling->id);

        $this->post(route('payment'), [
            'payment_kind' => 'custom_payment',
            'custom_payment_id' => $own->id,
            'outSum' => '1.00',
        ])->assertNotFound();
    }

    public function test_lesson_package_after_switch_opens_sibling_assignment(): void
    {
        $this->grantPermission($this->brother1, CabinetLessonPackagePermission::FIXED);
        $team = $this->makeTeam('Общая группа');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);

        $package = LessonPackage::factory()->forPartner($this->partner->id)->create([
            'name' => 'Абонемент Вася',
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
        ]);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $this->brother2->id,
            'lesson_package_id' => $package->id,
            'team_id' => $team->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 33300,
            'is_paid' => false,
        ]);

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment'), [
            'payment_kind' => 'lesson_package',
            'user_lesson_package_id' => $ulp->id,
            'outSum' => '1.00',
        ])
            ->assertOk()
            ->assertViewHas('outSum', '333.00')
            ->assertViewHas('userLessonPackageId', (int) $ulp->id);
    }

    public function test_tbank_sbp_init_after_switch_binds_intent_to_brother2(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_SBP_FAM',
            'token_password' => 'PWD_SBP_FAM',
            'e2c_terminal_key' => 'E2C',
            'e2c_token_password' => 'E2CP',
        ]);
        $team = $this->makeTeam('Общая группа');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);
        $entity = $this->seedRegisteredLegalEntityForPartner(shopCode: 'SHOP-SBP-FAM');
        $this->bindTeamsToLegalEntity($entity, $team);

        UserPrice::factory()->forUserAndMonth((int) $this->brother1->id, '2026-11-01', 100000, false, (int) $team->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-11-01', 250000, false, (int) $team->id)->create();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return Http::response([
                    'Success' => true,
                    'PaymentId' => 901002,
                    'PaymentURL' => 'https://example.test/sbp-family',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment.tinkoff.sbp'), $this->monthlyPayload((int) $team->id, '2026-11-01'))
            ->assertRedirect();

        $intent = PaymentIntent::query()->latest('id')->first();
        $this->assertNotNull($intent);
        $this->assertSame((int) $this->brother2->id, (int) $intent->user_id);
        $this->assertSame('sbp_qr', (string) $intent->payment_method);
        $meta = json_decode((string) $intent->meta, true);
        $this->assertSame((int) $this->brother1->id, (int) ($meta['actor_user_id'] ?? 0));
    }

    public function test_foreign_partner_team_is_forbidden_after_switch(): void
    {
        $team = $this->makeTeam('Своя');
        $this->attachStudent($this->brother1, $team);
        $this->attachStudent($this->brother2, $team);
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 250000, false, (int) $team->id)->create();

        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'Чужая организация',
        ]);

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment'), $this->monthlyPayload((int) $foreignTeam->id, '2026-09-01'))
            ->assertForbidden();
    }

    public function test_monthly_without_team_id_returns_422_when_sibling_has_two_teams(): void
    {
        $teamA = $this->makeTeam('A');
        $teamB = $this->makeTeam('B');
        $this->attachStudent($this->brother2, $teamA);
        $this->attachStudent($this->brother2, $teamB);
        $this->attachStudent($this->brother1, $teamA);

        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 100000, false, (int) $teamA->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->brother2->id, '2026-09-01', 200000, false, (int) $teamB->id)->create();

        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->post(route('payment'), [
            'paymentDate' => 'Сентябрь 2026',
            'formatedPaymentDate' => '2026-09-01',
            'outSum' => '1.00',
        ])->assertStatus(422);
    }

    public function test_student_without_siblings_still_pays_own_month(): void
    {
        $lonely = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->brother1->role_id,
            'parent_id' => null,
            'is_enabled' => true,
        ]);
        $team = $this->makeTeam('Один');
        $this->attachStudent($lonely, $team);
        UserPrice::factory()->forUserAndMonth((int) $lonely->id, '2026-09-01', 180000, false, (int) $team->id)->create();

        $this->actingAs($lonely)
            ->post(route('payment'), $this->monthlyPayload((int) $team->id, '2026-09-01'))
            ->assertOk()
            ->assertViewHas('outSum', '1800.00');
    }

    public function test_switch_json_without_student_id_returns_422_on_field(): void
    {
        $this->actingAs($this->brother1);

        $this->postJson(route('cabinet.active-student.switch'), [], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_user_id']);
    }

    public function test_tinkoff_json_custom_payment_without_id_returns_422_on_field(): void
    {
        $this->actingAs($this->brother1);
        $this->switchTo($this->brother2);

        $this->postJson(route('payment.tinkoff.pay'), [
            'payment_kind' => 'custom_payment',
        ], $this->jsonHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['custom_payment_id']);
    }

    public function test_guest_cannot_open_payment_or_switch(): void
    {
        Auth::logout();

        $this->post(route('payment'), $this->monthlyPayload(1, '2026-09-01'))
            ->assertRedirect();
        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $this->brother2->id,
        ])->assertRedirect();
    }
}
