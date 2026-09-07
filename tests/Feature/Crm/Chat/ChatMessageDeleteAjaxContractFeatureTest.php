<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatMessage;
use App\Models\ChatThread;

/**
 * P1: JSON-контракт удаления своего сообщения — 200 ok+message, 403.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMessageDeleteAjaxContractFeatureTest extends ChatTestCase
{
    public function test_ajax_delete_returns_ok_message_and_ids(): void
    {
        $peer = $this->makePeer('MsgDelAjax_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'AJAX');
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->deleteJson($this->destroyUrl($thread, $message))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Сообщение удалено.')
            ->assertJsonPath('thread_id', $thread->id)
            ->assertJsonPath('message_id', $message->id)
            ->assertJsonStructure(['ok', 'message', 'thread_id', 'message_id']);
    }

    public function test_ajax_without_permission_is_403_json(): void
    {
        $peer = $this->makePeer('MsgDelAjax403_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Нет права');

        $this->deleteJson($this->destroyUrl($thread, $message))->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_ajax_foreign_message_is_403_json(): void
    {
        $peer = $this->makePeer('MsgDelAjaxAlien_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $peer->id, 'Чужое AJAX');
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->deleteJson($this->destroyUrl($thread, $message))->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_ajax_success_body_is_not_empty(): void
    {
        $peer = $this->makePeer('MsgDelAjaxBody_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Тело');
        $this->grantPermission($this->user, 'messages.own.delete');

        $res = $this->deleteJson($this->destroyUrl($thread, $message))->assertOk();
        $this->assertNotSame('', trim((string) $res->getContent()));
        $this->assertIsInt($res->json('message_id'));
        $this->assertIsInt($res->json('thread_id'));
    }

    private function destroyUrl(ChatThread $thread, ChatMessage $message): string
    {
        return route('chat.api.threads.messages.destroy', [
            'thread' => $thread->id,
            'message' => $message->id,
        ]);
    }
}
