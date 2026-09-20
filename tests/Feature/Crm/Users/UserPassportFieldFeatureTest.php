<?php

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Поля паспорта / св-ва о рождении ученика (users.passport, users.passport_issued_at).
 *
 * @see /docs/documentation/admin-users.html §2.7
 */
final class UserPassportFieldFeatureTest extends CrmTestCase
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

    public function test_users_table_has_passport_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'passport'));
        $this->assertTrue(Schema::hasColumn('users', 'passport_issued_at'));
    }

    public function test_guest_cannot_create_or_update_student_passport(): void
    {
        Auth::logout();

        $this->postJson('/admin/users', [
            'name'               => 'Гость',
            'lastname'           => 'Паспорт',
            'role_id'            => $this->studentRoleId(),
            'passport'           => 'I-МЮ 123456',
            'passport_issued_at' => '2020-01-15',
        ])->assertUnauthorized();

        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'passport'           => 'старый',
            'passport_issued_at' => '2019-03-01',
        ]);

        $this->patchJson('/admin/users/' . $student->id, [
            'name'               => $student->name,
            'lastname'           => $student->lastname,
            'passport'           => 'новый',
            'passport_issued_at' => '2021-02-02',
        ])->assertUnauthorized();

        $fresh = $student->fresh();
        $this->assertSame('старый', $fresh->passport);
        $this->assertSame('2019-03-01', $fresh->passport_issued_at?->format('Y-m-d'));
    }

    public function test_manager_without_users_view_gets_403_on_passport_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->postJson('/admin/users', [
                'name'               => 'Нет',
                'lastname'           => 'Прав',
                'role_id'            => $this->studentRoleId(),
                'passport'           => 'XX 000000',
                'passport_issued_at' => '2020-01-01',
            ])
            ->assertForbidden();

        $student = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'passport'           => 'сохранить',
            'passport_issued_at' => '2018-06-10',
        ]);

        $this->actingAs($actor)->withSession($session)
            ->patchJson('/admin/users/' . $student->id, [
                'name'               => $student->name,
                'lastname'           => $student->lastname,
                'passport'           => 'изменён',
                'passport_issued_at' => '2022-01-01',
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->getJson('/admin/users/' . $student->id . '/edit')
            ->assertForbidden();

        $fresh = $student->fresh();
        $this->assertSame('сохранить', $fresh->passport);
        $this->assertSame('2018-06-10', $fresh->passport_issued_at?->format('Y-m-d'));
    }

    public function test_ajax_store_persists_passport_fields_and_returns_json_contract(): void
    {
        $response = $this->postJson('/admin/users', [
            'name'               => 'Иван',
            'lastname'           => 'Паспортный',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'passport'           => 'III-МЮ 654321',
            'passport_issued_at' => '2015-08-20',
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user' => ['id', 'name']])
            ->assertJsonPath('user.name', 'Иван');

        $user = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Паспортный')
            ->first();

        $this->assertNotNull($user);
        $this->assertSame('III-МЮ 654321', $user->passport);
        $this->assertSame('2015-08-20', $user->passport_issued_at?->format('Y-m-d'));
        $this->assertSame($user->id, (int) $response->json('user.id'));
    }

    public function test_ajax_store_allows_passport_without_issue_date_and_date_without_passport(): void
    {
        $onlyNumber = $this->postJson('/admin/users', [
            'name'               => 'Только',
            'lastname'           => 'Номер',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'passport'           => 'AB 111111',
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $onlyNumber->assertOk();
        $userNumber = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Номер')
            ->first();
        $this->assertNotNull($userNumber);
        $this->assertSame('AB 111111', $userNumber->passport);
        $this->assertNull($userNumber->passport_issued_at);

        $onlyDate = $this->postJson('/admin/users', [
            'name'               => 'Только',
            'lastname'           => 'Дата',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'passport_issued_at' => '2010-12-31',
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $onlyDate->assertOk();
        $userDate = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Дата')
            ->first();
        $this->assertNotNull($userDate);
        $this->assertNull($userDate->passport);
        $this->assertSame('2010-12-31', $userDate->passport_issued_at?->format('Y-m-d'));
    }

    public function test_ajax_update_persists_passport_fields_and_returns_message(): void
    {
        $user = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'name'               => 'Иван',
            'lastname'           => 'Паспортный',
            'passport'           => 'старый номер',
            'passport_issued_at' => '2010-01-01',
        ]);

        $this->patchJson('/admin/users/' . $user->id, [
            'name'               => 'Иван',
            'lastname'           => 'Паспортный',
            'passport'           => 'IV-РЯ 000111',
            'passport_issued_at' => '2024-05-09',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Клиент успешно обновлён');

        $fresh = $user->fresh();
        $this->assertSame('IV-РЯ 000111', $fresh->passport);
        $this->assertSame('2024-05-09', $fresh->passport_issued_at?->format('Y-m-d'));
    }

    public function test_ajax_update_clears_passport_fields_when_empty_strings_sent(): void
    {
        $user = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'name'               => 'Иван',
            'lastname'           => 'Очистка',
            'passport'           => 'был номер',
            'passport_issued_at' => '2011-11-11',
        ]);

        $this->patchJson('/admin/users/' . $user->id, [
            'name'               => 'Иван',
            'lastname'           => 'Очистка',
            'passport'           => '   ',
            'passport_issued_at' => '',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertNull($fresh->passport);
        $this->assertNull($fresh->passport_issued_at);
    }

    public function test_ajax_store_validation_returns_422_with_passport_field_errors(): void
    {
        $this->postJson('/admin/users', [
            'name'               => 'Иван',
            'lastname'           => 'ДлинныйПаспорт',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'passport'           => str_repeat('а', 101),
            'passport_issued_at' => Carbon::tomorrow()->toDateString(),
            'send_welcome_email' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['passport', 'passport_issued_at']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'ДлинныйПаспорт',
        ]);
    }

    public function test_ajax_update_rejects_future_passport_issued_at(): void
    {
        $user = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'name'               => 'Иван',
            'lastname'           => 'Будущее',
            'passport_issued_at' => '2020-01-01',
        ]);

        $this->patchJson('/admin/users/' . $user->id, [
            'name'               => 'Иван',
            'lastname'           => 'Будущее',
            'passport_issued_at' => Carbon::tomorrow()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['passport_issued_at'])
            ->assertJsonPath(
                'errors.passport_issued_at.0',
                'Поле «Дата выдачи паспорта/св-ва» не может быть позднее сегодняшнего дня.'
            );

        $this->assertSame('2020-01-01', $user->fresh()->passport_issued_at?->format('Y-m-d'));
    }

    public function test_ajax_store_accepts_today_as_passport_issued_at(): void
    {
        $today = Carbon::today()->toDateString();

        $this->postJson('/admin/users', [
            'name'               => 'Сегодня',
            'lastname'           => 'Выдача',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'passport_issued_at' => $today,
            'send_welcome_email' => 0,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $user = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'Выдача')
            ->first();

        $this->assertNotNull($user);
        $this->assertSame($today, $user->passport_issued_at?->format('Y-m-d'));
    }

    public function test_edit_json_includes_passport_fields_for_modal_fill(): void
    {
        $user = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'passport'           => 'II-ББ 777888',
            'passport_issued_at' => '2016-04-12',
        ]);

        $this->getJson('/admin/users/' . $user->id . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.passport', 'II-ББ 777888')
            ->assertJsonPath('user.passport_issued_at', '2016-04-12');
    }

    public function test_edit_json_returns_null_passport_fields_when_not_set(): void
    {
        $user = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'passport'           => null,
            'passport_issued_at' => null,
        ]);

        $this->getJson('/admin/users/' . $user->id . '/edit', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.passport', null)
            ->assertJsonPath('user.passport_issued_at', null);
    }

    public function test_foreign_partner_student_passport_is_not_accessible(): void
    {
        $foreign = User::factory()->create([
            'partner_id'         => $this->foreignPartner->id,
            'role_id'            => $this->studentRoleId(),
            'passport'           => 'чужой паспорт',
            'passport_issued_at' => '2014-07-07',
        ]);

        $this->getJson('/admin/users/' . $foreign->id . '/edit')->assertNotFound();

        $this->patchJson('/admin/users/' . $foreign->id, [
            'name'               => $foreign->name,
            'lastname'           => $foreign->lastname,
            'passport'           => 'взлом',
            'passport_issued_at' => '2022-02-02',
        ])->assertNotFound();

        $fresh = $foreign->fresh();
        $this->assertSame('чужой паспорт', $fresh->passport);
        $this->assertSame('2014-07-07', $fresh->passport_issued_at?->format('Y-m-d'));
    }

    public function test_store_non_ajax_redirects_and_creates_student_with_passport_fields(): void
    {
        $this->post(route('admin.user.store'), [
            'name'               => 'NonAjax',
            'lastname'           => 'СПаспортом',
            'role_id'            => $this->studentRoleId(),
            'passport'           => 'I-АА 123123',
            'passport_issued_at' => '2013-03-03',
            'is_enabled'         => 1,
            'send_welcome_email' => 0,
        ])->assertRedirect(route('admin.user1'));

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('lastname', 'СПаспортом')
            ->first();

        $this->assertNotNull($student);
        $this->assertSame('I-АА 123123', $student->passport);
        $this->assertSame('2013-03-03', $student->passport_issued_at?->format('Y-m-d'));
    }

    public function test_update_non_ajax_redirects_and_updates_passport_fields(): void
    {
        $user = User::factory()->create([
            'partner_id'         => $this->partner->id,
            'role_id'            => $this->studentRoleId(),
            'name'               => 'NonAjax',
            'lastname'           => 'Патч',
            'passport'           => 'до',
            'passport_issued_at' => '2001-01-01',
        ]);

        $this->patch(route('admin.user.update', $user), [
            'name'               => 'NonAjax',
            'lastname'           => 'Патч',
            'passport'           => 'после non-ajax',
            'passport_issued_at' => '2002-02-02',
        ])->assertRedirect(route('admin.user1'));

        $fresh = $user->fresh();
        $this->assertSame('после non-ajax', $fresh->passport);
        $this->assertSame('2002-02-02', $fresh->passport_issued_at?->format('Y-m-d'));
    }

    public function test_store_non_ajax_validation_failure_redirects_back_with_passport_error_not_empty_200(): void
    {
        $this->from(route('admin.user1'))
            ->post(route('admin.user.store'), [
                'name'               => 'Валидация',
                'lastname'           => 'ПаспортNonAjax',
                'role_id'            => $this->studentRoleId(),
                'passport'           => str_repeat('б', 101),
                'send_welcome_email' => 0,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['passport']);

        $this->assertDatabaseMissing('users', [
            'partner_id' => $this->partner->id,
            'lastname'   => 'ПаспортNonAjax',
        ]);
    }

    public function test_users_page_modals_render_passport_fields_after_address(): void
    {
        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="create-passport"', $html);
        $this->assertStringContainsString('id="create-passport-issued-at"', $html);
        $this->assertStringContainsString('name="passport"', $html);
        $this->assertStringContainsString('name="passport_issued_at"', $html);
        $this->assertStringContainsString('>Паспорт/св-во о рождении<', $html);
        $this->assertStringContainsString('>Дата выдачи паспорта/св-ва<', $html);
        $this->assertStringContainsString('id="edit-passport"', $html);
        $this->assertStringContainsString('id="edit-passport-issued-at"', $html);
        $this->assertStringContainsString('maxlength="100"', $html);
        $this->assertStringContainsString('max="' . now()->toDateString() . '"', $html);

        $createAddressPos = strpos($html, 'id="create-address"');
        $createPassportPos = strpos($html, 'id="create-passport"');
        $createIssuedPos = strpos($html, 'id="create-passport-issued-at"');
        $this->assertNotFalse($createAddressPos);
        $this->assertNotFalse($createPassportPos);
        $this->assertNotFalse($createIssuedPos);
        $this->assertLessThan($createPassportPos, $createAddressPos);
        $this->assertLessThan($createIssuedPos, $createPassportPos);

        $editAddressPos = strpos($html, 'id="edit-address"');
        $editPassportPos = strpos($html, 'id="edit-passport"');
        $editIssuedPos = strpos($html, 'id="edit-passport-issued-at"');
        $this->assertNotFalse($editAddressPos);
        $this->assertNotFalse($editPassportPos);
        $this->assertNotFalse($editIssuedPos);
        $this->assertLessThan($editPassportPos, $editAddressPos);
        $this->assertLessThan($editIssuedPos, $editPassportPos);

        $this->assertStringContainsString('name="parent_passport"', $html);
        $this->assertNotSame(
            $createPassportPos,
            strpos($html, 'id="create-parent-passport"')
        );
    }

    public function test_edit_user_modal_js_fills_passport_on_both_open_paths(): void
    {
        $path = resource_path('views/includes/modal/editUser.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $passportFill = "\$('#edit-user-form #edit-passport').val(response.user.passport || '')";
        $issuedFill = "\$('#edit-user-form #edit-passport-issued-at').val(response.user.passport_issued_at || '')";

        $this->assertStringContainsString($passportFill, $content);
        $this->assertStringContainsString($issuedFill, $content);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($content, $passportFill),
            'Оба JS-пути открытия модалки edit должны заполнять #edit-passport'
        );
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($content, $issuedFill),
            'Оба JS-пути открытия модалки edit должны заполнять #edit-passport-issued-at'
        );
    }

    public function test_create_user_modal_has_empty_passport_fields_by_default(): void
    {
        $path = resource_path('views/includes/modal/createUser.blade.php');
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('id="create-passport"', $content);
        $this->assertStringContainsString('name="passport"', $content);
        $this->assertStringContainsString('id="create-passport-issued-at"', $content);
        $this->assertStringContainsString('name="passport_issued_at"', $content);
        $this->assertStringContainsString('>Паспорт/св-во о рождении<', $content);
        $this->assertStringContainsString('>Дата выдачи паспорта/св-ва<', $content);
        $this->assertStringContainsString("old('passport')", $content);
        $this->assertStringContainsString("old('passport_issued_at')", $content);
        $this->assertStringNotContainsString(
            'parent_passport',
            substr($content, (int) strpos($content, 'id="create-passport"'), 400)
        );
    }

    public function test_school_leads_edit_modal_does_not_include_student_passport_fields(): void
    {
        $path = resource_path('views/admin/school-leads/partials/edit-lead-modal.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringNotContainsString('Паспорт/св-во о рождении', $content);
        $this->assertStringNotContainsString('Дата выдачи паспорта/св-ва', $content);
        $this->assertStringNotContainsString('name="passport"', $content);
        $this->assertStringNotContainsString('name="passport_issued_at"', $content);
        $this->assertStringContainsString('admin.users._parent_form', $content);
        $this->assertStringContainsString('js-parent-passport', $content);
    }
}
