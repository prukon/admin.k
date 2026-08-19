<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Доступ к неймингу клиентов: гость / без права / со правом; не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersClientNamingFullAccessFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_cannot_open_users_or_rules_or_mutate_students(): void
    {
        Auth::logout();
        $student = $this->makeStudent();

        foreach ($this->namingEndpoints($student) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertNotSame(500, $response->getStatusCode(), "{$item['method']} {$item['url']}");
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $web = $this->get(route('admin.user1'));
        $this->assertNotSame(200, $web->getStatusCode());
        $web->assertStatus(302);

        $this->getJson(route('admin.user1'))->assertStatus(401);
    }

    public function test_manager_without_users_view_gets_403_on_student_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $student = $this->makeStudent();

        foreach ($this->studentCrudEndpoints($student) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertNotSame(500, $response->getStatusCode(), "{$item['method']} {$item['url']}");
            $response->assertForbidden();
        }
    }

    public function test_manager_without_settings_roles_view_gets_403_on_rules_page(): void
    {
        $actor = $this->createUserWithoutPermission('settings.roles.view', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $this->get(route('admin.setting.rule'))->assertForbidden();
        $this->getJson(route('admin.setting.rule'))->assertForbidden();
    }

    public function test_manager_without_trainers_view_gets_403_on_trainers_tab(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $this->get(route('admin.trainers.index'))->assertForbidden();
    }

    public function test_admin_with_rights_gets_200_on_users_trainers_and_rules_pages(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);
        $this->grantTrainersView($this->user);
        $this->grantPermission($this->user, 'settings.roles.view');

        foreach ([
            route('admin.user1'),
            route('admin.trainers.index'),
            route('admin.administrators.index'),
            route('admin.setting.rule'),
        ] as $url) {
            $response = $this->get($url);
            $this->assertNotSame(500, $response->getStatusCode(), $url);
            $response->assertOk();
            $this->assertNotSame('', trim((string) $response->getContent()), $url);
        }
    }

    public function test_admin_can_store_update_and_delete_student_over_http(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $email = 'client-access-store-' . uniqid('', true) . '@example.test';

        $store = $this->postJson(route('admin.user.store'), [
            'name'       => 'Доступ',
            'lastname'   => 'Клиентов',
            'email'      => $email,
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $this->assertNotSame(500, $store->getStatusCode());
        $store->assertOk()->assertJsonPath('message', 'Клиент создан успешно');
        $id = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $id);

        $update = $this->patchJson(route('admin.user.update', $id), [
            'name'       => 'Обновлён',
            'lastname'   => 'Клиентов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertNotSame(500, $update->getStatusCode());
        $update->assertOk();

        $delete = $this->deleteJson(route('admin.user.delete', $id), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $this->assertNotSame(500, $delete->getStatusCode());
        $delete->assertOk();
    }

    public function test_unsupported_methods_on_users_and_rules_pages_are_not_server_errors(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantPermission($this->user, 'settings.roles.view');

        foreach ([route('admin.user1'), route('admin.setting.rule')] as $url) {
            foreach (['patch', 'put', 'delete'] as $method) {
                $response = $this->{$method}($url);
                $this->assertNotSame(500, $response->getStatusCode(), "{$method} {$url}");
                $this->assertContains($response->getStatusCode(), [404, 405], "{$method} {$url}");
            }
        }
    }

    public function test_delete_foreign_partner_student_is_not_found(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $foreign = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
        ]);

        $this->deleteJson(route('admin.user.delete', $foreign->id), [], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $foreign->id]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function namingEndpoints(User $student): array
    {
        return array_merge($this->studentCrudEndpoints($student), [
            [
                'method'  => 'GET',
                'url'     => route('admin.setting.rule'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('admin.trainers.index'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ]);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function studentCrudEndpoints(User $student): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.user1'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'POST',
                'url'     => route('admin.user.store'),
                'data'    => [
                    'name'       => 'Гость',
                    'lastname'   => 'Клиентов',
                    'role_id'    => $this->studentRoleId(),
                    'is_enabled' => 1,
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'GET',
                'url'     => route('admin.user.edit', $student->id),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'PATCH',
                'url'     => route('admin.user.update', $student->id),
                'data'    => [
                    'name'     => 'Патч',
                    'lastname' => 'Клиентов',
                    'role_id'  => $this->studentRoleId(),
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
            [
                'method'  => 'DELETE',
                'url'     => route('admin.user.delete', $student->id),
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ],
            ],
        ];
    }

    private function makeStudent(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Цель',
            'lastname'   => 'Доступа',
        ]);
    }
}
