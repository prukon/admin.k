<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

/**
 * Разметка модалки «с договором / без» и JS-контракт: дефолт, reopen, оба пути кнопки.
 */
final class SchoolLeadCreateClientWithContractUiContractsFeatureTest extends SchoolLeadCreateClientWithContractTestCase
{
    public function test_first_open_checks_with_contract_and_shows_paid_warning(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->makeContractTemplate(['title' => 'Единственный шаблон']);

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="createLeadClientChoiceModal"', $html);
        $this->assertMatchesRegularExpression(
            '/id="leadCreateClientModeWithContract"[^>]*\bchecked\b/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="leadCreateClientModeWithoutContract"[^>]*\bchecked\b/',
            $html
        );
        $this->assertStringContainsString('Это действие платное', $html);
        $this->assertStringContainsString('с баланса будет списано 70 ₽', $html);

        $modalStart = strpos($html, 'id="createLeadClientChoiceModal"');
        $this->assertNotFalse($modalStart);
        $choiceModalHtml = substr($html, $modalStart, 3500);
        $this->assertStringContainsString('modal-dialog modal-dialog-centered', $choiceModalHtml);
        $this->assertStringNotContainsString('modal-xl', $choiceModalHtml);
        $this->assertStringNotContainsString('modal-fullscreen', $choiceModalHtml);

        $withPos = strpos($html, 'id="leadCreateClientModeWithContract"');
        $withoutPos = strpos($html, 'id="leadCreateClientModeWithoutContract"');
        $templatePos = strpos($html, 'id="leadCreateClientTemplateId"');
        $paidPos = strpos($html, 'Это действие платное');
        $this->assertNotFalse($withPos);
        $this->assertNotFalse($withoutPos);
        $this->assertNotFalse($templatePos);
        $this->assertNotFalse($paidPos);
        $this->assertLessThan($withoutPos, $withPos);
        $this->assertLessThan($templatePos, $withoutPos);
        $this->assertLessThan($paidPos, $templatePos);
    }

    public function test_single_template_is_preselected_on_first_open(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $template = $this->makeContractTemplate(['title' => 'Автовыбор шаблона']);

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Автовыбор шаблона', $html);
        $this->assertStringNotContainsString('— выберите шаблон —', $html);
        $this->assertMatchesRegularExpression(
            '/<option value="'.$template->id.'"[^>]*\bselected\b/',
            $html
        );
    }

    public function test_multiple_templates_require_explicit_choice_and_none_are_forced(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $first = $this->makeContractTemplate(['title' => 'Шаблон Ааа']);
        $second = $this->makeContractTemplate(['title' => 'Шаблон Ббб']);

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('— выберите шаблон —', $html);
        $this->assertStringContainsString('Шаблон Ааа', $html);
        $this->assertStringContainsString('Шаблон Ббб', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="'.$first->id.'"[^>]*\bselected\b/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="'.$second->id.'"[^>]*\bselected\b/',
            $html
        );
    }

    public function test_no_templates_shows_warning_instead_of_select(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="createLeadClientChoiceModal"', $html);
        $this->assertStringContainsString('Шаблонов нет', $html);
        $this->assertStringContainsString('Создать шаблон', $html);
        $this->assertStringNotContainsString('id="leadCreateClientTemplateId"', $html);
        $this->assertStringContainsString('var hasLeadContractTemplates = false', $html);
    }

    public function test_archived_template_is_not_listed_in_choice_modal(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();
        $this->makeContractTemplate(['title' => 'Активный для списка']);
        $this->makeContractTemplate([
            'title'       => 'Архивный скрытый',
            'is_archived' => true,
        ]);

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Активный для списка', $html);
        $this->assertStringNotContainsString('Архивный скрытый', $html);
    }

