<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#session-lifetime-index совпадает с session-lifetime.html,
 * config/session.php, login/403/admin2 и PAGE_TITLES.
 */
final class SessionLifetimeDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_session_lifetime_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="session-lifetime-index"', $html);
        $start = strpos($html, 'id="session-lifetime-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="ops-welcome-mailable-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('SESSION_LIFETIME=43200', $chunk);
        $this->assertStringContainsString('30 суток', $chunk);
        $this->assertStringContainsString('expire_on_close', $chunk);
        $this->assertStringContainsString('file', $chunk);
        $this->assertStringContainsString('#remember', $chunk);
        $this->assertStringContainsString('hasOldInput()', $chunk);
        $this->assertStringContainsString('boolean(\'remember\')', $chunk);
        $this->assertStringContainsString('errors/403.blade.php', $chunk);
        $this->assertStringContainsString('layouts.app', $chunk);
        $this->assertStringContainsString('layouts.admin2', $chunk);
        $this->assertStringContainsString('user()?-&gt;role?-&gt;label', $chunk);
        $this->assertStringContainsString('optional(auth()->user()->role)', $chunk);
        $this->assertStringContainsString('/broadcasting/auth', $chunk);
        $this->assertStringContainsString('/docs/documentation/session-lifetime', $chunk);
        $this->assertStringContainsString('SessionLifetimeFeatureTest', $chunk);
        $this->assertStringContainsString('LoginRememberAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LoginRememberAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('LoginRememberNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('LoginRememberUxFeatureTest', $chunk);
        $this->assertStringContainsString('LoginRememberFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('Http403AccessFeatureTest', $chunk);
        $this->assertStringContainsString('Http403AjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('Http403NonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('Http403UxFeatureTest', $chunk);
        $this->assertStringContainsString('Http403FullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('sessionLifetimeBladePathsProvider', $chunk);
        $this->assertStringContainsString('SessionLifetimeDocumentationContractTest', $chunk);
        $this->assertStringNotContainsString('LoginRememberDefaultFeatureTest', $chunk);
        $this->assertStringNotContainsString('Http403PageFeatureTest', $chunk);
        $this->assertStringNotContainsString('SESSION_LIFETIME=120', $chunk);
        $this->assertStringNotContainsString('redis/database этот инкремент <b>включает</b>', $chunk);
    }

    public function test_session_lifetime_page_matches_code_contract(): void
    {
        $html = $this->docFile('session-lifetime.html');
        $this->assertStringContainsString('id="lifetime"', $html);
        $this->assertStringContainsString('/doc#session-lifetime-index', $html);
        $this->assertStringContainsString('SESSION_LIFETIME=43200', $html);
        $this->assertStringContainsString("env('SESSION_LIFETIME', 43200)", $html);
        $this->assertStringContainsString('expire_on_close', $html);
        $this->assertStringContainsString('SESSION_DRIVER=file', $html);
        $this->assertStringContainsString('#remember', $html);
        $this->assertStringContainsString('hasOldInput()', $html);
        $this->assertStringContainsString('boolean(\'remember\')', $html);
        $this->assertStringContainsString('errors/403.blade.php', $html);
        $this->assertStringContainsString('layouts.app', $html);
        $this->assertStringContainsString('layouts.admin2', $html);
        $this->assertStringContainsString('user()?->role?->label', $html);
        $this->assertStringContainsString('optional(auth()->user()->role)', $html);
        $this->assertStringContainsString('/broadcasting/auth', $html);
        $this->assertStringContainsString('route(\'login\')', $html);
        $this->assertStringContainsString('LoginRememberAccessFeatureTest', $html);
        $this->assertStringContainsString('LoginRememberAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('LoginRememberNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('LoginRememberUxFeatureTest', $html);
        $this->assertStringContainsString('LoginRememberFullAccessFeatureTest', $html);
        $this->assertStringContainsString('Http403AccessFeatureTest', $html);
        $this->assertStringContainsString('Http403AjaxContractFeatureTest', $html);
        $this->assertStringContainsString('Http403NonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('Http403UxFeatureTest', $html);
        $this->assertStringContainsString('Http403FullAccessFeatureTest', $html);
        $this->assertStringContainsString('sessionLifetimeBladePathsProvider', $html);
        $this->assertStringNotContainsString('LoginRememberDefaultFeatureTest', $html);
        $this->assertStringNotContainsString('Http403PageFeatureTest', $html);
        $this->assertStringNotContainsString('SESSION_DRIVER=redis', $html);
        $this->assertStringNotContainsString('env(\'SESSION_LIFETIME\', 120)', $html);
    }

    public function test_documentation_controller_lists_session_lifetime_page(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');
        $this->assertStringContainsString("'session-lifetime'", $controller);
        $this->assertStringContainsString('SESSION_LIFETIME=43200', $controller);
        $this->assertStringContainsString('Запомнить меня', $controller);
        $this->assertStringContainsString('403', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
