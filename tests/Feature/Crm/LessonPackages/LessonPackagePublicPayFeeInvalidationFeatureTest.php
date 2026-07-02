<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\Payable;
use App\Models\PaymentIntent;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackagePublicPayLink;
use App\Services\TeamUserSyncService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Публичная оплата абонемента: инвалидация T‑Bank-платежа при смене fee_amount,
 * контроль доступа endpoint'ов, AJAX/non-AJAX контракты update assignment.
 */
final class LessonPackagePublicPayFeeInvalidationFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
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

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    private function seedTbankForPartner(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_ULP_FEE_INV',
            'token_password' => 'PWD_ULP_FEE_INV',
            'e2c_terminal_key' => 'E2C_TERM',
            'e2c_token_password' => 'E2C_PWD',
        ]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-ULP-FEE-INV')
            ->create(['is_default' => true]);

        Partner::query()->whereKey($this->partner->id)->update([
            'tinkoff_partner_id' => null,
            'tax_id' => null,
        ]);
        $this->partner->refresh();
    }

    private function attachStudentToTeam(?Team $team = null): Team
    {
        $team ??= Team::factory()->create(['partner_id' => $this->partner->id]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        return $team;
    }

    private function createUnpaidAssignment(float $fee = 500.0, ?int $teamId = null): UserLessonPackage
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'ULP fee invalidation',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'team_id' => $teamId,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'fee_amount' => number_format($fee, 2, '.', ''),
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);
    }

    private function seedAssignmentWithTeam(float $fee = 500.0): UserLessonPackage
    {
        $team = $this->attachStudentToTeam();

        return $this->createUnpaidAssignment($fee, (int) $team->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultInitPayload(\Illuminate\Http\Client\Request $request, string $paymentId): array
    {
        $body = $request->data();

        return [
            'Success' => true,
            'PaymentId' => $paymentId,
            'PaymentURL' => 'https://pay.example.test/',
            'OrderId' => (string) ($body['OrderId'] ?? 'order-x'),
            'ErrorCode' => '0',
        ];
    }

    private function initResponse(\Illuminate\Http\Client\Request $request, string $paymentId): array
    {
        return $this->defaultInitPayload($request, $paymentId);
    }

    /**
     * @param  callable(\Illuminate\Http\Client\Request): array|null  $responder
     */
    private function fakeTbankHttp(callable $responder): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($responder) {
            $custom = $responder($request);
            if ($custom !== null) {
                return Http::response($custom, 200);
            }

            return Http::response(['Success' => false, 'Message' => 'unexpected'], 500);
        });
    }

    private function issuePublicPayToken(UserLessonPackage $assignment): string
    {
        $this->grantPermission('lessonPackages.view');

        $issue = $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $assignment->id]),
            [],
            $this->ajaxHeaders()
        )->assertOk();

        $url = (string) $issue->json('url');
        $token = substr($url, strrpos($url, '/') + 1);
        $this->assertSame(64, strlen($token));

        return $token;
    }

    private function currentPublicPayToken(UserLessonPackage $assignment): string
    {
        $token = (string) UserLessonPackagePublicPayLink::query()
            ->where('user_lesson_package_id', $assignment->id)
            ->value('token');

        $this->assertSame(64, strlen($token));

        return $token;
    }

    /**
     * @return list<array{method: string, url: string, acceptJson?: bool}>
     */
    private function publicPayEndpoints(string $token): array
    {
        return [
            ['method' => 'GET', 'url' => route('ulp.public.pay', ['token' => $token])],
            ['method' => 'GET', 'url' => route('ulp.public.pay.qr.json', ['token' => $token]), 'acceptJson' => true],
            ['method' => 'GET', 'url' => route('ulp.public.pay.qr.payload', ['token' => $token]), 'acceptJson' => true],
            ['method' => 'GET', 'url' => route('ulp.public.pay.qr.state', ['token' => $token]), 'acceptJson' => true],
        ];
    }

    // -------------------------------------------------------------------------
    // Инвалидация при смене fee_amount
    // -------------------------------------------------------------------------

    public function test_public_pay_reinits_when_fee_amount_changed_after_first_open(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6500.0);

        $initAmounts = [];
        $cancelledPaymentIds = [];
        $initCounter = 0;

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) use (&$initAmounts, &$cancelledPaymentIds, &$initCounter) {
            $url = $request->url();
            if (str_contains($url, '/v2/Init')) {
                $initAmounts[] = (int) ($request->data()['Amount'] ?? 0);
                $initCounter++;

                return $this->initResponse($request, $initCounter === 1 ? '7700650001' : '7700600001');
            }
            if (str_contains($url, '/v2/Cancel')) {
                $cancelledPaymentIds[] = (string) ($request->data()['PaymentId'] ?? '');

                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'CANCELED'];
            }
            if (str_contains($url, '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }

            return null;
        });

        $oldToken = $this->issuePublicPayToken($ulp);

        $this->get(route('ulp.public.pay', ['token' => $oldToken]))
            ->assertOk()
            ->assertSee('6 500', false);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]),
            ['fee_amount' => 6000],
            $this->ajaxHeaders()
        )->assertOk()->assertJsonPath('public_pay_url_rotated', true);

        $newToken = $this->currentPublicPayToken($ulp);
        $this->assertNotSame($oldToken, $newToken);

        $this->get(route('ulp.public.pay', ['token' => $oldToken]))->assertNotFound();

        $this->get(route('ulp.public.pay', ['token' => $newToken]))
            ->assertOk()
            ->assertSee('6 000', false);

        $this->assertSame([650000, 600000], $initAmounts);
        $this->assertSame(['7700650001'], $cancelledPaymentIds);
        $this->assertDatabaseHas('user_lesson_package_public_pay_links', [
            'user_lesson_package_id' => $ulp->id,
            'tinkoff_payment_id' => '7700600001',
        ]);
    }

    public function test_update_assignment_fee_invalidates_public_pay_binding_without_reopening_page(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6500.0);

        $cancelledPaymentIds = [];

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) use (&$cancelledPaymentIds) {
            if (str_contains($request->url(), '/v2/Init')) {
                return $this->initResponse($request, '7700650002');
            }
            if (str_contains($request->url(), '/v2/Cancel')) {
                $cancelledPaymentIds[] = (string) ($request->data()['PaymentId'] ?? '');

                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'CANCELED'];
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }

            return null;
        });

        $oldToken = $this->issuePublicPayToken($ulp);
        $this->get(route('ulp.public.pay', ['token' => $oldToken]))->assertOk();

        $link = UserLessonPackagePublicPayLink::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();
        $oldPayableId = (int) $link->payable_id;
        $oldIntentId = (int) $link->payment_intent_id;

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]),
            ['fee_amount' => 6000],
            $this->ajaxHeaders()
        )->assertOk()->assertJsonPath('success', true)->assertJsonPath('public_pay_url_rotated', true);

        $this->assertNotSame($oldToken, $this->currentPublicPayToken($ulp));

        $link->refresh();
        $this->assertNull($link->tinkoff_payment_id);
        $this->assertNull($link->payment_intent_id);
        $this->assertNull($link->payable_id);
        $this->assertSame(['7700650002'], $cancelledPaymentIds);
        $this->assertDatabaseHas('payables', ['id' => $oldPayableId, 'status' => 'cancelled']);
        $this->assertDatabaseHas('payment_intents', ['id' => $oldIntentId, 'status' => 'cancelled']);
    }

    public function test_public_pay_does_not_reinit_when_fee_amount_unchanged(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6000.0);

        $initCount = 0;

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) use (&$initCount) {
            if (str_contains($request->url(), '/v2/Init')) {
                $initCount++;

                return $this->initResponse($request, '7700600003');
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }

            return null;
        });

        $token = $this->issuePublicPayToken($ulp);
        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();
        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();

        $this->assertSame(1, $initCount);
        Http::assertNotSent(fn (\Illuminate\Http\Client\Request $r) => str_contains($r->url(), '/v2/Cancel'));
    }

    public function test_public_pay_reinits_on_page_open_when_fee_changed_directly_in_database(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6500.0);

        $initAmounts = [];

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) use (&$initAmounts) {
            if (str_contains($request->url(), '/v2/Init')) {
                $initAmounts[] = (int) ($request->data()['Amount'] ?? 0);

                return $this->initResponse(
                    $request,
                    count($initAmounts) === 1 ? '7700650010' : '7700600010'
                );
            }
            if (str_contains($request->url(), '/v2/Cancel')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'CANCELED'];
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }

            return null;
        });

        $token = $this->issuePublicPayToken($ulp);
        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();

        UserLessonPackage::query()->whereKey($ulp->id)->update(['fee_amount' => '6000.00']);

        $this->get(route('ulp.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertSee('6 000', false);

        $this->assertSame([650000, 600000], $initAmounts);
    }

    public function test_update_assignment_fee_does_not_invalidate_when_assignment_paid(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createUnpaidAssignment(6500.0);
        UserLessonPackage::query()->whereKey($ulp->id)->update(['is_paid' => true]);

        $token = bin2hex(random_bytes(32));
        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'expires_at' => now()->addDay(),
            'tinkoff_payment_id' => '7700650099',
        ]);

        Http::fake();

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]),
            ['fee_amount' => 6000],
            $this->ajaxHeaders()
        )->assertStatus(422)->assertJsonValidationErrors(['fee_amount']);

        $link = UserLessonPackagePublicPayLink::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();
        $this->assertSame('7700650099', (string) $link->tinkoff_payment_id);
        Http::assertNothingSent();
    }

    public function test_public_pay_does_not_invalidate_when_bank_payment_confirmed(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6500.0);
        $token = bin2hex(random_bytes(32));

        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'expires_at' => now()->addDay(),
            'tinkoff_payment_id' => '7700650088',
        ]);

        TinkoffPayment::query()->create([
            'order_id' => 'order-confirmed-ulp',
            'partner_id' => $this->partner->id,
            'amount' => 650000,
            'method' => 'sbp',
            'status' => 'NEW',
            'tinkoff_payment_id' => '7700650088',
        ]);

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'CONFIRMED'];
            }

            return null;
        });

        $this->get(route('ulp.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertViewIs('payment.ulp-public-status')
            ->assertSee('Оплата получена', false);

        Http::assertNotSent(fn (\Illuminate\Http\Client\Request $r) => str_contains($r->url(), '/v2/Cancel'));
    }

    public function test_public_pay_qr_endpoints_use_fresh_payment_after_fee_change(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6500.0);

        $qrPaymentIds = [];
        $initCounter = 0;

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) use (&$qrPaymentIds, &$initCounter) {
            $url = $request->url();
            if (str_contains($url, '/v2/Init')) {
                $initCounter++;
                $paymentId = $initCounter === 1 ? '7700650077' : '7700600077';

                return $this->initResponse($request, $paymentId);
            }
            if (str_contains($url, '/v2/Cancel')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'CANCELED'];
            }
            if (str_contains($url, '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }
            if (str_contains($url, '/v2/GetQr')) {
                $qrPaymentIds[] = (string) ($request->data()['PaymentId'] ?? '');

                return ['Success' => true, 'ErrorCode' => '0', 'Data' => 'qr-data'];
            }

            return null;
        });

        $oldToken = $this->issuePublicPayToken($ulp);
        $this->get(route('ulp.public.pay', ['token' => $oldToken]))->assertOk();

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]),
            ['fee_amount' => 6000],
            $this->ajaxHeaders()
        )->assertOk()->assertJsonPath('public_pay_url_rotated', true);

        $newToken = $this->currentPublicPayToken($ulp);

        $this->getJson(route('ulp.public.pay.qr.json', ['token' => $newToken]))->assertOk();
        $this->getJson(route('ulp.public.pay.qr.payload', ['token' => $newToken]))->assertOk();

        $this->assertContains('7700600077', $qrPaymentIds);
        $this->assertNotContains('7700650077', array_slice($qrPaymentIds, -1));
    }

    public function test_public_pay_reinits_when_bank_get_state_amount_differs_from_fee_amount(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6000.0);

        $initAmounts = [];
        $initCounter = 0;

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) use (&$initAmounts, &$initCounter) {
            if (str_contains($request->url(), '/v2/Init')) {
                $initAmounts[] = (int) ($request->data()['Amount'] ?? 0);
                $initCounter++;

                return $this->initResponse(
                    $request,
                    (int) ($request->data()['Amount'] ?? 0) === 650000 ? '7700650200' : '7700600200'
                );
            }
            if (str_contains($request->url(), '/v2/Cancel')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'CANCELED'];
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return [
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Status' => 'NEW',
                    'Amount' => 650000,
                ];
            }

            return null;
        });

        $token = bin2hex(random_bytes(32));
        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'expires_at' => now()->addDay(),
            'tinkoff_payment_id' => '7700650200',
        ]);

        TinkoffPayment::query()->create([
            'order_id' => 'order-bank-mismatch',
            'partner_id' => $this->partner->id,
            'amount' => 600000,
            'method' => 'sbp',
            'status' => 'NEW',
            'tinkoff_payment_id' => '7700650200',
        ]);

        $this->get(route('ulp.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertSee('6 000', false);

        $this->assertSame([600000], $initAmounts);
        $this->assertDatabaseHas('user_lesson_package_public_pay_links', [
            'user_lesson_package_id' => $ulp->id,
            'tinkoff_payment_id' => '7700600200',
        ]);
    }

    public function test_update_assignment_invalidates_when_fee_unchanged_but_payment_amount_mismatch(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6000.0);
        $token = bin2hex(random_bytes(32));

        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'expires_at' => now()->addDay(),
            'tinkoff_payment_id' => '7700650098',
        ]);

        TinkoffPayment::query()->create([
            'order_id' => 'order-stale-ulp',
            'partner_id' => $this->partner->id,
            'amount' => 650000,
            'method' => 'sbp',
            'status' => 'NEW',
            'tinkoff_payment_id' => '7700650098',
        ]);

        $cancelledPaymentIds = [];

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) use (&$cancelledPaymentIds) {
            if (str_contains($request->url(), '/v2/Cancel')) {
                $cancelledPaymentIds[] = (string) ($request->data()['PaymentId'] ?? '');

                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'CANCELED'];
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }

            return null;
        });

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]),
            ['fee_amount' => 6000],
            $this->ajaxHeaders()
        )->assertOk();

        $link = UserLessonPackagePublicPayLink::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();
        $this->assertNull($link->tinkoff_payment_id);
        $this->assertSame(['7700650098'], $cancelledPaymentIds);
    }

    public function test_cancel_failure_still_clears_binding_and_allows_reinit(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6500.0);

        $initCounter = 0;

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) use (&$initCounter) {
            if (str_contains($request->url(), '/v2/Init')) {
                $initCounter++;

                return $this->initResponse($request, $initCounter === 1 ? '7700650066' : '7700600066');
            }
            if (str_contains($request->url(), '/v2/Cancel')) {
                return ['Success' => false, 'Message' => 'cancel failed'];
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }

            return null;
        });

        $oldToken = $this->issuePublicPayToken($ulp);
        $this->get(route('ulp.public.pay', ['token' => $oldToken]))->assertOk();

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]),
            ['fee_amount' => 6000],
            $this->ajaxHeaders()
        )->assertOk()->assertJsonPath('public_pay_url_rotated', true);

        $link = UserLessonPackagePublicPayLink::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();
        $this->assertNull($link->tinkoff_payment_id);

        $newToken = $this->currentPublicPayToken($ulp);

        $this->get(route('ulp.public.pay', ['token' => $newToken]))
            ->assertOk()
            ->assertSee('6 000', false);

        $this->assertSame(2, $initCounter);
    }

    public function test_bank_terminal_failure_status_clears_binding_without_cancel_call(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6500.0);
        $token = bin2hex(random_bytes(32));

        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'expires_at' => now()->addDay(),
            'tinkoff_payment_id' => '7700650055',
        ]);

        TinkoffPayment::query()->create([
            'order_id' => 'order-deadline-ulp',
            'partner_id' => $this->partner->id,
            'amount' => 650000,
            'method' => 'sbp',
            'status' => 'NEW',
            'tinkoff_payment_id' => '7700650055',
        ]);

        $initCount = 0;
        $getStateCalls = 0;

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) use (&$initCount, &$getStateCalls) {
            if (str_contains($request->url(), '/v2/Init')) {
                $initCount++;

                return $this->initResponse($request, '7700650056');
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                $getStateCalls++;

                return [
                    'Success' => true,
                    'ErrorCode' => '0',
                    'Status' => $getStateCalls === 1 ? 'DEADLINE_EXPIRED' : 'NEW',
                ];
            }

            return null;
        });

        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();

        Http::assertNotSent(fn (\Illuminate\Http\Client\Request $r) => str_contains($r->url(), '/v2/Cancel'));

        $link = UserLessonPackagePublicPayLink::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();
        $this->assertSame('7700650056', (string) $link->tinkoff_payment_id);
        $this->assertSame(1, $initCount);

        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();
        $this->assertSame(1, $initCount);
    }

    public function test_marking_payables_and_intents_cancelled_on_invalidation(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6500.0);

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return $this->initResponse($request, '7700650033');
            }
            if (str_contains($request->url(), '/v2/Cancel')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'CANCELED'];
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }

            return null;
        });

        $token = $this->issuePublicPayToken($ulp);
        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();

        $link = UserLessonPackagePublicPayLink::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();
        $payable = Payable::query()->findOrFail((int) $link->payable_id);
        $intent = PaymentIntent::query()->findOrFail((int) $link->payment_intent_id);
        $tp = TinkoffPayment::query()
            ->where('tinkoff_payment_id', (string) $link->tinkoff_payment_id)
            ->firstOrFail();

        $this->assertSame('pending', (string) $payable->status);
        $this->assertSame('pending', (string) $intent->status);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]),
            ['fee_amount' => 6000],
            $this->ajaxHeaders()
        )->assertOk();

        $this->assertSame('cancelled', (string) $payable->fresh()->status);
        $this->assertSame('cancelled', (string) $intent->fresh()->status);
        $this->assertSame('CANCELED', (string) $tp->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Публичная страница: доступность (200, не 500)
    // -------------------------------------------------------------------------

    public function test_public_pay_endpoints_return_200_for_guest(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(500.0);

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return $this->initResponse($request, '7700500001');
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }
            if (str_contains($request->url(), '/v2/GetQr')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Data' => 'qr'];
            }

            return null;
        });

        $token = $this->issuePublicPayToken($ulp);
        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();

        Auth::logout();

        foreach ($this->publicPayEndpoints($token) as $item) {
            $headers = ! empty($item['acceptJson'])
                ? ['HTTP_ACCEPT' => 'application/json']
                : ['HTTP_ACCEPT' => 'text/html'];

            $response = $this->call($item['method'], $item['url'], [], [], [], $headers);

            $this->assertNotSame(500, $response->getStatusCode(), "{$item['method']} {$item['url']}");
            $this->assertSame(200, $response->getStatusCode(), "Гость: {$item['method']} {$item['url']}");
        }
    }

    public function test_public_pay_endpoints_return_200_for_authenticated_user_with_permission(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(500.0);

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return $this->initResponse($request, '7700500002');
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }
            if (str_contains($request->url(), '/v2/GetQr')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Data' => 'qr'];
            }

            return null;
        });

        $token = $this->issuePublicPayToken($ulp);
        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();

        foreach ($this->publicPayEndpoints($token) as $item) {
            $headers = ! empty($item['acceptJson'])
                ? ['HTTP_ACCEPT' => 'application/json']
                : ['HTTP_ACCEPT' => 'text/html'];

            $response = $this->call($item['method'], $item['url'], [], [], [], $headers);

            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_public_pay_endpoints_return_200_for_authenticated_user_without_permission(): void
    {
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(500.0);

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return $this->initResponse($request, '7700500003');
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }
            if (str_contains($request->url(), '/v2/GetQr')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Data' => 'qr'];
            }

            return null;
        });

        $token = $this->issuePublicPayToken($ulp);

        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();

        foreach ($this->publicPayEndpoints($token) as $item) {
            $headers = ! empty($item['acceptJson'])
                ? ['HTTP_ACCEPT' => 'application/json']
                : ['HTTP_ACCEPT' => 'text/html'];

            $response = $this->call($item['method'], $item['url'], [], [], [], $headers);

            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_public_pay_show_returns_404_for_unknown_token(): void
    {
        $this->get(route('ulp.public.pay', ['token' => str_repeat('b', 64)]))
            ->assertNotFound();
    }

    public function test_public_pay_expired_link_returns_200_status_page_not_500(): void
    {
        $ulp = $this->createUnpaidAssignment(500.0);
        $token = bin2hex(random_bytes(32));

        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('ulp.public.pay', ['token' => $token]))
            ->assertOk()
            ->assertViewIs('payment.ulp-public-status')
            ->assertSee('Ссылка недействительна', false);
    }

    // -------------------------------------------------------------------------
    // Admin update: доступ, AJAX/non-AJAX контракты
    // -------------------------------------------------------------------------

    public function test_update_assignment_forbidden_without_lesson_packages_view(): void
    {
        $ulp = $this->createUnpaidAssignment(500.0);
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]),
            ['fee_amount' => 600],
            $this->ajaxHeaders()
        )->assertForbidden();
    }

    public function test_update_assignment_guest_is_denied(): void
    {
        $ulp = $this->createUnpaidAssignment(500.0);
        Auth::logout();

        $response = $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]),
            ['fee_amount' => 600],
            $this->ajaxHeaders()
        );

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
    }

    public function test_update_assignment_non_ajax_redirects_updates_db_and_invalidates_public_pay(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->seedTbankForPartner();
        $ulp = $this->seedAssignmentWithTeam(6500.0);

        $this->fakeTbankHttp(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/v2/Init')) {
                return $this->initResponse($request, '7700650044');
            }
            if (str_contains($request->url(), '/v2/Cancel')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'CANCELED'];
            }
            if (str_contains($request->url(), '/v2/GetState')) {
                return ['Success' => true, 'ErrorCode' => '0', 'Status' => 'NEW'];
            }

            return null;
        });

        $token = $this->issuePublicPayToken($ulp);
        $this->get(route('ulp.public.pay', ['token' => $token]))->assertOk();

        $response = $this->put(route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]), [
            '_token' => csrf_token(),
            'fee_amount' => '6000.00',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ulp->id,
            'fee_amount' => '6000.00',
        ]);

        $link = UserLessonPackagePublicPayLink::query()->where('user_lesson_package_id', $ulp->id)->firstOrFail();
        $this->assertNull($link->tinkoff_payment_id);
    }

    public function test_update_assignment_non_ajax_validation_failure_redirects_back_not_empty_200(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createUnpaidAssignment(500.0);

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->put(route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]), [
                '_token' => csrf_token(),
                'fee_amount' => '',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));
        $response->assertSessionHasErrors(['fee_amount']);

        $this->assertDatabaseHas('user_lesson_packages', [
            'id' => $ulp->id,
            'fee_amount' => '500.00',
        ]);
    }

    public function test_update_assignment_ajax_validation_returns_422_with_field_errors(): void
    {
        $this->grantPermission('lessonPackages.view');
        $paid = $this->createUnpaidAssignment(500.0);
        UserLessonPackage::query()->whereKey($paid->id)->update(['is_paid' => true]);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $paid->id]),
            ['fee_amount' => 600],
            $this->ajaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fee_amount']);
    }
}
