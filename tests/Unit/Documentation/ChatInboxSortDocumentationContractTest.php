<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#chat-inbox-sort-index и chat.html §inbox-sort совпадают с кодом:
 * непрочитанные сверху, last_message_id / last_message_time, пустые внизу, не updated_at.
 */
final class ChatInboxSortDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_inbox_sort_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-inbox-sort-index"', $html);
        $start = strpos($html, 'id="chat-inbox-sort-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="online-users-overlay-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('непрочитанные', $chunk);
        $this->assertStringContainsString('last_message_time', $chunk);
        $this->assertStringContainsString('last_message_id', $chunk);
        $this->assertStringContainsString('threads.updated_at', $chunk);
        $this->assertStringContainsString('last_message_time: null', $chunk);
        $this->assertStringContainsString('sortThreads', $chunk);
        $this->assertStringContainsString('inbox.bump', $chunk);
        $this->assertStringContainsString('GET /chat/api/threads', $chunk);
        $this->assertStringContainsString('ChatService::threadsForUser', $chunk);
        $this->assertStringContainsString('ChatInboxSortFeatureTest', $chunk);
        $this->assertStringContainsString('ChatInboxSortUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#inbox-sort', $chunk);
        $this->assertStringContainsString('учебн', $chunk);

        $this->assertStringNotContainsString('сортировка по updated_at', $chunk);
        $this->assertStringNotContainsString('пустые группы сверху', $chunk);
        $this->assertStringNotContainsString('непрочитанные не поднимаем', $chunk);
    }

    public function test_chat_page_docs_match_inbox_sort_contract(): void
    {
        $html = $this->docFile('chat.html');

        $this->assertStringContainsString('id="inbox-sort"', $html);
        $this->assertStringContainsString('/doc#chat-inbox-sort-index', $html);
        $start = strpos($html, 'id="inbox-sort"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="presence"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('sortThreads', $chunk);
        $this->assertStringContainsString('last_message_id', $chunk);
        $this->assertStringContainsString('last_message_time: null', $chunk);
        $this->assertStringContainsString('threads.updated_at', $chunk);
        $this->assertStringContainsString('chat_inbox_sort', $chunk);
        $this->assertStringContainsString('groupCreatedBumpPayload', $chunk);
        $this->assertStringContainsString('ChatInboxSortFeatureTest', $chunk);
        $this->assertStringContainsString('ChatInboxSortUxFeatureTest', $chunk);
        $this->assertStringNotContainsString('порядок по updated_at', $chunk);
    }

    public function test_catalog_and_controller_title_mention_inbox_sort(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="chat-inbox-sort-index"', $index);
        $this->assertStringContainsString('/doc#chat-inbox-sort-index', $index);
        $this->assertStringContainsString('порядок списка', $index);
        $this->assertStringContainsString('сортировка inbox', $controller);
        $this->assertStringContainsString('не updated_at', $controller);
    }

    public function test_live_code_matches_documented_inbox_sort(): void
    {
        $root = dirname(__DIR__, 3);
        $js = (string) file_get_contents($root.'/resources/js/chat.js');
        $service = (string) file_get_contents($root.'/app/Services/Chat/ChatService.php');
        $docs = $this->docFile('chat.html');

        $this->assertStringContainsString("orderByDesc('threads.last_message_id')", $service);
        $this->assertStringContainsString('chat_inbox_sort', $service);
        $this->assertStringContainsString("CASE WHEN COALESCE(chat_inbox_sort.unread_count, 0) > 0 THEN 0 ELSE 1 END", $service);

        $inboxStart = strpos($service, 'private function serializeThread(');
        $headerStart = strpos($service, 'private function serializeThreadHeader(');
        $this->assertNotFalse($inboxStart);
        $this->assertNotFalse($headerStart);
        $inboxChunk = substr($service, $inboxStart, $headerStart - $inboxStart);
        $this->assertStringNotContainsString('updated_at', $inboxChunk);
        $this->assertStringContainsString('$last?->created_at?->toDateTimeString()', $inboxChunk);

        $bumpStart = strpos($service, 'private function groupCreatedBumpPayload(');
        $bumpEnd = strpos($service, 'private function titleForViewer(');
        $this->assertNotFalse($bumpStart);
        $this->assertNotFalse($bumpEnd);
        $bumpChunk = substr($service, $bumpStart, $bumpEnd - $bumpStart);
        $this->assertStringNotContainsString('updated_at', $bumpChunk);
        $this->assertStringContainsString('$last?->created_at?->toDateTimeString()', $bumpChunk);

        $sortStart = strpos($js, 'function sortThreads(');
        $sortEnd = strpos($js, 'function threadListTitle(');
        $this->assertNotFalse($sortStart);
        $this->assertNotFalse($sortEnd);
        $sortChunk = substr($js, $sortStart, $sortEnd - $sortStart);
        $this->assertStringContainsString('unread_count', $sortChunk);
        $this->assertStringContainsString('last_message_time', $sortChunk);
        $this->assertStringNotContainsString('updated_at', $sortChunk);

        $this->assertStringContainsString('ChatInboxSortFeatureTest', $docs);
        $this->assertStringContainsString('ChatInboxSortUxFeatureTest', $docs);
        $this->assertStringContainsString('ChatInboxSortDocumentationContractTest', $docs);
    }

    public function test_other_chat_docs_do_not_claim_empty_groups_float_by_updated_at(): void
    {
        $index = $this->docFile('index.html');
        $chat = $this->docFile('chat.html');

        $this->assertStringNotContainsString('пустые группы сверху', $index);
        $this->assertStringNotContainsString('пустые группы сверху', $chat);
        $this->assertStringNotContainsString('список по threads.updated_at', $index);
        $this->assertStringNotContainsString('список по threads.updated_at', $chat);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
