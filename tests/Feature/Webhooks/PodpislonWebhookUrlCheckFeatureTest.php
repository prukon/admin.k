<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Monolog\Handler\NullHandler;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Кабинет Подпислона сохраняет URL вебхука только если пустой POST даёт 200.
 * Самодельный ?token= не нужен; SIGNATURE Подпислона для событий остаётся.
 *
 * @see /docs/documentation/contracts.html#contracts-podpislon
 * @see /doc#legal-entities-podpislon-key-index
 */
final class PodpislonWebhookUrlCheckFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'logging.channels.podpislon' => [
                'driver' => 'monolog',
                'handler' => NullHandler::class,
            ],
        ]);
    }

    private function webhookUrl(): string
    {
        return route('webhooks.podpislon');
    }

    /**
     * @param  array<string, string>  $server
     */
    private function postEmptyForm(string $url, array $server = [])
    {
        return $this->call(
            'POST',
            $url,
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], $server),
            ''
        );
    }

    private function postRawForm(string $url, string $rawBody)
    {
        return $this->call(
            'POST',
            $url,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $rawBody
        );
    }

    private function makeSentContract(string $providerDocId): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
        ]);

        return Contract::create([
            'school_id' => $this->partner->id,
            'user_id' => $student->id,
            'group_id' => null,
            'status' => Contract::STATUS_SENT,
            'provider' => 'podpislon',
            'provider_doc_id' => $providerDocId,
            'source_pdf_path' => '/tmp/source-'.$providerDocId.'.pdf',
            'source_sha256' => hash('sha256', 'dummy-'.$providerDocId),
        ]);
    }

    public function test_guest_url_check_without_query_token_returns_200_json_not_login_redirect(): void
    {
        Auth::logout();

        $response = $this->postEmptyForm($this->webhookUrl());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(419, $response->getStatusCode());
        $this->assertNotSame(302, $response->getStatusCode(), 'Гость не должен уезжать на логин');
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'probe' => true,
            ]);
        $this->assertNotSame('', (string) $response->json('rid'));
    }

    public function test_podpislon_url_check_does_not_need_csrf_token(): void
    {
        Auth::logout();
        $this->app->instance('env', 'local');

        $response = $this->postEmptyForm($this->webhookUrl());

        $this->assertNotSame(419, $response->getStatusCode());
        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('probe', true);
    }

    public function test_legacy_query_token_is_not_required_for_url_check(): void
    {
        Auth::logout();

        $withLegacy = $this->postEmptyForm($this->webhookUrl().'?token=legacy-homemade-token');
        $without = $this->postEmptyForm($this->webhookUrl());

        foreach ([$withLegacy, $without] as $response) {
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(403, $response->getStatusCode(), 'Кастомный token не должен блокировать пробу');
            $response
                ->assertOk()
                ->assertJson([
                    'ok' => true,
                    'probe' => true,
                ]);
        }
    }

    public function test_webhook_path_with_homemade_token_segment_is_not_a_valid_url(): void
    {
        Auth::logout();

        $response = $this->postEmptyForm('/webhooks/podpislon/legacy-homemade-token');

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Токен в path не должен быть рабочим URL');
        $this->assertContains(
            $response->getStatusCode(),
            [404, 405],
            'Ожидали 404/405, получили '.$response->getStatusCode()
        );
    }

    public function test_query_token_is_not_a_substitute_for_podpislon_signature(): void
    {
        Auth::logout();
        $contract = $this->makeSentContract('555001');

        $response = $this->call(
            'POST',
            $this->webhookUrl().'?token=legacy-homemade-token',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            'EVENT=DOCUMENT_OPENED&FILE_ID=555001&COMPANY_ID=456'
        );

        $response
            ->assertStatus(403)
            ->assertJson([
                'ok' => false,
                'error' => 'signature_required',
            ]);
        $this->assertSame(Contract::STATUS_SENT, $contract->fresh()->status);
        $this->assertDatabaseMissing('contract_events', [
            'contract_id' => $contract->id,
            'type' => 'webhook_document_opened',
        ]);
    }

    public function test_opened_event_with_legacy_query_token_still_updates_contract(): void
    {
        Auth::logout();
        $contract = $this->makeSentContract('555002');

        $rawNoSig = 'EVENT=DOCUMENT_OPENED&FILE_ID=555002&COMPANY_ID=456';
        $rawBody = $rawNoSig.'&SIGNATURE='.md5($rawNoSig);

        $response = $this->postRawForm($this->webhookUrl().'?token=legacy-homemade-token', $rawBody);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(Contract::STATUS_OPENED, $contract->fresh()->status);
    }

    public function test_staff_without_contracts_view_does_not_block_url_check(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.view', $this->partner);
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $response = $this->postEmptyForm($this->webhookUrl());

        $this->assertNotSame(403, $response->getStatusCode(), 'Вебхук публичный: право CRM не нужно');
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk()->assertJsonPath('probe', true);
    }

    public function test_staff_with_contracts_view_can_still_receive_url_check(): void
    {
        $this->actingAs($this->user)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $response = $this->postEmptyForm($this->webhookUrl());

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('probe', true);
    }

    public function test_unsupported_methods_do_not_return_500_or_empty_200(): void
    {
        Auth::logout();

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->call($method, $this->webhookUrl());

            $this->assertNotSame(
                500,
                $response->getStatusCode(),
                $method.' /webhooks/podpislon не должен падать 500'
            );
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame('', trim((string) $response->getContent()), $method.' не пустой 200');
            }
            $this->assertContains(
                $response->getStatusCode(),
                [404, 405],
                $method.' ожидается 404/405, получили '.$response->getStatusCode()
            );
        }
    }

    public function test_named_route_has_no_homemade_token_query(): void
    {
        $this->assertSame('/webhooks/podpislon', parse_url($this->webhookUrl(), PHP_URL_PATH));
        $this->assertNull(parse_url($this->webhookUrl(), PHP_URL_QUERY));
        $this->assertStringNotContainsString('token=', $this->webhookUrl());
    }
}
