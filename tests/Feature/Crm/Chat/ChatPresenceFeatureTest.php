<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Events\InboxBump;
use App\Models\ParentProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * Онлайн-статус (last_seen_at, ping), ФИО родителя в контактах, галочки исходящего в списке.
 */
final class ChatPresenceFeatureTest extends ChatTestCase
{
    public function test_guest_cannot_ping_presence(): void
    {
        Auth::logout();

        $this->postJson(route('presence.ping'))->assertUnauthorized();

        $html = $this->post(route('presence.ping'));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertTrue($html->isRedirect());
    }

    public function test_presence_ping_works_without_messages_view_and_sets_last_seen(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);
        $this->assertNull($denied->fresh()->last_seen_at);

        $this->postJson(route('presence.ping'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull($denied->fresh()->last_seen_at);
    }

    public function test_presence_ping_is_throttled_within_thirty_seconds(): void
    {
        $this->travelTo('2026-08-18 12:00:00');
        $this->postJson(route('presence.ping'))->assertOk();
        $first = $this->user->fresh()->last_seen_at;
        $this->assertNotNull($first);

        $this->travel(10)->seconds();
        $this->postJson(route('presence.ping'))->assertOk();
        $this->assertTrue($this->user->fresh()->last_seen_at->equalTo($first));

        $this->travel(21)->seconds();
        $this->postJson(route('presence.ping'))->assertOk();
        $this->assertTrue($this->user->fresh()->last_seen_at->gt($first));
    }

    public function test_contacts_mark_online_within_two_minutes_and_offline_otherwise(): void
    {
        $online = $this->makePeer('OnlinePeer_');
        $stale = $this->makePeer('StalePeer_');
        $never = $this->makePeer('NeverPeer_');

        $online->forceFill(['last_seen_at' => now()->subSeconds(119)])->save();
        $stale->forceFill(['last_seen_at' => now()->subSeconds(121)])->save();

        $contacts = collect($this->getJson(route('chat.api.users'))->assertOk()->json());

        $this->assertTrue((bool) $contacts->firstWhere('id', $online->id)['is_online']);
        $this->assertFalse((bool) $contacts->firstWhere('id', $stale->id)['is_online']);
        $this->assertFalse((bool) $contacts->firstWhere('id', $never->id)['is_online']);
    }

    public function test_contacts_include_parent_full_name_when_profile_exists(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Петров',
            'firstname' => 'Пётр',
            'middlename' => 'Петрович',
        ]);
        $kid = $this->makePeer('KidWithParent_', ['parent_id' => $parent->id]);
        $staff = $this->makePeer('StaffNoParent_');

        $contacts = collect($this->getJson(route('chat.api.users'))->assertOk()->json());

