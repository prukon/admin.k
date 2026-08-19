<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX идентичности 1-на-1: клик по контакту не открывает группу,
 * «осиротевший» диалог без peer_id не склеивается с чужим человеком,
 * ответ store с тем же id не плодит вторую строку в списке.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatPrivateThreadIdentityUxFeatureTest extends ChatTestCase
{
    public function test_contact_click_opens_private_thread_not_group_with_same_person(): void
    {
        $ui = $this->simulateIdentityUi();

        $this->assertSame(10, (int) $ui['with_private']['opened']);
        $this->assertSame(0, (int) $ui['with_private']['fetch_count']);
        $this->assertSame(1, (int) $ui['with_private']['modal_hidden']);

        $this->assertNull($ui['only_group']['opened']);
        $this->assertSame(1, (int) $ui['only_group']['fetch_count'], 'Группа с peer_id не должна считаться личкой — нужен POST store');
        $this->assertSame(0, (int) $ui['only_group']['modal_hidden']);
    }

    public function test_orphaned_dialog_without_peer_id_does_not_match_contact_and_fetch_reuses_same_row(): void
    {
        $ui = $this->simulateIdentityUi();

        $this->assertSame(1, (int) $ui['orphan']['fetch_count'], 'Строка «Диалог» без peer_id — не чужой контакт, нужен POST');
        $this->assertNull($ui['orphan']['opened_before_fetch']);
        $this->assertSame(5, (int) $ui['after_restore']['opened']);
        $this->assertCount(1, $ui['after_restore']['ids']);
        $this->assertSame(5, (int) $ui['after_restore']['ids'][0]);
        $this->assertSame(11, (int) $ui['after_restore']['peer_id']);
        $this->assertSame('Иванов Иван', $ui['after_restore']['title']);
        $this->assertFalse((bool) $ui['after_restore']['is_group']);
    }

    public function test_new_thread_id_from_store_would_leave_orphan_row_so_server_must_reuse_id(): void
    {
        $ui = $this->simulateIdentityUi();

        $this->assertCount(2, $ui['wrong_new_id']['ids'], 'Если store плодит новый id — в списке остаётся «Диалог» и новая личка');
        $this->assertContains(5, $ui['wrong_new_id']['ids']);
        $this->assertContains(6, $ui['wrong_new_id']['ids']);
    }

    public function test_desktop_and_mobile_contacts_share_the_same_start_dialog_path(): void
    {
        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertSame(
            1,
            substr_count($js, 'startDialog(Number(u.id))'),
            'Клик по контакту (модалка и мобильная вкладка через renderContacts) должен идти в один startDialog'
        );
        $this->assertStringContainsString('function renderContacts(', $js);
        $this->assertStringContainsString('return !t.is_group && Number(t.peer_id) === Number(userId);', $js);
        $this->assertStringContainsString('if (patch.peer_id && !patch.is_group) {', $js);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateIdentityUi(): array
    {
        $chatJs = resource_path('js/chat.js');
        $this->assertFileExists($chatJs);

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');

function extractFn(src, name) {
    const needle = 'function ' + name + '(';
    const start = src.indexOf(needle);
    if (start < 0) {
        throw new Error('missing ' + name);
    }
    const brace = src.indexOf('{', start);
    let depth = 0;
    for (let j = brace; j < src.length; j++) {
        const ch = src[j];
        if (ch === '{') depth++;
        else if (ch === '}') {
            depth--;
            if (depth === 0) {
                return src.slice(start, j + 1);
            }
        }
    }
    throw new Error('unclosed ' + name);
}

function sortThreads(list) { return list; }
function renderThreads() {}
function applyThreadFilter(list) { return list; }

let threadsCache = [
    { id: 77, title: 'Сборная', is_group: true, peer_id: 11 },
    { id: 10, title: 'Личка', is_group: false, peer_id: 11 }
];
let startDialogBusy = false;
let opened = null;
let modalHidden = 0;
let fetchCount = 0;
const urls = { storeThread: '/chat/api/threads' };
function headers() { return {}; }
function showContactsError() {}
function fieldError() { return ''; }
function loadThreads() {}
function contactsModal() {
    return { hide: function () { modalHidden += 1; } };
}
function openThread(id) { opened = id; }
global.fetch = function () {
    fetchCount += 1;
    return Promise.resolve({ ok: true, json: function () { return Promise.resolve({}); } });
};

eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'upsertThread'));
eval(extractFn(chatJs, 'startDialog'));

startDialog(11);
const with_private = { opened: opened, modal_hidden: modalHidden, fetch_count: fetchCount };

threadsCache = [{ id: 77, title: 'Сборная', is_group: true, peer_id: 11 }];
startDialogBusy = false;
opened = null;
modalHidden = 0;
fetchCount = 0;
startDialog(11);
const only_group = { opened: opened, modal_hidden: modalHidden, fetch_count: fetchCount };

threadsCache = [{ id: 5, title: 'Диалог', is_group: false, peer_id: null, last_message: 'hi' }];
startDialogBusy = false;
opened = null;
modalHidden = 0;
fetchCount = 0;
startDialog(11);
const orphan = { fetch_count: fetchCount, opened_before_fetch: opened };

upsertThread({
    id: 5,
    title: 'Иванов Иван',
    peer_id: 11,
    is_group: false,
    unread_count: 0
});
openThread(5);
const after_restore = {
    opened: opened,
    ids: threadsCache.map(function (t) { return t.id; }),
    peer_id: (threadsCache[0] || {}).peer_id,
    title: (threadsCache[0] || {}).title,
    is_group: !!(threadsCache[0] || {}).is_group
};

threadsCache = [{ id: 5, title: 'Диалог', is_group: false, peer_id: null, last_message: 'hi' }];
upsertThread({
    id: 6,
    title: 'Иванов Иван',
    peer_id: 11,
    is_group: false,
    unread_count: 0
});
const wrong_new_id = {
    ids: threadsCache.map(function (t) { return t.id; })
};

process.stdout.write(JSON.stringify({
    with_private: with_private,
    only_group: only_group,
    orphan: orphan,
    after_restore: after_restore,
    wrong_new_id: wrong_new_id
}));
JS;

        $tmp = sys_get_temp_dir().'/chat-private-identity-ux-'.uniqid('', true).'.cjs';
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
