<?php

namespace Tests\Feature\Crm\Payments;

use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\Payments\PaymentLedgerTeamResolver;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Multi-team monthly payment: access control, endpoint contracts, non-AJAX form POST, report filter.
 *
 * @see MultiTeamMonthlyPaymentFlowTest — бизнес-поток (resolver, webhook, ledger)
 */
final class MultiTeamMonthlyPaymentAccessFeatureTest extends CrmTestCase
{
    private Team $teamA;

    private Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        [$this->teamA, $this->teamB] = $this->seedMultiTeamMonthlyPrices('2027-03-01');
    }

    /**
     * @return array{0: Team, 1: Team}
     */
    private function seedMultiTeamMonthlyPrices(string $month): array
    {
        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'MT-Alpha',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'MT-Beta',
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($this->user, (int) $teamA->id);
        $sync->attachTeamForStudent($this->user, (int) $teamB->id);

        UserPrice::factory()->forUserAndMonth((int) $this->user->id, $month, 3100, false, (int) $teamA->id)->create();
        UserPrice::factory()->forUserAndMonth((int) $this->user->id, $month, 4200, false, (int) $teamB->id)->create();

        return [$teamA, $teamB];
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantAllPaymentPermissions(): void
    {
        foreach ([
            'paying.classes',
            'payment.method.robokassa',
            'payment.method.tbankCard',
            'payment.method.tbankSBP',
        ] as $perm) {
            $this->grantPermission($perm);
        }
    }

    private function seedRobokassa(): void
    {
        PaymentSystem::factory()
            ->robokassa()
            ->create(['partner_id' => $this->partner->id]);
    }

    private function seedTbank(): void
    {
        $this->partner->tinkoff_partner_id = 'SHOP-ACCESS';
        $this->partner->save();

        PaymentSystem::create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'test_mode' => 1,
            'is_enabled' => true,
            'settings' => [
                'terminal_key' => 'TERM_ACCESS',
                'token_password' => 'PWD_ACCESS',
                'e2c_terminal_key' => 'E2C',
                'e2c_token_password' => 'E2CP',
            ],
        ]);
    }

    private function monthlyPaymentPayload(int $teamId): array
    {
        return [
            'paymentDate' => 'Март 2027',
            'formatedPaymentDate' => '2027-03-01',
            'team_id' => $teamId,
            'outSum' => '1.00',
        ];
    }

    /* ============================================================
     * A. Guest — redirect на login
     * ============================================================ */

    public function test_guest_post_payment_index_redirects_to_login(): void
    {
        auth()->logout();

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $this->teamA->id))
            ->assertRedirect(route('login'));
    }

    public function test_guest_post_payment_pay_redirects_to_login(): void
    {
        auth()->logout();

        $this->post(route('payment.pay'), $this->monthlyPaymentPayload((int) $this->teamA->id))
            ->assertRedirect(route('login'));
    }

    public function test_guest_post_tinkoff_pay_redirects_to_login(): void
    {
        auth()->logout();

        $this->post(route('payment.tinkoff.pay'), $this->monthlyPaymentPayload((int) $this->teamA->id))
            ->assertRedirect(route('login'));
    }

    public function test_guest_post_tinkoff_sbp_redirects_to_login(): void
    {
        auth()->logout();

        $this->post(route('payment.tinkoff.sbp'), $this->monthlyPaymentPayload((int) $this->teamA->id))
            ->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_payments_report_json(): void
    {
        auth()->logout();

        $this->getJson(route('payments.getPayments', ['draw' => 1]))
            ->assertUnauthorized();
    }

    /* ============================================================
     * B. Forbidden без нужных прав
     * ============================================================ */

    public function test_user_without_paying_classes_gets_403_on_payment_index(): void
    {
        $denied = $this->createUserWithoutPermission('paying.classes', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $this->teamA->id))
            ->assertForbidden();
    }

    public function test_user_without_robokassa_method_gets_403_on_payment_pay(): void
    {
        $this->grantPermission('paying.classes');
        $this->seedRobokassa();

        $denied = $this->createUserWithoutPermission('payment.method.robokassa', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($denied, (int) $this->teamA->id);
        UserPrice::factory()->forUserAndMonth((int) $denied->id, '2027-03-01', 3100, false, (int) $this->teamA->id)->create();

        $this->post(route('payment.pay'), [
            'formatedPaymentDate' => '2027-03-01',
            'team_id' => $this->teamA->id,
        ])->assertForbidden();
    }

    public function test_user_without_tbank_card_gets_403_on_tinkoff_pay(): void
    {
        $this->grantPermission('paying.classes');
        $this->seedTbank();

        $denied = $this->createUserWithoutPermission('payment.method.tbankCard', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->post(route('payment.tinkoff.pay'), $this->monthlyPaymentPayload((int) $this->teamA->id))
            ->assertForbidden();
    }

    public function test_user_without_tbank_sbp_gets_403_on_tinkoff_sbp(): void
    {
        $this->grantPermission('paying.classes');
        $this->seedTbank();

        $denied = $this->createUserWithoutPermission('payment.method.tbankSBP', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->post(route('payment.tinkoff.sbp'), $this->monthlyPaymentPayload((int) $this->teamA->id))
            ->assertForbidden();
    }

    public function test_user_without_reports_view_gets_403_on_get_payments_json(): void
    {
        $denied = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payments.getPayments', ['draw' => 1]))
            ->assertForbidden();
    }

    /* ============================================================
     * C. Authorized — POST /payment (витрина)
     * ============================================================ */

    public function test_authorized_user_with_paying_classes_gets_200_on_payment_index(): void
    {
        $this->grantAllPaymentPermissions();
        $this->seedRobokassa();

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $this->teamA->id))
            ->assertOk()
            ->assertViewIs('payment.paymentUser')
            ->assertViewHas('monthlyTeamId', (int) $this->teamA->id)
            ->assertViewHas('monthlyTeamTitle', 'MT-Alpha')
            ->assertSee('MT-Alpha', false)
            ->assertSee('Группа', false);
    }

    public function test_payment_index_returns_422_without_team_id_when_multiple_teams_same_month(): void
    {
        $this->grantPermission('paying.classes');

        $this->post(route('payment'), [
            'paymentDate' => 'Март 2027',
            'formatedPaymentDate' => '2027-03-01',
            'outSum' => '1.00',
        ])->assertStatus(422);
    }

    public function test_payment_index_returns_403_for_team_user_not_member_of(): void
    {
        $this->grantPermission('paying.classes');

        $foreignTeam = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Чужая']);

        $this->post(route('payment'), $this->monthlyPaymentPayload((int) $foreignTeam->id))
            ->assertForbidden();
    }

    /* ============================================================
     * D. Non-AJAX POST → 302 redirect, payable/intent с team_id (не пустой 200)
     * ============================================================ */

    public function test_robokassa_non_ajax_post_creates_payable_with_team_id_and_redirects(): void
    {
        $this->grantAllPaymentPermissions();
        $this->seedRobokassa();

        $response = $this->post(route('payment.pay'), $this->monthlyPaymentPayload((int) $this->teamB->id));

        $response->assertStatus(302);
        $response->assertRedirect();
        $this->assertNotSame(200, $response->getStatusCode());

        $payable = Payable::query()->latest('id')->first();
        $this->assertNotNull($payable);
        $this->assertSame('monthly_fee', (string) $payable->type);
        $this->assertSame((int) $this->teamB->id, (int) ($payable->meta['team_id'] ?? 0));
        $this->assertSame('4200.00', (string) $payable->amount);

        $intent = PaymentIntent::query()->latest('id')->first();
        $this->assertNotNull($intent);
        $meta = json_decode((string) $intent->meta, true);
        $this->assertSame((int) $this->teamB->id, (int) ($meta['team_id'] ?? 0));
    }

    public function test_tinkoff_card_non_ajax_post_creates_payable_with_team_id_and_redirects(): void
    {
        $this->grantAllPaymentPermissions();
        $this->seedTbank();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return Http::response([
                    'Success' => true,
                    'PaymentId' => 770001,
                    'PaymentURL' => 'https://example.test/pay-access',
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $response = $this->post(route('payment.tinkoff.pay'), $this->monthlyPaymentPayload((int) $this->teamA->id));

        $response->assertStatus(302);
        $response->assertRedirect('https://example.test/pay-access');

        $payable = Payable::query()->latest('id')->first();
        $this->assertSame((int) $this->teamA->id, (int) ($payable->meta['team_id'] ?? 0));
    }

    public function test_tinkoff_sbp_non_ajax_post_creates_payable_with_team_id_and_redirects_to_qr(): void
    {
        $this->grantAllPaymentPermissions();
        $this->seedTbank();

        Http::fake(function ($request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return Http::response([
                    'Success' => true,
                    'PaymentId' => 770002,
                    'PaymentURL' => null,
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $response = $this->post(route('payment.tinkoff.sbp'), $this->monthlyPaymentPayload((int) $this->teamA->id));

        $response->assertStatus(302);
        $response->assertRedirect(route('tinkoff.qr', 770002));

        $payable = Payable::query()->latest('id')->first();
        $this->assertSame((int) $this->teamA->id, (int) ($payable->meta['team_id'] ?? 0));
    }

    public function test_tinkoff_sbp_init_data_includes_team_id_for_monthly_fee(): void
    {
        $this->grantAllPaymentPermissions();
        $this->seedTbank();

        $capturedData = null;
        Http::fake(function ($request) use (&$capturedData) {
            if (str_contains($request->url(), '/v2/Init')) {
                $capturedData = $request->data()['DATA'] ?? null;

                return Http::response([
                    'Success' => true,
                    'PaymentId' => 770003,
                ], 200);
            }

            return Http::response(['Success' => false], 500);
        });

        $this->post(route('payment.tinkoff.sbp'), $this->monthlyPaymentPayload((int) $this->teamB->id))
            ->assertRedirect();

        $this->assertIsArray($capturedData);
        $this->assertSame((string) $this->teamB->id, (string) ($capturedData['team_id'] ?? ''));
    }

    /* ============================================================
     * E. Robokassa ResultURL — restore payable по meta.team_id
     * ============================================================ */

    public function test_robokassa_result_restores_payable_by_team_id_from_intent_meta(): void
    {
        Queue::fake();
        $this->seedRobokassa();

        $outSum = '4200.00';
        $month = '2027-03-01';

        Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => $outSum,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => $month,
            'meta' => ['month' => $month, 'team_id' => (int) $this->teamA->id],
            'created_at' => now(),
        ]);

        $payableB = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => $outSum,
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => $month,
            'meta' => ['month' => $month, 'team_id' => (int) $this->teamB->id],
            'created_at' => now(),
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => null,
            'provider' => 'robokassa',
            'status' => 'pending',
            'out_sum' => $outSum,
            'payment_date' => $month,
            'meta' => json_encode([
                'user_name' => (string) $this->user->name,
                'team_id' => (int) $this->teamB->id,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        $providerInvId = 1000000000 + (int) $intent->id;
        $intent->provider_inv_id = $providerInvId;
        $intent->save();

        $password2 = 'pass2';
        $signature = strtoupper(md5("{$outSum}:{$providerInvId}:{$password2}:Shp_paymentDate={$month}:Shp_userId={$this->user->id}"));

        $this->get(route('payment.result', [
            'OutSum' => $outSum,
            'InvId' => $providerInvId,
            'SignatureValue' => $signature,
            'Shp_paymentDate' => $month,
            'Shp_userId' => (string) $this->user->id,
        ]))->assertOk();

        $intent->refresh();
        $this->assertSame('paid', (string) $intent->status);
        $this->assertSame((int) $payableB->id, (int) $intent->payable_id);

        $payment = Payment::query()->where('payment_number', (string) $providerInvId)->first();
        $this->assertNotNull($payment);
        $this->assertSame((int) $this->teamB->id, (int) $payment->team_id);
        $this->assertSame('MT-Beta', (string) $payment->team_title);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->user->id,
            'team_id' => $this->teamB->id,
            'new_month' => $month,
            'is_paid' => 1,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->user->id,
            'team_id' => $this->teamA->id,
            'new_month' => $month,
            'is_paid' => 0,
        ]);
    }

    /* ============================================================
     * F. PaymentLedgerTeamResolver
     * ============================================================ */

    public function test_payment_ledger_team_resolver_uses_payable_meta_team_id_for_monthly_fee(): void
    {
        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => '100.00',
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => '2027-03-01',
            'meta' => ['team_id' => (int) $this->teamA->id],
        ]);

        $snapshot = app(PaymentLedgerTeamResolver::class)->resolveFromPayable($payable, $this->user);

        $this->assertSame((int) $this->teamA->id, $snapshot['team_id']);
        $this->assertSame('MT-Alpha', $snapshot['team_title']);
    }

    public function test_payment_ledger_team_resolver_falls_back_to_primary_team_when_meta_team_id_missing(): void
    {
        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => '100.00',
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => '2027-03-01',
            'meta' => ['month' => '2027-03-01'],
        ]);

        $snapshot = app(PaymentLedgerTeamResolver::class)->resolveFromPayable($payable, $this->user->fresh());

        $this->assertNotNull($snapshot['team_id']);
        $this->assertNotSame('', $snapshot['team_title']);
    }

    public function test_payment_ledger_team_resolver_returns_null_team_id_for_club_fee(): void
    {
        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'club_fee',
            'amount' => '500.00',
            'currency' => 'RUB',
            'status' => 'pending',
            'meta' => [],
        ]);

        $snapshot = app(PaymentLedgerTeamResolver::class)->resolveFromPayable($payable, $this->user);

        $this->assertNull($snapshot['team_id']);
    }

    /* ============================================================
     * G. Admin report JSON — filter_team_id + team_title
     * ============================================================ */

    private function asAdminWithReportSession(): void
    {
        $this->asAdmin();
        // attachTeamForStudent в setUp загружает role до смены role_id — сбрасываем кэш.
        $this->user->unsetRelation('role');
        $this->actingAs($this->user->fresh());
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function adminPaymentReportRows(array $query = []): array
    {
        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payments.getPayments', array_merge(['draw' => 1], $query)))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }

    public function test_admin_get_payments_json_with_filter_team_id_returns_200_and_paid_team_title(): void
    {
        $this->asAdminWithReportSession();

        $student = User::factory()->create(['partner_id' => $this->partner->id]);
        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $this->teamA->id);
        $sync->attachTeamForStudent($student, (int) $this->teamB->id);

        $payment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_id' => $this->teamA->id,
            'team_title' => 'MT-Alpha',
            'summ' => 3100,
            'payment_month' => '2027-03-01',
        ]);

        $rows = collect($this->adminPaymentReportRows([
            'filter_team_id' => $this->teamA->id,
            'filter_user_id' => $student->id,
        ]));

        $match = $rows->first(fn ($r) => (int) ($r['id'] ?? 0) === (int) $payment->id);
        $this->assertNotNull($match);
        $this->assertSame('MT-Alpha', html_entity_decode((string) ($match['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function test_admin_get_payments_json_filter_team_id_excludes_payment_for_other_paid_team(): void
    {
        $this->asAdminWithReportSession();

        $student = User::factory()->create(['partner_id' => $this->partner->id]);
        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $this->teamA->id);
        $sync->attachTeamForStudent($student, (int) $this->teamB->id);

        $payment = Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_id' => $this->teamA->id,
            'team_title' => 'MT-Alpha',
            'summ' => 3100,
        ]);

        $rows = collect($this->adminPaymentReportRows([
            'filter_team_id' => $this->teamB->id,
            'filter_user_id' => $student->id,
        ]));

        $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertNotContains((int) $payment->id, $ids);
    }

    public function test_admin_payments_report_page_returns_200(): void
    {
        $this->asAdminWithReportSession();

        $this->get(route('payments'))->assertOk();
    }

    public function test_admin_payments_total_json_returns_200(): void
    {
        $this->asAdminWithReportSession();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.payments.total'))
            ->assertOk();
    }

    /* ============================================================
     * H. T‑Bank webhook HTTP endpoint (публичный, не 500)
     * ============================================================ */

    public function test_tbank_webhook_post_without_order_returns_200_ok_not_500(): void
    {
        $this->post('/webhooks/tinkoff/payments', [
            'Status' => 'CONFIRMED',
            'PaymentId' => 999999,
        ])->assertOk()->assertSee('OK');
    }

    public function test_tbank_webhook_confirmed_multi_team_writes_payment_team_id_via_http(): void
    {
        Queue::fake();

        PaymentSystem::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'tbank',
            'settings' => [
                'terminal_key' => 'TERM_HTTP',
                'token_password' => 'PWD_HTTP',
                'e2c_terminal_key' => 'E2C',
                'e2c_token_password' => 'E2CP',
            ],
            'test_mode' => true,
            'is_enabled' => true,
        ]);

        TinkoffPayment::create([
            'order_id' => 'order-http-multi',
            'partner_id' => $this->partner->id,
            'amount' => 310000,
            'method' => 'card',
            'status' => 'FORM',
        ]);

        $payable = Payable::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'type' => 'monthly_fee',
            'amount' => '3100.00',
            'currency' => 'RUB',
            'status' => 'pending',
            'month' => '2027-03-01',
            'meta' => ['month' => '2027-03-01', 'team_id' => (int) $this->teamA->id],
        ]);

        $intent = PaymentIntent::create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'pending',
            'out_sum' => '3100.00',
            'payment_date' => '2027-03-01',
            'tbank_order_id' => 'order-http-multi',
            'tbank_payment_id' => 880099,
            'provider_inv_id' => 880099,
        ]);

        $payload = [
            'TerminalKey' => 'TERM_HTTP',
            'OrderId' => 'order-http-multi',
            'PaymentId' => 880099,
            'Status' => 'CONFIRMED',
            'Success' => true,
            'Data' => [
                'payment_intent_id' => (string) $intent->id,
                'payable_id' => (string) $payable->id,
                'user_id' => (string) $this->user->id,
                'team_id' => (string) $this->teamA->id,
            ],
        ];

        $cfgPassword = 'PWD_HTTP';
        $payload['Token'] = \App\Services\Tinkoff\TinkoffSignature::makeToken($payload, $cfgPassword);

        $this->post('/webhooks/tinkoff/payments', $payload)
            ->assertOk()
            ->assertSee('OK');

        $payment = Payment::query()->where('payment_number', '880099')->first();
        $this->assertNotNull($payment);
        $this->assertSame((int) $this->teamA->id, (int) $payment->team_id);
    }

    /* ============================================================
     * I. Success / fail pages (auth)
     * ============================================================ */

    public function test_payment_success_and_fail_pages_return_200_for_authenticated_user(): void
    {
        $this->get(route('payment.success'))->assertOk();
        $this->get(route('payment.fail'))->assertOk();
    }
}
