<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Services\Chat\ChatService;

/**
 * Непрочитанные — колонка participants.unread_count (SUM), не COUNT messages.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatUnreadCounterFeatureTest extends ChatTestCase
{
    public function test_unread_total_uses_participant_counter_not_message_count(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $this->user->id)
            ->update(['unread_count' => 5]);

        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 5);

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertSame(5, (int) ($row['unread_count'] ?? 0));
    }

    public function test_send_increments_peer_counter_and_read_resets_own(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Привет',
        ])->assertCreated();

        $this->assertSame(
            1,
            (int) ChatParticipant::query()
                ->where('thread_id', $threadId)
                ->where('user_id', $peer->id)
                ->value('unread_count')
        );
        $this->assertSame(
            0,
            (int) ChatParticipant::query()
                ->where('thread_id', $threadId)
                ->where('user_id', $this->user->id)
                ->value('unread_count')
        );

        $this->actingInPartner($peer);
        $this->grantPermission($peer, 'messages.view');
        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1);

        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('unread_total', 0);

        $this->assertSame(
            0,
            (int) ChatParticipant::query()
                ->where('thread_id', $threadId)
                ->where('user_id', $peer->id)
                ->value('unread_count')
        );
    }

    public function test_thread_list_uses_last_message_id_not_latest_scan(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, $peer->id, 'Первое');
        $second = $this->seedMessage($thread, $peer->id, 'Второе');

        $thread->refresh();
        $this->assertSame((int) $second->id, (int) $thread->last_message_id);

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertSame('Второе', $row['last_message'] ?? null);
    }

    public function test_unread_private_and_group_totals_split_by_is_group(): void
    {
        $peer = $this->makePeer();
        $other = $this->makePeer('ChatOther_');
        $private = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $group = $this->createGroupThreadForUsers([
            (int) $this->user->id,
            (int) $peer->id,
            (int) $other->id,
        ]);

        ChatParticipant::query()
            ->where('thread_id', $private->id)
            ->where('user_id', $this->user->id)
            ->update(['unread_count' => 2]);
        ChatParticipant::query()
            ->where('thread_id', $group->id)
            ->where('user_id', $this->user->id)
            ->update(['unread_count' => 3]);

        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 5);

        $chat = app(ChatService::class);
        $this->assertSame(5, $chat->unreadTotal((int) $this->user->id));
        $this->assertSame(2, $chat->unreadPrivateTotal((int) $this->user->id));
        $this->assertSame(3, $chat->unreadGroupTotal((int) $this->user->id));
    }
}
