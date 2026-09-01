<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * P1: доступ к созданию договора со списка клиентов — гость / без users.view / без contracts.view.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersContractCreateAccessFeatureTest extends AdminUsersContractCreateTestCase
{
    public function test_guest_is_denied_on_users_contract_create_endpoints_without_500(): void
    {
        Auth::logout();

        $student = $this->createStudent(['lastname' => 'ГостьКонтракт']);
        $contract = $this->createContractForUser($student, Contract::STATUS_DRAFT);

        $jsonEndpoints = [
            ['method' => 'GET', 'url' => '/admin/users/data?draw=1&start=0&length=10'],
            ['method' => 'GET', 'url' => route('contracts.users.search', ['q' => $student->lastname])],
            ['method' => 'GET', 'url' => route('contracts.user.group', ['user_id' => $student->id])],
            ['method' => 'POST', 'url' => url('/client-contracts/check-balance')],
        ];

        foreach ($this->featureEndpoints($student, $contract) as $item) {
            $web = $this->call($item['method'], $item['url'], $item['data'] ?? []);
            $this->assertNotSame(500, $web->getStatusCode(), $item['method'] . ' ' . $item['url']);
            $this->assertNotSame(200, $web->getStatusCode(), $item['method'] . ' ' . $item['url']);
            $this->assertContains($web->getStatusCode(), [302, 401, 403, 419], $item['method'] . ' ' . $item['url']);
        }

        foreach ($jsonEndpoints as $item) {
            $json = $this->json($item['method'], $item['url'], $item['data'] ?? []);
            $this->assertNotSame(500, $json->getStatusCode(), 'JSON ' . $item['method'] . ' ' . $item['url']);
            $this->assertContains($json->getStatusCode(), [401, 403, 419], 'JSON ' . $item['method'] . ' ' . $item['url']);
        }
    }

    public function test_manager_without_users_view_gets_403_even_with_contracts_view(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->grantContractsView($actor);

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertForbidden();
    }

    public function test_manager_with_users_view_only_gets_200_but_cannot_open_contract_lookups(): void
    {
        $this->actingAsUsersViewer(withContractsView: false);
        $student = $this->createStudent(['lastname' => 'ТолькоUsersViewCreate']);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertDontSee('id="createContractModal"', false)
            ->assertDontSee('users-contract-add-btn', false)
            ->assertDontSee('Создать ещё один договор', false);

        $this->getJson('/admin/users/data?draw=1&start=0&length=10&name=ТолькоUsersViewCreate')
            ->assertOk();

        $this->getJson(route('contracts.users.search', ['q' => 'ТолькоUsersViewCreate']))
            ->assertForbidden();
        $this->getJson(route('contracts.user.group', ['user_id' => $student->id]))
            ->assertForbidden();
        $this->postJson('/client-contracts/check-balance')
            ->assertForbidden();
        $this->post(route('contracts.store'), [
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'user_id'       => $student->id,
        ])->assertForbidden();
    }

    public function test_manager_with_users_and_contracts_view_can_open_page_and_lookups(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);
        $student = $this->createStudent(['lastname' => 'ОбаПраваCreate']);
        $this->partner->wallet_balance_cents = 50000;
        $this->partner->save();

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('id="createContractModal"', false)
            ->assertViewHas('contractTemplates')
            ->assertViewHas('contractCreatePartner')
            ->assertSee('users-contract-add-btn', false)
            ->assertSee('Создать ещё один договор', false);

        $this->getJson(route('contracts.users.search', ['q' => 'ОбаПраваCreate']))
            ->assertOk()
            ->assertJsonStructure(['results']);
        $this->getJson(route('contracts.user.group', ['user_id' => $student->id]))
            ->assertOk()
            ->assertJsonStructure(['groups']);
        $this->postJson('/client-contracts/check-balance')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_guest_store_from_clients_page_is_denied_without_500(): void
    {
        Auth::logout();

        $student = $this->createStudent(['lastname' => 'ГостьStore']);

        $web = $this->from(route('admin.user1'))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_PDF,
                'user_id'       => $student->id,
            ]);
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());
        $this->assertContains($web->getStatusCode(), [302, 401, 403, 419]);

        $json = $this->postJson(route('contracts.store'), [
            'creation_mode' => Contract::CREATION_MODE_PDF,
            'user_id'       => $student->id,
        ]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertContains($json->getStatusCode(), [401, 403, 419]);
        $this->assertSame(0, Contract::query()->count());
    }

    public function test_foreign_student_is_not_offered_in_users_search_from_clients_page_context(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);

        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
            'lastname'   => 'ЧужойКонтрактUx',
            'name'       => 'Ученик',
        ]);

        $results = $this->getJson(route('contracts.users.search', ['q' => 'ЧужойКонтрактUx']))
            ->assertOk()
            ->json('results');

        $ids = collect($results)->pluck('id')->all();
        $this->assertNotContains($foreign->id, $ids);
    }
}
