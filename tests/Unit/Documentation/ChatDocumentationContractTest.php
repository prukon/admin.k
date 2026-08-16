<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc и chat.html должны совпадать с фактическим UX чата
 * (бейдж info, не вспышка в открытом диалоге, опрос 1с/12с, Echo без wsPath).
 */
final class ChatDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_chat_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-index"', $html);
        $start = strpos($html, 'id="chat-index"');
        $this->assertNotFalse($start);
        $chunk = substr($html, $start, 4500);

        $this->assertStringContainsString('badge badge-info', $chunk);
        $this->assertStringContainsString('не вспыхивает', $chunk);
        $this->assertStringContainsString("wsPath: '/app'", $chunk);
        $this->assertStringContainsString('1 секунду', $chunk);
        $this->assertStringContainsString('12 секунд', $chunk);
        $this->assertStringContainsString('reverb-status', $chunk);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat', $chunk);
        $this->assertStringNotContainsString('красный бейдж', $chunk);
        $this->assertStringNotContainsString("wsPath: '/app'", str_replace(
            "<b>без</b> <code>wsPath: '/app'</code>",
            '',
            $chunk
        ));
    }

    public function test_chat_page_docs_match_sidebar_badge_and_reverb_status_access(): void
    {
        $html = $this->docFile('chat.html');

        $this->assertStringContainsString('badge badge-info', $html);
        $this->assertStringContainsString('не вспыхивает', $html);
        $this->assertStringContainsString("wsPath: '/app'", $html);
        $this->assertStringContainsString('без <code>messages.view</code>', $html);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump', $html);
        $this->assertStringContainsString('/doc#chat-index', $html);
        $this->assertStringNotContainsString('красный бейдж', $html);
        $this->assertStringNotContainsString(
            'опрос <code>is_read</code> раз в 12 секунд',
            $html
        );
        $this->assertStringNotContainsString(
            'опрос <code>/chat/api/unread</code> раз в 12 секунд без перезагрузки',
            $html
        );
    }

    public function test_doc_catalog_and_controller_title_mention_no_flash_and_no_wspath(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('без вспышки в открытом диалоге', $index);
        $this->assertStringContainsString("Reverb без <code>wsPath</code>", $index);
        $this->assertStringContainsString('без вспышки в открытом диалоге', $controller);
        $this->assertStringContainsString('Reverb без wsPath', $controller);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
