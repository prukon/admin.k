<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Events\InboxBump;
use App\Events\MessageCreated;
use App\Events\ThreadReadUpdated;
use App\Models\ChatParticipant;
use Illuminate\Support\Facades\Event;

/**
 * UX-баг: сообщение в открытом диалоге вспыхивало счётчиком в сайдбаре и сразу пропадало.
 *
 * Сервер в inbox.bump отдаёт unread_total уже с новым сообщением.
 * Старый клиент красил этот total, затем message.created / thread.read обнулял бейдж.
 * После фикса страница чата не красит total открытого треда.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatUnreadSidebarFlashUxFeatureTest extends ChatTestCase
{
    public function test_sidebar_counter_does_not_flash_when_message_arrives_in_open_dialog(): void
    {
        $result = $this->simulateSidebarPaints();

        $this->assertSame(
            [],
            $result['chat_page_open_dialog']['apply_unread_from_echo'],
            'На странице чата echo не должен красить unread_total до обработчика диалога'
        );
        $this->assertSame([0], $result['chat_page_open_dialog']['paints_from_bump']);
        $this->assertSame([0, 0], $result['chat_page_open_dialog']['paints_after_read']);
        $this->assertFalse(
            $this->flashedFromZero($result['chat_page_open_dialog']['paints_after_read']),
            'Бейдж не должен вспыхнуть 0→N→0, пока открыт этот диалог и других непрочитанных нет'
        );
    }

    public function test_sidebar_keeps_other_unreads_without_painting_the_open_thread_total(): void
    {
        $result = $this->simulateSidebarPaints();

        $this->assertSame([2], $result['chat_page_open_dialog_with_other_unreads']['paints_from_bump']);
        $this->assertNotContains(3, $result['chat_page_open_dialog_with_other_unreads']['paints_from_bump']);
        $this->assertSame([2, 2], $result['chat_page_open_dialog_with_other_unreads']['paints_after_read']);
        $this->assertFalse(
            $this->flashedFromZero($result['chat_page_open_dialog_with_other_unreads']['paints_after_read'])
        );
    }

    public function test_duplicate_inbox_listeners_on_chat_page_still_do_not_flash_the_badge(): void
    {
        $result = $this->simulateSidebarPaints();

        $this->assertSame([0, 0], $result['chat_page_double_bump']['paints_from_bump']);
        $this->assertSame([0, 0, 0], $result['chat_page_double_bump']['paints_after_read']);
        $this->assertFalse($this->flashedFromZero($result['chat_page_double_bump']['paints_after_read']));
    }

    public function test_sidebar_shows_counter_when_message_is_for_another_dialog(): void
    {
        $result = $this->simulateSidebarPaints();

        $this->assertSame([1], $result['chat_page_other_dialog']['paints_from_bump']);
        $this->assertSame(
            [1],
            $result['other_page']['paints_from_bump'],
            'Вне страницы чата бейдж должен сразу показать unread_total'
        );
        $this->assertSame([1], $result['other_page']['apply_unread_from_echo']);
    }

    public function test_incoming_message_payload_includes_new_unread_so_client_must_not_paint_it_while_viewing(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('unread_total', 0);

        ChatParticipant::query()
            ->where('thread_id', $threadId)
            ->where('user_id', $this->user->id)
            ->update(['last_read' => now()->subMinute()]);

        Event::fake([InboxBump::class, MessageCreated::class, ThreadReadUpdated::class]);

        $this->actingInPartner($peer);
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Пока ты в диалоге',
        ])->assertCreated();

        Event::assertDispatched(MessageCreated::class, function (MessageCreated $event) use ($threadId) {
            return $event->threadId === $threadId
                && $event->broadcastAs() === 'message.created'
                && $event->broadcastOn()[0]->name === 'private-thread.'.$threadId
                && (string) ($event->broadcastWith()['message']['body'] ?? '') === 'Пока ты в диалоге';
        });

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($threadId, $peer) {
            if ($event->userId !== (int) $this->user->id) {
                return false;
            }

            $data = $event->broadcastWith();

            return $event->broadcastAs() === 'inbox.bump'
                && $event->broadcastOn()[0]->name === 'private-inbox.'.$this->user->id
                && (int) $data['thread_id'] === $threadId
                && (int) $data['unread_count'] === 1
                && (int) $data['unread_total'] === 1
                && (int) $data['peer_id'] === (int) $peer->id;
        });

        $this->actingInPartner($this->user);
        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1);

        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('unread_total', 0);

        Event::assertDispatched(ThreadReadUpdated::class, function (ThreadReadUpdated $event) use ($threadId) {
            return $event->threadId === $threadId
                && $event->userId === (int) $this->user->id
                && $event->unreadTotal === 0
                && $event->broadcastAs() === 'thread.read';
        });
    }

    public function test_opening_one_dialog_does_not_clear_unread_of_another(): void
    {
        $peerA = $this->makePeer('PeerA_');
        $peerB = $this->makePeer('PeerB_');
        $threadA = $this->createThreadForUsers([$this->user->id, $peerA->id]);
        $threadB = $this->createThreadForUsers([$this->user->id, $peerB->id]);
        $this->seedMessage($threadA, $peerA->id, 'A unread');
        $this->seedMessage($threadB, $peerB->id, 'B unread');

        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 2);

        $this->getJson(route('chat.api.threads.show', $threadA->id))
            ->assertOk()
            ->assertJsonPath('unread_total', 1);

        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1);

        $rowB = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $threadB->id);
        $this->assertSame(1, (int) ($rowB['unread_count'] ?? 0));
    }

    public function test_mark_read_of_open_dialog_keeps_unread_of_other_dialog(): void
    {
        $peerA = $this->makePeer('PeerA_');
        $peerB = $this->makePeer('PeerB_');
        $threadA = $this->createThreadForUsers([$this->user->id, $peerA->id]);
        $threadB = $this->createThreadForUsers([$this->user->id, $peerB->id]);
        $this->seedMessage($threadA, $peerA->id, 'A');
        $this->seedMessage($threadB, $peerB->id, 'B');

        $this->patchJson(route('chat.api.threads.read', $threadA->id))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('unread_total', 1);
    }

    public function test_echo_inbox_bump_script_hands_off_to_chat_page_and_does_not_paint_first(): void
    {
        $blade = (string) file_get_contents(resource_path('views/includes/chat/echo.blade.php'));
        $start = strpos($blade, "channel.listen('.inbox.bump'");
        $end = strpos($blade, "channel.listen('.thread.read'");
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $listener = substr($blade, $start, $end - $start);

        $onInboxPos = strpos($listener, 'KidsCrmChatOnInboxBump');
        $applyPos = strpos($listener, 'applyUnread(payload.unread_total)');
        $this->assertNotFalse($onInboxPos);
        $this->assertNotFalse($applyPos);
        $this->assertLessThan(
            $applyPos,
            $onInboxPos,
            'Сначала передача на страницу чата, иначе бейдж вспыхнет от unread_total'
        );
        $this->assertStringContainsString('return;', $listener);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateSidebarPaints(): array
    {
        $chatJs = resource_path('js/chat.js');
        $echoBlade = resource_path('views/includes/chat/echo.blade.php');
        $this->assertFileExists($chatJs);
        $this->assertFileExists($echoBlade);

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');
const echoBlade = fs.readFileSync(process.argv[3], 'utf8');

const bumpStart = chatJs.indexOf('function applyInboxBump(e)');
const bumpEnd = chatJs.indexOf('function subscribeInbox(');
if (bumpStart < 0 || bumpEnd < 0 || bumpEnd <= bumpStart) {
    throw new Error('applyInboxBump not found in chat.js');
}
const applyInboxBumpSrc = chatJs.slice(bumpStart, bumpEnd);

const echoStart = echoBlade.indexOf("channel.listen('.inbox.bump'");
const echoEnd = echoBlade.indexOf("channel.listen('.thread.read'");
if (echoStart < 0 || echoEnd < 0 || echoEnd <= echoStart) {
    throw new Error('inbox.bump listener not found in echo.blade.php');
}
let echoHandler = echoBlade.slice(echoStart, echoEnd)
    .replace("channel.listen('.inbox.bump', function (payload)", 'function handleInboxBump(payload)')
    .trim();
echoHandler = echoHandler.replace(/\}\);\s*$/, '}');

const readStart = echoBlade.indexOf("channel.listen('.thread.read'");
const readEnd = echoBlade.indexOf('inboxBound = true');
if (readStart < 0 || readEnd < 0 || readEnd <= readStart) {
    throw new Error('thread.read listener not found in echo.blade.php');
}
let readHandler = echoBlade.slice(readStart, readEnd)
    .replace("channel.listen('.thread.read', function (payload)", 'function handleThreadRead(payload)')
    .trim();
readHandler = readHandler.replace(/\}\);\s*$/, '}');

function runScenario(opts) {
    const paints = [];
    const applyUnreadFromEcho = [];
    const onInboxPayloads = [];
    let currentThreadId = opts.currentThreadId;
    const me = opts.me;
    global.window = global;
    function applyUnread(n) {
        paints.push(Number(n));
    }
    function setUnreadBadge(n) {
        paints.push(Number(n));
    }
    function upsertThread() {}
    eval(applyInboxBumpSrc);
    window.KidsCrmChatOnInboxBump = opts.onChatPage
        ? function (payload) {
            onInboxPayloads.push(payload);
            applyInboxBump(payload);
        }
        : undefined;
    const wrappedApplyUnread = function (n) {
        applyUnreadFromEcho.push(Number(n));
        applyUnread(n);
    };
    eval(echoHandler.replace(/applyUnread\(/g, 'wrappedApplyUnread('));
    eval(readHandler);

    const paintsBefore = paints.length;
    handleInboxBump(opts.payload);
    if (opts.doubleBump) {
        handleInboxBump(opts.payload);
    }
    const paintsFromBump = paints.slice(paintsBefore);
    handleThreadRead({
        user_id: opts.me,
        unread_total: opts.readTotal
    });
    return {
        apply_unread_from_echo: applyUnreadFromEcho,
        on_inbox_count: onInboxPayloads.length,
        paints_from_bump: paintsFromBump,
        paints_after_read: paints.slice(paintsBefore)
    };
}

const openDialogPayload = { thread_id: 10, unread_count: 1, unread_total: 1 };
const openWithOthers = { thread_id: 10, unread_count: 1, unread_total: 3 };
const otherDialogPayload = { thread_id: 20, unread_count: 1, unread_total: 1 };

const out = {
    chat_page_open_dialog: runScenario({
        onChatPage: true,
        currentThreadId: 10,
        me: 7,
        payload: openDialogPayload,
        readTotal: 0
    }),
    chat_page_open_dialog_with_other_unreads: runScenario({
        onChatPage: true,
        currentThreadId: 10,
        me: 7,
        payload: openWithOthers,
        readTotal: 2
    }),
    chat_page_double_bump: runScenario({
        onChatPage: true,
        currentThreadId: 10,
        me: 7,
        payload: openDialogPayload,
        readTotal: 0,
        doubleBump: true
    }),
    chat_page_other_dialog: runScenario({
        onChatPage: true,
        currentThreadId: 10,
        me: 7,
        payload: otherDialogPayload,
        readTotal: 1
    }),
    other_page: runScenario({
        onChatPage: false,
        currentThreadId: null,
        me: 7,
        payload: openDialogPayload,
        readTotal: 1
    })
};
process.stdout.write(JSON.stringify(out));
JS;

        $path = sys_get_temp_dir().'/chat-sidebar-flash-'.uniqid('', true).'.cjs';
        file_put_contents($path, $script);

        try {
            $output = [];
            $exitCode = 0;
            exec(
                'node '.escapeshellarg($path).' '.escapeshellarg($chatJs).' '.escapeshellarg($echoBlade).' 2>&1',
                $output,
                $exitCode
            );
            $raw = implode("\n", $output);
            $this->assertSame(0, $exitCode, $raw);
            $decoded = json_decode($raw, true);
            $this->assertIsArray($decoded, $raw);

            return $decoded;
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param  list<int|float>  $paints
     */
    private function flashedFromZero(array $paints): bool
    {
        $seenPositive = false;
        foreach ($paints as $value) {
            $n = (int) $value;
            if ($n > 0) {
                $seenPositive = true;
            }
            if ($seenPositive && $n === 0) {
                return true;
            }
        }

        return false;
    }
}
