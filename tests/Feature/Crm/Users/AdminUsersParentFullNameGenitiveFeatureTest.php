<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * ФИО родителя в родительном падеже (parents.full_name_genitive) в админке /admin/users.
 *
 * @see /docs/documentation/admin-users.html §2.1
 * @see /docs/documentation/parents-and-family-cabinet.html
 */
final class AdminUsersParentFullNameGenitiveFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        $this->grantUsersView($this->user);
    }

    /** @return array<string, string> */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_guest_cannot_save_or_read_parent_genitive(): void
    {
        Auth::logout();

        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'full_name_genitive' => 'Иванова Ивана Ивановича',
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $this->get(route('admin.user1'))->assertRedirect();
        $this->getJson(route('admin.user.edit', $student->id))->assertUnauthorized();
        $this->getJson(route('admin.users.parents.search', ['q' => 'Иванов']))->assertUnauthorized();
        $this->postJson(route('admin.user.store'), [
            'name'                       => 'A',
            'lastname'                   => 'B',
            'role_id'                    => $this->studentRoleId(),
            'parent_full_name_genitive'  => 'Петрова Петра Петровича',
        ])->assertUnauthorized();
        $this->patchJson(route('admin.user.update', $student->id), [
            'name'                       => $student->name,
            'lastname'                   => $student->lastname,
            'role_id'                    => $student->role_id,
            'parent_full_name_genitive'  => 'Сидорова Сидора Сидоровича',
        ])->assertUnauthorized();
    }

    public function test_manager_without_users_view_gets_403_on_parent_genitive_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $this->actingAs($actor)->withSession($session)
            ->get(route('admin.user1'))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('admin.user.edit', $student->id))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('admin.users.parents.search', ['q' => '']))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('admin.user.store'), [
                'name'                      => 'X',
                'lastname'                  => 'Y',
                'role_id'                   => $this->studentRoleId(),
                'parent_full_name_genitive' => 'Тестова Теста Тестовича',
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->patchJson(route('admin.user.update', $student->id), [
                'name'                      => $student->name,
                'lastname'                  => $student->lastname,
                'role_id'                   => $student->role_id,
                'parent_full_name_genitive' => 'Тестова Теста Тестовича',
            ])
            ->assertForbidden();
    }

    public function test_store_ajax_creates_parent_with_genitive_and_returns_user(): void
    {
        $response = $this->postJson(route('admin.user.store'), [
            'name'                      => 'Ребёнок',
            'lastname'                  => 'GenitiveAjax',
            'role_id'                   => $this->studentRoleId(),
            'parent_lastname'           => 'Иванов',
            'parent_firstname'          => 'Иван',
            'parent_middlename'         => 'Иванович',
            'parent_full_name_genitive' => 'Иванова Ивана Ивановича',
            'is_enabled'                => 1,
        ], $this->ajaxHeaders());

        $response->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id']]);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $student = User::query()->findOrFail((int) $response->json('user.id'));
        $this->assertNotNull($student->parent_id);
        $this->assertDatabaseHas('parents', [
            'id'                 => $student->parent_id,
            'full_name_genitive' => 'Иванова Ивана Ивановича',
            'lastname'           => 'Иванов',
        ]);
    }

    public function test_store_ajax_validation_returns_422_when_genitive_too_long(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name'                      => 'Fail',
            'lastname'                  => 'Genitive',
            'role_id'                   => $this->studentRoleId(),
            'parent_lastname'           => 'Иванов',
            'parent_firstname'          => 'Иван',
            'parent_full_name_genitive' => str_repeat('а', 301),
            'is_enabled'                => 1,
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_full_name_genitive']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'Genitive',
            'name'       => 'Fail',
        ]);
    }

    public function test_update_ajax_updates_directory_parent_genitive_shared_card(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Петров',
            'firstname'          => 'Пётр',
            'full_name_genitive' => 'Старое Родительное',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $sibling = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'                      => $student->name,
            'lastname'                  => $student->lastname,
            'role_id'                   => $student->role_id,
            'parent_id'                 => $parent->id,
            'parent_lastname'           => 'Петров',
            'parent_firstname'          => 'Пётр',
            'parent_middlename'         => null,
            'parent_full_name_genitive' => 'Петрова Петра Петровича',
            'is_enabled'                => 1,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Пользователь успешно обновлён');

        $parent->refresh();
        $this->assertSame('Петрова Петра Петровича', $parent->full_name_genitive);

        $sibling->load('parentProfile');
        $this->assertSame('Петрова Петра Петровича', $sibling->parentProfile?->full_name_genitive);
    }

    public function test_update_ajax_clears_genitive_when_field_sent_empty(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Сидоров',
            'firstname'          => 'Сидор',
            'full_name_genitive' => 'Сидорова Сидора Сидоровича',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'                      => $student->name,
            'lastname'                  => $student->lastname,
            'role_id'                   => $student->role_id,
            'parent_id'                 => $parent->id,
            'parent_lastname'           => 'Сидоров',
            'parent_firstname'          => 'Сидор',
            'parent_middlename'         => '',
            'parent_full_name_genitive' => '',
            'is_enabled'                => 1,
        ], $this->ajaxHeaders())
            ->assertOk();

        $parent->refresh();
        $this->assertNull($parent->full_name_genitive);
        $this->assertSame('Сидоров', $parent->lastname);
    }

    public function test_edit_json_includes_parent_full_name_genitive(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Редактиров',
            'firstname'          => 'Родитель',
            'full_name_genitive' => 'Редактирова Родителя Родителевича',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $this->getJson(route('admin.user.edit', $student->id), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('user.parent_id', $parent->id)
            ->assertJsonPath('user.parent_lastname', 'Редактиров')
            ->assertJsonPath('user.parent_full_name_genitive', 'Редактирова Родителя Родителевича');
    }

    public function test_parents_search_returns_genitive_for_directory_fill(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'Поисков',
            'firstname'          => 'Родитель',
            'full_name_genitive' => 'Поискова Родителя Родителевича',
        ]);

        $response = $this->getJson(
            route('admin.users.parents.search', ['q' => 'Поисков']),
            $this->ajaxHeaders()
        )->assertOk();

        $this->assertNotSame('', trim((string) $response->getContent()));
        $results = $response->json('results');
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        $row = collect($results)->firstWhere('id', $parent->id);
        $this->assertNotNull($row);
        $this->assertSame('Поискова Родителя Родителевича', $row['parent_full_name_genitive']);
    }

    public function test_store_non_ajax_redirects_and_persists_genitive(): void
    {
        $this->post(route('admin.user.store'), [
            'name'                      => 'NonAjax',
            'lastname'                  => 'GenitiveSave',
            'role_id'                   => $this->studentRoleId(),
            'parent_lastname'           => 'NonAjaxРодитель',
            'parent_firstname'          => 'Имя',
            'parent_full_name_genitive' => 'NonAjaxРодителя Имени Отчества',
            'is_enabled'                => 1,
        ])->assertRedirect(route('admin.user1'));

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'GenitiveSave')
            ->first();

        $this->assertNotNull($student);
        $this->assertDatabaseHas('parents', [
            'id'                 => $student->parent_id,
            'full_name_genitive' => 'NonAjaxРодителя Имени Отчества',
        ]);
    }

    public function test_update_non_ajax_redirects_and_updates_genitive(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id'         => $this->partner->id,
            'lastname'           => 'ДоNonAjax',
            'firstname'          => 'Род',
            'full_name_genitive' => 'Старое',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $this->patch(route('admin.user.update', $student->id), [
            'name'                      => $student->name,
            'lastname'                  => $student->lastname,
            'role_id'                   => $student->role_id,
            'parent_id'                 => $parent->id,
            'parent_lastname'           => 'ПослеNonAjax',
            'parent_firstname'          => 'Род',
            'parent_full_name_genitive' => 'ПослеNonAjax Рода Отчества',
            'is_enabled'                => 1,
        ])->assertRedirect(route('admin.user1'));

        $this->assertSame('ПослеNonAjax Рода Отчества', $parent->fresh()->full_name_genitive);
    }

    public function test_non_ajax_validation_failure_for_genitive_redirects_with_field_error(): void
    {
        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), [
                'name'                      => 'Fail',
                'lastname'                  => 'NonAjaxGen',
                'role_id'                   => $this->studentRoleId(),
                'parent_lastname'           => 'X',
                'parent_firstname'          => 'Y',
                'parent_full_name_genitive' => str_repeat('б', 301),
                'is_enabled'                => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['parent_full_name_genitive']);
    }

    public function test_users_page_renders_genitive_field_after_middlename_before_passport(): void
    {
        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('ФИО родителя в родительном падеже', false)
            ->assertSee('name="parent_full_name_genitive"', false)
            ->assertSee('id="create-parent-full-name-genitive"', false)
            ->assertSee('id="edit-parent-full-name-genitive"', false)
            ->assertSee('js-parent-full-name-genitive', false)
            ->assertSee('maxlength="300"', false)
            ->getContent();

        $createMiddlenamePos = strpos($html, 'id="create-parent-middlename"');
        $createGenitivePos = strpos($html, 'id="create-parent-full-name-genitive"');
        $createPassportPos = strpos($html, 'id="create-parent-passport"');

        $this->assertNotFalse($createMiddlenamePos);
        $this->assertNotFalse($createGenitivePos);
        $this->assertNotFalse($createPassportPos);
        $this->assertLessThan($createGenitivePos, $createMiddlenamePos);
        $this->assertLessThan($createPassportPos, $createGenitivePos);

        $editMiddlenamePos = strpos($html, 'id="edit-parent-middlename"');
        $editGenitivePos = strpos($html, 'id="edit-parent-full-name-genitive"');
        $editPassportPos = strpos($html, 'id="edit-parent-passport"');

        $this->assertNotFalse($editMiddlenamePos);
        $this->assertNotFalse($editGenitivePos);
        $this->assertNotFalse($editPassportPos);
        $this->assertLessThan($editGenitivePos, $editMiddlenamePos);
        $this->assertLessThan($editPassportPos, $editGenitivePos);
    }
}
