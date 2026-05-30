<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Contract;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Колонка «Договор» на /admin/users и доступ к странице при users.view / contracts.view.
 */
final class AdminUsersContractColumnFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function grantUsersView(User $actor): void
    {
        $this->grantPermission($actor, 'users.view');
    }

    private function grantContractsView(User $actor): void
    {
        $this->grantPermission($actor, 'contracts.view');
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    private function actingAsUsersViewer(bool $withContractsView = false): User
    {
        $missingPermission = $withContractsView ? 'users.view' : 'contracts.view';
        $actor = $this->createUserWithoutPermission($missingPermission, $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        if ($withContractsView) {
            $this->grantContractsView($actor);
        }

        return $actor;
    }

    private function createContractForUser(User $user, string $status, ?\DateTimeInterface $createdAt = null): Contract
    {
        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/contract-' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'status'          => $status,
        ]);

        if ($createdAt !== null) {
            $contract->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $contract->fresh();
    }

    private function fetchUsersDataRow(string $lastname): ?array
    {
        $response = $this->getJson('/admin/users/data?draw=1&start=0&length=100&name=' . urlencode($lastname));

        $response->assertOk();

        return collect($response->json('data'))->first(function (array $row) use ($lastname) {
            return str_contains((string) ($row['name'] ?? ''), $lastname);
        });
    }

    // --- UI колонки «Договор» ---

    public function test_users_page_renders_contract_column_when_contracts_view_granted(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewIs('admin.user')
            ->assertViewHas('canViewContracts', true)
            ->getContent();

        $parentPos = strpos($html, '>Родитель</th>');
        $contractPos = strpos($html, '>Договор</th>');
        $teamsPos = strpos($html, '>Группа</th>');

        $this->assertNotFalse($parentPos);
        $this->assertNotFalse($contractPos);
        $this->assertNotFalse($teamsPos);
        $this->assertLessThan($contractPos, $parentPos);
        $this->assertLessThan($teamsPos, $contractPos);

        $this->assertStringContainsString('id="colContract"', $html);
        $this->assertStringContainsString('data-column-key="contract"', $html);
        $this->assertStringContainsString('canViewContracts = true', $html);
        $this->assertStringContainsString("'#0d6efd'", $html);
        $this->assertStringContainsString("'#6c757d'", $html);
        $this->assertStringContainsString("'Статус: ' + statusLabel", $html);
        $this->assertStringContainsString('renderContractCell', $html);
    }

    public function test_users_page_hides_contract_column_without_contracts_view(): void
    {
        $this->actingAsUsersViewer(withContractsView: false);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('canViewContracts', false)
            ->assertDontSee('>Договор</th>', false)
            ->assertDontSee('id="colContract"', false)
            ->assertDontSee('data-column-key="contract"', false);
    }

    // --- JSON DataTables: latest_contract ---

    public function test_users_data_omits_latest_contract_without_contracts_view(): void
    {
        $this->actingAsUsersViewer(withContractsView: false);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'БезContractsView',
            'name'       => 'Ученик',
        ]);

        $this->createContractForUser($user, Contract::STATUS_SIGNED);

        $row = $this->fetchUsersDataRow('БезContractsView');

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('latest_contract', $row);
    }

    public function test_users_data_omits_latest_contract_for_user_without_contract(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'БезДоговора',
            'name'       => 'Ученик',
        ]);

        $row = $this->fetchUsersDataRow('БезДоговора');

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('latest_contract', $row);
    }

    public function test_users_data_returns_signed_contract_payload(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ПодписанныйДоговор',
            'name'       => 'Ученик',
        ]);

        $contract = $this->createContractForUser($user, Contract::STATUS_SIGNED);

        $row = $this->fetchUsersDataRow('ПодписанныйДоговор');

        $this->assertNotNull($row);
        $this->assertArrayHasKey('latest_contract', $row);
        $this->assertSame(route('contracts.show', $contract->id), $row['latest_contract']['url']);
        $this->assertSame(Contract::STATUS_SIGNED, $row['latest_contract']['status']);
        $this->assertSame('Подписано', $row['latest_contract']['status_label']);

        $this->get($row['latest_contract']['url'])->assertOk();
    }

    public function test_users_data_returns_non_signed_contract_payload(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ЧерновикДоговор',
            'name'       => 'Ученик',
        ]);

        $contract = $this->createContractForUser($user, Contract::STATUS_DRAFT);

        $row = $this->fetchUsersDataRow('ЧерновикДоговор');

        $this->assertNotNull($row);
        $this->assertSame(route('contracts.show', $contract->id), $row['latest_contract']['url']);
        $this->assertSame(Contract::STATUS_DRAFT, $row['latest_contract']['status']);
        $this->assertSame('Черновик', $row['latest_contract']['status_label']);
        $this->assertNotSame(Contract::STATUS_SIGNED, $row['latest_contract']['status']);
    }

    public function test_users_data_uses_latest_contract_when_user_has_multiple(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'НесколькоДоговоров',
            'name'       => 'Ученик',
        ]);

        $this->createContractForUser($user, Contract::STATUS_DRAFT, now()->subDays(2));
        $latest = $this->createContractForUser($user, Contract::STATUS_SENT, now()->subDay());

        $row = $this->fetchUsersDataRow('НесколькоДоговоров');

        $this->assertNotNull($row);
        $this->assertSame(route('contracts.show', $latest->id), $row['latest_contract']['url']);
        $this->assertSame(Contract::STATUS_SENT, $row['latest_contract']['status']);
        $this->assertSame('Отправлено', $row['latest_contract']['status_label']);
    }

    public function test_users_data_ignores_contract_from_foreign_partner(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ЧужойПартнерДоговор',
            'name'       => 'Ученик',
        ]);

        Contract::create([
            'school_id'       => $this->foreignPartner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/test/foreign.pdf',
            'source_sha256'   => str_repeat('b', 64),
            'status'          => Contract::STATUS_SIGNED,
        ]);

        $row = $this->fetchUsersDataRow('ЧужойПартнерДоговор');

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('latest_contract', $row);
    }

    public function test_users_data_sorts_teams_at_column_index_five_with_contracts_view(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $teamAlpha = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'AlphaContractCol',
        ]);
        $teamBeta = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'BetaContractCol',
        ]);

        $u1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamBeta->id,
            'lastname'   => 'SortBeta',
            'name'       => 'Ученик',
        ]);
        $u2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamAlpha->id,
            'lastname'   => 'SortAlpha',
            'name'       => 'Ученик',
        ]);

        $this->createContractForUser($u1, Contract::STATUS_DRAFT);
        $this->createContractForUser($u2, Contract::STATUS_SIGNED);

        $json = $this->getJson('/admin/users/data?draw=1&start=0&length=100&order[0][column]=5&order[0][dir]=asc&name=Sort')->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $posU1 = array_search($u1->id, $ids, true);
        $posU2 = array_search($u2->id, $ids, true);

        $this->assertNotFalse($posU1);
        $this->assertNotFalse($posU2);
        $this->assertTrue($posU2 < $posU1, 'Ожидали сортировку по группе в колонке 5 при наличии колонки «Договор»');
    }

    public function test_users_table_settings_accepts_contract_column_key(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'avatar'   => true,
                'name'     => true,
                'parent'   => true,
                'contract' => false,
                'teams'    => true,
            ],
        ])->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertJsonPath('contract', false);
    }

    // --- Доступ: страница и endpoint’ы ---

    public function test_guest_cannot_access_users_page_and_contract_data(): void
    {
        Auth::logout();

        $this->get(route('admin.user1'))->assertRedirect();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertUnauthorized();
    }

    public function test_users_page_and_all_section_endpoints_return_ok_with_users_view_and_contracts_view(): void
    {
        $actor = $this->actingAsUsersViewer(withContractsView: true);

        $roleId = $this->studentRoleId();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $existingField = UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('users_contract_smoke_field')
            ->create(['name' => 'Contract smoke поле']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'lastname'   => 'ContractSmoke',
            'name'       => 'Студент',
            'is_enabled' => 1,
        ]);

        $contract = $this->createContractForUser($student, Contract::STATUS_SIGNED);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('activeTab', 'users')
            ->assertViewHas('canViewContracts', true)
            ->assertSee('>Договор</th>', false);

        $dataResponse = $this->getJson('/admin/users/data?draw=1&start=0&length=50&name=ContractSmoke');
        $dataResponse->assertOk()->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data',
        ]);

        $row = collect($dataResponse->json('data'))->firstWhere('id', $student->id);
        $this->assertNotNull($row);
        $this->assertSame(route('contracts.show', $contract->id), $row['latest_contract']['url']);

        $this->getJson('/admin/users/data?draw=1&status=active&search[value]=ContractSmoke')->assertOk();
        $this->getJson('/admin/users/data?draw=1&team_id=' . $team->id)->assertOk();
        $this->getJson('/admin/users/data?draw=1&order[0][column]=5&order[0][dir]=asc')->assertOk();

        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'avatar'   => true,
                'name'     => true,
                'parent'   => true,
                'contract' => true,
                'teams'    => true,
                'email'    => false,
            ],
        ])->assertOk();

        $this->getJson(route('logs.data.user', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $this->postJson(route('admin.field.store'), [
            'fields' => [
                [
                    'id'         => $existingField->id,
                    'name'       => $existingField->name,
                    'field_type' => 'string',
                    'roles'      => [],
                ],
            ],
        ])->assertOk();

        $this->getJson(route('admin.users.parents.search', ['q' => 'Contract']))->assertOk();

        $store = $this->postJson(route('admin.user.store'), [
            'name'       => 'Новый',
            'lastname'   => 'ContractSmoke',
            'email'      => 'users-contract-smoke-' . uniqid('', true) . '@example.test',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $userId = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $userId);

        $this->getJson(route('admin.user.edit', $userId))->assertOk();
        $this->patchJson(route('admin.user.update', $userId), [
            'name'     => 'Новый',
            'lastname' => 'Обновлён',
        ])->assertOk();

        $this->get($row['latest_contract']['url'])->assertOk();
    }

    public function test_users_page_and_data_return_ok_with_users_view_only_without_contract_fields(): void
    {
        $this->actingAsUsersViewer(withContractsView: false);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'ТолькоUsersView',
            'name'       => 'Ученик',
        ]);

        $this->createContractForUser($user, Contract::STATUS_SIGNED);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('canViewContracts', false)
            ->assertDontSee('>Договор</th>', false);

        $row = collect($this->getJson('/admin/users/data?draw=1&start=0&length=50&name=ТолькоUsersView')
            ->assertOk()
            ->json('data'))->first();

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('latest_contract', $row);
    }

    public function test_users_page_and_endpoints_return_forbidden_without_users_view(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);
        $this->grantContractsView($denied);

        $peer = User::factory()->create(['partner_id' => $this->partner->id]);
        $roleId = $this->studentRoleId();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertForbidden();
        $this->getJson(route('admin.users.table-settings.get'))->assertForbidden();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['contract' => true],
        ])->assertForbidden();
        $this->getJson(route('admin.users.parents.search', ['q' => 'test']))->assertForbidden();
        $this->getJson(route('logs.data.user', ['draw' => 1]))->assertForbidden();
        $this->postJson(route('admin.field.store'), ['fields' => []])->assertForbidden();
        $this->postJson(route('admin.user.store'), [
            'name'       => 'Нет',
            'lastname'   => 'Права',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ])->assertForbidden();
        $this->getJson(route('admin.user.edit', $peer->id))->assertForbidden();
        $this->patchJson(route('admin.user.update', $peer->id), [
            'name'     => 'Н',
            'lastname' => 'Т',
        ])->assertForbidden();
    }
}
