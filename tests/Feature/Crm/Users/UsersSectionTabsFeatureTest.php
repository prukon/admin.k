<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\Users\UsersSectionTabsResolver;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Раздел «Пользователи»: динамические вкладки по ролям (UsersSectionTabsResolver + UI).
 */
final class UsersSectionTabsFeatureTest extends CrmTestCase
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

    public function test_all_system_role_tabs_visible_with_full_permissions(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);
        $this->grantTrainersView($this->user);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('>Пользователи</a>', false)
            ->assertSee('>Тренеры</a>', false)
            ->assertSee('>Администраторы</a>', false)
            ->assertSee('href="' . route('admin.user1') . '"', false)
            ->assertSee('href="' . route('admin.trainers.index') . '"', false)
            ->assertSee('href="' . route('admin.administrators.index') . '"', false);
    }

    public function test_users_tab_hidden_without_users_role_update(): void
    {
        $actor = $this->createUserWithoutPermission('users.role.update', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);
        $this->grantTrainersView($actor);

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertSee('>Тренеры</a>', false)
            ->assertDontSee('>Пользователи</a>', false)
            ->assertDontSee('>Администраторы</a>', false);
    }

    public function test_trainers_tab_hidden_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);
        $this->grantStaffSectionAccess($actor);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('>Пользователи</a>', false)
            ->assertDontSee('>Тренеры</a>', false);
    }

    public function test_administrators_tab_hidden_without_users_role_update(): void
    {
        $actor = $this->createUserWithoutPermission('users.role.update', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertDontSee('>Администраторы</a>', false)
            ->assertDontSee('href="' . route('admin.administrators.index') . '"', false);
    }

    public function test_custom_role_tab_appears_with_users_role_update(): void
    {
        $role = $this->createPartnerCustomRole('manager_tab', 'Менеджеры');

        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('>Менеджеры</a>', false)
            ->assertSee(
                'href="' . route('admin.roles.users.index', ['role' => $role->name]) . '"',
                false
            );
    }

    public function test_resolver_never_includes_superadmin_role(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);
        $this->grantTrainersView($this->user);

        $tabs = app(UsersSectionTabsResolver::class)->resolve();
        $labels = collect($tabs)->pluck('label')->all();
        $routes = collect($tabs)->pluck('route')->all();

        $this->assertNotContains('Суперадмин', $labels);
        $this->assertFalse(collect($tabs)->contains(fn (array $tab) => str_contains($tab['id'], 'superadmin')));
        $this->assertContains('admin.user1', $routes);
        $this->assertContains('admin.trainers.index', $routes);
        $this->assertContains('admin.administrators.index', $routes);
    }

    public function test_reserved_system_roles_return_404_on_custom_roles_route(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        foreach (['user', 'admin', 'trainer', 'superadmin'] as $reserved) {
            $this->get(route('admin.roles.users.index', ['role' => $reserved]))
                ->assertNotFound();
        }
    }

    public function test_users_page_locks_student_role_in_create_modal(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $studentRoleId = $this->studentRoleId();

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('name="role_id" value="' . $studentRoleId . '"', false)
            ->assertDontSee('id="create_role_id"', false);
    }

    private function createPartnerCustomRole(string $machineName, string $label): Role
    {
        $role = Role::create([
            'name'       => $machineName . '_' . str_replace('.', '', uniqid('', true)),
            'label'      => $label,
            'is_sistem'  => 0,
            'is_visible' => 1,
            'order_by'   => (Role::max('order_by') ?? 0) + 10,
        ]);

        DB::table('partner_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id'    => $role->id,
        ]);

        return $role;
    }
}
