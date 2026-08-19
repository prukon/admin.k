<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX-баги сплита мобильного inbox: Echo не красит вкладки суммой,
 * bump группы не попадает в «Личные», leftover «Диалог» не на «Чаты»,
 * назад из группы остаётся на «Чаты», создание группы открывает вкладку «Чаты».
 *
 * Серверный 200 / node --check недостаточны — прогоняем реальный chat.js.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMobileInboxSplitUxFeatureTest extends ChatTestCase
{
    public function test_echo_total_unread_does_not_overwrite_split_nav_badges(): void
    {
        $ui = $this->simulateInboxSplitUx();

        $this->assertSame('9', (string) $ui['echo_only']['sidebar']);
        $this->assertSame('2', (string) $ui['echo_only']['private']);
        $this->assertSame('3', (string) $ui['echo_only']['group']);
        $this->assertSame('9', (string) $ui['set_unread']['sidebar']);
        $this->assertSame('2', (string) $ui['set_unread']['private']);
        $this->assertSame('3', (string) $ui['set_unread']['group']);
    }

    public function test_back_from_group_dialog_stays_on_chats_tab(): void
    {
        $ui = $this->simulateInboxSplitUx();

        $this->assertSame('1', (string) $ui['group_open']['is_dialog_open']);
        $this->assertSame('groups', $ui['group_open']['tab']);
        $this->assertSame('0', (string) $ui['group_back']['is_dialog_open']);
        $this->assertSame('groups', $ui['group_back']['tab']);
        $this->assertSame('messages', $ui['private_back']['tab']);
    }

    public function test_inbox_bump_of_group_does_not_appear_in_personal_list_on_mobile(): void
    {
        $ui = $this->simulateInboxSplitUx();

        $this->assertStringContainsString('bump-g', (string) $ui['bump_group']['groups']);
        $this->assertStringNotContainsString('bump-g', (string) $ui['bump_group']['personal']);
        $this->assertStringContainsString('Иванов Иван', (string) $ui['bump_group']['personal']);
        $this->assertSame('4', (string) $ui['bump_group']['group_badge']);
        $this->assertSame('2', (string) $ui['bump_group']['private_badge']);
    }

    public function test_inbox_bump_of_private_does_not_appear_in_groups_list_on_mobile(): void
    {
        $ui = $this->simulateInboxSplitUx();

        $this->assertStringContainsString('bump-p', (string) $ui['bump_private']['personal']);
        $this->assertStringNotContainsString('bump-p', (string) $ui['bump_private']['groups']);
        $this->assertStringContainsString('Сборная', (string) $ui['bump_private']['groups']);
    }

    public function test_removed_group_bump_drops_chats_row_and_does_not_move_it_to_personal(): void
    {
        $ui = $this->simulateInboxSplitUx();

        $this->assertStringNotContainsString('Сборная', (string) $ui['bump_removed']['groups']);
        $this->assertStringNotContainsString('Сборная', (string) $ui['bump_removed']['personal']);
        $this->assertStringContainsString('Групп нет', (string) $ui['bump_removed']['groups']);
        $this->assertStringContainsString('Иванов Иван', (string) $ui['bump_removed']['personal']);
        $this->assertSame('0', (string) $ui['bump_removed']['group_badge']);
        $this->assertSame('none', (string) $ui['bump_removed']['group_display']);
    }

    public function test_opening_a_group_clears_only_that_chat_from_chats_badge(): void
    {
        $ui = $this->simulateInboxSplitUx();

        $this->assertSame('5', (string) $ui['open_group_badges']['group']);
        $this->assertSame('2', (string) $ui['open_group_badges']['private']);
        $this->assertNotSame('8', (string) $ui['open_group_badges']['group']);
        $this->assertNotSame('10', (string) $ui['open_group_badges']['group']);
    }

    public function test_orphaned_dialog_stays_in_personal_messages_not_chats(): void
    {
        $ui = $this->simulateInboxSplitUx();

        $this->assertStringContainsString('Диалог', (string) $ui['leftover']['personal']);
        $this->assertStringNotContainsString('Диалогов нет', (string) $ui['leftover']['personal']);
        $this->assertStringNotContainsString('Диалог', (string) $ui['leftover']['groups']);
        $this->assertStringContainsString('Групп нет', (string) $ui['leftover']['groups']);
        $this->assertStringContainsString('Группа', (string) $ui['empty_group_title']['groups']);
        $this->assertStringNotContainsString('Групп нет', (string) $ui['empty_group_title']['groups']);
        $this->assertStringNotContainsString('Группа', (string) $ui['empty_group_title']['personal']);
        $this->assertStringContainsString('Диалогов нет', (string) $ui['empty_group_title']['personal']);
    }

    public function test_creating_group_on_mobile_opens_it_on_chats_tab(): void
    {
        $ui = $this->simulateInboxSplitUx();

        $this->assertSame('groups', $ui['create_group']['tab']);
        $this->assertSame('1', (string) $ui['create_group']['is_dialog_open']);
        $this->assertStringContainsString('Новая сборная', (string) $ui['create_group']['groups']);
        $this->assertStringNotContainsString('Новая сборная', (string) $ui['create_group']['personal']);
        $this->assertStringContainsString('Иванов Иван', (string) $ui['create_group']['personal']);
    }

    public function test_widening_to_desktop_puts_groups_back_into_main_list(): void
    {
        $ui = $this->simulateInboxSplitUx();

        $this->assertStringContainsString('Сборная', (string) $ui['desktop_after_resize']['personal']);
        $this->assertStringContainsString('Иванов Иван', (string) $ui['desktop_after_resize']['personal']);
        $this->assertStringContainsString('Сборная', (string) $ui['desktop_after_resize']['groups']);
        $this->assertStringNotContainsString('Иванов Иван', (string) $ui['desktop_after_resize']['groups']);
        $this->assertStringNotContainsString('Сборная', (string) $ui['mobile_before_resize']['personal']);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateInboxSplitUx(): array
    {
        $chatJs = resource_path('js/chat.js');
        $echoBlade = resource_path('views/includes/chat/echo.blade.php');
        $this->assertFileExists($chatJs);
        $this->assertFileExists($echoBlade);

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');
const echoJs = fs.readFileSync(process.argv[3], 'utf8');
global.window = global;

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

function makeEl(tag) {
    const el = {
        tagName: String(tag || 'div').toUpperCase(),
        className: '',
        value: '',
        children: [],
        parentElement: null,
        attrs: {},
        style: {},
        _text: '',
        _html: '',
        get textContent() { return this._text; },
        set textContent(v) {
            this._text = v == null ? '' : String(v);
            this._html = this._text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        },
        get innerHTML() { return this._html; },
        set innerHTML(v) {
            this._html = v == null ? '' : String(v);
            if (this._html === '') {
                this.children = [];
            }
        },
        setAttribute(k, v) { this.attrs[k] = String(v); },
        getAttribute(k) {
            return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
        },
        addEventListener() {},
        appendChild(child) {
            child.parentElement = this;
            this.children.push(child);
            return child;
        }
    };
    const set = new Set();
    el.classList = {
        add(c) { set.add(c); el.className = Array.from(set).join(' '); },
        remove(c) { set.delete(c); el.className = Array.from(set).join(' '); },
        contains(c) { return set.has(c); },
        toggle(c, on) {
            if (on) { this.add(c); } else { this.remove(c); }
        }
    };
    return el;
}

function collected(el) {
    return el.children.map(function (c) { return c.innerHTML; }).join('\n') + el.innerHTML;
}

function resetLists() {
    els.threads.innerHTML = '';
    els.threads.children = [];
    els.groupThreads.innerHTML = '';
    els.groupThreads.children = [];
}

let mobile = true;
window.matchMedia = function (q) {
    return { matches: mobile && String(q).indexOf('991.98px') !== -1 };
};

const els = {
    chatApp: makeEl('div'),
    threads: makeEl('div'),
    groupThreads: makeEl('div'),
    threadSearch: makeEl('input'),
    chatPrivateUnreadBadge: makeEl('span'),
    chatGroupUnreadBadge: makeEl('span'),
    sidebarBadge: makeEl('span'),
    threadTitle: makeEl('div'),
    threadAvatar: makeEl('img'),
    messagesBox: makeEl('div'),
    msgInput: makeEl('input'),
    msgBodyError: makeEl('div'),
    threadPeerHit: makeEl('div')
};
els.chatApp.setAttribute('data-mobile-tab', 'messages');
els.threadSearch.value = '';
els.msgInput.value = '';
els.threadAvatar.style = { display: 'none' };
els.chatPrivateUnreadBadge.style.display = 'none';
els.chatGroupUnreadBadge.style.display = 'none';
els.sidebarBadge.style.display = 'none';
els.sidebarBadge.className = 'js-chat-unread-count';
els.chatPrivateUnreadBadge.className = 'js-chat-private-unread-count';
els.chatGroupUnreadBadge.className = 'js-chat-group-unread-count';

const root = els.chatApp;
const svgTick = '<svg></svg>';
let currentThreadId = null;
let currentPeerId = null;
let currentIsGroup = false;
let lastMessageId = null;
let hasOlder = true;
let threadsCache = [];

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector() { return null; },
    querySelectorAll(sel) {
        if (sel === '.js-chat-unread-count') {
            return [els.sidebarBadge];
        }
        if (sel === '.js-chat-private-unread-count') {
            return [els.chatPrivateUnreadBadge];
        }
        if (sel === '.js-chat-group-unread-count') {
            return [els.chatGroupUnreadBadge];
        }
        return [];
    }
};

function persistLeavingDraft() {}
function setComposerEnabled() {}
function setHeaderPeerClickable() {}
function showMsgError() {}
function appendMessage() {}
function scrollBottom() {}
function maybeLoadOlder() {}
function subscribeThread() {}
function startPoll() {}
function setThreadSubtitle() {}
function composerDraftFor() { return ''; }
function closeCurrentThread() { currentThreadId = null; }
function headers() { return { Accept: 'application/json' }; }
function threadUrl(id, suffix) { return '/chat/api/threads/' + id + (suffix || ''); }
function setMobileTabButtons() {}

eval(extractFn(chatJs, 'ticksHtml'));
eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'pad'));
eval(extractFn(chatJs, 'isToday'));
eval(extractFn(chatJs, 'fmtTime'));
eval(extractFn(chatJs, 'normalizeDraft'));
eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'applyThreadFilter'));
eval(extractFn(chatJs, 'setCountBadge'));
eval(extractFn(chatJs, 'paintSplitNavBadges'));
eval(extractFn(chatJs, 'renderThreads'));
eval(extractFn(chatJs, 'sortThreads'));
eval(extractFn(chatJs, 'upsertThread'));
eval(extractFn(chatJs, 'setUnreadBadge'));
eval(extractFn(chatJs, 'openThread'));
eval(extractFn(chatJs, 'leaveMobileDialog'));
eval(extractFn(chatJs, 'applyInboxBump'));

