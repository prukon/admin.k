<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * Мобильный чат (планшет 992px): вкладки, диалог на весь экран, карточка аккаунта.
 * Серверный 200 недостаточен — прогоняем реальный chat.js.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMobileUxFeatureTest extends ChatTestCase
{
    public function test_open_thread_on_mobile_shows_dialog_and_desktop_does_not(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('1', $ui['mobile_open']['is_dialog_open']);
        $this->assertSame('messages', $ui['mobile_open']['tab']);
        $this->assertSame('0', $ui['desktop_open']['is_dialog_open']);
    }

    public function test_back_button_returns_to_thread_list_without_clearing_tab(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('0', $ui['back']['is_dialog_open']);
        $this->assertSame('messages', $ui['back']['tab']);
    }

    public function test_contacts_tab_loads_list_without_opening_modal(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('contacts', $ui['contacts_tab']['tab']);
        $this->assertSame('0', $ui['contacts_tab']['is_dialog_open']);
        $this->assertSame(0, (int) $ui['contacts_tab']['modal_show']);
        $this->assertStringContainsString('/chat/api/users', (string) $ui['contacts_tab']['url']);
        $this->assertSame('chatPaneContacts', $ui['contacts_tab']['mount_parent']);
    }

    public function test_account_tab_loads_own_card_into_account_pane_not_peer_modal(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('account', $ui['account_tab']['tab']);
        $this->assertStringContainsString('/chat/api/users/7', (string) $ui['account_tab']['url']);
        $this->assertStringContainsString('peer-card', (string) $ui['account_tab']['account_html']);
        $this->assertStringContainsString('Свой Аккаунт', (string) $ui['account_tab']['account_html']);
        $this->assertSame('', (string) $ui['account_tab']['peer_html']);
        $this->assertSame('', (string) $ui['account_tab']['error']);
        $this->assertStringNotContainsString('account-settings', (string) $ui['account_tab']['url']);
    }

    public function test_groups_tab_does_not_fetch_or_open_contacts_modal(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('groups', $ui['groups_tab']['tab']);
        $this->assertSame('0', $ui['groups_tab']['is_dialog_open']);
        $this->assertSame(0, (int) $ui['groups_tab']['modal_show']);
        $this->assertSame(0, (int) $ui['groups_tab']['fetch_count']);
    }

    public function test_opening_group_thread_on_mobile_stays_on_groups_tab(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('1', $ui['group_open']['is_dialog_open']);
        $this->assertSame('groups', $ui['group_open']['tab']);
        $this->assertSame('messages', $ui['mobile_open']['tab']);
    }

    public function test_back_from_group_dialog_keeps_chats_tab(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('0', $ui['group_back']['is_dialog_open']);
        $this->assertSame('groups', $ui['group_back']['tab']);
        $this->assertSame('messages', $ui['back']['tab']);
    }

    public function test_mobile_lists_split_private_and_group_threads_and_badges(): void
    {
        $ui = $this->simulateMobileInboxSplit();

        $this->assertStringContainsString('Иванов Иван', (string) $ui['mobile_threads']);
        $this->assertStringNotContainsString('Сборная', (string) $ui['mobile_threads']);
        $this->assertStringContainsString('Сборная', (string) $ui['mobile_groups']);
        $this->assertStringNotContainsString('Иванов Иван', (string) $ui['mobile_groups']);
        $this->assertSame('2', (string) $ui['private_badge']);
        $this->assertSame('3', (string) $ui['group_badge']);
        $this->assertSame('', (string) $ui['private_display']);
        $this->assertSame('', (string) $ui['group_display']);

        $this->assertStringContainsString('Иванов Иван', (string) $ui['desktop_threads']);
        $this->assertStringContainsString('Сборная', (string) $ui['desktop_threads']);
        $this->assertStringContainsString('Сборная', (string) $ui['desktop_groups']);
        $this->assertStringNotContainsString('Иванов Иван', (string) $ui['desktop_groups']);

        $this->assertStringContainsString('Групп нет', (string) $ui['empty_groups']);
        $this->assertStringContainsString('Диалогов нет', (string) $ui['empty_private']);
        $this->assertStringContainsString('Сборная', (string) $ui['search_groups']);
        $this->assertStringNotContainsString('Сборная', (string) $ui['search_private']);
    }

    public function test_desktop_contacts_button_opens_modal_and_mobile_tab_does_not(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame(1, (int) $ui['desktop_contacts']['modal_show']);
        $this->assertSame('contactsModalBody', $ui['desktop_contacts']['mount_parent']);
        $this->assertSame(0, (int) $ui['desktop_set_tab']['modal_show']);
        $this->assertSame(0, (int) $ui['contacts_tab']['modal_show']);
    }

    public function test_account_card_error_stays_in_account_pane_not_peer_modal(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('Нет доступа.', $ui['account_error']['error']);
        $this->assertSame('', (string) $ui['account_error']['peer_error']);
        $this->assertSame('', (string) $ui['account_error']['peer_html']);
        $this->assertStringNotContainsString('account-settings', (string) $ui['account_error']['url']);
    }

    public function test_clicking_contact_on_mobile_opens_fullscreen_dialog(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('1', $ui['contact_click']['is_dialog_open']);
        $this->assertSame('messages', $ui['contact_click']['tab']);
        $this->assertSame(1, (int) $ui['contact_click']['modal_hide']);
    }

    public function test_switching_dialog_on_mobile_clears_previous_messages_before_fetch(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('', (string) $ui['switch_mobile_before']);
        $this->assertSame('STALE_DESKTOP', (string) $ui['switch_desktop_before']);
    }

    public function test_switching_to_groups_closes_open_dialog(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('0', $ui['tab_closes_dialog']['is_dialog_open']);
        $this->assertSame('groups', $ui['tab_closes_dialog']['tab']);
    }

    public function test_widening_to_desktop_clears_dialog_and_returns_contacts_to_modal(): void
    {
        $ui = $this->simulateMobileUi();

        $this->assertSame('0', $ui['desktop_resize']['is_dialog_open']);
        $this->assertSame('contactsModalBody', $ui['desktop_resize']['mount_parent']);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateMobileUi(): array
    {
        $chatJs = resource_path('js/chat.js');
        $this->assertFileExists($chatJs);

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');
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
        innerHTML: '',
        textContent: '',
        value: '',
        children: [],
        parentElement: null,
        attrs: {},
        style: {},
        setAttribute(k, v) { this.attrs[k] = String(v); },
        getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; },
        removeAttribute(k) { delete this.attrs[k]; },
        addEventListener() {},
        appendChild(child) {
            if (child.parentElement && Array.isArray(child.parentElement.children)) {
                child.parentElement.children = child.parentElement.children.filter(function (c) { return c !== child; });
            }
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

let mobile = true;
window.matchMedia = function (q) {
    return { matches: mobile && String(q).indexOf('991.98px') !== -1 };
};

const navBtns = ['contacts', 'messages', 'groups', 'account'].map(function (tab) {
    const btn = makeEl('button');
    btn.className = 'chat-mobile-nav-btn' + (tab === 'messages' ? ' is-active' : '');
    btn.setAttribute('data-mobile-tab', tab);
    return btn;
});

const els = {
    chatApp: makeEl('div'),
    chatPaneContacts: makeEl('div'),
    contactsModalBody: makeEl('div'),
    contactsMount: makeEl('div'),
    contactsSearch: makeEl('input'),
    contactsTeamFilter: makeEl('select'),
    contactsList: makeEl('ul'),
    contactsError: makeEl('div'),
    contactsTeamError: makeEl('div'),
    accountCardBody: makeEl('div'),
    accountCardError: makeEl('div'),
    peerCardBody: makeEl('div'),
    peerCardError: makeEl('div'),
    openContactsBtn: makeEl('button'),
    threadSearch: makeEl('input'),
    threadTitle: makeEl('div'),
    threadAvatar: makeEl('img'),
    messagesBox: makeEl('div'),
    msgInput: makeEl('input'),
    msgBodyError: makeEl('div'),
    threadPeerHit: makeEl('div')
};
els.chatApp.setAttribute('data-mobile-tab', 'messages');
els.contactsModalBody.appendChild(els.contactsMount);
els.threadAvatar.style = { display: 'none' };
els.contactsSearch.value = '';
els.contactsTeamFilter.value = '';
els.msgInput.value = '';
els.threadSearch.value = '';

const root = els.chatApp;
const me = 7;
const urls = { users: '/chat/api/users', threads: '/chat/api/threads', storeThread: '/chat/api/threads' };
let threadsCache = [];
let startDialogBusy = false;
let currentThreadId = null;
let currentPeerId = null;
let currentIsGroup = false;
let accountFail = false;

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector() { return null; },
    querySelectorAll(sel) {
        if (sel === '.chat-mobile-nav-btn') {
            return navBtns;
        }
        return [];
    }
};

function headers() { return { Accept: 'application/json' }; }
function threadUrl(id, suffix) { return '/chat/api/threads/' + id + (suffix || ''); }
function fieldError(json, field) {
    if (json && json.errors && json.errors[field]) {
        const v = json.errors[field];
        return Array.isArray(v) ? String(v[0] || '') : String(v);
    }
    return json && json.message ? String(json.message) : '';
}
function persistLeavingDraft() {}
function setComposerEnabled() {}
function setHeaderPeerClickable() {}
function showMsgError() {}
function showContactsError(text) { els.contactsError.textContent = text || ''; }
function showContactsTeamError(text) { els.contactsTeamError.textContent = text || ''; }
function loadThreads() {}
function contactsModal() {
    return {
        show: function () { modalShow += 1; },
        hide: function () { modalHide += 1; }
    };
}
function contactsTeamValue() { return els.contactsTeamFilter.value || ''; }
function composerDraftFor() { return ''; }
function appendMessage() {}
function scrollBottom() {}
function maybeLoadOlder() {}
function subscribeThread() {}
function startPoll() {}
function setUnreadBadge() {}
function upsertThread() {}
function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function dashText(value) {
    const s = String(value == null ? '' : value).trim();
    return s === '' ? '-' : s;
}
function phoneHtml(phone) { return escapeHtml(dashText(phone)); }

eval(extractFn(chatJs, 'renderPeerCard'));
eval(extractFn(chatJs, 'showAccountCardError'));
eval(extractFn(chatJs, 'loadAccountCard'));
eval(extractFn(chatJs, 'isMobileChat'));
eval(extractFn(chatJs, 'placeContactsMount'));
eval(extractFn(chatJs, 'setMobileTabButtons'));
eval(extractFn(chatJs, 'setMobileTab'));
eval(extractFn(chatJs, 'leaveMobileDialog'));
eval(extractFn(chatJs, 'renderContacts'));
eval(extractFn(chatJs, 'loadContacts'));
eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'openThread'));
eval(extractFn(chatJs, 'startDialog'));

