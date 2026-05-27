<?php

namespace Tests\Feature\Crm\Chat;

use App\Models\User;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Participant;
use Cmgmyr\Messenger\Models\Thread;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Чат: страница и API, partner-scope (STRICT_CURRENT) на users/storeThread/addMembers,
 * доступ по messages.view.
 */
final class ChatPartnerScopeFullAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->grantMessagesView($this->user);
    }

    public function test_guest_cannot_access_chat_page_and_api(): void
    {
        Auth::logout();

        $this->get(route('chat.index'))->assertRedirect();

        $this->getJson('/chat/api/users')->assertUnauthorized();
    }

    public function test_user_without_messages_view_gets_403_on_page_and_api(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('chat.index'))->assertForbidden();
        $this->getJson('/chat/api/users')->assertForbidden();
        $this->getJson('/chat/api/threads')->assertForbidden();
    }

    public function test_user_with_messages_view_page_and_all_api_endpoints_return_success(): void
    {
        $actor = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->grantMessagesView($actor);
        $this->actingAs($actor);

        $peer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'name'       => 'ChatPeer_' . uniqid('', true),
        ]);

        $this->get(route('chat.index'))->assertOk();

        $this->getJson('/chat/api/users')->assertOk();
        $this->getJson('/chat/api/threads')->assertOk();

        $create = $this->postJson('/chat/api/threads', [
            'type'    => 'private',
            'members' => [$peer->id],
        ])->assertCreated()->assertJsonStructure(['thread_id']);

        $threadId = (int) $create->json('thread_id');

        $this->getJson("/chat/api/threads/{$threadId}")->assertOk()->assertJsonStructure(['thread', 'messages']);
        $this->getJson("/chat/api/threads/{$threadId}/messages")->assertOk();
        $this->getJson("/chat/api/threads/{$threadId}/members")->assertOk()->assertJsonPath('thread_id', $threadId);

        $this->postJson("/chat/api/threads/{$threadId}/messages", [
            'body' => 'Scope test message',
        ])->assertCreated();

        $this->postJson("/chat/api/threads/{$threadId}/typing", [
            'is_typing' => true,
        ])->assertOk();

        $this->patchJson("/chat/api/threads/{$threadId}/read")->assertOk();
    }

    public function test_chat_users_returns_only_users_of_current_partner(): void
    {
        $local = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'name'       => 'ChatLocalUser_' . uniqid('', true),
        ]);

        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->roleId('user'),
            'name'       => 'ChatForeignUser_' . uniqid('', true),
        ]);

        $ids = collect($this->getJson('/chat/api/users')->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $local->id, $ids);
        $this->assertNotContains((int) $this->foreignUser->id, $ids);
    }

    public function test_superadmin_with_null_partner_id_sees_only_current_partner_users(): void
    {
        $this->asSuperadmin();
        $this->user->partner_id = null;
        $this->user->save();
        $this->actingAs($this->user);
        $this->grantMessagesView($this->user);

        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->roleId('user'),
        ]);

        $ids = collect($this->getJson('/chat/api/users')->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertNotContains((int) $this->foreignUser->id, $ids);
    }

    public function test_store_thread_with_foreign_member_returns_403(): void
    {
        $this->postJson('/chat/api/threads', [
            'type'    => 'private',
            'members' => [$this->foreignUser->id],
        ])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя добавить пользователя другой организации.',
            ]);
    }

    public function test_add_members_with_foreign_user_returns_403(): void
    {
        $peer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
        ]);

        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->postJson("/chat/api/threads/{$thread->id}/members", [
            'members' => [$this->foreignUser->id],
        ])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Нельзя добавить пользователя другой организации.',
            ]);

        $this->assertDatabaseMissing('participants', [
            'thread_id' => $thread->id,
            'user_id'   => $this->foreignUser->id,
        ]);
    }

    public function test_cannot_access_thread_where_user_is_not_participant(): void
    {
        $foreignOnlyThread = $this->createThreadForUsers([
            $this->foreignUser->id,
            User::factory()->create([
                'partner_id' => $this->foreignPartner->id,
                'role_id'    => $this->roleId('user'),
            ])->id,
        ]);

        $this->getJson("/chat/api/threads/{$foreignOnlyThread->id}")->assertForbidden();
        $this->getJson("/chat/api/threads/{$foreignOnlyThread->id}/messages")->assertForbidden();
        $this->postJson("/chat/api/threads/{$foreignOnlyThread->id}/messages", [
            'body' => 'hack',
        ])->assertForbidden();
    }

    public function test_users_search_with_q_filters_within_current_partner(): void
    {
        $unique = 'UniqueLocalChat_' . uniqid('', true);
        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'name'       => $unique,
        ]);
        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->roleId('user'),
            'name'       => $unique . '_foreign',
        ]);

        $names = collect($this->getJson('/chat/api/users', ['q' => $unique])->json())
            ->pluck('name')
            ->all();

        $this->assertTrue(
            collect($names)->contains(fn ($n) => str_contains((string) $n, $unique) && ! str_contains((string) $n, '_foreign'))
        );
        $this->assertFalse(
            collect($names)->contains(fn ($n) => str_contains((string) $n, '_foreign'))
        );
    }

    private function createThreadForUsers(array $userIds, string $subject = 'Test thread'): Thread
    {
        $thread = Thread::create(['subject' => $subject]);

        foreach (array_unique($userIds) as $userId) {
            Participant::create([
                'thread_id' => $thread->id,
                'user_id'   => (int) $userId,
                'last_read' => now(),
            ]);
        }

        Message::create([
            'thread_id' => $thread->id,
            'user_id'   => (int) $userIds[0],
            'body'      => 'seed',
        ]);

        return $thread;
    }

    private function grantMessagesView(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('messages.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

}
