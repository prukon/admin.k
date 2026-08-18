<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\AdminUserPasswordUpdateTestHelpers;

/**
 * Разметка и правила UX смены пароля: всплывайка, скрытый блок при первом открытии,
 * @can, три JS-пути (админы / ученики / тренеры) не шлют повтор того же пароля.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AdminUserPasswordUpdateUiContractsFeatureTest extends CrmTestCase
{
    use AdminUserPasswordUpdateTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureArrayCacheForThrottle();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_administrators_page_shows_toast_and_hidden_password_block_on_first_open(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);
        $this->grantPasswordUpdate($this->user);

        $html = $this->get(route('admin.administrators.index'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringContainsString('id="kidsMainToastBody"', $html);
        $this->assertStringContainsString('window.showToast', $html);
        $this->assertStringContainsString('id="role-staff-change-password-btn"', $html);
        $this->assertStringContainsString('id="role-staff-change-pass-wrap"', $html);
        $this->assertMatchesRegularExpression(
            '/id="role-staff-change-pass-wrap"[^>]*style="display:\s*none;?"/',
            $html,
            'Блок смены пароля при первом открытии карточки должен быть скрыт'
        );
        $this->assertStringContainsString('id="role-staff-new-password"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
        $this->assertStringContainsString('id="role-staff-apply-password-btn"', $html);
        $this->assertStringContainsString('passwordUpdateUrlTemplate', $html);
    }

    public function test_administrators_page_hides_password_block_without_permission(): void
    {
        $actor = $this->createUserWithoutPermission('users.password.update', $this->partner);
        $this->actingAs($actor);
        $this->grantStaffSectionAccess($actor);

        $html = $this->get(route('admin.administrators.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringNotContainsString('id="role-staff-apply-password-btn"', $html);
        $this->assertStringNotContainsString('id="role-staff-new-password"', $html);
    }

    public function test_users_page_disables_change_password_button_without_permission(): void
    {
        $actor = $this->createUserWithoutPermission('users.password.update', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="change-password-btn"', $html);
        $this->assertStringContainsString('opacity-50 pe-none', $html);
        $this->assertStringContainsString('Нет прав на изменение пароля', $html);
        $this->assertMatchesRegularExpression(
            '/id="change-pass-wrap"[^>]*style="display:\s*none;?"/',
            $html
        );
    }

    public function test_users_page_enables_change_password_when_permission_granted(): void
    {
        $this->asAdmin();
        $this->grantPasswordChangeAccess($this->user);

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="change-password-btn"', $html);
        $this->assertStringContainsString('id="apply-password-btn"', $html);
        $this->assertStringContainsString('id="new-password"', $html);
        $this->assertStringNotContainsString('Нет прав на изменение пароля', $html);
    }

    public function test_trainers_page_keeps_password_wrap_hidden_until_button_click(): void
    {
        $this->asAdmin();
        $this->grantTrainersView($this->user);
        $this->grantPasswordUpdate($this->user);

        $html = $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="trainer-change-password-btn"', $html);
        $this->assertMatchesRegularExpression(
            '/id="trainer-change-pass-wrap"[^>]*style="display:\s*none;?"/',
            $html,
            'Блок пароля тренера при первом открытии модалки должен быть скрыт'
        );
        $this->assertStringContainsString('id="trainer-apply-password-btn"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="trainer-change-password-btn"[^>]*pe-none/',
            $html,
            'С правом users.password.update кнопка смены пароля тренера не должна быть заблокирована'
        );
        $this->assertStringContainsString(
            "passWrap.style.display = 'block'",
            $html,
            'Показ формы пароля должен задавать display:block, иначе CSS .change-pass-wrap { display:none } снова прячет блок'
        );
        $this->assertStringNotContainsString(
            "if (passWrap) passWrap.style.display = ''",
            $html,
            'Пустой inline display не перекрывает CSS display:none у .change-pass-wrap'
        );
    }

    public function test_layout_includes_shared_toast_partial(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/admin2.blade.php'));
        $this->assertStringContainsString("@include('partials.ui.main-toast')", $layout);

        $toast = (string) file_get_contents(resource_path('views/partials/ui/main-toast.blade.php'));
        $this->assertStringContainsString('id="kidsMainToast"', $toast);
        $this->assertStringContainsString('window.showToast = function', $toast);
        $this->assertStringContainsString('bootstrap.Toast.getOrCreateInstance', $toast);
    }

    public function test_all_three_password_js_paths_skip_resend_and_show_toast_instead_of_modal(): void
    {
        $paths = [
            'role_staff' => resource_path('views/admin/role_staff/index.blade.php'),
            'editUser'   => resource_path('views/includes/modal/editUser.blade.php'),
            'trainers'   => resource_path('views/admin/trainers/index.blade.php'),
        ];

        foreach ($paths as $label => $path) {
            $this->assertFileExists($path, $label);
            $js = (string) file_get_contents($path);

            $this->assertStringContainsString(
                'lastAppliedPasswordByUserId',
                $js,
                "{$label}: повтор того же пароля должен отсекаться на клиенте"
            );
            $this->assertStringContainsString(
                "lastAppliedPasswordByUserId[userId] === newPassword",
                $js,
                "{$label}: сравнение с уже применённым паролем этого пользователя"
            );
            $this->assertStringContainsString(
                'Пароль успешно изменен',
                $js,
                "{$label}: успех — всплывайка, не модалка"
            );
            $this->assertStringContainsString('showToast', $js, "{$label}: общая всплывайка");
            $this->assertStringContainsString(
                'Новый пароль совпадает с текущим.',
                $js,
                "{$label}: подсказка без повторного POST"
            );
            $this->assertStringContainsString("'Accept': 'application/json'", $js);
            $this->assertStringContainsString('success:', $js);
            $this->assertStringContainsString('error:', $js);

            $applyPos = strpos($js, "lastAppliedPasswordByUserId[userId] === newPassword");
            $this->assertNotFalse($applyPos, $label);
            $chunk = substr($js, $applyPos, 1800);
            $this->assertStringContainsString('return;', $chunk, "{$label}: при том же пароле AJAX не уходит");
            $this->assertStringNotContainsString(
                'showSuccessModal(\'Обновление пароля\'',
                $js,
                "{$label}: успех не должен открывать модалку даже запасным путём"
            );
        }
    }

    public function test_password_skip_is_keyed_by_user_so_other_user_is_not_blocked(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/role_staff/index.blade.php'));

        $this->assertStringContainsString('const lastAppliedPasswordByUserId = {};', $js);
        $this->assertStringContainsString('lastAppliedPasswordByUserId[userId] = newPassword;', $js);
        $this->assertStringNotContainsString(
            'lastAppliedPassword = newPassword',
            $js,
            'Запоминать пароль нужно по userId, иначе чужая карточка получит ложный skip'
        );
    }
}
