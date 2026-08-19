<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX-баг шапки: подпись «N участника» / «онлайн» остаётся от предыдущего диалога
 * или рисуется в списке слева. Серверный JSON 200 недостаточен —
 * прогоняем реальный chat.js (openThread / startDialog / closeCurrentThread /
 * renderThreads / fetchGroupMembers / applyInboxBump).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatHeaderSubtitleUxFeatureTest extends ChatTestCase
{
    public function test_idle_header_hides_subtitle_under_empty_title(): void
    {
        $ui = $this->simulateHeaderSubtitleUi();

        $this->assertSame('Выберите диалог', $ui['idle']['title']);
        $this->assertSame('', $ui['idle']['text']);
        $this->assertSame('none', $ui['idle']['display']);
    }

    public function test_opening_group_shows_member_count_and_private_shows_online_not_count(): void
    {
        $ui = $this->simulateHeaderSubtitleUi();

        $this->assertSame('Сборная', $ui['open_group']['title']);
        $this->assertSame('3 участника', $ui['open_group']['text']);
        $this->assertSame('', $ui['open_group']['display']);
        $this->assertSame('Иванов', $ui['open_private']['title']);
        $this->assertSame('онлайн', $ui['open_private']['text']);
        $this->assertSame('', $ui['open_private']['display']);
        $this->assertStringNotContainsString('участник', (string) $ui['open_private']['text']);
    }

    public function test_switching_group_and_private_does_not_leave_previous_subtitle(): void
    {
        $ui = $this->simulateHeaderSubtitleUi();

        $this->assertSame('онлайн', $ui['group_to_private']['text']);
        $this->assertStringNotContainsString('участник', (string) $ui['group_to_private']['text']);
        $this->assertSame('3 участника', $ui['private_to_group']['text']);
        $this->assertNotSame('онлайн', $ui['private_to_group']['text']);
    }

    public function test_closing_dialog_and_missing_subtitle_clear_leftover_label(): void
    {
        $ui = $this->simulateHeaderSubtitleUi();

        $this->assertSame('Выберите диалог', $ui['after_close']['title']);
        $this->assertSame('', $ui['after_close']['text']);
        $this->assertSame('none', $ui['after_close']['display']);
        $this->assertSame('', $ui['missing_subtitle']['text']);
        $this->assertSame('none', $ui['missing_subtitle']['display']);
        $this->assertSame('', $ui['never_seen']['text']);
        $this->assertSame('none', $ui['never_seen']['display']);
    }

    public function test_list_click_and_start_dialog_both_fill_header_subtitle(): void
    {
        $ui = $this->simulateHeaderSubtitleUi();

        $this->assertSame('3 участника', $ui['list_click']['text']);
        $this->assertSame('Сборная', $ui['list_click']['title']);
        $this->assertSame('онлайн', $ui['start_existing']['text']);
        $this->assertSame('онлайн', $ui['start_create']['text']);
        $this->assertSame('Иванов', $ui['start_create']['title']);
        $this->assertGreaterThan(0, (int) $ui['start_create']['store_posts']);
    }

    public function test_thread_list_does_not_render_member_count_or_online_line(): void
    {
        $ui = $this->simulateHeaderSubtitleUi();

        $this->assertStringContainsString('Сборная', (string) $ui['list']['html']);
        $this->assertStringContainsString('Иванов', (string) $ui['list']['html']);
        $this->assertStringNotContainsString('участник', (string) $ui['list']['html']);
        $this->assertStringNotContainsString('онлайн', (string) $ui['list']['html']);
        $this->assertStringNotContainsString('был(а) в сети', (string) $ui['list']['html']);
        $this->assertStringNotContainsString('threadSubtitle', (string) $ui['list']['html']);
    }

    public function test_private_ignores_stray_member_count_and_group_ignores_online_flag(): void
    {
        $ui = $this->simulateHeaderSubtitleUi();

        $this->assertSame('онлайн', $ui['private_stray_count']['text']);
        $this->assertStringNotContainsString('участник', (string) $ui['private_stray_count']['text']);
        $this->assertSame('8 участников', $ui['group_online_flag']['text']);
        $this->assertNotSame('онлайн', $ui['group_online_flag']['text']);
    }

    public function test_header_subtitle_uses_text_content_so_html_from_payload_is_not_executed(): void
    {
        $ui = $this->simulateHeaderSubtitleUi();

        $this->assertSame('<img src=x onerror=alert(1)>', $ui['xss']['text']);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', (string) $ui['xss']['html']);
        $this->assertStringNotContainsString('<img src=x', (string) $ui['xss']['html']);
    }

    public function test_after_kick_or_add_header_count_updates_and_removed_bump_clears_it(): void
    {
        $ui = $this->simulateHeaderSubtitleUi();

        $this->assertSame('2 участника', $ui['after_kick']['text']);
        $this->assertSame('', $ui['after_kick']['display']);
        $this->assertSame('4 участника', $ui['after_add']['text']);
        $this->assertSame('', $ui['removed_bump']['text']);
        $this->assertSame('none', $ui['removed_bump']['display']);
        $this->assertSame('Выберите диалог', $ui['removed_bump']['title']);
        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertStringContainsString('setThreadSubtitle(res.thread.header_subtitle)', $js);
        $this->assertStringContainsString("setThreadSubtitle('')", $js);
        $this->assertStringContainsString('setThreadSubtitle(membersCountLabel(thread.members_total))', $js);
        $this->assertStringContainsString("if (typeof setThreadSubtitle === 'function')", $js);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateHeaderSubtitleUi(): array
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

function makeEl(tag) {
    const el = {
        tagName: String(tag || 'div').toUpperCase(),
        className: '',
        style: {},
        children: [],
        attrs: {},
        disabled: false,
        value: '',
        src: '',
        _text: '',
        _html: '',
        listeners: {},
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
        removeAttribute(k) { delete this.attrs[k]; },
        addEventListener(type, fn) {
            this.listeners[type] = this.listeners[type] || [];
            this.listeners[type].push(fn);
        },
        dispatch(type) {
            (this.listeners[type] || []).forEach(function (fn) { fn(); });
        },
        appendChild(child) {
            this.children.push(child);
            return child;
        },
        focus() {},
        querySelector() { return null; },
        querySelectorAll() { return []; }
    };
    const set = new Set();
    el.classList = {
        add(c) { set.add(c); el.className = Array.from(set).join(' '); },
        remove(c) { set.delete(c); el.className = Array.from(set).join(' '); },
        contains(c) { return set.has(c); }
    };
    return el;
}

const FrozenNow = new Date('2026-08-18T12:00:00');
const RealDate = Date;
function FakeDate(...args) {
    if (args.length === 0) {
        return new RealDate(FrozenNow.getTime());
    }
    return new RealDate(...args);
}
FakeDate.now = () => FrozenNow.getTime();
FakeDate.parse = RealDate.parse;
FakeDate.UTC = RealDate.UTC;
FakeDate.prototype = RealDate.prototype;
global.Date = FakeDate;

const els = {
    threads: makeEl('div'),
    threadSearch: makeEl('input'),
    msgInput: makeEl('input'),
    threadTitle: makeEl('div'),
    threadSubtitle: makeEl('div'),
    threadAvatar: makeEl('img'),
    messagesBox: makeEl('div'),
    msgBodyError: makeEl('div'),
    threadPeerHit: makeEl('div'),
    chatApp: makeEl('div'),
    groupCardTitle: makeEl('div'),
    groupCardAvatar: makeEl('img'),
    groupCardCount: makeEl('div'),
    groupMembersBody: makeEl('tbody'),
    groupCardError: makeEl('div')
};
els.threadSearch.value = '';
els.msgInput.value = '';
els.threadTitle.textContent = 'Выберите диалог';
els.threadSubtitle.style.display = 'none';
els.threadAvatar.style.display = 'none';

global.window = global;
window.matchMedia = function () { return { matches: false }; };

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector() { return null; },
    querySelectorAll() { return []; }
};

const svgTick = '<svg></svg>';
const urls = { threads: '/chat/api/threads', storeThread: '/chat/api/threads' };
const root = els.chatApp;
let currentThreadId = null;
let currentPeerId = null;
let currentIsGroup = false;
let threadsCache = [];
let draftCache = Object.create(null);
let lastPatchedDraft = Object.create(null);
let draftTimer = null;
let lastMessageId = null;
let hasOlder = true;
let startDialogBusy = false;
let groupMembersBusy = false;
let groupMembersHasMore = true;
let groupMembersCanManage = true;
const me = 1;
let contactsHide = 0;
let fetchLog = [];
let showPayloads = {};
let membersPayload = {
    thread: { id: 88, title: 'Сборная', members_total: 3 },
    can_manage: true,
    has_more: false,
    members: []
};

function headers() { return { Accept: 'application/json' }; }
function setComposerEnabled() {}
function setHeaderPeerClickable() {}
function showMsgError() {}
function appendMessage() {}
function scrollBottom() {}
function maybeLoadOlder() {}
function subscribeThread() {}
function startPoll() {}
function setUnreadBadge() {}
function persistDraft() {}
function showContactsError() {}
function loadThreads() {}
function showGroupCardError() {}
function setGroupManageVisible() {}
function appendGroupMembers() {}
function lastGroupMemberUserId() { return 0; }
function maybeFillGroupMembers() {}
function contactsModal() {
    return { hide() { contactsHide += 1; }, show() {} };
}

eval(extractFn(chatJs, 'ticksHtml'));
eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'pad'));
eval(extractFn(chatJs, 'isToday'));
eval(extractFn(chatJs, 'fmtTime'));
eval(extractFn(chatJs, 'sortThreads'));
eval(extractFn(chatJs, 'applyThreadFilter'));
eval(extractFn(chatJs, 'normalizeDraft'));
eval(extractFn(chatJs, 'composerDraftFor'));
eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'renderThreads'));
eval(extractFn(chatJs, 'upsertThread'));
eval(extractFn(chatJs, 'persistLeavingDraft'));
eval(extractFn(chatJs, 'fieldError'));
eval(extractFn(chatJs, 'threadUrl'));
eval(extractFn(chatJs, 'membersCountLabel'));
eval(extractFn(chatJs, 'setThreadSubtitle'));
eval(extractFn(chatJs, 'openThread'));
eval(extractFn(chatJs, 'closeCurrentThread'));
eval(extractFn(chatJs, 'startDialog'));
eval(extractFn(chatJs, 'fetchGroupMembers'));
eval(extractFn(chatJs, 'applyInboxBump'));

