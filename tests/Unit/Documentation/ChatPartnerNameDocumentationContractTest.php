<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#chat-partner-name-index и chat.html §partner-name совпадают с кодом:
 * partners.title в модалках «Контакт» и «Группа», не вкладка «Аккаунт», не шапка.
 */
final class ChatPartnerNameDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_partner_name_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-partner-name-index"', $html);
        $start = strpos($html, 'id="chat-partner-name-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="session-lifetime-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#peerCardModal', $chunk);
        $this->assertStringContainsString('#groupCardModal', $chunk);
        $this->assertStringContainsString('peer-card-partner', $chunk);
        $this->assertStringContainsString('groupCardPartner', $chunk);
        $this->assertStringContainsString('partner_name', $chunk);
        $this->assertStringContainsString('partners.title', $chunk);
        $this->assertStringContainsString('accountCardBody', $chunk);
        $this->assertStringContainsString('#threadSubtitle', $chunk);
        $this->assertStringContainsString('Служба поддержки', $chunk);
        $this->assertStringContainsString('ChatPartnerNameFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPartnerNameUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#partner-name', $chunk);
        $this->assertStringContainsString('/doc#chat-presence-index', $chunk);
        $this->assertStringContainsString('/doc#chat-group-members-index', $chunk);

        $this->assertStringNotContainsString('во вкладке «Аккаунт» тоже партнёр', $chunk);
        $this->assertStringNotContainsString('под названием в шапке диалога', $chunk);
        $this->assertStringNotContainsString('partner_name в inbox', $chunk);
    }

    public function test_chat_page_docs_match_partner_name_contract(): void
    {
        $html = $this->docFile('chat.html');

        $this->assertStringContainsString('id="partner-name"', $html);
        $this->assertStringContainsString('/doc#chat-partner-name-index', $html);
        $start = strpos($html, 'id="partner-name"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="draft"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('peer-card-partner', $chunk);
        $this->assertStringContainsString('groupCardPartner', $chunk);
        $this->assertStringContainsString('partner_name', $chunk);
        $this->assertStringContainsString('partners.title', $chunk);
        $this->assertStringContainsString('accountCardBody', $chunk);
        $this->assertStringContainsString('#threadSubtitle', $chunk);
        $this->assertStringContainsString('ChatPartnerNameFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPartnerNameUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPartnerNameDocumentationContractTest', $chunk);
        $this->assertStringNotContainsString('вкладка «Аккаунт» показывает партнёра', $chunk);
    }

    public function test_catalog_and_controller_title_mention_partner_name(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="chat-partner-name-index"', $index);
        $this->assertStringContainsString('/doc#chat-partner-name-index', $index);
        $this->assertStringContainsString('название партнёра', $index);
        $this->assertStringContainsString('название партнёра в модалках Контакт и Группа', $controller);
        $this->assertStringContainsString('не Аккаунт, не шапка', $controller);
    }

    public function test_live_code_matches_documented_partner_name(): void
    {
        $root = dirname(__DIR__, 3);
        $js = (string) file_get_contents($root.'/resources/js/chat.js');
        $blade = (string) file_get_contents($root.'/resources/views/chat/index.blade.php');
        $css = (string) file_get_contents($root.'/resources/css/chat.css');
        $service = (string) file_get_contents($root.'/app/Services/Chat/ChatService.php');
        $controller = (string) file_get_contents($root.'/app/Http/Controllers/Chat/ChatApiController.php');
        $docs = $this->docFile('chat.html');

        $this->assertStringContainsString('id="groupCardPartner"', $blade);
        $this->assertStringContainsString('peer-card-partner', $js);
        $this->assertStringContainsString("targetId === 'accountCardBody'", $js);
        $this->assertStringContainsString('function setGroupCardPartner(', $js);
        $this->assertStringContainsString('.peer-card-partner {', $css);
        $this->assertStringContainsString('.group-card-partner {', $css);
        $this->assertStringContainsString('function partnerTitle(', $service);
        $this->assertStringContainsString("'partner_name'", $service);
        $this->assertStringContainsString('requirePartnerId()', $controller);

        $inboxStart = strpos($service, 'private function serializeThread(');
        $headerStart = strpos($service, 'private function serializeThreadHeader(');
        $this->assertNotFalse($inboxStart);
        $this->assertNotFalse($headerStart);
        $inboxChunk = substr($service, $inboxStart, $headerStart - $inboxStart);
        $headerChunk = substr($service, $headerStart, 1600);
        $this->assertStringNotContainsString("'partner_name'", $inboxChunk);
        $this->assertStringNotContainsString("'partner_name'", $headerChunk);

        $this->assertStringContainsString('ChatPartnerNameFeatureTest', $docs);
        $this->assertStringContainsString('ChatPartnerNameUxFeatureTest', $docs);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
