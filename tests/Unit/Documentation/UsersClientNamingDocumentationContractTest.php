<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#users-client-naming-index должен совпадать с UI и JSON:
 * ученик = «Клиент», раздел/сайдбар = «Пользователи».
 */
final class UsersClientNamingDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_client_naming_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="users-client-naming-index"', $html);
        $start = strpos($html, 'id="users-client-naming-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="phpunit-last-log-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('roles.name = user', $chunk);
        $this->assertStringContainsString('UsersSectionTabsResolver', $chunk);
        $this->assertStringContainsString('Клиенты', $chunk);
        $this->assertStringContainsString('Все клиенты', $chunk);
        $this->assertStringContainsString('Создание клиента', $chunk);
        $this->assertStringContainsString('Редактирование клиента', $chunk);
        $this->assertStringContainsString('Удаление клиента', $chunk);
        $this->assertStringContainsString('Клиент создан успешно', $chunk);
        $this->assertStringContainsString('Клиент успешно обновлён', $chunk);
        $this->assertStringContainsString('Клиент успешно удалён', $chunk);
        $this->assertStringContainsString('Клиент успешно удален.', $chunk);
        $this->assertStringContainsString('Удален клиент:', $chunk);
        $this->assertStringContainsString('Управление клиентами', $chunk);
        $this->assertStringContainsString('Страница "Пользователи"', $chunk);
        $this->assertStringContainsString('пользовательских полей', $chunk);
        $this->assertStringContainsString('Пользователь создан', $chunk);
        $this->assertStringContainsString('Пользователь успешно обновлен', $chunk);
        $this->assertStringContainsString('Все пользователи', $chunk);
        $this->assertStringContainsString('Импорт учеников из Excel', $chunk);
        $this->assertStringContainsString('editUserLink2', $chunk);
        $this->assertStringContainsString('editUserLink', $chunk);
        $this->assertStringContainsString('2026_08_18_221000_rename_user_client_labels.php', $chunk);
        $this->assertStringContainsString('admin-users-section', $chunk);
        $this->assertStringContainsString('admin-users#user-client-naming', $chunk);
        $this->assertStringContainsString('AdminUsersClientNamingFeatureTest', $chunk);
        $this->assertStringContainsString('UsersClientNamingDocumentationContractTest', $chunk);

        $this->assertStringNotContainsString('сайдбар и общий h4 по-прежнему <b>«Клиенты»</b>', $chunk);
        $this->assertStringNotContainsString('users.view</code> в матрице — «Страница "Клиенты"', $chunk);
        $this->assertStringNotContainsString('Отчёты (payment-intents): placeholder «Все клиенты»', $chunk);
    }

    public function test_announcement_matches_live_blade_and_controllers(): void
    {
        $usersPage = $this->viewFile('admin/user.blade.php');
        $sidebar = $this->viewFile('includes/sidebar.blade.php');
        $create = $this->viewFile('includes/modal/createUser.blade.php');
        $edit = $this->viewFile('includes/modal/editUser.blade.php');
        $fields = $this->viewFile('includes/modal/fieldModal.blade.php');
        $trainers = $this->viewFile('admin/trainers/index.blade.php');
        $account = $this->viewFile('account/users.blade.php');
        $intents = $this->viewFile('admin/report/payment_intents.blade.php');
        $userController = $this->appFile('Http/Controllers/Admin/UserController.php');
        $staffController = $this->appFile('Http/Controllers/Admin/RoleStaffUserController.php');
        $accountController = $this->appFile('Http/Controllers/Admin/AccountController.php');
        $tabs = $this->appFile('Services/Users/UsersSectionTabsResolver.php');
        $permSeeder = $this->databaseFile('seeders/PermissionSeeder.php');
        $rolesSeeder = $this->databaseFile('seeders/RolesSeeder.php');
        $groupsSeeder = $this->databaseFile('seeders/PermissionGroupsSeeder.php');

        $this->assertStringContainsString('<p>Пользователи</p>', $sidebar);
        $this->assertStringContainsString('>Пользователи</h4>', $usersPage);
        $this->assertStringContainsString('>Клиенты</h1>', $usersPage);
        $this->assertStringContainsString('>Все клиенты</option>', $usersPage);
        $this->assertStringContainsString('title="Импорт учеников из Excel"', $usersPage);
        $this->assertStringContainsString('Настройка пользовательских полей', $fields);
        $this->assertStringContainsString('id="createUserModalLabel">Создание клиента</h5>', $create);
        $this->assertStringContainsString('id="editUserModalLabel">Редактирование клиента</h5>', $edit);
        $this->assertStringContainsString('function editUserLink2()', $edit);
        $this->assertStringContainsString('function editUserLink()', $edit);
        $this->assertStringContainsString('window.showToast(\'Клиент успешно удален.\', \'success\');', $edit);
        $this->assertStringContainsString('"Удаление клиента"', $edit);
        $this->assertStringContainsString('id="trainerCreateModalLabel">Создание тренера</h5>', $trainers);
        $this->assertStringContainsString("'Клиент'", $account);
        $this->assertStringContainsString('data-placeholder="Все пользователи"', $intents);

        $this->assertStringContainsString("'user'    => 'Клиенты'", $tabs);
        $this->assertStringContainsString("'Клиент создан успешно'", $userController);
        $this->assertStringContainsString("'message' => 'Клиент успешно обновлён'", $userController);
        $this->assertStringContainsString("'success' => 'Клиент успешно удалён'", $userController);
        $this->assertStringContainsString('Удален клиент:', $userController);
        $this->assertStringContainsString("'Пользователь создан'", $staffController);
        $this->assertStringContainsString("'message' => 'Пользователь обновлён'", $staffController);
        $this->assertStringContainsString("'message' => 'Пользователь успешно обновлен'", $accountController);

        $this->assertStringContainsString('Страница "Пользователи"', $permSeeder);
        $this->assertStringContainsString("'label'      => 'Клиент'", $rolesSeeder);
        $this->assertStringContainsString("'name'        => 'Управление клиентами'", $groupsSeeder);
    }

    public function test_related_doc_pages_link_announcement_and_keep_umbrella_users(): void
    {
        $section = $this->docFile('admin-users-section.html');
        $users = $this->docFile('admin-users.html');
        $groups = $this->docFile('settings-permission-groups.html');
        $partners = $this->docFile('partners-permissions.html');

        $this->assertStringContainsString('id="user-client-naming"', $users);
        $this->assertStringContainsString('/doc#users-client-naming-index', $users);
        $this->assertStringContainsString('/doc#users-client-naming-index', $section);
        $this->assertStringContainsString('пункт сайдбара остаются «Пользователи»', $section);
        $this->assertStringContainsString('Управление клиентами', $groups);
        $this->assertStringContainsString('Страница "Пользователи"', $groups);
        $this->assertStringContainsString('2026_08_18_221000_rename_user_client_labels.php', $groups);
        $this->assertStringContainsString('UI-label «Клиент»', $partners);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function viewFile(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/resources/views/'.$relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function appFile(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/app/'.$relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function databaseFile(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/database/'.$relative;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
