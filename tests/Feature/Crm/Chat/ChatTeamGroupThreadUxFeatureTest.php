<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Services\TeamUserSyncService;

/**
 * UX авто-чата учебной группы: что видит пользователь в HTML/инбоксе/JS,
 * а не только 200 OK. Дефолт «название чата = имя группы» только при создании.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatTeamGroupThreadUxFeatureTest extends ChatTestCase
{
    use InteractsWithTeamGroupChats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->grantPermission($this->user, 'groups.view');
        $this->grantPermission($this->user, 'trainers.view');
        $this->grantPermission($this->user, 'locations.view');
        $this->grantPermission($this->user, 'schedule.view');
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'messages.view');
    }

    public function test_create_team_modal_first_paint_has_title_first_and_enabled_by_default(): void
    {
        $html = $this->get(route('admin.team.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="createTeamModal"', $html);
        $this->assertStringContainsString('id="teamForm"', $html);
        $this->assertStringContainsString('action="'.route('admin.team.store').'"', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('data-bs-target="#createTeamModal"', $html);

        $start = strpos($html, 'id="createTeamModal"');
        $end = strpos($html, 'id="editTeamModal"');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $titlePos = strpos($chunk, 'name="title"');
        $trainerPos = strpos($chunk, 'name="trainer_profile_ids[]"');
        $enabledPos = strpos($chunk, 'name="is_enabled"');
        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($trainerPos);
        $this->assertNotFalse($enabledPos);
        $this->assertLessThan($trainerPos, $titlePos, 'Название должно быть выше тренера');
        $this->assertLessThan($enabledPos, $titlePos, 'Название должно быть выше активности');

        $this->assertMatchesRegularExpression(
            '/name="is_enabled"[^>]*>\s*<option value="1">Активен<\/option>/s',
            $chunk
        );
        $this->assertStringContainsString('Тренеры', $chunk);
        $this->assertStringContainsString('js-generic-multiselect-select', $chunk);
        $this->assertStringContainsString('data-placeholder="Выберите тренеров"', $chunk);
    }

    public function test_create_team_modal_hides_trainer_without_trainers_view(): void
    {
        $denied = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->grantPermission($denied, 'groups.view');
        $this->actingInPartner($denied);

        $html = $this->get(route('admin.team.index'))->assertOk()->getContent();
        $start = strpos($html, 'id="createTeamModal"');
        $end = strpos($html, 'id="editTeamModal"');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $chunk = substr($html, $start, $end - $start);
        $this->assertStringNotContainsString('name="trainer_profile_ids[]"', $chunk);
        $this->assertStringNotContainsString('name="trainer_profile_id"', $chunk);
        $this->assertStringContainsString('name="title"', $chunk);
    }

    public function test_chat_page_create_group_buttons_do_not_create_training_team(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="chatApp"', $html);
        $this->assertStringContainsString('js-open-create-group', $html);
        $this->assertSame(2, substr_count($html, 'js-open-create-group'));
        $this->assertStringContainsString('Создать группу', $html);
        $this->assertStringNotContainsString('admin.team.store', $html);
        $this->assertMatchesRegularExpression(
            '/id="msgInput"[^>]*disabled/',
            $html
        );
    }

    public function test_after_creating_team_inbox_shows_group_title_not_dialog_and_peer_id_null(): void
    {
        $peer = $this->makePeer('UxPeer_');
        $privateId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        $title = 'Учебная '.uniqid('', true);
        $team = $this->storeTeamViaAjax($title);
        $thread = $this->teamThread($team);

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $group = $list->firstWhere('id', $thread->id);
        $private = $list->firstWhere('id', $privateId);
        $this->assertNotNull($group);
        $this->assertNotNull($private);
        $this->assertTrue((bool) $group['is_group']);
        $this->assertNull($group['peer_id']);
        $this->assertSame($title, $group['title']);
        $this->assertNotSame('Диалог', $group['title']);
        $this->assertFalse((bool) $private['is_group']);
        $this->assertSame((int) $peer->id, (int) $private['peer_id']);
    }

    public function test_renaming_team_does_not_change_inbox_title_reopening_still_old_name(): void
    {
        $title = 'ПервоеИмя '.uniqid('', true);
        $team = $this->storeTeamViaAjax($title);
        $thread = $this->teamThread($team);

        $this->patchJson(route('admin.team.update', $team->id), [
            'title' => 'ВтороеИмя '.uniqid('', true),
            'is_enabled' => 1,
        ], $this->teamChatAjaxHeaders())->assertOk();

        $row = $this->inboxRowFor($this->user, (int) $thread->id);
        $this->assertNotNull($row);
        $this->assertSame($title, $row['title']);

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.title', $title)
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.peer_id', null);
    }

    public function test_opening_thread_shows_members_subtitle_not_peer_card_identity(): void
    {
        $team = $this->storeTeamViaAjax('Шапка '.uniqid('', true));
        $thread = $this->teamThread($team);

        $json = $this->getJson(route('chat.api.threads.show', $thread))->assertOk()->json('thread');
        $this->assertTrue((bool) $json['is_group']);
        $this->assertNull($json['peer_id']);
        $this->assertGreaterThanOrEqual(1, (int) $json['members_total']);
        $this->assertStringContainsString('участник', (string) $json['header_subtitle']);
        $this->assertSame('', (string) ($json['peer_presence_label'] ?? ''));
    }

    public function test_contacts_filter_by_team_lists_students_not_the_team_chat(): void
    {
        $team = $this->storeTeamViaAjax('Фильтр '.uniqid('', true));
        $student = $this->makePeer('FiltStu_');
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
        $thread = $this->teamThread($team);

        $contacts = collect(
            $this->getJson(route('chat.api.users', ['team_id' => $team->id]))->assertOk()->json()
        );
        $this->assertNotNull($contacts->firstWhere('id', $student->id));
        $this->assertNull($contacts->firstWhere('id', $thread->id));
        $this->assertNull($contacts->firstWhere('is_group', true));
    }

    public function test_clicking_classmate_opens_one_to_one_not_team_chat(): void
    {
        $team = $this->storeTeamViaAjax('Одноклассник '.uniqid('', true));
        $student = $this->makePeer('ClassStu_');
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);
        $teamThread = $this->teamThread($team);

        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $student->id,
        ]);
        $this->assertContains($created->getStatusCode(), [200, 201]);
        $this->assertNotSame((int) $teamThread->id, (int) $created->json('thread_id'));
        $created->assertJsonPath('thread.is_group', false);
        $created->assertJsonPath('thread.peer_id', $student->id);
    }

    public function test_start_dialog_js_does_not_treat_team_chat_as_private_even_if_peer_id_leaks(): void
    {
        $ui = $this->simulateStartDialogWithTeamChat();
        $this->assertSame(10, (int) $ui['with_private']['opened']);
        $this->assertSame(0, (int) $ui['with_private']['fetch_count']);
        $this->assertSame(1, (int) $ui['only_team']['fetch_count'], 'Командный чат с peer_id не должен считаться личкой');
        $this->assertSame(0, (int) $ui['only_team']['opened']);
        $this->assertSame(1, (int) $ui['team_peer_null']['fetch_count']);
        $this->assertSame(0, (int) $ui['team_peer_null']['opened']);
    }

    public function test_inbox_bump_of_team_chat_does_not_rewrite_existing_one_to_one(): void
    {
        $ui = $this->simulateTeamChatInboxBump();
        $this->assertContains(10, $ui['ids']);
        $this->assertContains(77, $ui['ids']);
        $this->assertSame(11, (int) $ui['private_peer']);
        $this->assertFalse((bool) $ui['private_is_group']);
        $this->assertTrue((bool) $ui['group_is_group']);
        $this->assertTrue((bool) $ui['group_peer_null']);
        $this->assertSame(10, (int) $ui['start_opened']);
        $this->assertSame(0, (int) $ui['start_fetch']);
    }

    public function test_leaving_team_chat_hides_it_from_inbox_but_keeps_thread(): void
    {
        $this->grantPermission($this->user, 'messages.view');
        $team = $this->storeTeamViaAjax('Выход '.uniqid('', true));
        $thread = $this->teamThread($team);

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $this->user]))
            ->assertOk()
            ->assertJsonPath('thread_deleted', false);

        $this->assertNull($thread->fresh()->deleted_at);
        $this->assertFalse(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $this->user->id)
                ->exists()
        );

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertNull($list->firstWhere('id', $thread->id));
    }

    public function test_both_open_create_group_buttons_call_the_same_wizard_not_team_store(): void
    {
        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertSame(1, substr_count($js, "querySelectorAll('.js-open-create-group')"));
        $this->assertStringContainsString('function openCreateGroupWizard(', $js);
        $this->assertStringNotContainsString('/admin/teams', $js);
        $this->assertStringNotContainsString('admin.team.store', $js);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function simulateStartDialogWithTeamChat(): array
    {
        $chatJs = resource_path('js/chat.js');
        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');
function extractFn(src, name) {
    const needle = 'function ' + name + '(';
    const start = src.indexOf(needle);
    if (start < 0) throw new Error('missing ' + name);
    const brace = src.indexOf('{', start);
    let depth = 0;
    for (let j = brace; j < src.length; j++) {
        const ch = src[j];
        if (ch === '{') depth++;
        else if (ch === '}') {
            depth--;
            if (depth === 0) return src.slice(start, j + 1);
        }
    }
    throw new Error('unclosed ' + name);
}
let startDialogBusy = false;
let opened = null;
let fetchCount = 0;
const urls = { storeThread: '/chat/api/threads' };
function headers() { return {}; }
function showContactsError() {}
function fieldError() { return ''; }
function loadThreads() {}
function upsertThread() {}
function contactsModal() { return { hide: function () {} }; }
function openThread(id) { opened = id; }
global.fetch = function () { fetchCount += 1; return Promise.resolve({ ok: true, json: function () { return Promise.resolve({}); } }); };
eval(extractFn(chatJs, 'startDialog'));

let threadsCache = [
    { id: 77, title: 'Группа', is_group: true, peer_id: 11 },
    { id: 10, title: 'Личка', is_group: false, peer_id: 11 }
];
startDialog(11);
const with_private = { opened: opened, fetch_count: fetchCount };

opened = null; fetchCount = 0; startDialogBusy = false;
threadsCache = [{ id: 77, title: 'Группа', is_group: true, peer_id: 11 }];
startDialog(11);
const only_team = { opened: opened || 0, fetch_count: fetchCount };

opened = null; fetchCount = 0; startDialogBusy = false;
threadsCache = [{ id: 77, title: 'Учебная', is_group: true, peer_id: null }];
startDialog(11);
const team_peer_null = { opened: opened || 0, fetch_count: fetchCount };

process.stdout.write(JSON.stringify({ with_private, only_team, team_peer_null }));
JS;

        return $this->runNodeScript($script, $chatJs);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateTeamChatInboxBump(): array
    {
        $chatJs = resource_path('js/chat.js');
        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');
function extractFn(src, name) {
    const needle = 'function ' + name + '(';
    const start = src.indexOf(needle);
    if (start < 0) throw new Error('missing ' + name);
    const brace = src.indexOf('{', start);
    let depth = 0;
    for (let j = brace; j < src.length; j++) {
        const ch = src[j];
        if (ch === '{') depth++;
        else if (ch === '}') {
            depth--;
            if (depth === 0) return src.slice(start, j + 1);
        }
    }
    throw new Error('unclosed ' + name);
}
let threadsCache = [
    { id: 10, title: 'Личка', peer_id: 11, is_group: false, unread_count: 0, last_message: 'hi' }
];
let currentThreadId = null;
let opened = null;
let fetchCount = 0;
let startDialogBusy = false;
function sortThreads(list) { return list; }
function renderThreads() {}
function applyThreadFilter(list) { return list; }
function setUnreadBadge() {}
function headers() { return {}; }
function showContactsError() {}
function fieldError() { return ''; }
function loadThreads() {}
function contactsModal() { return { hide: function () {} }; }
function openThread(id) { opened = id; }
const urls = { storeThread: '/chat/api/threads' };
global.fetch = function () { fetchCount += 1; return Promise.resolve({ ok: true, json: function () { return Promise.resolve({}); } }); };
eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'upsertThread'));
eval(extractFn(chatJs, 'applyInboxBump'));
eval(extractFn(chatJs, 'startDialog'));
applyInboxBump({
    thread_id: 77,
    title: 'Учебная группа',
    avatar: '/img/default-avatar.png',
    peer_id: null,
    is_group: true,
    last_message: '',
    unread_count: 0,
    unread_total: 0
});
startDialog(11);
const priv = threadsCache.find(function (t) { return t.id === 10; }) || {};
const grp = threadsCache.find(function (t) { return t.id === 77; }) || {};
process.stdout.write(JSON.stringify({
    ids: threadsCache.map(function (t) { return t.id; }),
    private_peer: priv.peer_id,
    private_is_group: !!priv.is_group,
    group_is_group: !!grp.is_group,
    group_peer_null: grp.peer_id == null,
    start_opened: opened || 0,
    start_fetch: fetchCount
}));
JS;

        return $this->runNodeScript($script, $chatJs);
    }

    /**
     * @return array<string, mixed>
     */
    private function runNodeScript(string $script, string $chatJs): array
    {
        $tmp = sys_get_temp_dir().'/chat-team-group-ux-'.uniqid('', true).'.cjs';
        file_put_contents($tmp, $script);
        $cmd = 'node '.escapeshellarg($tmp).' '.escapeshellarg($chatJs).' 2>&1';
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        @unlink($tmp);
        $this->assertSame(0, $exit, implode("\n", $output));
        $json = json_decode(implode("\n", $output), true);
        $this->assertIsArray($json, implode("\n", $output));

        return $json;
    }
}
