<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#user-delete-clears-email-index: soft delete users обнуляет email.
 */
final class UserDeleteClearsEmailDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_email_cleared_on_user_delete(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="user-delete-clears-email-index"', $html);
        $start = strpos($html, 'id="user-delete-clears-email-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="cabinet-season-years-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('users.email', $chunk);
        $this->assertStringContainsString('null', $chunk);
        $this->assertStringContainsString('unique', $chunk);
        $this->assertStringContainsString('SoftDeletes', $chunk);
        $this->assertStringContainsString('admin-users#user-delete-clears-email', $chunk);
        $this->assertStringContainsString('errors.email', $chunk);
        $this->assertStringContainsString('UserDeleteClearsEmailFeatureTest', $chunk);
        $this->assertStringContainsString('UserDeleteClearsEmailAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('UserDeleteClearsEmailNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('UserDeleteClearsEmailAccessFeatureTest', $chunk);
        $this->assertStringContainsString('UserDeleteClearsEmailDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#user-delete-clears-email-index', $html);
    }

    public function test_admin_users_doc_describes_email_cleared_on_delete(): void
    {
        $html = $this->docFile('admin-users.html');

        $this->assertStringContainsString('id="user-delete-clears-email"', $html);
        $this->assertStringContainsString('DELETE</td><td><code>/admin/user/{user}</code>', $html);
        $this->assertStringContainsString('users.email = null', $html);
        $this->assertStringContainsString('unique:users,email', $html);
        $this->assertStringContainsString('User::deleting', $html);
        $this->assertStringContainsString('UserDeleteClearsEmailFeatureTest', $html);
        $this->assertStringContainsString('UserDeleteClearsEmailAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('UserDeleteClearsEmailNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('UserDeleteClearsEmailAccessFeatureTest', $html);
        $this->assertStringContainsString('errors.email', $html);
        $this->assertStringContainsString('Этот адрес электронной почты уже зарегистрирован.', $html);
        $this->assertStringContainsString('Пользователь с таким email уже существует', $html);
        $this->assertStringContainsString('/doc#user-delete-clears-email-index', $html);
    }

    public function test_trainers_and_staff_docs_point_to_shared_email_clear(): void
    {
        $trainers = $this->docFile('admin-trainers.html');
        $staff = $this->docFile('admin-role-staff.html');

        $this->assertStringContainsString('admin-users#user-delete-clears-email', $trainers);
        $this->assertStringContainsString('users.email = null', $trainers);
        $this->assertStringContainsString('UserDeleteClearsEmailFeatureTest', $trainers);
        $this->assertStringContainsString('UserDeleteClearsEmailAjaxContractFeatureTest', $trainers);
        $this->assertStringContainsString('admin-users#user-delete-clears-email', $staff);
        $this->assertStringContainsString('users.email = null', $staff);
        $this->assertStringContainsString('UserDeleteClearsEmailFeatureTest', $staff);
        $this->assertStringContainsString('UserDeleteClearsEmailAjaxContractFeatureTest', $staff);
    }

    public function test_user_model_clears_email_in_deleting_event(): void
    {
        $path = dirname(__DIR__, 3).'/app/Models/User.php';
        $this->assertFileExists($path);
        $src = (string) file_get_contents($path);

        $this->assertStringContainsString('static::deleting', $src);
        $this->assertStringContainsString("update(['email' => null])", $src);
        $this->assertStringContainsString('$user->email = null', $src);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
