<?php

namespace Tests\Feature\Crm\Parents;

use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Полный доступ (200) к семейному кабинету, блоку родителя в account-settings и CRUD родителя в админке.
 *
 * @see /docs/documentation/parents-and-family-cabinet.html
 * @see /docs/documentation/admin-users.html
 */
final class ParentsAndFamilyCabinetFullAccessFeatureTest extends CrmTestCase
{
    private ParentProfile $sharedParent;

    private User $brother1;

    private User $brother2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->createSiblingStudents();
    }

    public function test_guest_cannot_access_family_cabinet_and_parent_admin_endpoints(): void
    {
        Auth::logout();

        foreach ($this->familyCabinetRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        foreach ($this->adminParentRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость (админка): {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_student_without_dashboard_view_gets_403_on_cabinet_and_switch(): void
    {
        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->grantPermission($actor, 'myPayments.view');
        $this->actingAs($actor);

        $this->get(route('dashboard'))->assertForbidden();
        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $this->brother2->id,
        ])->assertForbidden();
    }

    public function test_student_without_my_payments_view_gets_403_on_payments_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('myPayments.view', $this->partner);
        $this->grantPermission($actor, 'dashboard.view');
        $this->actingAs($actor);

        $this->get(route('showUserPayments'))->assertForbidden();
        $this->getJson('/getUserPayments?draw=1&start=0&length=10')->assertForbidden();
    }

    public function test_student_without_account_user_view_gets_403_on_account_parent_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('account.user.view', $this->partner);
        $this->actingAs($actor);

        $this->get(route('account.user.edit'))->assertForbidden();
        $this->patchJson(route('account.user.update'), [
            'name'            => $actor->name,
            'lastname'        => $actor->lastname,
            'parent_lastname' => 'Тест',
        ], $this->jsonHeaders())->assertForbidden();
    }

    public function test_student_with_permissions_all_family_cabinet_endpoints_return_200(): void
    {
        $this->actingAs($this->brother1);
        $this->grantPermission($this->brother1, 'myPayments.view');

        foreach ($this->familyCabinetRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Семейный кабинет: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }

        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $this->brother2->id,
        ])->assertRedirect();
    }

    public function test_cabinet_layout_shows_family_switcher_and_parent_identity_for_siblings(): void
    {
        $this->actingAs($this->brother1);
        $this->grantPermission($this->brother1, 'dashboard.view');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Просмотр данных ученика', false)
            ->assertSee('family-active-student', false)
            ->assertSee('Иванова Мария', false)
            ->assertSee('mama@family.test', false)
            ->assertSee($this->brother1->full_name ?: $this->brother1->name, false)
            ->assertSee($this->brother2->full_name ?: $this->brother2->name, false);
    }

    public function test_dashboard_reflects_active_student_after_switch(): void
    {
        $this->actingAs($this->brother1);
        $this->grantPermission($this->brother1, 'dashboard.view');

        $this->brother2->forceFill(['email' => 'vasya-active@family.test'])->save();

        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $this->brother2->id,
        ])->assertRedirect();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('vasya-active@family.test', false);
    }

    public function test_student_account_parent_endpoints_return_200(): void
    {
        $this->actingAs($this->brother1);

        $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertSee('Данные родителя', false);

        $this->patchJson(route('account.user.update'), [
            'name'              => $this->brother1->name,
            'lastname'          => $this->brother1->lastname,
            'parent_lastname'   => 'Иванова',
            'parent_firstname'  => 'Мария',
            'parent_middlename' => null,
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_without_users_view_gets_403_on_parent_admin_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);

        foreach ($this->adminParentRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без users.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_admin_with_users_view_all_parent_admin_endpoints_return_200(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $this->sharedParent->id,
        ]);

        foreach ($this->adminParentRoutesPayload($student) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "Админка родители: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_lonely_student_dashboard_has_no_family_switcher(): void
    {
        $lonely = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => null,
            'is_enabled' => true,
        ]);

        $this->actingAs($lonely);
        $this->grantPermission($lonely, 'dashboard.view');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Просмотр данных ученика', false)
            ->assertDontSee('family-active-student', false);
    }

    public function test_second_sibling_login_sees_same_family_switcher_and_parent_identity(): void
    {
        $this->actingAs($this->brother2);
        $this->grantPermission($this->brother2, 'dashboard.view');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Просмотр данных ученика', false)
            ->assertSee('Иванова Мария', false)
            ->assertSee('mama@family.test', false)
            ->assertSee($this->brother1->full_name ?: $this->brother1->name, false)
            ->assertSee($this->brother2->full_name ?: $this->brother2->name, false);
    }

    public function test_student_without_account_parent_update_permission_cannot_change_parent_via_patch(): void
    {
        $this->revokePermissionFromRole('user', 'account.user.parent.update');

        $this->actingAs($this->brother1);

        $this->sharedParent->update([
            'lastname'  => 'Иванова',
            'firstname' => 'Мария',
        ]);

        $this->patchJson(route('account.user.update'), [
            'name'              => $this->brother1->name,
            'lastname'          => $this->brother1->lastname,
            'parent_lastname'   => 'Попытка',
            'parent_firstname'  => 'Взлом',
        ], $this->jsonHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->sharedParent->refresh();
        $this->assertSame('Иванова', $this->sharedParent->lastname);
        $this->assertSame('Мария', $this->sharedParent->firstname);
    }

    public function test_get_user_payments_returns_only_active_student_after_switch(): void
    {
        Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $this->brother1->id,
            'summ'       => 100,
        ]);

        Payment::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $this->brother2->id,
            'summ'       => 900,
        ]);

        $this->actingAs($this->brother1);
        $this->grantPermission($this->brother1, 'myPayments.view');

        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $this->brother2->id,
        ])->assertRedirect();

        $this->assertSame($this->brother2->id, session(FamilyStudentContextService::SESSION_KEY));

        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson('/getUserPayments?draw=1&start=0&length=10')
            ->assertOk()
            ->json();

        $rows = $json['data'] ?? [];
        $this->assertCount(1, $rows);
        $this->assertSame($this->brother2->id, (int) ($rows[0]['user_id'] ?? 0));
    }

    private function createSiblingStudents(): void
    {
        $roleId = $this->studentRoleId();

        $this->sharedParent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Иванова',
            'firstname'  => 'Мария',
            'email'      => 'mama@family.test',
        ]);

        $this->brother1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $this->sharedParent->id,
            'lastname'   => 'Иванов',
            'name'       => 'Петя',
            'is_enabled' => true,
        ]);

        $this->brother2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $roleId,
            'parent_id'  => $this->sharedParent->id,
            'lastname'   => 'Иванов',
            'name'       => 'Вася',
            'is_enabled' => true,
        ]);
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function familyCabinetRoutesPayload(): array
    {
        return [
            [
                'method'  => 'GET',
                'url'     => route('dashboard'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('showUserPayments'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method'  => 'GET',
                'url'     => '/getUserPayments?draw=1&start=0&length=10',
                'headers' => ['HTTP_X-Requested-With' => 'XMLHttpRequest'],
            ],
            [
                'method'  => 'GET',
                'url'     => route('account.user.edit'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function adminParentRoutesPayload(?User $student = null): array
    {
        $student ??= User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $this->sharedParent->id,
            'is_enabled' => true,
        ]);

        return [
            [
                'method'  => 'GET',
                'url'     => route('admin.user1'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url'    => '/admin/users/data?draw=1&start=0&length=10',
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.users.parents.search', ['q' => 'Иванова']),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.user.edit', $student->id),
            ],
            [
                'method' => 'GET',
                'url'    => route('admin.users.table-settings.get'),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.users.table-settings.save'),
                'data'   => ['columns' => ['parent' => true, 'name' => true]],
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.user.store'),
                'data'   => [
                    'name'              => 'Новый',
                    'lastname'          => 'Ученик',
                    'role_id'           => $this->studentRoleId(),
                    'parent_id'         => $this->sharedParent->id,
                    'is_enabled'        => 1,
                ],
            ],
            [
                'method' => 'PATCH',
                'url'    => route('admin.user.update', $student->id),
                'data'   => [
                    'name'              => $student->name,
                    'lastname'          => $student->lastname,
                    'role_id'           => $student->role_id,
                    'parent_id'         => $this->sharedParent->id,
                    'parent_lastname'   => 'Иванова',
                    'parent_firstname'  => 'Мария',
                    'is_enabled'        => 1,
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return [
            'Accept'           => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
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

    private function revokePermissionFromRole(string $roleName, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('role_id', $this->roleId($roleName))
            ->where('partner_id', $this->partner->id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }
}
