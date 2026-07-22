<?php

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Редактирование ФИО родителя в режиме «Из справочника» (общая карточка parents).
 *
 * @see /docs/documentation/admin-users.html §2.1.2
 * @see /docs/documentation/parents-and-family-cabinet.html
 */
final class AdminUsersParentDirectoryFioFeatureTest extends CrmTestCase
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

    public function test_guest_cannot_access_directory_fio_endpoints(): void
    {
        Auth::logout();

        $parent = ParentProfile::factory()->create(['partner_id' => $this->partner->id]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
        ]);

        $this->get(route('admin.user1'))->assertRedirect();
        $this->getJson(route('admin.users.parents.search', ['q' => 'А']))->assertUnauthorized();
        $this->getJson(route('admin.user.edit', $student->id))->assertUnauthorized();
        $this->postJson(route('admin.user.store'), [])->assertUnauthorized();
        $this->patchJson(route('admin.user.update', $student->id), [])->assertUnauthorized();
    }

    public function test_authenticated_without_users_view_gets_403_on_directory_fio_endpoints(): void
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
            ->getJson(route('admin.users.parents.search', ['q' => '']))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('admin.user.edit', $student->id))
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('admin.user.store'), [
                'name'     => 'X',
                'lastname' => 'Y',
                'role_id'  => $this->studentRoleId(),
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->patchJson(route('admin.user.update', $student->id), [
                'name'     => $student->name,
                'lastname' => $student->lastname,
                'role_id'  => $student->role_id,
            ])
            ->assertForbidden();
    }

    public function test_users_page_markup_keeps_fio_visible_in_directory_mode(): void
    {
        ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Справочник',
            'firstname'  => 'Родитель',
        ]);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertNotFalse($html);
        $this->assertStringContainsString('syncParentSelectLabelFromFio', $html);
        $this->assertStringContainsString('ФИО всегда доступны для просмотра/правки', $html);
        $this->assertMatchesRegularExpression(
            '/class="js-parent-fio-section"(?![^>]*\bd-none\b)/',
            $html
        );
        $this->assertStringNotContainsString("fioSection.toggleClass('d-none', !isNew)", $html);
    }

    public function test_store_with_parent_id_and_edited_fio_updates_directory_card(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Старая',
            'firstname'  => 'Карточка',
            'middlename' => 'Была',
        ]);

        $response = $this->postJson(route('admin.user.store'), [
            'name'              => 'Сын',
            'lastname'          => 'Новый',
            'role_id'           => $this->studentRoleId(),
            'parent_id'         => $parent->id,
            'parent_lastname'   => 'Исправленная',
            'parent_firstname'  => 'Карточка',
            'parent_middlename' => 'Стала',
            'parent_passport'   => '4010 999888',
            'is_enabled'        => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id']]);

        $this->assertNotSame('', trim((string) $response->getContent()));

        $student = User::query()->findOrFail((int) $response->json('user.id'));
        $this->assertSame($parent->id, $student->parent_id);

        $parent->refresh();
        $this->assertSame('Исправленная', $parent->lastname);
        $this->assertSame('Стала', $parent->middlename);
        $this->assertSame('4010 999888', $parent->passport);
        $this->assertSame(1, ParentProfile::query()->where('partner_id', $this->partner->id)->count());
    }

    public function test_update_with_parent_id_edits_shared_fio_and_profile_fields(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'До',
            'firstname'  => 'Правки',
            'phone'      => '79001112233',
            'email'      => 'old@example.com',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Ученик',
            'lastname'   => 'Тестов',
        ]);

        $response = $this->patchJson(route('admin.user.update', $student->id), [
            'name'                  => $student->name,
            'lastname'              => $student->lastname,
            'role_id'               => $student->role_id,
            'parent_id'             => $parent->id,
            'parent_lastname'       => 'После',
            'parent_firstname'      => 'Правки',
            'parent_middlename'     => 'ФИО',
            'parent_passport'       => '4500 111222',
            'parent_passport_issued'=> 'ОВД тест',
            'parent_address'        => 'г. Тест, ул. 1',
            'parent_phone'          => '+7 900 222-33-44',
            'parent_email'          => 'New@Example.com',
            'is_enabled'            => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('message', 'Пользователь успешно обновлён');

        $this->assertNotSame('', trim((string) $response->getContent()));

        $parent->refresh();
        $this->assertSame('После', $parent->lastname);
        $this->assertSame('ФИО', $parent->middlename);
        $this->assertSame('4500 111222', $parent->passport);
        $this->assertSame('ОВД тест', $parent->passport_issued);
        $this->assertSame('г. Тест, ул. 1', $parent->address);
        $this->assertSame('79002223344', $parent->phone);
        $this->assertSame('new@example.com', $parent->email);
        $this->assertSame($parent->id, $student->fresh()->parent_id);
    }

    public function test_sibling_sees_directory_fio_change_in_datatable_and_edit_json(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Общая',
            'firstname'  => 'Мама',
        ]);

        $childA = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Аня',
            'lastname'   => 'Дочь',
        ]);

        $childB = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'parent_id'  => $parent->id,
            'name'       => 'Боря',
            'lastname'   => 'Сын',
        ]);

        $this->patchJson(route('admin.user.update', $childA->id), [
            'name'             => $childA->name,
            'lastname'         => $childA->lastname,
            'role_id'          => $childA->role_id,
            'parent_id'        => $parent->id,
            'parent_lastname'  => 'НоваяОбщая',
            'parent_firstname' => 'Мама',
            'parent_middlename'=> 'Ивановна',
            'is_enabled'       => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $data = $this->getJson('/admin/users/data?id=' . $childB->id, [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()->json();

        $row = collect($data['data'])->firstWhere('id', $childB->id);
        $this->assertNotNull($row);
        $this->assertSame('НоваяОбщая Мама Ивановна', $row['parent']);

        $this->getJson(route('admin.user.edit', $childB->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.parent_id', $parent->id)
            ->assertJsonPath('user.parent_lastname', 'НоваяОбщая')
            ->assertJsonPath('user.parent_firstname', 'Мама')
            ->assertJsonPath('user.parent_middlename', 'Ивановна');

        $this->getJson(route('admin.users.parents.search', ['q' => 'НоваяОбщая']), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonFragment(['text' => 'НоваяОбщая Мама Ивановна']);
    }

    public function test_update_validation_rejects_foreign_parent_id_with_422(): void
    {
        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'lastname'   => 'Чужой',
            'firstname'  => 'Родитель',
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Локальный',
            'lastname'   => 'Ученик',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), [
            'name'             => $student->name,
            'lastname'         => $student->lastname,
            'role_id'          => $student->role_id,
            'parent_id'        => $foreignParent->id,
            'parent_lastname'  => 'Попытка',
            'parent_firstname' => 'Взлома',
            'is_enabled'       => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $foreignParent->refresh();
        $this->assertSame('Чужой', $foreignParent->lastname);
        $this->assertNull($student->fresh()->parent_id);
    }
}