        $kidRow = $contacts->firstWhere('id', $kid->id);
        $staffRow = $contacts->firstWhere('id', $staff->id);
        $this->assertNotNull($kidRow);
        $this->assertNotNull($staffRow);
        $this->assertSame('Петров Пётр Петрович', $kidRow['parent_full_name']);
        $this->assertSame('', $staffRow['parent_full_name']);
    }

    public function test_thread_list_has_outgoing_ticks_flags_and_peer_online(): void
    {
        $peer = $this->makePeer('TicksPeer_');
        $peer->forceFill(['last_seen_at' => now()])->save();

        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Исходящее',
        ])->assertCreated();

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row['peer_is_online']);
        $this->assertTrue((bool) $row['last_message_is_mine']);
        $this->assertFalse((bool) $row['last_message_is_read']);

        $this->actingInPartner($peer);
        $this->patchJson(route('chat.api.threads.read', $threadId))->assertOk();

        $this->actingInPartner($this->user);
        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $threadId);
        $this->assertTrue((bool) $row['last_message_is_mine']);
        $this->assertTrue((bool) $row['last_message_is_read']);
    }

    public function test_incoming_last_message_has_no_outgoing_tick_flags(): void
    {
        $peer = $this->makePeer('IncomingPeer_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, (int) $peer->id, 'Входящее');

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertNotNull($row);
        $this->assertFalse((bool) $row['last_message_is_mine']);
        $this->assertNull($row['last_message_is_read']);
        $this->assertFalse((bool) $row['peer_is_online']);
    }

    public function test_user_with_chat_permission_can_still_ping_presence(): void
    {
        $this->assertNull($this->user->fresh()->last_seen_at);

        $this->postJson(route('presence.ping'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull($this->user->fresh()->last_seen_at);
    }

    public function test_native_presence_ping_returns_json_ok_and_writes_last_seen(): void
    {
        $this->assertNull($this->user->fresh()->last_seen_at);

        $response = $this->post(route('presence.ping'));
        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertNotNull($this->user->fresh()->last_seen_at);
    }

    public function test_presence_ping_wrong_methods_are_not_empty_200(): void
    {
        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('presence.ping'));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' не пустой 200');
            $this->assertContains(
                $json->getStatusCode(),
                [404, 405],
                $method.' JSON должен быть 404/405, получено '.$json->getStatusCode()
            );

            $html = $this->call($method, route('presence.ping'));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertContains(
                $html->getStatusCode(),
                [404, 405],
                $method.' HTML должен быть 404/405, получено '.$html->getStatusCode()
            );
        }
    }

    public function test_guest_wrong_methods_on_presence_ping_do_not_return_server_error(): void
    {
        Auth::logout();

        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('presence.ping'));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertContains($json->getStatusCode(), [401, 404, 405, 419]);

            $html = $this->call($method, route('presence.ping'));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML гость не 500');
            $this->assertTrue(
                $html->isRedirect() || in_array($html->getStatusCode(), [401, 404, 405, 419], true),
                $method.' HTML гость: редирект/401/404/405/419, получено '.$html->getStatusCode()
            );
        }
    }

    public function test_peer_still_online_at_exactly_two_minutes_and_offline_a_second_later(): void
    {
        $this->travelTo('2026-08-18 12:00:00');
        $edge = $this->makePeer('EdgeOnline_');
        $over = $this->makePeer('JustOffline_');
        $edge->forceFill(['last_seen_at' => now()->subSeconds(120)])->save();
        $over->forceFill(['last_seen_at' => now()->subSeconds(121)])->save();

        $contacts = collect($this->getJson(route('chat.api.users'))->assertOk()->json());

        $this->assertTrue((bool) $contacts->firstWhere('id', $edge->id)['is_online']);
        $this->assertFalse((bool) $contacts->firstWhere('id', $over->id)['is_online']);
    }

    public function test_open_private_thread_header_subtitle_uses_online_relative_and_absolute_last_seen(): void
    {
        $this->travelTo('2028-08-19 12:00:00');

        $online = $this->makePeer('HdrOn_');
        $twoMin = $this->makePeer('HdrTwo_');
        $fiveMin = $this->makePeer('HdrFive_');
        $old = $this->makePeer('HdrOld_');
        $never = $this->makePeer('HdrNever_');

        $online->forceFill(['last_seen_at' => now()])->save();
        $twoMin->forceFill(['last_seen_at' => now()->subSeconds(121)])->save();
        $threeMin = $this->makePeer('HdrThree_');
        $threeMin->forceFill(['last_seen_at' => now()->subMinutes(3)])->save();
        $fiveMin->forceFill(['last_seen_at' => now()->subMinutes(5)])->save();
        $old->forceFill(['last_seen_at' => '2028-08-18 16:50:00'])->save();
        $never->forceFill(['last_seen_at' => null])->save();

        $cases = [
            [$online, 'онлайн', 'онлайн'],
            [$twoMin, 'был(а) в сети 2 минуты назад', 'был(а) в сети 2 минуты назад'],
            [$threeMin, 'был(а) в сети 3 минуты назад', 'был(а) в сети 3 минуты назад'],
            [$fiveMin, 'был(а) в сети 5 минут назад', 'был(а) в сети 5 минут назад'],
            [$old, 'был(а) в сети в 16:50 18 августа 2028', 'был(а) в сети в 16:50 18 августа 2028'],
            [$never, '', ''],
        ];

        foreach ($cases as [$peer, $subtitle, $presence]) {
            $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
            $this->getJson(route('chat.api.threads.show', $thread))
                ->assertOk()
                ->assertJsonPath('thread.is_group', false)
                ->assertJsonPath('thread.header_subtitle', $subtitle)
                ->assertJsonPath('thread.peer_presence_label', $presence)
                ->assertJsonPath('thread.members_total', null);
        }
    }

    public function test_thread_list_time_is_last_message_not_last_seen(): void
    {
        $this->travelTo('2026-08-18 15:00:00');
        $peer = $this->makePeer('SeenPeer_');
        $peer->forceFill(['last_seen_at' => '2026-08-01 09:00:00'])->save();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, (int) $this->user->id, 'Последнее');

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertNotNull($row);
        $this->assertSame('2026-08-18 15:00:00', $row['last_message_time']);
        $this->assertArrayNotHasKey('last_seen_at', $row);
        $this->assertArrayNotHasKey('header_subtitle', $row);
        $this->assertArrayNotHasKey('peer_presence_label', $row);
        $this->assertArrayNotHasKey('members_total', $row);
    }

    public function test_inbox_bump_carries_ticks_online_and_last_message_time_for_both_sides(): void
    {
        $this->travelTo('2026-08-18 15:00:00');
        $this->user->forceFill(['last_seen_at' => now()])->save();
        $peer = $this->makePeer('BumpPeer_');
        $peer->forceFill(['last_seen_at' => now()])->save();

        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        Event::fake([InboxBump::class]);

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Флаги списка',
        ])->assertCreated();

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($threadId, $peer) {
            if ($event->userId !== (int) $this->user->id) {
                return false;
            }
            $data = $event->broadcastWith();

            return (int) $data['thread_id'] === $threadId
                && (int) $data['peer_id'] === (int) $peer->id
                && $data['last_message_time'] === '2026-08-18 15:00:00'
                && $data['last_message_is_mine'] === true
                && $data['last_message_is_read'] === false
                && $data['peer_is_online'] === true;
        });

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($threadId) {
            if ($event->userId !== (int) $this->user->id) {
                $data = $event->broadcastWith();

                return (int) $data['thread_id'] === $threadId
                    && (int) $data['peer_id'] === (int) $this->user->id
                    && $data['last_message_time'] === '2026-08-18 15:00:00'
                    && $data['last_message_is_mine'] === false
                    && $data['last_message_is_read'] === null
                    && $data['peer_is_online'] === true;
            }

            return false;
        });
    }

    public function test_soft_deleted_parent_is_hidden_in_contacts_and_peer_card(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Удалённый',
            'firstname' => 'Родитель',
            'middlename' => 'Тестовый',
            'phone' => '+79009998877',
        ]);
        $kid = $this->makePeer('KidDeletedParent_', ['parent_id' => $parent->id]);
        $parent->delete();

        $contacts = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $kidRow = $contacts->firstWhere('id', $kid->id);
        $this->assertNotNull($kidRow);
        $this->assertSame('', $kidRow['parent_full_name']);
        $this->assertArrayNotHasKey('phone', $kidRow);
        $this->assertArrayNotHasKey('parent_phone', $kidRow);
        $this->assertArrayNotHasKey('last_seen_at', $kidRow);

        $this->getJson(route('chat.api.users.show', $kid))
            ->assertOk()
            ->assertJsonPath('parent_full_name', '')
            ->assertJsonPath('parent_phone', '');
    }

    public function test_disabled_same_school_peer_card_is_still_available(): void
    {
        $disabled = $this->makePeer('DisabledCard_', ['is_enabled' => 0]);

        $this->getJson(route('chat.api.users.show', $disabled))
            ->assertOk()
            ->assertJsonPath('id', $disabled->id);
    }

    public function test_own_peer_card_is_available(): void
    {
        $this->getJson(route('chat.api.users.show', $this->user))
            ->assertOk()
            ->assertJsonPath('id', $this->user->id);
    }

    public function test_native_peer_card_get_returns_json_profile_not_empty_page(): void
    {
        $peer = $this->makePeer('NativeCard_');

        $response = $this->get(route('chat.api.users.show', $peer));
        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('id', $peer->id)
            ->assertJsonPath('full_name', $peer->full_name);
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_peer_card_wrong_methods_are_not_empty_200(): void
    {
        $peer = $this->makePeer('MutateCard_');

        foreach (['POST', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.users.show', $peer));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' не пустой 200');
            $this->assertSame(405, $json->getStatusCode(), $method.' должен быть 405');
        }
    }

    public function test_user_without_messages_view_cannot_open_peer_card_but_can_ping(): void
    {
        $peer = $this->makePeer('DeniedCard_');
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $this->getJson(route('chat.api.users.show', $peer))->assertForbidden();
        $this->get(route('chat.api.users.show', $peer))->assertForbidden();
        $this->postJson(route('presence.ping'))->assertOk()->assertJsonPath('ok', true);
    }
}