    public function test_create_client_button_and_choice_modal_hidden_without_users_view(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'contracts.view');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertDontSee('id="createClientBtn"', false)
            ->assertDontSee('id="createLeadClientChoiceModal"', false);
    }

    public function test_without_contracts_view_page_does_not_force_with_contract_default(): void
    {
        $actor = $this->createUserWithoutPermission('contracts.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');
        $this->grantPermission($actor, 'users.view');

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="createClientBtn"', $html);
        $this->assertStringNotContainsString('id="createLeadClientChoiceModal"', $html);
        $this->assertStringContainsString('var canViewContracts = false', $html);
        $this->assertStringContainsString('submitCreateClientFromLead({ sendContract: false })', $html);
    }

    public function test_with_contracts_view_js_opens_choice_modal_instead_of_immediate_submit(): void
    {
        $this->actingAsLeadsUsersAndContractsViewer();

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('var canViewContracts = true', $html);
        $this->assertStringContainsString('precheckCreateClientFromLeadThenOpenChoice()', $html);
        $this->assertStringContainsString('openCreateLeadClientChoiceModal()', $html);
        $this->assertStringContainsString("if (canViewContracts && document.getElementById('createLeadClientChoiceModal'))", $html);
        $this->assertStringContainsString('clientPayload.validate_only = 1', $html);
    }

    public function test_choice_modal_markup_and_js_contract(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));
        $modal = (string) file_get_contents(resource_path('views/admin/school-leads/partials/create-client-choice-modal.blade.php'));
        $toast = (string) file_get_contents(resource_path('views/partials/ui/main-toast.blade.php'));

        $this->assertStringContainsString("@include('admin.school-leads.partials.create-client-choice-modal')", $js);
        $this->assertStringContainsString('function openCreateLeadClientChoiceModal()', $js);
        $this->assertStringContainsString('function precheckCreateClientFromLeadThenOpenChoice()', $js);
        $this->assertStringContainsString('submitCreateClientFromLead({ sendContract: false })', $js);
        $this->assertStringContainsString('clientPayload.send_contract', $js);
        $this->assertStringContainsString('clientPayload.validate_only = 1', $js);
        $this->assertStringContainsString('hideToast()', $js);
        $this->assertStringContainsString("$(document).on('change', 'input[name=\"lead_create_client_mode\"]'", $js);
        $this->assertStringContainsString('isLeadContractPrecheckError(errors)', $js);
        $this->assertStringContainsString("showModalQueued('createLeadClientChoiceModal'", $js);

        $openStart = strpos($js, 'function openCreateLeadClientChoiceModal()');
        $this->assertNotFalse($openStart);
        $openFn = substr($js, $openStart, 1400);
        $this->assertStringContainsString("$('#leadCreateClientModeWithContract').prop('checked', true)", $openFn);
        $this->assertStringContainsString("$('#leadCreateClientModeWithoutContract').prop('checked', false)", $openFn);
        $this->assertStringContainsString('hideToast()', $openFn);
        $this->assertStringContainsString('clearLeadCreateClientChoiceErrors()', $openFn);
        $this->assertStringContainsString('suppressLeadModalResetOnHide = true', $openFn);
        $this->assertStringContainsString("showModalQueued('createLeadClientChoiceModal'", $openFn);
        $this->assertLessThan(
            strpos($openFn, "showModalQueued('createLeadClientChoiceModal'"),
            strpos($openFn, 'suppressLeadModalResetOnHide = true')
        );

        $radioPos = strpos($js, "$(document).on('change', 'input[name=\"lead_create_client_mode\"]'");
        $this->assertNotFalse($radioPos);
        $radioChunk = substr($js, $radioPos, 280);
        $this->assertStringContainsString('hideToast()', $radioChunk);
        $this->assertStringContainsString('clearLeadCreateClientChoiceErrors()', $radioChunk);
        $this->assertStringContainsString('syncLeadCreateClientChoiceUi()', $radioChunk);

        $submitStart = strpos($js, 'function submitCreateClientFromLead(options)');
        $this->assertNotFalse($submitStart);
        $submitFn = substr($js, $submitStart, 4500);
        $this->assertStringContainsString('fromChoiceModal && isLeadContractPrecheckError(errors)', $submitFn);
        $this->assertStringContainsString('closeCreateLeadClientChoiceModal(true)', $submitFn);
        $this->assertStringContainsString('applyLeadFormErrors(errors)', $submitFn);
        $this->assertStringContainsString('applyLeadCreateClientChoiceErrors(errors)', $submitFn);
        $this->assertStringContainsString('saveLeadAjax()', $submitFn);
        $this->assertStringNotContainsString('validate_only', $submitFn);

        $precheckStart = strpos($js, 'function precheckCreateClientFromLeadThenOpenChoice()');
        $this->assertNotFalse($precheckStart);
        $precheckFn = substr($js, $precheckStart, 2200);
        $this->assertStringContainsString('clientPayload.validate_only = 1', $precheckFn);
        $this->assertStringContainsString('buildCreateClientFromLeadPayload({ sendContract: false })', $precheckFn);
        $this->assertStringContainsString('applyLeadFormErrors(errors)', $precheckFn);
        $this->assertStringContainsString('showCreateClientResultModal(false, message)', $precheckFn);
        $this->assertStringContainsString('openCreateLeadClientChoiceModal()', $precheckFn);
        $this->assertStringNotContainsString('saveLeadAjax()', $precheckFn);

        $clickPos = strpos($js, "$('#createClientBtn').on('click'");
        $this->assertNotFalse($clickPos);
        $clickChunk = substr($js, $clickPos, 900);
        $this->assertStringContainsString('precheckCreateClientFromLeadThenOpenChoice()', $clickChunk);
        $this->assertStringNotContainsString('openCreateLeadClientChoiceModal();', $clickChunk);

        $applyStart = strpos($js, 'function applyLeadCreateClientChoiceErrors(errors)');
        $this->assertNotFalse($applyStart);
        $applyFn = substr($js, $applyStart, 700);
        $this->assertStringContainsString('errors.contract_template_id', $applyFn);
        $this->assertStringContainsString('errors.send_contract', $applyFn);
        $this->assertStringNotContainsString('errors.wallet', $applyFn);

        $okPos = strpos($js, "$('#createLeadClientChoiceOkBtn').on('click'");
        $this->assertNotFalse($okPos);
        $okChunk = substr($js, $okPos, 1800);
        $this->assertStringContainsString("contract_template_id: ['Выберите шаблон договора.']", $okChunk);
        $this->assertStringContainsString('sendContract: true', $okChunk);
        $this->assertStringContainsString('fromChoiceModal: true', $okChunk);
        $this->assertStringContainsString('sendContract: false', $okChunk);

        $this->assertStringContainsString('id="createLeadClientChoiceModal"', $modal);
        $this->assertStringContainsString('value="with_contract"', $modal);
        $this->assertStringContainsString('value="without_contract"', $modal);
        $this->assertStringContainsString('checked', $modal);
        $this->assertStringContainsString('createLeadClientChoiceOkBtn', $modal);
        $this->assertStringContainsString('Это действие платное', $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);

        $this->assertStringContainsString('window.hideToast = function ()', $toast);
    }

    public function test_opening_choice_modal_does_not_reset_parent_email_when_lead_modal_is_queued_away(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));

        $this->assertStringContainsString('var suppressLeadModalResetOnHide = false', $js);
        $this->assertStringContainsString('function resetLeadModalTransientState()', $js);

        $openStart = strpos($js, 'function openCreateLeadClientChoiceModal()');
        $this->assertNotFalse($openStart);
        $openFn = substr($js, $openStart, 1400);
        $this->assertStringContainsString('editLeadModalEl.classList.contains(\'show\')', $openFn);
        $flagPos = strpos($openFn, 'suppressLeadModalResetOnHide = true');
        $queuePos = strpos($openFn, "showModalQueued('createLeadClientChoiceModal'");
        $this->assertNotFalse($flagPos);
        $this->assertNotFalse($queuePos);
        $this->assertLessThan($queuePos, $flagPos, 'Флаг должен ставиться до hide заявки через showModalQueued');

        $hiddenPos = strpos($js, "editLeadModalEl.addEventListener('hidden.bs.modal', function()");
        $this->assertNotFalse($hiddenPos);
        $hiddenFn = substr($js, $hiddenPos, 500);
        $this->assertStringContainsString('if (suppressLeadModalResetOnHide)', $hiddenFn);
        $this->assertStringContainsString('suppressLeadModalResetOnHide = false', $hiddenFn);
        $earlyReturn = strpos($hiddenFn, 'return;');
        $resetCall = strpos($hiddenFn, 'resetLeadModalTransientState()');
        $this->assertNotFalse($earlyReturn);
        $this->assertNotFalse($resetCall);
        $this->assertLessThan($resetCall, $earlyReturn);

        $resetStart = strpos($js, 'function resetLeadModalTransientState()');
        $this->assertNotFalse($resetStart);
        $resetFn = substr($js, $resetStart, 600);
        $this->assertStringContainsString("resetStudentParentForm('lead')", $resetFn);

        $closeStart = strpos($js, 'function closeCreateLeadClientChoiceModal(returnToLead)');
        $this->assertNotFalse($closeStart);
        $closeFn = substr($js, $closeStart, 900);
        $this->assertStringContainsString('resetLeadModalTransientState()', $closeFn);
    }

    public function test_collect_create_client_payload_does_not_set_send_contract(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));

        $createStart = strpos($js, 'function collectCreateClientPayload()');
        $createEnd = strpos($js, 'function saveLeadAjax()');
        $this->assertNotFalse($createStart);
        $this->assertNotFalse($createEnd);
        $createFn = substr($js, $createStart, $createEnd - $createStart);
        $this->assertStringNotContainsString('send_contract', $createFn);
        $this->assertStringNotContainsString('contract_template_id', $createFn);

        $submitStart = strpos($js, 'function submitCreateClientFromLead(options)');
        $this->assertNotFalse($submitStart);
        $submitFn = substr($js, $submitStart, 1800);
        $this->assertStringContainsString('buildCreateClientFromLeadPayload({', $submitFn);
        $this->assertStringContainsString('saveLeadAjax()', $submitFn);

        $buildStart = strpos($js, 'function buildCreateClientFromLeadPayload(options)');
        $this->assertNotFalse($buildStart);
        $buildFn = substr($js, $buildStart, 900);
        $this->assertStringContainsString('clientPayload.send_contract = options.sendContract ? 1 : 0', $buildFn);
        $this->assertStringContainsString('clientPayload.contract_template_id = options.templateId || \'\'', $buildFn);
    }

    public function test_widget_doc_describes_choice_modal_and_precheck(): void
    {
        $html = (string) file_get_contents(base_path('docs/documentation/school-leads-widget.html'));

        $this->assertStringContainsString('createLeadClientChoiceModal', $html);
        $this->assertStringContainsString('send_contract', $html);
        $this->assertStringContainsString('contract_template_id', $html);
        $this->assertStringContainsString('Недостаточно средств', $html);
        $this->assertStringContainsString('SchoolLeadCreateClientWithContractFeatureTest', $html);
        $this->assertStringContainsString('SchoolLeadCreateClientWithContractWorkflowFeatureTest', $html);
        $this->assertStringContainsString('/doc#school-lead-create-client-choice-index', $html);
        $this->assertStringContainsString('Без <code>contracts.view</code>', $html);
        $this->assertStringContainsString('validate_only', $html);
        $this->assertStringContainsString('precheckCreateClientFromLeadThenOpenChoice', $html);
        $this->assertStringContainsString('Этот адрес электронной почты уже зарегистрирован.', $html);
    }
}
