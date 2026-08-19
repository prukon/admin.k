<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Events\InboxBump;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Support\Facades\Event;

/**
 * Удаление чата: soft-delete лички и ручной группы, team_id нельзя,
 * повторный клик по контакту — новый 1-на-1.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatThreadDeleteFeatureTest extends ChatTestCase
{
    use InteractsWithTeamGroupChats;

    public function test_user_without_delete_permission_gets_403_and_thread_stays(): void
    {
        $peer = $this->makePeer('DelDenied_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, $this->user->id, 'Не удалять');

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertForbidden();

        $this->assertNotNull(ChatThread::query()->find($thread->id));
        $this->assertSame(1, ChatMessage::query()->where('thread_id', $thread->id)->count());
    }

    public function test_granted_user_soft_deletes_private_thread_and_messages(): void
    {
        $peer = $this->makePeer('DelPeer_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, $this->user->id, 'Удалится');
        $this->grantPermission($this->user, 'messages.threads.delete');

        Event::fake([InboxBump::class]);

        $this->deleteJson(route('chat.api.threads.destroy', $thread))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Чат удалён.')
            ->assertJsonPath('thread_id', $thread->id);

        $this->assertSoftDeleted('threads', ['id' => $thread->id]);
        $this->assertSoftDeleted('messages', [
            'thread_id' => $thread->id,
            'body' => 'Удалится',
        ]);
        $this->assertSoftDeleted('participants', [
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertSoftDeleted('participants', [
            'thread_id' => $thread->id,
            'user_id' => $peer->id,
        ]);

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($thread) {
            return (int) $event->userId === (int) $this->user->id
                && (int) ($event->payload['thread_id'] ?? 0) === (int) $thread->id
                && ! empty($event->payload['removed']);
        });
        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($thread, $peer) {
            return (int) $event->userId === (int) $peer->id
                && (int) ($event->payload['thread_id'] ?? 0) === (int) $thread->id
                && ! empty($event->payload['removed']);
        });
    }

    public function test_superadmin_deletes_without_explicit_grant(): void
    {
        $super = $this->createUserWithRole('superadmin');
        $peer = $this->makePeer('DelSaPeer_');
        $thread = $this->createThreadForUsers([$super->id, $peer->id]);
        $this->actingInPartner($super);

        $this->deleteJson(route('chat.api.threads.destroy', $thread))
            ->assertOk()
            ->assertJsonPath('message', 'Чат удалён.');

        $this->assertSoftDeleted('threads', ['id' => $thread->id]);
    }

    public function test_manual_group_is_soft_deleted(): void
    {
        $a = $this->makePeer('DelGrpA_');
        $b = $this->makePeer('DelGrpB_');
        $thread = $this->createGroupThreadForUsers([$this->user->id, $a->id, $b->id], 'Ручная');
        $this->grantPermission($this->user, 'messages.threads.delete');

        $this->deleteJson(route('chat.api.threads.destroy', $thread))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSoftDeleted('threads', ['id' => $thread->id]);
        $this->assertTrue((bool) ChatThread::withTrashed()->find($thread->id)?->is_group);
    }

    public function test_team_group_chat_returns_422_under_thread_and_stays(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $this->grantPermission($admin, 'groups.view');
        $this->grantPermission($admin, 'messages.threads.delete');
        $team = $this->storeTeamViaAjax('DelTeam_'.uniqid('', true));
        $thread = $this->teamThread($team);

        $this->deleteJson(route('chat.api.threads.destroy', $thread))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['thread']);

        $this->assertSame(
            'Нельзя удалить чат учебной группы.',
            $this->deleteJson(route('chat.api.threads.destroy', $thread))->json('errors.thread.0')
        );
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_after_delete_contact_click_creates_new_private_thread(): void
    {
        $peer = $this->makePeer('DelReuse_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, $this->user->id, 'Старое');
        $this->grantPermission($this->user, 'messages.threads.delete');

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertOk();

        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated();

        $newId = (int) $created->json('thread_id');
        $this->assertNotSame((int) $thread->id, $newId);
        $this->assertSame(0, ChatMessage::query()->where('thread_id', $newId)->count());
        $this->assertSame(1, ChatMessage::withTrashed()->where('thread_id', $thread->id)->count());
    }

    public function test_non_participant_with_permission_gets_403(): void
    {
        $a = $this->makePeer('DelA_');
        $b = $this->makePeer('DelB_');
        $thread = $this->createThreadForUsers([$a->id, $b->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertForbidden();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_deleted_thread_is_gone_from_inbox(): void
    {
        $peer = $this->makePeer('DelInbox_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertOk();

        $ids = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->pluck('id')
            ->all();
        $this->assertNotContains($thread->id, $ids);
    }

    public function test_missing_thread_is_404(): void
    {
        $this->grantPermission($this->user, 'messages.threads.delete');
        $this->deleteJson(route('chat.api.threads.destroy', 9_999_999))->assertNotFound();
    }

    public function test_peer_no_longer_sees_deleted_thread_in_inbox(): void
    {
        $peer = $this->makePeer('DelPeerInbox_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');
        $this->grantPermission($peer, 'messages.view');

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertOk();

        $this->assertNull($this->inboxRowFor($peer, (int) $thread->id));
    }

    public function test_second_delete_after_soft_delete_is_404_and_does_not_500(): void
    {
        $peer = $this->makePeer('DelTwice_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertOk();

        $again = $this->deleteJson(route('chat.api.threads.destroy', $thread));
        $this->assertNotSame(500, $again->getStatusCode());
        $this->assertNotSame(200, $again->getStatusCode());
        $again->assertNotFound();
    }

    public function test_superadmin_cannot_delete_team_group_chat(): void
    {
        $super = $this->makeEnabledSuperadmin('DelSaTeam');
        $this->actingInPartner($super);
        $team = $this->storeTeamViaAjax('DelSaTeam_'.uniqid('', true));
        $thread = $this->teamThread($team);
        $this->assertUserInThread($super, $thread);

        $this->deleteJson(route('chat.api.threads.destroy', $thread))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['thread'])
            ->assertJsonPath('errors.thread.0', 'Нельзя удалить чат учебной группы.');

        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_deleted_dialog_cannot_be_opened_and_has_no_live_messages(): void
    {
        $peer = $this->makePeer('DelGone_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, $this->user->id, 'Скроется');
        $this->grantPermission($this->user, 'messages.threads.delete');

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertOk();

        $show = $this->getJson(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $show->getStatusCode());
        $show->assertNotFound();

        $messages = $this->getJson(route('chat.api.threads.messages.index', $thread));
        $this->assertNotSame(500, $messages->getStatusCode());
        $messages->assertNotFound();
        $this->assertSame(0, ChatMessage::query()->where('thread_id', $thread->id)->count());
    }
}
