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
        yield 'legal entities show sm and crud forms' => ['admin/legal-entities/show.blade.php'];
        yield 'teams index legal entity column' => ['admin/team.blade.php'];
        yield 'account organization tab ajax form' => ['account/organizations.blade.php'];
        yield 'admin partner create edit modals' => ['includes/modal/editPartner.blade.php'];
        yield 'partner lead landing form' => ['landing/partner-lead.blade.php'];
        yield 'contract templates variables reference copy js' => ['contract-templates/partials/variables-reference.blade.php'];
        yield 'contract templates edit modal init' => ['contract-templates/partials/edit-modal-init.blade.php'];
        yield 'contract templates index page scripts' => ['contract-templates/index.blade.php'];
        yield 'account documents fill modal ajax' => ['account/documents.blade.php'];
        yield 'account settings tabs shell' => ['account/index.blade.php'];
        yield 'cabinet attach team modal' => ['includes/modal/cabinet_attach_team_modal.blade.php'];
    }

    /**
     * P1: модалка «Добавить группу» в ЛК — AJAX-контракт (preventDefault, fetch, errors.team_id, reload).
     */
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
                    sprintf(
                        "JS syntax error in cabinet attach team modal (block #%d):\n%s\n--- preview ---\n%s",
                        $index + 1,
                        implode("\n", $output),
                        mb_substr($js, 0, 800)
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
