<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatMessage;
use App\Models\ChatThread;

/**
 * P1: нативный POST/PATCH без X-Requested-With — 302 на /chat, запись есть, не сырой JSON 200.
 *
 * UX-баг до фикса: API всегда отдавал JSON 201 → в браузере белый экран с сырым JSON,
 * если JS не перехватил submit.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatNonAjaxSafetyNetFeatureTest extends ChatTestCase
{
    public function test_non_ajax_store_thread_redirects_to_chat_and_creates_dialog(): void
    {
        $peer = $this->makePeer('NonAjaxPeer_');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), [
                'user_id' => $peer->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Нативный POST не должен отдавать сырой JSON 200');
        $this->assertNotSame(201, $response->getStatusCode(), 'Нативный POST не должен отдавать JSON 201');
        $response->assertRedirect(route('chat.index'));

        $this->assertTrue(
            ChatThread::query()
                ->has('participants', '=', 2)
                ->whereHas('participants', fn ($q) => $q->where('user_id', $this->user->id))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $peer->id))
                ->exists()
        );
    }

    public function test_non_ajax_store_thread_validation_redirects_with_user_id_field_error(): void
    {
        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['user_id']);
    }

    public function test_non_ajax_store_message_redirects_and_persists_body(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.messages.store', $thread->id), [
                'body' => 'Нативное сообщение',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(201, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));

        $this->assertDatabaseHas('messages', [
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'body' => 'Нативное сообщение',
        ]);
    }

    public function test_non_ajax_empty_message_redirects_with_body_field_error_and_does_not_persist(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.messages.store', $thread->id), [
                'body' => '   ',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['body']);

        $this->assertSame(0, ChatMessage::query()->where('thread_id', $thread->id)->count());
    }

    public function test_non_ajax_mark_read_redirects_and_clears_unread(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, $peer->id, 'Непрочитанное');

        $this->from(route('chat.index'))
            ->patch(route('chat.api.threads.read', $thread->id))
            ->assertRedirect(route('chat.index'));

        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 0);
    }

    public function test_non_ajax_save_draft_redirects_and_persists_body(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $response = $this->from(route('chat.index'))
            ->patch(route('chat.api.threads.draft', $thread->id), [
                'body' => 'Нативный черновик',
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));

        $this->assertDatabaseHas('participants', [
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'draft_body' => 'Нативный черновик',
        ]);
    }

    public function test_non_ajax_too_long_draft_redirects_with_body_field_error(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $response = $this->from(route('chat.index'))
            ->patch(route('chat.api.threads.draft', $thread->id), [
                'body' => str_repeat('я', 5001),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['body']);
    }

    public function test_non_ajax_store_thread_with_disabled_peer_redirects_with_user_id_error(): void
    {
        $disabled = $this->makePeer('Off_', ['is_enabled' => 0]);

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), [
                'user_id' => $disabled->id,
            ]);

        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['user_id']);
        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_ajax_store_thread_still_returns_json_created_not_redirect(): void
    {
        $peer = $this->makePeer();

        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'created', 'thread_id', 'thread']);
    }
}
