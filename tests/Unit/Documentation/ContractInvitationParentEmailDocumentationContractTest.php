<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-invitation-parent-email-index совпадает с отправкой приглашения
 * на users.email и parents.email.
 */
final class ContractInvitationParentEmailDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_invitation_to_student_and_parent_emails(): void
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
        $this->assertStringContainsString('два отдельных', $chunk);
        $this->assertStringContainsString('не одно с двумя <code>To</code>', $chunk);
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
        $this->assertStringContainsString('два отдельных', $fill);
        $this->assertStringContainsString('users.email', $fill);
        $this->assertStringContainsString('parents.email', $fill);
        $this->assertStringNotContainsString('на email ученика уходит', $fill);

        $this->assertStringContainsString('/doc#contract-invitation-parent-email-index', $templates);
        $this->assertStringContainsString('два отдельных', $templates);

        $this->assertStringContainsString('/doc#contract-invitation-parent-email-index', $contracts);
        $this->assertStringContainsString('users.email', $contracts);
        $this->assertStringContainsString('parents.email', $contracts);
    }

    public function test_catalog_and_controller_title_mention_invitation_recipients(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#contract-invitation-parent-email-index', $index);
        $this->assertStringContainsString('приглашение на email ученика и родителя', $index);

        $this->assertStringContainsString('приглашение на users.email и parents.email (дедуп)', $controller);
    }

    public function test_live_code_sends_unique_student_and_parent_emails_separately(): void
    {
        $root = dirname(__DIR__, 3);
        $service = (string) file_get_contents($root.'/app/Services/Contracts/ContractCreationService.php');

        $this->assertStringContainsString('function invitationRecipientEmails', $service);
        $this->assertStringContainsString('parentProfile', $service);
        $this->assertStringContainsString("mb_strtolower(\$email, 'UTF-8')", $service);
        $this->assertStringContainsString('function sendFillInvitationEmail', $service);
        $this->assertStringContainsString('Mail::to($email)->send(new ContractClientFillInvitationMail', $service);
        $this->assertStringContainsString("'emails' => \$emails", $service);
        $this->assertStringContainsString("foreach (\$emails as \$email)", $service);
        $this->assertStringNotContainsString('Mail::to([$studentEmail, $parentEmail])', $service);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
