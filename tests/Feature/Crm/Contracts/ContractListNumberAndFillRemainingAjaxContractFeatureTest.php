<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use Illuminate\Support\Carbon;

/**
 * P1: AJAX-контракт списка — id, остаток дней, 422 по полям, без утечки чужой школы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListNumberAndFillRemainingAjaxContractFeatureTest extends ContractListNumberAndFillRemainingTestCase
{
    public function test_ajax_data_returns_id_and_remaining_labels_for_deadline_states(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $this->actingAsFillExpiresViewer();

        $twoDays = $this->makeTemplateContract(Carbon::parse('2026-09-14 15:00:00', 'Europe/Moscow'));
        $sixDays = $this->makeTemplateContract(Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'));
        $lastDay = $this->makeTemplateContract(Carbon::parse('2026-09-12 18:00:00', 'Europe/Moscow'));
        $expired = $this->makeTemplateContract(Carbon::parse('2026-09-10 12:00:00', 'Europe/Moscow'));
        $signed = $this->makeTemplateContract(Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'), [
            'status' => Contract::STATUS_SIGNED,
        ]);
        $pdf = $this->makeListContract(['fill_expires_at' => null]);

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
                'data' => [['id', 'fill_expires_at', 'fill_expires_remaining', 'fill_expires_remaining_warn']],
            ]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $rows = collect($response->json('data'))->keyBy('id');

        $this->assertSame($twoDays->id, $rows[$twoDays->id]['id']);
        $this->assertSame('2 дня', $rows[$twoDays->id]['fill_expires_remaining']);
        $this->assertTrue($rows[$twoDays->id]['fill_expires_remaining_warn']);

        $this->assertSame('6 дней', $rows[$sixDays->id]['fill_expires_remaining']);
        $this->assertFalse($rows[$sixDays->id]['fill_expires_remaining_warn']);

        $this->assertSame(Contract::CLIENT_FILL_REMAINING_LAST_DAY, $rows[$lastDay->id]['fill_expires_remaining']);
        $this->assertTrue($rows[$lastDay->id]['fill_expires_remaining_warn']);

        $this->assertSame(Contract::SCHOOL_LIST_FILL_REMAINING_EXPIRED, $rows[$expired->id]['fill_expires_remaining']);
        $this->assertTrue($rows[$expired->id]['fill_expires_remaining_warn']);

        $this->assertSame('18.09.2026 15:00:00', $rows[$signed->id]['fill_expires_at']);
        $this->assertSame('', $rows[$signed->id]['fill_expires_remaining']);
        $this->assertFalse($rows[$signed->id]['fill_expires_remaining_warn']);

        $this->assertSame('', $rows[$pdf->id]['fill_expires_at']);
        $this->assertSame('', $rows[$pdf->id]['fill_expires_remaining']);
        $this->assertFalse($rows[$pdf->id]['fill_expires_remaining_warn']);

        $this->assertStringNotContainsString('Осталось', (string) $response->getContent());
    }

    public function test_ajax_data_omits_remaining_keys_without_fill_expires_permission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $this->actingAsContractsViewer();
        $this->makeTemplateContract(Carbon::parse('2026-09-14 15:00:00', 'Europe/Moscow'));

        $row = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'   => 1,
                'start'  => 0,
                'length' => 20,
            ]))
            ->assertOk()
            ->json('data.0');

        $this->assertArrayHasKey('id', $row);
        $this->assertArrayNotHasKey('fill_expires_at', $row);
        $this->assertArrayNotHasKey('fill_expires_remaining', $row);
        $this->assertArrayNotHasKey('fill_expires_remaining_warn', $row);
    }

    public function test_ajax_search_by_contract_id_finds_own_row_and_not_foreign(): void
    {
        $this->actingAsContractsViewer();
        $own = $this->makeListContract();
        $foreign = $this->makeListContract(['school_id' => $this->foreignPartner->id]);

        $ids = collect($this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'         => 1,
                'start'        => 0,
                'length'       => 50,
                'search_value' => (string) $own->id,
            ]))
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();

        $this->assertSame([$own->id], $ids);
        $this->assertNotContains($foreign->id, $ids);

        $foreignHits = collect($this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'         => 1,
                'start'        => 0,
                'length'       => 50,
                'search_value' => (string) $foreign->id,
            ]))
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();

        $this->assertNotContains($foreign->id, $foreignHits);
    }

    public function test_ajax_data_rejects_invalid_length_and_search_with_field_errors(): void
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

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'         => 1,
                'start'        => 0,
                'length'       => 20,
                'search_value' => str_repeat('x', 256),
            ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['search_value']]);
    }

    public function test_ajax_columns_settings_save_rejects_empty_and_non_array_with_field_error(): void
    {
        $this->actingAsContractsViewer();

        $this->postJson(route('contracts.columns-settings.save'), [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['columns']]);

        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => 'contract_number',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['columns']]);
    }

    public function test_ajax_hiding_contract_number_column_persists_for_viewer(): void
    {
        $this->actingAsContractsViewer();

        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => [
                'contract_number' => false,
                'user_name'       => true,
            ],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->getJson(route('contracts.columns-settings.get'), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('contract_number', false)
            ->assertJsonPath('user_name', true);
    }

    public function test_ajax_data_default_and_number_column_order_are_id_desc_and_not_500(): void
    {
        $this->actingAsContractsViewer();
        $first = $this->makeListContract();
        $second = $this->makeListContract();

        $defaultIds = collect($this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'             => 1,
                'start'            => 0,
                'length'           => 20,
                'order[0][column]' => 1,
                'order[0][dir]'    => 'desc',
            ]))
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();

        $this->assertSame([$second->id, $first->id], $defaultIds);

        $asc = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'             => 1,
                'start'            => 0,
                'length'           => 20,
                'order[0][column]' => 1,
                'order[0][dir]'    => 'asc',
            ]));

        $this->assertNotSame(500, $asc->getStatusCode());
        $asc->assertOk();
        $this->assertSame(
            [$first->id, $second->id],
            collect($asc->json('data'))->pluck('id')->all()
        );
    }
}
