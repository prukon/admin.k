<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#chat-peer-card-cross-partner-index и chat.html §2.1.3 совпадают с кодом:
 * superadmin + живой общий тред, русский errors.user, не This action is unauthorized.
 */
final class ChatPeerCardCrossPartnerDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_cross_partner_card_rules(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-peer-card-cross-partner-index"', $html);
        $start = strpos($html, 'id="chat-peer-card-cross-partner-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="lesson-packages-type-permissions-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#peerCardModal', $chunk);
        $this->assertStringContainsString('#peerCardError', $chunk);
        $this->assertStringContainsString('GET /chat/api/users/{id}', $chunk);
        $this->assertStringContainsString('current_partner', $chunk);
        $this->assertStringContainsString('This action is unauthorized.', $chunk);
        $this->assertStringContainsString('Нет доступа к карточке этого пользователя.', $chunk);
        $this->assertStringContainsString('errors.user', $chunk);
        $this->assertStringContainsString('живой общий тред', $chunk);
        $this->assertStringContainsString('participants', $chunk);
        $this->assertStringContainsString('Ученик / админ / тренер', $chunk);
        $this->assertStringContainsString('Лишний superadmin', $chunk);
        $this->assertStringContainsString('openPeerCard', $chunk);
        $this->assertStringContainsString('headerPeerActivate', $chunk);
        $this->assertStringContainsString('is-idle', $chunk);
        $this->assertStringContainsString('partner_name', $chunk);
        $this->assertStringContainsString('404', $chunk);
        $this->assertStringContainsString('405', $chunk);
        $this->assertStringContainsString('ChatPeerCardCrossPartnerFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPeerCardCrossPartnerAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPeerCardCrossPartnerNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPeerCardCrossPartnerUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#peer-card-cross-partner', $chunk);
        $this->assertStringContainsString('/doc#chat-presence-index', $chunk);
        $this->assertStringContainsString('/doc#chat-partner-name-index', $chunk);
        $this->assertStringContainsString('/doc#chat-group-members-index', $chunk);
        $this->assertStringContainsString('/doc#chat-support-identity-index', $chunk);

        $this->assertStringNotContainsString('ученик открывает карточку чужой школы', $chunk);
        $this->assertStringNotContainsString('inbox режется current_partner', $chunk);
    }

    public function test_chat_page_docs_match_cross_partner_card_contract(): void
    {
        $html = $this->docFile('chat.html');

        $this->assertStringContainsString('id="peer-card-cross-partner"', $html);
        $this->assertStringContainsString('/doc#chat-peer-card-cross-partner-index', $html);
        $start = strpos($html, 'id="peer-card-cross-partner"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="draft"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('ChatUserShowRequest', $chunk);
        $this->assertStringContainsString('живой общий тред', $chunk);
        $this->assertStringContainsString('whereHas(\'thread\')', $chunk);
        $this->assertStringContainsString('errors.user', $chunk);
        $this->assertStringContainsString('Нет доступа к карточке этого пользователя.', $chunk);
        $this->assertStringContainsString('This action is unauthorized.', $chunk);
        $this->assertStringContainsString('fieldError(res.data, \'user\')', $chunk);
        $this->assertStringContainsString('headerPeerActivate', $chunk);
        $this->assertStringContainsString('openPeerCard(id, true)', $chunk);
        $this->assertStringContainsString('msg-avatar-btn', $chunk);
        $this->assertStringContainsString('openGroupCard', $chunk);
        $this->assertStringContainsString('partner_name', $chunk);
        $this->assertStringContainsString('ChatPeerCardCrossPartnerFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPeerCardCrossPartnerAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPeerCardCrossPartnerNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPeerCardCrossPartnerUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPeerCardCrossPartnerDocumentationContractTest', $chunk);
    }

    public function test_catalog_and_controller_title_mention_cross_partner_card(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="chat-peer-card-cross-partner-index"', $index);
        $this->assertStringContainsString('/doc#chat-peer-card-cross-partner-index', $index);
        $this->assertStringContainsString('карточка чужой школы', $index);
        $this->assertStringContainsString('карточка чужой школы у superadmin только при живом общем треде', $controller);
        $this->assertStringContainsString('403 errors.user по-русски', $controller);
    }

    public function test_live_request_matches_documented_russian_403(): void
    {
        $root = dirname(__DIR__, 3);
        $request = (string) file_get_contents($root.'/app/Http/Requests/Chat/ChatUserShowRequest.php');
        $js = (string) file_get_contents($root.'/resources/js/chat.js');
        $blade = (string) file_get_contents($root.'/resources/views/chat/index.blade.php');

        $this->assertStringContainsString('Нет доступа к карточке этого пользователя.', $request);
        $this->assertStringContainsString("'errors' => ['user' => [\$message]]", $request);
        $this->assertStringContainsString('sharesLiveThread', $request);
        $this->assertStringContainsString('isSuperAdmin', $request);
        $this->assertStringContainsString("fieldError(res.data, 'user')", $js);
        $this->assertStringContainsString('function headerPeerActivate(', $js);
        $this->assertStringContainsString("openPeerCard(Number(row.getAttribute('data-id')), true)", $js);
        $this->assertStringContainsString("openPeerCard(Number(avatarBtn.getAttribute('data-user-id')))", $js);
        $this->assertStringNotContainsString('This action is unauthorized.', $js);
        $this->assertStringContainsString('id="peerCardError"', $blade);
        $this->assertStringContainsString('id="threadPeerHit"', $blade);
        $this->assertStringContainsString('class="modal-dialog"', $blade);
        $this->assertStringNotContainsString('modal-xl', $blade);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
