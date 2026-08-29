<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Events\InboxBump;
use App\Models\ChatThread;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * Порядок inbox: непрочитанные сверху, затем last_message_id, чаты без сообщений внизу
 * (не threads.updated_at). Пустая новая группа не всплывает над переписками.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatInboxSortFeatureTest extends ChatTestCase
{
    use InteractsWithTeamGroupChats;

    public function test_guest_cannot_list_threads(): void
    {
        Auth::logout();

        $json = $this->getJson(route('chat.api.threads.index'));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();

        $html = $this->get(route('chat.api.threads.index'));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertTrue($html->isRedirect());
        $this->assertGuest();
    }

    public function test_user_without_messages_view_gets_403_on_inbox(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $json = $this->getJson(route('chat.api.threads.index'));
        $this->assertSame(403, $json->getStatusCode());
    }

    public function test_inbox_orders_unread_then_last_message_then_empty_threads(): void
    {
        [$withUnread, $withMessage, $emptyGroup] = $this->seedUnreadReadAndEmptyGroup();

        $threads = $this->getJson(route('chat.api.threads.index'))
            ->assertOk()
            ->json('threads');

        $this->assertSame(
            [(int) $withUnread->id, (int) $withMessage->id, (int) $emptyGroup->id],
            $this->orderOf($threads, [
                (int) $withUnread->id,
                (int) $withMessage->id,
                (int) $emptyGroup->id,
            ])
        );

        $emptyRow = collect($threads)->firstWhere('id', $emptyGroup->id);
        $this->assertNotNull($emptyRow);
        $this->assertNull($emptyRow['last_message']);
        $this->assertNull($emptyRow['last_message_time']);
        $this->assertSame(0, (int) $emptyRow['unread_count']);
    }

    public function test_native_get_inbox_keeps_the_same_order_and_is_json_not_html_page(): void
    {
        [$withUnread, $withMessage, $emptyGroup] = $this->seedUnreadReadAndEmptyGroup();

        $html = $this->get(route('chat.api.threads.index'));
        $this->assertNotSame(500, $html->getStatusCode());
        $html->assertOk();
        $this->assertStringContainsString('"threads"', $html->getContent());
        $this->assertStringNotContainsString('<html', strtolower($html->getContent()));

        $this->assertSame(
            [(int) $withUnread->id, (int) $withMessage->id, (int) $emptyGroup->id],
            $this->orderOf($html->json('threads'), [
                (int) $withUnread->id,
                (int) $withMessage->id,
                (int) $emptyGroup->id,
            ])
        );
    }

    public function test_empty_private_thread_stays_below_dialog_with_message(): void
    {
        $oldPeer = $this->makePeer('SortPrivOld_');
        $emptyPeer = $this->makePeer('SortPrivEmpty_');

        $withMessage = $this->createThreadForUsers([$this->user->id, $oldPeer->id], 'есть переписка');
        $this->seedMessage($withMessage, (int) $this->user->id, 'Исходящее');

        $emptyPrivateId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $emptyPeer->id,
        ])->assertCreated()->json('thread_id');
        ChatThread::query()->whereKey($emptyPrivateId)
            ->update(['updated_at' => now()->addDay()]);

        $threads = $this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads');
        $emptyRow = collect($threads)->firstWhere('id', $emptyPrivateId);
        $this->assertNotNull($emptyRow);
        $this->assertNull($emptyRow['last_message_time']);

        $this->assertSame(
            [(int) $withMessage->id, $emptyPrivateId],
            $this->orderOf($threads, [(int) $withMessage->id, $emptyPrivateId])
        );
    }

    public function test_empty_team_group_stays_below_dialog_despite_fresh_updated_at(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'groups.view');
        $this->grantPermission($this->user, 'trainers.view');
        $this->grantPermission($this->user, 'schedule.view');
        $this->grantPermission($this->user, 'locations.view');
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.group.update');
        $this->grantPermission($this->user, 'users.role.update');
        $this->grantPermission($this->user, 'users.activity.update');
        $this->grantPermission($this->user, 'users.name.update');
        $this->grantPermission($this->user, 'messages.view');

        $peer = $this->makePeer('SortTeamPeer_');
        $withMessage = $this->createThreadForUsers([$this->user->id, $peer->id], 'личка');
        $this->seedMessage($withMessage, (int) $this->user->id, 'Переписка');

        $team = $this->storeTeamViaAjax('СортУчебная '.uniqid('', true));
        $emptyTeam = $this->teamThread($team);
        $emptyTeam->forceFill(['updated_at' => now()->addDay()])->save();

        $threads = $this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads');
        $row = collect($threads)->firstWhere('id', $emptyTeam->id);
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row['is_group']);
        $this->assertNull($row['last_message']);
        $this->assertNull($row['last_message_time']);

        $this->assertSame(
            [(int) $withMessage->id, (int) $emptyTeam->id],
            $this->orderOf($threads, [(int) $withMessage->id, (int) $emptyTeam->id])
        );
    }

    public function test_newer_last_message_ranks_above_older_when_both_are_read(): void
    {
        $olderPeer = $this->makePeer('SortReadOld_');
        $newerPeer = $this->makePeer('SortReadNew_');

        $older = $this->createThreadForUsers([$this->user->id, $olderPeer->id], 'старше');
        $this->seedMessage($older, (int) $this->user->id, 'Раньше');

        $newer = $this->createThreadForUsers([$this->user->id, $newerPeer->id], 'новее');
        $this->seedMessage($newer, (int) $this->user->id, 'Позже');
        $older->forceFill(['updated_at' => now()->addHour()])->save();

        $threads = $this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads');
        $this->assertSame(
            [(int) $newer->id, (int) $older->id],
            $this->orderOf($threads, [(int) $newer->id, (int) $older->id])
        );
    }

    public function test_create_group_bump_and_inbox_use_null_last_message_time(): void
    {
        $a = $this->makePeer('SortBumpA_');
        $b = $this->makePeer('SortBumpB_');
        $peer = $this->makePeer('SortBumpPeer_');
        $withMessage = $this->createThreadForUsers([$this->user->id, $peer->id], 'есть');
        $this->seedMessage($withMessage, (int) $this->user->id, 'Текст');

        Event::fake([InboxBump::class]);

        $created = $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Пустая сорт',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated();
        $groupId = (int) $created->json('thread_id');

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($groupId) {
            return (int) $event->payload['thread_id'] === $groupId
                && $event->payload['last_message'] === null
                && $event->payload['last_message_time'] === null
                && (int) $event->payload['unread_count'] === 0;
        });

        $threads = $this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads');
        $this->assertSame(
            [(int) $withMessage->id, $groupId],
            $this->orderOf($threads, [(int) $withMessage->id, $groupId])
        );
    }

    public function test_add_member_bump_carries_last_message_of_existing_group(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('SortAddA_');
        $b = $this->makePeer('SortAddB_');
        $newbie = $this->makePeer('SortAddNew_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Живая');
        $this->actingInPartner($admin);
        $this->seedMessage($thread, (int) $admin->id, 'Уже писали');
        $thread->refresh();

        Event::fake([InboxBump::class]);

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$newbie->id],
        ])->assertOk();

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($thread, $newbie) {
            return (int) $event->userId === (int) $newbie->id
                && (int) $event->payload['thread_id'] === (int) $thread->id
                && $event->payload['last_message'] === 'Уже писали'
                && $event->payload['last_message_time'] !== null;
        });
    }

    public function test_message_in_empty_group_promotes_it_above_other_empty_thread(): void
    {
        $a = $this->makePeer('SortPromoA_');
        $b = $this->makePeer('SortPromoB_');
        $c = $this->makePeer('SortPromoC_');
        $d = $this->makePeer('SortPromoD_');

        $stillEmpty = $this->createGroupThreadForUsers(
            [$this->user->id, $c->id, $d->id],
            'Так и пустая'
        );
        $gainsMessage = $this->createGroupThreadForUsers(
            [$this->user->id, $a->id, $b->id],
            'Станет живой'
        );
        $stillEmpty->forceFill(['updated_at' => now()->addDay()])->save();

        $this->postJson(route('chat.api.threads.messages.store', $gainsMessage), [
            'body' => 'Первое',
        ])->assertCreated();

        $threads = $this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads');
        $this->assertSame(
            [(int) $gainsMessage->id, (int) $stillEmpty->id],
            $this->orderOf($threads, [(int) $gainsMessage->id, (int) $stillEmpty->id])
        );
    }

    /**
     * @return array{0: ChatThread, 1: ChatThread, 2: ChatThread}
     */
    private function seedUnreadReadAndEmptyGroup(): array
    {
        $oldPeer = $this->makePeer('OrdOld_');
        $newPeer = $this->makePeer('OrdNew_');
        $groupA = $this->makePeer('OrdGA_');
        $groupB = $this->makePeer('OrdGB_');

        $withUnread = $this->createThreadForUsers([$this->user->id, $oldPeer->id], 'unread old');
        $this->seedMessage($withUnread, (int) $oldPeer->id, 'Старое входящее');
        $withUnread->forceFill(['updated_at' => now()->subDays(10)])->save();

        $withMessage = $this->createThreadForUsers([$this->user->id, $newPeer->id], 'read newer');
        $this->seedMessage($withMessage, (int) $this->user->id, 'Свежее исходящее');

        $emptyGroup = $this->createGroupThreadForUsers(
            [$this->user->id, $groupA->id, $groupB->id],
            'Пустая новая группа'
        );
        $emptyGroup->forceFill(['updated_at' => now()->addDay()])->save();

        return [$withUnread, $withMessage, $emptyGroup];
    }

    /**
     * @param  list<array<string, mixed>>  $threads
     * @param  list<int>  $watched
     * @return list<int>
     */
    private function orderOf(array $threads, array $watched): array
    {
        $ids = array_map('intval', array_column($threads, 'id'));

        return array_values(array_intersect($ids, $watched));
    }
}
