<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Ui;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Ui\Concerns\SuccessToastInsteadOfModalTestHelpers;

/**
 * UX-баг: success-модалка требовала лишний клик «OK».
 * После фикса успех без reload — всплывайка #kidsMainToast / window.showToast.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class SuccessToastInsteadOfModalUiContractsFeatureTest extends CrmTestCase
{
    use SuccessToastInsteadOfModalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_users_page_renders_hidden_empty_toast_on_first_open_outside_edit_modal(): void
    {
        $this->asAdminWith(['users.view']);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringContainsString('id="kidsMainToastBody"', $html);
        $this->assertStringContainsString('window.showToast', $html);
        $this->assertStringContainsString('z-index: 4050', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="kidsMainToast"[^>]*\bshow\b/',
            $html,
            'При первом открытии страницы всплывайка не должна быть уже показана'
        );
        $this->assertMatchesRegularExpression(
            '/id="kidsMainToastBody"><\/div>/',
            $html,
            'Тело всплывайки при первом открытии пустое — текст появляется только после успеха'
        );

        $modalPos = strpos($html, 'id="editUserModal"');
        $layoutSuccessJsPos = strpos($html, 'function showSuccessModal');
        $toastPos = strpos($html, 'id="kidsMainToast"');
        $this->assertNotFalse($modalPos);
        $this->assertNotFalse($layoutSuccessJsPos);
        $this->assertNotFalse($toastPos);
        $this->assertLessThan($layoutSuccessJsPos, $modalPos);
        $this->assertLessThan(
            $toastPos,
            $layoutSuccessJsPos,
            'Toast в footer layout после successModal, не внутри #editUserModal: повторное открытие карточки не пересобирает всплывайку'
        );
        $this->assertStringContainsString(
            "window.showToast(response.message || 'Клиент успешно обновлён.', 'success')",
            $html
        );
        $this->assertStringContainsString(
            "window.showToast((response && response.message) ? response.message : 'Клиент успешно создан.', 'success')",
            $html
        );
        $this->assertStringNotContainsString('showSuccessModal("Редактирование клиента"', $html);
        $this->assertStringNotContainsString('showSuccessModal("Создание клиента"', $html);
    }

    public function test_shared_toast_partial_sits_above_confirm_and_error_modals(): void
    {
        $toast = (string) file_get_contents(resource_path('views/partials/ui/main-toast.blade.php'));

        $this->assertStringContainsString('z-index: 4050', $toast);
        $this->assertStringNotContainsString('z-index: 1090', $toast);
        $this->assertStringContainsString('document.body.appendChild(wrap)', $toast);
        $this->assertStringContainsString('existing.dispose()', $toast);
        $this->assertStringContainsString('autohide: true', $toast);
    }

    public function test_location_delete_shows_toast_after_confirm_modal_is_hidden(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/locations/index.blade.php'));
        $deletePos = strpos($js, 'function deleteLocation()');
        $this->assertNotFalse($deletePos);
        $chunk = substr($js, $deletePos, 2800);

        $this->assertStringContainsString("window.showToast('Объект успешно удалён.', 'success')", $chunk);
        $this->assertStringContainsString(
            "confirmEl.addEventListener('hidden.bs.modal', showDeletedToast, { once: true })",
            $chunk
        );
        $this->assertStringContainsString("'Accept': 'application/json'", $chunk);

        $toastPos = strpos($chunk, "window.showToast('Объект успешно удалён.', 'success')");
        $listenerPos = strpos($chunk, "confirmEl.addEventListener('hidden.bs.modal', showDeletedToast");
        $this->assertNotFalse($toastPos);
        $this->assertNotFalse($listenerPos);
        $this->assertLessThan(
            $listenerPos,
            $toastPos,
            'showDeletedToast с текстом тоста объявляется до слушателя hidden.bs.modal'
        );
    }

    public function test_location_create_and_edit_show_toast_after_ajax_success(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/locations/index.blade.php'));

        $createPos = strpos($js, "document.getElementById('locationCreateSubmit')");
        $this->assertNotFalse($createPos);
        $createChunk = substr($js, $createPos, 1600);
        $this->assertStringContainsString("window.showToast(data.message || 'Объект создан', 'success')", $createChunk);
        $this->assertStringContainsString('reloadLocationsTable()', $createChunk);
        $this->assertStringContainsString("if (typeof window.showToast === 'function')", $createChunk);

        $editPos = strpos($js, "document.getElementById('locationEditSubmit')");
        $this->assertNotFalse($editPos);
        $editEnd = strpos($js, 'function deleteLocation()', $editPos);
        $this->assertNotFalse($editEnd);
        $editChunk = substr($js, $editPos, $editEnd - $editPos);
        $this->assertStringContainsString("window.showToast(data.message || 'Объект обновлён', 'success')", $editChunk);
        $this->assertStringContainsString('reloadLocationsTable()', $editChunk);
        $this->assertStringContainsString("if (typeof window.showToast === 'function')", $editChunk);
        $this->assertStringNotContainsString("showSuccessModal('Редактирование объекта'", $editChunk);
    }

    public function test_team_delete_shows_toast_after_confirm_modal_is_hidden(): void
    {
        $js = (string) file_get_contents(resource_path('views/includes/modal/editTeam.blade.php'));
        $deletePos = strpos($js, 'function deleteTeam()');
        $this->assertNotFalse($deletePos);
        $chunk = substr($js, $deletePos, 2800);

        $this->assertStringContainsString("window.showToast('Группа успешно удалена.', 'success')", $chunk);
        $this->assertStringContainsString(
            "confirmEl.addEventListener('hidden.bs.modal', showDeletedToast, { once: true })",
            $chunk
        );
        $this->assertStringContainsString("'Accept': 'application/json'", $chunk);
        $this->assertStringNotContainsString('showSuccessModal("Удаление группы"', $chunk);

        $toastPos = strpos($chunk, "window.showToast('Группа успешно удалена.', 'success')");
        $listenerPos = strpos($chunk, "confirmEl.addEventListener('hidden.bs.modal', showDeletedToast");
        $this->assertNotFalse($toastPos);
        $this->assertNotFalse($listenerPos);
        $this->assertLessThan(
            $listenerPos,
            $toastPos,
            'showDeletedToast с текстом тоста объявляется до слушателя hidden.bs.modal'
        );
    }

    public function test_team_create_and_edit_show_toast_after_ajax_success(): void
    {
        $create = (string) file_get_contents(resource_path('views/includes/modal/createTeam.blade.php'));
        $createPos = strpos($create, 'function createTeam()');
        $this->assertNotFalse($createPos);
        $createChunk = substr($create, $createPos);
        $this->assertStringContainsString(
            "window.showToast((data && data.message) ? data.message : 'Группа создана успешно', 'success')",
            $createChunk
        );
        $this->assertStringContainsString('$(\'#teams-table\').DataTable().ajax.reload(null, false)', $createChunk);
        $this->assertStringContainsString("if (typeof window.showToast === 'function')", $createChunk);
        $this->assertStringContainsString('createModalInstance.hide()', $createChunk);
        $this->assertStringNotContainsString('showSuccessModal', $createChunk);

        $edit = (string) file_get_contents(resource_path('views/includes/modal/editTeam.blade.php'));
        $editPos = strpos($edit, "$('#update-team-btn').on('click'");
        $this->assertNotFalse($editPos);
        $editEnd = strpos($edit, 'function deleteTeam()', $editPos);
        $this->assertNotFalse($editEnd);
        $editChunk = substr($edit, $editPos, $editEnd - $editPos);
        $this->assertStringContainsString(
            "window.showToast(response.message || 'Группа успешно обновлена', 'success')",
            $editChunk
        );
        $this->assertStringContainsString('$(\'#teams-table\').DataTable().ajax.reload(null, false)', $editChunk);
        $this->assertStringContainsString("if (typeof window.showToast === 'function')", $editChunk);
        $this->assertStringContainsString("$('#editTeamModal').modal('hide')", $editChunk);
        $this->assertStringNotContainsString('showSuccessModal("Редактирование группы"', $editChunk);
    }

    public function test_each_listed_success_trigger_uses_shared_toast_instead_of_success_modal(): void
    {
        $cases = [
            'student-create' => [
                'path'  => resource_path('views/includes/modal/createUser.blade.php'),
                'toast' => "window.showToast((response && response.message) ? response.message : 'Клиент успешно создан.', 'success')",
                'absent'=> 'showSuccessModal',
            ],
            'student-edit' => [
                'path'  => resource_path('views/includes/modal/editUser.blade.php'),
                'toast' => "window.showToast(response.message || 'Клиент успешно обновлён.', 'success')",
                'absent'=> 'showSuccessModal("Редактирование клиента"',
            ],
            'student-delete' => [
                'path'  => resource_path('views/includes/modal/editUser.blade.php'),
                'toast' => "window.showToast('Клиент успешно удален.', 'success')",
                'absent'=> 'showSuccessModal("Удаление клиента"',
            ],
            'trainer-create' => [
                'path'  => resource_path('views/admin/trainers/index.blade.php'),
                'toast' => "window.showToast(data.message || 'Тренер успешно создан.', 'success')",
                'absent'=> "showSuccessModal(\n                        'Создание тренера'",
            ],
            'trainer-edit' => [
                'path'  => resource_path('views/admin/trainers/index.blade.php'),
                'toast' => "window.showToast(data.message || 'Тренер успешно обновлён.', 'success')",
                'absent'=> "showSuccessModal(\n                        'Редактирование тренера'",
            ],
            'trainer-delete' => [
                'path'  => resource_path('views/admin/trainers/index.blade.php'),
                'toast' => "window.showToast(data.message || 'Тренер успешно удалён.', 'success')",
                'absent'=> "showSuccessModal(\n                                'Удаление тренера'",
            ],
            'admin-create' => [
                'path'  => resource_path('views/admin/role_staff/index.blade.php'),
                'toast' => "window.showToast(result.data.message || 'Пользователь создан', 'success')",
                'absent'=> "showSuccessModal(\n                            'Создание пользователя'",
            ],
            'legal-entity-create' => [
                'path'  => resource_path('views/admin/legal-entities/index.blade.php'),
                'toast' => "window.showToast(data.message || 'Юр. лицо создано', 'success')",
                'absent'=> 'showSuccessModal',
            ],
            'legal-entity-edit' => [
                'path'  => resource_path('views/admin/legal-entities/index.blade.php'),
                'toast' => "window.showToast(data.message || 'Юр. лицо обновлено', 'success')",
                'absent'=> 'showSuccessModal',
            ],
            'location-create' => [
                'path'  => resource_path('views/admin/locations/index.blade.php'),
                'toast' => "window.showToast(data.message || 'Объект создан', 'success')",
                'absent'=> "showSuccessModal('Создание объекта'",
            ],
            'location-edit' => [
                'path'  => resource_path('views/admin/locations/index.blade.php'),
                'toast' => "window.showToast(data.message || 'Объект обновлён', 'success')",
                'absent'=> "showSuccessModal('Редактирование объекта'",
            ],
            'location-delete' => [
                'path'  => resource_path('views/admin/locations/index.blade.php'),
                'toast' => "window.showToast('Объект успешно удалён.', 'success')",
                'absent'=> "showSuccessModal('Удаление объекта'",
            ],
            'team-create' => [
                'path'  => resource_path('views/includes/modal/createTeam.blade.php'),
                'toast' => "window.showToast((data && data.message) ? data.message : 'Группа создана успешно', 'success')",
                'absent'=> 'showSuccessModal',
            ],
            'team-edit' => [
                'path'  => resource_path('views/includes/modal/editTeam.blade.php'),
                'toast' => "window.showToast(response.message || 'Группа успешно обновлена', 'success')",
                'absent'=> 'showSuccessModal("Редактирование группы"',
            ],
            'team-delete' => [
                'path'  => resource_path('views/includes/modal/editTeam.blade.php'),
                'toast' => "window.showToast('Группа успешно удалена.', 'success')",
                'absent'=> 'showSuccessModal("Удаление группы"',
            ],
            'trainer-type-save' => [
                'path'  => public_path('js/trainer-types.js'),
                'toast' => "window.showToast(data.message || 'Тип тренера сохранён', 'success')",
                'absent'=> "showSuccessModal('Типы тренеров'",
            ],
            'trainer-type-delete' => [
                'path'  => public_path('js/trainer-types.js'),
                'toast' => "window.showToast(data.message || 'Тип тренера удалён', 'success')",
                'absent'=> "showSuccessModal('Типы тренеров'",
            ],
            'lead-create-client' => [
                'path'  => resource_path('views/admin/school-leads/tabs/leads.blade.php'),
                'toast' => "window.showToast(message || 'Клиент создан.', 'success')",
                'absent'=> "showSuccessModal('Создание клиента'",
            ],
            'monthly-one-user' => [
                'path'  => resource_path('js/settings-prices.js'),
                'toast' => "window.showToast('Изменения сохранены.', 'success')",
                'absent'=> 'showSuccessModal("Редактирование цены"',
            ],
            'monthly-right-apply' => [
                'path'  => resource_path('js/settings-prices.js'),
                'toast' => "window.showToast('Цены ученикам в выбранной группе успешно обновлены.', 'success')",
                'absent'=> 'showSuccessModal("Установка цен в одной группе"',
            ],
            'create-role' => [
                'path'  => resource_path('views/admin/setting/rule.blade.php'),
                'toast' => "window.showToast('Роль успешно создана.', 'success')",
                'absent'=> 'showSuccessModal("Создание роли"',
            ],
            'delete-role' => [
                'path'  => resource_path('views/admin/setting/rule.blade.php'),
                'toast' => "window.showToast('Роль успешно удалена.', 'success')",
                'absent'=> 'showSuccessModal("Удаление роли"',
            ],
            'account-own-password' => [
                'path'  => resource_path('views/account/users.blade.php'),
                'toast' => "window.showToast('Пароль успешно изменен.', 'success')",
                'absent'=> 'showSuccessModal("Изменение пароля"',
            ],
            'student-password' => [
                'path'  => resource_path('views/includes/modal/editUser.blade.php'),
                'toast' => "window.showToast(message, type)",
                'absent'=> "showSuccessModal('Обновление пароля'",
            ],
            'trainer-password' => [
                'path'  => resource_path('views/admin/trainers/index.blade.php'),
                'toast' => "window.showToast(message, type)",
                'absent'=> "showSuccessModal('Обновление пароля'",
            ],
            'student-send-password' => [
                'path'  => resource_path('views/includes/modal/editUser.blade.php'),
                'toast' => "window.showToast(msg, 'success')",
                'absent'=> "showSuccessModal('Отправка пароля'",
            ],
        ];

        foreach ($cases as $label => $case) {
            $this->assertFileExists($case['path'], $label);
            $content = (string) file_get_contents($case['path']);
            $this->assertStringContainsString($case['toast'], $content, $label.': успех должен звать общую всплывайку');
            $this->assertStringNotContainsString($case['absent'], $content, $label.': success-модалка — UX-баг лишнего клика OK');
        }

        $ruleJs = (string) file_get_contents(resource_path('views/admin/setting/rule.blade.php'));
        $this->assertStringContainsString('function removeRoleColumnFromPermissionTables', $ruleJs);
        $this->assertStringContainsString('function removeRoleFromRolesTable', $ruleJs);
        $this->assertStringNotContainsString('location.reload();', $ruleJs);
    }

    public function test_both_custom_payment_js_paths_use_toast_instead_of_success_modal(): void
    {
        $paths = [
            'vite-source' => resource_path('js/setting-prices-custom-payments.js'),
            'public-js'   => public_path('js/setting-prices-custom-payments.js'),
        ];

        foreach ($paths as $label => $path) {
            $this->assertFileExists($path, $label);
            $content = (string) file_get_contents($path);
            $this->assertStringContainsString(
                "window.showToast('Дополнительный платеж успешно создан.', 'success')",
                $content,
                "{$label}: create — общий #kidsMainToast"
            );
            $this->assertStringContainsString(
                "window.showToast('Изменения сохранены.', 'success')",
                $content,
                "{$label}: update — общий #kidsMainToast, без alert/#priceToast"
            );
            $this->assertStringNotContainsString('showSuccessModal', $content, $label);
            $this->assertStringNotContainsString('priceToast', $content, $label);
        }
    }

    public function test_school_leads_create_client_success_uses_shared_toast_and_skips_local_toast(): void
    {
        app(\App\Services\PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
        $this->asAdminWith(['schoolLeads.view']);

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringContainsString('id="mainToast"', $html, 'Локальный toast заявок остаётся для других действий');

        $js = (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));
        $fnPos = strpos($js, 'function showCreateClientResultModal');
        $this->assertNotFalse($fnPos);
        $chunk = substr($js, $fnPos, 900);

        $this->assertStringContainsString("if (typeof window.showToast === 'function')", $chunk);
        $this->assertStringContainsString("window.showToast(message || 'Клиент создан.', 'success')", $chunk);
        $windowPos = strpos($chunk, 'window.showToast');
        $localPos = strpos($chunk, "showToast(message || 'Клиент создан.', 'success')");
        $this->assertNotFalse($windowPos);
        $this->assertNotFalse($localPos);
        $this->assertLessThan($localPos, $windowPos);
        $this->assertStringContainsString('return;', substr($chunk, $windowPos, 220));
        $this->assertStringNotContainsString("showSuccessModal('Создание клиента'", $chunk);
    }

    public function test_actions_outside_the_list_still_use_success_modal(): void
    {
        $prices = (string) file_get_contents(resource_path('js/settings-prices.js'));
        $this->assertStringContainsString(
            'showSuccessModal("Установка тарифов всем группам", "Тарифы группам успешно обновлены.", 1)',
            $prices,
            '«Применить» слева (всем группам) по-прежнему модалка с reload — не в списке toast'
        );

        $editUser = (string) file_get_contents(resource_path('views/includes/modal/editUser.blade.php'));
        $this->assertStringContainsString(
            "window.showToast(response.message || 'Клиент успешно обновлён.', 'success')",
            $editUser,
            'Сохранение карточки ученика — toast, не success-модалка'
        );
        $this->assertStringNotContainsString(
            'showSuccessModal("Редактирование клиента"',
            $editUser,
            'Сохранение карточки ученика больше не зовёт success-модалку'
        );
        $this->assertStringNotContainsString(
            "showSuccessModal('Отправка пароля'",
            $editUser,
            'Welcome-пароль по почте — toast, не success-модалка'
        );

        $createUser = (string) file_get_contents(resource_path('views/includes/modal/createUser.blade.php'));
        $this->assertStringContainsString(
            "window.showToast((response && response.message) ? response.message : 'Клиент успешно создан.', 'success')",
            $createUser,
            '«Добавить» клиента — toast, не success-модалка'
        );
        $this->assertStringNotContainsString('showSuccessModal', $createUser);
        $this->assertStringNotContainsString('window.location.reload()', $createUser);
    }

    public function test_reopening_create_or_edit_modal_does_not_rebuild_shared_toast_markup(): void
    {
        $this->asAdminWith(['trainers.view']);

        $html = $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'id="kidsMainToast"'),
            'Один toast в layout: открытие create/edit/повторное открытие не должно клонировать разметку'
        );
        $this->assertStringContainsString('id="trainerCreateModal"', $html);
        $this->assertStringContainsString('id="trainerEditModal"', $html);

        $createPos = strpos($html, 'id="trainerCreateModal"');
        $layoutSuccessJsPos = strpos($html, 'function showSuccessModal');
        $toastPos = strpos($html, 'id="kidsMainToast"');
        $this->assertNotFalse($createPos);
        $this->assertNotFalse($layoutSuccessJsPos);
        $this->assertNotFalse($toastPos);
        $this->assertLessThan($layoutSuccessJsPos, $createPos);
        $this->assertLessThan(
            $toastPos,
            $layoutSuccessJsPos,
            'Toast не внутри create-модалки тренера — иначе сбросится при пересборке формы'
        );
    }
}
