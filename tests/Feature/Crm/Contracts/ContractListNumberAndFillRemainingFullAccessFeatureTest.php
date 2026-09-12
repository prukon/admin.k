<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use Illuminate\Support\Carbon;

/**
 * Полный доступ: admin/superadmin 200 на список и data; чужие HTTP-методы не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListNumberAndFillRemainingFullAccessFeatureTest extends ContractListNumberAndFillRemainingTestCase
{
    public function test_admin_gets_200_on_list_and_data_with_contract_number(): void
    {
        $this->asAdmin();
        $contract = $this->makeListContract();

        $index = $this->get(route('contracts.index'));
        $index->assertOk();
        $this->assertNotSame('', trim((string) $index->getContent()));
        $index->assertSee('<th>Номер договора</th>', false);

        $row = $this->dataRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame($contract->id, $row['id']);
        $this->assertArrayNotHasKey('fill_expires_remaining', $row);
    }

    public function test_superadmin_sees_remaining_days_without_role_assignment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $this->asSuperadmin();
        $contract = $this->makeTemplateContract(Carbon::parse('2026-09-14 12:00:00', 'Europe/Moscow'));

        $index = $this->get(route('contracts.index'));
        $index->assertOk();
        $index->assertSee('<th>Номер договора</th>', false);
        $index->assertSee('<th>Срок подписания</th>', false);
        $this->assertStringContainsString("order: [[1, 'desc']]", $index->getContent());

        $row = $this->dataRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame('2 дня', $row['fill_expires_remaining']);
        $this->assertTrue($row['fill_expires_remaining_warn']);
    }

    public function test_wrong_http_methods_on_data_and_columns_settings_are_not_500(): void
    {
        $this->actingAsContractsViewer();

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $jsonData = $this->json($method, route('contracts.data', ['draw' => 1]));
            $this->assertNotSame(500, $jsonData->getStatusCode(), 'JSON data '.$method);
            $this->assertNotSame(200, $jsonData->getStatusCode(), 'JSON data '.$method);
            $this->assertContains($jsonData->getStatusCode(), [404, 405], 'JSON data '.$method);
            $this->assertNotSame('', trim((string) $jsonData->getContent()), 'JSON data '.$method);
        }

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $jsonColumns = $this->json($method, route('contracts.columns-settings.get'));
            $this->assertNotSame(500, $jsonColumns->getStatusCode(), 'JSON columns GET-route '.$method);
            $this->assertNotSame(200, $jsonColumns->getStatusCode(), 'JSON columns GET-route '.$method);
            $this->assertContains($jsonColumns->getStatusCode(), [404, 405], 'JSON columns GET-route '.$method);

            $jsonSave = $this->json($method, route('contracts.columns-settings.save'), [
                'columns' => ['contract_number' => true],
            ]);
            $this->assertNotSame(500, $jsonSave->getStatusCode(), 'JSON columns save '.$method);
            $this->assertNotSame(200, $jsonSave->getStatusCode(), 'JSON columns save '.$method);
            $this->assertContains($jsonSave->getStatusCode(), [404, 405], 'JSON columns save '.$method);
        }
    }
}
