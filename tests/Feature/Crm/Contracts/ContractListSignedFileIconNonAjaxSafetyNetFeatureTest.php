<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

/**
 * P1: native GET/POST без X-Requested-With для data, колонок и скачивания — не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListSignedFileIconNonAjaxSafetyNetFeatureTest extends ContractListSignedFileIconTestCase
{
    public function test_native_get_data_still_returns_download_signed_url(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeContractWithSignedFile();

        $response = $this->get(route('contracts.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 20,
        ]), ['HTTP_ACCEPT' => 'text/html']);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));

        $row = collect($response->json('data'))->firstWhere('id', $contract->id);
        $this->assertIsArray($row);
        $this->assertSame(route('contracts.downloadSigned', $contract), $row['download_signed_url']);
    }

    public function test_native_get_download_from_list_returns_file_not_empty_page(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeContractWithSignedFile();

        $response = $this->from(route('contracts.index'))
            ->get(route('contracts.downloadSigned', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $response->assertDownload('contract-'.$contract->id.'-signed.pdf');
    }

    public function test_native_get_download_without_file_redirects_with_field_error(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeListContract([
            'status'          => Contract::STATUS_SIGNED,
            'signed_pdf_path' => null,
        ]);

        $response = $this->from(route('contracts.index'))
            ->get(route('contracts.downloadSigned', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertRedirect(route('contracts.index'))
            ->assertSessionHasErrors(['file']);
    }

    public function test_native_post_columns_settings_still_saves_signed_file_flag(): void
    {
        $this->actingAsContractsViewer();

        $response = $this->from(route('contracts.index'))
            ->post(route('contracts.columns-settings.save'), [
                'columns' => [
                    'signed_file' => false,
                    'user_name'   => true,
                ],
            ], [
                'HTTP_ACCEPT' => 'text/html',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk()->assertJson(['success' => true]);

        $this->get(route('contracts.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('signed_file', false);
    }

    public function test_guest_native_get_download_does_not_return_file(): void
    {
        $contract = $this->makeContractWithSignedFile();

        Auth::logout();
        $response = $this->from(route('contracts.index'))
            ->get(route('contracts.downloadSigned', $contract));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
    }
}