function jsonOk(data) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: function () { return Promise.resolve(data); }
    });
}

let fetchLog = [];
let modalShow = 0;
let modalHide = 0;
global.bootstrap = { Modal: { getOrCreateInstance: function () { return { show: function () { modalShow += 1; }, hide: function () { modalHide += 1; } }; } } };

let threadIsGroup = false;
global.fetch = function (url) {
    fetchLog.push(String(url));
    const u = String(url);
    if (u.indexOf('/chat/api/users/7') !== -1) {
        if (accountFail) {
            return Promise.resolve({
                ok: false,
                status: 403,
                json: function () {
                    return Promise.resolve({
                        message: 'Нельзя открыть карточку.',
                        errors: { user: ['Нет доступа.'] }
                    });
                }
            });
        }
        return jsonOk({
            full_name: 'Свой Аккаунт',
            phone: '+79001112233',
            parent_full_name: '',
            parent_phone: '',
            last_seen_label: 'онлайн',
            team_title: '',
            avatar: '/img/default-avatar.png'
        });
    }
    if (u.indexOf('/chat/api/users') !== -1) {
        return jsonOk([]);
    }
    return jsonOk({
        thread: {
            id: threadIsGroup ? 4 : 3,
            title: threadIsGroup ? 'Сборная' : 'Диалог',
            avatar: '/img/default-avatar.png',
            peer_id: threadIsGroup ? null : 9,
            is_group: threadIsGroup,
            draft_body: ''
        },
        messages: [],
        unread_total: 0
    });
};

