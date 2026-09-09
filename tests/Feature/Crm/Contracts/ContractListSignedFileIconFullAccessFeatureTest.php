<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;

/**
 * Полный доступ: admin/superadmin 200 на список, data и скачивание; чужие HTTP-методы не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListSignedFileIconFullAccessFeatureTest extends ContractListSignedFileIconTestCase
{
    public function test_admin_gets_200_on_list_data_and_signed_download(): void
    {
        $this->asAdmin();
        $contract = $this->makeContractWithSignedFile();

        $index = $this->get(route('contracts.index'));
        $index->assertOk();
        $this->assertNotSame('', trim((string) $index->getContent()));

        $row = $this->dataRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame(route('contracts.downloadSigned', $contract), $row['download_signed_url']);

        $download = $this->get(route('contracts.downloadSigned', $contract));
        $download->assertOk();
        $download->assertDownload('contract-'.$contract->id.'-signed.pdf');
    }

    public function test_superadmin_can_download_signed_pdf_from_list_url(): void
    {
        $this->asSuperadmin();
        $contract = $this->makeContractWithSignedFile();

        $this->get(route('contracts.downloadSigned', $contract))
            ->assertOk()
            ->assertDownload('contract-'.$contract->id.'-signed.pdf');
    }

    public function test_wrong_http_methods_on_signed_download_and_data_are_not_500(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeContractWithSignedFile();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $jsonDownload = $this->json($method, route('contracts.downloadSigned', $contract));
            $this->assertNotSame(500, $jsonDownload->getStatusCode(), 'JSON download '.$method);
            $this->assertNotSame(200, $jsonDownload->getStatusCode(), 'JSON download '.$method);
            $this->assertContains($jsonDownload->getStatusCode(), [404, 405], 'JSON download '.$method);

            $webDownload = $this->call($method, route('contracts.downloadSigned', $contract));
            $this->assertNotSame(500, $webDownload->getStatusCode(), 'WEB download '.$method);
            $this->assertNotSame(200, $webDownload->getStatusCode(), 'WEB download '.$method);
            $this->assertContains($webDownload->getStatusCode(), [404, 405], 'WEB download '.$method);
        }

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $jsonData = $this->json($method, route('contracts.data', ['draw' => 1]));
            $this->assertNotSame(500, $jsonData->getStatusCode(), 'JSON data '.$method);
            $this->assertNotSame(200, $jsonData->getStatusCode(), 'JSON data '.$method);
            $this->assertContains($jsonData->getStatusCode(), [404, 405], 'JSON data '.$method);
        }
    }

    public function test_json_accept_download_of_missing_signed_file_is_not_empty_200(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeListContract([
            'status'          => Contract::STATUS_SIGNED,
            'signed_pdf_path' => null,
        ]);

        $response = $this->from(route('contracts.index'))
            ->get(route('contracts.downloadSigned', $contract), ['HTTP_ACCEPT' => 'application/json']);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 422]);
    }
}
