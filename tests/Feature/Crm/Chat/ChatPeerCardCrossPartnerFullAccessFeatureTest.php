<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;

/**
 * P1: карточка человека из шапки / состава группы — гость, без права, своя школа, чужая школа.
 *
 * UX-баг до фикса: superadmin после смены школы кликал имя в открытом диалоге
 * и получал 403 «This action is unauthorized.» — inbox не ограничен current_partner.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatPeerCardCrossPartnerFullAccessFeatureTest extends ChatTestCase
{
    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function peerCardReads(int $peerId): array
    {
        return [
            ['GET', 'chat.api.users.show', ['user' => $peerId]],
            ['GET', 'chat.index', []],
        ];
    }

    public function test_guest_is_redirected_from_peer_card_and_chat_page(): void
    {
        $peer = $this->makePeer('ГостьКарточка_');
        Auth::logout();

        foreach ($this->peerCardReads((int) $peer->id) as [$method, $name, $params]) {
            $response = $this->hit($method, $name, $params);
            $this->assertNotSame(500, $response->getStatusCode(), $name.' гость не 500');
            $this->assertNotSame(200, $response->getStatusCode(), $name.' гость не пустой 200');
            $this->assertTrue(
                $response->isRedirect(),
                $name.' для гостя должен редиректить, статус '.$response->getStatusCode()
            );
        }
        $this->assertGuest();
    }

    public function test_guest_json_peer_card_is_unauthorized_not_empty_200(): void
    {
        $peer = $this->makePeer('ГостьJsonКарточка_');
        Auth::logout();

        $response = $this->getJson(route('chat.api.users.show', $peer));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'JSON гость не 200');
        $response->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_guest_mutations_on_peer_card_are_not_empty_200(): void
    {
        $peer = $this->makePeer('ГостьМутация_');
        Auth::logout();

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.users.show', $peer));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON гость не 200');
            $this->assertContains(
                $json->getStatusCode(),
                [401, 405],
                $method.' JSON гость: 401 или 405, статус '.$json->getStatusCode()
            );

            $html = $this->call($method, route('chat.api.users.show', $peer));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML гость не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML гость не 200');
            $this->assertTrue(
                $html->isRedirect() || in_array($html->getStatusCode(), [401, 405], true),
                $method.' HTML гость: редирект/401/405, статус '.$html->getStatusCode()
            );
        }
        $this->assertGuest();
    }

    public function test_user_without_messages_view_cannot_open_peer_card_or_chat_page(): void
    {
        $peer = $this->makePeer('НетПравКарточка_');
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        foreach ($this->peerCardReads((int) $peer->id) as [$method, $name, $params]) {
            $json = $this->hitJson($method, $name, $params);
            $this->assertNotSame(500, $json->getStatusCode(), $name.' JSON без права не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $name.' JSON без права не 200');
            $this->assertSame(403, $json->getStatusCode(), $name.' JSON без права должен быть 403');

            $html = $this->hit($method, $name, $params);
            $this->assertNotSame(500, $html->getStatusCode(), $name.' HTML без права не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $name.' HTML без права не 200');
            $this->assertSame(403, $html->getStatusCode(), $name.' HTML без права должен быть 403');
        }

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.users.show', $peer));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON без права не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON без права не 200');
            $this->assertContains($json->getStatusCode(), [403, 405], $method.' JSON без права');

            $html = $this->call($method, route('chat.api.users.show', $peer));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML без права не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML без права не 200');
            $this->assertContains($html->getStatusCode(), [403, 405], $method.' HTML без права');
        }
    }

    public function test_manager_with_permission_opens_same_school_card(): void
    {
        $peer = $this->makePeer('СвояШкола_');

        $this->getJson(route('chat.api.users.show', $peer), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('id', (int) $peer->id)
            ->assertJsonPath('partner_name', $this->partner->title);
        $this->assertStringContainsString(
            'application/json',
            (string) $this->get(route('chat.api.users.show', $peer))->headers->get('content-type')
        );

        $page = $this->get(route('chat.index'))->assertOk();
        $html = $page->getContent();
        $this->assertStringContainsString('id="peerCardModal"', $html);
        $this->assertStringContainsString('id="peerCardError"', $html);
        $this->assertStringContainsString('id="threadPeerHit"', $html);
        $this->assertStringContainsString('chat-header-peer is-idle', $html);
    }

    public function test_admin_and_trainer_open_same_school_card_but_not_foreign_even_with_shared_thread(): void
    {
        $local = $this->makePeer('РолиЛокал_');

        foreach (['admin', 'trainer'] as $role) {
            $actor = $this->createUserWithRole($role);
            $this->actingInPartner($actor);
            $this->createThreadForUsers([$actor->id, $this->foreignUser->id], 'Mixed'.$role);

            $this->getJson(route('chat.api.users.show', $local), $this->ajaxHeaders())
                ->assertOk()
                ->assertJsonPath('id', (int) $local->id);

            $this->assertChatPeerCardForbidden(
                $this->getJson(route('chat.api.users.show', $this->foreignUser), $this->ajaxHeaders())
            );
            $this->assertChatPeerCardForbidden(
                $this->get(route('chat.api.users.show', $this->foreignUser))
            );
        }
    }

    public function test_student_with_shared_foreign_thread_still_cannot_open_that_card(): void
    {
        $this->createThreadForUsers([$this->user->id, $this->foreignUser->id], 'StudentMixed');
        $this->createGroupThreadForUsers(
            [$this->user->id, $this->makePeer('LocalMix_')->id, $this->foreignUser->id],
            'StudentMixedGroup'
        );

        $this->assertChatPeerCardForbidden(
            $this->getJson(route('chat.api.users.show', $this->foreignUser), $this->ajaxHeaders())
        );
    }

    public function test_superadmin_after_switching_school_opens_other_school_card_only_with_live_thread(): void
    {
        $homePeer = $this->makePeer('SaHomePeer_');
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => (int) $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $this->assertChatPeerCardForbidden(
            $this->getJson(route('chat.api.users.show', $homePeer), $this->ajaxHeaders())
        );

        $this->createThreadForUsers([$this->user->id, $homePeer->id], 'SaSwitchPrivate');

        $this->getJson(route('chat.api.users.show', $homePeer), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('id', (int) $homePeer->id)
            ->assertJsonPath('partner_name', $this->foreignPartner->title);
    }

    public function test_superadmin_opens_foreign_group_member_card_when_they_share_a_live_group(): void
    {
        $local = $this->makePeer('SaGroupLocal_');
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => (int) $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->createGroupThreadForUsers(
            [$this->user->id, $local->id, $this->foreignUser->id],
            'SaSwitchGroup'
        );

        $this->getJson(route('chat.api.users.show', $this->foreignUser), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('id', (int) $this->foreignUser->id)
            ->assertJsonPath('partner_name', $this->partner->title);
    }

    public function test_superadmin_cannot_open_foreign_card_after_thread_is_trashed(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => (int) $this->partner->id,
            '2fa:passed' => true,
        ]);
        $thread = $this->createThreadForUsers([$this->user->id, $this->foreignUser->id], 'SaTrashed');
        $thread->delete();

        $this->assertChatPeerCardForbidden(
            $this->getJson(route('chat.api.users.show', $this->foreignUser), $this->ajaxHeaders())
        );
        $this->assertTrue(ChatThread::withTrashed()->whereKey($thread->id)->exists());
    }

    public function test_superadmin_cannot_open_foreign_card_after_peer_leaves(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => (int) $this->partner->id,
            '2fa:passed' => true,
        ]);
        $thread = $this->createThreadForUsers([$this->user->id, $this->foreignUser->id], 'SaPeerLeft');
        ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $this->foreignUser->id)
            ->delete();

        $this->assertChatPeerCardForbidden(
            $this->getJson(route('chat.api.users.show', $this->foreignUser), $this->ajaxHeaders())
        );
    }

    public function test_regular_user_cannot_spoof_session_partner_to_open_foreign_card(): void
    {
        $this->createThreadForUsers([$this->user->id, $this->foreignUser->id], 'SpoofMixed');
        $this->withSession([
            'current_partner' => (int) $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $this->assertChatPeerCardForbidden(
            $this->getJson(route('chat.api.users.show', $this->foreignUser), $this->ajaxHeaders())
        );
    }

    public function test_missing_user_card_is_404_not_empty_200(): void
    {
        $json = $this->getJson(route('chat.api.users.show', 9_999_999), $this->ajaxHeaders());
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode(), 'Нет пользователя — не пустой 200');
        $json->assertNotFound();

        $html = $this->get(route('chat.api.users.show', 9_999_999));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $html->assertNotFound();
    }

    public function test_wrong_methods_on_peer_card_are_not_empty_200(): void
    {
        $peer = $this->makePeer('МетодКарточка_');

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.users.show', $peer), [], $this->ajaxHeaders());
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' JSON должен быть 405');

            $html = $this->call($method, route('chat.api.users.show', $peer));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertSame(405, $html->getStatusCode(), $method.' HTML должен быть 405');
        }
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function hit(string $method, string $name, array $params): TestResponse
    {
        $url = route($name, $params);
        $payload = $params;
        unset($payload['thread'], $payload['user'], $payload['message']);

        return match (strtoupper($method)) {
            'GET' => $this->get($url),
            'POST' => $this->post($url, $payload),
            'PATCH' => $this->patch($url, $payload),
            'PUT' => $this->put($url, $payload),
            'DELETE' => $this->delete($url, $payload),
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
        unset($payload['thread'], $payload['user'], $payload['message']);

        return match (strtoupper($method)) {
            'GET' => $this->getJson($url),
            'POST' => $this->postJson($url, $payload),
            'PATCH' => $this->patchJson($url, $payload),
            'PUT' => $this->putJson($url, $payload),
            'DELETE' => $this->deleteJson($url, $payload),
            default => $this->json($method, $url, $payload),
        };
    }
}