function wait() {
    return new Promise(function (resolve) { setImmediate(resolve); });
}

(async function () {
    mobile = true;
    fetchLog = [];
    await openThread(3);
    await wait();
    const mobile_open = {
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        tab: els.chatApp.getAttribute('data-mobile-tab')
    };

    leaveMobileDialog();
    const back = {
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        tab: els.chatApp.getAttribute('data-mobile-tab')
    };

    mobile = false;
    els.chatApp.classList.remove('is-dialog-open');
    await openThread(3);
    await wait();
    const desktop_open = {
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0'
    };

    mobile = true;
    fetchLog = [];
    modalShow = 0;
    setMobileTab('contacts');
    await wait();
    const contacts_tab = {
        tab: els.chatApp.getAttribute('data-mobile-tab'),
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        modal_show: modalShow,
        url: fetchLog[0] || '',
        mount_parent: els.contactsMount.parentElement === els.chatPaneContacts ? 'chatPaneContacts' : 'other'
    };

    fetchLog = [];
    els.accountCardBody.innerHTML = '';
    els.peerCardBody.innerHTML = '';
    setMobileTab('account');
    await wait();
    const account_tab = {
        tab: els.chatApp.getAttribute('data-mobile-tab'),
        url: fetchLog[0] || '',
        account_html: els.accountCardBody.innerHTML,
        peer_html: els.peerCardBody.innerHTML,
        error: els.accountCardError.textContent
    };

    els.chatApp.classList.add('is-dialog-open');
    fetchLog = [];
    modalShow = 0;
    setMobileTab('groups');
    await wait();
    const groups_tab = {
        tab: els.chatApp.getAttribute('data-mobile-tab'),
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        modal_show: modalShow,
        fetch_count: fetchLog.length
    };

    mobile = false;
    placeContactsMount();
    fetchLog = [];
    modalShow = 0;
    els.contactsSearch.value = '';
    els.contactsTeamFilter.value = '';
    showContactsError('');
    showContactsTeamError('');
    loadContacts('');
    contactsModal().show();
    await wait();
    const desktop_contacts = {
        modal_show: modalShow,
        mount_parent: els.contactsMount.parentElement === els.contactsModalBody ? 'contactsModalBody' : 'other'
    };

    modalShow = 0;
    setMobileTab('contacts');
    await wait();
    const desktop_set_tab = { modal_show: modalShow };

    mobile = true;
    accountFail = true;
    fetchLog = [];
    els.accountCardBody.innerHTML = '';
    els.peerCardBody.innerHTML = '';
    els.accountCardError.textContent = '';
    els.peerCardError.textContent = '';
    setMobileTab('account');
    await wait();
    const account_error = {
        error: els.accountCardError.textContent,
        peer_error: els.peerCardError.textContent,
        peer_html: els.peerCardBody.innerHTML,
        url: fetchLog[0] || ''
    };
    accountFail = false;

    threadsCache = [{ id: 3, peer_id: 9 }];
    modalHide = 0;
    els.chatApp.classList.remove('is-dialog-open');
    els.chatApp.setAttribute('data-mobile-tab', 'contacts');
    startDialog(9);
    await wait();
    const contact_click = {
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        tab: els.chatApp.getAttribute('data-mobile-tab'),
        modal_hide: modalHide
    };

    await openThread(3);
    await wait();
    setMobileTab('groups');
    await wait();
    const tab_closes_dialog = {
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        tab: els.chatApp.getAttribute('data-mobile-tab')
    };

    mobile = true;
    await openThread(3);
    await wait();
    mobile = false;
    placeContactsMount();
    if (!isMobileChat() && root) {
        root.classList.remove('is-dialog-open');
    }
    const desktop_resize = {
        is_dialog_open: els.chatApp.classList.contains('is-dialog-open') ? '1' : '0',
        mount_parent: els.contactsMount.parentElement === els.contactsModalBody ? 'contactsModalBody' : 'other'
    };

    mobile = true;
    threadIsGroup = true;
    threadsCache = [{ id: 4, is_group: true, peer_id: null }];
    els.chatApp.classList.remove('is-dialog-open');
    els.chatApp.setAttribute('data-mobile-tab', 'groups');
    await openThread(4);
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
    threadIsGroup = false;

    const savedFetch = global.fetch;
    mobile = true;
    currentThreadId = 3;
    els.messagesBox.innerHTML = 'STALE_THREAD_1';
    leaveMobileDialog();
    let resolveSecond;
    global.fetch = function () {
        return new Promise(function (resolve) { resolveSecond = resolve; });
    };
    openThread(8);
    const switch_mobile_before = els.messagesBox.innerHTML;
    resolveSecond(jsonOk({
        thread: { id: 8, title: 'Другой', avatar: '/img/default-avatar.png', peer_id: 10, is_group: false, draft_body: '' },
        messages: [],
        unread_total: 0
    }));
    await wait();

    mobile = false;
    currentThreadId = 3;
    els.messagesBox.innerHTML = 'STALE_DESKTOP';
    let resolveDesk;
    global.fetch = function () {
        return new Promise(function (resolve) { resolveDesk = resolve; });
    };
    openThread(8);
    const switch_desktop_before = els.messagesBox.innerHTML;
    resolveDesk(jsonOk({
        thread: { id: 8, title: 'Другой', avatar: '/img/default-avatar.png', peer_id: 10, is_group: false, draft_body: '' },
        messages: [],
        unread_total: 0
    }));
    await wait();
    global.fetch = savedFetch;

    process.stdout.write(JSON.stringify({
        mobile_open: mobile_open,
        back: back,
        desktop_open: desktop_open,
        contacts_tab: contacts_tab,
        account_tab: account_tab,
        groups_tab: groups_tab,
        desktop_contacts: desktop_contacts,
        desktop_set_tab: desktop_set_tab,
        account_error: account_error,
        contact_click: contact_click,
        tab_closes_dialog: tab_closes_dialog,
        desktop_resize: desktop_resize,
        group_open: group_open,
        group_back: group_back,
        switch_mobile_before: switch_mobile_before,
        switch_desktop_before: switch_desktop_before
    }));
})().catch(function (err) {
    console.error(err && err.stack ? err.stack : err);
    process.exit(1);
});
JS;

        $tmp = sys_get_temp_dir().'/chat-mobile-ux-'.uniqid('', true).'.cjs';
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

    /**
     * @return array<string, mixed>
     */
    private function simulateMobileInboxSplit(): array
    {
        $chatJs = resource_path('js/chat.js');
        $this->assertFileExists($chatJs);

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');
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
        getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; },
        addEventListener() {},
        appendChild(child) {
            child.parentElement = this;
            this.children.push(child);
            return child;
        }
    };
    return el;
}

