<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Чат 1-на-1: страница и API, partner-scope, дедуп диалога, непрочитанные, ошибки под полями.
 */
final class ChatPartnerScopeFullAccessFeatureTest extends ChatTestCase
{
    public function test_guest_cannot_access_chat_page_and_api(): void
    {
        Auth::logout();

        $this->get(route('chat.index'))->assertRedirect();
        $this->getJson(route('chat.api.users'))->assertUnauthorized();
    }

    public function test_user_without_messages_view_gets_403_on_page_and_api(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('chat.index'))->assertForbidden();
        $this->getJson(route('chat.api.users'))->assertForbidden();
        $this->getJson(route('chat.api.threads.index'))->assertForbidden();
    }

    public function test_user_with_messages_view_page_and_api_endpoints_return_success(): void
    {
        $peer = $this->makePeer();

        $this->get(route('chat.index'))->assertOk();
        $this->getJson(route('chat.api.users'))->assertOk();
        $this->getJson(route('chat.api.threads.index'))->assertOk()->assertJsonStructure(['threads', 'unread_total']);
        $this->getJson(route('chat.api.unread'))->assertOk()->assertJsonStructure(['unread_total']);

        $create = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->assertJsonStructure(['thread_id', 'thread']);

        $threadId = (int) $create->json('thread_id');

        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonStructure(['thread', 'messages']);
        $this->getJson(route('chat.api.threads.messages.index', $threadId))->assertOk();

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Scope test message',
        ])->assertCreated();

        $this->patchJson(route('chat.api.threads.read', $threadId))->assertOk();
    }

    public function test_chat_users_returns_only_users_of_current_partner(): void
    {
        $local = $this->makePeer('ChatLocalUser_');

        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->roleId('user'),
            'name' => 'ChatForeignUser_'.uniqid('', true),
        ]);

        $ids = collect($this->getJson(route('chat.api.users'))->json())
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

        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->roleId('user'),
        ]);

        $ids = collect($this->getJson(route('chat.api.users'))->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertNotContains((int) $this->foreignUser->id, $ids);
    }

    public function test_store_thread_with_foreign_member_returns_422_under_user_id(): void
    {
        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $this->foreignUser->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id'])
            ->assertJsonPath('errors.user_id.0', 'Нельзя добавить пользователя другой организации.');
    }

    public function test_store_thread_with_self_returns_422_under_user_id(): void
    {
        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $this->user->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_empty_message_returns_422_under_body(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => '   ',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_private_thread_is_reused_for_same_pair(): void
    {
        $peer = $this->makePeer();

        $first = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        $second = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertOk();

        $this->assertSame($first, (int) $second->json('thread_id'));
        $this->assertFalse((bool) $second->json('created'));
        $this->assertSame(1, ChatThread::query()->count());
    }

    public function test_cannot_access_thread_where_user_is_not_participant(): void
    {
        $foreignOnlyThread = $this->createThreadForUsers([
            $this->foreignUser->id,
            User::factory()->create([
                'partner_id' => $this->foreignPartner->id,
                'role_id' => $this->roleId('user'),
            ])->id,
        ]);

        $this->getJson(route('chat.api.threads.show', $foreignOnlyThread->id))->assertForbidden();
        $this->getJson(route('chat.api.threads.messages.index', $foreignOnlyThread->id))->assertForbidden();
        $this->postJson(route('chat.api.threads.messages.store', $foreignOnlyThread->id), [
            'body' => 'hack',
        ])->assertForbidden();
        $this->patchJson(route('chat.api.threads.read', $foreignOnlyThread->id))->assertForbidden();
    }

    public function test_users_search_with_q_filters_within_current_partner(): void
    {
        $unique = 'UniqueLocalChat_'.uniqid('', true);
        User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'name' => $unique,
        ]);
        User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->roleId('user'),
            'name' => $unique.'_foreign',
        ]);

        $names = collect($this->getJson(route('chat.api.users'), ['q' => $unique])->json())
            ->pluck('name')
            ->all();

        $this->assertTrue(
            collect($names)->contains(fn ($n) => str_contains((string) $n, $unique) && ! str_contains((string) $n, '_foreign'))
        );
        $this->assertFalse(
            collect($names)->contains(fn ($n) => str_contains((string) $n, '_foreign'))
        );
    }

    public function test_message_creates_unread_for_peer_and_mark_read_clears_it(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Hello peer',
        ])->assertCreated();

        $this->actingAs($peer)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1);

        $list = $this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads');
        $row = collect($list)->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row['unread_count']);
        $this->assertSame((int) $this->user->id, (int) $row['peer_id']);

        $this->patchJson(route('chat.api.threads.read', $threadId))->assertOk()->assertJsonPath('unread_total', 0);
        $this->getJson(route('chat.api.unread'))->assertJsonPath('unread_total', 0);
    }

    public function test_chat_page_contains_contacts_modal_and_field_error_slots(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="contactsModal"', $html);
        $this->assertStringContainsString('id="msgBodyError"', $html);
        $this->assertStringContainsString('id="contactsError"', $html);
        $this->assertStringContainsString('js/chat.js', $html);
        $this->assertStringNotContainsString('Создать группу', $html);
    }

    public function test_sidebar_shows_messages_item_and_unread_badge(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Ping',
        ]);

        $this->actingAs($peer)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('js-chat-unread-count', $html);
        $this->assertStringContainsString('Чат', $html);
        $this->assertStringContainsString('Контакты', $html);
    }
}
