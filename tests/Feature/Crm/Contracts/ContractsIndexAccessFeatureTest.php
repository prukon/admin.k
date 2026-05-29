<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ParentProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Доступ к странице списка договоров и связанным эндпоинтам модалки создания.
 */
class ContractsIndexAccessFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 500;
        $this->partner->save();
    }

    /** @test */
    public function guest_is_redirected_from_contracts_index_and_json_returns_401(): void
    {
        Auth::logout();

        $this->get(route('contracts.index'))->assertStatus(302);
        $this->get(route('contracts.create'))->assertStatus(302);

        $this->getJson(route('contracts.data', ['draw' => 1]))->assertStatus(401);
        $this->getJson(route('contracts.columns-settings.get'))->assertStatus(401);
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => ['user_name' => true],
        ])->assertStatus(401);
        $this->getJson(route('contracts.users.search', ['q' => 'test']))->assertStatus(401);
        $this->postJson('/client-contracts/check-balance')->assertStatus(401);
    }

    /** @test */
    public function user_without_contracts_view_gets_403_on_index_and_related_endpoints(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index'))->assertStatus(403);
        $this->get(route('contracts.create'))->assertStatus(403);
        $this->get(route('contracts.index', ['create' => 1]))->assertStatus(403);

        $this->getJson(route('contracts.data', ['draw' => 1]))->assertStatus(403);
        $this->getJson(route('contracts.columns-settings.get'))->assertStatus(403);
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => ['user_name' => true],
        ])->assertStatus(403);
        $this->getJson(route('contracts.users.search', ['q' => 'test']))->assertStatus(403);
        $this->getJson(route('contracts.user.group', ['user_id' => $student->id]))->assertStatus(403);
        $this->postJson('/client-contracts/check-balance')->assertStatus(403);
    }

    /** @test */
    public function user_with_contracts_view_gets_200_on_index_page_and_modal_variants(): void
    {
        $student = $this->createStudentWithTeam();

        $this->get(route('contracts.index'))->assertOk();
        $this->get(route('contracts.index', ['create' => 1]))->assertOk();
        $this->get(route('contracts.index', ['user_id' => $student->id]))->assertOk();

        $this->get(route('contracts.create'))
            ->assertRedirect(route('contracts.index', ['create' => 1]));

        $this->get(route('contracts.create', ['user_id' => $student->id]))
            ->assertRedirect(route('contracts.index', [
                'create'  => 1,
                'user_id' => $student->id,
            ]));
    }

    /** @test */
    public function user_with_contracts_view_gets_200_on_index_with_parent_prefill(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Access',
            'firstname'  => 'Parent',
            'middlename' => 'Index',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'parent_id'  => $parent->id,
            'is_enabled' => 1,
            'name'       => 'AccessChild',
            'lastname'   => 'IndexStudent',
        ]);

        $this->get(route('contracts.index', [
            'create'  => 1,
            'user_id' => $student->id,
        ]))
            ->assertOk()
            ->assertViewHas('shouldOpenCreateModal', true)
            ->assertSee('id="parent_full_name_display"', false)
            ->assertViewHas('preselectedUser', function ($pre) {
                return is_array($pre)
                    && ($pre['parent_full_name'] ?? null) === 'Access Parent Index';
            });
    }

    /** @test */
    public function user_with_contracts_view_gets_200_on_all_index_ajax_endpoints(): void
    {
        Storage::fake();

        $student = $this->createStudentWithTeam();

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => $student->team_id,
            'source_pdf_path' => 'documents/index-access/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('b', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        Storage::put($contract->source_pdf_path, '%PDF-index-access');

        $this->getJson(route('contracts.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $this->getJson(route('contracts.data', [
            'draw'         => 1,
            'start'        => 0,
            'length'       => 10,
            'search_value' => 'Index',
            'group_id'     => (string) $student->team_id,
            'status'       => Contract::STATUS_DRAFT,
        ]))->assertOk();

        $this->getJson(route('contracts.columns-settings.get'))->assertOk();

        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => [
                'user_name'     => true,
                'user_lastname' => true,
                'team_title'    => true,
                'status_label'  => true,
                'actions'       => true,
            ],
        ])->assertOk();

        $this->getJson(route('contracts.users.search', ['q' => 'Index']))
            ->assertOk()
            ->assertJsonStructure([
                'results' => [
                    [
                        'id',
                        'text',
                        'parent_full_name',
                    ],
                ],
            ]);

        $this->getJson(route('contracts.users.search', ['q' => '']))
            ->assertOk()
            ->assertJsonStructure(['results']);

        $this->getJson(route('contracts.user.group', ['user_id' => $student->id]))
            ->assertOk()
            ->assertJsonStructure(['groups']);

        $this->postJson('/client-contracts/check-balance')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'fee', 'balance']);
    }

    /** @test */
    public function dedicated_contracts_view_user_can_use_index_and_lookups(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);
        $this->grantPermissionToRoleForPartner($actor->role_id, $this->partner->id, self::PERM_CONTRACTS_VIEW);

        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Dedicated',
            'firstname'  => 'Parent',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'parent_id'  => $parent->id,
            'team_id'    => Team::factory()->create([
                'partner_id' => $this->partner->id,
                'title'      => 'Dedicated Group',
            ])->id,
            'is_enabled' => 1,
            'name'       => 'DedicatedChild',
            'lastname'   => 'IndexStudent',
        ]);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index'))->assertOk();
        $this->get(route('contracts.index', ['create' => 1]))->assertOk();
        $this->get(route('contracts.index', ['user_id' => $student->id]))->assertOk();

        $this->getJson(route('contracts.data', ['draw' => 1, 'start' => 0, 'length' => 5]))->assertOk();
        $this->getJson(route('contracts.columns-settings.get'))->assertOk();
        $this->postJson(route('contracts.columns-settings.save'), [
            'columns' => ['user_name' => true],
        ])->assertOk();

        $this->getJson(route('contracts.users.search', ['q' => 'DedicatedChild']))
            ->assertOk()
            ->assertJsonPath('results.0.parent_full_name', 'Dedicated Parent');

        $this->getJson(route('contracts.user.group', ['user_id' => $student->id]))->assertOk();
        $this->postJson('/client-contracts/check-balance')->assertOk();
    }

    /** @test */
    public function user_without_contracts_view_gets_403_on_users_search_with_parent_query(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Forbidden',
            'firstname'  => 'Parent',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'parent_id'  => $parent->id,
            'is_enabled' => 1,
            'name'       => 'ForbiddenChild',
            'lastname'   => 'AccessDenied',
        ]);

        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index', ['user_id' => $student->id]))->assertStatus(403);
        $this->getJson(route('contracts.users.search', ['q' => 'ForbiddenChild']))->assertStatus(403);
    }

    /** @test */
    public function guest_gets_401_on_users_search_with_parent_data_query(): void
    {
        Auth::logout();

        $this->getJson(route('contracts.users.search', ['q' => 'guest-parent-test']))->assertStatus(401);
    }

    private function createStudentWithTeam(): User
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Index Access Group',
        ]);

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'name'       => 'Index',
            'lastname'   => 'AccessStudent',
        ]);
    }
}
