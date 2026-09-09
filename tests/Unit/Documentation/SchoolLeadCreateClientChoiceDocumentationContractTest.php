<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#school-lead-create-client-choice-index совпадает с модалкой выбора
 * «с договором / без» на заявках и StoreRequest.
 */
final class SchoolLeadCreateClientChoiceDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_lead_create_client_choice_modal(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="school-lead-create-client-choice-index"', $html);
        $start = strpos($html, 'id="school-lead-create-client-choice-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-list-signed-file-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('/admin/school-leads', $chunk);
        $this->assertStringContainsString('#createLeadClientChoiceModal', $chunk);
        $this->assertStringContainsString('users.view', $chunk);
        $this->assertStringContainsString('contracts.view', $chunk);
        $this->assertStringContainsString('Создать клиента и отправить договор', $chunk);
        $this->assertStringContainsString('Создать клиента без договора', $chunk);
        $this->assertStringContainsString('billing.contract_create_fee', $chunk);
        $this->assertStringContainsString('70 ₽', $chunk);
        $this->assertStringContainsString('suppressLeadModalResetOnHide', $chunk);
        $this->assertStringContainsString('resetStudentParentForm', $chunk);
        $this->assertStringContainsString('parent_email', $chunk);
        $this->assertStringContainsString('send_contract', $chunk);
        $this->assertStringContainsString('contract_template_id', $chunk);
        $this->assertStringContainsString('admin.user.store', $chunk);
        $this->assertStringContainsString('validate_only', $chunk);
        $this->assertStringContainsString('FamilyStudentLoginResolver', $chunk);
        $this->assertStringContainsString('Этот адрес электронной почты уже зарегистрирован.', $chunk);
        $this->assertStringContainsString('{ok: true}', $chunk);
        $this->assertStringContainsString('ValidatesLeadSendContract', $chunk);
        $this->assertStringContainsString('assertCanCreateTemplateContract', $chunk);
        $this->assertStringContainsString('errors.wallet', $chunk);
        $this->assertStringContainsString('Недостаточно средств для создания договора.', $chunk);
        $this->assertStringContainsString('window.hideToast', $chunk);
        $this->assertStringContainsString('contract_id', $chunk);
        $this->assertStringContainsString('POST /client-contracts', $chunk);
        $this->assertStringContainsString('Это <b>не</b> «Создать договор»', $chunk);
        $this->assertStringContainsString('createUser.blade.php', $chunk);
        $this->assertStringContainsString('school-leads-widget#school-lead-create-client-choice', $chunk);
        $this->assertStringContainsString('SchoolLeadCreateClientWithContractFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadCreateClientWithContractWorkflowFeatureTest', $chunk);
        $this->assertStringContainsString('SchoolLeadCreateClientChoiceDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#school-lead-create-client-choice-index', $chunk);
        $this->assertStringContainsString('school-lead-create-client-choice-index', $html);
    }

    public function test_widget_and_admin_users_docs_match_choice_modal_and_code(): void
    {
        $widget = $this->docFile('school-leads-widget.html');
        $users = $this->docFile('admin-users.html');
        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/school-leads/tabs/leads.blade.php');
        $modal = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/admin/school-leads/partials/create-client-choice-modal.blade.php');
        $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/User/Concerns/ValidatesLeadSendContract.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/Admin/UserController.php');

        $this->assertStringContainsString('id="school-lead-create-client-choice"', $widget);
        $this->assertStringContainsString('/doc#school-lead-create-client-choice-index', $widget);
        $this->assertStringContainsString('suppressLeadModalResetOnHide', $widget);
        $this->assertStringContainsString('Недостаточно средств', $widget);
        $this->assertStringContainsString('SchoolLeadCreateClientChoiceDocumentationContractTest', $widget);
        $this->assertStringContainsString('validate_only', $widget);
        $this->assertStringContainsString('precheckCreateClientFromLeadThenOpenChoice', $widget);
        $this->assertStringContainsString('Этот адрес электронной почты уже зарегистрирован.', $widget);
        $this->assertStringContainsString('validate_only', $users);

        $this->assertStringContainsString('/doc#school-lead-create-client-choice-index', $users);
        $this->assertStringContainsString('send_contract', $users);
        $this->assertStringContainsString('contract_id', $users);

        $this->assertStringContainsString('id="createLeadClientChoiceModal"', $modal);
        $this->assertStringContainsString('Создать клиента и отправить договор', $modal);
        $this->assertStringContainsString('Создать клиента без договора', $modal);
        $this->assertStringContainsString('Это действие платное', $modal);
        $this->assertStringContainsString('var suppressLeadModalResetOnHide = false', $blade);
        $this->assertStringContainsString('resetStudentParentForm(\'lead\')', $blade);
        $this->assertStringContainsString('clientPayload.send_contract = options.sendContract ? 1 : 0', $blade);
        $this->assertStringContainsString('clientPayload.validate_only = 1', $blade);
        $this->assertStringContainsString('function precheckCreateClientFromLeadThenOpenChoice()', $blade);
        $this->assertStringContainsString('hideToast()', $blade);

        $this->assertStringContainsString('Недостаточно прав для отправки договора.', $request);
        $this->assertStringContainsString('assertCanCreateTemplateContract', $request);
        $this->assertStringContainsString("'ok' => true", $controller);
        $this->assertStringContainsString("'contract_id' => \$createdContract?->id", $controller);
    }

    public function test_users_contract_create_announcement_does_not_conflate_lead_send_contract(): void
    {
        $html = $this->docFile('index.html');
        $start = strpos($html, 'id="users-contract-create-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="kids-tooltip-contrast-index"');
        $this->assertNotFalse($end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('POST /client-contracts', $chunk);
        $this->assertStringContainsString('send_contract', $chunk);
        $this->assertStringContainsString('/doc#school-lead-create-client-choice-index', $chunk);
        $this->assertStringContainsString('не', $chunk);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
