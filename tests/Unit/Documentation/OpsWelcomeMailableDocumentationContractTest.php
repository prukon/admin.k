<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#ops-welcome-mailable-index совпадает с живым пультом и журналом:
 * тип письма в логе, fallback по теме, без бэкфилла, JSON без email.
 */
final class OpsWelcomeMailableDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_welcome_mailable_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="ops-welcome-mailable-index"', $html);
        $start = strpos($html, 'id="ops-welcome-mailable-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="ops-errors-detail-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#js-ops-monitors', $chunk);
        $this->assertStringContainsString('ClientWelcomeCredentialsMail', $chunk);
        $this->assertStringContainsString('STATUS_SENT', $chunk);
        $this->assertStringContainsString('STATUS_FAILED', $chunk);
        $this->assertStringContainsString('mailable_class', $chunk);
        $this->assertStringContainsString('__laravel_mailable', $chunk);
        $this->assertStringContainsString('buildViewDataUsing', $chunk);
        $this->assertStringContainsString('EventServiceProvider', $chunk);
        $this->assertStringContainsString('LogOutgoingEmail', $chunk);
        $this->assertStringContainsString('X-Mailable-Class', $chunk);
        $this->assertStringContainsString('Mail::raw', $chunk);
        $this->assertStringContainsString('SUBJECT_PREFIX', $chunk);
        $this->assertStringContainsString('Доступ в личный кабинет', $chunk);
        $this->assertStringContainsString('to_summary', $chunk);
        $this->assertStringContainsString('notifiable_id', $chunk);
        $this->assertStringContainsString('school_leads.user_id', $chunk);
        $this->assertStringContainsString('sending', $chunk);
        $this->assertStringContainsString('ClientSiblingAddedMail', $chunk);
        $this->assertStringContainsString('missing_count', $chunk);
        $this->assertStringContainsString('last_user_id', $chunk);
        $this->assertStringContainsString('current_partner', $chunk);
        $this->assertStringContainsString('бэкфиллим', $chunk);
        $this->assertStringContainsString('Laravel 10', $chunk);
        $this->assertStringContainsString('/doc#ops-monitors-overlay-index', $chunk);
        $this->assertStringContainsString('dashboard-cabinet#system-monitors', $chunk);
        $this->assertStringContainsString('reports-admin#reports-outgoing-emails', $chunk);
        $this->assertStringContainsString('admin-users#user-welcome-credentials', $chunk);
        $this->assertStringContainsString('school-leads-widget#school-lead-welcome-credentials', $chunk);
        $this->assertStringContainsString('chat#ops-monitors-overlay', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingUxFeatureTest', $chunk);
        $this->assertStringContainsString('SystemMonitorsOpsWelcomeAccountingFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LogOutgoingEmailMailableClassFeatureTest', $chunk);
        $this->assertStringContainsString('OpsWelcomeMailableDocumentationContractTest', $chunk);
        $this->assertStringNotContainsString('бэкфилл старых строк есть', $chunk);
        $this->assertStringNotContainsString('Laravel сам ставит __laravel_mailable', $chunk);
        $this->assertStringNotContainsString('welcome.email', $chunk);
        $this->assertStringNotContainsString('только текущий партнёр', $chunk);
        $this->assertStringNotContainsString('sibling закрывает Welcome', $chunk);
    }

    public function test_detail_pages_link_welcome_mailable_announcement(): void
    {
        $cabinet = $this->docFile('dashboard-cabinet.html');
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $cabinet);
        $this->assertStringContainsString('mailable_class', $cabinet);
        $this->assertStringContainsString('Доступ в личный кабинет', $cabinet);
        $this->assertStringContainsString('buildViewDataUsing', $cabinet);
        $this->assertStringContainsString('missing_count', $cabinet);
        $this->assertStringContainsString('last_user_id', $cabinet);

        $chat = $this->docFile('chat.html');
        $start = strpos($chat, 'id="ops-monitors-overlay"');
        $this->assertNotFalse($start);
        $end = strpos($chat, 'id="tests"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($chat, $start, $end - $start);
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $chunk);
        $this->assertStringContainsString('ClientWelcomeCredentialsMail', $chunk);
        $this->assertStringContainsString('missing_count', $chunk);
        $this->assertStringContainsString('last_user_id', $chunk);
        $this->assertStringContainsString('Доступ в личный кабинет', $chunk);

        $reports = $this->docFile('reports-admin.html');
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $reports);
        $this->assertStringContainsString('X-Mailable-Class', $reports);
        $this->assertStringContainsString('buildViewDataUsing', $reports);
        $this->assertStringContainsString('LogOutgoingEmailMailableClassFeatureTest', $reports);

        $users = $this->docFile('admin-users.html');
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $users);
        $this->assertStringContainsString('SUBJECT_PREFIX', $users);
        $this->assertStringContainsString('ClientSiblingAddedMail', $users);
        $this->assertStringContainsString('не</b> закрывает', $users);

        $leads = $this->docFile('school-leads-widget.html');
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $leads);

        $groups = $this->docFile('settings-permission-groups.html');
        $this->assertStringContainsString('/doc#ops-welcome-mailable-index', $groups);
    }

    public function test_documentation_controller_mentions_welcome_mailable(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString('mailable_class', $controller);
        $this->assertStringContainsString('Доступ в личный кабинет', $controller);
        $this->assertStringContainsString('оверлей Пульт', $controller);
        $this->assertStringContainsString('X-Mailable-Class', $controller);
        $this->assertStringNotContainsString('Laravel 10 сам ставит mailable_class', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
