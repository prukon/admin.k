<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

/**
 * P1: native POST аннулирования без X-Requested-With всё равно меняет статус (кнопка — $.ajax,
 * но safety-net не должен оставлять пустой 200 / 500 без записи в БД).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractAnnulAfterSendNonAjaxSafetyNetFeatureTest extends ContractAnnulAfterSendTestCase
{
    public function test_native_post_from_card_annuls_opened_contract_and_is_not_empty(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);

        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.revoke', $contract), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertContains($response->getStatusCode(), [200, 302]);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);

        if ($response->getStatusCode() === 200) {
            $response->assertJsonPath('status', 'revoked');
        } else {
            $response->assertRedirect(route('contracts.show', $contract));
        }
    }

    public function test_native_html_accept_post_still_annuls(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeAnnulContract();

        $response = $this->withHeaders(['Accept' => 'text/html'])
            ->from(route('contracts.show', $contract))
            ->post(route('contracts.revoke', $contract), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
    }

    public function test_native_post_on_signed_does_not_succeed_or_return_empty_200(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_SIGNED]);

        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.revoke', $contract), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SIGNED, $contract->status);
    }

    public function test_guest_native_post_does_not_annul(): void
    {
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);

        Auth::logout();
        $response = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.revoke', $contract), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());

        $contract->refresh();
        $this->assertSame(Contract::STATUS_OPENED, $contract->status);
    }
}
