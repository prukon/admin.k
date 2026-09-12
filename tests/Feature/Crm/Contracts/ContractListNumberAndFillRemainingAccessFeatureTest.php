<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к номеру договора и остатку дней: гость, без права, тренер, чужая школа, viewer.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractListNumberAndFillRemainingAccessFeatureTest extends ContractListNumberAndFillRemainingTestCase
{
    public function test_guest_is_denied_on_list_data_and_columns_settings(): void
    {
        $this->makeListContract();

        Auth::logout();

        $index = $this->get(route('contracts.index'), ['HTTP_ACCEPT' => 'text/html']);
        $this->assertNotSame(500, $index->getStatusCode());
        $this->assertNotSame(200, $index->getStatusCode());
        $index->assertRedirect();

        $data = $this->getJson(route('contracts.data', ['draw' => 1]));
        $this->assertNotSame(500, $data->getStatusCode());
        $this->assertContains($data->getStatusCode(), [401, 403]);

        $this->getJson(route('contracts.columns-settings.get'))->assertStatus(401);
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => ['contract_number' => true],
        ])->assertStatus(401);
    }

    public function test_manager_without_contracts_view_gets_403_on_list_data_and_columns(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index'))->assertForbidden();
        $this->getJson(route('contracts.data', ['draw' => 1]))->assertForbidden();
        $this->getJson(route('contracts.columns-settings.get'))->assertForbidden();
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => ['contract_number' => true],
        ])->assertForbidden();
    }

    public function test_trainer_without_contracts_view_cannot_open_list(): void
    {
        $trainer = $this->createUserWithRole('trainer');

        $this->actingAs($trainer)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index'))->assertForbidden();
        $this->getJson(route('contracts.data', ['draw' => 1]))->assertForbidden();
    }

    public function test_viewer_sees_contract_number_column_but_not_remaining_without_fill_expires_permission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $this->actingAsContractsViewer();
        $contract = $this->makeTemplateContract(Carbon::parse('2026-09-14 15:00:00', 'Europe/Moscow'));

        $index = $this->get(route('contracts.index'));
        $index->assertOk();
        $this->assertNotSame('', trim((string) $index->getContent()));
        $index->assertSee('<th>Номер договора</th>', false);
        $index->assertSee('data-column-key="contract_number"', false);
        $index->assertDontSee('<th>Срок подписания</th>', false);

        $row = $this->dataRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame($contract->id, $row['id']);
        $this->assertArrayNotHasKey('fill_expires_at', $row);
        $this->assertArrayNotHasKey('fill_expires_remaining', $row);
        $this->assertArrayNotHasKey('fill_expires_remaining_warn', $row);
    }

    public function test_viewer_with_fill_expires_permission_sees_remaining_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $this->actingAsFillExpiresViewer();
        $contract = $this->makeTemplateContract(Carbon::parse('2026-09-14 15:00:00', 'Europe/Moscow'));

        $index = $this->get(route('contracts.index'));
        $index->assertOk();
        $index->assertSee('<th>Номер договора</th>', false);
        $index->assertSee('<th>Срок подписания</th>', false);

        $row = $this->dataRowFor($contract);
        $this->assertIsArray($row);
        $this->assertSame($contract->id, $row['id']);
        $this->assertSame('2 дня', $row['fill_expires_remaining']);
        $this->assertTrue($row['fill_expires_remaining_warn']);
    }

    public function test_trainer_with_contracts_view_sees_number_column_but_not_remaining(): void
    {
        $trainer = $this->createUserWithRole('trainer');
        $this->grantPermissionToRoleForPartner(
            (int) $trainer->role_id,
            $this->partner->id,
            self::PERM_CONTRACTS_VIEW
        );

        $this->actingAs($trainer)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $html = $this->get(route('contracts.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<th>Номер договора</th>', $html);
        $this->assertStringNotContainsString('<th>Срок подписания</th>', $html);
        $this->assertStringContainsString("order: [[1, 'desc']]", $html);
    }

    public function test_foreign_school_cannot_see_this_school_contract_by_id_search(): void
    {
        $contract = $this->makeListContract();

        $this->grantPermissionToRoleForPartner(
            $this->foreignUser->role_id,
            $this->foreignPartner->id,
            self::PERM_CONTRACTS_VIEW
        );
        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true]);

        $ids = collect($this->withHeaders($this->ajaxHeaders())
            ->getJson(route('contracts.data', [
                'draw'         => 1,
                'start'        => 0,
                'length'       => 50,
                'search_value' => (string) $contract->id,
            ]))
            ->assertOk()
            ->json('data'))
            ->pluck('id')
            ->all();

        $this->assertNotContains($contract->id, $ids);
    }
}