function collected(el) {
    return el.children.map(function (c) { return c.innerHTML; }).join('\n') + el.innerHTML;
}

function subtitleState() {
    return {
        text: els.threadSubtitle.textContent,
        display: els.threadSubtitle.style.display || '',
        title: els.threadTitle.textContent
    };
}

function jsonOk(data) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: function () { return Promise.resolve(data); }
    });
}

global.fetch = function (url, opts) {
    const method = String((opts && opts.method) || 'GET').toUpperCase();
    const u = String(url);
    fetchLog.push({ url: u, method: method });
    if (method === 'POST' && u === '/chat/api/threads') {
        return jsonOk({
            ok: true,
            thread_id: 7,
            thread: {
                id: 7,
                title: 'Иванов',
                is_group: false,
                header_subtitle: 'онлайн',
                peer_id: 70,
                members_total: null
            }
        });
    }
    if (u.indexOf('/participants') !== -1) {
        return jsonOk(membersPayload);
    }
    const m = u.match(/\/chat\/api\/threads\/(\d+)$/);
    if (method === 'GET' && m) {
        const id = Number(m[1]);
        return jsonOk(showPayloads[id] || {
            thread: { id: id, title: 'Диалог', header_subtitle: '', is_group: false },
            messages: [],
            unread_total: 0
        });
    }
    return jsonOk({});
};

