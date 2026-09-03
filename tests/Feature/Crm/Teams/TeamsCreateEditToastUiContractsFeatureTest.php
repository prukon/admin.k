<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

/**
 * UX-баг прода: после create/edit/delete группы открывалась success-модалка и reload.
 * После фикса — toast #kidsMainToast, закрытие модалки, DataTables ajax.reload без F5.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TeamsCreateEditToastUiContractsFeatureTest extends TeamsCreateEditToastTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsGroupsViewer();
    }

    public function test_teams_page_first_open_shows_hidden_empty_toast_outside_create_and_edit_modals(): void
    {
        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html), 'Первое открытие /admin/teams не должно быть пустым 200');
        $this->assertStringContainsString('id="createTeamModal"', $html);
        $this->assertStringContainsString('id="editTeamModal"', $html);
        $this->assertStringContainsString('data-bs-target="#createTeamModal"', $html);
        $this->assertStringContainsString('id="teamForm"', $html);
        $this->assertStringContainsString('id="edit-team-form"', $html);
        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringContainsString('id="kidsMainToastBody"', $html);
        $this->assertStringContainsString('window.showToast', $html);
        $this->assertMatchesRegularExpression(
            '/id="kidsMainToastBody"><\/div>/',
            $html,
            'Тело всплывайки при первом открытии пустое — текст появляется только после успеха'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="kidsMainToast"[^>]*\bshow\b/',
            $html,
            'При первом открытии всплывайка не должна быть уже показана'
        );

        $createPos = strpos($html, 'id="createTeamModal"');
        $editPos = strpos($html, 'id="editTeamModal"');
        $layoutSuccessJsPos = strpos($html, 'function showSuccessModal');
        $toastPos = strpos($html, 'id="kidsMainToast"');
        $this->assertNotFalse($createPos);
        $this->assertNotFalse($editPos);
        $this->assertNotFalse($layoutSuccessJsPos);
        $this->assertNotFalse($toastPos);
        $this->assertLessThan($layoutSuccessJsPos, $createPos);
        $this->assertLessThan($layoutSuccessJsPos, $editPos);
        $this->assertLessThan(
            $toastPos,
            $layoutSuccessJsPos,
            'Toast в footer layout после модалок: повторное открытие карточки не пересобирает всплывайку'
        );

        $this->assertStringNotContainsString('showSuccessModal("Создание группы"', $html);
        $this->assertStringNotContainsString('showSuccessModal("Редактирование группы"', $html);
        $this->assertStringNotContainsString('showSuccessModal("Удаление группы"', $html);
        $this->assertStringNotContainsString('window.location.reload()', $html);
    }

    public function test_first_open_create_modal_has_title_first_and_active_by_default(): void
    {
        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->getContent();

        $start = strpos($html, 'id="createTeamModal"');
        $end = strpos($html, 'id="editTeamModal"');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $chunk = substr($html, $start, $end - $start);

        $titlePos = strpos($chunk, 'name="title"');
        $enabledPos = strpos($chunk, 'name="is_enabled"');
        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($enabledPos);
        $this->assertLessThan($enabledPos, $titlePos, 'Название выше активности при первом открытии');
        $this->assertMatchesRegularExpression(
            '/name="is_enabled"[^>]*>\s*<option value="1">Активен<\/option>/s',
            $chunk,
            'Первая опция «Активен» — дефолт браузера при открытии и после reset формы'
        );
        $this->assertStringContainsString('id="createTeamModalLabel">Создание группы</h5>', $html);
        $this->assertStringContainsString('id="editTeamModalLabel">Редактирование группы</h5>', $html);
    }

    public function test_create_success_uses_toast_and_table_reload_instead_of_success_modal(): void
    {
        $js = (string) file_get_contents(resource_path('views/includes/modal/createTeam.blade.php'));

        $submitPos = strpos($js, "teamForm.addEventListener('submit'");
        $this->assertNotFalse($submitPos);
        $success = substr($js, $submitPos);

        $this->assertStringContainsString('e.preventDefault()', $success);
        $this->assertStringContainsString('fetch(', $success);
        $this->assertStringContainsString(
            "window.showToast((data && data.message) ? data.message : 'Группа создана успешно', 'success')",
            $success,
            'UX-баг: после create должна быть всплывайка, не success-модалка'
        );
        $this->assertStringContainsString('createModalInstance.hide()', $success);
        $this->assertStringContainsString('$(\'#teams-table\').DataTable().ajax.reload(null, false)', $success);
        $this->assertStringContainsString('teamForm.reset()', $success);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.setValues($createTrainersSelect, [])', $success);
        $this->assertStringNotContainsString('showSuccessModal', $success);
        $this->assertStringNotContainsString('window.location.reload()', $success);
        $this->assertStringNotContainsString('location.reload()', $success);

        $errorPos = strpos($success, 'status === 422');
        $unrelatedPos = strpos($success, 'if (!ok) {');
        $this->assertNotFalse($errorPos);
        $this->assertNotFalse($unrelatedPos);
        $this->assertGreaterThan($errorPos, $unrelatedPos);
        $error = substr($success, $errorPos, $unrelatedPos - $errorPos);
        $this->assertStringContainsString('is-invalid', $error);
        $this->assertStringContainsString('errors.title', $error);
        $this->assertStringNotContainsString('window.showToast', $error, '422 — ошибки под полями, не toast успеха');
    }

    public function test_edit_success_uses_toast_and_table_reload_instead_of_success_modal(): void
    {
        $js = (string) file_get_contents(resource_path('views/includes/modal/editTeam.blade.php'));

        $updatePos = strpos($js, "$('#update-team-btn').on('click'");
        $deletePos = strpos($js, 'function deleteTeam()');
        $this->assertNotFalse($updatePos);
        $this->assertNotFalse($deletePos);
        $this->assertGreaterThan($updatePos, $deletePos);
        $form = substr($js, $updatePos, $deletePos - $updatePos);

        $this->assertStringContainsString('$.ajax', $form);
        $this->assertStringContainsString("'Accept': 'application/json'", $form);
        $this->assertStringContainsString("type: 'PATCH'", $form);
        $this->assertStringContainsString(
            "window.showToast(response.message || 'Группа успешно обновлена', 'success')",
            $form,
            'UX-баг: PATCH карточки звал showSuccessModal(..., 1) и reload'
        );
        $this->assertStringContainsString("$('#editTeamModal').modal('hide')", $form);
        $this->assertStringContainsString('$(\'#teams-table\').DataTable().ajax.reload(null, false)', $form);
        $this->assertStringNotContainsString('showSuccessModal("Редактирование группы"', $form);
        $this->assertStringNotContainsString('location.reload()', $form);

        $this->assertStringContainsString('xhr.status === 422', $form);
        $this->assertStringContainsString('is-invalid', $form);
        $errorPos = strpos($form, 'error: function(xhr)');
        $this->assertNotFalse($errorPos);
        $error = substr($form, $errorPos);
        $this->assertStringNotContainsString(
            "window.showToast(response.message || 'Группа успешно обновлена', 'success')",
            $error,
            '422 не должен показывать toast успеха'
        );
    }

    public function test_delete_success_uses_toast_after_confirm_hides_instead_of_success_modal(): void
    {
        $js = (string) file_get_contents(resource_path('views/includes/modal/editTeam.blade.php'));

        $deletePos = strpos($js, 'function deleteTeam()');
        $this->assertNotFalse($deletePos);
        $chunk = substr($js, $deletePos);

        $this->assertStringContainsString("window.showToast('Группа успешно удалена.', 'success')", $chunk);
        $this->assertStringContainsString(
            "confirmEl.addEventListener('hidden.bs.modal', showDeletedToast, { once: true })",
            $chunk
        );
        $this->assertStringContainsString('$(\'#teams-table\').DataTable().ajax.reload(null, false)', $chunk);
        $this->assertStringContainsString("$('#editTeamModal').modal('hide')", $chunk);
        $this->assertStringNotContainsString('showSuccessModal("Удаление группы"', $chunk);
        $this->assertStringNotContainsString('location.reload()', $chunk);
    }

    public function test_both_edit_open_paths_share_one_handler_and_do_not_submit(): void
    {
        $page = (string) file_get_contents(resource_path('views/admin/team.blade.php'));
        $edit = (string) file_get_contents(resource_path('views/includes/modal/editTeam.blade.php'));

        $this->assertStringContainsString("linkClass: 'edit-team-link'", $page, 'Клик по названию открывает edit');
        $this->assertStringContainsString('class="btn btn-sm btn-outline-primary edit-team-link"', $page, 'Кнопка «Редактировать» — тот же класс');

        $this->assertSame(
            1,
            substr_count($edit, "$(document).on('click', '.edit-team-link'"),
            'Два HTML-триггера и один GET-хендлер — иначе дубликат снова вернёт модалку'
        );
        $this->assertSame(1, substr_count($edit, "$('#update-team-btn').on('click'"));
        $this->assertSame(1, substr_count($edit, 'function deleteTeam()'));

        $openPos = strpos($edit, "$(document).on('click', '.edit-team-link'");
        $updatePos = strpos($edit, "$('#update-team-btn').on('click'");
        $this->assertNotFalse($openPos);
        $this->assertNotFalse($updatePos);
        $open = substr($edit, $openPos, $updatePos - $openPos);

        $this->assertStringContainsString("type: 'GET'", $open);
        $this->assertStringContainsString('$(\'#editTeamModal\').modal(\'show\')', $open);
        $this->assertStringNotContainsString('showSuccessModal', $open, 'Открытие карточки не показывает success-модалку');
        $this->assertStringNotContainsString('window.showToast', $open, 'Открытие — только гидратация, submit в update-team-btn');
        $this->assertStringNotContainsString("type: 'PATCH'", $open);
        $this->assertStringNotContainsString("type: 'DELETE'", $open);
    }

    public function test_create_form_reset_after_success_restores_empty_title_and_does_not_leave_trainers(): void
    {
        $js = (string) file_get_contents(resource_path('views/includes/modal/createTeam.blade.php'));
        $toastPos = strpos($js, "window.showToast((data && data.message) ? data.message : 'Группа создана успешно', 'success')");
        $this->assertNotFalse($toastPos);
        $successTail = substr($js, $toastPos - 900, 900);

        $this->assertStringContainsString('teamForm.reset()', $successTail);
        $this->assertStringContainsString(
            'KidsCrmGenericMultiselectSelect2.setValues($createTrainersSelect, [])',
            $successTail,
            'После create мультиселект тренеров сбрасывается — как при первом открытии'
        );
    }

    public function test_without_trainers_view_create_modal_does_not_force_trainer_select(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->grantPermissionsTo($actor, ['groups.view']);
        $this->actingAs($actor);

        $html = $this->get(route('admin.team.index'))->assertOk()->getContent();
        $start = strpos($html, 'id="createTeamModal"');
        $end = strpos($html, 'id="editTeamModal"');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringNotContainsString('name="trainer_profile_ids[]"', $chunk);
        $this->assertStringContainsString('name="title"', $chunk);
    }

    public function test_locations_page_does_not_get_team_create_toast_copy(): void
    {
        $locations = (string) file_get_contents(resource_path('views/admin/locations/index.blade.php'));

        $this->assertStringNotContainsString('Группа создана успешно', $locations);
        $this->assertStringNotContainsString('Группа успешно обновлена', $locations);
        $this->assertStringContainsString("window.showToast(data.message || 'Объект создан', 'success')", $locations);
    }
}