const applyStart = echoJs.indexOf('function applyUnread(count)');
const applyEnd = echoJs.indexOf('window.KidsCrmChatSetUnread');
if (applyStart < 0 || applyEnd < 0) {
    throw new Error('missing applyUnread');
}
eval(echoJs.slice(applyStart, applyEnd));
window.KidsCrmChatSetUnread = applyUnread;

function jsonOk(data) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: function () { return Promise.resolve(data); }
    });
}

global.fetch = function (url) {
    const u = String(url);
    const m = u.match(/\/chat\/api\/threads\/(\d+)$/);
    const id = m ? Number(m[1]) : 0;
    const isGroup = id === 1 || id === 77;
    return jsonOk({
        thread: {
            id: id || 1,
            title: id === 77 ? 'Новая сборная' : (isGroup ? 'Сборная' : 'Иванов Иван'),
            avatar: '/img/default-avatar.png',
            peer_id: isGroup ? null : 9,
            is_group: isGroup,
            draft_body: ''
        },
        messages: [],
        unread_total: 0
    });
};

function wait() {
    return new Promise(function (resolve) { setImmediate(resolve); });
}

function snapshotBadges() {
    return {
        sidebar: els.sidebarBadge.textContent,
        private: els.chatPrivateUnreadBadge.textContent,
        group: els.chatGroupUnreadBadge.textContent,
        private_display: els.chatPrivateUnreadBadge.style.display || '',
        group_display: els.chatGroupUnreadBadge.style.display || ''
    };
}

