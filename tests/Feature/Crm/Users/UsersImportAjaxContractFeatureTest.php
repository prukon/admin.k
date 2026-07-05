<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\PartnerLegalEntity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UsersImportTestHelpers;

/**
 * AJAX-контракт импорта учеников: postJson + X-Requested-With → JSON, статусы 200/422.
 */
final class UsersImportAjaxContractFeatureTest extends CrmTestCase
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

    public function test_preview_ajax_json_contract_on_success(): void
    {
        $email = 'ajax-preview-' . uniqid('', true) . '@example.test';
        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Ajax',
                'Имя ученика' => 'Preview',
                'Email ученика' => $email,
            ]),
        ]);

        $response = $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('message', 'Файл проверен успешно. Подтвердите импорт.')
            ->assertJsonStructure([
                'message',
                'import_token',
                'valid',
                'summary' => ['total_rows', 'create_count', 'update_count'],
                'preview' => [
                    ['row', 'student', 'team', 'mode'],
                ],
            ]);

        $this->assertNotSame('', (string) $response->json('import_token'));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_preview_ajax_validation_failure_without_file_returns_422_json(): void
    {
        $this->postJson(route('admin.users.import.preview'), [], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_preview_ajax_business_validation_failure_returns_422_json_contract(): void
    {
        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Группа' => 'Группа без юрлица',
                'Юр. лицо' => '',
            ]),
        ]);

        $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonStructure([
                'message',
                'valid',
                'errors' => [
                    ['row', 'field', 'message'],
                ],
            ])
            ->assertJsonFragment(['field' => 'Юр. лицо']);
    }

    public function test_commit_ajax_json_contract_on_success(): void
    {
        $email = 'ajax-commit-' . uniqid('', true) . '@example.test';
        $preview = $this->previewImportFile($this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => $email,
            ]),
        ]));

        $response = $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['message', 'created', 'updated'])
            ->assertJsonPath('created', 1)
            ->assertJsonPath('updated', 0);

        $this->assertStringContainsString('Импорт завершён', (string) $response->json('message'));
        $this->assertNotSame('', trim((string) $response->getContent()));

        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');
        $this->assertNotNull(
            User::query()
                ->where('partner_id', $this->partner->id)
                ->where('email', $email)
                ->where('role_id', $studentRoleId)
                ->first()
        );
    }

    public function test_commit_ajax_validation_failure_without_token_returns_422_json(): void
    {
        $this->postJson(route('admin.users.import.commit'), [], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['import_token']);
    }

    public function test_commit_ajax_validation_failure_with_invalid_uuid_returns_422_json(): void
    {
        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => 'not-a-uuid',
        ], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['import_token']);
    }

    public function test_commit_ajax_returns_422_when_import_session_expired(): void
    {
        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => '00000000-0000-4000-8000-000000000000',
        ], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сессия импорта истекла или не найдена. Загрузите файл повторно.');
    }

    public function test_commit_ajax_returns_422_when_token_belongs_to_another_actor(): void
    {
        $preview = $this->previewImportFile($this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => 'token-scope-' . uniqid('', true) . '@example.test',
            ]),
        ]));

        $other = $this->createUserWithoutPermission('users.import', $this->partner);
        $this->grantUsersView($other);
        $this->grantPermission($other, 'users.import');
        $this->actingAs($other);

        $this->postJson(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сессия импорта недоступна для текущего пользователя.');

        Cache::forget('users_import:' . $preview['import_token']);
    }

    public function test_preview_fails_when_student_email_belongs_to_foreign_partner(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->studentRoleId(),
            'email' => 'foreign-student@example.test',
        ]);

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => $foreignStudent->email,
            ]),
        ]);

        $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonFragment(['field' => 'Email ученика']);
    }

    public function test_preview_fails_on_duplicate_student_email_in_file(): void
    {
        $email = 'dup-student-' . uniqid('', true) . '@example.test';

        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Один',
                'Имя ученика' => 'Первый',
                'Email ученика' => $email,
            ]),
            $this->sampleImportRow($this->legalEntity, [
                'Фамилия ученика' => 'Два',
                'Имя ученика' => 'Второй',
                'Email ученика' => $email,
            ]),
        ]);

        $this->postJson(route('admin.users.import.preview'), ['file' => $file], $this->importAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonFragment(['field' => 'Email ученика']);
    }
}
