<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\Contract;

/**
 * P1: полный доступ админа/viewer с обоими правами; PUT/PATCH/DELETE не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersContractCreateFullAccessFeatureTest extends AdminUsersContractCreateTestCase
{
    public function test_admin_all_create_from_clients_endpoints_return_200(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantContractsView($this->user);
        $this->partner->wallet_balance_cents = 50000;
        $this->partner->save();

        $student = $this->createStudent(['lastname' => 'АдминCreate']);
        $contract = $this->createContractForUser($student, Contract::STATUS_DRAFT);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('id="createContractModal"', false)
            ->assertSee('js-open-create-contract-from-user', false)
            ->assertSee('Посмотреть черновик', false)
            ->assertSee('users-contract-add-btn', false)
            ->assertSee('Создать ещё один договор', false);

        foreach ($this->featureEndpoints($student, $contract) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? []
            );
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Админ: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame('', trim((string) $response->getContent()), $item['url']);
        }
    }

    public function test_viewer_with_both_permissions_gets_200_on_lookups_used_by_modal(): void
    {
        $this->actingAsUsersViewer(withContractsView: true);
        $this->partner->wallet_balance_cents = 50000;
        $this->partner->save();

        $student = $this->createStudent(['lastname' => 'ViewerCreate']);
        $contract = $this->createContractForUser($student, Contract::STATUS_SIGNED);

        foreach ($this->featureEndpoints($student, $contract) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? []
            );
            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Viewer: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_unsupported_methods_on_lookups_do_not_return_500(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantContractsView($this->user);

        $student = $this->createStudent(['lastname' => 'МетодыCreate']);

        $targets = [
            route('admin.user1'),
            '/admin/users/data',
            route('contracts.users.search'),
            route('contracts.user.group', ['user_id' => $student->id]),
            url('/client-contracts/check-balance'),
        ];

        foreach ($targets as $url) {
            foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
                $response = $this->json($method, $url, []);
                $this->assertNotSame(500, $response->getStatusCode(), $method . ' ' . $url);
                $this->assertContains(
                    $response->getStatusCode(),
                    [404, 405, 419],
                    $method . ' ' . $url . ' → ' . $response->getStatusCode()
                );
            }
        }
    }
}
