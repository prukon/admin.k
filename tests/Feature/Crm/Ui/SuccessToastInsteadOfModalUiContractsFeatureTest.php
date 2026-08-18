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
        $this->assertStringContainsString('z-index: 1090', $html);
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
    }

    public function test_each_listed_success_trigger_uses_shared_toast_instead_of_success_modal(): void
    {
        $cases = [
            'student-delete' => [
                'path'  => resource_path('views/includes/modal/editUser.blade.php'),
                'toast' => "window.showToast('Пользователь успешно удален.', 'success')",
                'absent'=> 'showSuccessModal("Удаление пользователя"',
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
            'location-delete' => [
                'path'  => resource_path('views/admin/locations/index.blade.php'),
                'toast' => "window.showToast('Объект успешно удалён.', 'success')",
                'absent'=> "showSuccessModal('Удаление объекта'",
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
                'Дополнительный платеж успешно создан.',
                $content,
                "{$label}: fallback toast после create"
            );
            $this->assertStringContainsString('window.showToast', $content, $label);
            $this->assertStringNotContainsString('showSuccessModal', $content, $label);
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
            'showSuccessModal("Редактирование пользователя"',
            $editUser,
            'Сохранение карточки ученика не в списке toast'
        );
        $this->assertStringNotContainsString(
            "showSuccessModal('Отправка пароля'",
            $editUser,
            'Welcome-пароль по почте — toast, не success-модалка'
        );
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
