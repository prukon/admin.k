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
        $this->assertStringContainsString('chat-presence-index', $chunk);
        $this->assertStringContainsString('ChatPresenceUxFeatureTest', $chunk);
        $this->assertStringContainsString('Черновик', $chunk);
        $this->assertStringContainsString('draft_body', $chunk);
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
        $this->assertStringContainsString('contact-team', $html);
        $this->assertStringContainsString('по центру на одной линии с именем', $html);
        $this->assertStringNotContainsString('Ниже — группы ученика', $html);
        $this->assertStringContainsString('users.lastname', $html);
        $this->assertStringContainsString('PEER_USER_COLUMNS', $html);
        $this->assertStringContainsString('parents.firstname', $html);
        $this->assertStringContainsString('2 минут', $html);
        $this->assertStringContainsString('chat-li-unread', $html);
        $this->assertStringContainsString('#f3a12b', $html);
        $this->assertStringContainsString('#msgInput', $html);
        $this->assertStringContainsString('draft_body', $html);
        $this->assertStringContainsString('Черновик', $html);
        $this->assertStringContainsString('chat.api.threads.draft', $html);
        $this->assertStringContainsString('ChatDraftFeatureTest', $html);
        $this->assertStringContainsString('ChatPresenceFeatureTest', $html);
        $this->assertStringContainsString('ChatPresenceUxFeatureTest', $html);
        $this->assertStringContainsString('peerCardError', $html);
        $this->assertStringContainsString('chat.api.users.show', $html);
        $this->assertStringContainsString('last_seen_label', $html);
        $this->assertStringContainsString('id="presence"', $html);
        $this->assertStringContainsString('/doc#chat-presence-index', $html);
        $this->assertStringContainsString('без</b> <code>can:messages.view</code>', $html);
        $this->assertStringContainsString('без точки и без', $html);
        $this->assertStringNotContainsString('все API чата</td>', $html);
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
        $this->assertStringContainsString('онлайн (ping без messages.view)', $controller);
        $this->assertStringContainsString('карточка собеседника из шапки', $controller);
        $this->assertStringContainsString('черновик на сервере', $controller);
    }

    public function test_doc_index_announces_chat_presence_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-presence-index"', $html);
        $start = strpos($html, 'id="chat-presence-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="admin-password-change-toast-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('last_seen_at', $chunk);
        $this->assertStringContainsString('2 минут', $chunk);
        $this->assertStringContainsString('120 секунд', $chunk);
        $this->assertStringContainsString('/presence/ping', $chunk);
        $this->assertStringContainsString('без</b> права', $chunk);
        $this->assertStringContainsString('messages.view', $chunk);
        $this->assertStringContainsString('{ok: true}', $chunk);
        $this->assertStringContainsString('не 302', $chunk);
        $this->assertStringContainsString('last_message_time', $chunk);
        $this->assertStringContainsString('последнего сообщения', $chunk);
        $this->assertStringContainsString('chat-li-unread', $chunk);
        $this->assertStringContainsString('#f3a12b', $chunk);
        $this->assertStringContainsString('#msgInput', $chunk);
        $this->assertStringContainsString('Черновик', $chunk);
        $this->assertStringContainsString('draft_body', $chunk);
        $this->assertStringContainsString('не</b> рисуем', $chunk);
        $this->assertStringContainsString('красной нет', $chunk);
        $this->assertStringContainsString('parent_full_name', $chunk);
        $this->assertStringContainsString('contact-team', $chunk);
        $this->assertStringContainsString('по центру на одной линии с именем', $chunk);
        $this->assertStringContainsString('parents.firstname', $chunk);
        $this->assertStringContainsString('lastname', $chunk);
        $this->assertStringContainsString('threadPeerHit', $chunk);
        $this->assertStringContainsString('is-idle', $chunk);
        $this->assertStringContainsString('tel:', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#presence', $chunk);
        $this->assertStringContainsString('ChatPresenceFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPresenceUxFeatureTest', $chunk);
        $this->assertStringContainsString('/doc#chat-index', $chunk);

        $this->assertStringNotContainsString('ping требует', $chunk);
        $this->assertStringNotContainsString('красная точка в списке', $chunk);
        $this->assertStringNotContainsString('время last_seen справа', $chunk);
        $this->assertStringNotContainsString('карточка из списка диалогов', $chunk);
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
