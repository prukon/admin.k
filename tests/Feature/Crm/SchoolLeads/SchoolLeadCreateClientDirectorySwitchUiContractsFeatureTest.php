<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

/**
 * Разметка модалки лида и JS-контракт: payload из формы, toast ошибок, режимы родителя.
 */
final class SchoolLeadCreateClientDirectorySwitchUiContractsFeatureTest extends SchoolLeadCreateClientDirectorySwitchTestCase
{
    public function test_first_open_shows_parent_mode_toggle_hidden_toast_and_no_inline_error_alert(): void
    {
        $this->actingAsLeadsAndUsersViewer();
        $this->makeDirectoryParent();

        $html = $this->get(route('admin.school-leads'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="editLeadModal"', $html);
        $this->assertStringContainsString('id="createClientBtn"', $html);
        $this->assertMatchesRegularExpression('/id="createClientBtn"[^>]*\bdisabled\b/', $html);
        $this->assertStringContainsString('Из справочника', $html);
        $this->assertStringContainsString('Новый родитель', $html);
        $this->assertStringContainsString('id="lead-parent-id"', $html);
        $this->assertStringContainsString('id="lead-parent-email"', $html);
        $this->assertStringContainsString('js-parent-mode-btn', $html);
        $this->assertStringContainsString('data-mode="directory"', $html);
        $this->assertStringContainsString('data-mode="new"', $html);
        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringNotContainsString('id="editLeadError"', $html);
        $this->assertStringNotContainsString('id="editLeadSuccess"', $html);
        $this->assertStringNotContainsString('id="mainToast"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="kidsMainToast"[^>]*\bshow\b/', $html);

        $modalBlade = (string) file_get_contents(resource_path('views/admin/school-leads/partials/edit-lead-modal.blade.php'));
        $includePos = strpos($modalBlade, "@include('partials.ui.main-toast')");
        $lastModalDiv = strrpos($modalBlade, '</div>');
        $this->assertNotFalse($includePos);
        $this->assertGreaterThan($lastModalDiv, $includePos);
    }

    public function test_create_client_button_hidden_without_users_view(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermission($actor, 'schoolLeads.view');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertDontSee('id="createClientBtn"', false);
    }

    public function test_collect_create_client_payload_reads_form_not_snapshot(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));

        $createStart = strpos($js, 'function collectCreateClientPayload()');
        $createEnd = strpos($js, 'function saveLeadAjax()');
        $this->assertNotFalse($createStart);
        $this->assertNotFalse($createEnd);
        $createFn = substr($js, $createStart, $createEnd - $createStart);

        $this->assertStringContainsString("$('#lead-parent-email').val()", $createFn);
        $this->assertStringContainsString("$('#lead-parent-id').val()", $createFn);
        $this->assertStringNotContainsString('payload.parent_email', $createFn);
        $this->assertStringNotContainsString('useSnapshotParentFields', $createFn);
        $this->assertStringNotContainsString('snapshot.email', $createFn);

        $putStart = strpos($js, 'function collectLeadPayload()');
        $this->assertNotFalse($putStart);
        $putFn = substr($js, $putStart, $createStart - $putStart);
        $this->assertStringContainsString('useSnapshotParentFields', $putFn);
        $this->assertStringContainsString('snapshot.email', $putFn);
    }

    public function test_switching_to_directory_clears_parent_fields_until_select(): void
    {
        $parentJs = (string) file_get_contents(resource_path('views/admin/users/_parent_form.blade.php'));

        $this->assertStringContainsString("$(document).on('click', '.js-parent-mode-btn'", $parentJs);
        $this->assertStringContainsString("if (mode === 'new')", $parentJs);
        $this->assertStringContainsString('clearParentSelect(prefix)', $parentJs);
        $this->assertStringContainsString('fillParentFio(prefix, {})', $parentJs);
        $this->assertStringContainsString("setParentFormMode(prefix, 'directory'", $parentJs);
        $this->assertStringContainsString('$select.on(\'select2:select.studentParent\'', $parentJs);
        $this->assertStringContainsString('fillParentFio(prefix, row)', $parentJs);
        $this->assertStringContainsString('parent_email: item.parent_email', $parentJs);
    }

