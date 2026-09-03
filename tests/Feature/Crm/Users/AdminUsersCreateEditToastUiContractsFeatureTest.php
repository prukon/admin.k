<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

/**
 * UX-баг прода: после create/edit ученика открывалась success-модалка и reload страницы.
 * После фикса — toast #kidsMainToast, закрытие модалки, DataTables ajax.reload без F5.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUsersCreateEditToastUiContractsFeatureTest extends AdminUsersCreateEditToastTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAsUsersViewer();
    }

    public function test_users_page_first_open_shows_hidden_empty_toast_outside_create_and_edit_modals(): void
    {
        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html), 'Первое открытие /admin/users не должно быть пустым 200');
        $this->assertStringContainsString('id="createUserModal"', $html);
        $this->assertStringContainsString('id="editUserModal"', $html);
        $this->assertStringContainsString('data-bs-target="#createUserModal"', $html);
        $this->assertStringContainsString('id="create-user-form"', $html);
        $this->assertStringContainsString('id="edit-user-form"', $html);
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

        $createPos = strpos($html, 'id="createUserModal"');
        $editPos = strpos($html, 'id="editUserModal"');
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

        $this->assertStringNotContainsString('showSuccessModal("Редактирование клиента"', $html);
        $this->assertStringNotContainsString('window.location.reload()', $html);
    }

    public function test_first_open_create_modal_has_welcome_checkbox_checked_by_default(): void
    {
        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="create-send-welcome-email"[^>]*\bchecked\b/',
            $html,
            'На первом HTML чекбокс «Отправить письмо» включён — после успеха JS возвращает тот же дефолт'
        );
        $this->assertStringContainsString('data-bs-backdrop="static"', $html);
        $this->assertStringContainsString('id="createUserModalLabel">Создание клиента</h5>', $html);
        $this->assertStringContainsString('id="editUserModalLabel">Редактирование клиента</h5>', $html);
    }

    public function test_create_success_uses_toast_and_table_reload_instead_of_success_modal(): void
    {
        $js = (string) file_get_contents(resource_path('views/includes/modal/createUser.blade.php'));

        $successPos = strpos($js, 'success: function (response)');
        $errorPos = strpos($js, 'error: function (xhr)');
        $this->assertNotFalse($successPos);
        $this->assertNotFalse($errorPos);
        $this->assertGreaterThan($successPos, $errorPos);
        $success = substr($js, $successPos, $errorPos - $successPos);

        $leadsIfPos = strpos($success, "\$form.data('success-handler') === 'school-leads-table'");
        $this->assertNotFalse($leadsIfPos, 'Ветка заявок должна остаться отдельной — иначе toast вкладки Клиенты уедет в лиды');
        $returnPos = strpos($success, 'return;', $leadsIfPos);
        $this->assertNotFalse($returnPos);
        $leadsBranch = substr($success, $leadsIfPos, $returnPos - $leadsIfPos);
        $defaultBranch = substr($success, $returnPos);

        $this->assertStringNotContainsString('window.showToast', $leadsBranch);
        $this->assertStringNotContainsString('users-table', $leadsBranch);
        $this->assertStringContainsString('onSchoolLeadUserCreated', $leadsBranch);

        $this->assertStringContainsString(
            "window.showToast((response && response.message) ? response.message : 'Клиент успешно создан.', 'success')",
            $defaultBranch,
            'UX-баг: после create должна быть всплывайка, не success-модалка'
        );
        $this->assertStringContainsString("createModalInstance.hide()", $defaultBranch);
        $this->assertStringContainsString('$(\'#users-table\').DataTable().ajax.reload(null, false)', $defaultBranch);
        $this->assertStringContainsString('$(\'#create-send-welcome-email\').prop(\'checked\', true)', $defaultBranch);
        $this->assertStringContainsString('$form[0]?.reset?.()', $defaultBranch);
        $this->assertStringNotContainsString('showSuccessModal', $success);
        $this->assertStringNotContainsString('window.location.reload()', $success);
        $this->assertStringNotContainsString('location.reload()', $defaultBranch);

        $error = substr($js, $errorPos, 2500);
        $this->assertStringContainsString('xhr.status === 422', $error);
        $this->assertStringContainsString('is-invalid', $error);
        $this->assertStringContainsString('invalid-feedback', $error);
        $this->assertStringNotContainsString('window.showToast', $error, '422 — ошибки под полями, не toast успеха');
    }

    public function test_edit_success_uses_toast_and_table_reload_instead_of_success_modal(): void
    {
        $js = (string) file_get_contents(resource_path('views/includes/modal/editUser.blade.php'));

        $formPos = strpos($js, 'function editUserForm()');
        $deletePos = strpos($js, 'function deleteUser()');
        $this->assertNotFalse($formPos);
        $this->assertNotFalse($deletePos);
        $this->assertGreaterThan($formPos, $deletePos);
        $form = substr($js, $formPos, $deletePos - $formPos);

        $this->assertStringContainsString('e.preventDefault()', $form);
        $this->assertStringContainsString('$.ajax', $form);
        $this->assertStringContainsString("'Accept': 'application/json'", $form);
        $this->assertStringContainsString("method: 'PATCH'", $form);
        $this->assertStringContainsString(
            "window.showToast(response.message || 'Клиент успешно обновлён.', 'success')",
            $form,
            'UX-баг: PATCH карточки звал showSuccessModal(..., 1) и reload'
        );
        $this->assertStringContainsString("bootstrap.Modal.getInstance(document.getElementById('editUserModal'))?.hide()", $form);
        $this->assertStringContainsString('$(\'#users-table\').DataTable().ajax.reload(null, false)', $form);
        $this->assertStringNotContainsString('showSuccessModal("Редактирование клиента"', $form);
        $this->assertStringNotContainsString('location.reload()', $form);

        $this->assertStringContainsString('xhr.status === 422', $form);
        $this->assertStringContainsString('is-invalid', $form);
        $errorPos = strpos($form, 'error: function (xhr)');
        $this->assertNotFalse($errorPos);
        $error = substr($form, $errorPos);
        $this->assertStringNotContainsString(
            "window.showToast(response.message || 'Клиент успешно обновлён.', 'success')",
            $error,
            '422 не должен показывать toast успеха'
        );
    }

    public function test_both_edit_open_paths_do_not_reset_success_to_modal_and_do_not_submit(): void
    {
        $js = (string) file_get_contents(resource_path('views/includes/modal/editUser.blade.php'));

        $link2Pos = strpos($js, 'function editUserLink2()');
        $linkPos = strpos($js, 'function editUserLink()');
        $formPos = strpos($js, 'function editUserForm()');
        $this->assertNotFalse($link2Pos);
        $this->assertNotFalse($linkPos);
        $this->assertNotFalse($formPos);
        $this->assertLessThan($linkPos, $link2Pos);
        $this->assertLessThan($formPos, $linkPos);

        $link2 = substr($js, $link2Pos, $linkPos - $link2Pos);
        $link = substr($js, $linkPos, $formPos - $linkPos);

        foreach (['editUserLink2' => $link2, 'editUserLink' => $link] as $label => $body) {
            $this->assertStringContainsString("url: `/admin/users/\${userId}/edit`", $body, $label);
            $this->assertStringContainsString('$(\'#editUserModal\').modal(\'show\')', $body, $label);
            $this->assertStringNotContainsString('showSuccessModal', $body, "{$label} не должен показывать success-модалку при открытии");
            $this->assertStringNotContainsString('location.reload()', $body, $label);
            $this->assertStringNotContainsString('window.showToast', $body, "{$label} — только гидратация, submit в editUserForm");
            $this->assertStringContainsString("method: 'GET'", $body, $label);
            $this->assertStringNotContainsString("method: 'PATCH'", $body, "{$label} не отправляет PATCH — submit в editUserForm");
        }

        $form = substr($js, $formPos, 3500);
        $this->assertStringContainsString('$.ajax', $form);
        $this->assertSame(
            1,
            substr_count($js, 'function editUserForm()'),
            'Два пути открытия и один submit — иначе дубликат снова вернёт модалку'
        );
    }

    public function test_create_submit_prevents_native_post_and_sends_json_accept(): void
    {
        $js = (string) file_get_contents(resource_path('views/includes/modal/createUser.blade.php'));

        $this->assertStringContainsString('$createUserFormRoot.on(\'submit\'', $js);
        $this->assertStringContainsString('e.preventDefault()', $js);
        $this->assertStringContainsString('$.ajax', $js);
        $this->assertStringContainsString("'Accept': 'application/json'", $js);
    }

    public function test_trainers_page_does_not_get_client_create_toast_copy(): void
    {
        $trainers = (string) file_get_contents(resource_path('views/admin/trainers/index.blade.php'));

        $this->assertStringNotContainsString('Клиент успешно создан.', $trainers);
        $this->assertStringNotContainsString('Клиент успешно обновлён.', $trainers);
        $this->assertStringContainsString("window.showToast(data.message || 'Тренер успешно создан.', 'success')", $trainers);
    }
}