(async function () {
    const mixed = [
        { id: 1, title: 'Сборная', is_group: true, last_message: 'g', unread_count: 3 },
        { id: 2, title: 'Иванов Иван', is_group: false, peer_id: 9, last_message: 'p', unread_count: 2 }
    ];

    els.sidebarBadge.textContent = '5';
    els.chatPrivateUnreadBadge.textContent = '2';
    els.chatGroupUnreadBadge.textContent = '3';
    applyUnread(9);
    const echo_only = snapshotBadges();

    threadsCache = mixed.map(function (t) { return Object.assign({}, t); });
    currentThreadId = null;
    setUnreadBadge(9);
    const set_unread = snapshotBadges();

    mobile = true;
    threadsCache = mixed.map(function (t) { return Object.assign({}, t); });
    currentThreadId = null;
    resetLists();
    renderThreads(applyThreadFilter(threadsCache));
    applyInboxBump({
        thread_id: 1,
        is_group: true,
        title: 'Сборная',
        last_message: 'bump-g',
        unread_count: 4,
        unread_total: 6
    });
    const bump_group = {
        personal: collected(els.threads),
        groups: collected(els.groupThreads),
        private_badge: els.chatPrivateUnreadBadge.textContent,
        group_badge: els.chatGroupUnreadBadge.textContent
    };

    applyInboxBump({
        thread_id: 2,
        is_group: false,
        peer_id: 9,
        title: 'Иванов Иван',
        last_message: 'bump-p',
        unread_count: 2,
        unread_total: 6
    });
    const bump_private = {
        personal: collected(els.threads),
        groups: collected(els.groupThreads)
    };

    applyInboxBump({ thread_id: 1, removed: true, unread_total: 2 });
    const bump_removed = {
        personal: collected(els.threads),
        groups: collected(els.groupThreads),
        group_badge: els.chatGroupUnreadBadge.textContent,
        group_display: els.chatGroupUnreadBadge.style.display === 'none' ? 'none' : (els.chatGroupUnreadBadge.style.display || '')
    };

    threadsCache = [
        { id: 1, title: 'Сборная', is_group: true, unread_count: 3 },
        { id: 4, title: 'Вторая', is_group: true, unread_count: 5 },
        { id: 2, title: 'Иванов Иван', is_group: false, peer_id: 9, unread_count: 2 }
    ];
    currentThreadId = 1;
    paintSplitNavBadges();
    const open_group_badges = snapshotBadges();

    mobile = true;
    currentThreadId = null;
    threadsCache = [{ id: 9, title: '', is_group: false, peer_id: null, unread_count: 0 }];
    resetLists();
    renderThreads(applyThreadFilter(threadsCache));
    const leftover = {
        personal: collected(els.threads),
        groups: collected(els.groupThreads)
    };

    threadsCache = [{ id: 10, title: '', is_group: true, peer_id: null, unread_count: 0 }];
    resetLists();
    renderThreads(applyThreadFilter(threadsCache));
    const empty_group_title = {
        personal: collected(els.threads),
        groups: collected(els.groupThreads)
    };

    threadsCache = mixed.map(function (t) { return Object.assign({}, t); });
    currentThreadId = null;
    resetLists();
    renderThreads(applyThreadFilter(threadsCache));
    const mobile_before_resize = {
        personal: collected(els.threads),
        groups: collected(els.groupThreads)
    };
    mobile = false;
    resetLists();
    renderThreads(applyThreadFilter(threadsCache));
    const desktop_after_resize = {
        personal: collected(els.threads),
        groups: collected(els.groupThreads)
    };

    mobile = true;
    threadsCache = [
        { id: 2, title: 'Иванов Иван', is_group: false, peer_id: 9, unread_count: 0 }
    ];
    currentThreadId = null;
    els.chatApp.setAttribute('data-mobile-tab', 'messages');
    els.chatApp.classList.remove('is-dialog-open');
    resetLists();
    upsertThread({ id: 77, is_group: true, title: 'Новая сборная', peer_id: null, unread_count: 0 });
    await openThread(77);
    await wait();
    const create_group = {
        tab: els.chatApp.getAttribute('data-mobile-tab'),
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        personal: collected(els.threads),
        groups: collected(els.groupThreads)
    };

    threadsCache = [{ id: 1, is_group: true, title: 'Сборная', peer_id: null }];
    els.chatApp.classList.remove('is-dialog-open');
    els.chatApp.setAttribute('data-mobile-tab', 'groups');
    await openThread(1);
    await wait();
    const group_open = {
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        tab: els.chatApp.getAttribute('data-mobile-tab')
    };
    leaveMobileDialog();
    const group_back = {
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        tab: els.chatApp.getAttribute('data-mobile-tab')
    };

    threadsCache = [{ id: 2, is_group: false, title: 'Иванов Иван', peer_id: 9 }];
    els.chatApp.classList.remove('is-dialog-open');
    els.chatApp.setAttribute('data-mobile-tab', 'messages');
    await openThread(2);
    await wait();
    leaveMobileDialog();
    const private_back = {
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        tab: els.chatApp.getAttribute('data-mobile-tab')
    };

    process.stdout.write(JSON.stringify({
        echo_only: echo_only,
        set_unread: set_unread,
        bump_group: bump_group,
        bump_private: bump_private,
        bump_removed: bump_removed,
        open_group_badges: open_group_badges,
        leftover: leftover,
        empty_group_title: empty_group_title,
        mobile_before_resize: mobile_before_resize,
        desktop_after_resize: desktop_after_resize,
        create_group: create_group,
        group_open: group_open,
        group_back: group_back,
        private_back: private_back
    }));
})().catch(function (err) {
    console.error(err && err.stack ? err.stack : err);
    process.exit(1);
});
JS;

        $tmp = sys_get_temp_dir().'/chat-mobile-inbox-split-ux-'.uniqid('', true).'.cjs';
        file_put_contents($tmp, $script);
        $cmd = 'node '.escapeshellarg($tmp).' '.escapeshellarg($chatJs).' '.escapeshellarg($echoBlade).' 2>&1';
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