function collected(el) {
    return el.children.map(function (c) { return c.innerHTML; }).join('\n') + el.innerHTML;
}

let mobile = true;
window.matchMedia = function (q) {
    return { matches: mobile && String(q).indexOf('991.98px') !== -1 };
};

const els = {
    threads: makeEl('div'),
    groupThreads: makeEl('div'),
    threadSearch: makeEl('input'),
    chatPrivateUnreadBadge: makeEl('span'),
    chatGroupUnreadBadge: makeEl('span')
};
els.threadSearch.value = '';
els.chatPrivateUnreadBadge.style.display = 'none';
els.chatGroupUnreadBadge.style.display = 'none';

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); }
};

const svgTick = '<svg></svg>';
let currentThreadId = null;
let threadsCache = [];
function openThread() {}

eval(extractFn(chatJs, 'ticksHtml'));
eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'pad'));
eval(extractFn(chatJs, 'isToday'));
eval(extractFn(chatJs, 'fmtTime'));
eval(extractFn(chatJs, 'normalizeDraft'));
eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'applyThreadFilter'));
eval(extractFn(chatJs, 'paintSplitNavBadges'));
eval(extractFn(chatJs, 'setCountBadge'));
eval(extractFn(chatJs, 'renderThreads'));

const mixed = [
    { id: 1, title: 'Сборная', is_group: true, last_message: 'g', unread_count: 3 },
    { id: 2, title: 'Иванов Иван', is_group: false, peer_id: 9, last_message: 'p', unread_count: 2 }
];
threadsCache = mixed.slice();
mobile = true;
els.threads.innerHTML = '';
els.threads.children = [];
els.groupThreads.innerHTML = '';
els.groupThreads.children = [];
renderThreads(applyThreadFilter(threadsCache));
const mobile_threads = collected(els.threads);
const mobile_groups = collected(els.groupThreads);
const private_badge = els.chatPrivateUnreadBadge.textContent;
const group_badge = els.chatGroupUnreadBadge.textContent;
const private_display = els.chatPrivateUnreadBadge.style.display;
const group_display = els.chatGroupUnreadBadge.style.display;

