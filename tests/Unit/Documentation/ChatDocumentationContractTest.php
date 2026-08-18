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
        $chunk = substr($html, $start, 6500);

        $this->assertStringContainsString('badge badge-info', $chunk);
        $this->assertStringContainsString('не вспыхивает', $chunk);
        $this->assertStringContainsString("wsPath: '/app'", $chunk);
        $this->assertStringContainsString('1 секунду', $chunk);
        $this->assertStringContainsString('12 секунд', $chunk);
        $this->assertStringContainsString('reverb-status', $chunk);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat', $chunk);
        $this->assertStringContainsString('nginx.ssl.conf_reverb', $chunk);
        $this->assertStringContainsString(':6009', $chunk);
        $this->assertStringContainsString('ChatReverbOverlayFeatureTest', $chunk);
        $this->assertStringContainsString('reverb-status-overlay-index', $chunk);
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
        $this->assertStringContainsString('nginx.ssl.conf_reverb', $html);
        $this->assertStringContainsString('127.0.0.1:6009', $html);
        $this->assertStringContainsString('ChatReverbOverlayFeatureTest', $html);
        $this->assertStringContainsString('/doc#reverb-status-overlay-index', $html);
        $this->assertStringContainsString('id="reverb-overlay"', $html);
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
        $this->assertStringContainsString('presence/ping', $html);
        $this->assertStringContainsString('last_seen_at', $html);
        $this->assertStringContainsString('parent_full_name', $html);
        $this->assertStringContainsString('2 минут', $html);
        $this->assertStringContainsString('ChatPresenceFeatureTest', $html);
        $this->assertStringContainsString('peerCardError', $html);
        $this->assertStringContainsString('chat.api.users.show', $html);
        $this->assertStringContainsString('last_seen_label', $html);
    }

    public function test_doc_catalog_and_controller_title_mention_no_flash_and_no_wspath(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('без вспышки в открытом диалоге', $index);
        $this->assertStringContainsString("Reverb без <code>wsPath</code>", $index);
        $this->assertStringContainsString('без вспышки в открытом диалоге', $controller);
        $this->assertStringContainsString('Reverb без wsPath', $controller);
        $this->assertStringContainsString('оверлей статуса процесса/сокета для superadmin', $controller);
    }

    public function test_doc_index_announces_reverb_overlay_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reverb-status-overlay-index"', $html);
        $start = strpos($html, 'id="reverb-status-overlay-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="journal-table-preloader-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('процесс', $chunk);
        $this->assertStringContainsString('сокет', $chunk);
        $this->assertStringContainsString('127.0.0.1:6008', $chunk);
        $this->assertStringContainsString('127.0.0.1:6009', $chunk);
        $this->assertStringContainsString('nginx.ssl.conf_reverb', $chunk);
        $this->assertStringContainsString('laravel-reverb', $chunk);
        $this->assertStringContainsString('reverb-prod.service', $chunk);
        $this->assertStringContainsString('connecting', $chunk);
        $this->assertStringContainsString('is-ok', $chunk);
        $this->assertStringContainsString('без</b> <code>messages.view</code>', $chunk);
        $this->assertStringContainsString('ChatReverbOverlayFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#reverb-overlay', $chunk);
        $this->assertStringNotContainsString('messages.view</code> даёт оверлей', $chunk);
        $this->assertStringNotContainsString("wsPath: '/app'", str_replace(
            "<b>без</b> <code>wsPath: '/app'</code>",
            '',
            $chunk
        ));
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
