<?php

namespace Tests\Feature\Crm\Users;

use App\Enums\AuditEvent;
use App\Enums\UserSex;
use App\Models\MyLog;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Пол и комментарий ученика: users.sex, users.comment.
 *
 * @see /docs/documentation/admin-users.html
 */
final class UserCommentAndSexFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();
        $this->grantUsersSectionPermissions($this->user);
    }

    // --- Gates и права ---

    public function test_gates_allow_users_sex_and_users_comment_with_permissions(): void
    {
        $this->assertTrue(\Gate::forUser($this->user)->allows('users.sex'));
        $this->assertTrue(\Gate::forUser($this->user)->allows('users.comment'));
    }

    public function test_gates_deny_users_sex_and_users_comment_without_permissions(): void
    {
        $this->revokePermission($this->user, 'users.sex');
        $this->revokePermission($this->user, 'users.comment');

        $this->assertFalse(\Gate::forUser($this->user)->allows('users.sex'));
        $this->assertFalse(\Gate::forUser($this->user)->allows('users.comment'));
    }

    public function test_legacy_account_user_sex_update_permission_is_not_registered(): void
    {
        $this->assertNull(
            DB::table('permissions')->where('name', 'account.user.sex.update')->value('id')
        );
    }

    public function test_student_role_base_permissions_include_users_sex_for_account(): void
    {
        $names = config('role_base_permissions.roles.user', []);

        $this->assertContains('users.sex', $names);
        $this->assertNotContains('account.user.sex.update', $names);
    }

    public function test_users_sex_permission_covers_both_crm_and_account_for_student(): void
    {
        $studentRoleId = $this->studentRoleId();
        $this->revokePermissionForRole($studentRoleId, 'users.sex');
        $this->grantPermissionForRole($studentRoleId, 'users.sex');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $studentRoleId,
            'sex'        => null,
        ]);

        $this->actingAs($student);

        $this->assertTrue($student->can('users.sex'));

        $html = (string) $this->get(route('account.user.edit'))->assertOk()->getContent();
        $this->assertStringContainsString('name="sex"', $html);

        $this->patchJson(route('account.user.update'), [
            'name'     => $student->name,
            'lastname' => $student->lastname,
            'sex'      => UserSex::Male->value,
        ])->assertOk();

        $this->assertSame(UserSex::Male->value, $student->fresh()->sex);
    }

    // --- JSON edit / store / update ---

    public function test_edit_json_includes_comment_and_sex_for_student(): void
    {
        $student = $this->createStudent([
            'sex'     => UserSex::Female->value,
            'comment' => 'Тестовый комментарий',
        ]);

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('user.sex', UserSex::Female->value)
            ->assertJsonPath('user.comment', 'Тестовый комментарий')
            ->assertJsonPath('ui.canViewUserSex', true)
            ->assertJsonPath('ui.canViewUserComment', true);
    }

    public function test_edit_json_omits_sex_and_comment_without_permissions(): void
    {
        $this->revokePermission($this->user, 'users.sex');
        $this->revokePermission($this->user, 'users.comment');

        $student = $this->createStudent([
            'sex'     => UserSex::Male->value,
            'comment' => 'Скрыто',
        ]);

        $response = $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $payload = $response->json();
        $this->assertArrayNotHasKey('sex', $payload['user']);
        $this->assertArrayNotHasKey('comment', $payload['user']);
        $this->assertFalse($payload['ui']['canViewUserSex']);
        $this->assertFalse($payload['ui']['canViewUserComment']);
    }

    public function test_edit_json_omits_only_sex_without_users_sex_permission(): void
    {
        $this->revokePermission($this->user, 'users.sex');

        $student = $this->createStudent([
            'sex'     => UserSex::Female->value,
            'comment' => 'Видимый комментарий',
        ]);

        $response = $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $payload = $response->json();
        $this->assertArrayNotHasKey('sex', $payload['user']);
        $this->assertSame('Видимый комментарий', $payload['user']['comment']);
        $this->assertFalse($payload['ui']['canViewUserSex']);
        $this->assertTrue($payload['ui']['canViewUserComment']);
    }

    public function test_table_settings_get_omits_sex_without_permission(): void
    {
        $this->revokePermission($this->user, 'users.sex');

        UserTableSetting::query()->updateOrCreate(
            [
                'user_id'   => $this->user->id,
                'table_key' => 'users_index',
            ],
            [
                'columns' => [
                    'name'    => true,
                    'sex'     => true,
                    'comment' => true,
                ],
            ]
        );

        $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertJsonMissingPath('sex')
            ->assertJsonPath('comment', true);
    }

    public function test_table_settings_save_ignores_sex_without_permission(): void
    {
        $this->revokePermission($this->user, 'users.sex');

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'name'    => true,
                'sex'     => true,
                'comment' => false,
            ],
        ])->assertOk();

        $stored = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->value('columns');

        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('sex', $stored);
        $this->assertFalse($stored['comment']);
    }

    public function test_table_settings_get_omits_comment_without_permission(): void
    {
        $this->revokePermission($this->user, 'users.comment');

        UserTableSetting::query()->updateOrCreate(
            [
                'user_id'   => $this->user->id,
                'table_key' => 'users_index',
            ],
            [
                'columns' => [
                    'name'    => true,
                    'sex'     => true,
                    'comment' => true,
                ],
            ]
        );

        $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertJsonPath('sex', true)
            ->assertJsonMissingPath('comment');
    }

    public function test_table_settings_save_ignores_comment_without_permission(): void
    {
        $this->revokePermission($this->user, 'users.comment');

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'name'    => true,
                'sex'     => true,
                'comment' => true,
            ],
        ])->assertOk();

        $stored = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->value('columns');

        $this->assertIsArray($stored);
        $this->assertTrue($stored['sex']);
        $this->assertArrayNotHasKey('comment', $stored);
    }

    public function test_store_persists_comment_and_sex_for_student(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name'       => 'Пётр',
            'lastname'   => 'Петров',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => '1',
            'sex'        => UserSex::Male->value,
            'comment'    => 'Новый ученик',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Пётр')
            ->firstOrFail();

        $this->assertSame(UserSex::Male->value, $student->sex);
        $this->assertSame('Новый ученик', $student->comment);
    }

    public function test_update_persists_comment_and_sex_for_student(): void
    {
        $student = $this->createStudent();

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'sex'     => UserSex::Female->value,
            'comment' => 'Обновлённый комментарий',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();

        $this->assertSame(UserSex::Female->value, $student->sex);
        $this->assertSame('Обновлённый комментарий', $student->comment);
    }

    public function test_update_can_clear_sex_and_comment(): void
    {
        $student = $this->createStudent([
            'sex'     => UserSex::Male->value,
            'comment' => 'Было',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'sex'     => '',
            'comment' => '',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();

        $this->assertNull($student->sex);
        $this->assertNull($student->comment);
    }

    public function test_update_ignores_fields_without_permissions(): void
    {
        $this->revokePermission($this->user, 'users.sex');
        $this->revokePermission($this->user, 'users.comment');

        $student = $this->createStudent([
            'sex'     => null,
            'comment' => null,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'sex'     => UserSex::Male->value,
            'comment' => 'Не должно сохраниться',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();

        $this->assertNull($student->sex);
        $this->assertNull($student->comment);
    }

    public function test_update_ignores_comment_and_sex_for_trainer_role(): void
    {
        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('trainer'),
            'sex'        => null,
            'comment'    => null,
        ]);

        $this->patchJson(route('admin.user.update', $trainer->id), [
            'name'       => $trainer->name,
            'lastname'   => $trainer->lastname,
            'role_id'    => $trainer->role_id,
            'is_enabled' => 1,
            'sex'        => UserSex::Male->value,
            'comment'    => 'Не для тренера',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $trainer->refresh();

        $this->assertNull($trainer->sex);
        $this->assertNull($trainer->comment);
    }

    public function test_update_strips_fields_when_role_changed_to_trainer(): void
    {
        $student = $this->createStudent([
            'sex'     => UserSex::Female->value,
            'comment' => 'Остаётся в БД',
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'role_id' => $this->roleId('trainer'),
            'sex'     => UserSex::Male->value,
            'comment' => 'Не применится',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();

        $this->assertSame('trainer', $student->role?->name);
        $this->assertSame(UserSex::Female->value, $student->sex);
        $this->assertSame('Остаётся в БД', $student->comment);
    }

    public function test_store_rejects_invalid_sex_value(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name'       => 'Иван',
            'lastname'   => 'Иванов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => '1',
            'sex'        => 'unknown',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sex']);
    }

    public function test_update_rejects_invalid_sex_value(): void
    {
        $student = $this->createStudent();

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'sex' => 'maybe',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sex']);
    }

    public function test_comment_validation_max_length_on_update(): void
    {
        $student = $this->createStudent();

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'comment' => str_repeat('а', 5001),
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_comment_validation_max_length_on_store(): void
    {
        $this->postJson(route('admin.user.store'), [
            'name'       => 'Иван',
            'lastname'   => 'Иванов',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => '1',
            'comment'    => str_repeat('б', 5001),
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);
    }

    // --- Аудит ---

    public function test_update_writes_audit_log_for_comment_and_sex_changes(): void
    {
        $student = $this->createStudent([
            'sex'     => null,
            'comment' => null,
        ]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'sex'     => UserSex::Male->value,
            'comment' => 'Заметка',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $log = MyLog::query()
            ->where('event', AuditEvent::UserUpdated->value)
            ->where('target_id', $student->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Пол:', (string) $log->description);
        $this->assertStringContainsString('Комментарий:', (string) $log->description);
    }

    public function test_update_audit_logs_only_sex_when_comment_permission_revoked(): void
    {
        $this->revokePermission($this->user, 'users.comment');

        $student = $this->createStudent(['sex' => null, 'comment' => null]);

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'sex'     => UserSex::Female->value,
            'comment' => 'Не сохранится',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $student->refresh();
        $this->assertSame(UserSex::Female->value, $student->sex);
        $this->assertNull($student->comment);

        $log = MyLog::query()
            ->where('event', AuditEvent::UserUpdated->value)
            ->where('target_id', $student->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $desc = (string) $log->description;
        $this->assertStringContainsString('Пол:', $desc);
        $this->assertStringNotContainsString('Комментарий:', $desc);
    }

    // --- DataTables JSON ---

    public function test_data_includes_comment_and_sex_when_actor_has_permissions(): void
    {
        $student = $this->createStudent([
            'sex'     => UserSex::Female->value,
            'comment' => 'Строка списка',
        ]);

        $row = $this->fetchUsersDataRowById($student->id);

        $this->assertNotNull($row);
        $this->assertSame(UserSex::Female->label(), $row['sex']);
        $this->assertSame('Строка списка', $row['comment']);
    }

    public function test_data_omits_comment_and_sex_without_permissions(): void
    {
        $this->revokePermission($this->user, 'users.sex');
        $this->revokePermission($this->user, 'users.comment');

        $student = $this->createStudent([
            'sex'     => UserSex::Male->value,
            'comment' => 'Скрыто',
        ]);

        $row = $this->fetchUsersDataRowById($student->id);

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('sex', $row);
        $this->assertArrayNotHasKey('comment', $row);
    }

    public function test_data_includes_only_sex_with_users_sex_permission_only(): void
    {
        $this->revokePermission($this->user, 'users.comment');

        $student = $this->createStudent([
            'sex'     => UserSex::Male->value,
            'comment' => 'Скрытый комментарий',
        ]);

        $row = $this->fetchUsersDataRowById($student->id);

        $this->assertNotNull($row);
        $this->assertSame(UserSex::Male->label(), $row['sex']);
        $this->assertArrayNotHasKey('comment', $row);
    }

    public function test_data_includes_only_comment_with_users_comment_permission_only(): void
    {
        $this->revokePermission($this->user, 'users.sex');

        $student = $this->createStudent([
            'sex'     => UserSex::Female->value,
            'comment' => 'Видимый комментарий',
        ]);

        $row = $this->fetchUsersDataRowById($student->id);

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('sex', $row);
        $this->assertSame('Видимый комментарий', $row['comment']);
    }

    public function test_data_sorts_by_sex_column(): void
    {
        $female = $this->createStudent([
            'lastname' => 'CommentSexSort',
            'name'     => 'Female',
            'sex'      => UserSex::Female->value,
        ]);
        $male = $this->createStudent([
            'lastname' => 'CommentSexSort',
            'name'     => 'Male',
            'sex'      => UserSex::Male->value,
        ]);

        $col = $this->usersListColumnIndex('sex', withContracts: $this->actorHasContractsColumn());
        $ids = collect(
            $this->getJson("/admin/users/data?draw=1&start=0&length=100&name=CommentSexSort&order[0][column]={$col}&order[0][dir]=asc")
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $posFemale = array_search($female->id, $ids, true);
        $posMale = array_search($male->id, $ids, true);

        $this->assertNotFalse($posFemale);
        $this->assertNotFalse($posMale);
        $this->assertLessThan($posMale, $posFemale, 'При asc female (f) должен быть раньше male (m)');
    }

    public function test_data_sorts_by_comment_column(): void
    {
        $first = $this->createStudent([
            'lastname' => 'CommentSort',
            'name'     => 'Aaa',
            'comment'  => 'Alpha comment',
        ]);
        $second = $this->createStudent([
            'lastname' => 'CommentSort',
            'name'     => 'Bbb',
            'comment'  => 'Beta comment',
        ]);

        $col = $this->usersListColumnIndex('comment', withContracts: $this->actorHasContractsColumn());
        $ids = collect(
            $this->getJson("/admin/users/data?draw=1&start=0&length=100&name=CommentSort&order[0][column]={$col}&order[0][dir]=asc")
                ->assertOk()
                ->json('data')
        )->pluck('id')->all();

        $posFirst = array_search($first->id, $ids, true);
        $posSecond = array_search($second->id, $ids, true);

        $this->assertNotFalse($posFirst);
        $this->assertNotFalse($posSecond);
        $this->assertLessThan($posSecond, $posFirst);
    }

    // --- UI страницы /admin/users ---

    public function test_users_page_shows_sex_and_comment_when_permissions_granted(): void
    {
        $html = (string) $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('canViewUserSex', true)
            ->assertViewHas('canViewUserComment', true)
            ->getContent();

        $this->assertStringContainsString('data-column-key="sex"', $html);
        $this->assertStringContainsString('data-column-key="comment"', $html);
        $this->assertStringContainsString('id="create-sex"', $html);
        $this->assertStringContainsString('id="edit-sex"', $html);
        $this->assertStringContainsString('id="create-comment"', $html);
        $this->assertStringContainsString('id="edit-comment"', $html);
        $this->assertStringContainsString('canViewUserSex = true', $html);
        $this->assertStringContainsString('canViewUserComment = true', $html);
    }

    public function test_users_page_hides_fields_without_permissions(): void
    {
        $this->revokePermission($this->user, 'users.sex');
        $this->revokePermission($this->user, 'users.comment');

        $html = (string) $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('canViewUserSex', false)
            ->assertViewHas('canViewUserComment', false)
            ->getContent();

        $this->assertStringNotContainsString('data-column-key="sex"', $html);
        $this->assertStringNotContainsString('data-column-key="comment"', $html);
        $this->assertStringNotContainsString('id="create-sex"', $html);
        $this->assertStringNotContainsString('id="edit-sex"', $html);
        $this->assertStringNotContainsString('id="create-comment"', $html);
        $this->assertStringNotContainsString('id="edit-comment"', $html);
    }

    public function test_users_page_shows_only_sex_block_with_users_sex_permission_only(): void
    {
        $this->revokePermission($this->user, 'users.comment');

        $html = (string) $this->get(route('admin.user1'))->assertOk()->getContent();

        $this->assertStringContainsString('id="create-sex"', $html);
        $this->assertStringNotContainsString('id="create-comment"', $html);
        $this->assertStringContainsString('data-column-key="sex"', $html);
        $this->assertStringNotContainsString('data-column-key="comment"', $html);
    }

    public function test_table_settings_accepts_sex_and_comment_column_keys(): void
    {
        $this->getJson(route('admin.users.table-settings.get'))->assertOk();

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'name'    => true,
                'sex'     => true,
                'comment' => false,
                'email'   => true,
            ],
        ])->assertOk();
    }

    // --- Личный кабинет: пол через users.sex (то же право, что в CRM) ---

    public function test_account_edit_page_shows_sex_with_permission(): void
    {
        $studentRoleId = $this->studentRoleId();
        $this->user->role_id = $studentRoleId;
        $this->user->save();
        $this->grantPermissionForRole($studentRoleId, 'users.sex');
        $this->actingAs($this->user);

        $html = (string) $this->get(route('account.user.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('id="sex"', $html);
        $this->assertStringContainsString('name="sex"', $html);
        $this->assertStringContainsString('>Пол</label>', $html);
        $this->assertStringNotContainsString('name="comment"', $html);
    }

    public function test_account_edit_page_hides_sex_without_permission(): void
    {
        $studentRoleId = $this->studentRoleId();
        $this->user->role_id = $studentRoleId;
        $this->user->save();
        $this->revokePermissionForRole($studentRoleId, 'users.sex');
        $this->actingAs($this->user);

        $html = (string) $this->get(route('account.user.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="sex"', $html);
        $this->assertStringNotContainsString('name="sex"', $html);
    }

    public function test_account_update_persists_sex_with_permission(): void
    {
        $studentRoleId = $this->studentRoleId();
        $this->user->role_id = $studentRoleId;
        $this->user->save();
        $this->grantPermissionForRole($studentRoleId, 'users.sex');
        $this->actingAs($this->user);

        $this->patchJson(route('account.user.update'), [
            'name'     => $this->user->name,
            'lastname' => $this->user->lastname,
            'sex'      => UserSex::Female->value,
        ])->assertOk();

        $this->user->refresh();
        $this->assertSame(UserSex::Female->value, $this->user->sex);
    }

    public function test_account_update_ignores_sex_without_permission(): void
    {
        $studentRoleId = $this->studentRoleId();
        $this->user->role_id = $studentRoleId;
        $this->user->sex = null;
        $this->user->save();
        $this->revokePermissionForRole($studentRoleId, 'users.sex');
        $this->actingAs($this->user);

        $this->patchJson(route('account.user.update'), [
            'name'     => $this->user->name,
            'lastname' => $this->user->lastname,
            'sex'      => UserSex::Male->value,
        ])->assertOk();

        $this->user->refresh();
        $this->assertNull($this->user->sex);
    }

    public function test_account_update_rejects_invalid_sex(): void
    {
        $studentRoleId = $this->studentRoleId();
        $this->user->role_id = $studentRoleId;
        $this->user->save();
        $this->grantPermissionForRole($studentRoleId, 'users.sex');
        $this->actingAs($this->user);

        $this->patchJson(route('account.user.update'), [
            'name'     => $this->user->name,
            'lastname' => $this->user->lastname,
            'sex'      => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sex']);
    }

    public function test_account_update_writes_audit_log_for_sex_change(): void
    {
        $studentRoleId = $this->studentRoleId();
        $this->user->role_id = $studentRoleId;
        $this->user->sex = null;
        $this->user->save();
        $this->grantPermissionForRole($studentRoleId, 'users.sex');
        $this->actingAs($this->user);

        $this->patchJson(route('account.user.update'), [
            'name'     => $this->user->name,
            'lastname' => $this->user->lastname,
            'sex'      => UserSex::Male->value,
        ])->assertOk();

        $log = MyLog::query()
            ->where('event', AuditEvent::UserAccountUpdated->value)
            ->where('target_id', $this->user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Пол:', (string) $log->description);
    }

    // --- Доступ к разделу: гость / 403 / полный smoke 200 ---

    public function test_guest_cannot_access_users_section_endpoints(): void
    {
        Auth::logout();

        $student = $this->createStudent();

        $this->get(route('admin.user1'))->assertStatus(302);

        $this->getJson('/admin/users/data?draw=1&start=0&length=10', [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertUnauthorized();

        $this->getJson(route('admin.user.edit', $student->id), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertUnauthorized();

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'sex'     => UserSex::Male->value,
            'comment' => 'x',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnauthorized();

        $this->postJson(route('admin.user.store'), [
            'name'       => 'G',
            'lastname'   => 'Guest',
            'role_id'    => $this->studentRoleId(),
            'is_enabled' => 1,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertUnauthorized();
    }

    public function test_user_without_users_view_gets_403_on_section_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->grantPermission($denied, 'users.sex');
        $this->grantPermission($denied, 'users.comment');
        $this->actingAs($denied);

        $student = $this->createStudent();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $this->get(route('admin.user1'))->assertForbidden();

        $this->getJson('/admin/users/data?draw=1&start=0&length=10')->assertForbidden();
        $this->getJson('/admin/users/data?order[0][column]=7&order[0][dir]=asc')->assertForbidden();

        $this->getJson(route('admin.users.table-settings.get'))->assertForbidden();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['sex' => true, 'comment' => true],
        ])->assertForbidden();

        $this->getJson(route('admin.users.parents.search', ['q' => 'test']))->assertForbidden();
        $this->getJson(route('logs.data.user', ['draw' => 1]))->assertForbidden();

        $this->getJson(route('admin.user.edit', $student->id))->assertForbidden();

        $this->patchJson(route('admin.user.update', $student->id), $this->studentPatchPayload($student, [
            'sex' => UserSex::Male->value,
        ]))->assertForbidden();

        $this->postJson(route('admin.user.store'), [
            'name'       => 'Нет',
            'lastname'   => 'Права',
            'role_id'    => $this->studentRoleId(),
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'sex'        => UserSex::Male->value,
            'comment'    => 'test',
        ])->assertForbidden();
    }

    public function test_users_section_endpoints_return_ok_with_users_view_and_comment_sex_permissions(): void
    {
        $actor = $this->actingAsCommentSexViewer();
        $student = $this->createStudent([
            'sex'     => UserSex::Female->value,
            'comment' => 'Smoke row',
        ]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $roleId = $this->studentRoleId();

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewIs('admin.user')
            ->assertViewHas('canViewUserSex', true)
            ->assertViewHas('canViewUserComment', true);

        $dataResponse = $this->getJson('/admin/users/data?draw=1&start=0&length=50&id=' . $student->id);
        $dataResponse->assertOk()->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data',
        ]);

        $row = collect($dataResponse->json('data'))->firstWhere('id', $student->id);
        $this->assertNotNull($row);
        $this->assertSame(UserSex::Female->label(), $row['sex']);
        $this->assertSame('Smoke row', $row['comment']);

        foreach ($this->commentSexDataUrls($team->id) as $url) {
            $this->getJson($url)->assertOk();
        }

        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'name'    => true,
                'sex'     => true,
                'comment' => true,
                'email'   => false,
            ],
        ])->assertOk();

        $this->getJson(route('logs.data.user', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->assertOk();

        $this->getJson(route('admin.users.parents.search', ['q' => 'Comment']))->assertOk();

        $store = $this->postJson(route('admin.user.store'), [
            'name'       => 'Smoke',
            'lastname'   => 'CommentSex',
            'email'      => 'comment-sex-smoke-' . uniqid('', true) . '@example.test',
            'role_id'    => $roleId,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'sex'        => UserSex::Male->value,
            'comment'    => 'Создан через smoke',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonStructure(['user' => ['id']]);

        $userId = (int) $store->json('user.id');
        $this->assertGreaterThan(0, $userId);

        $this->getJson(route('admin.user.edit', $userId), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['sex', 'comment'],
            ]);

        $this->patchJson(route('admin.user.update', $userId), [
            'name'     => 'Smoke',
            'lastname' => 'Updated',
            'role_id'  => $roleId,
            'sex'      => UserSex::Female->value,
            'comment'  => 'Обновлено smoke',
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $updated = User::query()->findOrFail($userId);
        $this->assertSame(UserSex::Female->value, $updated->sex);
        $this->assertSame('Обновлено smoke', $updated->comment);

        unset($actor);
    }

    public function test_users_section_endpoints_return_ok_with_users_view_only_without_comment_sex_fields_in_data(): void
    {
        $actor = $this->createUserWithoutPermission('users.sex', $this->partner);
        $this->revokePermission($actor, 'users.comment');
        $this->grantPermission($actor, 'users.view');
        $this->actingAs($actor);

        $student = $this->createStudent([
            'sex'     => UserSex::Male->value,
            'comment' => 'Hidden',
        ]);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('canViewUserSex', false)
            ->assertViewHas('canViewUserComment', false);

        $row = collect(
            $this->getJson('/admin/users/data?draw=1&start=0&length=50&id=' . $student->id)
                ->assertOk()
                ->json('data')
        )->firstWhere('id', $student->id);

        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('sex', $row);
        $this->assertArrayNotHasKey('comment', $row);

        $this->getJson('/admin/users/data?draw=1&start=0&length=10&status=active')->assertOk();
        $this->getJson(route('admin.users.table-settings.get'))->assertOk();
        $this->getJson(route('admin.users.parents.search', ['q' => 'test']))->assertOk();

        unset($actor);
    }

    public function test_account_section_endpoints_return_ok_with_account_user_view_and_users_sex(): void
    {
        $studentRoleId = $this->studentRoleId();
        $actor = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $studentRoleId,
            'sex'        => null,
        ]);
        $this->grantPermissionForRole($studentRoleId, 'account.user.view');
        $this->grantPermissionForRole($studentRoleId, 'users.sex');
        $this->actingAs($actor);

        $this->get(route('account.user.edit'))->assertOk();

        $this->patchJson(route('account.user.update'), [
            'name'     => $actor->name,
            'lastname' => $actor->lastname,
            'sex'      => UserSex::Female->value,
        ])->assertOk();

        $actor->refresh();
        $this->assertSame(UserSex::Female->value, $actor->sex);
    }

    // --- Helpers ---

    private function actorHasContractsColumn(): bool
    {
        $actor = Auth::user();

        return $actor !== null && $actor->can('contracts.view');
    }

    private function actingAsCommentSexViewer(): User
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->grantPermission($actor, 'users.view');
        $this->grantPermission($actor, 'users.sex');
        $this->grantPermission($actor, 'users.comment');
        $this->grantPermission($actor, 'users.name.update');
        $this->grantPermission($actor, 'users.activity.update');
        $this->grantPermission($actor, 'users.role.update');
        $this->actingAs($actor);

        return $actor;
    }

    /**
     * @return list<string>
     */
    private function commentSexDataUrls(int $teamId): array
    {
        $sexCol = $this->usersListColumnIndex('sex', withContracts: false);
        $commentCol = $this->usersListColumnIndex('comment', withContracts: false);

        return [
            '/admin/users/data?draw=1&start=0&length=10',
            '/admin/users/data?draw=1&start=0&length=10&status=active',
            '/admin/users/data?draw=1&start=0&length=10&team_id=' . $teamId,
            '/admin/users/data?draw=1&start=0&length=10&name=Smoke',
            "/admin/users/data?draw=1&start=0&length=10&order[0][column]={$sexCol}&order[0][dir]=asc",
            "/admin/users/data?draw=1&start=0&length=10&order[0][column]={$commentCol}&order[0][dir]=desc",
        ];
    }

    private function usersListColumnIndex(
        string $key,
        bool $withContracts = false,
        bool $canViewSex = true,
        bool $canViewComment = true,
    ): int {
        $keys = ['rownum', 'avatar', 'name', 'parent'];

        if ($withContracts) {
            $keys[] = 'contract';
        }

        $keys[] = 'teams';
        $keys[] = 'birthday';

        if ($canViewSex) {
            $keys[] = 'sex';
        }

        if ($canViewComment) {
            $keys[] = 'comment';
        }

        $keys = array_merge($keys, ['email', 'phone', 'status_label', 'actions']);

        $index = array_search($key, $keys, true);

        $this->assertNotFalse($index, "Column key '{$key}' not found in users list columns map");

        return (int) $index;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUsersDataRowById(int $userId): ?array
    {
        $response = $this->getJson('/admin/users/data?draw=1&start=0&length=100&id=' . $userId);
        $response->assertOk();

        return collect($response->json('data'))->firstWhere('id', $userId);
    }

    private function grantUsersSectionPermissions(User $user): void
    {
        foreach ([
            'users.view',
            'users.name.update',
            'users.activity.update',
            'users.role.update',
            'users.sex',
            'users.comment',
        ] as $permission) {
            $this->grantPermission($user, $permission);
        }
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

    private function revokePermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $actor->role_id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }

    private function grantPermissionForRole(int $roleId, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $roleId,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function revokePermissionForRole(int $roleId, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $roleId)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function createStudent(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
        ], $attrs));
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function studentPatchPayload(User $student, array $extra = []): array
    {
        return array_merge([
            'name'       => $student->name,
            'lastname'   => $student->lastname,
            'role_id'    => $student->role_id,
            'is_enabled' => $student->is_enabled ? '1' : '0',
        ], $extra);
    }
}
