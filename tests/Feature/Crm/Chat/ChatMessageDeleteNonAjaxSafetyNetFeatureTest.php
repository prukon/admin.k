<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatMessage;
use App\Models\ChatThread;

/**
 * P1: нативный DELETE без X-Requested-With — 302 на /chat, soft-delete есть, не сырой JSON 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMessageDeleteNonAjaxSafetyNetFeatureTest extends ChatTestCase
{
    public function test_non_ajax_delete_redirects_and_soft_deletes(): void
    {
        $peer = $this->makePeer('MsgDelNative_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Native');
        $this->grantPermission($this->user, 'messages.own.delete');

        $response = $this->from(route('chat.index'))
            ->delete($this->destroyUrl($thread, $message));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Нативный DELETE не должен отдавать сырой JSON 200');
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHas('status', 'Сообщение удалено.');
        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_non_ajax_without_permission_is_403_not_json_200(): void
    {
        $peer = $this->makePeer('MsgDelNative403_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Нет права');

        $response = $this->from(route('chat.index'))
            ->delete($this->destroyUrl($thread, $message));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_native_get_does_not_delete(): void
    {
        $peer = $this->makePeer('MsgDelNativeGet_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'GET');
        $this->grantPermission($this->user, 'messages.own.delete');

        $response = $this->get($this->destroyUrl($thread, $message));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [404, 405]);
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_native_post_and_patch_do_not_delete_the_message(): void
    {
        $peer = $this->makePeer('MsgDelNativeWrong_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Wrong');
        $this->grantPermission($this->user, 'messages.own.delete');
        $url = $this->destroyUrl($thread, $message);

        foreach (['POST', 'PATCH'] as $method) {
            $response = $this->from(route('chat.index'))->call($method, $url);
            $this->assertNotSame(500, $response->getStatusCode(), $method.' не 500');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' не сырой JSON 200');
            $this->assertContains($response->getStatusCode(), [404, 405], $method.' 404/405');
        }

        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    private function destroyUrl(ChatThread $thread, ChatMessage $message): string
    {
        return route('chat.api.threads.messages.destroy', [
            'thread' => $thread->id,
            'message' => $message->id,
        ]);
    }
}