    public function test_save_and_create_client_errors_use_shared_toast_not_error_modal(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));
        $modal = (string) file_get_contents(resource_path('views/admin/school-leads/partials/edit-lead-modal.blade.php'));

        $this->assertStringContainsString("@include('partials.ui.main-toast')", $modal);
        $includePos = strpos($modal, "@include('partials.ui.main-toast')");
        $lastDiv = strrpos($modal, '</div>');
        $this->assertNotFalse($includePos);
        $this->assertGreaterThan($lastDiv, $includePos);

        $this->assertStringContainsString("showToast(message, 'error')", $js);
        $this->assertStringContainsString("showToast(text, isSuccess ? 'success' : 'error')", $js);
        $this->assertStringContainsString("if (typeof window.showToast === 'function')", $js);
        $this->assertStringContainsString('window.showToast(message, type)', $js);
        $this->assertStringNotContainsString("showErrorModal('Создание клиента'", $js);
        $this->assertStringNotContainsString("showErrorModal('Сохранение", $js);

        $savePos = strpos($js, "$('#saveLeadBtn').on('click'");
        $this->assertNotFalse($savePos);
        $saveChunk = substr($js, $savePos, 1800);
        $this->assertStringContainsString("showToast(message, 'error')", $saveChunk);
        $this->assertStringNotContainsString('editLeadError', $saveChunk);

        $createPos = strpos($js, 'function submitCreateClientFromLead');
        $this->assertNotFalse($createPos);
        $createChunk = substr($js, $createPos, 3500);
        $this->assertStringContainsString('buildCreateClientFromLeadPayload({', $createChunk);
        $this->assertStringContainsString('showCreateClientResultModal(false, message)', $createChunk);
        $this->assertStringNotContainsString('editLeadError', $createChunk);
        $this->assertStringNotContainsString('showErrorModal', $createChunk);
        $this->assertStringContainsString('clientPayload.send_contract', $js);

        $clickPos = strpos($js, "$('#createClientBtn').on('click'");
        $this->assertNotFalse($clickPos);
        $clickChunk = substr($js, $clickPos, 900);
        $this->assertStringContainsString('precheckCreateClientFromLeadThenOpenChoice()', $clickChunk);
        $this->assertStringContainsString('submitCreateClientFromLead({ sendContract: false })', $clickChunk);
    }

    public function test_page_renders_single_kids_main_toast(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'id="kidsMainToast"'));
    }

    public function test_widget_doc_lists_directory_switch_coverage(): void
    {
        $html = (string) file_get_contents(base_path('docs/documentation/school-leads-widget.html'));

        $this->assertStringContainsString('SchoolLeadCreateClientDirectorySwitchFeatureTest', $html);
        $this->assertStringContainsString('SchoolLeadCreateClientDirectorySwitchAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('SchoolLeadCreateClientDirectorySwitchNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('collectCreateClientPayload', $html);
        $this->assertStringContainsString('#lead-parent-email', $html);
        $this->assertStringContainsString('не из снимка', $html);
        $this->assertStringContainsString('errors.parent_email', $html);
    }

    public function test_without_directory_parents_directory_toggle_is_not_shown(): void
    {
        $this->actingAsLeadsAndUsersViewer();

        $html = $this->get(route('admin.school-leads'))->assertOk()->getContent();
        $this->assertStringContainsString('data-has-parent-profiles="0"', $html);
        $this->assertStringNotContainsString('Из справочника', $html);
        $this->assertStringNotContainsString('data-mode="directory"', $html);
    }

    public function test_opening_modal_and_match_buttons_rebuild_parent_form_on_separate_js_paths(): void
    {
        $js = (string) file_get_contents(resource_path('views/admin/school-leads/tabs/leads.blade.php'));

        $populateStart = strpos($js, 'function populateLeadForm(rowData)');
        $collectStart = strpos($js, 'function collectLeadPayload()');
        $this->assertNotFalse($populateStart);
        $this->assertNotFalse($collectStart);
        $populateFn = substr($js, $populateStart, $collectStart - $populateStart);

        $this->assertStringContainsString("$('#leads-table').on('click', '.edit-lead'", $js);
        $this->assertStringContainsString('populateLeadForm(rowData)', $js);

        $this->assertStringContainsString("if (hasMatch && confirmed !== 'rejected')", $populateFn);
        $this->assertStringContainsString("window.setStudentParentForm('lead'", $populateFn);
        $this->assertStringContainsString('matchedParent.email || snapshot.email', $populateFn);
        $this->assertStringContainsString('forceNewParent: true', $populateFn);

        $matchIf = strpos($populateFn, "if (hasMatch && confirmed !== 'rejected')");
        $this->assertNotFalse($matchIf);
        $matchChunk = substr($populateFn, $matchIf);
        $elsePos = strpos($matchChunk, '} else {');
        $this->assertNotFalse($elsePos);
        $elseChunk = substr($matchChunk, $elsePos);
        $this->assertStringContainsString('forceNewParent: true', $elseChunk);
        $this->assertStringContainsString('parent_email: snapshot.email', $elseChunk);
        $this->assertStringNotContainsString('matchedParent.email', $elseChunk);

        $acceptStart = strpos($js, 'function acceptLeadParentMatch()');
        $rejectStart = strpos($js, 'function rejectLeadParentMatch()');
        $this->assertNotFalse($acceptStart);
        $this->assertNotFalse($rejectStart);
        $acceptFn = substr($js, $acceptStart, $rejectStart - $acceptStart);
        $this->assertStringContainsString('matched.email || snapshot.email', $acceptFn);
        $this->assertStringNotContainsString('forceNewParent', $acceptFn);

        $clearStart = strpos($js, 'function clearLeadFormErrors()');
        $rejectFn = substr($js, $rejectStart, $clearStart - $rejectStart);
        $this->assertStringContainsString('forceNewParent: true', $rejectFn);
        $this->assertStringContainsString('parent_email: snapshot.email || \'\'', $rejectFn);
        $this->assertStringNotContainsString('matched.email', $rejectFn);
    }

    public function test_directory_mode_click_clears_fields_until_select2_fills_them(): void
    {
        $parentJs = (string) file_get_contents(resource_path('views/admin/users/_parent_form.blade.php'));

        $clickStart = strpos($parentJs, "$(document).on('click', '.js-parent-mode-btn'");
        $this->assertNotFalse($clickStart);
        $clickFn = substr($parentJs, $clickStart, 900);

        $this->assertStringContainsString("if (mode === 'new')", $clickFn);
        $newBranch = substr($clickFn, 0, (int) strpos($clickFn, 'return;') + 8);
        $this->assertStringContainsString('clearParentSelect(prefix)', $newBranch);
        $this->assertStringContainsString('fillParentFio(prefix, {})', $newBranch);

        $directoryBranch = substr($clickFn, (int) strpos($clickFn, 'return;'));
        $this->assertStringContainsString('fillParentFio(prefix, {})', $directoryBranch);
        $this->assertStringContainsString("setParentFormMode(prefix, 'directory'", $directoryBranch);
        $this->assertStringNotContainsString('snapshot.email', $directoryBranch);

        $this->assertStringContainsString('$select.on(\'select2:select.studentParent\'', $parentJs);
        $selectStart = strpos($parentJs, '$select.on(\'select2:select.studentParent\'');
        $selectChunk = substr($parentJs, $selectStart, 500);
        $this->assertStringContainsString('fillParentFio(prefix, row)', $selectChunk);
        $this->assertStringContainsString('parent_email: item.parent_email', $parentJs);
    }
}
