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
        yield 'setting prices payment notifications tab' => ['admin/SettingPrices/payment-notifications.blade.php'];
        yield 'in-app notifications compose' => ['admin/in_app_notifications/compose.blade.php'];
        yield 'in-app notifications bell echo' => ['includes/in_app_notifications/echo.blade.php'];
        yield 'chat unread badge echo' => ['includes/chat/echo.blade.php'];
        yield 'chat reverb status overlay' => ['includes/chat/reverb_status.blade.php'];
        yield 'in-app notifications inbox highlight' => ['admin/in_app_notifications/index.blade.php'];
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
        yield 'debts report tab' => ['admin/report/debt.blade.php'];
        yield 'generic multiselect partial' => ['partials/select2/generic-multiselect.blade.php'];
        yield 'schedule journal statuses settings' => ['admin/shared/occurrence_statuses_crud.blade.php'];
        yield 'schedule section index shell' => ['admin/schedule/index.blade.php'];
        yield 'payment systems settings tab' => ['admin/setting/paymentSystem.blade.php'];
        yield 'tbank commissions settings tab' => ['admin/setting/tbankCommissions.blade.php'];
        yield 'school schedule calendar tab' => ['admin/lessonPackages/tabs/schoolSchedule.blade.php'];
        yield 'team schedule slot create edit modals' => ['admin/teamScheduleSlots/partials/slotModals.blade.php'];
        yield 'lesson packages tab modals' => ['admin/lessonPackages/tabs/packages.blade.php'];
        yield 'lesson package assignments tab' => ['admin/lessonPackages/tabs/assignments.blade.php'];
        yield 'club fee payment page' => ['payment/clubFee.blade.php'];
        yield 'ulp public pay page' => ['payment/ulp-public-pay.blade.php'];
        yield 'legal entities index modals' => ['admin/legal-entities/index.blade.php'];
        yield 'locations index delete toast ajax' => ['admin/locations/index.blade.php'];
        yield 'settings roles create and delete toast ajax' => ['admin/setting/rule.blade.php'];
        yield 'settings general tab cabinet diagnostics' => ['admin/setting/setting.blade.php'];
        yield 'legal entities show sm and crud forms' => ['admin/legal-entities/show.blade.php'];
        yield 'teams index legal entity column' => ['admin/team.blade.php'];
        yield 'account organization tab ajax form' => ['account/organizations.blade.php'];
        yield 'admin partner create edit modals' => ['includes/modal/editPartner.blade.php'];
        yield 'admin partners list metrics tab' => ['admin/partners/tabs/partners.blade.php'];
        yield 'partner lead landing form' => ['landing/partner-lead.blade.php'];
        yield 'contract templates variables reference copy js' => ['contract-templates/partials/variables-reference.blade.php'];
        yield 'contract templates edit modal init' => ['contract-templates/partials/edit-modal-init.blade.php'];
        yield 'contract templates index page scripts' => ['contract-templates/index.blade.php'];
        yield 'account documents fill modal ajax' => ['account/documents.blade.php'];
        yield 'account settings tabs shell' => ['account/index.blade.php'];
        yield 'cabinet attach team modal' => ['includes/modal/cabinet_attach_team_modal.blade.php'];
        yield 'user percent discount js helper' => ['partials/ui/discount-percent-js.blade.php'];
        yield 'admin main toast partial' => ['partials/ui/main-toast.blade.php'];
        yield 'admin2 layout leftover inline scripts' => ['layouts/admin2.blade.php'];
        yield 'landing layout leftover inline scripts' => ['layouts/landingPage.blade.php'];
        yield 'cabinet layout wide toggle' => ['includes/layout_wide_toggle.blade.php'];
    }

    /**
     * P1: модалка «Добавить группу» в ЛК — AJAX-контракт (preventDefault, fetch, errors.team_id, reload).
     */
    public function test_admin_and_landing_layouts_do_not_embed_fontawesome_kit_and_inline_scripts_are_valid(): void
    {
        foreach (['layouts/admin2.blade.php', 'layouts/landingPage.blade.php'] as $relative) {
            $path = resource_path('views/'.$relative);
            $this->assertFileExists($path);
            $content = (string) file_get_contents($path);

            $this->assertStringContainsString("@include('includes.fontawesome')", $content, $relative);
            $this->assertStringNotContainsString('js/fontawesome/fontawesome.js', $content, $relative);
            $this->assertStringNotContainsString('FontAwesomeKitConfig', $content, $relative);
            $this->assertStringNotContainsString('ka-f.fontawesome.com', $content, $relative);
        }

        $partial = (string) file_get_contents(resource_path('views/includes/fontawesome.blade.php'));
        $this->assertStringNotContainsString('<script', $partial);
        $this->assertStringContainsString('plugins/fontawesome-free/css/all.min.css', $partial);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            resource_path('views/layouts/admin2.blade.php'),
            'showModalQueued',
            'blade-js-admin2-modals'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            resource_path('views/layouts/landingPage.blade.php'),
            'showModalQueued',
            'blade-js-landing-modals'
        );
    }

    public function test_cabinet_attach_team_modal_ajax_contract_and_valid_javascript(): void
    {
        $path = resource_path('views/includes/modal/cabinet_attach_team_modal.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('cabinetAttachTeamForm', $content);
        $this->assertStringContainsString('cabinetAttachTeamSelect', $content);
        $this->assertStringContainsString('data-error-for="team_id"', $content);
        $this->assertStringContainsString('ФИО ученика', $content);
        $this->assertStringContainsString('Текущая группа', $content);
        $this->assertStringContainsString('Объект', $content);
        $this->assertStringContainsString('Новая группа', $content);
        $this->assertStringContainsString('Отмена', $content);
        $this->assertStringContainsString('cabinetAttachTeamSubmit', $content);

        $this->assertStringContainsString('preventDefault', $content);
        $this->assertStringContainsString('fetch(', $content);
        $this->assertStringContainsString("Accept': 'application/json'", $content);
        $this->assertStringContainsString('X-Requested-With', $content);
        $this->assertStringContainsString('XMLHttpRequest', $content);
        $this->assertStringContainsString('errors.team_id', $content);
        $this->assertStringContainsString('window.location.reload()', $content);
        $this->assertStringContainsString('hidden.bs.modal', $content);
        $this->assertStringContainsString('form.reset()', $content);
        $this->assertStringContainsString('is-invalid', $content);

        $submitPos = strpos($content, "form.addEventListener('submit'");
        $this->assertNotFalse($submitPos);
        $submitChunk = substr($content, (int) $submitPos, 2200);
        $this->assertStringContainsString('preventDefault', $submitChunk);
        $this->assertStringContainsString('fetch(', $submitChunk);
        $this->assertStringContainsString('window.location.reload()', $submitChunk);
        $this->assertStringContainsString('team_id', $submitChunk);
        $this->assertSame(1, substr_count($content, "form.addEventListener('submit'"));
        $this->assertStringNotContainsString('inAppNotifications.bell', $content);
        $this->assertStringNotContainsString('js-in-app-bell', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], 'В cabinet_attach_team_modal нет inline <script>');

        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'cabinetAttachTeamForm')) {
                continue;
            }
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-cabinet-attach-team-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    "JS syntax error in cabinet_attach_team_modal, script #{$index}:\n".implode("\n", $output)
                );
            } finally {
                @unlink($tempFile);
            }
        }
    }

    public function test_create_team_modal_ajax_prevents_native_submit_and_shows_title_errors(): void
    {
        $path = resource_path('views/includes/modal/createTeam.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('id="teamForm"', $content);
        $this->assertStringContainsString("route('admin.team.store')", $content);
        $this->assertStringContainsString("function createTeam()", $content);
        $this->assertSame(1, substr_count($content, "teamForm.addEventListener('submit'"));

        $submitPos = strpos($content, "teamForm.addEventListener('submit'");
        $this->assertNotFalse($submitPos);
        $submitChunk = substr($content, (int) $submitPos, 8000);
        $this->assertStringContainsString('e.preventDefault()', $submitChunk);
        $this->assertStringContainsString('fetch(', $submitChunk);
        $this->assertStringContainsString('X-Requested-With', $submitChunk);
        $this->assertStringContainsString('XMLHttpRequest', $submitChunk);
        $this->assertStringContainsString('errors.title', $submitChunk);
        $this->assertStringContainsString('title-error', $submitChunk);
        $this->assertStringContainsString('teamForm.reset()', $submitChunk);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'function createTeam()',
            'blade-js-create-team-modal'
        );
    }

    public function test_edit_team_modal_ajax_patches_and_shows_title_errors_without_touching_chat_api(): void
    {
        $path = resource_path('views/includes/modal/editTeam.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('id="edit-team-form"', $content);
        $this->assertStringContainsString("$('#update-team-btn').on('click'", $content);
        $this->assertStringContainsString('$.ajax({', $content);
        $this->assertStringContainsString("type: 'PATCH'", $content);
        $this->assertStringContainsString('errors.title', $content);
        $this->assertStringContainsString('edit-title-error', $content);
        $this->assertStringNotContainsString('/chat/api', $content);
        $this->assertStringNotContainsString('threads.subject', $content);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            "type: 'PATCH'",
            'blade-js-edit-team-modal'
        );
    }

    /**
     * P1: семейный переключатель — native POST, selected активного ученика, без AJAX-submit.
     */
    public function test_family_student_switcher_posts_student_id_and_is_valid_javascript(): void
    {
        $path = resource_path('views/includes/family_student_switcher.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("route('cabinet.active-student.switch')", $content);
        $this->assertStringContainsString('method="post"', $content);
        $this->assertStringContainsString('name="student_user_id"', $content);
        $this->assertStringContainsString('id="family-active-student"', $content);
        $this->assertStringContainsString('onchange="this.form.submit()"', $content);
        $this->assertStringContainsString('@selected', $content);
        $this->assertStringContainsString('$activeStudent', $content);
        $this->assertStringContainsString('$familyStudents', $content);
        $this->assertStringNotContainsString('fetch(', $content);
        $this->assertStringNotContainsString('$.ajax', $content);

        $js = 'document.querySelector("#family-active-student") && document.querySelector("#family-active-student").form && document.querySelector("#family-active-student").form.submit();';
        $tempFile = sys_get_temp_dir().'/blade-js-family-switcher-'.uniqid('', true).'.js';
        try {
            file_put_contents($tempFile, $js);
            $output = [];
            $exitCode = 0;
            exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
            $this->assertSame(0, $exitCode, implode("\n", $output));
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * P1: консоль — две JS-сборки формы «Оплатить» сезон (шаблон и пересборка) сохраняют team_id и период.
     */
    public function test_dashboard_season_pay_forms_keep_team_and_period_on_rebuild(): void
    {
        $path = resource_path('views/dashboard.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("paymentUrl: '{{ route('payment') }}'", $content);
        $this->assertStringContainsString('const paymentUrl = window.Laravel.paymentUrl', $content);

        $initialPos = strpos($content, 'name="formatedPaymentDate" value="${formatedPaymentDate}"');
        $this->assertNotFalse($initialPos);
        $initialChunk = substr($content, $initialPos - 400, 900);
        $this->assertStringContainsString('action="${paymentUrl}"', $initialChunk);
        $this->assertStringContainsString('method="POST"', $initialChunk);
        $this->assertStringContainsString('name="team_id"', $initialChunk);
        $this->assertStringContainsString('name="formatedPaymentDate"', $initialChunk);

        $singlePos = strpos($content, 'name="formatedPaymentDate" value="${matchedData.new_month}"');
        $this->assertNotFalse($singlePos);
        $singleChunk = substr($content, $singlePos - 500, 1100);
        $this->assertStringContainsString('action="${paymentUrl}"', $singleChunk);
        $this->assertStringContainsString('name="team_id" value="${matchedData.team_id || \'\'}"', $singleChunk);
        $this->assertStringContainsString('name="formatedPaymentDate" value="${matchedData.new_month}"', $singleChunk);
        $this->assertStringContainsString('method="POST"', $singleChunk);

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($content, 'name="formatedPaymentDate" value="${matchedData.new_month}"'),
            'Ожидались обе пересборки формы (одна группа и несколько групп)'
        );
        $this->assertStringContainsString('name="team_id" value="${matchedData.team_id || \'\'}"', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);
        $foundPayRebuild = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'matchedData.team_id')) {
                continue;
            }
            $foundPayRebuild = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $tempFile = sys_get_temp_dir().'/blade-js-dashboard-family-pay-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    "JS syntax error in dashboard season pay rebuild, script #{$index}:\n".implode("\n", $output)."\n".mb_substr($js, 0, 400)
                );
            } finally {
                @unlink($tempFile);
            }
        }
        $this->assertTrue($foundPayRebuild, 'В dashboard.blade.php нет пересборки формы оплаты с matchedData.team_id');
    }

    /**
     * P1: список /admin/partners — метрики, дефолты колонок, сброс фильтра, node --check.
     */
    public function test_partners_list_metrics_inline_script_contract_and_valid_javascript(): void
    {
        $path = resource_path('views/admin/partners/tabs/partners.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('id="partners-table"', $content);
        $this->assertStringContainsString('KidsCrmDataTable.create', $content);
        $this->assertStringContainsString("const defaultFilterStatus = 'active';", $content);
        $this->assertStringContainsString("$('#filter-status').val(defaultFilterStatus);", $content);
        $this->assertStringContainsString("e.preventDefault();", $content);
        $this->assertStringContainsString('reloadPartnersTable', $content);
        $this->assertStringContainsString('window.reloadPartnersTable', $content);

        $this->assertStringContainsString('active_users_count: true', $content);
        $this->assertStringContainsString('signed_contracts_count: true', $content);
        $this->assertStringContainsString('turnover_all: true', $content);
        $this->assertStringContainsString('platform_commission_all: true', $content);
        $this->assertStringContainsString('turnover_month_0: true', $content);
        $this->assertStringContainsString('platform_commission_month_0: true', $content);
        $this->assertStringContainsString('turnover_month_1: true', $content);
        $this->assertStringContainsString('platform_commission_month_1: true', $content);
        $this->assertStringContainsString('turnover_month_2: true', $content);
        $this->assertStringContainsString('platform_commission_month_2: true', $content);

        $this->assertStringContainsString("key: 'active_users_count', type: 'count'", $content);
        $this->assertStringContainsString("key: 'signed_contracts_count', type: 'count'", $content);
        $this->assertStringContainsString("key: 'turnover_all', type: 'money'", $content);
        $this->assertStringContainsString("key: 'platform_commission_all', type: 'money'", $content);
        $this->assertStringContainsString("key: 'turnover_month_0', type: 'money'", $content);
        $this->assertStringContainsString("key: 'platform_commission_month_0', type: 'money'", $content);
        $this->assertStringContainsString("key: 'turnover_month_1', type: 'money'", $content);
        $this->assertStringContainsString("key: 'platform_commission_month_1', type: 'money'", $content);
        $this->assertStringContainsString("key: 'turnover_month_2', type: 'money'", $content);
        $this->assertStringContainsString("key: 'platform_commission_month_2', type: 'money'", $content);

        $this->assertStringContainsString('% {{ $partnerMetricMonthLabels[0] }}', $content);
        $this->assertStringContainsString('% {{ $partnerMetricMonthLabels[1] }}', $content);
        $this->assertStringContainsString('% {{ $partnerMetricMonthLabels[2] }}', $content);
        $this->assertStringContainsString('% за всё время', $content);
        $this->assertStringNotContainsString("key: 'август'", $content);
        $this->assertStringNotContainsString('Оборот за август', $content);
        $this->assertStringNotContainsString('Кол-во активных пользователей', $content);
        $this->assertStringNotContainsString('Кол-во договоров', $content);
        $this->assertStringNotContainsString('Оборот за всё время', $content);
        $this->assertStringNotContainsString('Оборот за {{', $content);

        $createPos = strpos($content, "KidsCrmDataTable.create('#partners-table'");
        $this->assertNotFalse($createPos);
        $columnsPos = strpos($content, 'columns: [', $createPos);
        $this->assertNotFalse($columnsPos);
        $actionsKeyPos = strpos($content, "key: 'actions'", $columnsPos);
        $this->assertNotFalse($actionsKeyPos);
        $columnsChunk = substr($content, $columnsPos, $actionsKeyPos - $columnsPos);
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'platform_commission_all'"),
            strpos($columnsChunk, "key: 'turnover_all'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'turnover_month_0'"),
            strpos($columnsChunk, "key: 'platform_commission_all'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'platform_commission_month_0'"),
            strpos($columnsChunk, "key: 'turnover_month_0'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'turnover_month_1'"),
            strpos($columnsChunk, "key: 'platform_commission_month_0'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'platform_commission_month_1'"),
            strpos($columnsChunk, "key: 'turnover_month_1'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'turnover_month_2'"),
            strpos($columnsChunk, "key: 'platform_commission_month_1'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'platform_commission_month_2'"),
            strpos($columnsChunk, "key: 'turnover_month_2'")
        );

        $resetPos = strpos($content, "$('#filter-reset').on('click'");
        $this->assertNotFalse($resetPos);
        $resetChunk = substr($content, (int) $resetPos, 400);
        $this->assertStringContainsString('defaultFilterStatus', $resetChunk);
        $this->assertStringNotContainsString("$('#filter-status').val('');", $resetChunk);
        $this->assertStringNotContainsString('column-toggle', $resetChunk);
        $this->assertStringNotContainsString("prop('checked'", $resetChunk);

        $theadPos = strpos($content, '<th>Статус</th>');
        $usersPos = strpos($content, '<th>Акт. польз.</th>');
        $contractsPos = strpos($content, '<th>Договоров</th>');
        $turnoverPos = strpos($content, '<th>За всё время</th>');
        $commissionAllPos = strpos($content, '<th>% за всё время</th>');
        $actionsPos = strpos($content, '<th>Действия</th>');
        $this->assertNotFalse($theadPos);
        $this->assertNotFalse($usersPos);
        $this->assertNotFalse($contractsPos);
        $this->assertNotFalse($turnoverPos);
        $this->assertNotFalse($commissionAllPos);
        $this->assertNotFalse($actionsPos);
        $this->assertLessThan($usersPos, $theadPos);
        $this->assertLessThan($contractsPos, $usersPos);
        $this->assertLessThan($turnoverPos, $contractsPos);
        $this->assertLessThan($commissionAllPos, $turnoverPos);
        $this->assertLessThan($actionsPos, $commissionAllPos);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], 'В admin/partners/tabs/partners.blade.php нет inline <script>');

        foreach ($matches[1] as $index => $rawScript) {
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            if (trim($js) === '') {
                continue;
            }

            $tempFile = sys_get_temp_dir().'/blade-js-partners-metrics-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in partners.blade.php, script #%d:\n%s",
                        $index + 1,
                        implode("\n", $output)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }
    }

    /**
     * P1: вкладка «Мои документы» — badge каунтера с отступом ms-2 (не без margin).
     */
    public function test_account_index_documents_tab_counter_badge_markup_contract(): void
    {
        $path = resource_path('views/account/index.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("route('account.documents.index')", $content);
        $this->assertStringContainsString('unsignedContractsCount', $content);
        $this->assertStringContainsString(
            '@if(($unsignedContractsCount ?? 0) > 0)<span class="badge badge-info ms-2">{{ $unsignedContractsCount }}</span>@endif',
            $content
        );
        $this->assertStringNotContainsString(
            'Мои документы@if(($unsignedContractsCount ?? 0) > 0)<span class="badge badge-info">{{ $unsignedContractsCount }}</span>@endif',
            $content
        );
    }

    /**
     * P1: сайдбар «Учетная запись» — badge как у «Пользователи», скрыт при 0.
     */
    public function test_sidebar_account_menu_counter_badge_markup_contract(): void
    {
        $path = resource_path('views/includes/sidebar.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('unsignedContractsCount', $content);
        $this->assertStringContainsString(
            '@if(($unsignedContractsCount ?? 0) > 0)<span class="badge badge-info right">{{ $unsignedContractsCount }}</span>@endif',
            $content
        );
        $this->assertStringContainsString('js-chat-unread-count', $content);
        $this->assertStringContainsString('Чат', $content);
        $this->assertStringContainsString("route('chat.index')", $content);
        $this->assertStringContainsString('@can(\'messages.view\')', $content);
    }

    public function test_chat_js_module_has_valid_javascript_and_field_errors(): void
    {
        $path = resource_path('js/chat.js');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("Accept': 'application/json'", $content);
        $this->assertStringContainsString('errors', $content);
        $this->assertStringContainsString('msgBodyError', $content);
        $this->assertStringContainsString('contactsError', $content);
        $this->assertStringContainsString('contactsTeamError', $content);
        $this->assertStringContainsString('contactsTeamFilter', $content);
        $this->assertStringContainsString("fieldError(res.data, 'team_id')", $content);
        $this->assertStringContainsString('user_id', $content);
        $this->assertStringContainsString('preventDefault', $content);
        $this->assertStringContainsString('bootstrap.Modal', $content);
        $this->assertStringContainsString('e.preventDefault()', $content);
        $this->assertStringContainsString('fetch(', $content);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $content);
        $this->assertStringContainsString("fieldError(res.data, 'body')", $content);
        $this->assertStringContainsString("fieldError(res.data, 'user_id')", $content);
        $this->assertStringContainsString("fieldError(res.data, 'user_ids')", $content);
        $this->assertStringContainsString("fieldError(res.data, 'title')", $content);
        $this->assertStringContainsString("fieldError(res.data, 'thread')", $content);
        $this->assertStringContainsString('function confirmDeleteThread(', $content);
        $this->assertStringContainsString('function submitDeleteThread(', $content);
        $this->assertStringContainsString('function setDeleteThreadVisible(', $content);
        $this->assertStringContainsString("chatToast(res.data.message || 'Чат удалён.')", $content);
        $this->assertStringContainsString('headers(true)', $content);
        $this->assertStringContainsString('e.stopPropagation()', $content);
        $this->assertStringContainsString('if (e.removed)', $content);
        $this->assertStringContainsString('currentTeamId = res.thread.team_id ? Number(res.thread.team_id) : null;', $content);
        $this->assertStringContainsString('setDeleteThreadVisible();', $content);
        $this->assertStringContainsString('showConfirmDeleteModal(', $content);
        $this->assertStringContainsString('function openCreateGroupWizard(', $content);
        $this->assertStringContainsString('function submitCreateGroup(', $content);
        $this->assertStringContainsString('function resetCreateGroupWizard(', $content);
        $this->assertStringContainsString('js-open-create-group', $content);
        $this->assertStringContainsString("querySelectorAll('.js-open-create-group')", $content);
        $this->assertStringContainsString('is_group: e.is_group', $content);
        $this->assertStringContainsString('function threadListTitle(', $content);
        $this->assertStringContainsString("return t && t.is_group ? 'Группа' : 'Диалог';", $content);
        $this->assertStringContainsString("threadTitle').textContent = threadListTitle(res.thread)", $content);
        $this->assertStringContainsString('function setThreadSubtitle(', $content);
        $this->assertStringContainsString("getElementById('threadSubtitle')", $content);
        $this->assertStringContainsString('res.thread.header_subtitle', $content);
        $this->assertStringContainsString("setThreadSubtitle('')", $content);
        $this->assertStringContainsString('function membersCountLabel(', $content);
        $this->assertStringContainsString('setThreadSubtitle(membersCountLabel(thread.members_total))', $content);
        $this->assertStringContainsString('return !t.is_group && Number(t.peer_id) === Number(userId);', $content);
        $this->assertStringContainsString('if (patch.peer_id && !patch.is_group) {', $content);
        $this->assertStringContainsString('is_group: e.is_group', $content);
        $this->assertStringNotContainsString('/admin/teams', $content);
        $this->assertStringNotContainsString('admin.team.store', $content);
        $this->assertStringContainsString('setComposerEnabled(true)', $content);
        $this->assertStringContainsString("getElementById('msgInput').focus()", $content);
        $this->assertStringContainsString('persistLeavingDraft(threadId)', $content);
        $this->assertStringContainsString("threadUrl(id, '/draft')", $content);
        $this->assertStringContainsString('function persistLeavingDraft(', $content);
        $this->assertStringContainsString('function scheduleDraftSave(', $content);
        $this->assertStringContainsString('function composerDraftFor(', $content);
        $this->assertStringContainsString('function mergeLocalDrafts(', $content);
        $this->assertStringContainsString('startDialogBusy', $content);
        $this->assertSame(1, substr_count($content, 'startDialog(Number(u.id))'));
        $this->assertStringContainsString('Number(t.peer_id) !== Number(patch.peer_id)', $content);
        $this->assertStringContainsString("contactsSearch').value = ''", $content);
        $this->assertStringContainsString("contactsTeamFilter').value = ''", $content);
        $this->assertStringContainsString("params.set('team_id', teamId)", $content);
        $this->assertStringContainsString("getElementById('contactsTeamFilter').addEventListener('change'", $content);
        $this->assertStringContainsString('showContactsTeamError', $content);
        $this->assertStringContainsString('bootstrap.Modal.getOrCreateInstance', $content);
        $this->assertStringContainsString('ticksHtml', $content);
        $this->assertStringContainsString('checks-read', $content);
        $this->assertStringContainsString('syncMineReadStatus', $content);
        $this->assertStringContainsString('if (!window.Echo) return', $content);
        $this->assertStringNotContainsString('$.ajax', $content);
        $this->assertStringContainsString('function applyInboxBump(', $content);
        $this->assertStringContainsString('Number(e.unread_total) - Number(e.unread_count || 0)', $content);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump = applyInboxBump', $content);
        $this->assertStringContainsString('markThreadRead(threadId)', $content);
        $this->assertStringContainsString('last_message_is_mine', $content);
        $this->assertStringContainsString('function markListOutgoingRead(', $content);
        $this->assertStringContainsString('chat-online-dot', $content);
        $this->assertStringContainsString('contact-online-dot', $content);
        $this->assertStringContainsString('parent_full_name', $content);
        $this->assertStringContainsString('function openPeerCard(', $content);
        $this->assertStringContainsString('if (!id)', $content);
        $this->assertStringContainsString('function openGroupCard(', $content);
        $this->assertStringContainsString('function headerPeerActivate(', $content);
        $this->assertStringContainsString('function dashText(', $content);
        $this->assertStringContainsString('fmtTime(t.last_message_time)', $content);
        $this->assertStringContainsString("parentFio ? '<div class=\"contact-parent\">'", $content);
        $this->assertStringContainsString('last_seen_label', $content);
        $this->assertStringContainsString('threadPeerHit', $content);
        $this->assertStringContainsString('function loadAccountCard(', $content);
        $this->assertStringContainsString("renderPeerCard(res.data, 'accountCardBody')", $content);
        $this->assertStringContainsString('function showAccountCardError(', $content);
        $this->assertStringContainsString("fieldError(res.data, 'user')", $content);
        $this->assertStringContainsString("fieldError(res.data, 'after_user_id')", $content);
        $this->assertStringContainsString("fieldError(res.data, 'exclude_thread_id')", $content);
        $this->assertStringContainsString('function fetchGroupMembers(', $content);
        $this->assertStringContainsString('function submitRemoveGroupMember(', $content);
        $this->assertStringContainsString('function submitLeaveGroup(', $content);
        $this->assertStringContainsString('function submitAddGroupMembers(', $content);
        $this->assertStringContainsString('function maybeFillGroupMembers(', $content);
        $this->assertStringContainsString("params.set('exclude_thread_id'", $content);
        $this->assertStringContainsString('if (e.removed)', $content);
        $this->assertStringContainsString("showToast(message, 'success')", $content);
        $this->assertStringContainsString('e.stopPropagation()', $content);
        $this->assertStringContainsString('function setMobileTab(', $content);
        $this->assertStringContainsString("matchMedia('(max-width: 991.98px)')", $content);
        $this->assertStringContainsString('is-dialog-open', $content);
        $this->assertStringContainsString('function maybeLoadOlder(', $content);
        $this->assertStringContainsString('olderPrefetchThreshold', $content);
        $this->assertStringContainsString('preventPageZoom', $content);
        $this->assertStringNotContainsString('scrollTop < 40', $content);
        $this->assertStringNotContainsString('account-settings', $content);

        $setMobilePos = strpos($content, 'function setMobileTab(');
        $this->assertNotFalse($setMobilePos);
        $setMobileChunk = substr(
            $content,
            $setMobilePos,
            strpos($content, 'function leaveMobileDialog(') - $setMobilePos
        );
        $this->assertStringNotContainsString('contactsModal().show()', $setMobileChunk);
        $this->assertStringContainsString("tab === 'contacts'", $setMobileChunk);
        $this->assertStringContainsString("tab === 'account'", $setMobileChunk);
        $this->assertStringContainsString('loadContacts', $setMobileChunk);
        $this->assertStringContainsString('loadAccountCard', $setMobileChunk);
        $this->assertStringNotContainsString("tab === 'groups'", $setMobileChunk);

        $openContactsPos = strpos($content, "getElementById('openContactsBtn')");
        $this->assertNotFalse($openContactsPos);
        $openContactsChunk = substr($content, $openContactsPos, 700);
        $this->assertStringContainsString('contactsModal().show()', $openContactsChunk);
        $this->assertStringContainsString("contactsSearch').value = ''", $openContactsChunk);

        $this->assertStringContainsString("classList.remove('is-dialog-open')", $content);
        $mqPos = strpos($content, "matchMedia('(max-width: 991.98px)')");
        $this->assertNotFalse($mqPos);
        $mqTail = substr($content, $mqPos);
        $this->assertStringContainsString('placeContactsMount()', $mqTail);
        $this->assertStringContainsString('if (!isMobileChat() && root)', $mqTail);
        $this->assertStringContainsString('renderThreads(applyThreadFilter(threadsCache))', $mqTail);

        $blade = (string) file_get_contents(resource_path('views/chat/index.blade.php'));
        $stylesPos = strpos($blade, "@push('styles')");
        $scriptsPos = strpos($blade, "@push('scripts')");
        $this->assertNotFalse($stylesPos);
        $this->assertNotFalse($scriptsPos);
        $this->assertGreaterThan($stylesPos, $scriptsPos);
        $stylesChunk = substr($blade, $stylesPos, $scriptsPos - $stylesPos);
        $this->assertStringContainsString("@vite(['resources/css/chat.css'])", $stylesChunk);
        $this->assertStringContainsString("@vite(['resources/js/chat.js'])", substr($blade, $scriptsPos));
        $this->assertStringNotContainsString("@vite(['resources/js/chat.js'])", $stylesChunk);
        $this->assertStringNotContainsString("@vite(['resources/css/chat.css'])", substr($blade, $scriptsPos));
        $this->assertStringNotContainsString("asset('js/chat.js')", $blade);

        $vite = (string) file_get_contents(base_path('vite.config.js'));
        $this->assertStringContainsString("'resources/css/chat.css'", $vite);
        $this->assertStringContainsString("'resources/js/chat.js'", $vite);

        $renderThreadsPos = strpos($content, 'function renderThreads(');
        $this->assertNotFalse($renderThreadsPos);
        $renderThreadsChunk = substr(
            $content,
            $renderThreadsPos,
            strpos($content, 'function upsertThread(') - $renderThreadsPos
        );
        $this->assertStringNotContainsString('last_seen', $renderThreadsChunk);
        $this->assertStringNotContainsString('is-offline', $renderThreadsChunk);
        $this->assertStringContainsString('openThread(t.id)', $renderThreadsChunk);
        $this->assertStringContainsString('chat-li-unread', $renderThreadsChunk);
        $this->assertStringContainsString('Черновик: ', $renderThreadsChunk);
        $this->assertStringContainsString('is-draft', $renderThreadsChunk);
        $this->assertStringNotContainsString('bg-primary', $renderThreadsChunk);
        $this->assertStringNotContainsString('openPeerCard', $renderThreadsChunk);
        $this->assertStringContainsString("getElementById('groupThreads')", $renderThreadsChunk);
        $this->assertStringContainsString("filter(function (t) { return !t.is_group; })", $renderThreadsChunk);
        $this->assertStringContainsString("'Групп нет'", $renderThreadsChunk);
        $this->assertStringContainsString('threadsCache.filter', $renderThreadsChunk);
        $this->assertStringContainsString('paintSplitNavBadges', $renderThreadsChunk);

        $openThreadPos = strpos($content, 'function openThread(');
        $this->assertNotFalse($openThreadPos);
        $openThreadChunk = substr(
            $content,
            $openThreadPos,
            strpos($content, 'function olderPrefetchThreshold(') - $openThreadPos
        );
        $this->assertStringContainsString("currentIsGroup ? 'groups' : 'messages'", $openThreadChunk);
        $this->assertStringContainsString("tab = 'groups'", $openThreadChunk);
        $this->assertStringContainsString('openThread._seq', $openThreadChunk);
        $clearBoxPos = strpos($openThreadChunk, "box.innerHTML = ''");
        $fetchPos = strpos($openThreadChunk, 'fetch(threadUrl(threadId)');
        $this->assertNotFalse($clearBoxPos);
        $this->assertNotFalse($fetchPos);
        $this->assertLessThan(
            $fetchPos,
            $clearBoxPos,
            'На мобилке #messagesBox очищается до fetch, иначе видна переписка прошлого диалога'
        );
        $this->assertStringContainsString('if (isMobile && openSeq !== openThread._seq)', $openThreadChunk);

        $this->assertStringContainsString("querySelectorAll('.js-open-create-group')", $content);
        $submitCreatePos = strpos($content, 'function submitCreateGroup(');
        $this->assertNotFalse($submitCreatePos);
        $submitCreateChunk = substr(
            $content,
            $submitCreatePos,
            strpos($content, 'let contactsDebounce') - $submitCreatePos
        );
        $this->assertStringContainsString('upsertThread(Object.assign({ unread_count: 0 }, res.data.thread))', $submitCreateChunk);
        $this->assertStringContainsString('openThread(id)', $submitCreateChunk);

        $renderContactsPos = strpos($content, 'function renderContacts(');
        $this->assertNotFalse($renderContactsPos);
        $renderContactsChunk = substr(
            $content,
            $renderContactsPos,
            strpos($content, 'function loadContacts(') - $renderContactsPos
        );
        $this->assertStringContainsString('is-offline', $renderContactsChunk);
        $this->assertStringContainsString('contact-main', $renderContactsChunk);
        $this->assertStringContainsString('contact-team', $renderContactsChunk);
        $this->assertStringContainsString('contact-role', $renderContactsChunk);
        $this->assertStringNotContainsString('d-flex justify-content-between', $renderContactsChunk);
        $this->assertStringContainsString('startDialog(Number(u.id))', $renderContactsChunk);
        $this->assertStringNotContainsString('openPeerCard', $renderContactsChunk);
        $this->assertStringContainsString('u.role_label || u.role_name', $renderContactsChunk);
        $this->assertStringContainsString("escapeHtml(u.name || '')", $renderContactsChunk);
        $this->assertStringContainsString("parentFio ? '<div class=\"contact-parent\">'", $renderContactsChunk);

        $renderMembersPos = strpos($content, 'function renderGroupMembers(');
        $this->assertNotFalse($renderMembersPos);
        $renderMembersChunk = substr(
            $content,
            $renderMembersPos,
            strpos($content, 'function loadGroupMembers(') - $renderMembersPos
        );
        $this->assertStringContainsString("parentFio ? '<div class=\"contact-parent\">'", $renderMembersChunk);
        $this->assertStringContainsString('contact-name', $renderMembersChunk);
        $this->assertStringContainsString('group-member-row', $renderMembersChunk);
        $this->assertStringNotContainsString('contact-online-dot', $renderMembersChunk);
        $this->assertSame(
            2,
            substr_count($content, "parentFio ? '<div class=\"contact-parent\">'"),
            'И вкладка «Контакты», и модалка участников должны рисовать .contact-parent'
        );

        $this->assertSame(
            3,
            substr_count($content, 'u.role_label || u.role_name'),
            'Контакты, создание группы и добавление участников должны брать role_label, иначе колонка покажет superadmin'
        );
        $this->assertSame(1, substr_count($content, 'm.role_label || m.role_name'));
        $this->assertStringContainsString("escapeHtml(dashText(u.full_name))", $content);
        $this->assertStringContainsString("escapeHtml(m.full_name || '')", $content);

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in resources/js/chat.js:\n".implode("\n", $output)
        );
    }

    public function test_chat_echo_inbox_bump_defers_to_open_dialog_and_is_valid_javascript(): void
    {
        $path = resource_path('views/includes/chat/echo.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $start = strpos($content, "channel.listen('.inbox.bump'");
        $end = strpos($content, "channel.listen('.thread.read'");
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $listener = substr($content, $start, $end - $start);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump', $listener);
        $this->assertStringContainsString('return;', $listener);
        $this->assertLessThan(
            strpos($listener, 'applyUnread(payload.unread_total)'),
            strpos($listener, 'KidsCrmChatOnInboxBump')
        );
        $this->assertStringContainsString("querySelectorAll('.js-chat-unread-count')", $content);
        $this->assertStringNotContainsString('js-chat-private-unread-count', $content);
        $this->assertStringNotContainsString('js-chat-group-unread-count', $content);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'KidsCrmChatOnInboxBump',
            'blade-js-chat-echo-inbox'
        );
    }

    /**
     * P1: оверлей Reverb + Echo-клиент — синтаксис и UX-контракт
     * (процесс/сокет раздельно, connecting ≠ connected, без wsPath: '/app').
     */
    public function test_reverb_overlay_and_echo_client_contracts_are_valid_javascript(): void
    {
        $overlayPath = resource_path('views/includes/chat/reverb_status.blade.php');
        $echoPath = resource_path('views/includes/in_app_notifications/echo.blade.php');
        $this->assertFileExists($overlayPath);
        $this->assertFileExists($echoPath);

        $overlay = (string) file_get_contents($overlayPath);
        $echo = (string) file_get_contents($echoPath);

        $this->assertStringContainsString("credentials: 'same-origin'", $overlay);
        $this->assertStringContainsString("'Accept': 'application/json'", $overlay);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $overlay);
        $this->assertStringContainsString('setInterval(refreshProcess, 3000)', $overlay);
        $this->assertStringContainsString('setInterval(paint, 1000)', $overlay);
        $this->assertStringContainsString("connection.bind('state_change'", $overlay);
        $this->assertStringContainsString("state === 'connecting' || state === 'initialized'", $overlay);
        $this->assertStringContainsString("listening && sockKind === 'ok'", $overlay);
        $this->assertStringContainsString("listening || sockKind === 'warn'", $overlay);
        $this->assertStringContainsString('navigator.clipboard.writeText', $overlay);
        $this->assertStringContainsString("процесс: '", $overlay);
        $this->assertStringContainsString("сокет: '", $overlay);
        $this->assertStringNotContainsString('@can(', $overlay);
        $this->assertStringNotContainsString('messages.view', $overlay);

        $this->assertStringContainsString("broadcaster: 'reverb'", $echo);
        $this->assertStringContainsString('wsHost: window.location.hostname', $echo);
        $this->assertStringContainsString('forceTLS: true', $echo);
        $this->assertStringContainsString('wssPort: 443', $echo);
        $this->assertStringContainsString("enabledTransports: ['ws', 'wss']", $echo);
        $this->assertStringNotContainsString("wsPath: '/app'", $echo);
        $this->assertStringNotContainsString('wsPath: "/app"', $echo);
        $this->assertStringContainsString("route('presence.ping')", $echo);
        $this->assertStringContainsString('setInterval(ping, 60000)', $echo);
        $this->assertStringContainsString("method: 'POST'", $echo);
        $this->assertStringContainsString('credentials: \'same-origin\'', $echo);
        $pingStart = strpos($echo, 'var presenceUrl');
        $this->assertNotFalse($pingStart);
        $pingChunk = substr($echo, $pingStart, 900);
        $this->assertStringContainsString('function ping()', $pingChunk);
        $this->assertStringContainsString('ping();', $pingChunk);
        $this->assertStringNotContainsString("route('chat.index')", $pingChunk);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $overlayPath,
            'function refreshProcess()',
            'blade-js-reverb-overlay'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $echoPath,
            "broadcaster: 'reverb'",
            'blade-js-reverb-echo-client'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $echoPath,
            'setInterval(ping, 60000)',
            'blade-js-presence-ping'
        );
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
        $this->assertStringContainsString('empty-cell-context', $content);
        $this->assertStringContainsString('place-trial-lesson', $content);
        $this->assertStringContainsString('place-single-lesson', $content);
        $this->assertStringContainsString('openEmptyCellPlaceModal', $content);
        $this->assertStringContainsString('flexible_options', $content);
        $this->assertStringContainsString('data-flexible-remaining', $content);
        $this->assertStringContainsString('syncFlexibleEmptyCellAffordance', $content);
        $this->assertStringContainsString("data-mode', 'flexible'", $content);
        $this->assertStringContainsString('cell-status-option--disabled', $content);
        $this->assertStringContainsString('Достигнут лимит занятий по гибкому абонементу.', $content);
        $this->assertStringContainsString('data-flexible-remaining', $content);
        $this->assertStringContainsString('flexibleRemaining', $content);
        $this->assertStringContainsString("attr('data-empty-lesson') === '1'", $content);
        $this->assertStringContainsString('Пробное, разовое или занятие из гибкого абонемента', $content);
        $this->assertStringContainsString('emptyCellPlaceForm', $content);

        $cssPath = resource_path('css/schedule.css');
        $this->assertFileExists($cssPath);
        $css = (string) file_get_contents($cssPath);
        $this->assertStringContainsString('cell-status-option--disabled', $css);
        $this->assertStringContainsString('#eef0f2', $css);
        $this->assertStringContainsString('empty_cell_lesson_occurrence_status_id', $content);
        $this->assertStringContainsString('showEmptyCellErrors', $content);
        $this->assertStringContainsString('data-empty-lesson', $content);
        $this->assertStringContainsString('btn-add-flexible-lesson', $content);
        $this->assertStringContainsString('renderScheduleCellAfterFlexiblePlace', $content);
        $this->assertStringContainsString('updateFlexibleHintAfterPlace', $content);
        $this->assertStringContainsString('flexible_lesson_occurrence_status_id', $content);
        $this->assertStringContainsString('renderScheduleCellAfterFlexiblePlace($cell, result)', $content);
        $this->assertStringContainsString('renderScheduleCellFromResult', $content);
        $this->assertStringContainsString('renderScheduleCellAfterStatusSave', $content);
        $this->assertStringContainsString('syncFlexibleHintAfterAnnul', $content);
        $this->assertStringContainsString('cell-delete-confirm-name', $content);
        $this->assertStringContainsString('btn-cell-delete', $content);
        $this->assertStringContainsString('/schedule/occurrence/', $content);
        $this->assertStringContainsString('cellDeleteConfirmModal', $content);
        $this->assertStringContainsString('btn-cell-delete-confirm', $content);
        $this->assertStringContainsString('renderScheduleCellAfterDelete', $content);
        $this->assertStringContainsString('syncCellDeleteButton', $content);
        // Успех DELETE occurrence — точечный DOM без reload.
        $destroyPos = strpos($content, "url: '/schedule/occurrence/'");
        $this->assertNotFalse($destroyPos);
        $destroyChunk = substr($content, (int) $destroyPos, 1400);
        $this->assertStringContainsString('method: \'DELETE\'', $destroyChunk);
        $this->assertStringContainsString('renderScheduleCellFromResult', $destroyChunk);
        $this->assertStringContainsString('syncFlexibleHintAfterAnnul', $destroyChunk);
        $this->assertStringNotContainsString('window.location.reload()', $destroyChunk);
        $this->assertStringContainsString('syncFlexibleTrainerBlock', $content);
        $this->assertStringContainsString('populateFlexibleTrainerSelect', $content);
        $this->assertStringContainsString('showFlexibleErrors', $content);
        // Мультитренеры при «Посетил»: generic multiselect + trainer_profile_ids[].
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2', $content);
        $this->assertStringContainsString('populateTrainerMultiselect', $content);
        $this->assertStringContainsString('trainerIdsForVisited', $content);
        $this->assertStringContainsString('defaultTrainerIdsFromContext', $content);
        $this->assertStringContainsString('selectedTrainerNames', $content);
        // UX: сохранённый «Посетил» без тренеров не подменяется team_default.
        $trainerIdsFnPos = strpos($content, 'function trainerIdsForVisited');
        $this->assertNotFalse($trainerIdsFnPos);
        $trainerIdsFnChunk = substr($content, (int) $trainerIdsFnPos, 1400);
        $this->assertStringContainsString('isVisitedStatusId(ctx.current_status_id)', $trainerIdsFnChunk);
        $this->assertStringContainsString('trainer_profile_ids_for_select', $trainerIdsFnChunk);
        $this->assertStringContainsString('team_default_trainer_profile_id', $trainerIdsFnChunk);
        $this->assertStringContainsString('cell-trainer-profile-ids', $content);
        $this->assertStringContainsString('flexible-trainer-profile-ids', $content);
        $this->assertStringContainsString('trainer_profile_ids', $content);
        $this->assertStringContainsString('trainer_profile_ids_for_select', $content);
        // Дефолт: при «Посетил» без сохранённых — team_default; при не-Посетил — clear.
        $this->assertStringContainsString('team_default_trainer_profile_id', $content);
        $this->assertStringContainsString('clearTrainerMultiselect', $content);
        $this->assertStringContainsString("names.join(', ')", $content);
        // Ошибки валидации под мультиселектом (не только legacy trainer_profile_id).
        $this->assertStringContainsString('errors.trainer_profile_ids', $content);
        $this->assertStringContainsString('KidsCrmGenericMultiselectSelect2.markInvalid', $content);
        // Успех place-flexible обновляет DOM без reload (reload остаётся у других потоков журнала).
        $flexibleSubmitPos = strpos($content, "url: '/schedule/user/' + userId + '/place-flexible-abonement'");
        $this->assertNotFalse($flexibleSubmitPos);
        $flexibleSubmitChunk = substr($content, (int) $flexibleSubmitPos, 1400);
        $this->assertStringContainsString('trainer_profile_ids: trainerIds', $flexibleSubmitChunk);
        $this->assertStringContainsString("enrichResultTrainerNameFromSelect(result, '#flexible-trainer-profile-ids')", $flexibleSubmitChunk);
        $this->assertStringContainsString('renderScheduleCellAfterFlexiblePlace', $flexibleSubmitChunk);
        $this->assertStringContainsString('updateFlexibleHintAfterPlace', $flexibleSubmitChunk);
        $this->assertStringNotContainsString('window.location.reload()', $flexibleSubmitChunk);
        // schedule.update: при не-Посетил не отправляем trainer_profile_ids[].
        $cellEditSubmitPos = strpos($content, "$('#cellEditForm').on('submit'");
        $this->assertNotFalse($cellEditSubmitPos);
        $cellEditSubmitChunk = substr($content, (int) $cellEditSubmitPos, 1800);
        $this->assertStringContainsString('preventDefault', $cellEditSubmitChunk);
        $this->assertStringContainsString("item.name !== 'trainer_profile_ids[]'", $cellEditSubmitChunk);
        // Успех place-trial / place-single (пустая ячейка) — точечный DOM без reload.
        $this->assertStringContainsString("'/schedule/user/' + userId + '/place-trial-lesson'", $content);
        $this->assertStringContainsString("'/schedule/user/' + userId + '/place-single-lesson'", $content);
        $emptyCellFormPos = strpos($content, "$('#emptyCellPlaceForm').on('submit'");
        $this->assertNotFalse($emptyCellFormPos);
        $emptyCellChunk = substr($content, (int) $emptyCellFormPos, 3500);
        $this->assertStringContainsString('preventDefault', $emptyCellChunk);
        $this->assertStringContainsString('place-single-lesson', $emptyCellChunk);
        $this->assertStringContainsString('renderScheduleCellFromResult', $emptyCellChunk);
        $this->assertStringNotContainsString('window.location.reload()', $emptyCellChunk);
        // Успех schedule.update (модалка «Статус занятия») — точечный DOM без reload.
        $updateSubmitPos = strpos($content, "url: '/schedule/update'");
        $this->assertNotFalse($updateSubmitPos);
        $updateSubmitChunk = substr($content, (int) $updateSubmitPos, 1200);
        $this->assertStringContainsString('renderScheduleCellAfterStatusSave', $updateSubmitChunk);
        $this->assertStringNotContainsString('window.location.reload()', $updateSubmitChunk);
        $statusSaveFnPos = strpos($content, 'function renderScheduleCellAfterStatusSave');
        $this->assertNotFalse($statusSaveFnPos);
        $statusSaveFnChunk = substr($content, (int) $statusSaveFnPos, 500);
        $this->assertStringContainsString('updateFlexibleHintAfterPlace', $statusSaveFnChunk);
        $this->assertStringContainsString('/schedule/update', $content);
        $this->assertStringContainsString('preventDefault', $content);
        $this->assertStringContainsString("Accept': 'application/json'", $content);
        $this->assertStringContainsString('$.ajax', $content);
        $this->assertStringContainsString('fillAbonementForm', $content);
        $this->assertStringContainsString('fillAbonementUlpOptions', $content);
        $this->assertStringContainsString('renderAbonementTeamUi', $content);
        $this->assertStringContainsString('applySelectedUlpPeriodUi', $content);
        $this->assertStringContainsString('abonement-team-readonly', $content);
        $this->assertStringContainsString('abonement-team-display', $content);
        $this->assertStringContainsString('flexible-team-display', $content);
        $this->assertStringContainsString('context_team_id', $content);
        $this->assertStringContainsString('team_locked', $content);
        $this->assertStringContainsString('scheduleJournalFilterTeamId', $content);
        $this->assertStringContainsString('syncAbonementStartDateQuickPicks', $content);
        $this->assertStringContainsString('abonement-start-date-quick', $content);
        $this->assertStringContainsString('formatAbonementPreviewText', $content);
        $this->assertStringContainsString('formatDateHumanYmd', $content);
        $this->assertStringContainsString("'Занятий: '", $content);
        $this->assertStringContainsString("' занятие: '", $content);
        $this->assertStringContainsString('flexibleAbonementColumnLabel', $content);
        $this->assertStringContainsString('flexibleAbonementColumnHoverLine', $content);
        $this->assertStringContainsString('formatFeeRubFromCents', $content);
        $this->assertStringContainsString('fee_amount_cents', $content);
        $this->assertStringContainsString('applyTrainerHoverToCellText', $content);
        $this->assertStringContainsString('stripTrainerHoverLines', $content);
        $this->assertStringContainsString('enrichResultTrainerNameFromSelect', $content);
        $this->assertStringContainsString('setCellEditTeamDisplay', $content);
        $this->assertStringContainsString('trainer_name', $content);
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

        // Смена фильтра группы — полный GET (сервер пересчитывает галочку оплаты), не патч иконки в DOM.
        $filterChangePos = strpos($content, "$('.schedule-filter-year, .schedule-filter-month, .schedule-filter-team').on('change'");
        $this->assertNotFalse($filterChangePos);
        $filterChunk = substr($content, (int) $filterChangePos, 700);
        $this->assertStringContainsString("newUrl.searchParams.set('team', $('#filter-team').val())", $filterChunk);
        $this->assertStringContainsString('window.location.href = newUrl.toString()', $filterChunk);
        $this->assertStringNotContainsString('data-journal-payment-status', $filterChunk);
        $this->assertStringNotContainsString('journalPaymentStatuses', $content);

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
     * Общего компонента прелоадера нет: он ломал AdminLTE. Остался только журнал.
     */
    public function test_kids_table_preloader_component_and_datatable_bind_contract(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/components/ui/table-preloader.blade.php'));

        $jsPath = resource_path('js/kids-datatable.js');
        $this->assertFileExists($jsPath);
        $js = (string) file_get_contents($jsPath);
        $this->assertStringNotContainsString('KidsCrmTablePreloader', $js);
        $this->assertStringNotContainsString('bindTablePreloader', $js);
        $this->assertStringNotContainsString('__bindsTablePreloader', $js);

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($jsPath).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in {$jsPath}:\n".implode("\n", $output)
        );
    }

    /**
     * Журнал: локальный прелоадер #schedule-journal-stage, без общего компонента.
     * Снятие: revealScheduleJournalTable() сразу после DataTable() (SSR-таблица).
     */
    public function test_schedule_journal_table_preloader_contract(): void
    {
        $blade = resource_path('views/admin/schedule/journal.blade.php');
        $this->assertFileExists($blade);
        $journal = (string) file_get_contents($blade);
        $this->assertStringContainsString('id="schedule-journal-stage"', $journal);
        $this->assertStringContainsString('schedule-journal-preloader', $journal);
        $this->assertStringContainsString('schedule-journal-pagination', $journal);
        $this->assertStringNotContainsString('<x-ui.table-preloader', $journal);
        $this->assertStringNotContainsString('kids-table-preloader', $journal);
        $this->assertStringNotContainsString('Загрузка расписания', $journal);
        $stagePos = strpos($journal, 'id="schedule-journal-stage"');
        $pagerPos = strpos($journal, 'schedule-journal-pagination');
        $this->assertNotFalse($stagePos);
        $this->assertNotFalse($pagerPos);
        $this->assertGreaterThan($stagePos, $pagerPos);

        $index = resource_path('views/admin/schedule/index.blade.php');
        $indexContent = (string) file_get_contents($index);
        $stylesPos = strpos($indexContent, "@push('styles')");
        $scriptsPos = strpos($indexContent, "@push('scripts')");
        $this->assertNotFalse($stylesPos);
        $this->assertNotFalse($scriptsPos);
        $stylesChunk = substr($indexContent, $stylesPos, $scriptsPos - $stylesPos);
        $this->assertStringContainsString("@vite(['resources/css/schedule.css'])", $stylesChunk);
        $this->assertStringContainsString("asset('css/schedule-journal-cells.css')", $stylesChunk);
        $this->assertStringContainsString('#schedule-journal-stage:not(.is-ready)', $stylesChunk);
        $this->assertStringContainsString('.schedule-journal-preloader', $stylesChunk);
        $this->assertStringContainsString('.schedule-fullscreen-wrapper.fullscreen .schedule-journal-preloader', $stylesChunk);
        $this->assertStringNotContainsString('kids-table-preloader', $stylesChunk);
        $this->assertStringNotContainsString("@vite(['resources/css/schedule.css'])", substr($indexContent, $scriptsPos));

        $css = (string) file_get_contents(resource_path('css/schedule.css'));
        $this->assertStringContainsString('.schedule-fullscreen-wrapper.fullscreen .schedule-journal-preloader', $css);
        $this->assertStringNotContainsString('.kids-table-preloader', $css);

        $this->assertStringContainsString('height: 12rem', $stylesChunk);
        $this->assertStringContainsString('overflow: hidden', $stylesChunk);
        $this->assertStringContainsString('visibility: hidden', $stylesChunk);
        $this->assertStringContainsString('display: none !important', $stylesChunk);
        $this->assertStringContainsString('background: #f4f6f9', $stylesChunk);
        $this->assertStringContainsString('<noscript>', $stylesChunk);
        $this->assertStringContainsString('asset(\'js/schedule-journal.js\')', $indexContent);

        $sourceJs = (string) file_get_contents(resource_path('js/schedule.js'));
        $hotfixJs = (string) file_get_contents(public_path('js/schedule-journal.js'));
        $this->assertScheduleJournalRevealAfterDataTableContract($sourceJs, resource_path('js/schedule.js'));
        $this->assertScheduleJournalRevealAfterDataTableContract($hotfixJs, public_path('js/schedule-journal.js'));
        $this->assertSame(
            $this->scheduleJournalRevealSnippet($sourceJs),
            $this->scheduleJournalRevealSnippet($hotfixJs),
            'hotfix public/js/schedule-journal.js должен снимать прелоадер так же, как resources/js/schedule.js'
        );
    }

    /**
     * P1: колонка оплаты месяца в журнале — серверный HTML + tooltip-hint, без legacy userPrices.
     * Смена фильтра группы в hotfix-копии schedule-journal.js тоже полный GET.
     */
    public function test_schedule_journal_monthly_payment_column_blade_and_filter_js_contract(): void
    {
        $blade = resource_path('views/admin/schedule/journal.blade.php');
        $this->assertFileExists($blade);
        $content = (string) file_get_contents($blade);

        $this->assertStringContainsString("partials.ui.tooltip-hint", $content);
        $this->assertStringContainsString('journalPaymentStatuses', $content);
        $this->assertStringContainsString('data-journal-payment-status', $content);
        $this->assertStringContainsString('journal-monthly-payment-hint', $content);
        $this->assertStringContainsString("payState === 'paid' || \$payState === 'partial'", $content);
        $this->assertStringContainsString("'iconClass' => \$payIcon", $content);
        $this->assertStringContainsString("'wrapperClass' => 'journal-monthly-payment-hint'", $content);
        $this->assertStringNotContainsString('$userPrices[$user->id]', $content);
        $this->assertStringNotContainsString('is_paid == 1', $content);
        $this->assertStringContainsString('id="filter-team"', $content);
        $this->assertStringContainsString('value="all"', $content);
        $this->assertStringContainsString('value="none"', $content);

        $hint = resource_path('views/partials/ui/tooltip-hint.blade.php');
        $this->assertFileExists($hint);
        $hintContent = (string) file_get_contents($hint);
        $this->assertStringContainsString('data-kids-tooltip-hint', $hintContent);
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $hintContent);
        $this->assertStringContainsString('ulp-assignment-paid-tooltip', $hintContent);
        $this->assertStringContainsString('wrapperClass', $hintContent);
        $this->assertStringContainsString('iconClass', $hintContent);
        $this->assertStringNotContainsString('<script', $hintContent);

        foreach ([
            resource_path('js/schedule.js'),
            public_path('js/schedule-journal.js'),
        ] as $jsPath) {
            $this->assertFileExists($jsPath);
            $js = (string) file_get_contents($jsPath);
            $this->assertStringContainsString("newUrl.searchParams.set('team', $('#filter-team').val())", $js);
            $this->assertStringContainsString('window.location.href = newUrl.toString()', $js);
            $this->assertStringNotContainsString('data-journal-payment-status', $js);

            $output = [];
            $exitCode = 0;
            exec('node --check '.escapeshellarg($jsPath).' 2>&1', $output, $exitCode);
            $this->assertSame(
                0,
                $exitCode,
                "JS syntax error in {$jsPath}:\n".implode("\n", $output)
            );
        }
    }

    /**
     * P1: пагинация журнала — GET-поиск без hidden page; смена года/месяца/группы
     * в обеих копиях JS сбрасывает page и не трогает q. journal.blade.php без inline <script>.
     */
    public function test_schedule_journal_pagination_and_search_js_contract(): void
    {
        $blade = resource_path('views/admin/schedule/journal.blade.php');
        $this->assertFileExists($blade);
        $journal = (string) file_get_contents($blade);

        $this->assertStringNotContainsString('<script', $journal);
        $this->assertStringContainsString('<form method="get" action="{{ route(\'schedule.index\') }}"', $journal);
        $this->assertStringContainsString('<input type="hidden" name="year" value="{{ $year }}">', $journal);
        $this->assertStringContainsString('<input type="hidden" name="month" value="{{ $month }}">', $journal);
        $this->assertStringContainsString('<input type="hidden" name="team" value="{{ $team_id }}">', $journal);
        $this->assertStringNotContainsString('name="page"', $journal);
        $this->assertStringContainsString('id="table-search"', $journal);
        $this->assertStringContainsString('name="q"', $journal);
        $this->assertStringContainsString('value="{{ $searchQ ?? \'\' }}"', $journal);
        $this->assertStringContainsString('Найти', $journal);
        $this->assertStringContainsString("@error('year')", $journal);
        $this->assertStringContainsString("@error('month')", $journal);
        $this->assertStringContainsString("@error('team')", $journal);
        $this->assertStringContainsString("@error('q')", $journal);
        $this->assertStringContainsString('$users->lastPage() > 1', $journal);
        $this->assertStringContainsString('schedule-journal-pagination', $journal);
        $this->assertStringContainsString('($users->firstItem() ?? 1) + $index', $journal);

        $yearPos = strpos($journal, 'id="filter-year"');
        $monthPos = strpos($journal, 'id="filter-month"');
        $teamPos = strpos($journal, 'id="filter-team"');
        $searchPos = strpos($journal, 'id="table-search"');
        $this->assertNotFalse($yearPos);
        $this->assertNotFalse($monthPos);
        $this->assertNotFalse($teamPos);
        $this->assertNotFalse($searchPos);
        $this->assertLessThan($monthPos, $yearPos);
        $this->assertLessThan($teamPos, $monthPos);
        $this->assertLessThan($searchPos, $teamPos);

        foreach ([
            resource_path('js/schedule.js'),
            public_path('js/schedule-journal.js'),
        ] as $jsPath) {
            $this->assertFileExists($jsPath);
            $js = (string) file_get_contents($jsPath);

            $this->assertStringContainsString(
                "$('.schedule-filter-year, .schedule-filter-month, .schedule-filter-team').on('change'",
                $js
            );
            $setTeam = "newUrl.searchParams.set('team', $('#filter-team').val())";
            $deletePage = "newUrl.searchParams.delete('page')";
            $this->assertStringContainsString($setTeam, $js);
            $this->assertStringContainsString($deletePage, $js);
            $this->assertGreaterThan(
                strpos($js, $setTeam),
                strpos($js, $deletePage),
                "{$jsPath}: delete('page') должен идти после set('team') при смене фильтра"
            );
            $this->assertStringNotContainsString("newUrl.searchParams.delete('q')", $js);
            $this->assertStringContainsString('paging: false', $js);
            $this->assertStringContainsString("$('#table-search').on('keyup'", $js);
            $this->assertStringContainsString('table.search(this.value).draw()', $js);
            $this->assertStringContainsString('window.location.href = newUrl.toString()', $js);

            $output = [];
            $exitCode = 0;
            exec('node --check '.escapeshellarg($jsPath).' 2>&1', $output, $exitCode);
            $this->assertSame(
                0,
                $exitCode,
                "JS syntax error in {$jsPath}:\n".implode("\n", $output)
            );
        }
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
        $this->assertStringContainsString('id="abonement-user-name"', $content);
        $this->assertStringContainsString('id="abonement-team-display"', $content);
        $this->assertStringContainsString('id="abonement-team-id"', $content);
        $this->assertStringContainsString('id="abonement-team-readonly"', $content);
        $this->assertStringContainsString('Выберите группу', $content);
        $this->assertStringContainsString('id="abonement-ulp-id"', $content);
        $this->assertStringContainsString('id="abonement-weekdays"', $content);
        $this->assertStringContainsString('abonement-weekdays-legend', $content);
        $this->assertStringContainsString('abonement-weekdays-legend__title', $content);
        $this->assertStringContainsString('Подсказка', $content);
        $this->assertStringContainsString('день недели согласно расписанию', $content);
        $this->assertStringContainsString('на этот день недели вы установите расписание', $content);
        $this->assertStringContainsString('id="abonement-start-date"', $content);
        $this->assertStringContainsString('id="abonement-start-date-error"', $content);
        $this->assertStringContainsString('id="abonement-start-date-quick"', $content);
        $this->assertStringContainsString('id="abonement-start-quick-month-start"', $content);
        $this->assertStringContainsString('id="abonement-start-quick-today"', $content);
        $this->assertStringContainsString('id="abonement-ends-at"', $content);
        $this->assertStringNotContainsString(
            'id="abonement-start-date" name="start_date" required',
            $content
        );

        $this->assertStringContainsString('id="flexiblePlaceModal"', $content);
        $this->assertStringContainsString('id="flexiblePlaceForm" novalidate', $content);
        $this->assertStringContainsString('id="flexible-team-display"', $content);
        $this->assertStringContainsString('id="flexible-user-name"', $content);
        $this->assertStringContainsString('id="btn-cell-delete"', $content);
        $this->assertStringContainsString('id="cellDeleteConfirmModal"', $content);
        $this->assertStringContainsString('id="btn-cell-delete-confirm"', $content);
        $this->assertStringContainsString('id="cell-delete-confirm-name"', $content);
        $this->assertStringContainsString('id="cell-delete-confirm-date"', $content);
        $this->assertStringContainsString('id="cell-delete-confirm-hint"', $content);
        $this->assertStringContainsString('id="flexible-team-error"', $content);
        $this->assertStringContainsString('id="flexible-ulp-error"', $content);
        $this->assertStringContainsString('id="flexible-date-error"', $content);
        $this->assertStringContainsString('id="flexible-status-error"', $content);
        $this->assertStringContainsString('name="flexible_lesson_occurrence_status_id"', $content);
        $this->assertStringContainsString('id="flexible-trainer-wrap"', $content);
        $this->assertStringContainsString('id="flexible-trainer-profile-ids"', $content);
        $this->assertStringContainsString('name="trainer_profile_ids[]"', $content);
        $this->assertStringContainsString('js-generic-multiselect-select', $content);
        $this->assertStringContainsString('id="cell-trainer-wrap"', $content);
        $this->assertStringContainsString('id="cell-trainer-profile-ids"', $content);
        $this->assertStringContainsString('id="flexible-comment"', $content);
        $this->assertStringContainsString('id="btn-add-flexible-lesson"', $content);
        $this->assertStringContainsString('journal-flexible-hint', $content);
        $this->assertStringContainsString('journal-flexible-hint--ratio', $content);
        $this->assertStringContainsString('journal-flexible-hint--multi', $content);
        $this->assertStringContainsString('data-flexible=', $content);
        $this->assertStringContainsString('data-flexible-remaining', $content);
        $this->assertStringContainsString('data-flexible-ulp-id', $content);
        $this->assertStringContainsString('data-slots-remaining', $content);
        $this->assertStringContainsString('data-lessons-total', $content);
        $this->assertStringContainsString('data-flexible-items', $content);
        $this->assertStringContainsString('id="emptyCellPlaceModal"', $content);
        $this->assertStringContainsString('id="emptyCellPlaceForm" novalidate', $content);
        $this->assertStringContainsString('id="empty-cell-choice-error"', $content);
        $this->assertStringContainsString('id="empty-cell-team-error"', $content);
        $this->assertStringContainsString('id="empty-cell-team-display"', $content);
        $this->assertStringContainsString('id="empty-cell-fee-error"', $content);
        $this->assertStringContainsString('id="empty-cell-status-error"', $content);
        $this->assertStringContainsString('name="empty_cell_lesson_occurrence_status_id"', $content);
        $this->assertStringContainsString('id="empty-cell-trainer-wrap"', $content);
        $this->assertStringContainsString('id="empty-cell-fee-amount"', $content);
        $this->assertStringContainsString('kids-user-discount-price-wrap', $content);
        $this->assertStringContainsString('id="btnEmptyCellPlace"', $content);
        $this->assertStringContainsString('data-empty-lesson=', $content);
        $this->assertStringContainsString('id="edit-user-teams-display"', $content);
        $this->assertStringContainsString('class="abonement-start-quick-link"', $content);
        $this->assertStringContainsString('journal-postpay-hint', $content);
        $this->assertStringNotContainsString('empty-cell-choice-summary', $content);
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
        $this->assertStringContainsString('/admin/setting-prices/manual-paid', $content);
        $this->assertStringContainsString('postManualPaid', $content);
        $this->assertStringContainsString('showManualPaidCommentModal', $content);
        $this->assertStringContainsString('user-manual-paid-select', $content);
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
            'showSuccessModal("Установка цен в одной группе"',
            $content
        );
        $this->assertStringContainsString(
            "window.showToast('Цены ученикам в выбранной группе успешно обновлены.', 'success')",
            $content
        );
        $this->assertStringContainsString(
            "window.showToast('Изменения сохранены.', 'success')",
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
     * P1: вкладка «по месяцам» — % у цены ученика по снимку строки; левая колонка группы без %.
     */
    public function test_setting_prices_monthly_vite_discount_badge_uses_applied_snapshot_not_team_price(): void
    {
        $path = resource_path('js/settings-prices.js');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('function appliedDiscountPercent(up)', $content);
        $this->assertStringContainsString('function currentUserDiscountPercent(up, userTeam)', $content);
        $this->assertStringContainsString('function payableRubAfterUserDiscount(amountRub, percent)', $content);
        $this->assertStringContainsString('api.wrapPriceHtml(', $content);
        $this->assertStringContainsString('appliedPct', $content);
        $this->assertStringContainsString('up.applied_discount_comment', $content);
        $this->assertStringContainsString('api.hideBadge($wrap.get(0))', $content);
        $this->assertStringContainsString('kids-user-discount-price-wrap', $content);

        $wrapPos = strpos($content, 'api.wrapPriceHtml(');
        $this->assertNotFalse($wrapPos);
        $wrapChunk = substr($content, (int) $wrapPos, 400);
        $this->assertStringContainsString('appliedPct', $wrapChunk);
        $this->assertStringNotContainsString('setting-prices-team-price-value', $wrapChunk);

        $teamUiPos = strpos($content, 'function syncTeamRowPackageUi');
        $this->assertNotFalse($teamUiPos);
        $teamChunk = substr($content, (int) $teamUiPos, 2500);
        $this->assertStringContainsString('setting-prices-team-price-value', $teamChunk);
        $this->assertStringNotContainsString('wrapPriceHtml', $teamChunk);
        $this->assertStringNotContainsString('KidsCrmUserDiscount', $teamChunk);

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in resources/js/settings-prices.js (discount):\n".implode("\n", $output)
        );
    }

    /**
     * P1: Vite-модуль KidsCrmDataTable — preset money с копейками (formatRub-логика).
     */
    public function test_kids_datatable_money_preset_formats_kopecks_and_has_valid_javascript_syntax(): void
    {
        $path = resource_path('js/kids-datatable.js');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString("case 'money':", $content);
        $this->assertStringContainsString('dt-col-money-value', $content);
        $this->assertStringContainsString('Math.round(num * 100)', $content);
        $this->assertStringContainsString("padStart(2, '0')", $content);
        $this->assertStringContainsString('toLocaleString(\'ru-RU\')', $content);
        // Не truncate через parseInt — копейки должны сохраняться в display.
        $moneyCasePos = strpos($content, "case 'money':");
        $this->assertNotFalse($moneyCasePos);
        $moneyChunk = substr($content, (int) $moneyCasePos, 1200);
        $this->assertStringNotContainsString('parseInt(value, 10)', $moneyChunk);

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in resources/js/kids-datatable.js:\n".implode("\n", $output)
        );
    }

    /**
     * P1: видимость колонок KidsCrmDataTable — по ключу, не по индексу.
     * Иначе после смены порядка столбцов скрытый «Телефон» уедет на чужую колонку.
     */
    public function test_kids_datatable_applies_column_visibility_by_key_not_index(): void
    {
        $path = resource_path('js/kids-datatable.js');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('function buildColumnsMap(columnDefinitions)', $content);
        $this->assertStringContainsString('map[col.key] = index', $content);
        $this->assertStringContainsString('function applyVisibleColumns(config)', $content);
        $this->assertStringContainsString('const colIndex = columnsMap[key];', $content);
        $this->assertStringContainsString('toBool(config[key], defaultColumnsVisibility[key])', $content);
        $this->assertStringContainsString('[data-column-key="\' + key + \'"]', $content);
        $this->assertStringNotContainsString('config[colIndex]', $content);
        $this->assertStringNotContainsString('config[index]', $content);
        $this->assertStringContainsString('function bindPageLengthPersist(table, settings)', $content);
        $this->assertStringContainsString('settings.persistPageLength !== true', $content);
        $this->assertStringContainsString('page_length: length', $content);
        $this->assertStringContainsString('kids-dt-page-length-error', $content);
        $this->assertStringContainsString("table.on('length.kidsCrmPageLength'", $content);

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in resources/js/kids-datatable.js:\n".implode("\n", $output)
        );
    }

    /**
     * P1: persistPageLength шлёт только page_length, не номер страницы и не columns.
     * UX-баг: если вместе с length уйдут columns=defaults — скрытые колонки вспыхнут после смены «Показать N».
     */
    public function test_kids_datatable_page_length_persist_posts_only_page_length_not_page_number(): void
    {
        $path = resource_path('js/kids-datatable.js');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $fnStart = strpos($content, 'function pageLengthErrorHost(wrapper)');
        $this->assertNotFalse($fnStart);
        $fnEnd = strpos($content, 'function ensureTableScrollHost', $fnStart);
        $this->assertNotFalse($fnEnd);
        $chunk = substr($content, $fnStart, $fnEnd - $fnStart);

        $this->assertStringContainsString('function bindPageLengthPersist(table, settings)', $chunk);
        $this->assertStringContainsString('settings.persistPageLength !== true', $chunk);
        $this->assertStringContainsString("table.on('length.kidsCrmPageLength'", $chunk);
        $this->assertStringContainsString('page_length: length', $chunk);
        $this->assertStringContainsString("firstValidationError(xhr, 'page_length')", $chunk);
        $this->assertStringContainsString('.dataTables_length', $chunk);
        $this->assertStringContainsString('kids-dt-page-length-error', $chunk);
        $this->assertStringNotContainsString('start:', $chunk);
        $this->assertStringNotContainsString('columns:', $chunk);
        $this->assertStringNotContainsString('draw:', $chunk);

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in resources/js/kids-datatable.js:\n".implode("\n", $output)
        );
    }

    /**
     * P1: persistPageLength только на согласованных таблицах.
     * Иначе «Показать N» начнёт писать в чужие columns-settings.
     */
    public function test_only_opted_in_blades_enable_page_length_persist(): void
    {
        $hits = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (! str_contains($contents, 'persistPageLength: true')) {
                continue;
            }

            $relative = str_replace(
                resource_path('views').DIRECTORY_SEPARATOR,
                '',
                $file->getPathname()
            );
            $hits[] = str_replace('\\', '/', $relative);
        }

        sort($hits);

        $this->assertSame([
            'admin/partners/tabs/payouts.blade.php',
            'admin/report/fiscal_receipts.blade.php',
            'admin/report/ltv.blade.php',
            'admin/report/payment.blade.php',
            'admin/report/payment_intents.blade.php',
            'admin/report/payment_monthly.blade.php',
            'admin/school-leads/tabs/leads.blade.php',
            'admin/user.blade.php',
        ], $hits);

        $optIns = [
            "KidsCrmDataTable.create('#leads-table'" => [
                'file' => resource_path('views/admin/school-leads/tabs/leads.blade.php'),
                'pageLength' => 'pageLength: @json((int) ($leadsPageLength ?? 10))',
                'prefix' => 'blade-js-school-leads-page-length',
            ],
            "KidsCrmDataTable.create('#users-table'" => [
                'file' => resource_path('views/admin/user.blade.php'),
                'pageLength' => 'pageLength: @json((int) ($usersPageLength ?? 10))',
                'prefix' => 'blade-js-users-page-length',
            ],
            "KidsCrmDataTable.create('#payments-table'" => [
                'file' => resource_path('views/admin/report/payment.blade.php'),
                'pageLength' => 'pageLength: @json((int) ($paymentsPageLength ?? 10))',
                'prefix' => 'blade-js-payments-page-length',
            ],
            "KidsCrmDataTable.create('#payments-monthly-table'" => [
                'file' => resource_path('views/admin/report/payment_monthly.blade.php'),
                'pageLength' => 'pageLength: @json((int) ($paymentsMonthlyPageLength ?? 10))',
                'prefix' => 'blade-js-payments-monthly-page-length',
            ],
            "KidsCrmDataTable.create('#ltv-table'" => [
                'file' => resource_path('views/admin/report/ltv.blade.php'),
                'pageLength' => 'pageLength: @json((int) ($ltvPageLength ?? 10))',
                'prefix' => 'blade-js-ltv-page-length',
            ],
            "KidsCrmDataTable.create('#payment-intents-table'" => [
                'file' => resource_path('views/admin/report/payment_intents.blade.php'),
                'pageLength' => 'pageLength: @json((int) ($paymentIntentsPageLength ?? 10))',
                'prefix' => 'blade-js-payment-intents-page-length',
            ],
            "KidsCrmDataTable.create('#fiscal-receipts-table'" => [
                'file' => resource_path('views/admin/report/fiscal_receipts.blade.php'),
                'pageLength' => 'pageLength: @json((int) ($fiscalReceiptsPageLength ?? 10))',
                'prefix' => 'blade-js-fiscal-receipts-page-length',
            ],
            "KidsCrmDataTable.create('#payouts-table'" => [
                'file' => resource_path('views/admin/partners/tabs/payouts.blade.php'),
                'pageLength' => 'pageLength: @json((int) ($payoutsPageLength ?? 10))',
                'prefix' => 'blade-js-payouts-page-length',
            ],
        ];

        foreach ($optIns as $createNeedle => $meta) {
            $contents = (string) file_get_contents($meta['file']);
            $createPos = strpos($contents, $createNeedle);
            $this->assertNotFalse($createPos, $createNeedle);
            $chunk = substr($contents, $createPos, 4500);
            $this->assertStringContainsString('persistPageLength: true', $chunk);
            $this->assertStringContainsString($meta['pageLength'], $chunk);
            $this->assertInlineScriptsContainingHaveValidJavascript(
                $meta['file'],
                $createNeedle,
                $meta['prefix']
            );
        }
    }

    /**
     * P1: вложенные таблицы LTV/monthly — отдельный DataTable, не пресет.
     * UX-баг: если туда попадёт сохранённый N основной таблицы, детализация «прыгнет» на 50/100.
     */
    public function test_monthly_and_ltv_detail_tables_hardcode_ten_and_do_not_persist(): void
    {
        $monthly = (string) file_get_contents(resource_path('views/admin/report/payment_monthly.blade.php'));
        $monthlyFn = strpos($monthly, 'function initMonthlyPaymentsDetailTable');
        $this->assertNotFalse($monthlyFn);
        $monthlyCreate = strpos($monthly, "KidsCrmDataTable.create('#payments-monthly-table'");
        $this->assertNotFalse($monthlyCreate);
        $monthlyDetail = substr($monthly, $monthlyFn, $monthlyCreate - $monthlyFn);
        $this->assertStringContainsString('pageLength: 10', $monthlyDetail);
        $this->assertStringNotContainsString('persistPageLength', $monthlyDetail);
        $this->assertStringNotContainsString('$paymentsMonthlyPageLength', $monthlyDetail);

        $ltv = (string) file_get_contents(resource_path('views/admin/report/ltv.blade.php'));
        $ltvFn = strpos($ltv, 'function initLtvUserPaymentsDetailTable');
        $this->assertNotFalse($ltvFn);
        $ltvCreate = strpos($ltv, "KidsCrmDataTable.create('#ltv-table'");
        $this->assertNotFalse($ltvCreate);
        $ltvDetail = substr($ltv, $ltvFn, $ltvCreate - $ltvFn);
        $this->assertStringContainsString('pageLength: 10', $ltvDetail);
        $this->assertStringNotContainsString('persistPageLength', $ltvDetail);
        $this->assertStringNotContainsString('$ltvPageLength', $ltvDetail);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            resource_path('views/admin/report/payment_monthly.blade.php'),
            'function initMonthlyPaymentsDetailTable',
            'blade-js-monthly-detail-page-length'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            resource_path('views/admin/report/ltv.blade.php'),
            'function initLtvUserPaymentsDetailTable',
            'blade-js-ltv-detail-page-length'
        );
    }

    /**
     * P1: «Применить» / сброс фильтров перезагружает таблицу, не пересоздаёт её.
     * Иначе сохранённый «Показать N» сбросится на дефолт DataTables.
     */
    public function test_report_and_payout_filter_submit_reloads_without_recreating_datatable(): void
    {
        $cases = [
            resource_path('views/admin/report/payment.blade.php') => [
                'create' => "KidsCrmDataTable.create('#payments-table'",
                'submit' => '$payFiltersForm.on(\'submit\'',
                'reload' => 'dtApi.reload();',
            ],
            resource_path('views/admin/report/payment_monthly.blade.php') => [
                'create' => "KidsCrmDataTable.create('#payments-monthly-table'",
                'submit' => '$payMonthlyFiltersForm.on(\'submit\'',
                'reload' => 'dtApi.reload({ keepPage: true });',
            ],
            resource_path('views/admin/report/ltv.blade.php') => [
                'create' => "KidsCrmDataTable.create('#ltv-table'",
                'submit' => '$ltvFiltersForm.on(\'submit\'',
                'reload' => 'dtApi.reload({ keepPage: true });',
            ],
            resource_path('views/admin/report/payment_intents.blade.php') => [
                'create' => "KidsCrmDataTable.create('#payment-intents-table'",
                'submit' => '$form.on(\'submit\'',
                'reload' => 'dtApi.reload();',
            ],
            resource_path('views/admin/report/fiscal_receipts.blade.php') => [
                'create' => "KidsCrmDataTable.create('#fiscal-receipts-table'",
                'submit' => '$form.on(\'submit\'',
                'reload' => 'dtApi.reload();',
            ],
            resource_path('views/admin/partners/tabs/payouts.blade.php') => [
                'create' => "KidsCrmDataTable.create('#payouts-table'",
                'submit' => '$filtersForm.on(\'submit\'',
                'reload' => 'dtApi.reload({ keepPage: true });',
            ],
        ];

        foreach ($cases as $path => $meta) {
            $content = (string) file_get_contents($path);
            $this->assertSame(1, substr_count($content, $meta['create']), $path);
            $submitPos = strpos($content, $meta['submit']);
            $this->assertNotFalse($submitPos, $path);
            $chunk = substr($content, $submitPos, 900);
            $this->assertStringContainsString('e.preventDefault()', $chunk, $path);
            $this->assertStringContainsString($meta['reload'], $chunk, $path);
            $this->assertStringNotContainsString('KidsCrmDataTable.create', $chunk, $path);
            $this->assertInlineScriptsContainingHaveValidJavascript(
                $path,
                $meta['submit'],
                'blade-js-filter-reload-'.basename($path)
            );
        }
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
     * P1: inline JS кабинета — фильтр абонементов селектом группы работает и без сезонов.
     * Регрессия: applyDashboardTeamContext раньше выходил по !dashboardSeasonsEnabled
     * до filterLessonPackagesByTeam.
     */
    public function test_dashboard_lesson_package_team_filter_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/dashboard.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('filterLessonPackagesByTeam', $content);
        $this->assertStringContainsString('dashboardTeamSwitcherEnabled', $content);
        $this->assertStringContainsString('data-ulp-team-id', $content);
        $this->assertStringContainsString('dashboard-lesson-packages', $content);
        $this->assertStringContainsString('cardTeam && Number(cardTeam) === Number(teamId)', $content);
        $this->assertStringContainsString("card.classList.remove('d-none')", $content);
        $this->assertStringContainsString("block.classList.toggle('d-none', visible === 0)", $content);
        $this->assertStringContainsString('if (!dashboardTeamSwitcherEnabled || !dashboardTeams.length)', $content);
        $this->assertDoesNotMatchRegularExpression(
            '/function applyDashboardTeamContext\([^)]*\)\s*\{\s*if\s*\(\s*!dashboardSeasonsEnabled/',
            $content,
            'applyDashboardTeamContext не должен выходить по сезонам до фильтра абонементов.'
        );

        preg_match(
            '/function applyDashboardTeamContext\(teamId, persist\) \{([\s\S]*?)\n            function /',
            $content,
            $applyMatch
        );
        $this->assertNotEmpty($applyMatch[1] ?? '', 'Не найдено тело applyDashboardTeamContext');
        $applyBody = (string) $applyMatch[1];
        $filterPos = strpos($applyBody, 'filterLessonPackagesByTeam(teamId)');
        $seasonsPos = strpos($applyBody, 'if (!dashboardSeasonsEnabled)');
        $this->assertNotFalse($filterPos);
        $this->assertNotFalse($seasonsPos);
        $this->assertLessThan($seasonsPos, $filterPos);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], 'В dashboard.blade.php нет inline <script>');

        $filterScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'filterLessonPackagesByTeam')) {
                continue;
            }
            $filterScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-dashboard-ulp-filter-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in dashboard package-filter script (block #%d):\n%s\n--- preview ---\n%s",
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
            $filterScriptFound,
            'В dashboard.blade.php не найден script с filterLessonPackagesByTeam'
        );
    }

    /**
     * P1: консоль — типы абонементов. Регрессия: блок ULP снова обернут
     * setPrices.packageAssignments.view, а dashboardSeasonsEnabled не включает postpay.
     */
    public function test_dashboard_cabinet_packages_type_permissions_js_and_markup_contract(): void
    {
        $path = resource_path('views/dashboard.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);

        $this->assertStringNotContainsString(
            "@can('setPrices.packageAssignments.view')",
            $content,
            'Блок абонементов на консоли не должен зависеть от packageAssignments.view'
        );
        $this->assertStringContainsString(
            "@canany(['setPrices.cabinetSeasons.view', 'setPrices.cabinetPackages.postpay.view'])",
            $content
        );
        $this->assertSame(
            2,
            substr_count(
                $content,
                "@canany(['setPrices.cabinetSeasons.view', 'setPrices.cabinetPackages.postpay.view'])"
            ),
            'HTML сезонов и JS dashboardSeasonsEnabled должны использовать один @canany (сезоны + postpay)'
        );
        $this->assertStringContainsString('var dashboardSeasonsEnabled = true', $content);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = false', $content);
        $this->assertStringContainsString('CabinetLessonPackagePermission::userCanViewSeasonsBlock', $content);
        $this->assertStringContainsString("isset(\$userLessonPackages)", $content);
        $this->assertStringContainsString('id="dashboard-lesson-packages"', $content);
        $this->assertStringContainsString('filterLessonPackagesByTeam', $content);
        $this->assertStringContainsString('createSeasons()', $content);
        $this->assertStringContainsString('if (!dashboardSeasonsEnabled)', $content);
        $this->assertDoesNotMatchRegularExpression(
            '/function applyDashboardTeamContext\([^)]*\)\s*\{\s*if\s*\(\s*!dashboardSeasonsEnabled/',
            $content,
            'При включённых сезонах через postpay фильтр абонементов всё равно должен вызываться раньше выхода.'
        );

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], 'В dashboard.blade.php нет inline <script>');

        $seasonsScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'dashboardSeasonsEnabled')
                || ! str_contains($rawScript, 'createSeasons')) {
                continue;
            }
            $seasonsScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-dashboard-cabinet-packages-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in dashboard cabinet-packages script (block #%d):\n%s\n--- preview ---\n%s",
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
            $seasonsScriptFound,
            'В dashboard.blade.php не найден script с dashboardSeasonsEnabled / createSeasons'
        );
    }

    public function test_dashboard_has_no_cabinet_diagnostics_overlay(): void
    {
        $path = resource_path('views/dashboard.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringNotContainsString('id="cabinet-diagnostics"', $content);
        $this->assertStringNotContainsString('id="cabinet-diagnostics-json"', $content);
        $this->assertStringNotContainsString('data-cabinet-diagnostics="1"', $content);
        $this->assertStringNotContainsString('refreshCabinetDiagnosticsPanel', $content);
        $this->assertStringNotContainsString('__cabinetDiagnosticsPayload', $content);
        $this->assertStringNotContainsString('cabinet_diagnostics', $content);
        $this->assertStringNotContainsString('Диагностика консоли', $content);
    }

    public function test_settings_cabinet_diagnostics_button_ajax_contract_and_valid_javascript(): void
    {
        $path = resource_path('views/admin/setting/setting.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('id="rowCabinetDiagnostics"', $content);
        $this->assertStringContainsString('id="btnCabinetDiagnostics"', $content);
        $this->assertStringContainsString('id="cabinetDiagnosticsError"', $content);
        $this->assertStringContainsString('data-error-for="cabinetDiagnostics"', $content);
        $this->assertStringContainsString("route('settings.cabinetDiagnostics')", $content);
        $this->assertStringContainsString('errors.cabinetDiagnostics', $content);
        $this->assertStringContainsString('$error.text', $content);
        $this->assertStringContainsString("@can('settings.reverbOverlay.manage')", $content);
        $this->assertSame(2, substr_count($content, "@can('settings.reverbOverlay.manage')"));
        $this->assertStringNotContainsString("@can('settings.cabinetDiagnostics.manage')", $content);
        $this->assertStringNotContainsString('canManageCabinetDiagnostics', $content);
        $this->assertStringContainsString('Оверлей статуса Reverb', $content);
        $this->assertStringNotContainsString('Диагностика консоли', $content);

        $handlerStart = strpos($content, "click', '#btnCabinetDiagnostics'");
        $this->assertNotFalse($handlerStart);
        $handlerEnd = strpos($content, "@can('settings.registration.manage')", $handlerStart);
        $this->assertNotFalse($handlerEnd);
        $handler = substr($content, $handlerStart, $handlerEnd - $handlerStart);
        $this->assertStringContainsString("\$label.text(active ? 'включён' : 'выключен')", $handler);
        $this->assertStringNotContainsString('включена', $handler);
        $this->assertStringNotContainsString('выключена', $handler);
        $this->assertStringContainsString("\$cb.prop('checked', !active)", $handler);
        $this->assertStringContainsString('xhr.status !== 403', $handler);
        $this->assertStringContainsString('Оверлей статуса Reverb доступен только суперадмину.', $handler);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $handler);
        $this->assertStringContainsString("xhr.responseJSON.errors.cabinetDiagnostics", $handler);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'btnCabinetDiagnostics',
            'blade-js-settings-cabinet-diagnostics'
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
     * P1: inline JS модалок «Добавить слот» / «Редактировать занятие» (fetch + errors под полями).
     */
    public function test_team_schedule_slot_modals_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/teamScheduleSlots/partials/slotModals.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('id="slotCreateModal"', $content);
        $this->assertStringContainsString('id="slotCreateSubmit">Добавить</button>', $content);
        $this->assertStringContainsString('>Отмена</button>', $content);
        $this->assertStringContainsString('autoSelectSoleTeam', $content);
        $this->assertStringContainsString('selectSoleVisibleTeamIfAny', $content);
        $this->assertStringContainsString('slotCreateLocationsCount', $content);
        $this->assertStringContainsString('openSlotCreateModalWithDefaults', $content);
        $this->assertStringContainsString('applyErrors', $content);
        $this->assertStringContainsString('data-error-for', $content);
        $this->assertStringContainsString("Accept': 'application/json'", $content);
        $this->assertStringContainsString('X-Requested-With', $content);
        $this->assertStringContainsString('admin.team-schedule-slots.store', $content);
        $this->assertStringContainsString('fetch(', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], 'В slotModals.blade.php нет inline <script>');

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'slotCreateSubmit') && ! str_contains($rawScript, 'autoSelectSoleTeam')) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-slot-modals-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in slotModals script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, 'В slotModals.blade.php не найден script с slotCreateSubmit / autoSelectSoleTeam');
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
     * Включает автоподстановку group_patterns в шаблон привязки.
     */
    public function test_school_schedule_fixed_bind_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/schoolSchedule.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('submitSchoolCalSlotFixedRegistration', $content);
        $this->assertStringContainsString('showSchoolCalSlotFixedFieldErrs', $content);
        $this->assertStringContainsString('routes.fixedAssign', $content);
        $this->assertStringContainsString('routes.fixedAssignPreview', $content);
        $this->assertStringContainsString('schoolCalSlotFixedFormWrap', $content);
        $this->assertStringContainsString('schoolCalFixedChainPreview', $content);
        $this->assertStringContainsString('scheduleSchoolCalFixedChainPreview', $content);
        $this->assertStringContainsString('refreshSchoolCalFixedChainPreview', $content);
        $this->assertStringContainsString('data-err="patterns"', $content);
        $this->assertStringContainsString('X-Requested-With', $content);
        $this->assertStringContainsString('seedSchoolCalSlotFixedPatternsFromOccurrence', $content);
        $this->assertStringContainsString('addSchoolCalFixedPatternRow', $content);
        $this->assertStringContainsString('fillSchoolCalFixedPatternRow', $content);
        $this->assertStringContainsString('group_patterns', $content);
        $this->assertStringContainsString('schoolCalSlotBindAction(\'fixed\')', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $fixedScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'submitSchoolCalSlotFixedRegistration')) {
                continue;
            }
            $fixedScriptFound = true;
            $this->assertStringContainsString('group_patterns', $rawScript);
            $this->assertStringContainsString('addSchoolCalFixedPatternRow', $rawScript);
            $this->assertStringContainsString('fixedAssignPreview', $rawScript);
            $this->assertStringContainsString('refreshSchoolCalFixedChainPreview', $rawScript);
            $this->assertStringContainsString('scheduleSchoolCalFixedChainPreview', $rawScript);
            $previewPos = strpos($rawScript, 'fetch(routes.fixedAssignPreview');
            $this->assertNotFalse($previewPos, 'Не найден fetch(routes.fixedAssignPreview)');
            $previewChunk = substr($rawScript, (int) $previewPos, 900);
            $this->assertStringContainsString('X-Requested-With', $previewChunk);
            $this->assertStringContainsString("Accept': 'application/json'", $previewChunk);
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
     * P1: колонка «Отправка СМС» — node --check и UX-контракт (native disabled + kids-tooltip-hint,
     * reset модалки при повторном открытии, errors.phone под полем, errors.sms в алерте без «Попробуйте позже»).
     */
    public function test_lesson_package_assignment_pay_sms_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/assignments.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('function ulpSmsSendRender(data, type, row)', $content);
        $this->assertStringContainsString("$(document).on('click', '.js-ulp-send-sms'", $content);
        $this->assertStringContainsString('function openSmsModal(assignmentId)', $content);
        $this->assertStringContainsString('function resetSmsModal()', $content);
        $this->assertStringContainsString('function fillSmsModal(payload)', $content);
        $this->assertStringContainsString("assignmentsBase + '/' + encodeURIComponent(String(assignmentId)) + '/sms-preview'", $content);
        $this->assertStringContainsString("assignmentsBase + '/' + encodeURIComponent(String(smsAssignmentId)) + '/send-sms'", $content);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $content);

        $renderStart = strpos($content, 'function ulpSmsSendRender(data, type, row)');
        $this->assertNotFalse($renderStart);
        $renderFn = substr($content, $renderStart, (int) strpos(substr($content, $renderStart), 'function ulpLessonsRender'));
        $this->assertStringContainsString("if (!row.sms_send_available)", $renderFn);
        $this->assertStringContainsString("return '<span class=\"text-muted small\">—</span>'", $renderFn);

        $walletOffStart = strpos($renderFn, 'if (!row.sms_wallet_ok)');
        $this->assertNotFalse($walletOffStart);
        $walletOnStart = strpos($renderFn, "return '<button type=\"button\" class=\"btn btn-sm btn-outline-primary ulp-sms-send-btn js-ulp-send-sms\"");
        $this->assertNotFalse($walletOnStart);
        $this->assertTrue($walletOffStart < $walletOnStart);
        $walletOff = substr($renderFn, $walletOffStart, $walletOnStart - $walletOffStart);
        $this->assertStringContainsString('kids-tooltip-hint', $walletOff);
        $this->assertStringContainsString('data-kids-tooltip-hint="1"', $walletOff);
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $walletOff);
        $this->assertStringContainsString('data-bs-custom-class="ulp-assignment-paid-tooltip"', $walletOff);
        $this->assertStringContainsString('Недостаточно средств. Пополните баланс кабинета', $walletOff);
        $this->assertStringContainsString('disabled', $walletOff);
        $this->assertStringNotContainsString('aria-disabled', $walletOff);
        $this->assertStringNotContainsString('is-visually-disabled', $walletOff);
        $this->assertStringNotContainsString('js-ulp-send-sms', $walletOff);

        $walletOn = substr($renderFn, $walletOnStart, 280);
        $this->assertStringContainsString('js-ulp-send-sms', $walletOn);
        $this->assertStringNotContainsString('aria-disabled', $walletOn);
        $this->assertStringNotContainsString('is-visually-disabled', $walletOn);
        $this->assertStringNotContainsString('data-kids-tooltip-hint', $walletOn);
        $this->assertStringNotContainsString(' disabled', $walletOn);

        $this->assertSame(
            1,
            substr_count($content, "$(document).on('click', '.js-ulp-send-sms'"),
            'Должен быть один обработчик открытия модалки SMS'
        );
        $this->assertStringContainsString("KidsCrmTooltip.init(dtApi.table.table().body(), { scopes: ['hint'] })", $content);
        $this->assertStringContainsString('draw.dt.ulpSmsWalletHint', $content);

        $clickStart = strpos($content, "$(document).on('click', '.js-ulp-send-sms'");
        $this->assertNotFalse($clickStart);
        $clickChunk = substr($content, $clickStart, 520);
        $this->assertStringContainsString('e.preventDefault()', $clickChunk);
        $this->assertStringContainsString('btn.disabled', $clickChunk);
        $this->assertStringContainsString('openSmsModal(id)', $clickChunk);
        $this->assertStringNotContainsString('aria-disabled', $clickChunk);

        $openStart = strpos($content, 'async function openSmsModal(assignmentId)');
        $this->assertNotFalse($openStart);
        $openChunk = substr($content, $openStart, 900);
        $resetPos = strpos($openChunk, 'resetSmsModal()');
        $fetchPos = strpos($openChunk, '/sms-preview');
        $this->assertNotFalse($resetPos);
        $this->assertNotFalse($fetchPos);
        $this->assertTrue(
            $resetPos < $fetchPos,
            'Повторное открытие модалки должно сначала сбросить форму, иначе останется readonly/busy прошлого назначения'
        );

        $resetStart = strpos($content, 'function resetSmsModal()');
        $this->assertNotFalse($resetStart);
        $resetChunk = substr($content, $resetStart, 900);
        $this->assertStringContainsString('smsPhoneLocked = false', $resetChunk);
        $this->assertStringContainsString('smsSendBtn.disabled = true', $resetChunk);
        $this->assertStringContainsString("smsSendBtn.removeAttribute('data-busy')", $resetChunk);
        $this->assertStringContainsString('smsPhoneInput.readOnly = false', $resetChunk);
        $this->assertStringContainsString("smsPhoneInput.removeAttribute('readonly')", $resetChunk);
        $this->assertStringContainsString("setSmsAlert('')", $resetChunk);
        $this->assertStringContainsString("setSmsPhoneError('')", $resetChunk);

        $fillStart = strpos($content, 'function fillSmsModal(payload)');
        $this->assertNotFalse($fillStart);
        $fillChunk = substr($content, $fillStart, 1800);
        $this->assertStringContainsString('smsPhoneLocked = !!payload.phone_locked', $fillChunk);
        $this->assertStringContainsString('smsPhoneInput.readOnly = smsPhoneLocked', $fillChunk);
        $this->assertStringContainsString("smsPhoneInput.setAttribute('readonly', 'readonly')", $fillChunk);
        $this->assertStringContainsString("smsPhoneInput.removeAttribute('readonly')", $fillChunk);
        $this->assertStringContainsString('syncSmsSendEnabled()', $fillChunk);

        $sendStart = strpos($content, 'smsSendBtn?.addEventListener(\'click\'');
        $this->assertNotFalse($sendStart);
        $sendChunk = substr($content, $sendStart, 3200);
        $this->assertStringContainsString('if (!smsPhoneLocked)', $sendChunk);
        $this->assertStringContainsString('body.phone =', $sendChunk);
        $this->assertStringContainsString('if (errors.phone)', $sendChunk);
        $this->assertStringContainsString('setSmsPhoneError', $sendChunk);
        $this->assertStringContainsString('errors.wallet', $sendChunk);
        $this->assertStringContainsString('errors.sms', $sendChunk);
        $this->assertStringContainsString('setSmsAlert', $sendChunk);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true })', $sendChunk);

        $smsErrPos = strpos($sendChunk, 'errors.sms');
        $walletErrPos = strpos($sendChunk, 'errors.wallet');
        $payloadMsgPos = strpos($sendChunk, 'payload && payload.message');
        $statusFallbackPos = strpos($sendChunk, "Не удалось отправить SMS (' + status + ')");
        $this->assertNotFalse($smsErrPos);
        $this->assertNotFalse($walletErrPos);
        $this->assertNotFalse($payloadMsgPos);
        $this->assertNotFalse($statusFallbackPos);
        $this->assertTrue(
            $smsErrPos < $walletErrPos && $walletErrPos < $payloadMsgPos && $payloadMsgPos < $statusFallbackPos,
            'Алерт модалки должен брать errors.sms (причина шлюза), а не общий fallback по HTTP-статусу'
        );
        $this->assertStringContainsString('setSmsAlert(general)', $sendChunk);
        $this->assertStringNotContainsString(
            'Не удалось отправить SMS. Попробуйте позже.',
            $sendChunk,
            'JS не должен подменять errors.sms общей «Попробуйте позже» — это текст с сервера'
        );
        $catchPos = strpos($sendChunk, '} catch (err)');
        $this->assertNotFalse($catchPos);
        $catchChunk = substr($sendChunk, $catchPos);
        $this->assertStringContainsString('Проверьте соединение', $catchChunk);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'function ulpSmsSendRender',
            'blade-js-ulp-sms-render'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'function openSmsModal',
            'blade-js-ulp-sms-modal'
        );
    }

    /**
     * P1: inline JS фильтра «Удаленные» + бейдж soft-deleted ученика (таблица и модалка).
     */
    public function test_lesson_package_assignments_deleted_users_filter_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/assignments.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('id="ulp-filter-deleted-users"', $content);
        $this->assertStringContainsString('name="filter_deleted_users"', $content);
        $this->assertStringContainsString('>Удаленные<', $content);
        $this->assertStringContainsString('id="ulp-modal-user-deleted-badge"', $content);
        $this->assertStringContainsString("filter_deleted_users:", $content);
        $this->assertStringContainsString("find('[name=\"filter_deleted_users\"]')", $content);
        $this->assertStringContainsString('row.user_is_deleted', $content);
        $this->assertStringContainsString('badge text-bg-secondary ms-1">Удалён</span>', $content);
        $this->assertStringContainsString("toggle('d-none', !a.user_is_deleted)", $content);
        $this->assertStringContainsString('ulpStudentRender', $content);
        $this->assertStringContainsString('fillModal', $content);
        $this->assertStringContainsString('X-Requested-With', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $deletedFilterScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'filter_deleted_users')
                && ! str_contains($rawScript, 'user_is_deleted')
            ) {
                continue;
            }
            $deletedFilterScriptFound = true;
            $this->assertStringContainsString('filter_deleted_users', $rawScript);
            $this->assertStringContainsString('user_is_deleted', $rawScript);
            $this->assertStringContainsString('ulp-modal-user-deleted-badge', $rawScript);

            $studentRenderPos = strpos($rawScript, 'function ulpStudentRender');
            $this->assertNotFalse($studentRenderPos, 'Не найден ulpStudentRender');
            $studentChunk = substr($rawScript, (int) $studentRenderPos, 900);
            $this->assertStringContainsString('row.user_is_deleted', $studentChunk);
            $this->assertStringContainsString('Удалён', $studentChunk);

            $fillPos = strpos($rawScript, 'function fillModal');
            $this->assertNotFalse($fillPos, 'Не найден fillModal');
            $fillChunk = substr($rawScript, (int) $fillPos, 700);
            $this->assertStringContainsString('user_is_deleted', $fillChunk);
            $this->assertStringContainsString('ulp-modal-user-deleted-badge', $fillChunk);

            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-ulp-deleted-users-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in assignments deleted-users script (block #%d):\n%s\n--- preview ---\n%s",
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
            $deletedFilterScriptFound,
            'В assignments.blade.php не найден script с filter_deleted_users / user_is_deleted'
        );
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
     * P1: inline JS автопролонгации на вкладке назначений (флаг + Select2 blocked + badge).
     */
    public function test_lesson_package_assignment_auto_prolong_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/assignments.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('ulp_auto_prolong_wrap', $content);
        $this->assertStringContainsString('ulp-modal-auto-prolong', $content);
        $this->assertStringContainsString('syncAutoProlongVisibility', $content);
        $this->assertStringContainsString('body.auto_prolong_enabled', $content);
        $this->assertStringContainsString('payload.errors.auto_prolong_enabled', $content);
        $this->assertStringContainsString('blocked_reason', $content);
        $this->assertStringContainsString("context: 'assign'", $content);
        $this->assertStringContainsString('auto_prolong_badge_label', $content);
        $this->assertStringContainsString('X-Requested-With', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $autoProlongScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'auto_prolong')
                && ! str_contains($rawScript, 'syncAutoProlongVisibility')
                && ! str_contains($rawScript, 'blocked_reason')
            ) {
                continue;
            }
            $autoProlongScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-auto-prolong-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in assignment auto-prolong script (block #%d):\n%s\n--- preview ---\n%s",
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
            $autoProlongScriptFound,
            'В assignments.blade.php не найден script с auto_prolong / syncAutoProlongVisibility'
        );
    }

    /**
     * P1: фильтр «Прошедшие» + красная дата в колонке «Занятия» (last_lesson_is_past → text-danger).
     */
    public function test_lesson_package_assignments_past_lessons_filter_and_lessons_column_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/assignments.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('id="ulp-filter-past-lessons"', $content);
        $this->assertStringContainsString('name="filter_past_lessons"', $content);
        $this->assertStringContainsString('Прошедшие', $content);
        $this->assertStringContainsString('font-weight: 400 !important', $content);

        $this->assertStringContainsString('function ulpAssignmentFilterParams()', $content);
        $this->assertStringContainsString(
            "filter_past_lessons: \$ulpFiltersForm.find('[name=\"filter_past_lessons\"]').is(':checked') ? '1' : ''",
            $content
        );
        $this->assertStringContainsString('ulpAssignmentsFiltersResetBtn', $content);
        $this->assertStringContainsString('$ulpFiltersForm[0].reset()', $content);
        $this->assertStringContainsString('e.preventDefault()', $content);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true })', $content);

        $this->assertStringContainsString('function ulpLessonsRender', $content);
        $this->assertStringContainsString('row.last_lesson_is_past', $content);
        $this->assertStringContainsString('ulp-assignment-lessons-cell__date text-danger', $content);
        $this->assertStringContainsString("'ulp-assignment-lessons-cell__date'", $content);
        $this->assertStringContainsString('Посл.:', $content);
        $this->assertStringContainsString('js-ulp-assignment-lessons', $content);
        $this->assertStringContainsString("key: 'lessons'", $content);
        $this->assertStringContainsString('render: ulpLessonsRender', $content);

        $renderPos = strpos($content, 'function ulpLessonsRender');
        $this->assertNotFalse($renderPos);
        $renderChunk = substr($content, (int) $renderPos, 900);
        $this->assertStringContainsString('row.last_lesson_is_past', $renderChunk);
        $this->assertStringContainsString('text-danger', $renderChunk);
        $this->assertMatchesRegularExpression(
            '/row\.last_lesson_is_past\s*\?\s*[\'"]ulp-assignment-lessons-cell__date text-danger[\'"]\s*:\s*[\'"]ulp-assignment-lessons-cell__date[\'"]/',
            $renderChunk,
            'Красный цвет только при last_lesson_is_past, иначе обычный класс даты'
        );

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $scriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'ulpLessonsRender')
                && ! str_contains($rawScript, 'filter_past_lessons')
            ) {
                continue;
            }
            $scriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-ulp-past-lessons-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in assignments past-lessons / lessons-column script (block #%d):\n%s\n--- preview ---\n%s",
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
            $scriptFound,
            'В assignments.blade.php не найден script с ulpLessonsRender / filter_past_lessons'
        );
    }

    /**
     * P1: inline JS календаря школы — Select2 ученика с blocked_reason автопролонга.
     */
    public function test_school_schedule_auto_prolong_user_select_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/schoolSchedule.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('schoolCalSlotUserSelect', $content);
        $this->assertStringContainsString('blocked_reason', $content);
        $this->assertStringContainsString('item.blocked', $content);
        $this->assertStringContainsString('templateResult', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $selectScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'schoolCalSlotUserSelect')
                || ! str_contains($rawScript, 'blocked_reason')
            ) {
                continue;
            }
            $selectScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-school-cal-auto-prolong-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in school schedule auto-prolong user select (block #%d):\n%s\n--- preview ---\n%s",
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
            $selectScriptFound,
            'В schoolSchedule.blade.php не найден script с schoolCalSlotUserSelect + blocked_reason'
        );
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
     * P1: вкладка «Уведомления» — JS-контракты модалки (дефолты openCreate, превью UX, переменные, триггер).
     */
    public function test_payment_notifications_modal_js_contracts_and_syntax(): void
    {
        $path = resource_path('views/admin/SettingPrices/payment-notifications.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('id="pnTemplateVarsToggle"', $content);
        $this->assertStringContainsString('id="pnTemplateVarsPanel"', $content);
        $this->assertMatchesRegularExpression('/id="pnTemplateVarsPanel"[^>]*\bhidden\b/', $content);
        $this->assertStringContainsString('Скрыть: переменные шаблона', $content);
        $this->assertStringNotContainsString('id="pn-variables-help"', $content);

        $this->assertStringContainsString('Правила email-рассылки:', $content);
        $this->assertStringContainsString('Абонемент не оплачен.', $content);
        $this->assertStringContainsString('Отправка в 10:00 (Europe/Moscow).', $content);

        $this->assertStringContainsString('function openCreate()', $content);
        $this->assertStringContainsString("setScheduleTypes(['fixed', 'flexible'])", $content);
        $this->assertStringContainsString("value = 'day_of_month'", $content);
        $this->assertStringContainsString("pn-rule-trigger-value').value = '5'", $content);
        $this->assertStringContainsString("pn-rule-billing-offset').value = '0'", $content);
        $this->assertStringContainsString("pn-rule-enabled').checked = true", $content);

        $this->assertStringContainsString('function syncTriggerUi()', $content);
        $this->assertStringContainsString('days_after_overdue', $content);
        $this->assertStringContainsString("offsetWrap.classList.add('d-none')", $content);
        $this->assertStringContainsString("offsetWrap.classList.remove('d-none')", $content);

        $this->assertStringContainsString('id="pn-preview-frame"', $content);
        $this->assertStringContainsString('frame.srcdoc = res.data.email_html', $content);
        $this->assertStringContainsString('id="pn-preview-demo-note"', $content);
        $this->assertStringContainsString('res.data.is_demo', $content);
        $this->assertStringContainsString('showFieldErrors', $content);

        $this->assertStringContainsString('hidden.bs.modal', $content);
        $this->assertStringContainsString("panel.setAttribute('hidden', 'hidden')", $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'function openCreate') && ! str_contains($rawScript, 'pn-preview-frame')) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-payment-notifications-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in payment-notifications script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, 'В payment-notifications.blade.php не найден script с openCreate / preview');
    }

    /**
     * P1: фильтр email_category в исходящих письмах передаётся в getFilterParams.
     */
    public function test_outgoing_emails_report_payment_notification_category_filter_js_contract(): void
    {
        $path = resource_path('views/admin/report/outgoing_emails.blade.php');
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('name="email_category"', $content);
        $this->assertStringContainsString('value="payment_notification"', $content);
        $this->assertStringContainsString('email_category:', $content);
        $this->assertStringContainsString('[name="email_category"]', $content);
    }

    /**
     * P1: inline JS отчёта «Задолженности» — total по тем же фильтрам, default status=active, DataTables.
     * Контракт: refreshDebtReportTotal дергает /debts/total с debtReportFilterParams (в т.ч. status).
     */
    public function test_debts_report_total_refresh_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/report/debt.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('KidsCrmDataTable.create', $content);
        $this->assertStringContainsString('#debts-table', $content);
        $this->assertStringContainsString('debtReportFilterParams', $content);
        $this->assertStringContainsString('refreshDebtReportTotal', $content);
        $this->assertStringContainsString("defaultFilterUserStatus = 'active'", $content);
        $this->assertStringContainsString("status: \$debtFiltersForm.find('[name=\"status\"]').val() || ''", $content);
        $this->assertStringContainsString('reports.debts.total', $content);
        $this->assertStringContainsString('debts.getDebts', $content);
        $this->assertStringContainsString('debt-report-filters', $content);
        $this->assertStringContainsString('pay-debt-filter-user-status', $content);
        // Сброс фильтров не должен «забывать» дефолт active при повторном открытии логики reset
        $this->assertStringContainsString('debtReportFiltersResetBtn', $content);
        $this->assertStringContainsString('defaultFilterUserStatus', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], 'В debt.blade.php нет inline <script>');

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'refreshDebtReportTotal')) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-debts-report-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in debts report script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, 'В debt.blade.php не найден script с refreshDebtReportTotal');
    }

    /**
     * P1: порядок колонок заявок в inline JS — ключи, when-флаги, columns-settings по имени.
     */
    public function test_school_leads_column_order_inline_script_contract_and_valid_javascript(): void
    {
        $path = resource_path('views/admin/school-leads/tabs/leads.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("KidsCrmDataTable.create('#leads-table'", $content);
        $this->assertStringContainsString("key: 'child_full_name'", $content);
        $this->assertStringContainsString("key: 'name'", $content);
        $this->assertStringContainsString("key: 'status'", $content);
        $this->assertStringContainsString("key: 'location'", $content);
        $this->assertStringContainsString("key: 'team_title'", $content);
        $this->assertStringContainsString("key: 'phone'", $content);
        $this->assertStringContainsString("key: 'contract'", $content);
        $this->assertStringContainsString('when: canViewLocations', $content);
        $this->assertStringContainsString('when: canShowLeadClientColumn', $content);
        $this->assertStringContainsString('when: canViewDistricts', $content);
        $this->assertStringContainsString("toggleSelector: '.school-leads-column-toggle'", $content);
        $this->assertStringContainsString('persistPageLength: true', $content);
        $this->assertStringContainsString('pageLength: @json((int) ($leadsPageLength ?? 10))', $content);
        $this->assertStringContainsString('child_full_name: true', $content);
        $this->assertStringContainsString('phone: true', $content);
        $this->assertStringContainsString("order: [[0, 'desc']]", $content);
        $this->assertStringNotContainsString('data-column-key="0"', $content);

        $createPos = strpos($content, "KidsCrmDataTable.create('#leads-table'");
        $this->assertNotFalse($createPos);
        $columnsPos = strpos($content, 'columns: [', $createPos);
        $this->assertNotFalse($columnsPos);
        $commentPos = strpos($content, "key: 'comment'", $columnsPos);
        $this->assertNotFalse($commentPos);
        $columnsChunk = substr($content, $columnsPos, $commentPos - $columnsPos);

        $this->assertLessThan(
            strpos($columnsChunk, "key: 'name'"),
            strpos($columnsChunk, "key: 'child_full_name'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'status'"),
            strpos($columnsChunk, "key: 'name'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'location'"),
            strpos($columnsChunk, "key: 'status'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'team_title'"),
            strpos($columnsChunk, "key: 'location'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'phone'"),
            strpos($columnsChunk, "key: 'team_title'")
        );
        $this->assertLessThan(
            strpos($columnsChunk, "key: 'contract'"),
            strpos($columnsChunk, "key: 'phone'")
        );

        $locationBlockStart = strpos($columnsChunk, "key: 'location'");
        $teamBlockStart = strpos($columnsChunk, "key: 'team_title'");
        $this->assertNotFalse($locationBlockStart);
        $this->assertNotFalse($teamBlockStart);
        $locationBlock = substr($columnsChunk, $locationBlockStart, $teamBlockStart - $locationBlockStart);
        $this->assertStringContainsString('when: canViewLocations', $locationBlock);

        $phoneBlockStart = strpos($columnsChunk, "key: 'phone'");
        $contractBlockStart = strpos($columnsChunk, "key: 'contract'");
        $phoneBlock = substr($columnsChunk, $phoneBlockStart, $contractBlockStart - $phoneBlockStart);
        $this->assertStringNotContainsString('when: canShowLeadClientColumn', $phoneBlock);
        $contractBlock = substr($columnsChunk, $contractBlockStart, 400);
        $this->assertStringContainsString('when: canShowLeadClientColumn', $contractBlock);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            "KidsCrmDataTable.create('#leads-table'",
            'blade-js-school-leads-columns'
        );
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

    /**
     * P1: автосопоставление родителя в модалке лида — Accept JSON PUT + confirm UI gate.
     */
    public function test_school_leads_parent_match_inline_script_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/school-leads/tabs/leads.blade.php');
        $this->assertFileExists($path);

        $content = (string) file_get_contents($path);
        $this->assertStringContainsString('leadParentMatchUi', $content);
        $this->assertStringContainsString('acceptLeadParentMatch', $content);
        $this->assertStringContainsString('rejectLeadParentMatch', $content);
        $this->assertStringContainsString('needsParentDecision', $content);
        $this->assertStringContainsString('Выберите родителя', $content);
        $this->assertStringContainsString('parent_match_confirmed', $content);
        $this->assertStringContainsString('parent_match_needs_decision', $content);
        $this->assertStringContainsString('matched_parent', $content);
        $this->assertStringContainsString('useSnapshotParentFields', $content);
        $this->assertStringContainsString('leadParentMatchAcceptBtn', $content);
        $this->assertStringContainsString('leadParentMatchRejectBtn', $content);
        $this->assertStringContainsString('highlightLeadParentSnapshotMatches', $content);
        $this->assertStringContainsString('is-match-hit', $content);
        $this->assertStringContainsString('lead-parent-match-hit-badge', $content);
        $this->assertStringContainsString("type: 'PUT'", $content);
        $this->assertStringContainsString("Accept': 'application/json'", $content);
        $this->assertStringContainsString('$.ajax', $content);
        $this->assertStringContainsString('syncCreateClientBtnState', $content);

        $modalPath = resource_path('views/admin/school-leads/partials/edit-lead-modal.blade.php');
        $this->assertFileExists($modalPath);
        $modal = (string) file_get_contents($modalPath);
        $this->assertStringContainsString('modal-xl', $modal);
        $this->assertStringContainsString('id="leadParentMatchBanner"', $modal);
        $this->assertStringContainsString('id="leadParentMatchAcceptBtn"', $modal);
        $this->assertStringContainsString('id="leadParentMatchRejectBtn"', $modal);
        $this->assertStringContainsString('id="leadParentSnapshotCol"', $modal);
        $this->assertStringContainsString('Данные из заявки', $modal);
        $this->assertStringContainsString('data-match-field="email"', $modal);
        $this->assertStringContainsString('data-match-field="phone"', $modal);
        $this->assertStringContainsString('data-match-field="lastname"', $modal);
        $this->assertStringContainsString('is-match-hit', $modal);
        $this->assertStringContainsString('id="leadParentMatchConfirmed"', $modal);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'acceptLeadParentMatch') || ! str_contains($rawScript, 'needsParentDecision')) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-school-leads-parent-match-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in school-leads parent-match script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, 'В leads.blade.php не найден script с acceptLeadParentMatch + needsParentDecision');
    }

    /**
     * P1: editUser — оба пути открытия модалки заполняют адрес ученика (|| '' сброс).
     */
    public function test_edit_user_modal_address_fill_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/includes/modal/editUser.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('id="edit-address"', $content);
        $this->assertStringContainsString("\$('#edit-user-form #edit-address').val(response.user.address || '')", $content);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($content, "\$('#edit-user-form #edit-address').val(response.user.address || '')")
        );

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $addressScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'edit-address')) {
                continue;
            }
            $addressScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-edit-address-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in editUser address script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($addressScriptFound, 'В editUser.blade.php не найден script с edit-address');
    }

    /**
     * P1: editUser — оба пути открытия модалки заполняют ФИО ученика в родительном (|| '' сброс).
     * UX-баг: один из двух AJAX-путей (editUserLink2 / editUserLink) забывает гидратировать
     * → при повторном открытии остаётся значение предыдущего ученика.
     */
    public function test_edit_user_modal_child_genitive_fill_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/includes/modal/editUser.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $hydrateLine = "\$('#edit-user-form #edit-full-name-genitive').val(response.user.full_name_genitive || '')";
        $this->assertStringContainsString('id="edit-full-name-genitive"', $content);
        $this->assertStringContainsString('@can(\'users.full_name_genitive\')', $content);
        $this->assertStringContainsString($hydrateLine, $content);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($content, $hydrateLine),
            'Оба пути открытия editUser (editUserLink2 и editUserLink) должны гидратировать full_name_genitive'
        );

        $createPath = resource_path('views/includes/modal/createUser.blade.php');
        $this->assertFileExists($createPath);
        $createContent = (string) file_get_contents($createPath);
        $this->assertStringContainsString('id="create-full-name-genitive"', $createContent);
        $this->assertStringContainsString('@can(\'users.full_name_genitive\')', $createContent);
        $this->assertStringNotContainsString(
            "edit-full-name-genitive').val",
            $createContent,
            'createUser не должен гидратировать genitive из AJAX edit JSON'
        );

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $genitiveScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'edit-full-name-genitive')) {
                continue;
            }
            $genitiveScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-edit-child-genitive-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in editUser child genitive script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($genitiveScriptFound, 'В editUser.blade.php не найден script с edit-full-name-genitive');
    }

    /**
     * P1: /admin/users — колонка «Телефон родителя» после «Родитель», «Телефон» → «Телефон ученика».
     * UX-баг: в JS columns/defaults забыли parent_phone или оставили старый заголовок «Телефон»
     * → DataTables сдвигает индексы сортировки и путает телефоны ученика/родителя.
     */
    public function test_admin_users_parent_phone_column_js_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/user.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('<th>Телефон родителя</th>', $content);
        $this->assertStringContainsString('<th>Телефон ученика</th>', $content);
        $this->assertStringNotContainsString('<th>Телефон</th>', $content);
        $this->assertStringContainsString('data-column-key="parent_phone"', $content);
        $this->assertStringContainsString('for="colParentPhone">Телефон родителя</label>', $content);
        $this->assertStringContainsString('for="colPhone">Телефон ученика</label>', $content);
        $this->assertStringContainsString('parent_phone: true', $content);
        $this->assertStringContainsString('persistPageLength: true', $content);
        $this->assertStringContainsString('pageLength: @json((int) ($usersPageLength ?? 10))', $content);

        $parentKeyPos = strpos($content, "{ key: 'parent', type: 'text', data: 'parent' }");
        $parentPhoneKeyPos = strpos($content, "key: 'parent_phone'");
        $phoneKeyPos = strpos($content, "{ key: 'phone', type: 'text', data: 'phone'");

        $this->assertNotFalse($parentKeyPos);
        $this->assertNotFalse($parentPhoneKeyPos);
        $this->assertNotFalse($phoneKeyPos);
        $this->assertLessThan($parentPhoneKeyPos, $parentKeyPos);
        $this->assertLessThan($phoneKeyPos, $parentPhoneKeyPos);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $usersTableScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'parent_phone') || ! str_contains($rawScript, 'users-table')) {
                continue;
            }
            $usersTableScriptFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-users-parent-phone-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in admin/user.blade.php parent_phone script (block #%d):\n%s\n--- preview ---\n%s",
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
            $usersTableScriptFound,
            'В admin/user.blade.php не найден script с users-table и parent_phone'
        );
    }

    /**
     * P1: модалка импорта учеников — node --check + контракт дозаписи родителя.
     * Два JS-пути сброса (hidden.bs.modal и «Другой файл») не должны затирать памятку
     * с правилом «пустые ячейки не трогают справочник».
     */
    public function test_users_import_modal_ajax_contract_and_valid_javascript(): void
    {
        $modalPath = resource_path('views/admin/users/_import_modal.blade.php');
        $pagePath = resource_path('views/admin/user.blade.php');
        $this->assertFileExists($modalPath);
        $this->assertFileExists($pagePath);

        $modal = (string) file_get_contents($modalPath);
        $page = (string) file_get_contents($pagePath);

        $this->assertStringStartsWith("@can('users.import')", trim($modal));
        $this->assertStringContainsString('id="usersImportModal"', $modal);
        $this->assertStringContainsString('id="usersImportMemoAccordion"', $modal);
        $this->assertStringContainsString('accordion-button collapsed', $modal);
        $this->assertStringContainsString('aria-expanded="false"', $modal);
        $this->assertStringContainsString('не трогают</b> справочник', $modal);
        $this->assertStringContainsString('дописываются</b> в пустые поля карточки', $modal);
        $this->assertStringNotContainsString('В справочнике — только при полном совпадении', $modal);

        $filePos = strpos($modal, 'id="users-import-file"');
        $errorPos = strpos($modal, 'id="users-import-file-error"');
        $this->assertNotFalse($filePos);
        $this->assertNotFalse($errorPos);
        $this->assertGreaterThan($filePos, $errorPos);
        $this->assertStringContainsString('class="invalid-feedback" id="users-import-file-error"', $modal);

        $this->assertMatchesRegularExpression(
            '/<button type="button"[^>]*id="users-import-check-btn"/s',
            $modal
        );
        $this->assertMatchesRegularExpression(
            '/<button type="button"[^>]*id="users-import-commit-btn"/s',
            $modal
        );
        $this->assertStringContainsString('d-none" id="users-import-commit-btn"', $modal);
        $this->assertStringContainsString('id="users-import-step-preview" class="d-none"', $modal);
        $this->assertStringContainsString('id="users-import-step-errors" class="d-none"', $modal);

        $this->assertStringContainsString("@can('users.import')", $page);
        $this->assertStringContainsString('data-bs-target="#usersImportModal"', $page);
        $this->assertStringContainsString("@include('admin.users._import_modal')", $page);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $page, $matches);
        $this->assertNotEmpty($matches[1], 'В admin/user.blade.php нет inline <script>');

        $importScriptFound = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'initUsersImportModal')) {
                continue;
            }
            $importScriptFound = true;

            $this->assertStringContainsString('function initUsersImportModal', $rawScript);
            $this->assertSame(1, substr_count($rawScript, 'function initUsersImportModal'));
            $this->assertStringContainsString('function resetImportModal', $rawScript);
            $this->assertStringContainsString("\$modal.on('hidden.bs.modal', resetImportModal)", $rawScript);
            $this->assertStringContainsString("\$resetBtn.on('click', resetImportModal)", $rawScript);
            $this->assertSame(1, substr_count($rawScript, "\$modal.on('hidden.bs.modal', resetImportModal)"));
            $this->assertSame(1, substr_count($rawScript, "\$resetBtn.on('click', resetImportModal)"));

            $resetPos = strpos($rawScript, 'function resetImportModal');
            $this->assertNotFalse($resetPos);
            $resetChunk = substr($rawScript, (int) $resetPos, 900);
            $this->assertStringContainsString("importToken = ''", $resetChunk);
            $this->assertStringContainsString('$memoAccordion.removeClass(\'d-none\')', $resetChunk);
            $this->assertStringContainsString('$stepUpload.removeClass(\'d-none\')', $resetChunk);
            $this->assertStringNotContainsString('$memoAccordion.empty(', $resetChunk);
            $this->assertStringNotContainsString('form.reset()', $resetChunk);

            $this->assertStringContainsString('function showErrors(message, errors)', $rawScript);
            $this->assertStringContainsString('item.field', $rawScript);
            $this->assertStringContainsString('item.message', $rawScript);
            $this->assertStringContainsString('payload.errors', $rawScript);
            $this->assertStringContainsString("showErrors(payload.message, payload.errors)", $rawScript);
            $this->assertStringContainsString('reloadUsersTable()', $rawScript);
            $this->assertStringContainsString("importToken = response.import_token", $rawScript);
            $this->assertStringContainsString("formData.append('file', file)", $rawScript);
            $this->assertStringContainsString("url: previewUrl", $rawScript);
            $this->assertStringContainsString("url: commitUrl", $rawScript);
            $this->assertSame(2, substr_count($rawScript, '$.ajax({'));
            $this->assertStringContainsString('buildChangesTableHtml', $rawScript);
            $this->assertStringContainsString('change.from', $rawScript);
            $this->assertStringContainsString('change.to', $rawScript);
            $this->assertStringContainsString("Выберите файл Excel для импорта.", $rawScript);
            $this->assertStringContainsString('$fileError.text(', $rawScript);
            $this->assertStringNotContainsString('form.reset()', $rawScript);

            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-users-import-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in admin/user.blade.php initUsersImportModal (block #%d):\n%s\n--- preview ---\n%s",
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
            $importScriptFound,
            'В admin/user.blade.php не найден script с initUsersImportModal'
        );
    }

    /**
     * /admin/users: без table-preloader (он раздувал AdminLTE на всю ширину экрана).
     * CSS тулбара — в @push('styles') / head, не в середине content.
     */
    public function test_admin_users_table_preloader_contract(): void
    {
        $path = resource_path('views/admin/user.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringNotContainsString('<x-ui.table-preloader', $content);
        $this->assertStringNotContainsString('users-table-stage', $content);
        $this->assertStringNotContainsString('kids-table-preloader', $content);
        $this->assertStringContainsString('<div class="table-responsive">', $content);
        $this->assertStringContainsString('id="users-table"', $content);
        $this->assertStringContainsString("KidsCrmDataTable.create('#users-table'", $content);
        $createPos = strpos($content, "KidsCrmDataTable.create('#users-table'");
        $this->assertNotFalse($createPos);
        $createNearby = substr($content, max(0, $createPos - 400), 900);
        $this->assertStringNotContainsString('bindTablePreloader', $createNearby);
        $this->assertStringNotContainsString('KidsCrmTablePreloader', $createNearby);
        $this->assertStringNotContainsString('__bindsTablePreloader', $createNearby);

        $toolbarPos = strpos($content, 'payments-report-toolbar');
        $tablePos = strpos($content, 'id="users-table"');
        $this->assertNotFalse($toolbarPos);
        $this->assertNotFalse($tablePos);
        $this->assertLessThan($tablePos, $toolbarPos);

        $this->assertStringContainsString("@push('styles')", $content);
        $pushPos = strpos($content, "@push('styles')");
        $sectionPos = strpos($content, "@section('content')");
        $this->assertNotFalse($pushPos);
        $this->assertNotFalse($sectionPos);
        $this->assertLessThan($sectionPos, $pushPos);
        $stylesChunk = substr($content, $pushPos, $sectionPos - $pushPos);
        $this->assertStringContainsString("@vite(['resources/css/admin-list-toolbar.css', 'resources/css/user.css'])", $stylesChunk);
        $this->assertStringNotContainsString("@vite(['resources/css/admin-list-toolbar.css', 'resources/css/user.css'])", substr($content, $sectionPos));

        $css = (string) file_get_contents(resource_path('css/user.css'));
        $this->assertStringNotContainsString('#users-table-stage:not(.is-ready)', $css);
        $this->assertStringNotContainsString('.users-table-preloader', $css);
    }

    /**
     * P1: ФИО родителя в родительном — оба пути editUser + fillParentFio/reset/Select2 не теряют поле.
     * UX-баг: один из двух AJAX-путей открытия модалки забывает гидратировать genitive →
     * при повторном открытии остаётся значение предыдущего ученика или пусто.
     */
    public function test_edit_user_and_parent_form_genitive_fill_contract_is_valid_javascript(): void
    {
        $editPath = resource_path('views/includes/modal/editUser.blade.php');
        $parentFormPath = resource_path('views/admin/users/_parent_form.blade.php');
        $this->assertFileExists($editPath);
        $this->assertFileExists($parentFormPath);

        $editContent = (string) file_get_contents($editPath);
        $parentForm = (string) file_get_contents($parentFormPath);

        $hydrateLine = 'parent_full_name_genitive: response.user.parent_full_name_genitive';
        $this->assertStringContainsString($hydrateLine, $editContent);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($editContent, $hydrateLine),
            'Оба пути открытия editUser должны передавать parent_full_name_genitive в setStudentParentForm'
        );

        $this->assertStringContainsString('name="parent_full_name_genitive"', $parentForm);
        $this->assertStringContainsString('js-parent-full-name-genitive', $parentForm);
        $this->assertStringContainsString('fullNameGenitive:', $parentForm);
        $this->assertStringContainsString(
            "$(ids.fullNameGenitive).val(data.parent_full_name_genitive || '')",
            $parentForm
        );
        $this->assertStringContainsString(
            'parent_full_name_genitive: item.parent_full_name_genitive',
            $parentForm
        );
        $this->assertStringContainsString("parent_full_name_genitive: '',", $parentForm);

        $fillPos = strpos($parentForm, 'function fillParentFio');
        $this->assertNotFalse($fillPos);
        $fillChunk = substr($parentForm, (int) $fillPos, 900);
        $this->assertStringContainsString('parent_full_name_genitive', $fillChunk);
        $this->assertStringContainsString("|| ''", $fillChunk);

        $resetPos = strpos($parentForm, 'window.resetStudentParentForm');
        $this->assertNotFalse($resetPos);
        $resetChunk = substr($parentForm, (int) $resetPos, 700);
        $this->assertStringContainsString("parent_full_name_genitive: ''", $resetChunk);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $editContent, $editScripts);
        $this->assertNotEmpty($editScripts[1]);
        $editGenitiveFound = false;
        foreach ($editScripts[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'parent_full_name_genitive')) {
                continue;
            }
            $editGenitiveFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));
            $this->assertGreaterThanOrEqual(
                1,
                substr_count($rawScript, $hydrateLine)
            );

            $tempFile = sys_get_temp_dir().'/blade-js-edit-genitive-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in editUser genitive script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }
        $this->assertTrue($editGenitiveFound, 'В editUser.blade.php не найден script с parent_full_name_genitive');

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $parentForm, $parentScripts);
        $this->assertNotEmpty($parentScripts[1]);
        $parentGenitiveFound = false;
        foreach ($parentScripts[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'fullNameGenitive')
                && ! str_contains($rawScript, 'parent_full_name_genitive')
            ) {
                continue;
            }
            $parentGenitiveFound = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-parent-genitive-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in _parent_form genitive script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }
        $this->assertTrue($parentGenitiveFound, 'В _parent_form.blade.php не найден script с genitive');
    }

    /**
     * P1: legal-entities index — fillForm гидратирует bank_corr_account.
     */
    public function test_legal_entities_index_fill_form_bank_corr_account_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/legal-entities/index.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('function fillForm(form, data)', $content);
        $this->assertStringContainsString('bank_corr_account: data.bank_corr_account', $content);
        $this->assertStringContainsString('bank_account: data.bank_account', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, 'bank_corr_account')) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/blade-js-le-bank-corr-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in legal-entities bank_corr_account script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, 'В legal-entities/index.blade.php не найден script с bank_corr_account');
    }

    /**
     * P1: копирование {{variable}} в справочнике шаблонов договоров (в т.ч. Юр. лицо).
     */
    public function test_contract_template_variables_reference_copy_handler_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/contract-templates/partials/variables-reference.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('contract-template-copy-variable', $content);
        $this->assertStringContainsString('data-copy=', $content);
        $this->assertStringContainsString('navigator.clipboard', $content);
        $this->assertStringContainsString('ContractTemplateVariablePresets::groupLabels()', $content);
        $this->assertStringContainsString('recommendedForGroup($groupKey)', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (!str_contains($rawScript, 'contract-template-copy-variable')) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));
            $this->assertStringContainsString("closest('.contract-template-copy-variable')", $js);
            $this->assertStringContainsString("getAttribute('data-copy')", $js);

            $tempFile = sys_get_temp_dir() . '/blade-js-ct-vars-' . uniqid('', true) . '.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check ' . escapeshellarg($tempFile) . ' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in contract-templates variables-reference (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, 'В variables-reference.blade.php не найден script копирования переменных');
    }

    /**
     * P1: кабинет «Мои документы» — AJAX fill/generate (оба триггера открытия + submit).
     */
    public function test_account_documents_contract_fill_ajax_handlers_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/account/documents.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("submit', '#contractFillModal .contract-fill-form'", $content);
        $this->assertStringContainsString('e.preventDefault()', $content);
        $this->assertStringContainsString('loadContractFill', $content);
        $this->assertStringContainsString('window.openContractFillModal = loadContractFill', $content);
        $this->assertStringContainsString("click', '.js-open-contract-fill'", $content);
        $this->assertStringContainsString("click', '.js-open-contract-fill-edit'", $content);
        $this->assertStringContainsString("'mode=edit'", $content);
        $this->assertStringContainsString("headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}", $content);
        $this->assertStringContainsString('showFillAjaxErrors', $content);
        $this->assertStringContainsString('resp.poll', $content);

        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1]);

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (!str_contains($rawScript, 'loadContractFill')) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));
            $this->assertStringContainsString('e.preventDefault()', $js);
            $this->assertStringContainsString("'.js-open-contract-fill'", $js);
            $this->assertStringContainsString("'.js-open-contract-fill-edit'", $js);

            $tempFile = sys_get_temp_dir() . '/blade-js-docs-fill-' . uniqid('', true) . '.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check ' . escapeshellarg($tempFile) . ' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in account/documents fill script (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, 'В account/documents.blade.php не найден script loadContractFill');
    }

    /**
     * P1: createUser — скидка только у роли ученик; * у основания при % ≥ 1; сброс при reset.
     */
    public function test_create_user_modal_discount_js_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/includes/modal/createUser.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("@include('includes.modal._student_discount_fields'", $content);
        $this->assertStringContainsString("'prefix' => 'create'", $content);
        $this->assertStringContainsString('function setCreateUserDiscountFields(values)', $content);
        $this->assertStringContainsString('function syncCreateUserDiscountRequired()', $content);
        $this->assertStringContainsString('function resetCreateUserCommentSexFields()', $content);
        $this->assertStringContainsString("setCreateUserDiscountFields({ discount_percent: '', discount_comment: '' })", $content);
        $this->assertStringContainsString("find('.js-user-sex-wrap, .js-user-comment-wrap, .js-user-discount-wrap')", $content);
        $this->assertStringContainsString("toggleClass('d-none', !isStudent)", $content);
        $this->assertStringContainsString('const need = p >= 1;', $content);
        $this->assertStringContainsString(".js-user-discount-comment-required').toggleClass('d-none', !need)", $content);
        $this->assertStringContainsString('$createUserFormRoot.on(\'input change\', \'#create-discount_percent\', syncCreateUserDiscountRequired)', $content);
        $this->assertStringContainsString("window.resetCreateUserCommentSexFields = resetCreateUserCommentSexFields", $content);
        $this->assertStringContainsString("xhr.responseJSON.errors", $content);
        $this->assertStringContainsString('$form.find(\'[name="\' + safe + \'"]\')', $content);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'syncCreateUserDiscountRequired',
            'blade-js-create-discount'
        );

        $partial = (string) file_get_contents(resource_path('views/includes/modal/_student_discount_fields.blade.php'));
        $this->assertStringContainsString('id="{{ $fieldPrefix }}discount_percent"', $partial);
        $this->assertStringContainsString('data-error-for="discount_percent"', $partial);
        $this->assertStringContainsString('data-error-for="discount_comment"', $partial);
        $this->assertStringContainsString('js-user-discount-comment-required', $partial);
    }

    /**
     * P1: editUser — оба пути открытия модалки заполняют скидку; 0% → пустое поле, не «0».
     */
    public function test_edit_user_modal_discount_fill_contract_on_both_open_paths(): void
    {
        $path = resource_path('views/includes/modal/editUser.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("@include('includes.modal._student_discount_fields'", $content);
        $this->assertStringContainsString('function setEditUserDiscountFields(user)', $content);
        $this->assertStringContainsString('function syncEditUserDiscountRequired()', $content);
        $this->assertStringContainsString('setEditUserCommentSexFields(response.user)', $content);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($content, 'setEditUserCommentSexFields(response.user)')
        );
        $this->assertStringContainsString('function editUserLink2()', $content);
        $this->assertStringContainsString('function editUserLink()', $content);
        $this->assertStringContainsString("\$percent.val(p === 0 || p === '0' ? '' : p)", $content);
        $this->assertStringContainsString('ui?.canManageUserDiscount === true', $content);
        $this->assertStringContainsString("\$('.js-user-discount-wrap').remove()", $content);
        $this->assertStringContainsString("find('.js-user-sex-wrap, .js-user-comment-wrap, .js-user-discount-wrap')", $content);
        $this->assertStringContainsString('const need = p >= 1;', $content);
        $this->assertStringContainsString("$(document).on('input change', '#edit-discount_percent', syncEditUserDiscountRequired)", $content);

        $link2Pos = strpos($content, 'function editUserLink2()');
        $linkPos = strpos($content, 'function editUserLink()');
        $this->assertNotFalse($link2Pos);
        $this->assertNotFalse($linkPos);
        $this->assertStringContainsString(
            'setEditUserCommentSexFields(response.user)',
            substr($content, (int) $link2Pos, (int) $linkPos - (int) $link2Pos)
        );
        $this->assertStringContainsString(
            'setEditUserCommentSexFields(response.user)',
            substr($content, (int) $linkPos, 4000)
        );

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'setEditUserDiscountFields',
            'blade-js-edit-discount'
        );
    }

    /**
     * P1: вкладка «по ученикам» — дефолт цены со скидкой карточки; бейдж по снимку месяца; hide при ручной сумме.
     */
    public function test_setting_prices_year_tab_discount_js_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/SettingPrices/users.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('KidsCrmUserDiscount', $content);
        $this->assertStringContainsString('function payableRubAfterUserDiscount', $content);
        $this->assertStringContainsString('function yearUserDiscountPercent()', $content);
        $this->assertStringContainsString('lastYearUserDiscount.percent', $content);
        $this->assertStringContainsString('item.applied_discount_percent', $content);
        $this->assertStringContainsString('api.wrapPriceHtml(priceInputHtml, appliedPct, item.applied_discount_comment || \'\')', $content);
        $this->assertStringContainsString('$input.val(formatPriceValue(payableRubAfterUserDiscount(pkgPrice, pct)))', $content);
        $this->assertStringContainsString('api.hideBadge($wrap.get(0))', $content);
        $this->assertStringContainsString("\$('#user-prices-table-wrapper').on('change', '.setting-prices-monthly-package-select'", $content);
        $this->assertStringContainsString("\$('#user-prices-table-wrapper').on('input change', '.user-price-input'", $content);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'payableRubAfterUserDiscount',
            'blade-js-year-discount'
        );
    }

    /**
     * P1: назначения — смена ученика и смена пакета пересчитывают сумму со скидкой; бейдж в таблице и в модалке «Изменить».
     */
    public function test_lesson_package_assignments_discount_js_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/assignments.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('function selectedUserDiscount()', $content);
        $this->assertStringContainsString('function syncFeeFromSelectedPackage()', $content);
        $this->assertStringContainsString('window.ulpSyncFeeFromSelectedPackage = syncFeeFromSelectedPackage', $content);
        $this->assertStringContainsString('api.payableAfterDiscountCents(cents, disc.percent)', $content);
        $this->assertStringContainsString("\$ulpUser.on('change'", $content);
        $this->assertStringContainsString('window.ulpSyncFeeFromSelectedPackage()', $content);
        $this->assertStringContainsString("scheduleSelect?.addEventListener('change'", $content);
        $this->assertStringContainsString('function ulpFeeRender(data, type, row)', $content);
        $this->assertStringContainsString('KidsCrmUserDiscount.badgeHtml(row.discount_percent, row.discount_comment)', $content);
        $this->assertStringContainsString('feeInput.dataset.discountPercent', $content);
        $this->assertStringContainsString('function syncUlpModalFeeBadge(feeInput)', $content);
        $this->assertStringContainsString('api.hideBadge(wrap)', $content);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'syncFeeFromSelectedPackage',
            'blade-js-ulp-discount'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'syncUlpModalFeeBadge',
            'blade-js-ulp-modal-discount'
        );
    }

    /**
     * P1: календарь школы — create_new подставляет fee_amount_default со скидкой;
     * смена шаблона не затирает сумму, если поле уже трогали.
     */
    public function test_school_calendar_single_fee_discount_js_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/admin/lessonPackages/tabs/schoolSchedule.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('function syncSchoolCalSlotSingleFeeBadgeFromOption(opt)', $content);
        $this->assertStringContainsString('function populateSchoolCalSlotSingleForm(single)', $content);
        $this->assertStringContainsString('data-fee-default', $content);
        $this->assertStringContainsString('data-discount-percent', $content);
        $this->assertStringContainsString('data-discount-comment', $content);
        $this->assertStringContainsString('feeInp.value = first.fee_amount_default != null ? String(first.fee_amount_default) : \'\'', $content);
        $this->assertStringContainsString('syncSchoolCalSlotSingleFeeBadgeFromOption(tplSel.options[tplSel.selectedIndex])', $content);
        $this->assertStringContainsString('if (schoolCalSlotSingleFeeTouched)', $content);
        $this->assertStringContainsString('schoolCalSlotSingleFeeTouched = true', $content);
        $this->assertStringContainsString('api.hideBadge(wrap)', $content);

        $changePos = strpos($content, "document.getElementById('schoolCalSlotSingleTemplate')?.addEventListener('change'");
        $this->assertNotFalse($changePos);
        $changeChunk = substr($content, (int) $changePos, 900);
        $this->assertStringContainsString('if (schoolCalSlotSingleFeeTouched)', $changeChunk);
        $this->assertStringContainsString('return;', $changeChunk);
        $this->assertStringContainsString('data-fee-default', $changeChunk);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'syncSchoolCalSlotSingleFeeBadgeFromOption',
            'blade-js-schoolcal-discount'
        );
    }

    /**
     * P1: журнал — create_new несёт data-discount-*; бейдж только у create_new, иначе hide.
     */
    public function test_schedule_journal_empty_cell_discount_js_contract_is_valid_javascript(): void
    {
        foreach ([
            resource_path('js/schedule.js'),
            public_path('js/schedule-journal.js'),
        ] as $path) {
            $this->assertFileExists($path);
            $content = (string) file_get_contents($path);

            $this->assertStringContainsString("\$input.attr('data-discount-percent'", $content);
            $this->assertStringContainsString("\$input.attr('data-discount-comment'", $content);
            $this->assertStringContainsString('function syncEmptyCellFeeBadge($checked)', $content);
            $this->assertStringContainsString('data-mode', $content);
            $this->assertStringContainsString("!== 'create_new'", $content);
            $this->assertStringContainsString('api.hideBadge(wrap)', $content);
            $this->assertStringContainsString("data-discount-comment", $content);
            $this->assertStringContainsString('api.showBadge(wrap, pct', $content);

            $output = [];
            $exitCode = 0;
            exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
            $this->assertSame(
                0,
                $exitCode,
                "JS syntax error in {$path}:\n".implode("\n", $output)
            );
        }
    }

    /**
     * P1: ЗП тренеров — hotfix public/js/trainer-salary.js и resources/js/trainer-salary.js
     * (два пути, как schedule-journal). Канзас: reload_table + extra.team_id + data-save-trainer-id.
     */
    public function test_trainer_salary_js_kansas_reload_contract_is_valid_javascript(): void
    {
        $resourcePath = resource_path('js/trainer-salary.js');
        $publicPath = public_path('js/trainer-salary.js');
        $this->assertFileExists($resourcePath);
        $this->assertFileExists($publicPath);
        $this->assertSame(
            (string) file_get_contents($resourcePath),
            (string) file_get_contents($publicPath),
            'Hotfix public/js/trainer-salary.js должен совпадать с resources/js/trainer-salary.js'
        );

        $index = (string) file_get_contents(resource_path('views/admin/schedule/index.blade.php'));
        $this->assertStringContainsString("asset('js/trainer-salary.js')", $index);
        $this->assertStringNotContainsString("@vite(['resources/js/trainer-salary.js'])", $index);

        foreach ([$resourcePath, $publicPath] as $path) {
            $content = (string) file_get_contents($path);

            $this->assertStringContainsString('function applyTableHtml(html)', $content);
            $this->assertStringContainsString('result.body.reload_table && applyTableHtml(result.body.table_html)', $content);
            $this->assertStringContainsString("extra.team_id = teamHost.getAttribute('data-team-id')", $content);
            $this->assertStringContainsString("input.getAttribute('data-save-trainer-id')", $content);
            $this->assertStringContainsString("input.closest('.trainer-salary-kansas-x')", $content);
            $this->assertStringContainsString("input.closest('.trainer-salary-kansas-month-group')", $content);
            $this->assertStringContainsString("input.closest('[data-save-trainer-id]')", $content);
            $this->assertStringContainsString("field === 'base_avg_students'", $content);
            $this->assertStringContainsString('function applyMonthSettingsHtml(html)', $content);
            $this->assertStringContainsString("typeof data.month_settings_html === 'string'", $content);
            $this->assertStringContainsString('applyMonthSettingsHtml(data.month_settings_html)', $content);
            $this->assertStringContainsString('bindMonthSettingsEvents()', $content);
            $this->assertStringContainsString("getElementById('trainer-salary-kansas-month-settings-btn')", $content);
            $this->assertStringNotContainsString('monthSettingsBtn.addEventListener', $content);

            $this->assertSame(
                1,
                preg_match('/function fetchReport\(\) \{[\s\S]*?\n    function scheduleFetch/', $content, $fetchMatch),
                'Не найден fetchReport — смена месяца должна пересобирать модалку настроек'
            );
            $this->assertStringContainsString(
                'applyMonthSettingsHtml(data.month_settings_html)',
                $fetchMatch[0],
                'Смена месяца (GET …/data) должна подменять тело модалки настроек'
            );

            $this->assertSame(
                1,
                preg_match('/function saveDraft\([\s\S]*?\n    function formOne/', $content, $saveMatch),
                'Не найден saveDraft'
            );
            $this->assertStringNotContainsString(
                'applyMonthSettingsHtml',
                $saveMatch[0],
                'PATCH не должен затирать поля открытой модалки настроек месяца'
            );

            $this->assertSame(
                1,
                preg_match('/function formOne\([\s\S]*?\n    function formAll/', $content, $formOneMatch),
                'Не найден formOne'
            );
            $this->assertStringNotContainsString(
                'applyMonthSettingsHtml',
                $formOneMatch[0],
                '«Расчет» не должен затирать поля открытой модалки настроек месяца'
            );

            $this->assertSame(
                1,
                preg_match('/function formAll\(\) \{[\s\S]*?\n    function bindDraftInputs/', $content, $formAllMatch),
                'Не найден formAll'
            );
            $this->assertStringNotContainsString(
                'applyMonthSettingsHtml',
                $formAllMatch[0],
                '«Сформировать всех» не должен затирать поля открытой модалки настроек месяца'
            );
            $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $content);
            $this->assertStringContainsString("'Accept': 'application/json'", $content);
            $this->assertStringContainsString('showRowErrors(tr, result.body.errors)', $content);
            $this->assertStringContainsString('showHostFieldErrors(result.body.errors)', $content);
            $this->assertStringContainsString('[data-error-for="', $content);
            $this->assertStringContainsString('bindTableEvents()', $content);
            $this->assertStringContainsString("monthEl.addEventListener('change'", $content);
            $this->assertStringContainsString('if (!canManage)', $content);
            $this->assertStringContainsString("KidsCrmTooltip.dispose(tableHost, { scopes: ['hint'] })", $content);
            $this->assertStringContainsString("KidsCrmTooltip.init(tableHost, { scopes: ['hint'] })", $content);
            $this->assertStringContainsString("KidsCrmTooltip.dispose(monthSettingsHost, { scopes: ['hint'] })", $content);
            $this->assertStringContainsString("KidsCrmTooltip.init(monthSettingsHost, { scopes: ['hint'] })", $content);
            $this->assertStringContainsString('data-modal-title', $content);

            $output = [];
            $exitCode = 0;
            exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
            $this->assertSame(
                0,
                $exitCode,
                "JS syntax error in {$path}:\n".implode("\n", $output)
            );
        }

        foreach ([
            resource_path('views/admin/schedule/trainer_salary.blade.php'),
            resource_path('views/admin/schedule/trainer-salary/kansas/_table.blade.php'),
            resource_path('views/admin/schedule/trainer-salary/kansas/_sheet_detail_table.blade.php'),
            resource_path('views/admin/schedule/trainer-salary/kansas/_avg_cell.blade.php'),
            resource_path('views/admin/schedule/trainer-salary/kansas/_month_settings_body.blade.php'),
            resource_path('views/admin/schedule/trainer-salary/kansas/_month_settings_modal.blade.php'),
        ] as $blade) {
            $this->assertFileExists($blade);
            $this->assertStringNotContainsString(
                '<script',
                (string) file_get_contents($blade),
                'Таблица Канзаса не должна содержать inline <script> — логика в trainer-salary.js'
            );
        }

        $avgCell = (string) file_get_contents(resource_path('views/admin/schedule/trainer-salary/kansas/_avg_cell.blade.php'));
        $this->assertStringNotContainsString('data-kids-tooltip-hint', $avgCell);
        $this->assertStringNotContainsString('partials.ui.tooltip-hint', $avgCell);
        $this->assertStringNotContainsString('fa-info-circle', $avgCell);

        $monthBody = (string) file_get_contents(resource_path('views/admin/schedule/trainer-salary/kansas/_month_settings_body.blade.php'));
        $this->assertSame(1, substr_count($monthBody, 'data-kids-tooltip-hint'), 'Ховер только у X, не у базового среднего');
        $this->assertStringContainsString('data-field="premium_increment"', $monthBody);
        $this->assertStringContainsString('data-field="base_avg_students"', $monthBody);
        $this->assertStringContainsString('step="1"', $monthBody);
        $this->assertStringContainsString('step="0.01"', $monthBody);
        $this->assertStringNotContainsString('step="0.1"', $monthBody);
        $baseFieldPos = strpos($monthBody, 'data-field="base_avg_students"');
        $this->assertNotFalse($baseFieldPos);
        $baseChunk = substr($monthBody, $baseFieldPos, 700);
        $this->assertStringContainsString('step="1"', $baseChunk);
        $this->assertStringNotContainsString('data-kids-tooltip-hint', $baseChunk);
        $xFieldPos = strpos($monthBody, 'data-field="premium_increment"');
        $this->assertNotFalse($xFieldPos);
        $xChunk = substr($monthBody, $xFieldPos, 700);
        $this->assertStringContainsString('data-kids-tooltip-hint', $xChunk);
        $this->assertStringContainsString('step="0.01"', $xChunk);

        $kansasTable = (string) file_get_contents(resource_path('views/admin/schedule/trainer-salary/kansas/_table.blade.php'));
        $this->assertStringNotContainsString('data-kids-tooltip-hint', $kansasTable);
        $this->assertStringNotContainsString("'full' =>", $kansasTable);
        $sheetTable = (string) file_get_contents(resource_path('views/admin/schedule/trainer-salary/kansas/_sheet_detail_table.blade.php'));
        $this->assertStringNotContainsString('data-kids-tooltip-hint', $sheetTable);
        $this->assertStringNotContainsString("'full' =>", $sheetTable);
    }

    /**
     * P1: смена пароля в админке — три JS-пути (админы / ученики / тренеры)
     * показывают всплывайку, чистят поле и не шлют повтор того же пароля.
     * UX-баг на проде: AJAX без success/error, повтор «Применить» → 422 в консоли.
     */
    public function test_admin_password_change_js_paths_show_toast_and_skip_same_password(): void
    {
        $toastPath = resource_path('views/partials/ui/main-toast.blade.php');
        $this->assertFileExists($toastPath);
        $toast = (string) file_get_contents($toastPath);
        $this->assertStringContainsString('window.showToast = function (message, type)', $toast);
        $this->assertStringContainsString('id="kidsMainToast"', $toast);
        $this->assertStringContainsString('z-index: 4050', $toast);
        $this->assertStringNotContainsString('z-index: 1090', $toast);
        $this->assertStringContainsString('document.body.appendChild(wrap)', $toast);
        $this->assertStringContainsString('existing.dispose()', $toast);
        $this->assertStringContainsString('bootstrap.Toast.getOrCreateInstance', $toast);
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $toastPath,
            'window.showToast',
            'blade-js-kids-main-toast'
        );

        $layout = (string) file_get_contents(resource_path('views/layouts/admin2.blade.php'));
        $this->assertStringContainsString("@include('partials.ui.main-toast')", $layout);

        $files = [
            'role-staff' => resource_path('views/admin/role_staff/index.blade.php'),
            'edit-user'  => resource_path('views/includes/modal/editUser.blade.php'),
            'trainers'   => resource_path('views/admin/trainers/index.blade.php'),
        ];

        foreach ($files as $label => $path) {
            $this->assertFileExists($path, $label);
            $content = (string) file_get_contents($path);

            $this->assertStringContainsString('lastAppliedPasswordByUserId', $content, $label);
            $this->assertStringContainsString(
                "lastAppliedPasswordByUserId[userId] === newPassword",
                $content,
                "{$label}: повтор того же пароля не должен уходить на сервер"
            );
            $this->assertStringContainsString('Пароль успешно изменен', $content, $label);
            $this->assertStringContainsString('showToast', $content, $label);
            $this->assertStringContainsString('success:', $content, $label);
            $this->assertStringContainsString('error:', $content, $label);
            $this->assertStringContainsString("'Accept': 'application/json'", $content, $label);

            $this->assertInlineScriptsContainingHaveValidJavascript(
                $path,
                'lastAppliedPasswordByUserId',
                'blade-js-password-update-'.$label
            );
        }
    }

    /**
     * P1: нейминг ученика «Клиент» — оба пути открытия editUser и submit createUser.
     * UX-баг: один из дубликатов (editUserLink2 / editUserLink) или ветка
     * school-leads-table пересобирает заголовок/тост обратно на «пользователя».
     */
    public function test_student_client_naming_js_paths_keep_client_copy_and_do_not_reset_modal_title(): void
    {
        $createPath = resource_path('views/includes/modal/createUser.blade.php');
        $editPath = resource_path('views/includes/modal/editUser.blade.php');
        $leadsPath = resource_path('views/admin/school-leads/tabs/leads.blade.php');
        $trainersPath = resource_path('views/admin/trainers/index.blade.php');
        $staffPath = resource_path('views/admin/role_staff/index.blade.php');

        $create = (string) file_get_contents($createPath);
        $this->assertStringContainsString('id="createUserModalLabel">Создание клиента</h5>', $create);
        $this->assertStringContainsString('e.preventDefault()', $create);
        $this->assertStringContainsString("showSuccessModal(\n                        \"Создание клиента\"", $create);
        $this->assertStringContainsString('(response && response.message) ? response.message : "Клиент успешно создан."', $create);
        $this->assertStringContainsString("\$form.data('success-handler') === 'school-leads-table'", $create);
        $this->assertStringNotContainsString('Создание пользователя', $create);
        $this->assertStringNotContainsString('Пользователь успешно создан', $create);
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $createPath,
            "showSuccessModal(\n                        \"Создание клиента\"",
            'blade-js-create-user-client-naming'
        );

        $leads = (string) file_get_contents($leadsPath);
        $this->assertStringContainsString("window.showToast(message || 'Клиент создан.', 'success')", $leads);
        $this->assertStringContainsString("showErrorModal('Создание клиента'", $leads);
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $leadsPath,
            "window.showToast(message || 'Клиент создан.', 'success')",
            'blade-js-leads-create-client-naming'
        );

        $edit = (string) file_get_contents($editPath);
        $this->assertStringContainsString('id="editUserModalLabel">Редактирование клиента</h5>', $edit);
        $this->assertStringContainsString('function editUserLink2()', $edit);
        $this->assertStringContainsString('function editUserLink()', $edit);
        $this->assertStringContainsString('function editUserForm()', $edit);
        $this->assertStringContainsString('showSuccessModal("Редактирование клиента", "Клиент успешно обновлён.", 1);', $edit);
        $this->assertStringContainsString('window.showToast(\'Клиент успешно удален.\', \'success\');', $edit);
        $this->assertStringContainsString('showConfirmDeleteModal(', $edit);
        $this->assertStringContainsString('"Удаление клиента"', $edit);
        $this->assertStringContainsString('Вы уверены, что хотите удалить клиента?', $edit);
        $this->assertStringNotContainsString('Редактирование пользователя', $edit);
        $this->assertStringNotContainsString('Удаление пользователя', $edit);
        $this->assertStringNotContainsString('Пользователь успешно обновлён', $edit);

        $link2Pos = strpos($edit, 'function editUserLink2()');
        $linkPos = strpos($edit, 'function editUserLink()');
        $formPos = strpos($edit, 'function editUserForm()');
        $this->assertNotFalse($link2Pos);
        $this->assertNotFalse($linkPos);
        $this->assertNotFalse($formPos);
        $this->assertLessThan($linkPos, $link2Pos);
        $this->assertLessThan($formPos, $linkPos);

        $link2Body = substr($edit, $link2Pos, $linkPos - $link2Pos);
        $linkBody = substr($edit, $linkPos, $formPos - $linkPos);

        foreach (['editUserLink2' => $link2Body, 'editUserLink' => $linkBody] as $label => $body) {
            $this->assertStringNotContainsString(
                'editUserModalLabel',
                $body,
                "{$label} не должен перезаписывать заголовок модалки при открытии"
            );
            $this->assertStringNotContainsString('Редактирование пользователя', $body, $label);
            $this->assertStringContainsString("url: `/admin/users/\${userId}/edit`", $body, $label);
            $this->assertStringContainsString('$(\'#editUserModal\').modal(\'show\')', $body, $label);
        }

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $editPath,
            'function editUserLink2()',
            'blade-js-edit-user-link2-client-naming'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $editPath,
            'function editUserLink()',
            'blade-js-edit-user-link-client-naming'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $editPath,
            'function editUserForm()',
            'blade-js-edit-user-form-client-naming'
        );

        $trainers = (string) file_get_contents($trainersPath);
        $this->assertStringContainsString('id="trainerCreateModalLabel">Создание тренера</h5>', $trainers);
        $this->assertStringNotContainsString('id="trainerCreateModalLabel">Создание клиента</h5>', $trainers);

        $staff = (string) file_get_contents($staffPath);
        $this->assertStringContainsString('Пользователь создан', $staff);
        $this->assertStringNotContainsString('Клиент создан успешно', $staff);
    }

    /**
     * Успех без reload страницы — toast (#kidsMainToast), не showSuccessModal.
     */
    public function test_listed_success_actions_use_toast_instead_of_success_modal(): void
    {
        $cases = [
            'student-delete' => [
                'path' => resource_path('views/includes/modal/editUser.blade.php'),
                'toast' => 'Клиент успешно удален.',
                'absent' => 'showSuccessModal("Удаление клиента"',
            ],
            'trainer-create' => [
                'path' => resource_path('views/admin/trainers/index.blade.php'),
                'toast' => 'Тренер успешно создан.',
                'absent' => "showSuccessModal(\n                        'Создание тренера'",
            ],
            'trainer-edit' => [
                'path' => resource_path('views/admin/trainers/index.blade.php'),
                'toast' => 'Тренер успешно обновлён.',
                'absent' => "showSuccessModal(\n                        'Редактирование тренера'",
            ],
            'trainer-delete' => [
                'path' => resource_path('views/admin/trainers/index.blade.php'),
                'toast' => 'Тренер успешно удалён.',
                'absent' => "showSuccessModal(\n                                'Удаление тренера'",
            ],
            'admin-create' => [
                'path' => resource_path('views/admin/role_staff/index.blade.php'),
                'toast' => 'Пользователь создан',
                'absent' => "showSuccessModal(\n                            'Создание пользователя'",
            ],
            'legal-entity-create' => [
                'path' => resource_path('views/admin/legal-entities/index.blade.php'),
                'toast' => 'Юр. лицо создано',
                'absent' => 'showSuccessModal',
            ],
            'legal-entity-edit' => [
                'path' => resource_path('views/admin/legal-entities/index.blade.php'),
                'toast' => 'Юр. лицо обновлено',
                'absent' => 'showSuccessModal',
            ],
            'location-create' => [
                'path' => resource_path('views/admin/locations/index.blade.php'),
                'toast' => "window.showToast(data.message || 'Объект создан', 'success')",
                'absent' => "showSuccessModal('Создание объекта'",
            ],
            'location-edit' => [
                'path' => resource_path('views/admin/locations/index.blade.php'),
                'toast' => "window.showToast(data.message || 'Объект обновлён', 'success')",
                'absent' => "showSuccessModal('Редактирование объекта'",
            ],
            'location-delete' => [
                'path' => resource_path('views/admin/locations/index.blade.php'),
                'toast' => 'Объект успешно удалён.',
                'absent' => "showSuccessModal('Удаление объекта'",
            ],
            'trainer-type-save' => [
                'path' => public_path('js/trainer-types.js'),
                'toast' => 'Тип тренера сохранён',
                'absent' => "showSuccessModal('Типы тренеров'",
            ],
            'trainer-type-delete' => [
                'path' => public_path('js/trainer-types.js'),
                'toast' => 'Тип тренера удалён',
                'absent' => "showSuccessModal('Типы тренеров'",
            ],
            'lead-create-client' => [
                'path' => resource_path('views/admin/school-leads/tabs/leads.blade.php'),
                'toast' => "window.showToast(message || 'Клиент создан.', 'success')",
                'absent' => "showSuccessModal('Создание клиента'",
            ],
            'custom-payment-create' => [
                'path' => resource_path('js/setting-prices-custom-payments.js'),
                'toast' => 'Дополнительный платеж успешно создан.',
                'absent' => 'window.showSuccessModal',
            ],
            'custom-payment-create-public' => [
                'path' => public_path('js/setting-prices-custom-payments.js'),
                'toast' => 'Дополнительный платеж успешно создан.',
                'absent' => 'window.showSuccessModal',
            ],
            'custom-payment-update' => [
                'path' => resource_path('js/setting-prices-custom-payments.js'),
                'toast' => "window.showToast('Изменения сохранены.', 'success')",
                'absent' => 'priceToast',
            ],
            'custom-payment-update-public' => [
                'path' => public_path('js/setting-prices-custom-payments.js'),
                'toast' => "window.showToast('Изменения сохранены.', 'success')",
                'absent' => 'priceToast',
            ],
            'create-role' => [
                'path' => resource_path('views/admin/setting/rule.blade.php'),
                'toast' => 'Роль успешно создана.',
                'absent' => 'showSuccessModal("Создание роли"',
            ],
            'delete-role' => [
                'path' => resource_path('views/admin/setting/rule.blade.php'),
                'toast' => "window.showToast('Роль успешно удалена.', 'success')",
                'absent' => 'showSuccessModal("Удаление роли"',
            ],
            'account-own-password' => [
                'path' => resource_path('views/account/users.blade.php'),
                'toast' => 'Пароль успешно изменен.',
                'absent' => 'showSuccessModal("Изменение пароля"',
            ],
            'student-password' => [
                'path' => resource_path('views/includes/modal/editUser.blade.php'),
                'toast' => "window.showToast(message, type)",
                'absent' => "showSuccessModal('Обновление пароля'",
            ],
            'trainer-password' => [
                'path' => resource_path('views/admin/trainers/index.blade.php'),
                'toast' => "window.showToast(message, type)",
                'absent' => "showSuccessModal('Обновление пароля'",
            ],
            'student-send-password' => [
                'path' => resource_path('views/includes/modal/editUser.blade.php'),
                'toast' => "window.showToast(msg, 'success')",
                'absent' => "showSuccessModal('Отправка пароля'",
            ],
            'monthly-one-user' => [
                'path' => resource_path('js/settings-prices.js'),
                'toast' => "window.showToast('Изменения сохранены.', 'success')",
                'absent' => 'showSuccessModal("Редактирование цены"',
            ],
            'monthly-right-apply' => [
                'path' => resource_path('js/settings-prices.js'),
                'toast' => "window.showToast('Цены ученикам в выбранной группе успешно обновлены.', 'success')",
                'absent' => 'showSuccessModal("Установка цен в одной группе"',
            ],
        ];

        foreach ($cases as $label => $case) {
            $this->assertFileExists($case['path'], $label);
            $content = (string) file_get_contents($case['path']);
            $this->assertStringContainsString('window.showToast', $content, $label);
            $this->assertStringContainsString($case['toast'], $content, $label);
            $this->assertStringNotContainsString($case['absent'], $content, $label);

            if (str_ends_with($case['path'], '.blade.php')) {
                $this->assertInlineScriptsContainingHaveValidJavascript(
                    $case['path'],
                    'window.showToast',
                    'blade-js-success-toast-'.$label
                );
            } else {
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($case['path']).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    "JS syntax error in {$case['path']} ({$label}):\n".implode("\n", $output)
                );
            }
        }

        $locationsJs = (string) file_get_contents(resource_path('views/admin/locations/index.blade.php'));
        $this->assertStringContainsString(
            "window.showToast(data.message || 'Объект создан', 'success')",
            $locationsJs,
            'Создание объекта: toast #kidsMainToast после AJAX-успеха'
        );
        $this->assertStringContainsString(
            "window.showToast(data.message || 'Объект обновлён', 'success')",
            $locationsJs,
            'Редактирование объекта: toast #kidsMainToast после AJAX-успеха'
        );
        $this->assertStringContainsString(
            "confirmEl.addEventListener('hidden.bs.modal', showDeletedToast, { once: true })",
            $locationsJs,
            'Удаление объекта: toast после закрытия #confirmDeleteModal, иначе оверлей z-index 1900 его перекрывает'
        );
    }

    /**
     * UX-баг: удаление роли звало success-модалку и location.reload().
     * Create-путь обязан ставить data-role-id — иначе delete без reload не снимет колонку.
     */
    public function test_role_delete_without_reload_js_updates_dom_instead_of_success_modal(): void
    {
        $path = resource_path('views/admin/setting/rule.blade.php');
        $this->assertFileExists($path);
        $js = (string) file_get_contents($path);

        $this->assertStringNotContainsString('showSuccessModal("Удаление роли"', $js);
        $this->assertStringNotContainsString('location.reload();', $js);
        $this->assertStringContainsString("window.showToast('Роль успешно удалена.', 'success')", $js);
        $this->assertStringContainsString('function removeRoleColumnFromPermissionTables', $js);
        $this->assertStringContainsString('function removeRoleFromRolesTable', $js);

        $appendPos = strpos($js, 'function appendRoleColumnToPermissionTables');
        $this->assertNotFalse($appendPos);
        $this->assertStringContainsString(".attr('data-role-id', roleId)", substr($js, $appendPos, 1400));

        $removePos = strpos($js, 'function removeRoleColumnFromPermissionTables');
        $this->assertNotFalse($removePos);
        $this->assertStringContainsString('thead th[data-role-id="', substr($js, $removePos, 1100));
        $this->assertLessThan($removePos, $appendPos);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'function removeRoleColumnFromPermissionTables',
            'blade-js-role-delete-column'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'function removeRoleFromRolesTable',
            'blade-js-role-delete-row'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            "window.showToast('Роль успешно удалена.', 'success')",
            'blade-js-role-delete-toast'
        );
    }

    /**
     * P1: типы тренера Канзаса — hotfix public/js/trainer-types.js и два JS-пути
     * (карточка тренера обновляет селекты, ЗП перезагружает таблицу только после save).
     */
    public function test_trainer_types_js_contract_is_valid_javascript(): void
    {
        $path = public_path('js/trainer-types.js');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString("headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }", $content);
        $this->assertStringContainsString("'Content-Type': 'application/json'", $content);
        $this->assertStringContainsString("form.querySelector('[data-error-for=\"' + key + '\"]')", $content);
        $this->assertStringContainsString("if (res.status === 422)", $content);
        $this->assertStringContainsString('showFieldErrors(data.errors || {})', $content);
        $this->assertStringContainsString("rate_per_training: '0.00'", $content);
        $this->assertStringContainsString("base_premium: '0.00'", $content);
        $this->assertStringContainsString("sort_order: 10", $content);
        $this->assertStringContainsString("loadList('open')", $content);
        $this->assertStringContainsString("await loadList('saved')", $content);
        $this->assertStringContainsString("reason = reason || 'open'", $content);
        $this->assertStringContainsString('window.__onTrainerTypesChanged(types, reason)', $content);
        $this->assertStringContainsString('if (!canManage) return', $content);
        $this->assertStringContainsString('window.showConfirmDeleteModal', $content);
        $this->assertStringContainsString('Удаление типа тренера', $content);
        $this->assertStringNotContainsString('window.confirm', $content);
        $this->assertStringContainsString('enabled.disabled = !!type?.is_system', $content);
        $this->assertStringContainsString("type?.rate_per_training ?? '0.00'", $content);

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in {$path}:\n".implode("\n", $output)
        );

        $trainersIndex = resource_path('views/admin/trainers/index.blade.php');
        $trainersJs = (string) file_get_contents($trainersIndex);
        $this->assertStringContainsString('window.__onTrainerTypesChanged = function (types)', $trainersJs);
        $this->assertStringContainsString('if (select.disabled)', $trainersJs);
        $this->assertStringContainsString('Number(t.is_enabled) === 1', $trainersJs);
        $this->assertStringContainsString('Number(currentType.is_enabled) !== 1', $trainersJs);
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $trainersIndex,
            'window.__onTrainerTypesChanged',
            'blade-js-trainer-types-selects'
        );

        $salaryIndex = resource_path('views/admin/schedule/index.blade.php');
        $salaryJs = (string) file_get_contents($salaryIndex);
        $this->assertStringContainsString("window.__onTrainerTypesChanged = function (types, reason)", $salaryJs);
        $this->assertStringContainsString("if (reason === 'open')", $salaryJs);
        $this->assertStringContainsString('window.__reloadTrainerSalaryReport()', $salaryJs);
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $salaryIndex,
            'window.__onTrainerTypesChanged',
            'blade-js-trainer-types-salary-reload'
        );

        $assets = resource_path('views/admin/trainers/_trainer_types_assets.blade.php');
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $assets,
            '__trainerTypesConfig',
            'blade-js-trainer-types-config'
        );

        $modal = (string) file_get_contents(resource_path('views/admin/trainers/_trainer_types_modal.blade.php'));
        $this->assertStringNotContainsString('<script', $modal);
        $this->assertStringContainsString('data-error-for="name"', $modal);
        $this->assertStringContainsString('data-error-for="rate_per_training"', $modal);
        $this->assertStringContainsString('data-error-for="base_premium"', $modal);
        $this->assertStringContainsString('class="modal-dialog"', $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
    }

    /**
     * P1: общий helper бейджа % — синтаксис и контракт формулы (1000 − 10% = 900).
     */
    public function test_discount_percent_js_partial_contract_is_valid_javascript(): void
    {
        $path = resource_path('views/partials/ui/discount-percent-js.blade.php');
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('w.KidsCrmUserDiscount = {', $content);
        $this->assertStringContainsString('payableAfterDiscountCents: function (priceCents, percent)', $content);
        $this->assertStringContainsString('const discount = Math.round(cents * p / 100);', $content);
        $this->assertStringContainsString('return cents - discount;', $content);
        $this->assertStringContainsString('wrapPriceHtml: function (inputHtml, percent, comment)', $content);
        $this->assertStringContainsString('hideBadge: function (wrapEl)', $content);
        $this->assertStringContainsString('matchesPayable: function (amountRub, catalogRub, percent)', $content);
        $this->assertStringContainsString("'Скидка ' + p + '%. '", $content);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $path,
            'KidsCrmUserDiscount',
            'blade-js-discount-helper'
        );
    }

    public function test_in_app_notifications_compose_and_bell_js_contracts_and_valid_javascript(): void
    {
        $composePath = resource_path('views/admin/in_app_notifications/compose.blade.php');
        $echoPath = resource_path('views/includes/in_app_notifications/echo.blade.php');
        $indexPath = resource_path('views/admin/in_app_notifications/index.blade.php');
        $this->assertFileExists($composePath);
        $this->assertFileExists($echoPath);
        $this->assertFileExists($indexPath);

        $compose = (string) file_get_contents($composePath);
        $echo = (string) file_get_contents($echoPath);
        $index = (string) file_get_contents($indexPath);

        $this->assertStringContainsString('inAppNotificationComposeForm', $compose);
        $this->assertStringContainsString('reloadRoles', $compose);
        $this->assertStringContainsString('fetch(', $compose);
        $this->assertStringContainsString('X-Requested-With', $compose);
        $this->assertStringContainsString("partnersWrap.style.display = checked ? 'none' : ''", $compose);
        $this->assertStringContainsString("ttlPreset.value === 'custom'", $compose);
        $this->assertStringContainsString("selected.indexOf(String(role.id)) !== -1", $compose);
        $this->assertStringContainsString("\$body.val(\$body.summernote('code'))", $compose);
        $this->assertStringContainsString("['insert', ['link']]", $compose);
        $this->assertStringNotContainsString('preventDefault', $compose);

        $this->assertStringContainsString('item.page_url', $echo);
        $this->assertStringContainsString('bell-preview', $echo);
        $this->assertStringContainsString("category === 'update' || category === 'important'", $echo);
        $this->assertStringNotContainsString("category === 'normal'", $echo);
        $this->assertStringNotContainsString('open_url', $echo);
        $this->assertStringNotContainsString('js-in-app-bell-mark-read', $echo);

        $this->assertStringContainsString('scrollIntoView', $index);
        $this->assertStringContainsString("behavior: 'smooth'", $index);

        $this->assertInlineScriptsContainingHaveValidJavascript(
            $composePath,
            'inAppNotificationComposeForm',
            'blade-js-in-app-compose'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $echoPath,
            'in-app-notification.bell',
            'blade-js-in-app-echo'
        );
        $this->assertInlineScriptsContainingHaveValidJavascript(
            $indexPath,
            'scrollIntoView',
            'blade-js-in-app-index'
        );
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

    /**
     * @param  non-empty-string  $needle
     */
    private function assertInlineScriptsContainingHaveValidJavascript(string $path, string $needle, string $tempPrefix): void
    {
        $content = (string) file_get_contents($path);
        preg_match_all('/<script(?![^>]*\bsrc\b)[^>]*>(.*?)<\/script>/is', $content, $matches);
        $this->assertNotEmpty($matches[1], "В {$path} не найдено inline <script>");

        $found = false;
        foreach ($matches[1] as $index => $rawScript) {
            if (! str_contains($rawScript, $needle)) {
                continue;
            }
            $found = true;
            $js = $this->normalizeBladeScriptForSyntaxCheck($rawScript);
            $this->assertNotSame('', trim($js));

            $tempFile = sys_get_temp_dir().'/'.$tempPrefix.'-'.uniqid('', true).'.js';
            try {
                file_put_contents($tempFile, $js);
                $output = [];
                $exitCode = 0;
                exec('node --check '.escapeshellarg($tempFile).' 2>&1', $output, $exitCode);
                $this->assertSame(
                    0,
                    $exitCode,
                    sprintf(
                        "JS syntax error in %s (needle %s, block #%d):\n%s\n--- preview ---\n%s",
                        $path,
                        $needle,
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
                    )
                );
            } finally {
                @unlink($tempFile);
            }
        }

        $this->assertTrue($found, "В {$path} не найден script с «{$needle}»");
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

    /**
     * UX-баг: оверлей ждал draw.dt или обёртку KidsCrmDataTable.create на parse-time
     * Vite-модуля → is-ready не ставился, спиннер крутился бесконечно.
     * Журнал SSR: reveal сразу после DataTable({...}) и в catch, в обеих копиях JS.
     */
    private function assertScheduleJournalRevealAfterDataTableContract(string $js, string $path): void
    {
        $this->assertStringContainsString('function revealScheduleJournalTable()', $js, $path);
        $this->assertStringContainsString("stage.classList.add('is-ready')", $js, $path);
        $this->assertStringContainsString("stage.setAttribute('aria-busy', 'false')", $js, $path);
        $this->assertStringContainsString('$(\'#schedule-table\').DataTable({', $js, $path);
        $this->assertStringNotContainsString('KidsCrmTablePreloader', $js, $path);
        $this->assertStringNotContainsString('bindTablePreloader', $js, $path);
        $this->assertStringNotContainsString('KidsCrmDataTable.create(\'#schedule-table\'', $js, $path);
        $this->assertStringNotContainsString('draw.dt', $js, $path);

        $dtPos = strpos($js, '$(\'#schedule-table\').DataTable({');
        $this->assertNotFalse($dtPos, $path);
        $afterDt = substr($js, $dtPos);
        $this->assertMatchesRegularExpression(
            '/\$\(\'#schedule-table\'\)\.DataTable\(\{[\s\S]*?\}\);\s*revealScheduleJournalTable\(\);/',
            $afterDt,
            $path.': revealScheduleJournalTable() должен вызываться сразу после DataTable(), а не только объявляться выше'
        );
        $this->assertMatchesRegularExpression(
            '/catch\s*\(\s*err\s*\)\s*\{\s*revealScheduleJournalTable\(\);/',
            $js,
            $path.': catch тоже снимает прелоадер, иначе при ошибке DataTable спиннер бесконечный'
        );

        $output = [];
        $exitCode = 0;
        exec('node --check '.escapeshellarg($path).' 2>&1', $output, $exitCode);
        $this->assertSame(
            0,
            $exitCode,
            "JS syntax error in {$path}:\n".implode("\n", $output)
        );
    }

    private function scheduleJournalRevealSnippet(string $js): string
    {
        $start = strpos($js, 'function revealScheduleJournalTable()');
        $this->assertNotFalse($start);
        $throwPos = strpos($js, 'throw err;', $start);
        $this->assertNotFalse($throwPos);

        return substr($js, $start, $throwPos + strlen('throw err;') - $start);
    }
}
