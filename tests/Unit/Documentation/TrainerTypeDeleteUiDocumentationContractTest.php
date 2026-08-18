<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc: удаление типа — AJAX-список + toast (не success-модалка).
 * Детали JS и брендовый confirm — admin-trainers §6.3 и public/js/trainer-types.js.
 */
final class TrainerTypeDeleteUiDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_ajax_list_and_toast(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="trainer-types-kansas-index"', $html);
        $start = strpos($html, 'id="trainer-types-kansas-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="trainer-salary-kansas-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('showConfirmDeleteModal', $chunk);
        $this->assertStringContainsString('не браузерный <code>confirm</code>', $chunk);
        $this->assertStringContainsString('AJAX <code>loadList</code>', $chunk);
        $this->assertStringContainsString('toast «Тип тренера удалён»', $chunk);
        $this->assertStringContainsString('errors.name', $chunk);
        $this->assertStringContainsString('TrainerTypesUiContractsFeatureTest', $chunk);
        $this->assertStringContainsString('test_trainer_types_js_contract_is_valid_javascript', $chunk);
        $this->assertStringContainsString('admin-trainers#trainer-types', $chunk);

        $this->assertStringNotContainsString('window.confirm()', $chunk);
        $this->assertStringNotContainsString('if (confirm(', $chunk);
        $this->assertStringNotContainsString("showSuccessModal('", $chunk);
        $this->assertStringNotContainsString('location.reload', $chunk);
    }

    public function test_success_toast_announcement_lists_trainer_type_save_and_delete(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="success-toast-instead-of-modal-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="admin-sidebar-stability-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('Типы тренера', $chunk);
        $this->assertStringContainsString('Тип тренера сохранён', $chunk);
        $this->assertStringContainsString('window.showToast', $chunk);
        $this->assertStringNotContainsString("showSuccessModal('Типы тренеров'", $chunk);
    }

    public function test_admin_trainers_section_matches_live_delete_js(): void
    {
        $html = $this->docFile('admin-trainers.html');
        $start = strpos($html, 'id="trainer-types"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="trainer-edit-password"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('trainer-type-delete-btn', $chunk);
        $this->assertStringContainsString('confirmDeleteType()', $chunk);
        $this->assertStringContainsString("showConfirmDeleteModal('Удаление типа тренера'", $chunk);
        $this->assertStringContainsString('window.confirm', $chunk);
        $this->assertStringContainsString('Вы уверены, что хотите удалить тип тренера «Название»?', $chunk);
        $this->assertStringContainsString('trainerTypesModal', $chunk);
        $this->assertStringContainsString("loadList('saved')", $chunk);
        $this->assertStringContainsString('showList()', $chunk);
        $this->assertStringContainsString('Тип тренера удалён', $chunk);
        $this->assertStringContainsString('errors.name', $chunk);
        $this->assertStringContainsString('showToast', $chunk);
        $this->assertStringContainsString('не <code>showSuccessModal</code>', $chunk);
        $this->assertStringNotContainsString("showSuccessModal('", $chunk);
        $this->assertStringNotContainsString('location.reload', $chunk);
    }

    public function test_admin_trainers_test_list_points_to_delete_ui_contracts(): void
    {
        $html = $this->docFile('admin-trainers.html');

        $this->assertStringContainsString('TrainerTypeDeleteUiDocumentationContractTest', $html);
        $this->assertStringContainsString("showConfirmDeleteModal", $html);
        $this->assertStringContainsString("loadList('saved')", $html);
        $this->assertStringContainsString('не <code>window.confirm</code>', $html);
    }

    public function test_live_js_matches_documented_delete_flow(): void
    {
        $path = dirname(__DIR__, 3).'/public/js/trainer-types.js';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertStringContainsString('function confirmDeleteType()', $content);
        $this->assertStringContainsString('window.showConfirmDeleteModal', $content);
        $this->assertStringContainsString('Удаление типа тренера', $content);
        $this->assertStringContainsString("Вы уверены, что хотите удалить тип тренера «' + name + '»?", $content);
        $this->assertStringContainsString("method: 'DELETE'", $content);
        $this->assertStringContainsString("await loadList('saved')", $content);
        $this->assertStringContainsString('showList()', $content);
        $this->assertStringContainsString("window.showToast(data.message || 'Тип тренера удалён', 'success')", $content);
        $this->assertStringContainsString('showFieldErrors(data.errors || {})', $content);
        $this->assertStringNotContainsString('window.confirm', $content);

        $deleteStart = strpos($content, 'function confirmDeleteType()');
        $this->assertNotFalse($deleteStart);
        $deleteChunk = substr($content, $deleteStart, 3000);
        $this->assertStringNotContainsString('trainerTypesModal', $deleteChunk);
        $this->assertStringNotContainsString('.hide()', $deleteChunk);
        $this->assertStringNotContainsString('hideModal', $deleteChunk);
        $this->assertStringNotContainsString('showSuccessModal', $deleteChunk);
        $this->assertStringNotContainsString('location.reload', $deleteChunk);
    }

    public function test_related_docs_do_not_claim_browser_confirm_for_trainer_types(): void
    {
        $salary = $this->docFile('schedule-trainer-salary.html');
        $toast = $this->docFile('reusable-ui-partials.html');

        $this->assertStringContainsString('showConfirmDeleteModal', $salary);
        $this->assertStringContainsString('TrainerTypeDeleteUiDocumentationContractTest', $salary);

        $this->assertStringContainsString('showConfirmDeleteModal', $toast);
        $this->assertStringContainsString('trainerTypesModal', $toast);
        $this->assertStringContainsString('TrainerTypeDeleteUiDocumentationContractTest', $toast);
        $this->assertStringNotContainsString("window.confirm('Удалить этот тип тренера?')", $salary);
        $this->assertStringNotContainsString("window.confirm('Удалить этот тип тренера?')", $toast);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