function sleep(ms) {
    return new Promise(function (resolve) { setTimeout(resolve, ms); });
}

showPayloads[88] = {
    thread: {
        id: 88,
        title: 'Сборная',
        is_group: true,
        header_subtitle: '3 участника',
        members_total: 3,
        peer_id: null,
        peer_is_online: false,
        avatar: '/img/default-avatar.png',
        draft_body: ''
    },
    messages: [],
    unread_total: 0
};
showPayloads[7] = {
    thread: {
        id: 7,
        title: 'Иванов',
        is_group: false,
        header_subtitle: 'онлайн',
        members_total: null,
        peer_id: 70,
        peer_is_online: true,
        avatar: '/img/default-avatar.png',
        draft_body: ''
    },
    messages: [],
    unread_total: 0
};
showPayloads[9] = {
    thread: {
        id: 9,
        title: 'Тихий',
        is_group: false,
        header_subtitle: '',
        members_total: null,
        peer_id: 90,
        draft_body: ''
    },
    messages: [],
    unread_total: 0
};
showPayloads[10] = {
    thread: {
        id: 10,
        title: 'Без поля',
        is_group: false,
        peer_id: 100,
        draft_body: ''
    },
    messages: [],
    unread_total: 0
};
showPayloads[11] = {
    thread: {
        id: 11,
        title: 'ЛичкаСоСчётчиком',
        is_group: false,
        header_subtitle: 'онлайн',
        members_total: 99,
        peer_id: 110,
        draft_body: ''
    },
    messages: [],
    unread_total: 0
};
showPayloads[12] = {
    thread: {
        id: 12,
        title: 'ГруппаОнлайн',
        is_group: true,
        header_subtitle: '8 участников',
        members_total: 8,
        peer_id: null,
        peer_is_online: true,
        draft_body: ''
    },
    messages: [],
    unread_total: 0
};
showPayloads[13] = {
    thread: {
        id: 13,
        title: 'XSS',
        is_group: true,
        header_subtitle: '<img src=x onerror=alert(1)>',
        members_total: 3,
        peer_id: null,
        draft_body: ''
    },
    messages: [],
    unread_total: 0
};

