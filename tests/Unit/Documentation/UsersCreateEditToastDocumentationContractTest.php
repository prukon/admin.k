<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#users-create-edit-toast-index и admin-users §4.5.5
 * совпадают с UI: create/edit ученика — toast #kidsMainToast, не showSuccessModal и не F5.
 */
final class UsersCreateEditToastDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_student_create_and_edit_toast(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="users-create-edit-toast-index"', $html);
        $start = strpos($html, 'id="users-create-edit-toast-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="partner-wallet-isolation-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#createUserModal', $chunk);
        $this->assertStringContainsString('#editUserModal', $chunk);
        $this->assertStringContainsString('#users-table', $chunk);
        $this->assertStringContainsString("ajax.reload(null, false)", $chunk);
        $this->assertStringContainsString('#kidsMainToast', $chunk);
        $this->assertStringContainsString('window.showToast', $chunk);
        $this->assertStringContainsString('Клиент создан успешно', $chunk);
        $this->assertStringContainsString('Клиент успешно обновлён', $chunk);
        $this->assertStringContainsString('Клиент успешно создан.', $chunk);
        $this->assertStringContainsString('Клиент успешно обновлён.', $chunk);
        $this->assertStringContainsString('send_welcome_email', $chunk);
        $this->assertStringContainsString('Клиент успешно удален.', $chunk);
        $this->assertStringContainsString('school-leads-table', $chunk);
        $this->assertStringContainsString('onSchoolLeadUserCreated', $chunk);
        $this->assertStringContainsString('createUser.blade.php', $chunk);
        $this->assertStringContainsString('не</b> подключается', $chunk);
        $this->assertStringContainsString('editUserLink2', $chunk);
        $this->assertStringContainsString('editUserLink', $chunk);
        $this->assertStringContainsString('editUserForm', $chunk);
        $this->assertStringContainsString('admin-users#user-ajax-submit', $chunk);
        $this->assertStringContainsString('success-toast-instead-of-modal-index', $chunk);
        $this->assertStringContainsString('teams-create-edit-toast-index', $chunk);
        $this->assertStringContainsString('AdminUsersCreateEditToastAccessFeatureTest', $chunk);
        $this->assertStringContainsString('AdminUsersCreateEditToastAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('AdminUsersCreateEditToastNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('AdminUsersCreateEditToastUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString('AdminUsersCreateEditToastFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('UsersCreateEditToastDocumentationContractTest', $chunk);
        $this->assertStringContainsString('test_create_and_edit_user_success_without_reload_js_is_valid_and_uses_toast', $chunk);
        $this->assertStringContainsString('/doc#users-create-edit-toast-index', $html);

        $this->assertStringNotContainsString('сохранение карточки ученика (PATCH + reload)', $chunk);
        $this->assertStringNotContainsString('«Добавить» на вкладке «Клиенты»', $chunk);
    }

    public function test_umbrella_toast_list_includes_create_edit_and_links_dedicated_announcement(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="success-toast-instead-of-modal-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="admin-sidebar-stability-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('Create и PATCH карточки ученика', $chunk);
        $this->assertStringContainsString('ajax.reload(null, false)', $chunk);
        $this->assertStringContainsString('/doc#users-create-edit-toast-index', $chunk);
        $this->assertStringContainsString('CRUD партнёров', $chunk);
        $this->assertStringContainsString('Группа создана успешно', $chunk);
        $this->assertStringContainsString('/doc#teams-create-edit-toast-index', $chunk);
        $this->assertStringNotContainsString('CRUD групп и партнёров', $chunk);
        $this->assertStringNotContainsString('сохранение карточки ученика (PATCH + reload)', $chunk);
        $this->assertStringNotContainsString('«Добавить» на вкладке «Клиенты»', $chunk);
    }

    public function test_admin_users_doc_describes_create_and_edit_toast_without_reload(): void
    {
        $html = $this->docFile('admin-users.html');

        $this->assertStringContainsString('id="user-ajax-submit"', $html);
        $this->assertStringContainsString('/doc#users-create-edit-toast-index', $html);
        $this->assertStringContainsString('window.showToast', $html);
        $this->assertStringContainsString('ajax.reload(null, false)', $html);
        $this->assertStringContainsString('#createUserModal', $html);
        $this->assertStringContainsString('#editUserModal', $html);
        $this->assertStringContainsString('Клиент успешно создан.', $html);
        $this->assertStringContainsString('Клиент успешно обновлён.', $html);
        $this->assertStringContainsString('school-leads-widget#school-lead-create-client-button', $html);
        $this->assertStringContainsString('AdminUsersCreateEditToastAccessFeatureTest', $html);
        $this->assertStringContainsString('AdminUsersCreateEditToastAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('AdminUsersCreateEditToastNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('AdminUsersCreateEditToastUiContractsFeatureTest', $html);
        $this->assertStringContainsString('AdminUsersCreateEditToastFullAccessFeatureTest', $html);
        $this->assertStringContainsString('UsersCreateEditToastDocumentationContractTest', $html);
        $this->assertStringNotContainsString('showSuccessModal("Редактирование клиента"', $html);
    }

    public function test_related_pages_and_controller_title_link_announcement(): void
    {
        $section = $this->docFile('admin-users-section.html');
        $partials = $this->docFile('reusable-ui-partials.html');
        $leads = $this->docFile('school-leads-widget.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#users-create-edit-toast-index', $section);
        $this->assertStringContainsString('ajax.reload(null, false)', $section);
        $this->assertStringContainsString('/doc#users-create-edit-toast-index', $partials);
        $this->assertStringContainsString('/doc#users-create-edit-toast-index', $leads);
        $this->assertStringContainsString('create/edit без success-модалки', $controller);
        $this->assertStringContainsString('#kidsMainToast', $controller);
    }

    public function test_create_and_edit_js_matches_documented_toasts(): void
    {
        $create = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/modal/createUser.blade.php');
        $edit = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/modal/editUser.blade.php');

        $this->assertStringContainsString(
            "window.showToast((response && response.message) ? response.message : 'Клиент успешно создан.', 'success')",
            $create
        );
        $this->assertStringContainsString('$(\'#users-table\').DataTable().ajax.reload(null, false)', $create);
        $this->assertStringContainsString("\$form.data('success-handler') === 'school-leads-table'", $create);
        $this->assertStringContainsString('onSchoolLeadUserCreated', $create);
        $this->assertStringNotContainsString('data-success-handler', $create);
        $this->assertStringNotContainsString('showSuccessModal', $create);
        $this->assertStringNotContainsString('window.location.reload()', $create);

        $this->assertStringContainsString(
            "window.showToast(response.message || 'Клиент успешно обновлён.', 'success')",
            $edit
        );
        $this->assertStringContainsString('$(\'#users-table\').DataTable().ajax.reload(null, false)', $edit);
        $this->assertStringContainsString('function editUserLink2()', $edit);
        $this->assertStringContainsString('function editUserLink()', $edit);
        $this->assertStringContainsString('function editUserForm()', $edit);
        $this->assertStringNotContainsString('showSuccessModal("Редактирование клиента"', $edit);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
