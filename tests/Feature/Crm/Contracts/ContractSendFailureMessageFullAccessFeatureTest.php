<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;

/**
 * Полный доступ: admin/superadmin видят текст ошибки; чужие HTTP-методы не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractSendFailureMessageFullAccessFeatureTest extends ContractSendFailureMessageTestCase
{
    public function test_admin_sees_provider_error_on_show_and_list_data(): void
    {
        $this->asAdmin();
        $contract = $this->makeFailedContractWithProviderMessage();

        $response = $this->get(route('contracts.show', $contract));
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertSee('id="contract-status-error"', false)
            ->assertSee(self::PROVIDER_ERROR, false);

        $index = $this->get(route('contracts.index'));
        $index->assertOk();
        $this->assertNotSame('', trim((string) $index->getContent()));
        $index->assertSee('row.status_error', false);

        $row = $this->listRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame(self::PROVIDER_ERROR, $row['status_error']);
    }

    public function test_admin_ajax_send_returns_422_with_provider_message(): void
    {
        $this->asAdmin();
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonAddDocumentRejected();
        $contract = $this->makeDraftContract();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'send_not_sent')
            ->assertJsonPath('message', self::PROVIDER_ERROR);

        $this->assertSame(Contract::STATUS_FAILED, $contract->fresh()->status);
        $this->assertSame(0, ContractEvent::query()->where('contract_id', $contract->id)->where('type', 'sent')->count());
    }

    public function test_superadmin_sees_provider_error_on_show(): void
    {
        $this->asSuperadmin();
        $contract = $this->makeFailedContractWithProviderMessage();

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(self::PROVIDER_ERROR, false);

        $row = $this->listRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame(self::PROVIDER_ERROR, $row['status_error']);
    }

    public function test_unsupported_methods_on_send_are_not_500(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeDraftContract();

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
    }

    public function test_wrong_http_methods_on_show_and_data_are_not_500(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeFailedContractWithProviderMessage();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $show = $this->json($method, route('contracts.show', $contract));
            $this->assertNotSame(500, $show->getStatusCode(), 'JSON show '.$method);
            $this->assertNotSame(200, $show->getStatusCode(), 'JSON show '.$method);
            $this->assertContains($show->getStatusCode(), [404, 405], 'JSON show '.$method);

            $data = $this->json($method, route('contracts.data', ['draw' => 1]));
            $this->assertNotSame(500, $data->getStatusCode(), 'JSON data '.$method);
            $this->assertNotSame(200, $data->getStatusCode(), 'JSON data '.$method);
            $this->assertContains($data->getStatusCode(), [404, 405], 'JSON data '.$method);
            $this->assertNotSame('', trim((string) $data->getContent()), 'JSON data '.$method);
        }

        $this->assertSame(Contract::STATUS_FAILED, $contract->fresh()->status);
        $this->assertSame(self::PROVIDER_ERROR, $contract->fresh()->lastFailureMessage());
    }
}
