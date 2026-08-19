<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;

/**
 * Идентичность 1-на-1: UNIQUE участника, поиск пары без COUNT=2,
 * 0/1 живых, группа из двоих не становится личкой.
 *
 * UX-баг до фикса: после выхода/отключения собеседника повторный клик
 * плодил второй тред; группа с двумя живыми открывалась как «личный диалог»;
 * unique + updateOrCreate после leave давал 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatPrivateThreadIdentityFeatureTest extends ChatTestCase
{
    public function test_guest_cannot_create_or_reuse_private_thread(): void
    {
        $peer = $this->makePeer('IdGuest_');
        Auth::logout();

        $html = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), ['user_id' => $peer->id]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertTrue($html->isRedirect());

        $json = $this->postJson(route('chat.api.threads.store'), ['user_id' => $peer->id]);
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_user_without_messages_view_cannot_create_or_reuse_private_thread(): void
    {
        $peer = $this->makePeer('IdDeniedPeer_');
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $json = $this->postJson(route('chat.api.threads.store'), ['user_id' => $peer->id]);
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertForbidden();

        $html = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), ['user_id' => $peer->id]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $html->assertForbidden();
    }

    public function test_user_with_permission_creates_then_reuses_same_private_thread(): void
    {
        $peer = $this->makePeer('IdReuse_');

        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('created', true)
            ->assertJsonPath('thread.is_group', false)
            ->assertJsonPath('thread.peer_id', (int) $peer->id)
            ->assertJsonStructure([
                'ok',
                'created',
                'thread_id',
                'thread' => ['id', 'title', 'avatar', 'peer_id', 'peer_is_online', 'is_group'],
            ]);

        $threadId = (int) $created->json('thread_id');
        $this->assertGreaterThan(0, $threadId);

        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('created', false)
            ->assertJsonPath('thread_id', $threadId)
            ->assertJsonPath('thread.is_group', false)
            ->assertJsonPath('thread.peer_id', (int) $peer->id);

        $this->actingInPartner($peer);
        $this->grantPermission($peer, 'messages.view');
        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $this->user->id,
        ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('thread_id', $threadId);
    }

    public function test_validation_errors_stay_under_user_id(): void
    {
        $this->postJson(route('chat.api.threads.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id'])
            ->assertJsonPath('errors.user_id.0', 'Выберите собеседника.');

        $this->postJson(route('chat.api.threads.store'), ['user_id' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->postJson(route('chat.api.threads.store'), ['user_id' => $this->user->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Нельзя создать диалог с самим собой.');

        $this->postJson(route('chat.api.threads.store'), ['user_id' => $this->foreignUser->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Нельзя добавить пользователя другой организации.');

        $disabled = $this->makePeer('IdOff_', ['is_enabled' => 0]);
        $this->postJson(route('chat.api.threads.store'), ['user_id' => $disabled->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Этот пользователь отключён.');
    }

    public function test_wrong_methods_on_store_uri_are_not_empty_200(): void
    {
        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.threads.store'), ['user_id' => 1]);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' JSON должен быть 405');

            $html = $this->call($method, route('chat.api.threads.store'), ['user_id' => 1]);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertSame(405, $html->getStatusCode(), $method.' HTML должен быть 405');
        }
    }

    public function test_native_reuse_redirects_and_does_not_create_second_thread(): void
    {
        $peer = $this->makePeer('IdNative_');
        $first = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), [
                'user_id' => (string) $peer->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Нативный повтор не сырой JSON 200');
        $this->assertNotSame(201, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $this->assertSame(1, ChatThread::query()->where('is_group', false)->count());
        $this->assertTrue(ChatThread::query()->whereKey($first)->exists());
    }

    public function test_native_validation_redirects_with_user_id_field_error(): void
    {
        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['user_id']);
    }

    public function test_leftover_private_thread_stays_in_inbox_and_is_reused_with_history(): void
    {
        $peer = $this->makePeer('IdLeft_');
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Сохрани историю',
        ])->assertCreated();

        ChatParticipant::query()
            ->where('thread_id', $threadId)
            ->where('user_id', $peer->id)
            ->delete();

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $row = $list->firstWhere('id', $threadId);
        $this->assertNotNull($row, 'Оставшийся участник должен видеть тред в списке');
        $this->assertFalse((bool) ($row['is_group'] ?? false));
        $this->assertNull($row['peer_id']);
        $this->assertSame('Диалог', $row['title']);

        $this->actingInPartner($peer);
        $this->grantPermission($peer, 'messages.view');
        $peerList = collect($this->getJson(route('chat.api.threads.index'))->json('threads'));
        $this->assertNull($peerList->firstWhere('id', $threadId));

        $this->actingInPartner($this->user);
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Пока один',
        ])
            ->assertCreated()
            ->assertJsonPath('body', 'Пока один');
        $this->assertFalse(
            ChatParticipant::query()->where('thread_id', $threadId)->where('user_id', $peer->id)->exists(),
            'Отправка не должна возвращать вышедшего собеседника'
        );

        $this->patchJson(route('chat.api.threads.read', $threadId))->assertOk();
        $this->patchJson(route('chat.api.threads.draft', $threadId), [
            'body' => 'черновик после ухода',
        ])->assertOk()->assertJsonPath('ok', true);

        $again = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('thread_id', $threadId)
            ->assertJsonPath('thread.peer_id', (int) $peer->id)
            ->assertJsonPath('thread.is_group', false);

        $this->assertNotSame('Диалог', $again->json('thread.title'));
        $this->assertSame(2, ChatParticipant::query()->where('thread_id', $threadId)->count());
        $this->assertSame(1, ChatThread::query()->where('is_group', false)->count());

        $bodies = collect($this->getJson(route('chat.api.threads.show', $threadId))->json('messages'))
            ->pluck('body')
            ->all();
        $this->assertContains('Сохрани историю', $bodies);
        $this->assertContains('Пока один', $bodies);
    }

    public function test_empty_private_thread_is_hidden_until_contact_click_restores_both(): void
    {
        $peer = $this->makePeer('IdEmpty_');
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        ChatParticipant::query()->where('thread_id', $threadId)->delete();

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertNull($list->firstWhere('id', $threadId));

        $native = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), ['user_id' => $peer->id]);
        $this->assertNotSame(500, $native->getStatusCode());
        $this->assertNotSame(200, $native->getStatusCode());
        $native->assertRedirect(route('chat.index'));

        $this->assertSame(2, ChatParticipant::query()->where('thread_id', $threadId)->count());
        $this->assertSame(1, ChatThread::query()->where('is_group', false)->count());
    }

    public function test_two_person_group_is_not_opened_as_private_dialog(): void
    {
        $peer = $this->makePeer('IdTwoGroup_');
        $group = $this->createGroupThreadForUsers([$this->user->id, $peer->id], 'Вдвоём');

        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated();

        $privateId = (int) $created->json('thread_id');
        $this->assertNotSame((int) $group->id, $privateId);
        $this->assertFalse((bool) $created->json('thread.is_group'));
        $this->assertSame((int) $peer->id, (int) $created->json('thread.peer_id'));

        $list = collect($this->getJson(route('chat.api.threads.index'))->json('threads'));
        $this->assertNotNull($list->firstWhere('id', $group->id));
        $this->assertNotNull($list->firstWhere('id', $privateId));
        $this->assertTrue((bool) $list->firstWhere('id', $group->id)['is_group']);
        $this->assertFalse((bool) $list->firstWhere('id', $privateId)['is_group']);
    }

    public function test_thread_with_three_people_without_group_flag_is_not_reused_as_private(): void
    {
        $a = $this->makePeer('IdCrowdA_');
        $b = $this->makePeer('IdCrowdB_');
        $crowd = $this->createThreadForUsers([$this->user->id, $a->id, $b->id], 'Трое');
        $this->assertFalse((bool) $crowd->fresh()->is_group);

        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $a->id,
        ])->assertCreated();

        $this->assertNotSame((int) $crowd->id, (int) $created->json('thread_id'));
        $this->assertFalse((bool) $created->json('thread.is_group'));
        $this->assertSame((int) $a->id, (int) $created->json('thread.peer_id'));
    }

    public function test_empty_team_chat_is_not_reused_as_private_thread(): void
    {
        $peer = $this->makePeer('IdTeamEmpty_');
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Пустая '.uniqid('', true),
        ]);
        $teamChat = ChatThread::query()->create([
            'subject' => $team->title,
            'is_group' => true,
            'team_id' => $team->id,
        ]);

        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated();

        $this->assertNotSame((int) $teamChat->id, (int) $created->json('thread_id'));
        $this->assertFalse((bool) $created->json('thread.is_group'));
        $this->assertNull($teamChat->fresh()->deleted_at);
        $this->assertSame((int) $team->id, (int) $teamChat->fresh()->team_id);
    }

    public function test_starting_dialog_after_peer_disabled_reuses_old_private_thread(): void
    {
        $peer = $this->makePeer('IdDisable_');
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        ChatMessage::query()->create([
            'thread_id' => $threadId,
            'user_id' => $this->user->id,
            'body' => 'До отключения',
        ]);

        $peer->forceFill(['is_enabled' => 0])->save();
        $this->assertFalse(
            ChatParticipant::query()->where('thread_id', $threadId)->where('user_id', $peer->id)->exists()
        );
        $this->assertNull(ChatThread::query()->find($threadId)?->deleted_at);

        $peer->forceFill(['is_enabled' => 1])->save();
        $this->assertFalse(
            ChatParticipant::query()->where('thread_id', $threadId)->where('user_id', $peer->id)->exists(),
            'Включение не должно само возвращать в личку'
        );

        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('thread_id', $threadId);

        $this->assertTrue(
            ChatParticipant::query()->where('thread_id', $threadId)->where('user_id', $peer->id)->exists()
        );
        $this->assertSame(1, ChatThread::query()->where('is_group', false)->count());
    }

    public function test_left_group_member_cannot_send_or_draft_and_does_not_get_server_error(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('IdLeaveA_');
        $b = $this->makePeer('IdLeaveB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Выход unique');
        $this->actingInPartner($a);

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))
            ->assertOk()
            ->assertJsonPath('left', true);

        $send = $this->postJson(route('chat.api.threads.messages.store', $thread), [
            'body' => 'после выхода',
        ]);
        $this->assertNotSame(500, $send->getStatusCode());
        $send->assertForbidden();

        $draft = $this->patchJson(route('chat.api.threads.draft', $thread), [
            'body' => 'черновик после выхода',
        ]);
        $this->assertNotSame(500, $draft->getStatusCode());
        $draft->assertForbidden();

        $read = $this->patchJson(route('chat.api.threads.read', $thread));
        $this->assertNotSame(500, $read->getStatusCode());
        $read->assertForbidden();

        $this->assertSame(
            1,
            ChatParticipant::withTrashed()->where('thread_id', $thread->id)->where('user_id', $a->id)->count()
        );
        $this->assertFalse(
            ChatMessage::query()->where('thread_id', $thread->id)->where('body', 'после выхода')->exists()
        );
    }

    public function test_chat_page_exposes_store_thread_url_for_contacts(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="chatApp"', $html);
        $this->assertStringContainsString('data-store-thread-url="'.route('chat.api.threads.store').'"', $html);
        $this->assertStringContainsString('id="contactsError"', $html);
        $this->assertStringContainsString('id="contactsModal"', $html);
    }
}
