<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ParentProfile;
use Illuminate\Support\Facades\Auth;

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
}
