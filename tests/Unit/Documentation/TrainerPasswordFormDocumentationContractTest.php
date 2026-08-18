<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc и admin-trainers §6.4 должны совпадать с фактическим показом
 * формы пароля: тренер — display:block из‑за CSS .change-pass-wrap;
 * ученик — jQuery .show(); сотрудник — без этого класса.
 */
final class TrainerPasswordFormDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_trainer_password_form_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="trainer-password-form-index"', $html);
        $start = strpos($html, 'id="trainer-password-form-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-presence-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/trainers', $chunk);
        $this->assertStringContainsString('Редактирование тренера', $chunk);
        $this->assertStringContainsString('trainer-change-pass-wrap', $chunk);
        $this->assertStringContainsString('trainer-change-password-btn', $chunk);
        $this->assertStringContainsString('change-pass-wrap', $chunk);
        $this->assertStringContainsString('resources/css/style.css', $chunk);
        $this->assertStringContainsString('admin2', $chunk);
        $this->assertStringContainsString("style.display = ''", $chunk);
        $this->assertStringContainsString('display: block', $chunk);
        $this->assertStringContainsString('resetTrainerPasswordUi', $chunk);
        $this->assertStringContainsString('users.password.update', $chunk);
        $this->assertStringContainsString('$(\'#change-pass-wrap\').show()', $chunk);
        $this->assertStringContainsString('inline-block', $chunk);
        $this->assertStringContainsString('role-staff-change-pass-wrap', $chunk);
        $this->assertStringContainsString('без</b> класса', $chunk);
        $this->assertStringContainsString('admin-trainers#trainer-edit-password', $chunk);
        $this->assertStringContainsString('AdminUserPasswordUpdateUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString('не</b> welcome-письмо', $chunk);
        $this->assertStringContainsString('без reload', $chunk);

        $this->assertStringNotContainsString('location.reload', $chunk);
        $this->assertStringNotContainsString('send_welcome_email', $chunk);
        $this->assertStringNotContainsString('Отправить новый пароль по почте» у тренера есть', $chunk);
        $this->assertStringNotContainsString('сотрудник ставит display: block', $chunk);
        $this->assertStringNotContainsString('ученик ставит display: block', $chunk);
    }

    public function test_admin_trainers_page_matches_css_and_js_show_contract(): void
    {
        $html = $this->docFile('admin-trainers.html');
        $start = strpos($html, 'id="trainer-edit-password"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="schedule-journal-trainer"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('trainerEditModal', $chunk);
        $this->assertStringContainsString('trainer-change-password-btn', $chunk);
        $this->assertStringContainsString('trainer-change-pass-wrap', $chunk);
        $this->assertStringContainsString('display: block', $chunk);
        $this->assertStringContainsString('.change-pass-wrap', $chunk);
        $this->assertStringContainsString("style.display = ''", $chunk);
        $this->assertStringContainsString('resetTrainerPasswordUi', $chunk);
        $this->assertStringContainsString('$(\'#change-pass-wrap\').show()', $chunk);
        $this->assertStringContainsString('inline-block', $chunk);
        $this->assertStringContainsString('role-staff-change-pass-wrap', $chunk);
        $this->assertStringContainsString('без</b> класса', $chunk);
        $this->assertStringContainsString('/doc#trainer-password-form-index', $chunk);
        $this->assertStringContainsString('Resend пароля по email на этой странице <b>нет</b>', $chunk);
        $this->assertStringNotContainsString('location.reload', $chunk);
    }

    public function test_catalog_and_controller_title_point_to_the_same_form_fix(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="trainer-password-form-index"', $index);
        $this->assertStringContainsString('/doc#trainer-password-form-index', $index);
        $this->assertStringContainsString('форма «Изменить пароль»', $index);
        $this->assertStringContainsString("'admin-trainers'", $controller);
        $this->assertStringContainsString('форма «Изменить пароль» (display:block из‑за CSS .change-pass-wrap)', $controller);
    }

    public function test_live_markup_matches_documented_display_rules(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 3).'/resources/css/style.css');
        $layout = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/layouts/admin2.blade.php');
        $trainers = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/trainers/index.blade.php');
        $editUser = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/includes/modal/editUser.blade.php');
        $roleStaff = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/role_staff/index.blade.php');
        $account = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/account/users.blade.php');

        $this->assertMatchesRegularExpression(
            '/\.change-pass-wrap\s*\{\s*display:\s*none;?\s*\}/',
            $css,
            'Vite-CSS кабинета скрывает .change-pass-wrap'
        );
        $this->assertStringContainsString("'resources/css/style.css'", $layout);

        $this->assertStringContainsString(
            'class="buttons-wrap change-pass-wrap" id="trainer-change-pass-wrap"',
            $trainers
        );
        $this->assertStringContainsString("passWrap.style.display = 'block'", $trainers);
        $this->assertStringNotContainsString("if (passWrap) passWrap.style.display = ''", $trainers);

        $this->assertStringContainsString("$('#change-pass-wrap').show();", $editUser);
        $this->assertStringContainsString('class="buttons-wrap change-pass-wrap" id="change-pass-wrap"', $editUser);

        $this->assertStringContainsString(
            'id="role-staff-change-pass-wrap" class="wrap-change-password mt-2"',
            $roleStaff
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="role-staff-change-pass-wrap" class="[^"]*\bchange-pass-wrap\b/',
            $roleStaff,
            'У сотрудника нет CSS-класса .change-pass-wrap (подстрока в id не считается)'
        );
        $this->assertStringContainsString(
            "document.getElementById('role-staff-change-pass-wrap').style.display = '';",
            $roleStaff
        );

        $this->assertStringContainsString("style.display = 'inline-block'", $account);
        $this->assertStringContainsString('class="buttons-wrap change-pass-wrap', $account);
    }

    public function test_other_password_docs_do_not_claim_trainer_show_for_staff_or_student(): void
    {
        $users = $this->docFile('admin-users.html');
        $staff = $this->docFile('admin-role-staff.html');

        $this->assertStringContainsString("$('#change-pass-wrap').show()", $users);
        $this->assertStringContainsString('admin-trainers#trainer-edit-password', $users);

        $this->assertStringContainsString('Класса <code>.change-pass-wrap</code> у блока <b>нет</b>', $staff);
        $this->assertStringContainsString("style.display = ''</code> здесь достаточно", $staff);
        $this->assertStringContainsString('admin-trainers#trainer-edit-password', $staff);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
