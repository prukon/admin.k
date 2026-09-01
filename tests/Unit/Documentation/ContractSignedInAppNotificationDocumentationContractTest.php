<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#contract-signed-in-app-index совпадает с кодом колокольчика при signed.
 */
final class ContractSignedInAppNotificationDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_contract_signed_bell_for_school_admins(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="contract-signed-in-app-index"', $html);
        $start = strpos($html, 'id="contract-signed-in-app-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="session-lifetime-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('signed', $chunk);
        $this->assertStringContainsString('admin', $chunk);
        $this->assertStringContainsString('7 суток', $chunk);
        $this->assertStringContainsString('Договор подписан', $chunk);
        $this->assertStringContainsString('ContractSignedNotifier', $chunk);
        $this->assertStringContainsString('events.contract_signed', $chunk);
        $this->assertStringContainsString('PodpislonWebhookController', $chunk);
        $this->assertStringContainsString('ContractSigningController::status', $chunk);
        $this->assertStringContainsString('Ссылки на карточку договора в тексте <b>нет</b>', $chunk);
        $this->assertStringContainsString('source=event', $chunk);
        $this->assertStringContainsString('provider_doc_id', $chunk);
        $this->assertStringContainsString('parent_full_name', $chunk);
        $this->assertStringContainsString('created_by', $chunk);
        $this->assertStringContainsString('$becameSigned', $chunk);
        $this->assertStringContainsString('Пока воркер не отработал', $chunk);
        $this->assertStringContainsString('ContractSignedInAppNotification*FeatureTest', $chunk);
        $this->assertStringNotContainsString('trainer', $chunk);
    }

    public function test_related_doc_pages_link_announcement(): void
    {
        $inApp = $this->docFile('in-app-notifications.html');
        $contracts = $this->docFile('contracts.html');
        $fill = $this->docFile('account-contract-fill.html');

        $this->assertStringContainsString('/doc#contract-signed-in-app-index', $inApp);
        $this->assertStringContainsString('id="events-contract-signed"', $inApp);
        $this->assertStringContainsString('role_names</code> admin', $inApp);
        $this->assertStringContainsString('срок жизни 7 суток', $inApp);
        $this->assertStringContainsString('Native GET', $inApp);
        $this->assertStringContainsString('JSON-вебхук', $inApp);

        $this->assertStringContainsString('/doc#contract-signed-in-app-index', $contracts);
        $this->assertStringContainsString('id="contract-signed-in-app"', $contracts);

        $this->assertStringContainsString('/doc#contract-signed-in-app-index', $fill);
        $this->assertStringContainsString('админы школы получают уведомление в колокольчик на 7 суток', $fill);
    }

    public function test_catalog_and_controller_title_mention_signed_bell(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $config = (string) file_get_contents(dirname(__DIR__, 3).'/config/in_app_notifications.php');

        $this->assertStringContainsString('id="contract-signed-in-app-index"', $index);
        $this->assertStringContainsString('/doc#contract-signed-in-app-index', $index);
        $this->assertStringContainsString('колокольчик админам при signed', $index);
        $this->assertStringContainsString('автособытие «договор подписан» (только admin школы, 7 суток)', $controller);
        $this->assertStringContainsString('колокольчик админам при signed', $controller);
        $this->assertStringContainsString("'contract_signed'", $config);
        $this->assertStringContainsString("'role_names' => ['admin']", $config);
        $this->assertStringContainsString("'ttl_preset' => '7d'", $config);
    }

    public function test_live_code_matches_documented_notifier_and_call_sites(): void
    {
        $root = dirname(__DIR__, 3);
        $notifier = (string) file_get_contents($root.'/app/Services/InAppNotifications/ContractSignedNotifier.php');
        $webhook = (string) file_get_contents($root.'/app/Http/Controllers/Webhooks/PodpislonWebhookController.php');
        $signing = (string) file_get_contents($root.'/app/Http/Controllers/Contracts/ContractSigningController.php');

        $this->assertStringContainsString('class ContractSignedNotifier', $notifier);
        $this->assertStringContainsString("config('in_app_notifications.events.contract_signed'", $notifier);
        $this->assertStringContainsString('Договор №', $notifier);
        $this->assertStringContainsString('Родитель:', $notifier);
        $this->assertStringNotContainsString('/client-contracts/', $notifier);

        $this->assertStringContainsString('contractSignedNotifier->notify', $webhook);
        $this->assertStringContainsString('if ($becameSigned)', $webhook);
        $this->assertStringContainsString('contractSignedNotifier->notify', $signing);
        $this->assertStringContainsString('$becameSigned = true', $signing);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
