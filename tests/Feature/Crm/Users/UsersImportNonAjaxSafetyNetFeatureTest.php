<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\PartnerLegalEntity;
use App\Models\Role;
use App\Models\User;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;
use Tests\Feature\Crm\Users\Concerns\UsersImportTestHelpers;

/**
 * Non-AJAX safety-net импорта: POST без X-Requested-With → JSON-контракт, не пустой 200/500.
 *
 * Импорт — JSON-only endpoint'ы (без HTML-формы), поэтому вместо redirect проверяем
 * корректный JSON-ответ и запись в БД при успешном commit.
 */
final class UsersImportNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_preview_non_ajax_multipart_returns_json_contract_not_empty_200(): void
    {
        $email = 'nonajax-preview-' . uniqid('', true) . '@example.test';
        $file = $this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => $email,
            ]),
        ]);

        $response = $this->post(route('admin.users.import.preview'), [
            'file' => $file,
        ], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonStructure([
                'message',
                'import_token',
                'valid',
                'summary' => [
                    'total_rows',
                    'create_count',
                    'update_count',
                    'update_with_changes_count',
                    'update_unchanged_count',
                    'update_with_clears_count',
                ],
                'preview' => [
                    ['row', 'student', 'team', 'mode', 'changes', 'has_clears'],
                ],
            ]);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_preview_non_ajax_update_diff_returns_summary_counts_and_creates_no_db_side_effects(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => (int) Role::query()->where('name', 'user')->value('id'),
            'lastname' => 'NonAjax',
            'name' => 'Diff',
            'email' => 'nonajax-diff@example.test',
            'phone' => '+79008880008',
            'is_enabled' => true,
        ]);

        $response = $this->post(route('admin.users.import.preview'), [
            'file' => $this->makeImportFile([
                $this->sampleImportRow($this->legalEntity, [
                    'Фамилия ученика' => $student->lastname,
                    'Имя ученика' => 'Changed',
                    'Email ученика' => $student->email,
                    'Группа' => '',
                    'Юр. лицо' => '',
                    'Телефон ученика' => '',
                    'Активен' => 'да',
                ]),
            ]),
        ], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.update_count', 1)
            ->assertJsonPath('summary.update_with_changes_count', 1)
            ->assertJsonPath('summary.update_unchanged_count', 0)
            ->assertJsonPath('summary.update_with_clears_count', 1)
            ->assertJsonPath('preview.0.has_clears', true);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $student->refresh();
        $this->assertSame('Diff', $student->name);
        $this->assertSame('+79008880008', $student->phone);
    }

    public function test_preview_non_ajax_validation_failure_returns_422_json_not_empty_200(): void
    {
        $response = $this->post(route('admin.users.import.preview'), [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_commit_non_ajax_post_returns_json_and_creates_student_not_empty_200(): void
    {
        $email = 'nonajax-commit-' . uniqid('', true) . '@example.test';
        $preview = $this->previewImportFile($this->makeImportFile([
            $this->sampleImportRow($this->legalEntity, [
                'Email ученика' => $email,
            ]),
        ]));

        $response = $this->post(route('admin.users.import.commit'), [
            'import_token' => $preview['import_token'],
        ], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'created', 'updated'])
            ->assertJsonPath('created', 1);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());

        $studentRoleId = (int) Role::query()->where('name', 'user')->value('id');
        $this->assertNotNull(
            User::query()
                ->where('partner_id', $this->partner->id)
                ->where('email', $email)
                ->where('role_id', $studentRoleId)
                ->first()
        );
    }

    public function test_commit_non_ajax_validation_failure_returns_422_json_not_empty_200(): void
    {
        $response = $this->post(route('admin.users.import.commit'), [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['import_token']);

        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_template_download_non_ajax_returns_xlsx_not_empty_200(): void
    {
        $response = $this->get(route('admin.users.import.template'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition');

        $this->assertStringContainsString(
            'import_users_template.xlsx',
            (string) $response->headers->get('content-disposition')
        );
        $this->assertNotSame(500, $response->getStatusCode());
    }
}
