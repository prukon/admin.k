<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#user-student-middlename-index и admin-users §2.8
 * совпадают с полем отчества ученика в модалках /admin/users.
 */
final class UserStudentMiddlenameDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_student_middlename(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="user-student-middlename-index"', $html);
        $start = strpos($html, 'id="user-student-middlename-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-ltv-group-mode-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/users', $chunk);
        $this->assertStringContainsString('users.middlename', $chunk);
        $this->assertStringContainsString('Отчество ученика', $chunk);
        $this->assertStringContainsString('users.name.update', $chunk);
        $this->assertStringContainsString('full_name', $chunk);
        $this->assertStringContainsString('fullNameWithPatronymic', $chunk);
        $this->assertStringContainsString('#edit-middlename', $chunk);
        $this->assertStringContainsString('UserMiddlenameFieldFeatureTest', $chunk);
        $this->assertStringContainsString('admin-users#user-student-middlename', $chunk);
    }

    public function test_admin_users_section_and_live_code_match_middlename_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $adminUsers = $this->docFile('admin-users.html');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/DocumentationController.php');
        $store = (string) file_get_contents($root.'/app/Http/Requests/User/StoreRequest.php');
        $update = (string) file_get_contents($root.'/app/Http/Requests/User/UpdateRequest.php');
        $createModal = (string) file_get_contents($root.'/resources/views/includes/modal/createUser.blade.php');
        $editModal = (string) file_get_contents($root.'/resources/views/includes/modal/editUser.blade.php');
        $userModel = (string) file_get_contents($root.'/app/Models/User.php');
        $userController = (string) file_get_contents($root.'/app/Http/Controllers/Admin/UserController.php');

        $this->assertStringContainsString('id="user-student-middlename"', $adminUsers);
        $this->assertStringContainsString('/doc#user-student-middlename-index', $adminUsers);
        $this->assertStringContainsString('users.middlename', $adminUsers);
        $this->assertStringContainsString('UserMiddlenameFieldFeatureTest', $adminUsers);
        $this->assertStringContainsString('fullNameWithPatronymic', $adminUsers);

        $this->assertStringContainsString('users.middlename, nullable max 100', $controller);

        $this->assertStringContainsString("'middlename'  => 'nullable|string|max:100'", $store);
        $this->assertStringContainsString("\$rules['middlename'] = ['nullable', 'string', 'max:100'];", $update);
        $this->assertStringContainsString("offsetUnset('middlename')", $update);

        $this->assertStringContainsString('id="create-middlename"', $createModal);
        $this->assertStringContainsString('Отчество ученика', $createModal);
        $this->assertStringContainsString('id="edit-middlename"', $editModal);
        $this->assertStringContainsString("@cannot('users.name.update') disabled aria-disabled=\"true\" @endcannot", $editModal);

        $fill = "\$('#edit-user-form #edit-middlename').val(response.user.middlename || '')";
        $this->assertGreaterThanOrEqual(2, substr_count($editModal, $fill));

        $this->assertStringContainsString('function fullNameWithPatronymic()', $userModel);
        $this->assertStringNotContainsString('fullNameWithPatronymic()', $userController);
        $this->assertStringContainsString("->orWhere('users.middlename', 'like', \$like)", $userController);
        $this->assertStringContainsString("'name'         => \$user->full_name ?: 'Без имени'", $userController);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
