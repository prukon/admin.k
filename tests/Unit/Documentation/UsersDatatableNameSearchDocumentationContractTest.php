<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#users-datatable-name-search-index и admin-users
 * совпадают с поиском DataTables по склейке фамилии и имени.
 */
final class UsersDatatableNameSearchDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_users_datatable_name_search(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="users-datatable-name-search-index"', $html);
        $start = strpos($html, 'id="users-datatable-name-search-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="unlimited-school-schedule-trials-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/users', $chunk);
        $this->assertStringContainsString('search.value', $chunk);
        $this->assertStringContainsString("CONCAT_WS(' ', users.lastname, users.name)", $chunk);
        $this->assertStringContainsString("CONCAT_WS(' ', users.name, users.lastname)", $chunk);
        $this->assertStringContainsString("CONCAT_WS(' ', lastname, firstname, middlename)", $chunk);
        $this->assertStringContainsString("CONCAT_WS(' ', firstname, lastname)", $chunk);
        $this->assertStringContainsString("CONCAT_WS(' ', firstname, middlename, lastname)", $chunk);
        $this->assertStringContainsString('full_name_genitive', $chunk);
        $this->assertStringContainsString('test_users_data_search_matches_lastname_and_name_together', $chunk);
        $this->assertStringContainsString('test_users_data_search_matches_parent_full_name', $chunk);
        $this->assertStringContainsString('admin-users#users-datatable-name-search', $chunk);
    }

    public function test_admin_users_section_and_live_code_match_name_search_contract(): void
    {
        $root = dirname(__DIR__, 3);
        $adminUsers = $this->docFile('admin-users.html');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/DocumentationController.php');
        $userController = (string) file_get_contents($root.'/app/Http/Controllers/Admin/UserController.php');

        $this->assertStringContainsString('id="users-datatable-name-search"', $adminUsers);
        $this->assertStringContainsString('/doc#users-datatable-name-search-index', $adminUsers);
        $this->assertStringContainsString("CONCAT_WS(' ', users.lastname, users.name)", $adminUsers);
        $this->assertStringContainsString("CONCAT_WS(' ', firstname, middlename, lastname)", $adminUsers);
        $this->assertStringContainsString('test_users_data_search_matches_lastname_and_name_together', $adminUsers);

        $this->assertStringContainsString('поиск DataTables по склейке фамилия+имя', $controller);

        $this->assertStringContainsString("CONCAT_WS(' ', users.lastname, users.name) LIKE ?", $userController);
        $this->assertStringContainsString("CONCAT_WS(' ', users.name, users.lastname) LIKE ?", $userController);
        $this->assertStringContainsString("CONCAT_WS(' ', lastname, firstname, middlename) LIKE ?", $userController);
        $this->assertStringContainsString("CONCAT_WS(' ', firstname, lastname) LIKE ?", $userController);
        $this->assertStringContainsString("CONCAT_WS(' ', firstname, middlename, lastname) LIKE ?", $userController);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
