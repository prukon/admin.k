<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Payment;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * HTTP-матрица GET /cabinet/system-monitors/ops для строк «Сегодня» / «Вчера»:
 * гость / без права / с правом, JSON и нативный GET, без утечки оборотки, мутации не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsDayAccessFeatureTest extends SystemMonitorsTestCase
{
    public function test_guest_is_denied_and_does_not_receive_today_or_yesterday_keys(): void
    {
        Auth::logout();

        foreach (['GET'] as $method) {
            $json = $this->json($method, $this->opsUrl(), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON гость не 200');
            $this->assertTrue(
                $json->isRedirect() || in_array($json->getStatusCode(), [401, 403, 419], true),
                $method.' JSON гость: отказ, получено '.$json->getStatusCode()
            );
            $payload = $json->json() ?? [];
            $this->assertArrayNotHasKey('day', $payload);
            $this->assertArrayNotHasKey('yesterday', $payload);

            $html = $this->call($method, $this->opsUrl());
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML гость не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML гость не 200');
        }
    }

    public function test_admin_without_permission_gets_403_without_today_turnover(): void
    {
        $this->seedTodayTbankPayment(15_000_000, 'deal-forbidden-leak');
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $json = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
        $json->assertForbidden();
        $this->assertNotSame('', trim((string) $json->getContent()));
        $this->assertArrayNotHasKey('day', $json->json() ?? []);
        $this->assertArrayNotHasKey('yesterday', $json->json() ?? []);
        $this->assertStringNotContainsString('150000', (string) $json->getContent());
        $this->assertStringNotContainsString('deal-forbidden-leak', (string) $json->getContent());

        $html = $this->actingAs($this->user)->get($this->opsUrl());
        $html->assertForbidden();
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertStringNotContainsString('150000', (string) $html->getContent());
    }

    public function test_authorized_operator_gets_integer_today_and_yesterday_on_ajax_and_native_get(): void
    {
        $this->asAdmin();
        $this->grantSystemMonitorsView($this->user);
        $this->seedTodayTbankPayment(15_000_000, 'deal-operator-day');

        $ajax = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('day.commission', 600)
            ->assertJsonPath('day.payments_count', 1)
            ->assertJsonPath('yesterday.turnover', 0)
            ->assertJsonPath('yesterday.commission', 0)
            ->assertJsonPath('yesterday.payments_count', 0);
        $this->assertIsInt($ajax->json('day.turnover'));
        $this->assertIsInt($ajax->json('day.commission'));
        $this->assertIsInt($ajax->json('day.payments_count'));
        $this->assertIsInt($ajax->json('yesterday.turnover'));
        $this->assertIsInt($ajax->json('yesterday.commission'));
        $this->assertIsInt($ajax->json('yesterday.payments_count'));
        $this->assertNotSame('', trim((string) $ajax->getContent()));

        $native = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->get($this->opsUrl())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('day.turnover', 150000)
            ->assertJsonPath('yesterday.payments_count', 0);
        $this->assertIsInt($native->json('day.turnover'));
        $this->assertIsInt($native->json('yesterday.commission'));
        $this->assertStringContainsString(
            'json',
            strtolower((string) $native->headers->get('content-type'))
        );
        $this->assertStringNotContainsString('<html', strtolower((string) $native->getContent()));
    }

    public function test_trainer_and_student_with_permission_see_today_integers(): void
    {
        $this->seedTodayTbankPayment(10_000_000, 'deal-roles-day');

        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner);
            $this->grantSystemMonitorsView($actor);

            $response = $this->actingInCurrentPartner($actor)
                ->getJson($this->opsUrl(), $this->ajaxHeaders())
                ->assertOk()
                ->assertJsonPath('ok', true)
                ->assertJsonPath('day.turnover', 100000)
                ->assertJsonPath('day.payments_count', 1);
            $this->assertIsInt($response->json('day.turnover'));
            $this->assertIsInt($response->json('yesterday.turnover'));
        }
    }

    public function test_mutating_ops_day_is_not_empty_200_for_operator_and_guest(): void
    {
        $this->asSuperadmin();

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->actingAs($this->user)
                ->json($method, $this->opsUrl(), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON оператор не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON оператор не 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON оператор');
            $this->assertArrayNotHasKey('day', $json->json() ?? []);

            $html = $this->from(route('dashboard'))
                ->actingAs($this->user)
                ->call($method, $this->opsUrl());
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML оператор не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML оператор не 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML оператор');
        }

        Auth::logout();
        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, $this->opsUrl());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON гость не 200');
            $this->assertArrayNotHasKey('day', $json->json() ?? []);
        }
    }

    private function seedTodayTbankPayment(int $summCents, string $dealId): void
    {
        $now = Carbon::parse('2026-09-03 12:00:00', 'Europe/Moscow');
        $this->travelTo($now);

        TinkoffCommissionRule::factory()->globalRule()->create([
            'platform_percent' => 0.40,
            'platform_min_fixed' => 0,
            'is_enabled' => true,
        ]);

        $student = $this->createUserWithRole('user', $this->partner);
        $this->makeTbankPayment($student, $summCents, $now, $dealId);
    }

    private function makeTbankPayment(
        User $student,
        int $summCents,
        Carbon $operationAt,
        string $dealId
    ): void {
        Payment::factory()->forUser($student)->create([
            'partner_id' => $student->partner_id,
            'summ_cents' => $summCents,
            'operation_date' => $operationAt->format('Y-m-d H:i:s'),
            'deal_id' => $dealId,
            'payment_id' => 'pid-'.$dealId,
            'payment_status' => 'paid',
        ]);
        TinkoffPayment::query()->create([
            'order_id' => 'order-'.$dealId,
            'partner_id' => (int) $student->partner_id,
            'amount' => $summCents,
            'method' => 'card',
            'status' => 'CONFIRMED',
            'deal_id' => $dealId,
            'tinkoff_payment_id' => (string) random_int(300_000_000, 2_000_000_000),
        ]);
    }
}
