<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#chat-draft-index и chat.html §черновик должны совпадать
 * с фактическим UX: серверный draft_body, превью «Черновик:», собеседник не видит.
 */
final class ChatDraftDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_chat_draft_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-draft-index"', $html);
        $start = strpos($html, 'id="chat-draft-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="trainer-password-form-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('participants.draft_body', $chunk);
        $this->assertStringContainsString('5000', $chunk);
        $this->assertStringContainsString('PATCH /chat/api/threads/{thread}/draft', $chunk);
        $this->assertStringContainsString('chat.api.threads.draft', $chunk);
        $this->assertStringContainsString('messages.view', $chunk);
        $this->assertStringContainsString('errors.body', $chunk);
        $this->assertStringContainsString('Черновик:', $chunk);
        $this->assertStringContainsString('chat-li-preview.is-draft', $chunk);
        $this->assertStringContainsString('#f3a12b', $chunk);
        $this->assertStringContainsString('last_message_time', $chunk);
        $this->assertStringContainsString('#msgInput', $chunk);
        $this->assertStringContainsString('persistLeavingDraft', $chunk);
        $this->assertStringContainsString('500', $chunk);
        $this->assertStringContainsString('draft_body: \'\'', $chunk);
        $this->assertStringContainsString('только</b> отправителю', $chunk);
        $this->assertStringContainsString('ChatDraftFeatureTest', $chunk);
        $this->assertStringContainsString('ChatDraftUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#draft', $chunk);
        $this->assertStringContainsString('не</b> черновик ЗП', $chunk);
        $this->assertStringContainsString('sessionStorage', $chunk);

        $this->assertStringNotContainsString('sessionStorage</code> — единственное хранилище', $chunk);
        $this->assertStringNotContainsString('собеседник видит чужой черновик', $chunk);
        $this->assertStringNotContainsString('trainer_salary_draft', $chunk);
        $this->assertStringNotContainsString('inbox.bump</code> собеседнику при наборе', $chunk);
    }

    public function test_chat_page_docs_match_draft_contract(): void
    {
        $html = $this->docFile('chat.html');

        $this->assertStringContainsString('id="draft"', $html);
        $this->assertStringContainsString('/doc#chat-draft-index', $html);
        $start = strpos($html, 'id="draft"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="routes"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('participants.draft_body', $chunk);
        $this->assertStringContainsString('SaveChatDraftRequest', $chunk);
        $this->assertStringContainsString('PATCH /chat/api/threads/{thread}/draft', $chunk);
        $this->assertStringContainsString('errors.body', $chunk);
        $this->assertStringContainsString('Черновик:', $chunk);
        $this->assertStringContainsString('chat-li-preview.is-draft', $chunk);
        $this->assertStringContainsString('#msgInput', $chunk);
        $this->assertStringContainsString('500', $chunk);
        $this->assertStringContainsString('draft_body: \'\'', $chunk);
        $this->assertStringContainsString('не</b> бродкастит', $chunk);
        $this->assertStringNotContainsString('sessionStorage', $chunk);
        $this->assertStringNotContainsString('собеседник видит', $chunk);
    }

    public function test_catalog_and_controller_title_mention_server_draft(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('id="chat-draft-index"', $index);
        $this->assertStringContainsString('/doc#chat-draft-index', $index);
        $this->assertStringContainsString('черновик сообщения', $index);
        $this->assertStringContainsString('черновик на сервере (превью «Черновик:»)', $controller);
    }

    public function test_live_code_matches_documented_draft_rules(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 3).'/public/js/chat.js');
        $service = (string) file_get_contents(dirname(__DIR__, 3).'/app/Services/Chat/ChatService.php');
        $routes = (string) file_get_contents(dirname(__DIR__, 3).'/routes/web.php');
        $request = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Requests/Chat/SaveChatDraftRequest.php');
        $blade = (string) file_get_contents(dirname(__DIR__, 3).'/resources/views/chat/index.blade.php');

        $this->assertStringContainsString("threadUrl(id, '/draft')", $js);
        $this->assertStringContainsString("'Черновик: ' + draft", $js);
        $this->assertStringContainsString('is-draft', $js);
        $this->assertStringContainsString('persistLeavingDraft(threadId)', $js);
        $this->assertStringContainsString('}, 500)', $js);
        $this->assertStringContainsString('composerDraftFor(res.thread)', $js);

        $this->assertStringContainsString("function saveDraft(", $service);
        $this->assertStringContainsString("'draft_body' => \$this->viewerDraftBody", $service);
        $this->assertStringContainsString("\$bump['draft_body'] = ''", $service);
        $this->assertStringContainsString("if (\$isSender)", $service);

        $this->assertStringContainsString("/chat/api/threads/{thread}/draft", $routes);
        $this->assertStringContainsString("chat.api.threads.draft", $routes);

        $this->assertStringContainsString("'max:5000'", $request);
        $this->assertStringContainsString('Черновик слишком длинный (максимум 5000 символов).', $request);

        $this->assertStringContainsString('.chat-li-preview.is-draft', $blade);
        $this->assertStringContainsString('#f3a12b', $blade);
    }

    public function test_other_chat_docs_do_not_claim_peer_sees_draft_or_client_only_storage(): void
    {
        $index = $this->docFile('index.html');
        $chat = $this->docFile('chat.html');

        $this->assertStringNotContainsString('собеседник видит чужой черновик', $index);
        $this->assertStringNotContainsString('собеседник видит чужой черновик', $chat);
        $this->assertStringNotContainsString('черновик только в sessionStorage', $index);
        $this->assertStringNotContainsString('черновик только в sessionStorage', $chat);
        $this->assertStringContainsString('Собеседник чужой черновик не видит', $index);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
