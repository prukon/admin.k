<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use Illuminate\Support\Facades\Auth;

/**
 * P1: мобильный чат — HTTP своей карточки и /chat, история before_id, бейдж нижней панели.
 * Серверный 200 без проверки прав, JSON-полей и разметки шапки недостаточен.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMobileFeatureTest extends ChatTestCase
{
    public function test_guest_cannot_open_own_chat_card(): void
    {
        $me = (int) $this->user->id;
        Auth::logout();

        $json = $this->getJson(route('chat.api.users.show', $me));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();

        $html = $this->get(route('chat.api.users.show', $me));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode(), 'Гость не должен получать JSON 200 своей карточки');
        $this->assertTrue($html->isRedirect());
    }

    public function test_user_without_messages_view_cannot_open_own_chat_card(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $this->getJson(route('chat.api.users.show', $denied))->assertForbidden();
        $this->get(route('chat.api.users.show', $denied))->assertForbidden();
    }

    public function test_user_with_messages_view_can_open_own_card_with_same_fields_as_peer(): void
    {
        $this->user->forceFill([
            'lastname' => 'Свой',
            'name' => 'Аккаунт',
            'phone' => '+79001234567',
            'last_seen_at' => now(),
        ])->save();

        $this->getJson(route('chat.api.users.show', $this->user))
            ->assertOk()
            ->assertJsonStructure([
                'id', 'avatar', 'full_name', 'phone', 'parent_full_name', 'parent_phone',
                'is_online', 'last_seen_at', 'last_seen_label', 'team_title',
            ])
            ->assertJsonPath('id', (int) $this->user->id)
            ->assertJsonPath('full_name', 'Свой Аккаунт')
            ->assertJsonPath('phone', '+79001234567')
            ->assertJsonPath('is_online', true)
            ->assertJsonPath('last_seen_label', 'онлайн');
    }

    public function test_native_own_card_get_returns_json_not_empty_page(): void
    {
        $response = $this->get(route('chat.api.users.show', $this->user));
        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('id', (int) $this->user->id);
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_own_card_wrong_methods_are_not_empty_200(): void
    {
        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.users.show', $this->user));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' должен быть 405');

            $html = $this->call($method, route('chat.api.users.show', $this->user));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertSame(405, $html->getStatusCode(), $method.' HTML должен быть 405');
        }
    }

    public function test_foreign_partner_cannot_open_this_schools_user_card(): void
    {
        $this->getJson(route('chat.api.users.show', $this->foreignUser))->assertForbidden();
        $this->get(route('chat.api.users.show', $this->foreignUser))->assertForbidden();
    }

    public function test_guest_is_redirected_from_chat_page_and_json_is_unauthorized(): void
    {
        Auth::logout();

        $html = $this->get(route('chat.index'));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode(), 'Гость не должен видеть /chat');
        $this->assertTrue($html->isRedirect());
        $this->assertStringNotContainsString('id="chatMobileNav"', (string) $html->getContent());

        $json = $this->getJson(route('chat.index'));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();
    }

    public function test_user_without_messages_view_cannot_open_chat_page(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $html = $this->get(route('chat.index'));
        $html->assertForbidden();
        $this->assertStringNotContainsString('id="chatMobileNav"', $html->getContent());

        $this->getJson(route('chat.index'))->assertForbidden();
    }

    public function test_user_with_messages_view_sees_header_controls_and_chat_cannot_be_pinch_zoomed(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-widget="pushmenu"', $html);
        $this->assertStringContainsString('confirm-logout-modal', $html);
        $this->assertStringContainsString('Выйти', $html);
        $this->assertStringContainsString('fa-bell', $html);
        $this->assertStringContainsString('maximum-scale=1, user-scalable=no', $html);
        $this->assertStringContainsString('id="chatMobileNav"', $html);
        $this->assertStringContainsString('id="messagesBox"', $html);
        $this->assertStringContainsString('id="msgInput"', $html);
        $this->assertStringContainsString('id="chatMobileBack"', $html);
    }

    public function test_dashboard_still_allows_pinch_zoom(): void
    {
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('user-scalable=no', $html);
        $this->assertStringNotContainsString('id="chatMobileNav"', $html);
    }

    public function test_chat_page_wrong_methods_are_not_empty_200(): void
    {
        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.index'));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON /chat не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON /chat не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' JSON /chat должен быть 405');

            $html = $this->call($method, route('chat.index'));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML /chat не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML /chat не пустой 200');
            $this->assertSame(405, $html->getStatusCode(), $method.' HTML /chat должен быть 405');
        }
    }

    public function test_history_before_id_zero_returns_field_error_json_and_native_redirect(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, (int) $this->user->id, 'есть история');

        $json = $this->getJson(route('chat.api.threads.messages.index', [
            'thread' => $thread->id,
            'before_id' => 0,
        ]));
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode(), 'Некорректный before_id не пустой 200');
        $json->assertStatus(422)
            ->assertJsonValidationErrors(['before_id'])
            ->assertJsonPath('errors.before_id.0', 'Некорректный идентификатор сообщения.');

        $native = $this->from(route('chat.index'))
            ->get(route('chat.api.threads.messages.index', [
                'thread' => $thread->id,
                'before_id' => 0,
            ]));
        $this->assertNotSame(500, $native->getStatusCode());
        $this->assertNotSame(200, $native->getStatusCode(), 'Нативный GET с before_id=0 не пустой 200');
        $native->assertRedirect(route('chat.index'));
        $native->assertSessionHasErrors('before_id');
    }

    public function test_native_history_get_returns_json_list_not_empty_page(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, (int) $this->user->id, 'страница-1');

        $response = $this->get(route('chat.api.threads.messages.index', $thread->id));
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertSame('страница-1', $response->json('0.body'));
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_history_index_wrong_methods_are_not_empty_200(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $url = route('chat.api.threads.messages.index', $thread->id);

        foreach (['PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, $url);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON history не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON history не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' JSON history должен быть 405');

            $html = $this->call($method, $url);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML history не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML history не пустой 200');
            $this->assertSame(405, $html->getStatusCode(), $method.' HTML history должен быть 405');
        }
    }

    public function test_mobile_nav_unread_badge_is_hidden_when_inbox_is_empty(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $nav = $this->mobileNavHtml($html);

        $this->assertMatchesRegularExpression(
            '/js-chat-private-unread-count"[^>]*style="display:none"/',
            $nav
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-group-unread-count"[^>]*style="display:none"/',
            $nav
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-private-unread-count[^>]*>0</',
            $nav
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-group-unread-count[^>]*>0</',
            $nav
        );
        $this->assertStringNotContainsString('js-chat-unread-count', $nav);
    }

    public function test_mobile_nav_private_badge_shows_when_peer_wrote(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Новое',
        ]);

        $this->actingInPartner($peer);
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $nav = $this->mobileNavHtml($html);

        $this->assertDoesNotMatchRegularExpression(
            '/js-chat-private-unread-count"[^>]*style="display:none"/',
            $nav
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-private-unread-count[^>]*>1</',
            $nav
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-group-unread-count"[^>]*style="display:none"/',
            $nav
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-unread-count[^>]*>1</',
            $html
        );
    }

    public function test_mobile_nav_group_badge_shows_when_group_peer_wrote(): void
    {
        $peer = $this->makePeer();
        $other = $this->makePeer('ChatOther_');
        $thread = $this->createGroupThreadForUsers([
            (int) $this->user->id,
            (int) $peer->id,
            (int) $other->id,
        ], 'Мобильная группа');
        $this->seedMessage($thread, (int) $peer->id, 'В группу');

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $nav = $this->mobileNavHtml($html);

        $this->assertDoesNotMatchRegularExpression(
            '/js-chat-group-unread-count"[^>]*style="display:none"/',
            $nav
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-group-unread-count[^>]*>1</',
            $nav
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-private-unread-count"[^>]*style="display:none"/',
            $nav
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-unread-count[^>]*>1</',
            $html
        );
    }

    private function mobileNavHtml(string $html): string
    {
        $start = strpos($html, 'id="chatMobileNav"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contactsModal"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        return substr($html, $start, $end - $start);
    }
}
