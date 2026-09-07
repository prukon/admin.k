<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Events\InboxBump;
use App\Events\MessageDeleted;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use Illuminate\Support\Facades\Event;

/**
 * Удаление своих сообщений: soft-delete, превью inbox, unread, чужие нельзя.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMessageDeleteFeatureTest extends ChatTestCase
{
    use InteractsWithTeamGroupChats;

    public function test_user_without_delete_permission_gets_403_and_message_stays(): void
    {
        $peer = $this->makePeer('MsgDelDenied_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Не удалять');

        $this->deleteJson($this->destroyUrl($thread, $message))->assertForbidden();

        $this->assertNotNull(ChatMessage::query()->find($message->id));
        $this->assertSame((int) $message->id, (int) $thread->fresh()->last_message_id);
    }

    public function test_granted_user_soft_deletes_own_message_and_updates_last(): void
    {
        $peer = $this->makePeer('MsgDelPeer_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $first = $this->seedMessage($thread, $this->user->id, 'Останется');
        $second = $this->seedMessage($thread, $this->user->id, 'Удалится');
        $this->grantPermission($this->user, 'messages.own.delete');

        Event::fake([InboxBump::class, MessageDeleted::class]);

        $this->deleteJson($this->destroyUrl($thread, $second))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Сообщение удалено.')
            ->assertJsonPath('thread_id', $thread->id)
            ->assertJsonPath('message_id', $second->id);

        $this->assertSoftDeleted('messages', [
            'id' => $second->id,
            'body' => 'Удалится',
        ]);
        $this->assertNotNull(ChatMessage::query()->find($first->id));
        $this->assertSame((int) $first->id, (int) $thread->fresh()->last_message_id);

        $inbox = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertNotNull($inbox);
        $this->assertSame('Останется', $inbox['last_message']);

        Event::assertDispatched(MessageDeleted::class, function (MessageDeleted $event) use ($thread, $second) {
            return $event->threadId === (int) $thread->id
                && $event->messageId === (int) $second->id;
        });
        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($thread) {
            return (int) $event->userId === (int) $this->user->id
                && (int) ($event->payload['thread_id'] ?? 0) === (int) $thread->id
                && ($event->payload['last_message'] ?? null) === 'Останется';
        });
    }

    public function test_deleting_last_remaining_message_clears_preview(): void
    {
        $peer = $this->makePeer('MsgDelLast_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Единственное');
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->deleteJson($this->destroyUrl($thread, $message))->assertOk();

        $this->assertNull($thread->fresh()->last_message_id);
        $inbox = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertNotNull($inbox);
        $this->assertNull($inbox['last_message']);
        $this->assertNull($inbox['last_message_time']);
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_cannot_delete_peers_message_even_with_permission(): void
    {
        $peer = $this->makePeer('MsgDelAlien_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $peer->id, 'Чужое');
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->deleteJson($this->destroyUrl($thread, $message))->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_superadmin_deletes_own_without_grant_but_not_peers(): void
    {
        $super = $this->createUserWithRole('superadmin');
        $peer = $this->makePeer('MsgDelSaPeer_');
        $thread = $this->createThreadForUsers([$super->id, $peer->id]);
        $own = $this->seedMessage($thread, $super->id, 'Моё SA');
        $alien = $this->seedMessage($thread, $peer->id, 'Чужое SA');
        $this->actingInPartner($super);

        $this->deleteJson($this->destroyUrl($thread, $own))
            ->assertOk()
            ->assertJsonPath('message', 'Сообщение удалено.');
        $this->assertSoftDeleted('messages', ['id' => $own->id]);

        $this->deleteJson($this->destroyUrl($thread, $alien))->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($alien->id));
    }

    public function test_unread_decrements_only_for_those_who_had_not_read(): void
    {
        $peer = $this->makePeer('MsgDelUnread_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Непрочитанное');
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->assertSame(1, (int) ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $peer->id)
            ->value('unread_count'));

        $this->deleteJson($this->destroyUrl($thread, $message))->assertOk();

        $this->assertSame(0, (int) ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $peer->id)
            ->value('unread_count'));
    }

    public function test_already_read_unread_stays_zero(): void
    {
        $peer = $this->makePeer('MsgDelRead_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Прочитанное');
        ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $peer->id)
            ->update([
                'unread_count' => 0,
                'last_read' => now()->addMinute(),
            ]);
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->deleteJson($this->destroyUrl($thread, $message))->assertOk();

        $this->assertSame(0, (int) ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $peer->id)
            ->value('unread_count'));
    }

    public function test_own_message_in_team_group_chat_can_be_deleted(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $this->grantPermission($admin, 'groups.view');
        $this->grantPermission($admin, 'messages.own.delete');
        $team = $this->storeTeamViaAjax('MsgDelTeam_'.uniqid('', true));
        $thread = $this->teamThread($team);
        $message = $this->seedMessage($thread, $admin->id, 'В учебной группе');

        $this->deleteJson($this->destroyUrl($thread, $message))
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertSoftDeleted('messages', ['id' => $message->id]);
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_non_participant_with_permission_gets_403(): void
    {
        $a = $this->makePeer('MsgDelA_');
        $b = $this->makePeer('MsgDelB_');
        $thread = $this->createThreadForUsers([$a->id, $b->id]);
        $message = $this->seedMessage($thread, $a->id, 'Не своё');
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->deleteJson($this->destroyUrl($thread, $message))->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_missing_message_is_404(): void
    {
        $peer = $this->makePeer('MsgDel404_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->deleteJson(route('chat.api.threads.messages.destroy', [
            'thread' => $thread->id,
            'message' => 9_999_999,
        ]))->assertNotFound();
    }

    public function test_message_from_other_thread_is_404(): void
    {
        $peer = $this->makePeer('MsgDelWrong_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $other = $this->createThreadForUsers([$this->user->id, $peer->id], 'Другой');
        $message = $this->seedMessage($other, $this->user->id, 'Не этот тред');
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->deleteJson($this->destroyUrl($thread, $message))->assertNotFound();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_deleted_message_disappears_from_history(): void
    {
        $peer = $this->makePeer('MsgDelHist_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $keep = $this->seedMessage($thread, $this->user->id, 'Живое');
        $gone = $this->seedMessage($thread, $this->user->id, 'Скроется');
        $this->grantPermission($this->user, 'messages.own.delete');

        $this->deleteJson($this->destroyUrl($thread, $gone))->assertOk();

        $bodies = collect($this->getJson(route('chat.api.threads.show', $thread))->assertOk()->json('messages'))
            ->pluck('body')
            ->all();
        $this->assertContains('Живое', $bodies);
        $this->assertNotContains('Скроется', $bodies);
        $this->assertNotNull(ChatMessage::query()->find($keep->id));
    }

    private function destroyUrl(ChatThread $thread, ChatMessage $message): string
    {
        return route('chat.api.threads.messages.destroy', [
            'thread' => $thread->id,
            'message' => $message->id,
        ]);
    }
}
