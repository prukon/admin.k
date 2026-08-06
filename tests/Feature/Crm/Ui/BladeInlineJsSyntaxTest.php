<?php

namespace Tests\Feature\Crm\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Статическая проверка inline-JS в blade-модалках с AJAX-submit.
 * Ловит синтаксические ошибки (например, PHP elseif вместо JS else if),
 * из-за которых обработчик submit не регистрируется и форма уходит нативным POST.
 */
final class BladeInlineJsSyntaxTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function criticalModalBladePathsProvider(): iterable
    {
        yield 'create user modal' => ['includes/modal/createUser.blade.php'];
        yield 'edit user modal' => ['includes/modal/editUser.blade.php'];
        yield 'create team modal' => ['includes/modal/createTeam.blade.php'];
        yield 'edit team modal' => ['includes/modal/editTeam.blade.php'];
        yield 'setting prices users tab' => ['admin/SettingPrices/users.blade.php'];
        yield 'setting prices monthly tab' => ['admin/SettingPrices/monthly.blade.php'];
        yield 'setting prices custom payments tab' => ['admin/SettingPrices/custom-payments.blade.php'];
        yield 'dashboard cabinet team switcher' => ['dashboard.blade.php'];
        yield 'districts index modals' => ['admin/districts/index.blade.php'];
        yield 'sport types index modals' => ['admin/sport-types/index.blade.php'];
        yield 'admin users page' => ['admin/user.blade.php'];
        yield 'admin users parent form ajax handlers' => ['admin/users/_parent_form.blade.php'];
        yield 'admin trainers create welcome email ajax' => ['admin/trainers/index.blade.php'];
        yield 'admin role staff create welcome email ajax' => ['admin/role_staff/index.blade.php'];
        yield 'school leads tab ajax handlers' => ['admin/school-leads/tabs/leads.blade.php'];
        yield 'account user parent form' => ['account/users.blade.php'];
        yield 'outgoing emails report tab' => ['admin/report/outgoing_emails.blade.php'];
        yield 'fiscal receipts report tab' => ['admin/report/fiscal_receipts.blade.php'];
        yield 'payment intents report tab' => ['admin/report/payment_intents.blade.php'];
        yield 'generic multiselect partial' => ['partials/select2/generic-multiselect.blade.php'];
        yield 'schedule journal statuses settings' => ['admin/shared/occurrence_statuses_crud.blade.php'];
        yield 'schedule section index shell' => ['admin/schedule/index.blade.php'];
        yield 'payment systems settings tab' => ['admin/setting/paymentSystem.blade.php'];
        yield 'tbank commissions settings tab' => ['admin/setting/tbankCommissions.blade.php'];
        yield 'school schedule calendar tab' => ['admin/lessonPackages/tabs/schoolSchedule.blade.php'];
        yield 'lesson packages tab modals' => ['admin/lessonPackages/tabs/packages.blade.php'];
        yield 'lesson package assignments tab' => ['admin/lessonPackages/tabs/assignments.blade.php'];
        yield 'club fee payment page' => ['payment/clubFee.blade.php'];
        yield 'ulp public pay page' => ['payment/ulp-public-pay.blade.php'];
        yield 'legal entities index modals' => ['admin/legal-entities/index.blade.php'];
        yield 'legal entities show sm and crud forms' => ['admin/legal-entities/show.blade.php'];
        yield 'teams index legal entity column' => ['admin/team.blade.php'];
        yield 'account organization tab ajax form' => ['account/organizations.blade.php'];
        yield 'admin partner create edit modals' => ['includes/modal/editPartner.blade.php'];
        yield 'partner lead landing form' => ['landing/partner-lead.blade.php'];
    }

    /**
     * P1: Vite-модуль журнала /schedule — AJAX place-fixed + update (не inline blade).
     * node --check + контракт обработчиков (preventDefault, Accept JSON).
     */
    public function test_schedule_journal_vite_module_ajax_handlers_have_valid_javascript_syntax(): void
    {
        $path = resource_path('js/schedule.js');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('place-fixed-abonement', $content);
        $this->assertStringContainsString('place-flexible-abonement', $content);
        $this->assertStringContainsString('flexible-context', $content);
        $this->assertStringContainsString('openFlexiblePlaceModal', $content);
        $this->assertStringContainsString('btn-add-flexible-lesson', $content);
        $this->assertStringContainsString('renderScheduleCellAfterFlexiblePlace', $content);
        $this->assertStringContainsString('updateFlexibleHintAfterPlace', $content);
        $this->assertStringContainsString('flexible_lesson_occurrence_status_id', $content);
        $this->assertStringContainsString('renderScheduleCellAfterFlexiblePlace($cell, result)', $content);
        $this->assertStringContainsString('syncFlexibleTrainerBlock', $content);
        $this->assertStringContainsString('populateFlexibleTrainerSelect', $content);
        $this->assertStringContainsString('showFlexibleErrors', $content);
        // Успех place-flexible обновляет DOM без reload (reload остаётся у других потоков журнала).
        $flexibleSubmitPos = strpos($content, "url: '/schedule/user/' + userId + '/place-flexible-abonement'");
        $this->assertNotFalse($flexibleSubmitPos);
        $flexibleSubmitChunk = substr($content, (int) $flexibleSubmitPos, 1200);
        $this->assertStringContainsString('renderScheduleCellAfterFlexiblePlace', $flexibleSubmitChunk);
        $this->assertStringContainsString('updateFlexibleHintAfterPlace', $flexibleSubmitChunk);
        $this->assertStringNotContainsString('window.location.reload()', $flexibleSubmitChunk);
        $this->assertStringContainsString('/schedule/update', $content);
        $this->assertStringContainsString('preventDefault', $content);
        $this->assertStringContainsString("Accept': 'application/json'", $content);
        $this->assertStringContainsString('$.ajax', $content);
        $this->assertStringContainsString('applySelectedUlpPeriodUi', $content);
        $this->assertStringContainsString('from_setting_prices', $content);
        $this->assertStringContainsString('abonement-ends-at', $content);
        // Валидация даты начала — Laravel (novalidate на форме), без HTML5 min/max.
        $this->assertStringNotContainsString("attr('min'", $content);
        $this->assertStringNotContainsString('attr("min"', $content);
        $this->assertStringNotContainsString("attr('max'", $content);
        $this->assertStringContainsString("prop('required', false)", $content);
        $this->assertStringContainsString('showAbonementErrors', $content);
        $this->assertStringContainsString('abonement-start-date-error', $content);
        // Постоплата: createPostpay + выбор группы в модалке (имя поля create_postpay в blade)
        $this->assertStringContainsString('createPostpay', $content);
        $this->assertStringContainsString('edit-create-postpay', $content);
        $this->assertStringContainsString('postpay_teams', $content);
        $this->assertStringContainsString('is_postpay', $content);
        $this->assertStringContainsString('edit-postpay-team-select', $content);
        // jQuery $.ajax по умолчанию ставит X-Requested-With: XMLHttpRequest;
        // backend также принимает expectsJson через Accept: application/json.

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in resources/js/schedule.js:\n".implode("\n", $output)
        );
    }

    /**
     * P1: модалка «Разложить абонемент» — novalidate + Laravel-ошибки под полями (без HTML5 required/min/max).
     * Гибкий: #flexiblePlaceForm novalidate + хуки ошибок / кнопка добавления.
     */
    public function test_schedule_journal_abonement_place_form_has_novalidate_and_field_error_hooks(): void
    {
        $path = resource_path('views/admin/schedule/journal.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('id="abonementPlaceForm" novalidate', $content);
        $this->assertStringContainsString('id="abonement-start-date"', $content);
        $this->assertStringContainsString('id="abonement-start-date-error"', $content);
        $this->assertStringContainsString('id="abonement-ends-at"', $content);
        $this->assertStringNotContainsString(
            'id="abonement-start-date" name="start_date" required',
            $content
        );

        $this->assertStringContainsString('id="flexiblePlaceModal"', $content);
        $this->assertStringContainsString('id="flexiblePlaceForm" novalidate', $content);
        $this->assertStringContainsString('id="flexible-team-error"', $content);
        $this->assertStringContainsString('id="flexible-ulp-error"', $content);
        $this->assertStringContainsString('id="flexible-date-error"', $content);
        $this->assertStringContainsString('id="flexible-status-error"', $content);
        $this->assertStringContainsString('name="flexible_lesson_occurrence_status_id"', $content);
        $this->assertStringContainsString('id="flexible-trainer-wrap"', $content);
        $this->assertStringContainsString('id="flexible-comment"', $content);
        $this->assertStringContainsString('id="btn-add-flexible-lesson"', $content);
        $this->assertStringContainsString('journal-flexible-hint', $content);
        $this->assertStringContainsString('journal-flexible-hint--ratio', $content);
        $this->assertStringContainsString('journal-flexible-hint--multi', $content);
        $this->assertStringContainsString('data-flexible=', $content);
        $this->assertStringContainsString('data-flexible-ulp-id', $content);
        $this->assertStringContainsString('data-slots-remaining', $content);
        $this->assertStringContainsString('data-lessons-total', $content);
        $this->assertStringContainsString('data-flexible-items', $content);
    }

    /**
     * P1: Vite-модуль вкладки «по месяцам» — бывшие участники (read-only) + AJAX apply.
     */
    public function test_setting_prices_monthly_vite_module_former_members_ajax_handlers_have_valid_javascript_syntax(): void
    {
        $path = resource_path('js/settings-prices.js');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('isFormerMemberFlag', $content);
        $this->assertStringContainsString('is_former_member', $content);
        $this->assertStringContainsString('data-is-former-member', $content);
        $this->assertStringContainsString('/admin/setting-prices/get-team-price', $content);
        $this->assertStringContainsString('/admin/setting-prices/set-price-all-users', $content);
        $this->assertStringContainsString('preventDefault', $content);
        $this->assertStringContainsString("Accept': 'application/json'", $content);
        $this->assertStringContainsString('$.ajax', $content);
        $this->assertStringContainsString('pendingFormerSnapshot', $content);
        $this->assertStringContainsString('buildRightApplyPayloadFromDom', $content);
        $this->assertStringContainsString('pendingApplyPayload', $content);
        // После Apply справа: группа слева остаётся выбранной (без reload)
        $this->assertStringContainsString('wrap-team--active', $content);
        $this->assertStringContainsString('loadTeamUsersRightColumn(lastTeamId)', $content);
        $this->assertStringContainsString('clearTeamRowHighlight', $content);
        $this->assertStringNotContainsString(
            'showSuccessModal("Установка цен в одной группе", "Цены ученикам в выбранной группе успешно обновлены.", 1)',
            $content
        );
        // Постоплата: пересчёт цены, поле визитов, is_postpay в каталоге
        $this->assertStringContainsString('is_postpay', $content);
        $this->assertStringContainsString('postpay_visits', $content);
        $this->assertStringContainsString('setting-prices-monthly-postpay-visits', $content);
        $this->assertStringContainsString('is-postpay-calc', $content);
        $this->assertStringContainsString('packageIsPostpay', $content);
        $this->assertStringContainsString('calcPostpayAmount', $content);
        $this->assertStringContainsString('setting-prices-team-postpay-hint', $content);
        $this->assertStringContainsString('Сумма считается по посещениям у каждого ученика', $content);

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in resources/js/settings-prices.js:\n".implode("\n", $output)
        );
    }

    /**
     * P1: inline JS шаблонов абонементов — schedule_type=postpay (цена за занятие).
     */
    public function test_lesson_packages_postpay_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/packages.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('value="postpay"', $content);
        $this->assertStringContainsString('@can(\'lessonPackages.type.postpay\')', $content);
        $this->assertStringContainsString("t === 'postpay'", $content);
        $this->assertStringContainsString('Стоимость за одно занятие', $content);
        $this->assertStringContainsString('preventDefault', $content);
        $this->assertStringContainsString("Accept': 'application/json'", $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], 'В packages.blade.php нет inline <script>');

        $postpayScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, "t === 'postpay'") && ! str_contains($rawScript, 'postpay')) {
                continue;
            }
            $postpayScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-packages-postpay-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in lesson packages postpay script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue(
            $postpayScriptFound,
            'В packages.blade.php не найден script с обработкой schedule_type=postpay'
        );
    }

    /**
     * P1: inline JS кабинета — блокировка кнопки оплаты постоплаты до 1 числа.
     */
    public function test_dashboard_postpay_pay_gate_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/dashboard.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('postpay_pay_available', $content);
        $this->assertStringContainsString('postpayBlocked', $content);
        $this->assertStringContainsString('is_postpay', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], 'В dashboard.blade.php нет inline <script>');

        $postpayScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'postpay_pay_available')
                && ! str_contains($rawScript, 'postpayBlocked')) {
                continue;
            }
            $postpayScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-dashboard-postpay-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in dashboard postpay script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue(
            $postpayScriptFound,
            'В dashboard.blade.php не найден script с postpay_pay_available / postpayBlocked'
        );
    }

    /**
     * P1: inline JS вкладки «по ученикам» — фильтр former-team-ids + read-only year prices.
     */
    public function test_setting_prices_users_tab_former_members_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/SettingPrices/users.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('data-former-team-ids', $content);
        $this->assertStringContainsString('is_former_member', $content);
        $this->assertStringContainsString('/admin/setting-prices/user-year-prices', $content);
        $this->assertStringContainsString('user-year-prices/save', $content);
        $this->assertStringContainsString('не в группе', $content);
        $this->assertStringContainsString("Accept': 'application/json'", $content);
        $this->assertStringContainsString('$.ajax', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], 'В users.blade.php нет inline <script>');

        $formerScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'data-former-team-ids')
                && ! str_contains($rawScript, 'is_former_member')) {
                continue;
            }
            $formerScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-users-former-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in setting prices users former script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue(
            $formerScriptFound,
            'В users.blade.php не найден script с обработкой бывших участников (data-former-team-ids / is_former_member)'
        );
    }

    /**
     * P1: inline JS модалки выгрузки Excel на календаре школы (fetch + ошибки под полями).
     */
    public function test_school_schedule_export_modal_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/schoolSchedule.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('initSchoolCalExport', $content);
        $this->assertStringContainsString('schoolCalExportSubmit', $content);
        $this->assertStringContainsString('schoolCalExportDateFromErr', $content);
        $this->assertStringContainsString('exportXlsx', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $exportScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'initSchoolCalExport')) {
                continue;
            }
            $exportScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-export-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in school schedule export script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($exportScriptFound, 'В schoolSchedule.blade.php не найден script с initSchoolCalExport');
    }

    /**
     * P1: inline JS привязки фиксированного абонемента в модалке слота (fetch + errors.patterns).
     */
    public function test_school_schedule_fixed_bind_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/schoolSchedule.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('submitSchoolCalSlotFixedRegistration', $content);
        $this->assertStringContainsString('showSchoolCalSlotFixedFieldErrs', $content);
        $this->assertStringContainsString('routes.fixedAssign', $content);
        $this->assertStringContainsString('schoolCalSlotFixedFormWrap', $content);
        $this->assertStringContainsString('data-err="patterns"', $content);
        $this->assertStringContainsString('X-Requested-With', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $fixedScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'submitSchoolCalSlotFixedRegistration')) {
                continue;
            }
            $fixedScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-fixed-bind-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in school schedule fixed-bind script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($fixedScriptFound, 'В schoolSchedule.blade.php не найден script с submitSchoolCalSlotFixedRegistration');
    }

    /**
     * P1: inline JS модалки смены даты окончания назначения (ends_at + errors под полем).
     */
    public function test_lesson_package_assignment_ends_at_modal_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/assignments.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('ulp-modal-period-end', $content);
        $this->assertStringContainsString('ulp-modal-period-end-err', $content);
        $this->assertStringContainsString('period_editable', $content);
        $this->assertStringContainsString('body.ends_at', $content);
        $this->assertStringContainsString('payload.errors.ends_at', $content);
        $this->assertStringContainsString('ulp-modal-save', $content);
        $this->assertStringContainsString('X-Requested-With', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $endsAtScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'body.ends_at') && ! str_contains($rawScript, 'period_editable')) {
                continue;
            }
            $endsAtScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-ends-at-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in assignment ends_at modal script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($endsAtScriptFound, 'В assignments.blade.php не найден script с period_editable / ends_at');
    }

    /**
     * P1: inline JS модалки «История» на вкладке назначений (showLogModal + logs-data).
     */
    public function test_lesson_package_assignments_history_modal_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/assignments.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('historyModal', $content);
        $this->assertStringContainsString('showLogModal', $content);
        $this->assertStringContainsString('logs.data.lesson-package-assignment', $content);
        $this->assertStringContainsString('fa-clock-rotate-left', $content);
        $this->assertStringContainsString("includes.logModal", $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $historyScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'showLogModal')) {
                continue;
            }
            $historyScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-ulp-history-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in assignments history script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($historyScriptFound, 'В assignments.blade.php не найден script с showLogModal');
    }

    /**
     * P1: inline JS вкладки «Статусы занятий» (toolbar + DataTables + AJAX CRUD fetch).
     */
    public function test_occurrence_statuses_crud_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/shared/occurrence_statuses_crud.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('KidsCrmDataTable.create', $content);
        $this->assertStringContainsString('los-create-submit', $content);
        $this->assertStringContainsString('los-edit-submit', $content);
        $this->assertStringContainsString('reloadLosTable', $content);
        $this->assertStringContainsString('showLogModal', $content);
        $this->assertStringContainsString('fetch(', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $crudScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'KidsCrmDataTable.create')) {
                continue;
            }
            $crudScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-los-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in occurrence_statuses_crud script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($crudScriptFound, 'В occurrence_statuses_crud.blade.php не найден script с KidsCrmDataTable.create');
    }

    /**
     * P1: inline JS отчёта «Исходящие письма» — фильтр партнёра, DataTables reload, модалка show.
     */
    public function test_outgoing_emails_report_partner_filter_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/report/outgoing_emails.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('em-filter-partner', $content);
        $this->assertStringContainsString('emailsCanFilterPartner', $content);
        $this->assertStringContainsString('getFilterParams', $content);
        $this->assertStringContainsString('partner_id', $content);
        $this->assertStringContainsString('openOutgoingEmailShowModal', $content);
        $this->assertStringContainsString('KidsCrmDataTable.create', $content);
        $this->assertStringContainsString("key: 'partner'", $content);
        $this->assertStringContainsString('X-Requested-With', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'getFilterParams')) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-emails-partner-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in outgoing_emails partner-filter script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, 'В outgoing_emails.blade.php не найден script с getFilterParams');
    }

    /**
     * P1: inline JS лидов — смена статуса у лида с клиентом + динамический tooltip «Создать клиента».
     */
    public function test_school_leads_linked_status_and_create_client_tooltip_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/school-leads/tabs/leads.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('buildCreateClientMissingHint', $content);
        $this->assertStringContainsString('updateCreateClientBtnTooltip', $content);
        $this->assertStringContainsString('syncCreateClientBtnState', $content);
        $this->assertStringContainsString('saveLeadStatusInline', $content);
        $this->assertStringContainsString('saveLeadStatusInline(modalLeadId, newStatusId', $content);
        $this->assertStringContainsString('У лида с клиентом нет «Сохранить»', $content);
        $this->assertStringContainsString('email родителя', $content);
        $this->assertStringContainsString('school_lead_status_id', $content);
        $this->assertStringContainsString("type: 'PUT'", $content);
        $this->assertStringContainsString('X-CSRF-TOKEN', $content);
        $this->assertStringContainsString('$.ajax', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'saveLeadStatusInline') || ! str_contains($rawScript, 'buildCreateClientMissingHint')) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-school-leads-linked-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in school-leads linked-status script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, 'В leads.blade.php не найден script с saveLeadStatusInline + buildCreateClientMissingHint');
    }

    #[DataProvider('criticalModalBladePathsProvider')]
    public function test_critical_modal_inline_scripts_have_valid_javascript_syntax(string $relativePath): void
    {
        $path = resource_path('views/' . $relativePath);
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);

        $this->assertNotEmpty(
            $matches[1],
            "В {$relativePath} не найдено inline <script> для проверки"
        );

        foreach ($matches[1] as $index => $rawScript) {
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);

            if (trim($js) === '') {
                continue;
            }

            $tempFile = sys_get_temp_dir() . '/blade-js-' . uniqid('', true) . '.js';

            try {
                file_put_contents($tempFile, $js);

                $output = [];
                $exitCode = 0;
                exec('node --check ' . escapeshellarg($tempFile) . ' 2>&1', $output, $exitCode);

                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in %s, script block #%d:\n%s\n--- script preview ---\n%s",
                        $relativePath,
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 500)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }
    }

    private function normalizeBladeScriptForSyntaxCheck(string $script): string
    {
        $js = $this->stripBladeJsonCalls($script);

        // Blade-выражения → placeholder без кавычек (часто внутри строк: "{{ asset(...) }}/").
        $js = preg_replace('/\{!!.*?!!\}/s', '__BLADE__', $js) ?? $js;
        $js = preg_replace('/\{\{.*?\}\}/s', '__BLADE__', $js) ?? $js;

        // Blade-директивы с аргументами в скобках (@if(...), @include(...), @can(...) и т.п.).
        $js = $this->stripBalancedBladeDirectiveCalls($js);

        // Однострочные blade-директивы (@csrf, @endforeach, @endif и т.п.) — убираем.
        $js = preg_replace('/^\s*@\w+.*$/m', '', $js) ?? $js;

        return $js;
    }

    /**
     * Заменяет @directive(...) на null с учётом вложенных скобок в аргументах.
     */
    private function stripBalancedBladeDirectiveCalls(string $script): string
    {
        $pos = 0;
        $len = strlen($script);

        while ($pos < $len) {
            $at = strpos($script, '@', $pos);
            if ($at === false) {
                break;
            }

            if (! preg_match('/@\w+/', $script, $match, 0, $at) || $match[0] === '') {
                $pos = $at + 1;

                continue;
            }

            $directiveEnd = $at + strlen($match[0]);
            $tail = substr($script, $directiveEnd);
            if (! preg_match('/^\s*\(/', $tail)) {
                $pos = $directiveEnd;

                continue;
            }

            $openParen = $directiveEnd + (int) strpos($tail, '(');
            $i = $openParen + 1;
            $depth = 1;

            while ($i < $len && $depth > 0) {
                $ch = $script[$i];
                if ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth--;
                }
                $i++;
            }

            $script = substr($script, 0, $at).'null'.substr($script, $i);
            $pos = $at + 4;
            $len = strlen($script);
        }

        return $script;
    }

    private function stripBladeJsonCalls(string $script): string
    {
        $needle = '@json(';
        $pos = 0;

        while (($start = strpos($script, $needle, $pos)) !== false) {
            $open = $start + strlen($needle);
            $depth = 1;
            $i = $open;
            $len = strlen($script);

            while ($i < $len && $depth > 0) {
                $ch = $script[$i];
                if ($ch === '(') {
                    $depth++;
                } elseif ($ch === ')') {
                    $depth--;
                }
                $i++;
            }

            $script = substr($script, 0, $start) . 'null' . substr($script, $i);
            $pos = $start + 4;
        }

        return $script;
    }
}
