<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#chat-emoji-index и chat.html §emoji должны совпадать с кодом.
 */
final class ChatEmojiDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_chat_emoji_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-emoji-index"', $html);
        $start = strpos($html, 'id="chat-emoji-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-thread-delete-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#emojiBtn', $chunk);
        $this->assertStringContainsString('#msgInput', $chunk);
        $this->assertStringContainsString('ChatEmoji::COMPOSER', $chunk);
        $this->assertStringContainsString('is-big-emoji', $chunk);
        $this->assertStringContainsString('один смайл', $chunk);
        $this->assertStringContainsString('аватарки', $chunk);
        $this->assertStringContainsString('4+', $chunk);
        $this->assertStringContainsString('PUT /chat/api/threads/{thread}/messages/{message}/reaction', $chunk);
        $this->assertStringContainsString('chat.api.threads.messages.reaction.update', $chunk);
        $this->assertStringContainsString('errors.emoji', $chunk);
        $this->assertStringContainsString('#msgReactionError', $chunk);
        $this->assertStringContainsString('message.reaction', $chunk);
        $this->assertStringContainsString('inbox.bump', $chunk);
        $this->assertStringContainsString('ChatReactionFeatureTest', $chunk);
        $this->assertStringContainsString('ChatReactionUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#emoji', $chunk);
        $this->assertStringNotContainsString('стикерпак', $chunk);
        $this->assertStringNotContainsString('несколько смайлов от одного человека', $chunk);
    }

    public function test_chat_page_docs_match_emoji_contract(): void
    {
        $html = $this->docFile('chat.html');

        $this->assertStringContainsString('id="emoji"', $html);
        $this->assertStringContainsString('/doc#chat-emoji-index', $html);
        $start = strpos($html, 'id="emoji"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="routes"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#emojiBtn', $chunk);
        $this->assertStringContainsString('#composerEmojisJson', $chunk);
        $this->assertStringContainsString('StoreChatMessageReactionRequest', $chunk);
        $this->assertStringContainsString('message_reactions', $chunk);
        $this->assertStringContainsString('errors.emoji', $chunk);
        $this->assertStringContainsString('is-big-emoji', $chunk);
        $this->assertStringContainsString('msg-reactions', $chunk);
        $this->assertStringContainsString('message.reaction', $chunk);
        $this->assertStringContainsString('<b>Не</b> шлёт <code>inbox.bump</code>', $chunk);
        $this->assertStringNotContainsString('стикерпак обязателен', $chunk);
    }

    public function test_live_code_matches_documented_emoji_rules(): void
    {
        $base = dirname(__DIR__, 3);
        $js = (string) file_get_contents($base.'/resources/js/chat.js');
        $css = (string) file_get_contents($base.'/resources/css/chat.css');
        $service = (string) file_get_contents($base.'/app/Services/Chat/ChatService.php');
        $emoji = (string) file_get_contents($base.'/app/Services/Chat/ChatEmoji.php');
        $routes = (string) file_get_contents($base.'/routes/web.php');
        $request = (string) file_get_contents($base.'/app/Http/Requests/Chat/StoreChatMessageReactionRequest.php');
        $blade = (string) file_get_contents($base.'/resources/views/chat/index.blade.php');
        $event = (string) file_get_contents($base.'/app/Events/MessageReactionUpdated.php');

        $this->assertStringContainsString('id="emojiBtn"', $blade);
        $this->assertStringContainsString('chat-composer-field', $blade);
        $this->assertStringContainsString('id="msgReactionError"', $blade);
        $this->assertStringContainsString('data-error-for="emoji"', $blade);
        $this->assertStringContainsString('id="composerEmojisJson"', $blade);
        $this->assertStringContainsString('id="reactionEmojisJson"', $blade);

        $this->assertStringContainsString('insertComposerEmoji(', $js);
        $this->assertStringContainsString('parseEmojiJson(', $js);
        $this->assertStringContainsString("listen('.message.reaction'", $js);
        $this->assertStringContainsString('is-big-emoji', $js);
        $this->assertStringContainsString('if (count >= 4)', $js);

        $this->assertStringContainsString('.chat-emoji-btn {', $css);
        $this->assertStringContainsString('.msg-reactions {', $css);
        $this->assertStringContainsString('.msg-bubble.is-big-emoji {', $css);

        $this->assertStringContainsString('function setMessageReaction(', $service);
        $this->assertStringContainsString("'reactions' =>", $service);
        $this->assertStringContainsString('public const COMPOSER', $emoji);
        $this->assertStringContainsString('public const REACTIONS', $emoji);

        $this->assertStringContainsString("/chat/api/threads/{thread}/messages/{message}/reaction", $routes);
        $this->assertStringContainsString('chat.api.threads.messages.reaction.update', $routes);

        $this->assertStringContainsString('Этот смайлик нельзя поставить как реакцию.', $request);
        $this->assertStringContainsString('Выберите смайлик.', $request);

        $this->assertStringContainsString("return 'message.reaction';", $event);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