mobile = false;
els.threads.innerHTML = '';
els.threads.children = [];
els.groupThreads.innerHTML = '';
els.groupThreads.children = [];
renderThreads(applyThreadFilter(threadsCache));
const desktop_threads = collected(els.threads);
const desktop_groups = collected(els.groupThreads);

mobile = true;
els.threadSearch.value = 'сборн';
els.threads.innerHTML = '';
els.threads.children = [];
els.groupThreads.innerHTML = '';
els.groupThreads.children = [];
renderThreads(applyThreadFilter(threadsCache));
const search_private = collected(els.threads);
const search_groups = collected(els.groupThreads);

els.threadSearch.value = '';
threadsCache = [{ id: 1, title: 'Сборная', is_group: true, unread_count: 0 }];
els.threads.innerHTML = '';
els.threads.children = [];
els.groupThreads.innerHTML = '';
els.groupThreads.children = [];
renderThreads(applyThreadFilter(threadsCache));
const empty_private = collected(els.threads);

threadsCache = [{ id: 2, title: 'Иванов Иван', is_group: false, unread_count: 0 }];
els.threads.innerHTML = '';
els.threads.children = [];
els.groupThreads.innerHTML = '';
els.groupThreads.children = [];
renderThreads(applyThreadFilter(threadsCache));
const empty_groups = collected(els.groupThreads);

process.stdout.write(JSON.stringify({
    mobile_threads: mobile_threads,
    mobile_groups: mobile_groups,
    private_badge: private_badge,
    group_badge: group_badge,
    private_display: private_display,
    group_display: group_display,
    desktop_threads: desktop_threads,
    desktop_groups: desktop_groups,
    search_private: search_private,
    search_groups: search_groups,
    empty_private: empty_private,
    empty_groups: empty_groups
}));
JS;

        $tmp = sys_get_temp_dir().'/chat-mobile-inbox-split-'.uniqid('', true).'.cjs';
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
