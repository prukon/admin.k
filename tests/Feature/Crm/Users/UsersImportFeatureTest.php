<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\ParentProfile;
use App\Models\PartnerLegalEntity;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UsersImportTestHelpers;

/**
 * Бизнес-логика импорта учеников из Excel + E2E smoke (HTTP-цепочка без браузера).
 */
final class UsersImportFeatureTest extends CrmTestCase
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

    public function test_import_endpoints_require_users_import_permission(): void
    {
        $actor = $this->createUserWithoutPermission('users.import');
        $this->grantUsersView($actor);
        $this->actingAs($actor);

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity),
        ]);

        $this->post(route('admin.users.import.preview'), ['file' => $file])
            ->assertForbidden();

        $this->get(route('admin.users.import.template'))
            ->assertForbidden();

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => '00000000-0000-4000-8000-000000000000',
        ])->assertForbidden();
    }

    public function test_preview_rejects_missing_required_column(): void
    {
        $file = $this->makeImportFileWithHeaders(['Имя ученика']);

        $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonFragment(['field' => 'Фамилия ученика']);
    }

    public function test_import_creates_student_without_group(): void
    {
        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Безгруппов',
                'Имя ученика' => 'Ученик',
                'Группа' => '',
                'Юр. лицо' => '',
                'Email ученика' => 'no-team@example.test',
            ]),
        ]);

        $preview = $this->previewImportFile($file);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('created', 1);

        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');
        $student = User::query()
            ->where('email', 'no-team@example.test')
            ->where('role_id', $studentRoleId)
            ->first();

        $this->assertNotNull($student);
        $this->assertSame([], app(\App\Services\TeamUserSyncService::class)->teamIdsForStudent($student));
    }

    public function test_successful_import_creates_student_team_and_parent(): void
    {
        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Петров',
                'Имя ученика' => 'Пётр',
                'Группа' => 'Новая группа импорта',
                'Юр. лицо' => $this->legalEntity->displayTitle(),
                'Email ученика' => 'import-student@example.test',
                'Email родителя' => 'import-parent@example.test',
                'Фамилия родителя' => 'Петрова',
                'Имя родителя' => 'Анна',
            ]),
        ]);

        $preview = $this->previewImportFile($file);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0);

        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');

        $student = User::query()
            ->where('partner_id', $this->partner->id)
            ->where('email', 'import-student@example.test')
            ->where('role_id', $studentRoleId)
            ->first();

        $this->assertNotNull($student);
        $this->assertSame('Петров', $student->lastname);
        $this->assertSame('Пётр', $student->name);

        $team = Team::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Новая группа импорта')
            ->first();

        $this->assertNotNull($team);
        $this->assertSame($this->legalEntity->id, (int) $team->legal_entity_id);
        $this->assertTrue($student->teams()->whereKey($team->id)->exists());

        $parent = ParentProfile::query()
            ->where('partner_id', $this->partner->id)
            ->where('email', 'import-parent@example.test')
            ->first();

        $this->assertNotNull($parent);
        $this->assertSame((int) $parent->id, (int) $student->parent_id);
    }

    public function test_import_updates_existing_student_by_email_and_replaces_team(): void
    {
        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');
        $oldTeam = Team::factory()->for($this->partner)->create([
            'title' => 'Старая группа',
            'legal_entity_id' => $this->legalEntity->id,
        ]);
        $newTeam = Team::factory()->for($this->partner)->create([
            'title' => 'Новая группа',
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $studentRoleId,
            'lastname' => 'Сидоров',
            'name' => 'Сидор',
            'email' => 'update-import@example.test',
            'phone' => '+79001112233',
        ]);

        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($student, [$oldTeam->id]);

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Сидоров',
                'Имя ученика' => 'Сидор',
                'Группа' => 'Новая группа',
                'Юр. лицо' => $this->legalEntity->displayTitle(),
                'Email ученика' => 'update-import@example.test',
                'Телефон ученика' => '',
            ]),
        ]);

        $preview = $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('summary.update_count', 1)
            ->json();

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $student->refresh();
        $this->assertNull($student->phone);
        $this->assertSame([$newTeam->id], app(\App\Services\TeamUserSyncService::class)->teamIdsForStudent($student));
    }

    public function test_preview_fails_when_parent_email_has_conflicting_rows(): void
    {
        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Один',
                'Имя ученика' => 'Первый',
                'Email родителя' => 'same-parent@example.test',
                'Фамилия родителя' => 'Иванова',
            ]),
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Два',
                'Имя ученика' => 'Второй',
                'Email родителя' => 'same-parent@example.test',
                'Фамилия родителя' => 'Петрова',
            ]),
        ]);

        $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonFragment(['field' => 'Email родителя']);
    }

    public function test_preview_fails_for_soft_deleted_team_title(): void
    {
        Team::factory()->for($this->partner)->create([
            'title' => 'Удалённая группа',
            'legal_entity_id' => $this->legalEntity->id,
            'deleted_at' => now(),
        ]);

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Группа' => 'Удалённая группа',
            ]),
        ]);

        $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonFragment(['field' => 'Группа']);
    }

    public function test_update_with_empty_group_clears_all_teams(): void
    {
        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');
        $team = Team::factory()->for($this->partner)->create([
            'title' => 'Группа для снятия',
            'legal_entity_id' => $this->legalEntity->id,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $studentRoleId,
            'email' => 'clear-teams@example.test',
        ]);

        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($student, [$team->id]);

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => $student->lastname,
                'Имя ученика' => $student->name,
                'Группа' => '',
                'Юр. лицо' => '',
                'Email ученика' => $student->email,
            ]),
        ]);

        $preview = $this->previewImportFile($file);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('updated', 1);

        $student->refresh();
        $this->assertSame([], app(\App\Services\TeamUserSyncService::class)->teamIdsForStudent($student));
    }

    public function test_users_page_shows_import_button_with_permission(): void
    {
        $html = $this->get(route('admin.user1'))->assertOk()->getContent();

        $this->assertStringContainsString('usersImportModal', $html);
        $this->assertStringContainsString('>Импорт</span>', $html);
        $this->assertStringContainsString('initUsersImportModal', $html);
        $this->assertStringContainsString('users-import-step-success', $html);
    }

    /**
     * [P2] E2E smoke: страница → preview → commit → ученик виден в DataTables без F5.
     */
    public function test_import_workflow_page_preview_commit_and_datatable_shows_student(): void
    {
        $email = 'workflow-' . uniqid('', true) . '@example.test';
        $lastname = 'Workflow';
        $firstname = 'Import';

        $this->withoutVite();

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('usersImportModal', false)
            ->assertSee('users-import-check-btn', false);

        $preview = $this->previewImportFile($this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => $lastname,
                'Имя ученика' => $firstname,
                'Email ученика' => $email,
                'Группа' => '',
                'Юр. лицо' => '',
            ]),
        ]));

        $this->assertSame(1, (int) ($preview['preview']['summary']['create_count'] ?? 0));

        $commit = $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('created', 1);

        $this->assertStringContainsString('Импорт завершён', (string) $commit->json('message'));

        $data = $this->getJson('/admin/users/data?draw=1&start=0&length=500&search[value]=' . urlencode($email), $this->importAjaxHeaders())
            ->assertOk()
            ->json();

        $rows = collect($data['data'] ?? []);
        $this->assertTrue(
            $rows->contains(fn (array $row) => str_contains((string) ($row['email'] ?? ''), $email)),
            'Импортированный ученик должен появиться в DataTables без перезагрузки страницы.'
        );
    }
}
