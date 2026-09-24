<?php

namespace Tests\Feature\Crm\Users;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Отчество ученика (users.middlename) на /admin/users.
 *
 * @see /docs/documentation/admin-users.html §2.8
 */
final class UserMiddlenameFieldFeatureTest extends CrmTestCase
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
        $this->grantPermission($this->user, 'users.name.update');
    }

    public function test_users_table_has_middlename_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'middlename'));
    }

    public function test_ajax_store_persists_trimmed_middlename_and_empty_string_as_null(): void
    {
        $this->postJson('/admin/users', [
            'name'               => 'Иван',
            'lastname'           => 'Отчествов',
            'middlename'         => '  Иванович  ',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $withMiddle = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Отчествов')
            ->first();

        $this->assertNotNull($withMiddle);
        $this->assertSame('Иванович', $withMiddle->middlename);
        $this->assertSame('Отчествов Иван', $withMiddle->full_name);
        $this->assertSame('Отчествов Иван Иванович', $withMiddle->fullNameWithPatronymic());

        $this->postJson('/admin/users', [
            'name'               => 'Пётр',
            'lastname'           => 'БезОтчества',
            'middlename'         => '   ',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $withoutMiddle = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'БезОтчества')
            ->first();

        $this->assertNotNull($withoutMiddle);
        $this->assertNull($withoutMiddle->middlename);
        $this->assertSame('БезОтчества Пётр', $withoutMiddle->full_name);
    }

    public function test_ajax_store_validation_returns_middlename_error_under_field_key(): void
    {
        $this->postJson('/admin/users', [
            'name'               => 'Иван',
            'lastname'           => 'Длинный',
            'middlename'         => str_repeat('А', 101),
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.middlename.0', 'Отчество не должно превышать 100 символов.');
    }

    public function test_ajax_update_persists_middlename_and_writes_audit_line(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Иван',
            'lastname'   => 'Журналов',
            'middlename' => null,
        ]);

        $this->patchJson('/admin/users/'.$user->id, [
            'name'       => 'Иван',
            'lastname'   => 'Журналов',
            'middlename' => 'Петрович',
        ])->assertOk()
            ->assertJsonPath('message', 'Клиент успешно обновлён');

        $this->assertSame('Петрович', $user->fresh()->middlename);

        $log = MyLog::query()
            ->where('target_type', User::class)
            ->where('target_id', $user->id)
            ->where('event', AuditEvent::UserUpdated->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Отчество: — → Петрович', (string) $log->description);
    }

    public function test_ajax_update_clears_middlename_when_blank_sent(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Иван',
            'lastname'   => 'Очистка',
            'middlename' => 'Сергеевич',
        ]);

        $this->patchJson('/admin/users/'.$user->id, [
            'name'       => 'Иван',
            'lastname'   => 'Очистка',
            'middlename' => '   ',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertNull($fresh->middlename);
        $this->assertSame('Очистка Иван', $fresh->full_name);
    }

    public function test_update_without_name_permission_ignores_middlename(): void
    {
        $actor = $this->createUserWithoutPermission('users.name.update', $this->partner);
        $this->grantUsersView($actor);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Иван',
            'lastname'   => 'Закрытый',
            'middlename' => 'Оставить',
        ]);

        $this->actingAs($actor)
            ->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed'      => true,
            ])
            ->patchJson('/admin/users/'.$student->id, [
                'middlename' => 'Подменить',
            ])
            ->assertOk();

        $this->assertSame('Оставить', $student->fresh()->middlename);
    }

    public function test_edit_json_returns_middlename(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'name'       => 'Иван',
            'lastname'   => 'Карточка',
            'middlename' => 'Иванович',
        ]);

        $this->getJson('/admin/users/'.$user->id.'/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.middlename', 'Иванович')
            ->assertJsonPath('user.full_name', 'Карточка Иван');
    }

    public function test_users_table_name_omits_middlename_but_search_finds_it(): void
    {
        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'lastname'   => 'СписокФам',
            'name'       => 'СписокИмя',
            'middlename' => 'ПоискОтчестваУник',
        ]);

        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'lastname'   => 'ДругойФам',
            'name'       => 'ДругоеИмя',
            'middlename' => null,
        ]);

        $response = $this->getJson('/admin/users/data?draw=1&start=0&length=50&name=ПоискОтчестваУник');

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertSame($target->id, $response->json('data.0.id'));
        $this->assertSame('СписокФам СписокИмя', $response->json('data.0.name'));
        $this->assertStringNotContainsString('ПоискОтчестваУник', (string) $response->json('data.0.name'));
    }

    public function test_users_page_has_middlename_fields_in_modals_and_not_as_table_column(): void
    {
        $html = $this->get('/admin/users')->assertOk()->getContent();

        $this->assertStringContainsString('id="create-middlename"', $html);
        $this->assertStringContainsString('id="edit-middlename"', $html);
        $this->assertStringContainsString('Отчество ученика', $html);
        $this->assertStringContainsString('name="middlename"', $html);
        $this->assertStringNotContainsString('<th>Отчество</th>', $html);

        $fill = "\$('#edit-user-form #edit-middlename').val(response.user.middlename || '')";
        $this->assertGreaterThanOrEqual(2, substr_count($html, $fill));
    }

    public function test_guest_cannot_store_middlename(): void
    {
        Auth::logout();

        $this->postJson('/admin/users', [
            'name'       => 'Гость',
            'lastname'   => 'Отчество',
            'middlename' => 'Гостевич',
            'role_id'    => $this->studentRoleId(),
        ])->assertUnauthorized();
    }
}
