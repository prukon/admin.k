<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Services\Chat\ChatSupportIdentity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

/**
 * P1: гость / без messages.view / со правом — эндпоинты «Служба поддержки».
 * Ни один вызов не 500 и не пустой бессмысленный 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatSupportIdentityFullAccessFeatureTest extends ChatTestCase
{
    use InteractsWithChatSupportIdentity;

    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function supportEndpoints(int $canonicalId, int $extraId, int $peerId, int $threadId, int $groupId): array
    {
        return [
            ['GET', 'chat.api.users', []],
            ['GET', 'chat.api.users.show', ['user' => $canonicalId]],
            ['GET', 'chat.api.users.show', ['user' => $extraId]],
            ['POST', 'chat.api.threads.store', ['user_id' => $canonicalId]],
            ['POST', 'chat.api.threads.store', ['user_id' => $extraId]],
            ['GET', 'chat.api.threads.show', ['thread' => $threadId]],
            ['GET', 'chat.api.threads.index', []],
            ['GET', 'chat.api.threads.participants.index', ['thread' => $groupId]],
            ['POST', 'chat.api.threads.groups.store', [
                'title' => 'СаГруппа',
                'user_ids' => [$peerId, $canonicalId],
            ]],
            ['POST', 'chat.api.threads.participants.store', [
                'thread' => $groupId,
                'user_ids' => [$extraId],
            ]],
        ];
    }

    public function test_guest_is_redirected_from_support_html_endpoints_and_creates_nothing(): void
    {
        $canonical = $this->makeSupport('ГостьКанон_', 'А');
        $extra = $this->makeSupport('ГостьЛишний_', 'Б');
        $peer = $this->makePeer('ГостьПир_');
        $thread = $this->createThreadForUsers([$this->user->id, $canonical->id]);
        $group = $this->createGroupThreadForUsers([$this->user->id, $peer->id], 'ГостьСа');

        Auth::logout();

        foreach ($this->supportEndpoints(
            (int) $canonical->id,
            (int) $extra->id,
            (int) $peer->id,
            (int) $thread->id,
            (int) $group->id
        ) as [$method, $name, $params]) {
            $response = $this->hit($method, $name, $params);
            $this->assertNotSame(500, $response->getStatusCode(), $name.' гость не 500');
            $this->assertNotSame(200, $response->getStatusCode(), $name.' гость не пустой 200');
            $this->assertTrue(
                $response->isRedirect(),
                $name.' для гостя должен редиректить, статус '.$response->getStatusCode()
            );
        }
        $this->assertGuest();
        $this->assertSame(1, ChatThread::query()->where('is_group', false)->count());
        $this->assertSame(1, ChatThread::query()->where('is_group', true)->count());
    }

    public function test_guest_json_requests_to_support_endpoints_are_unauthorized(): void
    {
        $canonical = $this->makeSupport('ГостьJsonКанон_', 'А');
        $extra = $this->makeSupport('ГостьJsonЛишний_', 'Б');
        $peer = $this->makePeer('ГостьJsonПир_');
        $thread = $this->createThreadForUsers([$this->user->id, $canonical->id]);
        $group = $this->createGroupThreadForUsers([$this->user->id, $peer->id], 'ГостьJsonСа');

        Auth::logout();

        foreach ($this->supportEndpoints(
            (int) $canonical->id,
            (int) $extra->id,
            (int) $peer->id,
            (int) $thread->id,
            (int) $group->id
        ) as [$method, $name, $params]) {
            $response = $this->hitJson($method, $name, $params);
            $this->assertNotSame(500, $response->getStatusCode(), $name.' JSON гость не 500');
            $response->assertUnauthorized();
        }
        $this->assertGuest();
    }

    public function test_user_without_messages_view_gets_403_on_support_endpoints(): void
    {
        $canonical = $this->makeSupport('НетПравКанон_', 'А');
        $extra = $this->makeSupport('НетПравЛишний_', 'Б');
        $peer = $this->makePeer('НетПравПир_');
        $thread = $this->createThreadForUsers([$this->user->id, $canonical->id]);
        $group = $this->createGroupThreadForUsers([$this->user->id, $peer->id], 'НетПравСа');

        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        foreach ($this->supportEndpoints(
            (int) $canonical->id,
            (int) $extra->id,
            (int) $peer->id,
            (int) $thread->id,
            (int) $group->id
        ) as [$method, $name, $params]) {
            $json = $this->hitJson($method, $name, $params);
            $this->assertSame(403, $json->getStatusCode(), $name.' JSON без права должен быть 403');

            $html = $this->hit($method, $name, $params);
            $this->assertNotSame(500, $html->getStatusCode(), $name.' HTML без права не 500');
            $this->assertSame(403, $html->getStatusCode(), $name.' HTML без права должен быть 403');
        }

        $this->assertFalse(
            ChatParticipant::query()->where('user_id', $denied->id)->exists(),
            'Без messages.view нельзя создать диалог со службой поддержки'
        );
    }

    public function test_user_with_messages_view_reaches_support_without_server_error(): void
    {
        $canonical = $this->makeSupport('ЕстьПравКанон_', 'А');
        $extra = $this->makeSupport('ЕстьПравЛишний_', 'Б');
        $peer = $this->makePeer('ЕстьПравПир_');

        $contacts = $this->getJson(route('chat.api.users'))->assertOk();
        $this->assertNotSame('', trim((string) $contacts->getContent()));
        $this->assertNotNull(
            collect($contacts->json())->firstWhere('id', $canonical->id)
        );

        $this->getJson(route('chat.api.users.show', $canonical))
            ->assertOk()
            ->assertJsonPath('full_name', ChatSupportIdentity::DISPLAY_NAME);

        $extraCard = $this->getJson(route('chat.api.users.show', $extra));
        $this->assertNotSame(500, $extraCard->getStatusCode());
        $this->assertNotSame(200, $extraCard->getStatusCode(), 'Лишний superadmin не должен отдавать карточку 200');
        $extraCard->assertForbidden();

        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $extra->id,
        ], $this->chatAjaxHeaders());
        $this->assertContains($created->getStatusCode(), [200, 201]);
        $this->assertSame((int) $canonical->id, (int) $created->json('thread.peer_id'));

        $threadId = (int) $created->json('thread_id');
        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('thread.title', ChatSupportIdentity::DISPLAY_NAME);

        $group = $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'СаДоступ',
            'user_ids' => [$peer->id, $canonical->id],
        ], $this->chatAjaxHeaders());
        $this->assertSame(201, $group->getStatusCode());
        $this->assertGreaterThan(0, (int) $group->json('thread_id'));
    }

    public function test_admin_and_trainer_see_support_in_contacts(): void
    {
        $canonical = $this->makeSupport('РолиКанон_', 'А');

        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $adminRows = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $this->assertNotNull($adminRows->firstWhere('id', $canonical->id));
        $this->assertSame(
            ChatSupportIdentity::DISPLAY_NAME,
            $adminRows->firstWhere('id', $canonical->id)['name']
        );

        $trainer = $this->createUserWithRole('trainer');
        $this->actingInPartner($trainer);
        $trainerRows = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $this->assertNotNull($trainerRows->firstWhere('id', $canonical->id));
    }

    public function test_support_card_wrong_methods_are_not_empty_200(): void
    {
        $canonical = $this->makeSupport('МетодКанон_', 'А');

        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.users.show', $canonical));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' JSON должен быть 405');

            $html = $this->call($method, route('chat.api.users.show', $canonical));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertSame(405, $html->getStatusCode(), $method.' HTML должен быть 405');
        }
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