(async function main() {
    const idle = subtitleState();

    openThread(88);
    await sleep(30);
    const open_group = subtitleState();

    openThread(7);
    await sleep(30);
    const group_to_private = subtitleState();
    const open_private = subtitleState();

    openThread(88);
    await sleep(30);
    const private_to_group = subtitleState();

    closeCurrentThread();
    const after_close = subtitleState();

    openThread(88);
    await sleep(30);
    openThread(9);
    await sleep(30);
    const never_seen = subtitleState();

    openThread(88);
    await sleep(30);
    openThread(10);
    await sleep(30);
    const missing_subtitle = subtitleState();

    threadsCache = [
        {
            id: 88,
            title: 'Сборная',
            is_group: true,
            members_total: 3,
            header_subtitle: '3 участника',
            last_message: 'привет',
            last_message_time: '2026-08-01 09:30:00',
            unread_count: 0,
            peer_is_online: false
        },
        {
            id: 7,
            title: 'Иванов',
            is_group: false,
            members_total: 99,
            header_subtitle: 'онлайн',
            last_message: 'hi',
            last_message_time: '2026-08-01 09:30:00',
            unread_count: 0,
            peer_id: 70,
            peer_is_online: true
        }
    ];
    els.threads.innerHTML = '';
    els.threads.children = [];
    renderThreads(threadsCache);
    const list = { html: collected(els.threads) };

    closeCurrentThread();
    els.threads.children[0].dispatch('click');
    await sleep(30);
    const list_click = subtitleState();

    closeCurrentThread();
    contactsHide = 0;
    fetchLog = [];
    threadsCache = [{ id: 7, is_group: false, peer_id: 70, title: 'Иванов' }];
    startDialog(70);
    await sleep(30);
    const start_existing = Object.assign(subtitleState(), { store_posts: fetchLog.filter(function (c) { return c.method === 'POST'; }).length });

    closeCurrentThread();
    fetchLog = [];
    threadsCache = [{ id: 88, is_group: true, peer_id: 70, title: 'Сборная' }];
    startDialog(70);
    await sleep(30);
    const start_create = Object.assign(subtitleState(), {
        store_posts: fetchLog.filter(function (c) { return c.method === 'POST'; }).length
    });

    openThread(11);
    await sleep(30);
    const private_stray_count = subtitleState();

    openThread(12);
    await sleep(30);
    const group_online_flag = subtitleState();

    openThread(13);
    await sleep(30);
    const xss = {
        text: els.threadSubtitle.textContent,
        html: els.threadSubtitle.innerHTML
    };

    currentThreadId = 88;
    currentIsGroup = true;
    groupMembersBusy = false;
    membersPayload = {
        thread: { id: 88, title: 'Сборная', members_total: 2 },
        can_manage: true,
        has_more: false,
        members: []
    };
    fetchGroupMembers(true);
    await sleep(30);
    const after_kick = subtitleState();

    membersPayload = {
        thread: { id: 88, title: 'Сборная', members_total: 4 },
        can_manage: true,
        has_more: false,
        members: []
    };
    groupMembersBusy = false;
    fetchGroupMembers(true);
    await sleep(30);
    const after_add = subtitleState();

    threadsCache = [{ id: 88, title: 'Сборная', is_group: true }];
    currentThreadId = 88;
    applyInboxBump({ thread_id: 88, removed: true, unread_total: 0 });
    const removed_bump = subtitleState();

    process.stdout.write(JSON.stringify({
        idle: idle,
        open_group: open_group,
        open_private: open_private,
        group_to_private: group_to_private,
        private_to_group: private_to_group,
        after_close: after_close,
        never_seen: never_seen,
        missing_subtitle: missing_subtitle,
        list: list,
        list_click: list_click,
        start_existing: start_existing,
        start_create: start_create,
        private_stray_count: private_stray_count,
        group_online_flag: group_online_flag,
        xss: xss,
        after_kick: after_kick,
        after_add: after_add,
        removed_bump: removed_bump
    }));
})().catch(function (e) {
    process.stderr.write(String(e && e.stack ? e.stack : e));
    process.exit(1);
});
JS;

        $path = sys_get_temp_dir().'/chat-header-subtitle-ux-'.uniqid('', true).'.cjs';
        file_put_contents($path, $script);

        try {
            $output = [];
            $exitCode = 0;
            exec(
                'node '.escapeshellarg($path).' '.escapeshellarg($chatJs).' 2>&1',
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
}
