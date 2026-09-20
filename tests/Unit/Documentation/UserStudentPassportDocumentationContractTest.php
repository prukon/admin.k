<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#user-student-passport-index и admin-users §2.7
 * совпадают с полями паспорта ученика в модалках /admin/users
 * и отсутствием этих полей в модалке лида.
 */
final class UserStudentPassportDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_student_passport_fields(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="user-student-passport-index"', $html);
        $start = strpos($html, 'id="user-student-passport-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-group-mode-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/users', $chunk);
        $this->assertStringContainsString('users.passport', $chunk);
        $this->assertStringContainsString('users.passport_issued_at', $chunk);
        $this->assertStringContainsString('Паспорт/св-во о рождении', $chunk);
        $this->assertStringContainsString('Дата выдачи паспорта/св-ва', $chunk);
        $this->assertStringContainsString('/admin/school-leads', $chunk);
        $this->assertStringContainsString('UserPassportFieldFeatureTest', $chunk);
        $this->assertStringContainsString('admin-users#user-student-passport', $chunk);
        $this->assertStringContainsString('#edit-passport', $chunk);
        $this->assertStringContainsString('#edit-passport-issued-at', $chunk);
    }

    public function test_admin_users_section_and_live_code_match_passport_contract(): void
    {
        $adminUsers = $this->docFile('admin-users.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $store = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/User/StoreRequest.php');
        $update = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/User/UpdateRequest.php');
        $createModal = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/modal/createUser.blade.php');
        $editModal = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/modal/editUser.blade.php');
        $leadModal = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/school-leads/partials/edit-lead-modal.blade.php');

        $this->assertStringContainsString('id="user-student-passport"', $adminUsers);
        $this->assertStringContainsString('/doc#user-student-passport-index', $adminUsers);
        $this->assertStringContainsString('users.passport', $adminUsers);
        $this->assertStringContainsString('users.passport_issued_at', $adminUsers);
        $this->assertStringContainsString('before_or_equal:today', $adminUsers);
        $this->assertStringContainsString('UserPassportFieldFeatureTest', $adminUsers);

        $this->assertStringContainsString('users.passport, users.passport_issued_at', $controller);

        $this->assertStringContainsString("'passport'    => 'nullable|string|max:100'", $store);
        $this->assertStringContainsString("'passport_issued_at' => 'nullable|date|before_or_equal:today'", $store);
        $this->assertStringContainsString("'passport' => ['nullable', 'string', 'max:100']", $update);
        $this->assertStringContainsString("'passport_issued_at' => ['nullable', 'date', 'before_or_equal:today']", $update);

        $this->assertStringContainsString('id="create-passport"', $createModal);
        $this->assertStringContainsString('id="create-passport-issued-at"', $createModal);
        $this->assertStringContainsString('id="edit-passport"', $editModal);
        $this->assertStringContainsString('id="edit-passport-issued-at"', $editModal);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($editModal, "\$('#edit-user-form #edit-passport').val(response.user.passport || '')")
        );

        $this->assertStringNotContainsString('Паспорт/св-во о рождении', $leadModal);
        $this->assertStringNotContainsString('name="passport_issued_at"', $leadModal);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
