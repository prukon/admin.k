<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * P1: native GET/POST без X-Requested-With для data и колонок — не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListNumberAndFillRemainingNonAjaxSafetyNetFeatureTest extends ContractListNumberAndFillRemainingTestCase
{
    public function test_native_get_data_still_returns_contract_id(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeListContract();

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
        $this->assertSame($contract->id, $row['id']);
        $this->assertArrayNotHasKey('fill_expires_remaining', $row);
    }

    public function test_native_get_data_returns_remaining_when_fill_expires_permission_granted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $this->actingAsFillExpiresViewer();
        $contract = $this->makeTemplateContract(Carbon::parse('2026-09-14 12:00:00', 'Europe/Moscow'));

        $response = $this->from(route('contracts.index'))
            ->get(route('contracts.data', [
                'draw'   => 1,
                'start'  => 0,
                'length' => 20,
            ]), ['HTTP_ACCEPT' => 'text/html']);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));

        $row = collect($response->json('data'))->firstWhere('id', $contract->id);
        $this->assertIsArray($row);
        $this->assertSame('2 дня', $row['fill_expires_remaining']);
        $this->assertTrue($row['fill_expires_remaining_warn']);
    }

    public function test_native_post_columns_settings_still_saves_contract_number_flag(): void
    {
        $this->actingAsContractsViewer();

        $response = $this->from(route('contracts.index'))
            ->post(route('contracts.columns-settings.save'), [
                'columns' => [
                    'contract_number' => false,
                    'user_name'       => true,
                ],
            ], [
                'HTTP_ACCEPT' => 'text/html',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk()->assertJson(['success' => true]);

        $this->get(route('contracts.columns-settings.get'))
            ->assertOk()
            ->assertJsonPath('contract_number', false);
    }

    public function test_native_post_columns_settings_without_columns_is_not_empty_200(): void
    {
        $this->actingAsContractsViewer();

        $response = $this->from(route('contracts.index'))
            ->post(route('contracts.columns-settings.save'), [], [
                'HTTP_ACCEPT' => 'text/html',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonStructure(['message', 'errors' => ['columns']]);
    }

    public function test_guest_native_get_data_does_not_return_rows(): void
    {
        $this->makeListContract();

        Auth::logout();
        $response = $this->from(route('contracts.index'))
            ->get(route('contracts.data', [
                'draw'   => 1,
                'start'  => 0,
                'length' => 20,
            ]), ['HTTP_ACCEPT' => 'text/html']);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
    }
}
