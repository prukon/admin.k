<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Services\Chat\ChatService;
use Illuminate\Support\Facades\Auth;

/**
 * Сплит мобильного inbox: «Личные» vs «Чаты», бейджи, смешанный API.
 *
 * UX-баг до фикса: группы жили во вкладке «Личные», «Чаты» была заглушкой,
 * оба бейджа нижней панели красили одну сумму, API мог начать фильтровать
 * is_group на сервере. Сплит — клиентский; GET threads остаётся смешанным.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMobileInboxSplitFeatureTest extends ChatTestCase
{
    public function test_guest_redirect_does_not_leak_split_inbox_markup(): void
    {
        Auth::logout();

        $page = $this->get(route('chat.index'));
        $this->assertNotSame(500, $page->getStatusCode());
        $this->assertNotSame(200, $page->getStatusCode(), 'Гость не должен видеть /chat');
        $this->assertTrue($page->isRedirect());
        $html = (string) $page->getContent();
        $this->assertStringNotContainsString('id="groupThreads"', $html);
        $this->assertStringNotContainsString('js-chat-private-unread-count', $html);
        $this->assertStringNotContainsString('js-chat-group-unread-count', $html);
        $this->assertStringNotContainsString('chat-groups-stub', $html);

        $json = $this->getJson(route('chat.api.threads.index'));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();

        $unread = $this->getJson(route('chat.api.unread'));
        $this->assertNotSame(500, $unread->getStatusCode());
        $unread->assertUnauthorized();
    }

    public function test_user_without_messages_view_does_not_see_split_inbox(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $page = $this->get(route('chat.index'));
        $this->assertSame(403, $page->getStatusCode());
        $this->assertStringNotContainsString('id="groupThreads"', $page->getContent());
        $this->assertStringNotContainsString('js-chat-private-unread-count', $page->getContent());

        $this->getJson(route('chat.api.threads.index'))->assertForbidden();
        $this->getJson(route('chat.api.unread'))->assertForbidden();
        $this->get(route('chat.api.threads.index'))->assertForbidden();
        $this->get(route('chat.api.unread'))->assertForbidden();
    }

    public function test_user_with_permission_sees_groups_list_without_search_and_split_badges(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="groupThreads"', $html);
        $this->assertStringNotContainsString('chat-groups-stub', $html);
        $this->assertStringContainsString('id="openCreateGroupMobileBtn"', $html);
        $this->assertStringContainsString('id="openCreateGroupBtn"', $html);

        $groupsPane = $this->paneHtml($html, 'id="chatPaneGroups"', 'id="chatPaneAccount"');
        $this->assertStringContainsString('id="groupThreads"', $groupsPane);
        $this->assertStringContainsString('id="openCreateGroupMobileBtn"', $groupsPane);
        $this->assertStringContainsString('js-open-create-group', $groupsPane);
        $this->assertStringNotContainsString('id="threadSearch"', $groupsPane);
        $this->assertStringNotContainsString('placeholder="Поиск"', $groupsPane);
        $this->assertStringNotContainsString('chat-groups-stub', $groupsPane);

        $nav = $this->mobileNavHtml($html);
        $this->assertStringContainsString('js-chat-private-unread-count', $nav);
        $this->assertStringContainsString('js-chat-group-unread-count', $nav);
        $this->assertStringContainsString('id="chatPrivateUnreadBadge"', $nav);
        $this->assertStringContainsString('id="chatGroupUnreadBadge"', $nav);
        $this->assertStringNotContainsString('js-chat-unread-count', $nav);

        $this->assertStringContainsString('js-chat-unread-count', $html);
        $this->assertSame(2, substr_count($html, 'js-open-create-group'));
    }

    public function test_admin_and_trainer_with_messages_view_also_see_split_inbox_markup(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $adminHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="groupThreads"', $adminHtml);
        $this->assertStringContainsString('js-chat-private-unread-count', $adminHtml);
        $this->assertStringContainsString('js-chat-group-unread-count', $adminHtml);

        $trainer = $this->createUserWithRole('trainer');
        $this->actingInPartner($trainer);
        $trainerHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="groupThreads"', $trainerHtml);
        $this->assertStringNotContainsString('chat-groups-stub', $trainerHtml);
    }

    public function test_mixed_unread_paints_private_and_group_badges_separately_not_the_same_total(): void
    {
        $this->seedMixedUnreadInbox(2, 3);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $nav = $this->mobileNavHtml($html);

        $this->assertDoesNotMatchRegularExpression(
            '/js-chat-private-unread-count"[^>]*style="display:none"/',
            $nav
        );
        $this->assertDoesNotMatchRegularExpression(
            '/js-chat-group-unread-count"[^>]*style="display:none"/',
            $nav
        );
        $this->assertMatchesRegularExpression('/js-chat-private-unread-count[^>]*>2</', $nav);
        $this->assertMatchesRegularExpression('/js-chat-group-unread-count[^>]*>3</', $nav);
        $this->assertMatchesRegularExpression('/js-chat-unread-count[^>]*>5</', $html);
        $this->assertStringNotContainsString('js-chat-private-unread-count">5<', $nav);
        $this->assertStringNotContainsString('js-chat-group-unread-count">5<', $nav);
    }

    public function test_inbox_json_stays_mixed_and_does_not_expose_split_unread_keys(): void
    {
        $seeded = $this->seedMixedUnreadInbox(2, 3);

        $payload = $this->getJson(route('chat.api.threads.index'))
            ->assertOk()
            ->assertJsonPath('unread_total', 5)
            ->json();
        $this->assertArrayHasKey('threads', $payload);
        $this->assertArrayNotHasKey('unread_private', $payload);
        $this->assertArrayNotHasKey('unread_group', $payload);

        $threads = collect($payload['threads']);
        $private = $threads->firstWhere('id', $seeded['private']->id);
        $group = $threads->firstWhere('id', $seeded['group']->id);
        $this->assertNotNull($private);
        $this->assertNotNull($group);
        $this->assertFalse((bool) ($private['is_group'] ?? false));
        $this->assertTrue((bool) ($group['is_group'] ?? false));
        $this->assertSame(2, (int) ($private['unread_count'] ?? 0));
        $this->assertSame(3, (int) ($group['unread_count'] ?? 0));

        $filtered = $this->getJson(route('chat.api.threads.index', ['is_group' => 1]))
            ->assertOk()
            ->json('threads');
        $ids = collect($filtered)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $seeded['private']->id, $ids, 'Клиент фильтрует is_group, сервер не должен выкидывать личку');
        $this->assertContains((int) $seeded['group']->id, $ids);

        $unread = $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 5)
            ->json();
        $this->assertSame(['unread_total'], array_keys($unread));
    }

    public function test_native_inbox_and_unread_get_return_json_not_empty_page(): void
    {
        $this->seedMixedUnreadInbox(1, 1);

        foreach ([route('chat.api.threads.index'), route('chat.api.unread')] as $url) {
            $html = $this->get($url);
            $this->assertSame(200, $html->getStatusCode(), $url.' нативный GET не 500/пустой 200');
            $this->assertStringContainsString('application/json', (string) $html->headers->get('Content-Type'));
            $this->assertStringNotContainsString('<html', $html->getContent());
            $this->assertNotSame('', trim((string) $html->getContent()));
            $decoded = json_decode((string) $html->getContent(), true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('unread_total', $decoded);
            $this->assertArrayNotHasKey('unread_private', $decoded);
        }
    }

    public function test_wrong_methods_on_inbox_and_unread_are_not_empty_200(): void
    {
        foreach (['PATCH', 'DELETE', 'PUT'] as $method) {
            $url = route('chat.api.threads.index');
            $json = $this->json($method, $url);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON inbox не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON inbox не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' JSON inbox должен быть 405');

            $html = $this->call($method, $url);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML inbox не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML inbox не пустой 200');
            $this->assertSame(405, $html->getStatusCode(), $method.' HTML inbox должен быть 405');
        }

        foreach (['POST', 'PATCH', 'DELETE', 'PUT'] as $method) {
            $url = route('chat.api.unread');
            $json = $this->json($method, $url);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON unread не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON unread не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' JSON unread должен быть 405');

            $html = $this->call($method, $url);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML unread не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML unread не пустой 200');
            $this->assertSame(405, $html->getStatusCode(), $method.' HTML unread должен быть 405');
        }
    }

    public function test_creating_group_over_ajax_keeps_private_dialog_in_the_same_inbox_payload(): void
    {
        $peer = $this->makePeer();
        $other = $this->makePeer('SplitOther_');
        $privateId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        $group = $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Сборная сплита',
            'user_ids' => [$peer->id, $other->id],
        ]);
        $this->assertSame(201, $group->getStatusCode());
        $group->assertJsonPath('thread.is_group', true);
        $group->assertJsonPath('thread.peer_id', null);
        $groupId = (int) $group->json('thread_id');
        $this->assertGreaterThan(0, $groupId);

        $threads = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertFalse((bool) ($threads->firstWhere('id', $privateId)['is_group'] ?? true));
        $this->assertTrue((bool) ($threads->firstWhere('id', $groupId)['is_group'] ?? false));
    }

    public function test_leftover_dialog_stays_in_inbox_as_personal_not_group(): void
    {
        $peer = $this->makePeer('SplitLeft_');
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'останется',
        ])->assertCreated();

        ChatParticipant::query()
            ->where('thread_id', $threadId)
            ->where('user_id', $peer->id)
            ->delete();

        $row = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertFalse((bool) ($row['is_group'] ?? false));
        $this->assertNull($row['peer_id']);
        $this->assertSame('Диалог', $row['title']);
    }

    public function test_left_group_does_not_inflate_chats_badge(): void
    {
        $seeded = $this->seedMixedUnreadInbox(2, 4);

        ChatParticipant::query()
            ->where('thread_id', $seeded['group']->id)
            ->where('user_id', $this->user->id)
            ->delete();

        $this->assertSame(2, app(ChatService::class)->unreadPrivateTotal((int) $this->user->id));
        $this->assertSame(0, app(ChatService::class)->unreadGroupTotal((int) $this->user->id));
        $this->assertSame(2, app(ChatService::class)->unreadTotal((int) $this->user->id));

        $ids = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertNotContains((int) $seeded['group']->id, $ids);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $nav = $this->mobileNavHtml($html);
        $this->assertMatchesRegularExpression('/js-chat-private-unread-count[^>]*>2</', $nav);
        $this->assertMatchesRegularExpression('/js-chat-group-unread-count"[^>]*style="display:none"/', $nav);
        $this->assertMatchesRegularExpression('/js-chat-unread-count[^>]*>2</', $html);
    }

    public function test_foreign_school_does_not_see_this_schools_group_unread_on_split_badges(): void
    {
        $this->seedMixedUnreadInbox(2, 3);

        $this->grantPermission($this->foreignUser, 'messages.view', (int) $this->foreignUser->partner_id);
        $this->asForeignUser();

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertTrue($list->isEmpty());
        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 0);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $nav = $this->mobileNavHtml($html);
        $this->assertMatchesRegularExpression('/js-chat-private-unread-count"[^>]*style="display:none"/', $nav);
        $this->assertMatchesRegularExpression('/js-chat-group-unread-count"[^>]*style="display:none"/', $nav);
        $this->assertMatchesRegularExpression('/js-chat-private-unread-count[^>]*>0</', $nav);
        $this->assertMatchesRegularExpression('/js-chat-group-unread-count[^>]*>0</', $nav);
    }

    /**
     * @return array{private: ChatThread, group: ChatThread}
     */
    private function seedMixedUnreadInbox(int $privateUnread, int $groupUnread): array
    {
        $peer = $this->makePeer();
        $other = $this->makePeer('SplitGrp_');
        $private = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $group = $this->createGroupThreadForUsers([
            (int) $this->user->id,
            (int) $peer->id,
            (int) $other->id,
        ], 'Сборная сплита');

        ChatParticipant::query()
            ->where('thread_id', $private->id)
            ->where('user_id', $this->user->id)
            ->update(['unread_count' => $privateUnread]);
        ChatParticipant::query()
            ->where('thread_id', $group->id)
            ->where('user_id', $this->user->id)
            ->update(['unread_count' => $groupUnread]);

        return ['private' => $private, 'group' => $group];
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

    private function paneHtml(string $html, string $startNeedle, string $endNeedle): string
    {
        $start = strpos($html, $startNeedle);
        $this->assertNotFalse($start);
        $end = strpos($html, $endNeedle);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        return substr($html, $start, $end - $start);
    }
}
