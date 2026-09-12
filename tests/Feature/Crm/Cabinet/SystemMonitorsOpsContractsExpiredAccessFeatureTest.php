<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Cabinet;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * HTTP-матрица GET /cabinet/system-monitors/ops для строки «Договоры»:
 * гость / без права / с правом, JSON и нативный GET, без утечки ФИО, мутации не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SystemMonitorsOpsContractsExpiredAccessFeatureTest extends SystemMonitorsTestCase
{
    public function test_guest_is_denied_and_does_not_receive_contracts_key(): void
    {
        $this->seedExpiredFill('Гостевой', 'Утечка');
        Auth::logout();

        $json = $this->json('GET', $this->opsUrl(), [], $this->ajaxHeaders());
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertTrue(
            $json->isRedirect() || in_array($json->getStatusCode(), [401, 403, 419], true),
            'JSON гость: отказ, получено '.$json->getStatusCode()
        );
        $payload = $json->json() ?? [];
        $this->assertArrayNotHasKey('contracts', $payload);
        $this->assertStringNotContainsString('Гостевой', (string) $json->getContent());

        $html = $this->call('GET', $this->opsUrl());
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertStringNotContainsString('Гостевой', (string) $html->getContent());
    }

    public function test_admin_without_permission_gets_403_without_student_name(): void
    {
        $this->seedExpiredFill('Запрещённый', 'Ученик');
        $this->asAdmin();
        $this->user->forceFill(['system_monitors' => true])->save();

        $json = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders());
        $json->assertForbidden();
        $this->assertNotSame('', trim((string) $json->getContent()));
        $this->assertArrayNotHasKey('contracts', $json->json() ?? []);
        $this->assertStringNotContainsString('Запрещённый', (string) $json->getContent());

        $html = $this->actingAs($this->user)->get($this->opsUrl());
        $html->assertForbidden();
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertStringNotContainsString('Запрещённый', (string) $html->getContent());
    }

    public function test_authorized_operator_gets_contracts_on_ajax_and_native_get(): void
    {
        $this->seedExpiredFill('Операторский', 'Клиент');
        $this->asAdmin();
        $this->grantSystemMonitorsView($this->user);

        $ajax = $this->actingAs($this->user)
            ->getJson($this->opsUrl(), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('contracts.fill_expired_count', 1)
            ->assertJsonPath('contracts.fill_expired.0.name', 'Операторский Клиент')
            ->assertJsonPath('contracts.sms_expired_count', 0);
        $this->assertIsInt($ajax->json('contracts.fill_expired_count'));
        $this->assertIsInt($ajax->json('contracts.sms_expired_count'));
        $this->assertNotSame('', trim((string) $ajax->getContent()));

        $native = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->get($this->opsUrl())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('contracts.fill_expired_count', 1);
        $this->assertIsInt($native->json('contracts.sms_expired_count'));
        $this->assertStringContainsString(
            'json',
            strtolower((string) $native->headers->get('content-type'))
        );
        $this->assertStringNotContainsString('<html', strtolower((string) $native->getContent()));
    }

    public function test_trainer_and_student_with_permission_see_contracts_integers(): void
    {
        $this->seedExpiredFill('Ролевой', 'Счёт');

        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner);
            $this->grantSystemMonitorsView($actor);

            $response = $this->actingInCurrentPartner($actor)
                ->getJson($this->opsUrl(), $this->ajaxHeaders())
                ->assertOk()
                ->assertJsonPath('ok', true)
                ->assertJsonPath('contracts.fill_expired_count', 1);
            $this->assertIsInt($response->json('contracts.fill_expired_count'));
            $this->assertIsInt($response->json('contracts.sms_expired_count'));
        }
    }

    public function test_mutating_ops_contracts_is_not_empty_200_for_operator_and_guest(): void
    {
        $this->asSuperadmin();

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->actingAs($this->user)
                ->json($method, $this->opsUrl(), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON оператор не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON оператор не 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON оператор');
            $this->assertArrayNotHasKey('contracts', $json->json() ?? []);

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
            $this->assertArrayNotHasKey('contracts', $json->json() ?? []);
        }
    }

    private function seedExpiredFill(string $lastname, string $name): User
    {
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => $lastname,
            'name' => $name,
        ]);
        $suffix = (string) random_int(100000, 999999);
        Contract::create([
            'school_id' => $this->partner->id,
            'user_id' => $student->id,
            'group_id' => null,
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => now()->subHour(),
            'source_pdf_path' => 'documents/2026/09/ops-access-'.$suffix.'.pdf',
            'source_sha256' => hash('sha256', 'ops-access-contract-'.$suffix),
            'provider' => 'podpislon',
        ]);

        return $student;
    }
}
