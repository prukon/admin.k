<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-invitation-parent-email-index совпадает с отправкой приглашения:
 * почта родителя, иначе клиента, и обращение {{addressee_name}}.
 */
final class ContractInvitationParentEmailDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_invitation_to_parent_email_or_client(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-invitation-parent-email-index"', $html);
        $start = strpos($html, 'id="contract-invitation-parent-email-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="reports-payments-commission-total-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('users.email', $chunk);
        $this->assertStringContainsString('parents.email', $chunk);
        $this->assertStringContainsString('только родителю', $chunk);
        $this->assertStringContainsString('{{addressee_name}}', $chunk);
        $this->assertStringContainsString('не два <code>To</code>', $chunk);
        $this->assertStringNotContainsString('два отдельных</b> письма', $chunk);
        $this->assertStringContainsString('ContractClientFillInvitationMail', $chunk);
        $this->assertStringContainsString('invitationRecipientEmails', $chunk);
        $this->assertStringContainsString('client_invited_to_fill', $chunk);
        $this->assertStringContainsString('account-contract-fill#invitation-email-recipients', $chunk);
        $this->assertStringContainsString('ContractInvitationEmailFeatureTest', $chunk);
        $this->assertStringContainsString('ContractInvitationParentEmailDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#contract-invitation-parent-email-index', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $fill = $this->docFile('account-contract-fill.html');
        $templates = $this->docFile('contract-templates.html');
        $contracts = $this->docFile('contracts.html');

        $this->assertStringContainsString('id="invitation-email-recipients"', $fill);
        $this->assertStringContainsString('/doc#contract-invitation-parent-email-index', $fill);
        $this->assertStringContainsString('только родителю', $fill);
        $this->assertStringContainsString('{{addressee_name}}', $fill);
        $this->assertStringContainsString('users.email', $fill);
        $this->assertStringContainsString('parents.email', $fill);
        $this->assertStringNotContainsString('на email ученика уходит', $fill);

        $this->assertStringContainsString('/doc#contract-invitation-parent-email-index', $templates);
        $this->assertStringContainsString('только родителю', $templates);
        $this->assertStringContainsString('{{addressee_name}}', $templates);

        $this->assertStringContainsString('/doc#contract-invitation-parent-email-index', $contracts);
        $this->assertStringContainsString('users.email', $contracts);
        $this->assertStringContainsString('parents.email', $contracts);
        $this->assertStringContainsString('{{addressee_name}}', $contracts);
    }

    public function test_catalog_and_controller_title_mention_invitation_recipients(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#contract-invitation-parent-email-index', $index);
        $this->assertStringContainsString('приглашение на почту родителя, иначе клиента', $index);

        $this->assertStringContainsString('приглашение: parents.email, иначе users.email, одно письмо; {{addressee_name}}', $controller);
    }

    public function test_live_code_sends_one_email_to_parent_or_else_client(): void
    {
        $root = dirname(__DIR__, 3);
        $service = (string) file_get_contents($root.'/app/Services/Contracts/ContractCreationService.php');
        $defaults = (string) file_get_contents($root.'/app/Services/Contracts/ContractTemplateEmailDefaults.php');
        $renderer = (string) file_get_contents($root.'/app/Services/Contracts/ContractInvitationEmailRenderer.php');

        $this->assertStringContainsString('function invitationRecipientEmails', $service);
        $this->assertStringContainsString('parentProfile', $service);
        $parentCheck = strpos($service, "if (\$parentEmail !== '')");
        $clientCheck = strpos($service, "if (\$clientEmail !== '')");
        $this->assertNotFalse($parentCheck);
        $this->assertNotFalse($clientCheck);
        $this->assertLessThan($clientCheck, $parentCheck);
        $this->assertStringContainsString('function sendFillInvitationEmail', $service);
        $this->assertStringContainsString('Mail::to($email)->send(new ContractClientFillInvitationMail', $service);
        $this->assertStringContainsString("'emails' => \$emails", $service);
        $this->assertStringContainsString("foreach (\$emails as \$email)", $service);
        $this->assertStringNotContainsString('Mail::to([$studentEmail, $parentEmail])', $service);

        $this->assertStringContainsString("PLACEHOLDER_ADDRESSEE_NAME = '{{addressee_name}}'", $defaults);
        $this->assertStringContainsString('PLACEHOLDER_ADDRESSEE_NAME', $defaults);
        $this->assertStringContainsString('function addresseeFullName', $renderer);
        $this->assertStringContainsString('parent_full_name', $renderer);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
