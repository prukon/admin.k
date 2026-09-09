<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use Illuminate\Support\Facades\Auth;

/**
 * Доступ к иконке подписанного PDF в списке: гость, без права, тренер, чужая школа, viewer.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListSignedFileIconAccessFeatureTest extends ContractListSignedFileIconTestCase
{
    public function test_guest_is_denied_on_list_data_and_signed_download(): void
    {
        $contract = $this->makeContractWithSignedFile();

        Auth::logout();

        $index = $this->get(route('contracts.index'), ['HTTP_ACCEPT' => 'text/html']);
        $this->assertNotSame(500, $index->getStatusCode());
        $this->assertNotSame(200, $index->getStatusCode());
        $index->assertRedirect();

        $data = $this->getJson(route('contracts.data', ['draw' => 1]));
        $this->assertNotSame(500, $data->getStatusCode());
        $this->assertContains($data->getStatusCode(), [401, 403]);

        $download = $this->get(route('contracts.downloadSigned', $contract));
        $this->assertNotSame(500, $download->getStatusCode());
        $this->assertNotSame(200, $download->getStatusCode());
        $this->assertContains($download->getStatusCode(), [302, 401, 403]);

        $this->getJson(route('contracts.columns-settings.get'))->assertStatus(401);
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => ['signed_file' => true],
        ])->assertStatus(401);
    }

    public function test_manager_without_contracts_view_gets_403_on_list_data_and_signed_download(): void
    {
        $contract = $this->makeContractWithSignedFile();
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index'))->assertForbidden();
        $this->getJson(route('contracts.data', ['draw' => 1]))->assertForbidden();
        $this->get(route('contracts.downloadSigned', $contract))->assertForbidden();
        $this->getJson(route('contracts.columns-settings.get'))->assertForbidden();
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => ['signed_file' => true],
        ])->assertForbidden();
    }

    public function test_trainer_cannot_open_list_or_download_signed_pdf(): void
    {
        $contract = $this->makeContractWithSignedFile();
        $trainer = $this->createUserWithRole('trainer');

        $this->actingAs($trainer)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index'))->assertForbidden();
        $this->getJson(route('contracts.data', ['draw' => 1]))->assertForbidden();
        $this->get(route('contracts.downloadSigned', $contract))->assertForbidden();
    }

    public function test_foreign_school_cannot_download_or_see_signed_file_of_this_school(): void
    {
        $contract = $this->makeContractWithSignedFile();

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true]);
        $this->grantPermissionToRoleForPartner(
            $this->foreignUser->role_id,
            $this->foreignPartner->id,
            self::PERM_CONTRACTS_VIEW
        );

        $this->get(route('contracts.downloadSigned', $contract))->assertForbidden();

        $ids = collect($this->getJson(route('contracts.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
        ]))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertNotContains($contract->id, $ids);
    }

    public function test_viewer_with_contracts_view_opens_list_sees_download_url_and_gets_file(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeContractWithSignedFile();

        $index = $this->get(route('contracts.index'));
        $index->assertOk();
        $this->assertNotSame('', trim((string) $index->getContent()));
        $index->assertSee('data-column-key="signed_file"', false);

        $row = $this->dataRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame(route('contracts.downloadSigned', $contract), $row['download_signed_url']);

        $this->get(route('contracts.downloadSigned', $contract))
            ->assertOk()
            ->assertDownload('contract-'.$contract->id.'-signed.pdf');
    }
}
