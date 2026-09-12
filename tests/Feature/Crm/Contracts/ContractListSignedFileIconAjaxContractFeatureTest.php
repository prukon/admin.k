<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;

/**
 * P1: AJAX-контракт списка — download_signed_url только при файле, 422 по полям, без утечки пути.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListSignedFileIconAjaxContractFeatureTest extends ContractListSignedFileIconTestCase
{
    public function test_ajax_data_returns_download_url_only_when_signed_pdf_path_is_filled(): void
    {
        $this->actingAsContractsViewer();

        $withFile = $this->makeContractWithSignedFile();
        $signedWithoutFile = $this->makeListContract([
            'status'          => Contract::STATUS_SIGNED,
            'signed_pdf_path' => null,
        ]);
        $emptyPath = $this->makeListContract([
            'status'          => Contract::STATUS_SIGNED,
            'signed_pdf_path' => '',
        ]);
        $revokedWithFile = $this->makeContractWithSignedFile([
            'status' => Contract::STATUS_REVOKED,
        ]);
        $draftWithFile = $this->makeContractWithSignedFile([
            'status' => Contract::STATUS_DRAFT,
        ]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'   => 1,
                'start'  => 0,
                'length' => 50,
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [['id', 'download_signed_url', 'status']],
            ]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $rows = collect($response->json('data'))->keyBy('id');

        $this->assertSame(
            route('contracts.downloadSigned', $withFile),
            $rows[$withFile->id]['download_signed_url']
        );
        $this->assertNull($rows[$signedWithoutFile->id]['download_signed_url']);
        $this->assertNull($rows[$emptyPath->id]['download_signed_url']);
        $this->assertSame(
            route('contracts.downloadSigned', $revokedWithFile),
            $rows[$revokedWithFile->id]['download_signed_url']
        );
        $this->assertSame(
            route('contracts.downloadSigned', $draftWithFile),
            $rows[$draftWithFile->id]['download_signed_url']
        );

        $this->assertArrayNotHasKey('signed_pdf_path', $rows[$withFile->id]);
    }

    public function test_ajax_data_does_not_include_foreign_school_contract(): void
    {
        $this->actingAsContractsViewer();
        $foreign = $this->makeContractWithSignedFile([
            'school_id' => $this->foreignPartner->id,
        ]);

        $ids = collect($this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'   => 1,
                'start'  => 0,
                'length' => 50,
            ]))
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();

        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_ajax_data_rejects_invalid_length_with_field_error(): void
    {
        $this->actingAsContractsViewer();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'   => 1,
                'start'  => 0,
                'length' => 0,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['length']]);
    }

    public function test_ajax_columns_settings_save_rejects_non_array_with_field_error(): void
    {
        $this->actingAsContractsViewer();

        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => 'signed_file',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['columns']]);
    }

    public function test_ajax_hiding_signed_file_column_persists_for_viewer(): void
    {
        $this->actingAsContractsViewer();

        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => [
                'signed_file'  => false,
                'status_label' => true,
            ],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->getJson(route('contracts.columns-settings.get'), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('signed_file', false)
            ->assertJsonPath('status_label', true);
    }

    public function test_ajax_data_order_on_icon_column_is_not_500(): void
    {
        $this->actingAsContractsViewer();
        $this->makeContractWithSignedFile();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson('/client-contracts/data?draw=1&start=0&length=20&order[0][column]=8&order[0][dir]=desc');

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertArrayHasKey('download_signed_url', $response->json('data.0'));
    }
}
