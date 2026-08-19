<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Services\Chat\ChatService;
use Illuminate\Support\Facades\Auth;

/**
 * P1: подзаголовок шапки открытого диалога — число участников у группы,
 * «онлайн» / «был(а) в сети…» у лички. Не в списке слева.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatHeaderSubtitleFeatureTest extends ChatTestCase
{
    public function test_guest_cannot_open_dialog_header_payload(): void
    {
        $peer = $this->makePeer('HdrGuest_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        Auth::logout();

        $json = $this->getJson(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();

        $html = $this->get(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode(), 'Гость не должен получать JSON 200 шапки диалога');
        $this->assertTrue($html->isRedirect());
        $this->assertGuest();
    }

    public function test_user_without_messages_view_cannot_open_dialog_header_payload(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $peer = $this->makePeer('HdrDenied_');
        $thread = $this->createThreadForUsers([$denied->id, $peer->id]);
        $this->actingInPartner($denied);

        $this->getJson(route('chat.api.threads.show', $thread))->assertForbidden();
        $this->get(route('chat.api.threads.show', $thread))->assertForbidden();
    }

    public function test_outsider_and_foreign_school_cannot_read_header_subtitle(): void
    {
        $peer = $this->makePeer('HdrPeer_');
        $outsider = $this->makePeer('HdrOut_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->actingInPartner($outsider);
        $this->getJson(route('chat.api.threads.show', $thread))->assertForbidden();

        $foreignThread = $this->createThreadForUsers([
            $this->foreignUser->id,
            $this->makePeer('HdrForeign_', ['partner_id' => $this->foreignPartner->id])->id,
        ]);
        $this->actingInPartner($this->user);
        $this->getJson(route('chat.api.threads.show', $foreignThread))->assertForbidden();
    }

    public function test_missing_dialog_is_404_not_server_error(): void
    {
        $response = $this->getJson(route('chat.api.threads.show', 9_999_999));
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertNotFound();
    }

    public function test_wrong_methods_on_open_dialog_are_not_empty_200(): void
    {
        $peer = $this->makePeer('HdrMethod_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $url = route('chat.api.threads.show', $thread);

        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, $url);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' не пустой 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON 404/405');

            $html = $this->call($method, $url);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML 404/405');
        }
    }

    public function test_native_open_dialog_returns_json_header_not_empty_page(): void
    {
        $peer = $this->makePeer('HdrNative_', ['last_seen_at' => now()]);
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $response = $this->get(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('thread.header_subtitle', 'онлайн')
            ->assertJsonPath('thread.peer_presence_label', 'онлайн');
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertStringNotContainsString('<html', (string) $response->getContent());
    }

    public function test_open_private_dialog_header_follows_online_two_to_five_minutes_and_absolute_datetime(): void
    {
        $this->travelTo('2028-08-19 12:00:00');

        $cases = [
            ['online', now(), 'онлайн'],
            ['edge', now()->subSeconds(120), 'онлайн'],
            ['two', now()->subSeconds(121), 'был(а) в сети 2 минуты назад'],
            ['three', now()->subMinutes(3), 'был(а) в сети 3 минуты назад'],
            ['four', now()->subMinutes(4), 'был(а) в сети 4 минуты назад'],
            ['five', now()->subMinutes(5), 'был(а) в сети 5 минут назад'],
            ['six', now()->subMinutes(6), 'был(а) в сети в 11:54 19 августа 2028'],
            ['old', '2028-08-18 16:50:00', 'был(а) в сети в 16:50 18 августа 2028'],
            ['never', null, ''],
        ];

        foreach ($cases as [$prefix, $seen, $expected]) {
            $peer = $this->makePeer('Hdr'.$prefix.'_', ['last_seen_at' => $seen]);
            $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

            $this->getJson(route('chat.api.threads.show', $thread))
                ->assertOk()
                ->assertJsonPath('thread.is_group', false)
                ->assertJsonPath('thread.header_subtitle', $expected)
                ->assertJsonPath('thread.peer_presence_label', $expected)
                ->assertJsonPath('thread.members_total', null);

            $this->assertStringNotContainsString(
                'участник',
                (string) $this->getJson(route('chat.api.threads.show', $thread))->json('thread.header_subtitle')
            );
        }
    }

    public function test_open_group_header_shows_member_count_not_online_even_if_members_are_online(): void
    {
        $this->travelTo('2026-08-19 12:00:00');
        $a = $this->makePeer('HdrGrpA_', ['last_seen_at' => now()]);
        $b = $this->makePeer('HdrGrpB_', ['last_seen_at' => now()]);
        $thread = $this->createGroupThreadForUsers([$this->user->id, $a->id, $b->id], 'ОнлайнГруппа');

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.members_total', 3)
            ->assertJsonPath('thread.header_subtitle', '3 участника')
            ->assertJsonPath('thread.peer_presence_label', '')
            ->assertJsonPath('thread.peer_id', null);

        $this->assertSame(
            '3 участника',
            (string) $this->getJson(route('chat.api.threads.show', $thread))->json('thread.header_subtitle')
        );
        $this->assertNotSame(
            'онлайн',
            $this->getJson(route('chat.api.threads.show', $thread))->json('thread.header_subtitle')
        );
    }

    public function test_group_header_uses_russian_plural_and_ignores_left_members(): void
    {
        $service = app(ChatService::class);
        $this->assertSame('1 участник', $service->membersCountLabel(1));
        $this->assertSame('21 участник', $service->membersCountLabel(21));
        $this->assertSame('11 участников', $service->membersCountLabel(11));

        $cases = [
            1 => '1 участник',
            2 => '2 участника',
            5 => '5 участников',
            11 => '11 участников',
        ];

        foreach ($cases as $count => $label) {
            $ids = [$this->user->id];
            while (count($ids) < $count) {
                $ids[] = $this->makePeer('Pl'.$count.'_'.count($ids).'_')->id;
            }
            $thread = $this->createGroupThreadForUsers($ids, 'Склонение'.$count);
            $this->getJson(route('chat.api.threads.show', $thread))
                ->assertOk()
                ->assertJsonPath('thread.members_total', $count)
                ->assertJsonPath('thread.header_subtitle', $label);
        }

        $a = $this->makePeer('HdrLeftA_');
        $b = $this->makePeer('HdrLeftB_');
        $thread = $this->createGroupThreadForUsers([$this->user->id, $a->id, $b->id], 'После выхода');
        ChatParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $b->id)
            ->delete();

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.members_total', 2)
            ->assertJsonPath('thread.header_subtitle', '2 участника');
    }

    public function test_dialog_list_does_not_include_header_subtitle(): void
    {
        $peer = $this->makePeer('HdrList_', ['last_seen_at' => now()]);
        $private = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $a = $this->makePeer('HdrListA_');
        $b = $this->makePeer('HdrListB_');
        $group = $this->createGroupThreadForUsers([$this->user->id, $a->id, $b->id], 'СписокГруппа');

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $privateRow = $list->firstWhere('id', $private->id);
        $groupRow = $list->firstWhere('id', $group->id);
        $this->assertNotNull($privateRow);
        $this->assertNotNull($groupRow);
        $this->assertArrayNotHasKey('header_subtitle', $privateRow);
        $this->assertArrayNotHasKey('peer_presence_label', $privateRow);
        $this->assertArrayNotHasKey('members_total', $privateRow);
        $this->assertArrayNotHasKey('header_subtitle', $groupRow);
        $this->assertArrayNotHasKey('members_total', $groupRow);
    }

    public function test_creating_group_ajax_returns_member_count_in_header_fields(): void
    {
        $a = $this->makePeer('HdrCreateA_');
        $b = $this->makePeer('HdrCreateB_');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'ШапкаГруппа',
            'user_ids' => [$a->id, $b->id],
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.members_total', 3)
            ->assertJsonPath('thread.header_subtitle', '3 участника')
            ->assertJsonPath('thread.peer_presence_label', '');
    }

    public function test_creating_private_dialog_ajax_returns_presence_in_header_fields(): void
    {
        $this->travelTo('2026-08-19 12:00:00');
        $peer = $this->makePeer('HdrCreateP_', ['last_seen_at' => now()->subMinutes(5)]);

        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])
            ->assertCreated()
            ->assertJsonPath('thread.is_group', false)
            ->assertJsonPath('thread.header_subtitle', 'был(а) в сети 5 минут назад')
            ->assertJsonPath('thread.peer_presence_label', 'был(а) в сети 5 минут назад')
            ->assertJsonPath('thread.members_total', null);
    }

    public function test_adding_and_removing_members_updates_header_count_on_open_and_ajax_payload(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('HdrAddA_');
        $b = $this->makePeer('HdrAddB_');
        $newbie = $this->makePeer('HdrAddN_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'СоставШапка');
        $this->actingInPartner($admin);

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$newbie->id],
        ])
            ->assertOk()
            ->assertJsonPath('members_total', 4)
            ->assertJsonPath('thread.header_subtitle', '4 участника')
            ->assertJsonPath('thread.members_total', 4);

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.header_subtitle', '4 участника');

        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('thread.header_subtitle', '4 участника');

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))
            ->assertOk()
            ->assertJsonPath('members_total', 3);

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.header_subtitle', '3 участника')
            ->assertJsonPath('thread.members_total', 3);
    }

    public function test_native_create_group_redirects_and_later_open_shows_member_count(): void
    {
        $a = $this->makePeer('HdrNatA_');
        $b = $this->makePeer('HdrNatB_');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.groups.store'), [
                'title' => 'НативШапка',
                'user_ids' => [$a->id, $b->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(201, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));

        $thread = ChatThread::query()->where('subject', 'НативШапка')->where('is_group', true)->first();
        $this->assertNotNull($thread);

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.header_subtitle', '3 участника');
    }

    public function test_native_create_private_dialog_redirects_and_later_open_shows_online(): void
    {
        $this->travelTo('2026-08-19 12:00:00');
        $peer = $this->makePeer('HdrNatP_', ['last_seen_at' => now()]);

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), [
                'user_id' => $peer->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(201, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));

        $thread = ChatThread::query()
            ->has('participants', '=', 2)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $this->user->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $peer->id))
            ->first();
        $this->assertNotNull($thread);

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.header_subtitle', 'онлайн');
    }

    public function test_student_admin_and_trainer_with_permission_can_read_header_subtitle(): void
    {
        $a = $this->makePeer('HdrRoleA_');
        $b = $this->makePeer('HdrRoleB_');
        $thread = $this->createGroupThreadForUsers([$this->user->id, $a->id, $b->id], 'РолиШапка');

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.header_subtitle', '3 участника');

        $admin = $this->createUserWithRole('admin');
        ChatParticipant::query()->create([
            'thread_id' => $thread->id,
            'user_id' => (int) $admin->id,
        ]);
        $this->actingInPartner($admin);
        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.header_subtitle', '4 участника');

        $trainer = $this->createUserWithRole('trainer');
        ChatParticipant::query()->create([
            'thread_id' => $thread->id,
            'user_id' => (int) $trainer->id,
        ]);
        $this->actingInPartner($trainer);
        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.header_subtitle', '5 участников');
    }

    public function test_chat_page_renders_idle_subtitle_hidden_under_title_not_in_thread_list(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $headerStart = strpos($html, 'id="threadPeerHit"');
        $this->assertNotFalse($headerStart);
        $header = substr($html, $headerStart, 900);

        $titlePos = strpos($header, 'id="threadTitle"');
        $subPos = strpos($header, 'id="threadSubtitle"');
        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($subPos);
        $this->assertGreaterThan($titlePos, $subPos, 'Подпись должна быть под названием, не над ним');
        $this->assertStringContainsString('Выберите диалог', $header);
        $this->assertStringContainsString('chat-header-subtitle', $header);
        $this->assertMatchesRegularExpression(
            '/id="threadSubtitle"[^>]*style="display:none;"/',
            $header
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="threadSubtitle"[^>]*>[^<]+</',
            $header,
            'Пока диалог не выбран, подпись пустая'
        );

        $listStart = strpos($html, 'id="threads"');
        $this->assertNotFalse($listStart);
        $list = substr($html, $listStart, 400);
        $this->assertStringNotContainsString('threadSubtitle', $list);
        $this->assertStringNotContainsString('участник', $list);
    }
}
