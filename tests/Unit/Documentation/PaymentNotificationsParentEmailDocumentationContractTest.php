<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#payment-notifications-parent-email-index: почта родителя, иначе ученика,
 * обращение {{addressee_name}}, кнопка «По умолчанию».
 */
final class PaymentNotificationsParentEmailDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_parent_email_and_addressee(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="payment-notifications-parent-email-index"', $html);
        $start = strpos($html, 'id="payment-notifications-parent-email-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="list-persisted-filters-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('parents.email', $chunk);
        $this->assertStringContainsString('users.email', $chunk);
        $this->assertStringContainsString('PaymentNotificationRecipient', $chunk);
        $this->assertStringContainsString('{{addressee_name}}', $chunk);
        $this->assertStringContainsString('{{student_name}}', $chunk);
        $this->assertStringContainsString('только родителю', $chunk);
        $this->assertStringContainsString('два письма', $chunk);
        $this->assertStringContainsString('#pn-rule-reset-defaults', $chunk);
        $this->assertStringContainsString('По умолчанию', $chunk);
        $this->assertStringContainsString('PaymentNotificationsFeatureTest', $chunk);
        $this->assertStringContainsString('PaymentNotificationsParentEmailDocumentationContractTest', $chunk);
        $this->assertStringContainsString('/doc#payment-notifications-parent-email-index', $chunk);
        $this->assertStringContainsString('setting-prices-payment-notifications#audience', $chunk);
    }

    public function test_notifications_page_documents_recipient_and_default_button(): void
    {
        $html = $this->docFile('setting-prices-payment-notifications.html');

        $this->assertStringContainsString('id="audience"', $html);
        $this->assertStringContainsString('parents.email', $html);
        $this->assertStringContainsString('users.email', $html);
        $this->assertStringContainsString('{{addressee_name}}', $html);
        $this->assertStringContainsString('только родителю', $html);
        $this->assertStringContainsString('два письма', $html);
        $this->assertStringContainsString('#pn-rule-reset-defaults', $html);
        $this->assertStringContainsString('applyDefaultTemplates', $html);
        $this->assertStringContainsString('/doc#payment-notifications-parent-email-index', $html);
        $this->assertStringContainsString('PaymentNotificationRecipient', $html);
    }

    public function test_catalog_and_controller_title_mention_parent_recipient(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#payment-notifications-parent-email-index', $index);
        $this->assertStringContainsString('почта родителя иначе ученика', $index);
        $this->assertStringContainsString('parents.email иначе users.email, одно письмо, {{addressee_name}}', $controller);
    }

    public function test_live_code_prefers_parent_email_and_default_greeting(): void
    {
        $root = dirname(__DIR__, 3);
        $recipient = (string) file_get_contents($root.'/app/Services/PaymentNotifications/PaymentNotificationRecipient.php');
        $renderer = (string) file_get_contents($root.'/app/Services/PaymentNotifications/PaymentNotificationTemplateRenderer.php');
        $blade = (string) file_get_contents($root.'/resources/views/admin/SettingPrices/payment-notifications.blade.php');

        $parentCheck = strpos($recipient, '$parentEmail');
        $studentCheck = strpos($recipient, '$studentEmail');
        $this->assertNotFalse($parentCheck);
        $this->assertNotFalse($studentCheck);
        $this->assertLessThan($studentCheck, $parentCheck);
        $this->assertStringContainsString('parentProfile', $recipient);

        $this->assertStringContainsString('{{addressee_name}}', $renderer);
        $this->assertStringContainsString('function defaultBodyHtmlTemplate', $renderer);
        $this->assertStringContainsString('id="pn-rule-reset-defaults"', $blade);
        $this->assertStringContainsString('applyDefaultTemplates', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
