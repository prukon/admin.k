<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

/**
 * P1: гость / без права / со правом — каждый endpoint чата, без 500 и пустого 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatAccessFeatureTest extends ChatTestCase
{
    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function protectedEndpoints(int $threadId, int $peerId): array
    {
        return [
            ['GET', 'chat.index', []],
            ['GET', 'chat.api.threads.index', []],
            ['POST', 'chat.api.threads.store', ['user_id' => 1]],
            ['POST', 'chat.api.threads.groups.store', ['title' => 'Группа', 'user_ids' => [1, 2]]],
            ['GET', 'chat.api.unread', []],
            ['GET', 'chat.api.users', []],
            ['GET', 'chat.api.users.show', ['user' => $peerId]],
            ['GET', 'chat.api.threads.show', ['thread' => $threadId]],
            ['GET', 'chat.api.threads.messages.index', ['thread' => $threadId]],
            ['POST', 'chat.api.threads.messages.store', ['thread' => $threadId, 'body' => 'x']],
            ['PATCH', 'chat.api.threads.read', ['thread' => $threadId]],
            ['PATCH', 'chat.api.threads.draft', ['thread' => $threadId, 'body' => 'черновик']],
            ['GET', 'chat.api.threads.participants.index', ['thread' => $threadId]],
            ['POST', 'chat.api.threads.participants.store', ['thread' => $threadId, 'user_ids' => [1]]],
            ['DELETE', 'chat.api.threads.participants.destroy', ['thread' => $threadId, 'user' => $peerId]],
        ];
    }

    public function test_guest_is_redirected_from_every_html_chat_endpoint(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        Auth::logout();

        foreach ($this->protectedEndpoints((int) $thread->id, (int) $peer->id) as [$method, $name, $params]) {
            $response = $this->hit($method, $name, $params);
            $this->assertNotSame(500, $response->getStatusCode(), $name.' не должен отдавать 500 гостю');
            $this->assertTrue(
                $response->isRedirect(),
                $name.' для гостя должен редиректить на логин, статус '.$response->getStatusCode()
            );
        }
        $this->assertGuest();
    }

    public function test_guest_json_requests_are_unauthorized_on_every_api_endpoint(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        Auth::logout();

        foreach ($this->protectedEndpoints((int) $thread->id, (int) $peer->id) as [$method, $name, $params]) {
            if ($name === 'chat.index') {
                continue;
            }
            $response = $this->hitJson($method, $name, $params);
            $this->assertNotSame(500, $response->getStatusCode(), $name.' JSON гость не 500');
            $response->assertUnauthorized();
        }
    }

    public function test_user_without_messages_view_gets_403_on_every_endpoint(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        foreach ($this->protectedEndpoints((int) $thread->id, (int) $peer->id) as [$method, $name, $params]) {
            $response = $this->hitJson($method, $name, $params);
            $this->assertSame(
                403,
                $response->getStatusCode(),
                $name.' без messages.view должен быть 403, получено '.$response->getStatusCode()
            );
        }
    }

    public function test_user_with_messages_view_reaches_every_endpoint_without_server_error(): void
    {
        $peer = $this->makePeer();

        $this->get(route('chat.index'))->assertOk()->assertSee('id="chatApp"', false);
        $this->getJson(route('chat.api.users'))->assertOk();
        $this->getJson(route('chat.api.users.show', $peer))->assertOk();
        $this->getJson(route('chat.api.threads.index'))->assertOk();
        $this->getJson(route('chat.api.unread'))->assertOk();

        $create = $this->postJson(route('chat.api.threads.store'), ['user_id' => $peer->id]);
        $this->assertContains($create->getStatusCode(), [200, 201]);
        $this->assertNotSame(500, $create->getStatusCode());
        $threadId = (int) $create->json('thread_id');
        $this->assertGreaterThan(0, $threadId);

        $this->getJson(route('chat.api.threads.show', $threadId))->assertOk();
        $this->getJson(route('chat.api.threads.messages.index', $threadId))->assertOk();

        $send = $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Access ping',
        ]);
        $this->assertSame(201, $send->getStatusCode());
        $this->assertNotSame('', trim((string) $send->getContent()));

        $this->patchJson(route('chat.api.threads.read', $threadId))->assertOk();
        $this->patchJson(route('chat.api.threads.draft', $threadId), [
            'body' => 'Access draft',
        ])->assertOk()->assertJsonPath('ok', true);

        $second = $this->makePeer('AccessGroup_');
        $group = $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'AccessGroup',
            'user_ids' => [$peer->id, $second->id],
        ]);
        $this->assertSame(201, $group->getStatusCode());
        $this->assertNotSame('', trim((string) $group->getContent()));
        $this->assertGreaterThan(0, (int) $group->json('thread_id'));

        $groupId = (int) $group->json('thread_id');
        $this->getJson(route('chat.api.threads.participants.index', $groupId))
            ->assertOk()
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('can_manage', false);

        $toAdd = $this->makePeer('AccessAdd_');
        $studentAdd = $this->postJson(route('chat.api.threads.participants.store', $groupId), [
            'user_ids' => [$toAdd->id],
        ]);
        $this->assertNotSame(500, $studentAdd->getStatusCode());
        $studentAdd->assertForbidden();

        $studentKick = $this->deleteJson(route('chat.api.threads.participants.destroy', [$groupId, $peer]));
        $this->assertNotSame(500, $studentKick->getStatusCode());
        $studentKick->assertForbidden();
    }

    public function test_guest_cannot_read_reverb_status(): void
    {
        Auth::logout();
        $this->getJson(route('chat.api.reverb-status'))->assertUnauthorized();
    }

    public function test_guest_html_request_to_reverb_status_is_redirected_to_login(): void
    {
        Auth::logout();

        $response = $this->get(route('chat.api.reverb-status'));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
    }

    public function test_user_without_messages_view_gets_403_on_html_chat_page(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $this->get(route('chat.index'))->assertForbidden();
    }

    public function test_participant_can_subscribe_to_own_inbox_and_thread_but_not_to_foreign_ones(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);

        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $foreignThread = $this->createThreadForUsers([
            $this->foreignUser->id,
            $this->makePeer('ForeignPeer_', ['partner_id' => $this->foreignPartner->id])->id,
        ]);

        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-inbox.'.$this->user->id,
            'socket_id' => '1.1',
        ])->assertOk();

        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-thread.'.$thread->id,
            'socket_id' => '1.2',
        ])->assertOk();

        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-inbox.'.$peer->id,
            'socket_id' => '1.3',
        ])->assertForbidden();

        $this->post('/broadcasting/auth', [
            'channel_name' => 'private-thread.'.$foreignThread->id,
            'socket_id' => '1.4',
        ])->assertForbidden();
    }

    public function test_regular_user_cannot_read_reverb_status(): void
    {
        $this->getJson(route('chat.api.reverb-status'))->assertForbidden();
    }

    public function test_superadmin_reverb_status_returns_listening_flag(): void
    {
        $superadmin = $this->createUserWithRole('superadmin');
        $this->actingInPartner($superadmin);
        config(['broadcasting.connections.reverb.options.host' => '127.0.0.1']);
        config(['broadcasting.connections.reverb.options.port' => 1]);
        config(['broadcasting.default' => 'reverb']);

        $this->getJson(route('chat.api.reverb-status'))
            ->assertOk()
            ->assertJsonPath('listening', false)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('host', '127.0.0.1')
            ->assertJsonPath('port', 1)
            ->assertJsonPath('driver', 'reverb');
    }

    public function test_admin_and_trainer_with_base_permission_can_open_chat_page(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $this->get(route('chat.index'))->assertOk();

        $trainer = $this->createUserWithRole('trainer');
        $this->actingInPartner($trainer);
        $this->get(route('chat.index'))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function hit(string $method, string $name, array $params): TestResponse
    {
        $url = route($name, $params);
        $payload = $params;
        unset($payload['thread'], $payload['user']);

        return match (strtoupper($method)) {
            'GET' => $this->get($url),
            'POST' => $this->post($url, $payload),
            'PATCH' => $this->patch($url, $payload),
            default => $this->call($method, $url, $payload),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function hitJson(string $method, string $name, array $params): TestResponse
    {
        $url = route($name, $params);
        $payload = $params;
        unset($payload['thread'], $payload['user']);

        return match (strtoupper($method)) {
            'GET' => $this->getJson($url),
            'POST' => $this->postJson($url, $payload),
            'PATCH' => $this->patchJson($url, $payload),
            default => $this->json($method, $url, $payload),
        };
    }
}
