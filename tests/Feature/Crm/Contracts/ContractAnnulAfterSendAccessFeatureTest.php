<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к аннулированию sent/opened: гость, без права, чужая школа, с contracts.view.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractAnnulAfterSendAccessFeatureTest extends ContractAnnulAfterSendTestCase
{
    public function test_guest_json_annul_is_denied_and_does_not_change_status(): void
    {
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);

        Auth::logout();
        $json = $this->postJson(route('contracts.revoke', $contract), []);

        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertContains($json->getStatusCode(), [401, 403, 419]);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_OPENED, $contract->status);
    }

    public function test_guest_native_annul_redirects_to_login_and_does_not_change_status(): void
    {
        $contract = $this->makeAnnulContract();

        Auth::logout();
        $web = $this->from(route('contracts.show', $contract))
            ->post(route('contracts.revoke', $contract), []);

        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());
        $this->assertContains($web->getStatusCode(), [302, 401, 403, 419]);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);
    }

    public function test_manager_without_contracts_view_gets_403_on_annul_json_and_native(): void
    {
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson(route('contracts.revoke', $contract), [])->assertForbidden();
        $this->from(route('contracts.show', $contract))
            ->post(route('contracts.revoke', $contract), [])
            ->assertForbidden();

        $this->get(route('contracts.show', $contract))->assertForbidden();

        $contract->refresh();
        $this->assertSame(Contract::STATUS_OPENED, $contract->status);
    }

    public function test_trainer_cannot_annul_opened_contract(): void
    {
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);
        $trainer = $this->createUserWithRole('trainer');

        $this->actingAs($trainer)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson(route('contracts.revoke', $contract), [])->assertForbidden();
        $contract->refresh();
        $this->assertSame(Contract::STATUS_OPENED, $contract->status);
    }

    public function test_foreign_school_cannot_annul_or_open_card(): void
    {
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true]);

        $this->get(route('contracts.show', $contract))->assertForbidden();
        $this->postJson(route('contracts.revoke', $contract), [])->assertForbidden();

        $contract->refresh();
        $this->assertSame(Contract::STATUS_OPENED, $contract->status);
    }

    public function test_viewer_with_contracts_view_can_open_card_and_annul(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeAnnulContract(['status' => Contract::STATUS_OPENED]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('id="annulAfterSendBtn"', false);

        $this->postJson(route('contracts.revoke', $contract), [])
            ->assertOk()
            ->assertJsonPath('status', 'revoked');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_REVOKED, $contract->status);
    }
}
