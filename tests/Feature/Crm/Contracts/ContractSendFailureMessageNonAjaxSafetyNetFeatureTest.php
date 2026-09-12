<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;
use Illuminate\Support\Facades\Auth;

/**
 * P1: native POST/GET без X-Requested-With отдаёт текст ошибки, не 500 и не пустой ответ.
 *
 * Native send остаётся JSON 422 (контракт signing-url), не 302 на список.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractSendFailureMessageNonAjaxSafetyNetFeatureTest extends ContractSendFailureMessageTestCase
{
    public function test_native_post_send_returns_provider_message_and_is_not_empty(): void
    {
        $this->actingAsContractsViewer();
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonAddDocumentRejected();

        $contract = $this->makeDraftContract();

        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', self::PROVIDER_ERROR);

        $this->assertSame(Contract::STATUS_FAILED, $contract->fresh()->status);
        $this->assertSame(0, ContractEvent::query()->where('contract_id', $contract->id)->where('type', 'sent')->count());
    }

    public function test_native_post_send_with_invalid_fields_returns_422_and_does_not_mark_failed(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeDraftContract();

        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.send', $contract), [
                'signer_firstname' => 'Иван',
                'signer_phone' => '+7 (900) 111-22-33',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['signer_lastname']);

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
        $this->assertNull($contract->fresh()->lastFailureMessage());
    }

    public function test_guest_native_post_send_does_not_mark_failed(): void
    {
        $this->seedPodpislonLegalEntity();
        $this->fakePodpislonAddDocumentRejected();
        $contract = $this->makeDraftContract();

        Auth::logout();
        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.send', $contract), $this->validSendPayload());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);

        $this->assertSame(Contract::STATUS_DRAFT, $contract->fresh()->status);
        $this->assertSame(0, ContractEvent::query()->where('contract_id', $contract->id)->count());
    }

    public function test_native_html_get_card_shows_stored_provider_error(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeFailedContractWithProviderMessage();

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->get(route('contracts.show', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk()
            ->assertSee('id="contract-status-error"', false)
            ->assertSee(self::PROVIDER_ERROR, false);
    }

    public function test_native_get_list_data_includes_status_error(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeFailedContractWithProviderMessage();

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->get('/client-contracts/data?draw=1&start=0&length=20');

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $contract->id);
        $this->assertIsArray($row);
        $this->assertSame(self::PROVIDER_ERROR, $row['status_error']);
    }

    public function test_native_get_index_page_contains_status_error_title_js(): void
    {
        $this->actingAsContractsViewer();
        $this->makeFailedContractWithProviderMessage();

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->get(route('contracts.index'));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('row.status_error', $html);
        $this->assertStringContainsString('titleAttr', $html);
        $this->assertStringContainsString("title=\"' + statusError + '\"", $html);
    }
}
