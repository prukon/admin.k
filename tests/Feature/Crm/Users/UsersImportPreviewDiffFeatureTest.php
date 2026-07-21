<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UsersImportTestHelpers;

/**
 * Preview-diff импорта учеников: field-level changes, summary, порядок строк, очистки.
 */
final class UsersImportPreviewDiffFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;
    use UsersImportTestHelpers;

    private PartnerLegalEntity $legalEntity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPermission($this->user, 'users.import');
        $this->requirePhpSpreadsheet();
        $this->legalEntity = $this->createImportLegalEntity();
    }

    public function test_preview_create_row_has_empty_changes_and_zero_update_diff_counts(): void
    {
        $email = 'diff-create-' . uniqid('', true) . '@example.test';

        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => 'Новиков',
                    'Имя ученика' => 'Новый',
                    'Email ученика' => $email,
                    'Группа' => '',
                    'Юр. лицо' => '',
                ]),
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('summary.create_count', 1)
            ->assertJsonPath('summary.update_count', 0)
            ->assertJsonPath('summary.update_with_changes_count', 0)
            ->assertJsonPath('summary.update_unchanged_count', 0)
            ->assertJsonPath('summary.update_with_clears_count', 0);

        $row = $response->json('preview.0');
        $this->assertSame('create', $row['mode'] ?? null);
        $this->assertSame([], $row['changes'] ?? null);
        $this->assertFalse((bool) ($row['has_clears'] ?? true));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_preview_update_diff_covers_scalar_fields_birthday_and_enabled(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Бывший',
            'name' => 'Старый',
            'email' => 'diff-scalars@example.test',
            'phone' => '+79001110001',
            'birthday' => '2010-01-15',
            'is_enabled' => true,
        ]);

        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => 'Новый',
                    'Имя ученика' => 'Имя',
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Email ученика' => $student->email,
                    'Телефон ученика' => '+79002220002',
                    'Дата рождения' => '16.02.2011',
                    'Активен' => 'нет',
                ]),
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('summary.update_count', 1)
            ->assertJsonPath('summary.update_with_changes_count', 1)
            ->assertJsonPath('summary.update_unchanged_count', 0)
            ->assertJsonPath('summary.update_with_clears_count', 0);

        $changes = collect($response->json('preview.0.changes') ?? [])->keyBy('field');

        foreach (['student_lastname', 'student_name', 'student_phone', 'birthday', 'is_enabled'] as $field) {
            $this->assertTrue($changes->has($field), "Ожидалось изменение поля {$field}");
            $this->assertSame('changed', $changes->get($field)['kind']);
            $this->assertArrayHasKey('label', $changes->get($field));
            $this->assertArrayHasKey('from', $changes->get($field));
            $this->assertArrayHasKey('to', $changes->get($field));
        }

        $this->assertSame('Бывший', $changes->get('student_lastname')['from']);
        $this->assertSame('Новый', $changes->get('student_lastname')['to']);
        $this->assertSame('15.01.2010', $changes->get('birthday')['from']);
        $this->assertSame('16.02.2011', $changes->get('birthday')['to']);
        $this->assertSame('Да', $changes->get('is_enabled')['from']);
        $this->assertSame('Нет', $changes->get('is_enabled')['to']);
        $this->assertFalse((bool) $response->json('preview.0.has_clears'));
    }

    public function test_preview_update_diff_marks_phone_birthday_and_teams_as_cleared(): void
    {
        $team = Team::factory()->for($this->partner)->create([
            'title' => 'Группа очистки',
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Очистка',
            'name' => 'Полей',
            'email' => 'diff-clears@example.test',
            'phone' => '+79003330003',
            'birthday' => '2012-03-20',
            'is_enabled' => true,
        ]);

        app(TeamUserSyncService::class)->syncTeamsForStudent($student, [$team->id]);

        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => $student->lastname,
                    'Имя ученика' => $student->name,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Email ученика' => $student->email,
                    'Телефон ученика' => '',
                    'Дата рождения' => '',
                    'Активен' => 'да',
                ]),
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('summary.update_with_changes_count', 1)
            ->assertJsonPath('summary.update_with_clears_count', 1)
            ->assertJsonPath('preview.0.has_clears', true);

        $changes = collect($response->json('preview.0.changes') ?? [])->keyBy('field');

        $this->assertSame('cleared', $changes->get('student_phone')['kind']);
        $this->assertSame('+79003330003', $changes->get('student_phone')['from']);
        $this->assertSame('—', $changes->get('student_phone')['to']);

        $this->assertSame('cleared', $changes->get('birthday')['kind']);
        $this->assertSame('20.03.2012', $changes->get('birthday')['from']);
        $this->assertSame('—', $changes->get('birthday')['to']);

        $this->assertSame('cleared', $changes->get('teams')['kind']);
        $this->assertSame('Группа очистки', $changes->get('teams')['from']);
        $this->assertSame('—', $changes->get('teams')['to']);
    }

    public function test_preview_update_diff_parent_link_to_existing_and_unchanged_when_same(): void
    {
        $parent = ParentProfile::query()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Связанный',
            'firstname' => 'Родитель',
            'middlename' => null,
            'email' => 'diff-parent-link@example.test',
            // Импорт нормализует телефон родителя в цифры (без +).
            'phone' => '79004440004',
        ]);

        $studentWithoutParent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Без',
            'name' => 'Родителя',
            'email' => 'diff-student-no-parent@example.test',
            'phone' => null,
            'birthday' => null,
            'parent_id' => null,
            'is_enabled' => true,
        ]);

        $studentWithSameParent = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Уже',
            'name' => 'СРодителем',
            'email' => 'diff-student-same-parent@example.test',
            'phone' => null,
            'birthday' => null,
            'parent_id' => $parent->id,
            'is_enabled' => true,
        ]);

        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => $studentWithoutParent->lastname,
                    'Имя ученика' => $studentWithoutParent->name,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Email ученика' => $studentWithoutParent->email,
                    'Активен' => 'да',
                    'Email родителя' => $parent->email,
                    'Фамилия родителя' => $parent->lastname,
                    'Имя родителя' => $parent->firstname,
                    'Телефон родителя' => $parent->phone,
                ]),
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => $studentWithSameParent->lastname,
                    'Имя ученика' => $studentWithSameParent->name,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Email ученика' => $studentWithSameParent->email,
                    'Активен' => 'да',
                    'Email родителя' => $parent->email,
                    'Фамилия родителя' => $parent->lastname,
                    'Имя родителя' => $parent->firstname,
                    'Телефон родителя' => $parent->phone,
                ]),
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('summary.update_count', 2)
            ->assertJsonPath('summary.update_with_changes_count', 1)
            ->assertJsonPath('summary.update_unchanged_count', 1);

        $byEmail = collect($response->json('preview') ?? [])->keyBy('email');

        $linkRow = $byEmail->get('diff-student-no-parent@example.test');
        $this->assertNotNull($linkRow);
        $parentChange = collect($linkRow['changes'])->firstWhere('field', 'parent');
        $this->assertNotNull($parentChange);
        $this->assertSame('changed', $parentChange['kind']);
        $this->assertSame('—', $parentChange['from']);
        $this->assertStringContainsString('Связанный', (string) $parentChange['to']);

        $sameRow = $byEmail->get('diff-student-same-parent@example.test');
        $this->assertNotNull($sameRow);
        $this->assertSame([], $sameRow['changes']);
        $this->assertFalse((bool) $sameRow['has_clears']);
    }

    public function test_preview_orders_rows_with_changes_before_create_and_unchanged_updates(): void
    {
        $unchanged = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'БезИзм',
            'name' => 'Ученик',
            'email' => 'diff-order-unchanged@example.test',
            'phone' => null,
            'birthday' => null,
            'is_enabled' => true,
        ]);

        $changed = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Старое',
            'name' => 'Имя',
            'email' => 'diff-order-changed@example.test',
            'phone' => null,
            'birthday' => null,
            'is_enabled' => true,
        ]);

        // Excel: create → unchanged update → changed update.
        // Preview: changed → create → unchanged.
        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => 'Создание',
                    'Имя ученика' => 'Первым',
                    'Email ученика' => 'diff-order-create@example.test',
                    'Группа' => '',
                    'Юр. лицо' => '',
                ]),
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => $unchanged->lastname,
                    'Имя ученика' => $unchanged->name,
                    'Email ученика' => $unchanged->email,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Активен' => 'да',
                ]),
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => 'Новое',
                    'Имя ученика' => $changed->name,
                    'Email ученика' => $changed->email,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Активен' => 'да',
                ]),
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('summary.create_count', 1)
            ->assertJsonPath('summary.update_count', 2)
            ->assertJsonPath('summary.update_with_changes_count', 1)
            ->assertJsonPath('summary.update_unchanged_count', 1);

        $emails = collect($response->json('preview') ?? [])->pluck('email')->all();
        $this->assertSame([
            'diff-order-changed@example.test',
            'diff-order-create@example.test',
            'diff-order-unchanged@example.test',
        ], $emails);
    }

    public function test_preview_mixed_summary_counts_create_update_changed_unchanged_and_clears(): void
    {
        $unchanged = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Сводка',
            'name' => 'БезИзм',
            'email' => 'diff-summary-unchanged@example.test',
            'phone' => null,
            'birthday' => null,
            'is_enabled' => true,
        ]);

        $changed = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Сводка',
            'name' => 'СИзм',
            'email' => 'diff-summary-changed@example.test',
            'phone' => '+79005550005',
            'birthday' => null,
            'is_enabled' => true,
        ]);

        $response = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => 'Сводка',
                    'Имя ученика' => 'Создать',
                    'Email ученика' => 'diff-summary-create@example.test',
                    'Группа' => '',
                    'Юр. лицо' => '',
                ]),
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => $unchanged->lastname,
                    'Имя ученика' => $unchanged->name,
                    'Email ученика' => $unchanged->email,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Активен' => 'да',
                ]),
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => $changed->lastname,
                    'Имя ученика' => $changed->name,
                    'Email ученика' => $changed->email,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Телефон ученика' => '',
                    'Активен' => 'да',
                ]),
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('summary.total_rows', 3)
            ->assertJsonPath('summary.create_count', 1)
            ->assertJsonPath('summary.update_count', 2)
            ->assertJsonPath('summary.update_with_changes_count', 1)
            ->assertJsonPath('summary.update_unchanged_count', 1)
            ->assertJsonPath('summary.update_with_clears_count', 1);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_users_page_with_import_permission_contains_preview_diff_ui_hooks(): void
    {
        $html = $this->get(route('admin.user1'))->assertOk()->getContent();

        $this->assertStringContainsString('usersImportModal', $html);
        $this->assertStringContainsString('update_with_changes_count', $html);
        $this->assertStringContainsString('update_unchanged_count', $html);
        $this->assertStringContainsString('buildChangesTableHtml', $html);
        $this->assertStringContainsString('users-import-preview-row', $html);
        $this->assertStringContainsString('is-expandable', $html);
        $this->assertStringContainsString('<th>Поле</th><th>Было</th><th>Станет</th>', $html);
        $this->assertStringContainsString('с изменениями', $html);
        $this->assertStringContainsString('без изменений', $html);
        $this->assertNotSame('', trim($html));
    }

    /**
     * [P2] E2E smoke: страница → preview с diff → commit update → ученик обновлён, таблица видит данные.
     */
    public function test_import_update_workflow_preview_diff_commit_and_datatable_shows_changes(): void
    {
        $this->withoutVite();

        $email = 'diff-workflow-' . uniqid('', true) . '@example.test';
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'lastname' => 'Workflow',
            'name' => 'Old',
            'email' => $email,
            'phone' => '+79006660006',
            'is_enabled' => true,
        ]);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('usersImportModal', false)
            ->assertSee('buildChangesTableHtml', false);

        $preview = $this->postJson(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => 'Workflow',
                    'Имя ученика' => 'New',
                    'Email ученика' => $email,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Телефон ученика' => '',
                    'Активен' => 'да',
                ]),
            ]),
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('summary.update_with_changes_count', 1)
            ->assertJsonPath('preview.0.has_clears', true)
            ->json();

        $changes = collect($preview['preview'][0]['changes'] ?? [])->keyBy('field');
        $this->assertSame('changed', $changes->get('student_name')['kind'] ?? null);
        $this->assertSame('cleared', $changes->get('student_phone')['kind'] ?? null);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $student->refresh();
        $this->assertSame('New', $student->name);
        $this->assertNull($student->phone);

        $data = $this->getJson(
            '/admin/users/data?draw=1&start=0&length=500&search[value]=' . urlencode($email),
            $this->importAjaxHeaders()
        )->assertOk()->json();

        $rows = collect($data['data'] ?? []);
        $this->assertTrue(
            $rows->contains(fn (array $row) => str_contains((string) ($row['email'] ?? ''), $email)
                && str_contains((string) ($row['name'] ?? ''), 'New')),
            'Обновлённый ученик должен быть виден в DataTables без перезагрузки страницы.'
        );
    }
}
