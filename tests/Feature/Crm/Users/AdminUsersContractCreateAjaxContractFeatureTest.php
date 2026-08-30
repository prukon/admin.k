<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Storage;

/**
 * P1: AJAX-контракт модалки создания договора со списка клиентов:
 * users-search / user-group / check-balance → JSON 200 или 422 с message.
 *
 * Submit самой формы — native POST (см. NonAjaxSafetyNet), не JSON store.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersContractCreateAjaxContractFeatureTest extends AdminUsersContractCreateTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsUsersViewer(withContractsView: true);
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 50000;
        $this->partner->save();
    }

    public function test_users_search_ajax_returns_student_for_prefill_from_clients_table(): void
    {
        $student = $this->createStudent([
            'lastname' => 'ПоискМодалки',
            'name'     => 'Иван',
        ]);

        $response = $this->getJson(
            route('contracts.users.search', ['q' => 'ПоискМодалки']),
            $this->ajaxHeaders()
        )->assertOk()->assertJsonStructure(['results']);

        $match = collect($response->json('results'))->firstWhere('id', $student->id);
        $this->assertNotNull($match);
        $this->assertArrayHasKey('parent_full_name', $match);
        $this->assertArrayHasKey('text', $match);
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_user_group_ajax_returns_groups_array_for_selected_student(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа модалки клиентов',
        ]);
        $student = $this->createStudent(['lastname' => 'ГруппаМодалки']);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [(int) $team->id]);

        $this->getJson(
            route('contracts.user.group', ['user_id' => $student->id]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonStructure(['groups'])
            ->assertJsonPath('groups.0.id', $team->id)
            ->assertJsonPath('groups.0.title', 'Группа модалки клиентов');
    }

    public function test_user_group_ajax_without_user_id_returns_422_with_field_error(): void
    {
        $this->getJson(route('contracts.user.group'), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_user_group_ajax_for_foreign_student_returns_empty_groups_not_500(): void
    {
        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
            'lastname'   => 'ЧужойGroupUx',
        ]);

        $this->getJson(
            route('contracts.user.group', ['user_id' => $foreign->id]),
            $this->ajaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('groups', []);
    }

    public function test_check_balance_ajax_ok_when_wallet_covers_fee(): void
    {
        $this->postJson('/client-contracts/check-balance', [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'balance', 'fee']);
    }

    public function test_check_balance_ajax_returns_422_message_when_wallet_is_empty(): void
    {
        $this->partner->wallet_balance_cents = 0;
        $this->partner->save();

        $this->postJson('/client-contracts/check-balance', [], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Недостаточно средств для создания договора.');
    }

    public function test_ajax_store_validation_does_not_return_empty_200_or_500(): void
    {
        Storage::fake();

        $response = $this->postJson(route('contracts.store'), [
            'creation_mode' => Contract::CREATION_MODE_PDF,
        ], $this->ajaxHeaders());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 422]);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_opening_modal_does_not_charge_wallet(): void
    {
        $this->get(route('admin.user1'))->assertOk();

        $this->assertSame(50000, (int) $this->partner->fresh()->wallet_balance_cents);
    }
}
