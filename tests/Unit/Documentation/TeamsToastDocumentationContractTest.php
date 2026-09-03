<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#teams-create-edit-toast-index и admin-teams §1.2
 * совпадают с UI групп: create/edit/delete — общий #kidsMainToast, не showSuccessModal и не F5.
 */
final class TeamsToastDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_team_create_edit_delete_toast(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="teams-create-edit-toast-index"', $html);
        $start = strpos($html, 'id="teams-create-edit-toast-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="partner-wallet-ledger-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#createTeamModal', $chunk);
        $this->assertStringContainsString('#editTeamModal', $chunk);
        $this->assertStringContainsString('#teams-table', $chunk);
        $this->assertStringContainsString("ajax.reload(null, false)", $chunk);
        $this->assertStringContainsString('#kidsMainToast', $chunk);
        $this->assertStringContainsString('window.showToast', $chunk);
        $this->assertStringContainsString('Группа создана успешно', $chunk);
        $this->assertStringContainsString('Группа успешно обновлена', $chunk);
        $this->assertStringContainsString('Группа успешно удалена.', $chunk);
        $this->assertStringContainsString('Группа и её связь с пользователями успешно помечены как удалённые', $chunk);
        $this->assertStringContainsString('#confirmDeleteModal', $chunk);
        $this->assertStringContainsString('hidden.bs.modal', $chunk);
        $this->assertStringContainsString('Введите название', $chunk);
        $this->assertStringContainsString('JSON 200', $chunk);
        $this->assertStringContainsString('не 302', $chunk);
        $this->assertStringContainsString('ChatTeamGroupThreadNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('edit-team-link', $chunk);
        $this->assertStringContainsString('#update-team-btn', $chunk);
        $this->assertStringContainsString('deleteTeam()', $chunk);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.setValues', $chunk);
        $this->assertStringContainsString('chat-team-groups-index', $chunk);
        $this->assertStringContainsString('chat-groups-index', $chunk);
        $this->assertStringContainsString('admin-teams#team-ajax-submit', $chunk);
        $this->assertStringContainsString('success-toast-instead-of-modal-index', $chunk);
        $this->assertStringContainsString('TeamsCreateEditToastAccessFeatureTest', $chunk);
        $this->assertStringContainsString('TeamsCreateEditToastAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('TeamsCreateEditToastNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('TeamsCreateEditToastUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString('TeamsCreateEditToastFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('TeamsToastDocumentationContractTest', $chunk);
        $this->assertStringContainsString('test_create_and_edit_team_success_without_reload_js_is_valid_and_uses_toast', $chunk);
        $this->assertStringContainsString('/doc#teams-create-edit-toast-index', $html);

        $this->assertStringNotContainsString('CRUD групп и партнёров', $chunk);
    }

    public function test_umbrella_toast_list_includes_teams_and_links_dedicated_announcement(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="success-toast-instead-of-modal-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="admin-sidebar-stability-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('Группа создана успешно', $chunk);
        $this->assertStringContainsString('Группа успешно обновлена', $chunk);
        $this->assertStringContainsString('Группа успешно удалена.', $chunk);
        $this->assertStringContainsString('Группа и её связь с пользователями успешно помечены как удалённые', $chunk);
        $this->assertStringContainsString('ajax.reload(null, false)', $chunk);
        $this->assertStringContainsString('#confirmDeleteModal', $chunk);
        $this->assertStringContainsString('#kidsMainToast', $chunk);
        $this->assertStringContainsString('/doc#teams-create-edit-toast-index', $chunk);
        $this->assertStringContainsString('TeamsToastDocumentationContractTest', $chunk);
        $this->assertStringContainsString('TeamsCreateEditToastAccessFeatureTest', $chunk);
        $this->assertStringContainsString('CRUD партнёров', $chunk);
        $this->assertStringNotContainsString('CRUD групп и партнёров', $chunk);
    }

    public function test_admin_teams_doc_describes_create_edit_delete_toast(): void
    {
        $html = $this->docFile('admin-teams.html');

        $this->assertStringContainsString('id="team-ajax-submit"', $html);
        $this->assertStringContainsString('window.showToast', $html);
        $this->assertStringContainsString('Группа создана успешно', $html);
        $this->assertStringContainsString('Группа успешно обновлена', $html);
        $this->assertStringContainsString('Группа успешно удалена.', $html);
        $this->assertStringContainsString('ajax.reload(null, false)', $html);
        $this->assertStringContainsString('showSuccessModal', $html);
        $this->assertStringContainsString('TeamsToastDocumentationContractTest', $html);
        $this->assertStringContainsString('TeamsCreateEditToastAccessFeatureTest', $html);
        $this->assertStringContainsString('TeamsCreateEditToastAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('TeamsCreateEditToastNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('TeamsCreateEditToastUiContractsFeatureTest', $html);
        $this->assertStringContainsString('TeamsCreateEditToastFullAccessFeatureTest', $html);
        $this->assertStringContainsString('hidden.bs.modal', $html);
        $this->assertStringContainsString('#confirmDeleteModal', $html);
        $this->assertStringContainsString('/doc#teams-create-edit-toast-index', $html);
        $this->assertStringContainsString('/doc#success-toast-instead-of-modal-index', $html);
        $this->assertStringContainsString('JSON 200', $html);
        $this->assertStringContainsString('ChatTeamGroupThreadNonAjaxSafetyNetFeatureTest', $html);
    }

    public function test_related_pages_and_controller_title_link_announcement(): void
    {
        $partials = $this->docFile('reusable-ui-partials.html');
        $hierarchy = $this->docFile('directories-hierarchy.html');
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#teams-create-edit-toast-index', $partials);
        $this->assertStringContainsString('ajax.reload(null, false)', $partials);
        $this->assertStringContainsString('/doc#teams-create-edit-toast-index', $hierarchy);
        $this->assertStringContainsString('/doc#teams-create-edit-toast-index', $index);
        $this->assertStringContainsString('create/edit/delete без success-модалки', $controller);
        $this->assertStringContainsString('#kidsMainToast', $controller);
    }

    public function test_team_modals_js_matches_documented_toasts(): void
    {
        $createPath = dirname(__DIR__, 3).'/resources/views/includes/modal/createTeam.blade.php';
        $editPath = dirname(__DIR__, 3).'/resources/views/includes/modal/editTeam.blade.php';
        $this->assertFileExists($createPath);
        $this->assertFileExists($editPath);

        $create = (string) file_get_contents($createPath);
        $edit = (string) file_get_contents($editPath);

        $this->assertStringContainsString(
            "window.showToast((data && data.message) ? data.message : 'Группа создана успешно', 'success')",
            $create
        );
        $this->assertStringContainsString('$(\'#teams-table\').DataTable().ajax.reload(null, false)', $create);
        $this->assertStringContainsString('createModalInstance.hide()', $create);
        $this->assertStringNotContainsString('showSuccessModal', $create);
        $this->assertStringNotContainsString('location.reload', $create);

        $this->assertStringContainsString(
            "window.showToast(response.message || 'Группа успешно обновлена', 'success')",
            $edit
        );
        $this->assertStringContainsString(
            "window.showToast('Группа успешно удалена.', 'success')",
            $edit
        );
        $this->assertStringContainsString('$(\'#teams-table\').DataTable().ajax.reload(null, false)', $edit);
        $this->assertStringContainsString(
            "confirmEl.addEventListener('hidden.bs.modal', showDeletedToast, { once: true })",
            $edit
        );
        $this->assertStringNotContainsString('showSuccessModal("Редактирование группы"', $edit);
        $this->assertStringNotContainsString('showSuccessModal("Удаление группы"', $edit);
        $this->assertStringNotContainsString('location.reload', $edit);
    }

    public function test_controller_json_messages_match_documented_toasts(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/TeamController.php');

        $this->assertStringContainsString("'message' => 'Группа создана успешно'", $controller);
        $this->assertStringContainsString("return redirect()->route('admin.team.index')", $controller);
        $this->assertStringContainsString("return response()->json(['message' => 'Группа успешно обновлена'])", $controller);
        $this->assertStringContainsString(
            "return response()->json(['message' => 'Группа и её связь с пользователями успешно помечены как удалённые'])",
            $controller
        );
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
