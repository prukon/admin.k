<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

/**
 * Полный доступ: admin/superadmin видят ссылку; чужие HTTP-методы не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractPodpislonSigningUrlFullAccessFeatureTest extends ContractPodpislonSigningUrlTestCase
{
    public function test_admin_gets_200_on_card_with_sms_link(): void
    {
        $this->asAdmin();
        $contract = $this->makeSentWithSigningUrl();

        $show = $this->get(route('contracts.show', $contract));
        $show->assertOk();
        $this->assertNotSame('', trim((string) $show->getContent()));
        $show->assertSee('id="contract-provider-signing-url"', false);
        $show->assertSee(self::SAMPLE_URL, false);
    }

    public function test_superadmin_sees_sms_link_and_can_sync_it(): void
    {
        $this->asSuperadmin();
        $entity = $this->seedPodpislonLegalEntity();
        $this->fakePodpislonHttpWithSigningUrl();

        $contract = $this->makeContract([
            'provider_doc_id' => '971890',
            'status' => \App\Models\Contract::STATUS_SENT,
            'legal_entity_id' => $entity->id,
        ]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('id="syncStatusBtn"', false);

        $this->getJson(route('contracts.status', $contract))
            ->assertOk()
            ->assertJsonPath('status', \App\Models\Contract::STATUS_SENT);

        $this->assertSame(self::SAMPLE_URL, $contract->fresh()->provider_signing_url);
    }

    public function test_wrong_http_methods_on_send_status_resend_are_not_500_and_do_not_save_url(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeSentWithSigningUrl(['provider_signing_url' => null]);

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('contracts.send', $contract), $this->validSendPayload());
            $this->assertNotSame(500, $json->getStatusCode(), 'JSON send '.$method);
            $this->assertNotSame(200, $json->getStatusCode(), 'JSON send '.$method);
            $this->assertContains($json->getStatusCode(), [404, 405], 'JSON send '.$method);

            $web = $this->call($method, route('contracts.send', $contract), $this->validSendPayload());
            $this->assertNotSame(500, $web->getStatusCode(), 'WEB send '.$method);
            $this->assertNotSame(200, $web->getStatusCode(), 'WEB send '.$method);
            $this->assertContains($web->getStatusCode(), [404, 405], 'WEB send '.$method);
        }

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('contracts.status', $contract));
            $this->assertNotSame(500, $json->getStatusCode(), 'JSON status '.$method);
            $this->assertNotSame(200, $json->getStatusCode(), 'JSON status '.$method);
            $this->assertContains($json->getStatusCode(), [404, 405], 'JSON status '.$method);
        }

        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('contracts.resend', $contract));
            $this->assertNotSame(500, $json->getStatusCode(), 'JSON resend '.$method);
            $this->assertNotSame(200, $json->getStatusCode(), 'JSON resend '.$method);
            $this->assertContains($json->getStatusCode(), [404, 405], 'JSON resend '.$method);
        }

        $this->assertNull($contract->fresh()->provider_signing_url);
    }
}
