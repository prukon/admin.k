<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX карточки группы: клик по шапке открывает модалку состава (не карточку человека);
 * «удалить» только у админа и не у себя; подтверждение; тост; AJAX-перезагрузка;
 * пикер добавления сбрасывается при повторном открытии; ошибки под полями.
 *
 * Серверный JSON 200 недостаточен — прогоняем реальный chat.js, не заглушки submit*.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatGroupMembersUxFeatureTest extends ChatTestCase
{
    public function test_group_header_opens_group_card_and_private_opens_peer_card(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $this->assertSame(1, (int) $ui['group']['group_open']);
        $this->assertSame(0, (int) $ui['group']['peer_open']);
        $this->assertStringContainsString('/participants', (string) $ui['group']['url']);
        $this->assertStringNotContainsString('after_user_id=', (string) $ui['group']['url']);
        $this->assertSame(0, (int) $ui['idle']['group_open']);
        $this->assertSame(0, (int) $ui['idle']['peer_open']);
        $this->assertSame(1, (int) $ui['private']['peer_open']);
        $this->assertSame(0, (int) $ui['private']['group_open']);
        $this->assertSame(1, (int) $ui['header_click']['group_open']);
        $this->assertSame(1, (int) $ui['header_enter']['group_open']);
        $this->assertSame(1, (int) $ui['header_space']['group_open']);
    }

    public function test_admin_row_renders_remove_button_except_self_and_non_admin_hides_it(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $this->assertStringContainsString('js-remove-group-member', (string) $ui['admin_rows']['other_html']);
        $this->assertStringContainsString('удалить', (string) $ui['admin_rows']['other_html']);
        $this->assertStringNotContainsString('js-remove-group-member', (string) $ui['admin_rows']['self_html']);
        $this->assertStringNotContainsString('js-remove-group-member', (string) $ui['student_rows']['other_html']);
        $this->assertStringContainsString('Администратор', (string) $ui['admin_rows']['other_html']);
        $this->assertStringContainsString('&lt;b&gt;XSS&lt;/b&gt;', (string) $ui['xss']['html']);
        $this->assertStringNotContainsString('<b>XSS</b>', (string) $ui['xss']['html']);
        $this->assertSame(1, (int) $ui['dup']['rows']);
    }

    public function test_kick_success_shows_toast_reloads_members_and_cancel_does_not_fetch(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $this->assertSame('Удаление участника', (string) $ui['kick']['confirm_title']);
        $this->assertSame('Удалить этого участника из группы?', (string) $ui['kick']['confirm_text']);
        $this->assertSame('Участник удалён.', (string) $ui['kick']['toast']);
        $this->assertSame('success', (string) $ui['kick']['toast_type']);
        $this->assertGreaterThan(0, (int) $ui['kick']['delete_count']);
        $this->assertGreaterThan(0, (int) $ui['kick']['reload_count']);
        $this->assertSame(0, (int) $ui['kick']['peer_open']);
        $this->assertStringContainsString('После кика', (string) $ui['kick']['list_html']);
        $this->assertSame('2 участника', (string) $ui['kick']['header_subtitle']);
        $this->assertSame('', (string) $ui['kick']['error']);
        $this->assertSame(0, (int) $ui['kick_cancel']['delete_count']);
        $this->assertSame('', (string) $ui['kick_cancel']['toast']);
        $this->assertSame('Добавлять и удалять участников может только администратор.', (string) $ui['kick_fail']['error']);
        $this->assertSame('', (string) $ui['kick_fail']['toast']);
    }

    public function test_clicking_member_row_opens_peer_card_but_remove_button_does_not(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $this->assertSame(1, (int) $ui['row_click']['peer_open']);
        $this->assertSame(0, (int) $ui['row_click']['confirm']);
        $this->assertSame(0, (int) $ui['remove_click']['peer_open']);
        $this->assertSame(1, (int) $ui['remove_click']['confirm']);
        $this->assertTrue((bool) $ui['remove_click']['stopped']);
    }

    public function test_leave_success_shows_toast_and_closes_thread_failure_keeps_card(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $this->assertSame('Покинуть группу', (string) $ui['leave']['confirm_title']);
        $this->assertSame('Вы уверены, что хотите покинуть группу?', (string) $ui['leave']['confirm_text']);
        $this->assertSame('Вы покинули группу.', (string) $ui['leave']['toast']);
        $this->assertSame(1, (int) $ui['leave']['closed']);
        $this->assertSame(0, (int) $ui['leave']['cache_has_thread']);
        $this->assertSame(1, (int) $ui['leave_btn']['confirm']);
        $this->assertSame('Не удалось покинуть группу.', (string) $ui['leave_fail']['error']);
        $this->assertSame(1, (int) $ui['leave_fail']['card_shown']);
        $this->assertSame(0, (int) $ui['leave_fail']['closed']);
    }

    public function test_short_member_list_prefetches_next_page_tall_list_does_not(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $this->assertGreaterThan(0, (int) $ui['short_fill']['after_fetches']);
        $this->assertStringContainsString('after_user_id=', (string) $ui['short_fill']['url']);
        $this->assertSame(0, (int) $ui['tall_open']['after_fetches']);
        $this->assertSame(0, (int) $ui['zero_height']['after_fetches']);
        $this->assertGreaterThan(0, (int) $ui['scroll_bottom']['after_fetches']);
        $this->assertSame('Сборная', (string) $ui['card']['title']);
        $this->assertSame('Школа Альфа', (string) $ui['card']['partner']);
        $this->assertSame('', (string) $ui['card']['partner_display']);
        $this->assertSame('3 участника', (string) $ui['card']['count']);
        $this->assertSame('3 участника', (string) $ui['card']['header_subtitle']);
        $this->assertTrue((bool) $ui['card']['add_visible']);
        $this->assertFalse((bool) $ui['student_card']['add_visible']);
        $this->assertSame('1 участник', (string) $ui['counts']['one']);
        $this->assertSame('2 участника', (string) $ui['counts']['two']);
        $this->assertSame('5 участников', (string) $ui['counts']['five']);
        $this->assertSame('11 участников', (string) $ui['counts']['eleven']);
        $this->assertSame('21 участник', (string) $ui['counts']['twenty_one']);
    }

    public function test_removed_inbox_bump_drops_thread_and_closes_only_if_it_is_open(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $this->assertSame(1, (int) $ui['bump_open']['closed']);
        $this->assertSame(0, (int) $ui['bump_open']['cache_has_thread']);
        $this->assertSame(3, (int) $ui['bump_open']['unread']);
        $this->assertSame(0, (int) $ui['bump_other']['closed']);
        $this->assertSame(0, (int) $ui['bump_other']['cache_has_thread']);
        $this->assertSame(1, (int) $ui['bump_other']['cache_has_other']);
        $this->assertSame(0, (int) $ui['bump_other']['unread_changed']);
    }

    public function test_reopening_add_members_resets_filter_and_sends_exclude_thread_id(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $this->assertSame('', (string) $ui['add_reopen']['team_value']);
        $this->assertSame('', (string) $ui['add_reopen']['search_value']);
        $this->assertSame('', (string) $ui['add_reopen']['error']);
        $this->assertSame('', (string) $ui['add_reopen']['team_error']);
        $this->assertSame('', (string) $ui['add_reopen']['search_error']);
        $this->assertStringContainsString('exclude_thread_id=88', (string) $ui['add_reopen']['url']);
        $this->assertStringNotContainsString('team_id=', (string) $ui['add_reopen']['url']);
        $this->assertStringNotContainsString('q=', (string) $ui['add_reopen']['url']);
        $this->assertSame(1, (int) $ui['add_reopen']['queued']);
        $this->assertSame(0, (int) $ui['add_reopen']['modal_show']);
        $this->assertSame(1, (int) $ui['add_fallback']['modal_show']);
        $this->assertSame(0, (int) $ui['add_denied']['fetch_count']);
        $this->assertSame(0, (int) $ui['add_denied']['queued']);
    }

    public function test_add_members_search_and_group_filter_keep_each_other_and_show_field_errors(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $changeUrl = urldecode((string) $ui['add_change']['url']);
        $this->assertStringContainsString('team_id=15', $changeUrl);
        $this->assertStringContainsString('q=Иванов', $changeUrl);
        $this->assertStringContainsString('exclude_thread_id=88', $changeUrl);

        $this->assertSame([], $ui['add_search']['urls_before_flush']);
        $searchUrl = urldecode((string) $ui['add_search']['url']);
        $this->assertStringContainsString('team_id=15', $searchUrl);
        $this->assertStringContainsString('q=Сидоров', $searchUrl);

        $this->assertSame('Выберите группу из списка.', (string) $ui['add_team_error']['team_error']);
        $this->assertSame('', (string) $ui['add_team_error']['search_error']);
        $this->assertStringContainsString('Ничего не найдено', (string) $ui['add_team_error']['list_html']);

        $this->assertSame('', (string) $ui['add_q_error']['team_error']);
        $this->assertSame('Строка поиска слишком длинная (максимум 120 символов).', (string) $ui['add_q_error']['search_error']);

        $this->assertSame('Нет доступа к этому диалогу.', (string) $ui['add_exclude_error']['list_error']);
        $this->assertTrue((bool) $ui['add_select']['selected']);
        $this->assertFalse((bool) $ui['add_select']['online_dot']);
    }

    public function test_add_members_empty_selection_shows_error_success_toasts_and_double_submit_is_ignored(): void
    {
        $ui = $this->simulateGroupMembersUi();

        $this->assertSame('Выберите хотя бы одного участника.', (string) $ui['add_empty']['error']);
        $this->assertSame(0, (int) $ui['add_empty']['post_count']);
        $this->assertSame('Этот пользователь уже в группе.', (string) $ui['add_422']['error']);
        $this->assertSame(0, (int) $ui['add_422']['hidden']);
        $this->assertSame('Участники добавлены.', (string) $ui['add_ok']['toast']);
        $this->assertSame(1, (int) $ui['add_ok']['hidden']);
        $this->assertGreaterThan(0, (int) $ui['add_ok']['reload']);
        $this->assertSame('4 участника', (string) $ui['add_ok']['header_subtitle']);
        $this->assertSame(1, (int) $ui['add_double']['post_count']);
        $this->assertSame(1, (int) $ui['add_form']['prevented']);
        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertStringContainsString('showConfirmDeleteModal', $js);
        $this->assertStringContainsString('maybeLoadMoreMembers', $js);
        $this->assertStringContainsString("chatToast(res.data.message || 'Участник удалён.')", $js);
        $this->assertStringContainsString("chatToast(res.data.message || 'Вы покинули группу.')", $js);
        $this->assertStringContainsString("chatToast(res.data.message || 'Участники добавлены.')", $js);
        $this->assertStringContainsString("params.set('exclude_thread_id'", $js);
        $this->assertStringContainsString('e.stopPropagation()', $js);
        $this->assertStringContainsString('openPeerCard(Number(row.getAttribute(\'data-id\')), true)', $js);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateGroupMembersUi(): array
    {
        $chatJs = resource_path('js/chat.js');
        $this->assertFileExists($chatJs);

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');
global.window = global;
global.CSS = { escape: function (s) { return String(s).replace(/[^a-zA-Z0-9_\-]/g, '\\$&'); } };

function extractFn(src, name) {
    const needle = 'function ' + name + '(';
    const start = src.indexOf(needle);
    if (start < 0) throw new Error('missing ' + name);
    const brace = src.indexOf('{', start);
    let depth = 0;
    for (let j = brace; j < src.length; j++) {
        if (src[j] === '{') depth++;
        else if (src[j] === '}') {
            depth--;
            if (depth === 0) return src.slice(start, j + 1);
        }
    }
    throw new Error('unclosed ' + name);
}

function extractListenerByVar(src, varName, event) {
    const needle = varName + ".addEventListener('" + event + "'";
    const start = src.indexOf(needle);
    if (start < 0) throw new Error('missing listener ' + varName + ' ' + event);
    const fnPos = src.indexOf('function', start);
    const brace = src.indexOf('{', fnPos);
    let depth = 0;
    for (let j = brace; j < src.length; j++) {
        if (src[j] === '{') depth++;
        else if (src[j] === '}') {
            depth--;
            if (depth === 0) return src.slice(fnPos, j + 1);
        }
    }
    throw new Error('unclosed listener ' + varName);
}

function makeEl(tag) {
    const el = {
        tagName: String(tag || 'div').toUpperCase(),
        className: '',
        value: '',
        style: {},
        children: [],
        attrs: {},
        listeners: {},
        parentElement: null,
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
        get innerHTML() {
            if (this._html) {
                return this._html;
            }
            if (this.children.length) {
                return this.children.map(function (c) {
                    return c._html || c.innerHTML || '';
                }).join('');
            }
            return '';
        },
        set innerHTML(v) {
            this._html = v == null ? '' : String(v);
            if (this._html === '') this.children = [];
        },
        setAttribute(k, v) { this.attrs[k] = String(v); },
        getAttribute(k) {
            return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
        },
        addEventListener(ev, fn) {
            this.listeners[ev] = this.listeners[ev] || [];
            this.listeners[ev].push(fn);
        },
        appendChild(child) {
            child.parentElement = this;
            this.children.push(child);
            return child;
        },
        querySelector(sel) {
            const m = String(sel).match(/data-id="([^"]+)"/);
            if (!m) return null;
            return this.children.find(function (c) { return c.getAttribute('data-id') === m[1]; }) || null;
        },
        querySelectorAll(sel) {
            if (String(sel).indexOf('tr[data-id]') !== -1) return this.children;
            return [];
        }
    };
    const set = new Set();
    el.classList = {
        add(c) { set.add(c); el.className = Array.from(set).join(' '); },
        remove(c) { set.delete(c); el.className = Array.from(set).join(' '); },
        contains(c) { return set.has(c); }
    };
    el.closest = function (sel) {
        if (sel === '.js-remove-group-member' && String(this.className).indexOf('js-remove-group-member') !== -1) {
            return this;
        }
        if (String(sel).indexOf('tr[data-id]') === 0 && this.tagName === 'TR' && this.getAttribute('data-id')) {
            return this;
        }
        return this.parentElement && this.parentElement.closest ? this.parentElement.closest(sel) : null;
    };
    return el;
}

const wrap = Object.assign(makeEl('div'), { clientHeight: 320, scrollHeight: 400, scrollTop: 0 });
const addBtn = makeEl('button');
const els = {
    groupMembersBody: makeEl('tbody'),
    groupCardTitle: makeEl('div'),
    groupCardAvatar: makeEl('img'),
    groupCardPartner: makeEl('div'),
    groupCardCount: makeEl('div'),
    groupCardError: makeEl('div'),
    addGroupMembersBtn: addBtn,
    leaveGroupBtn: makeEl('button'),
    groupMembersWrap: wrap,
    addGroupMembersSearch: makeEl('input'),
    addGroupMembersTeamFilter: makeEl('select'),
    addGroupMembersList: makeEl('ul'),
    addGroupMembersError: makeEl('div'),
    addGroupMembersTeamError: makeEl('div'),
    addGroupMembersSearchError: makeEl('div'),
    addGroupMembersForm: makeEl('form'),
    addGroupMembersModal: makeEl('div'),
    threadPeerHit: makeEl('div'),
    threadSubtitle: makeEl('div'),
    confirmDeleteModal: makeEl('div')
};
els.addGroupMembersSearch.value = '';
els.addGroupMembersTeamFilter.value = '';

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector(sel) {
        if (String(sel).indexOf('#addGroupMembersList') === 0) {
            return els.addGroupMembersList.querySelector(sel.replace('#addGroupMembersList ', ''));
        }
        return null;
    },
    querySelectorAll(sel) {
        if (sel === '#groupMembersBody tr[data-id]') return els.groupMembersBody.children;
        return [];
    }
};

const svgTick = '<svg></svg>';
const urls = { users: '/chat/api/users' };
const me = 7;
let currentThreadId = 88;
let currentIsGroup = true;
let currentPeerId = null;
let threadsCache = [{ id: 88 }, { id: 99 }];
let groupMembersBusy = false;
let groupMembersHasMore = false;
let groupMembersCanManage = true;
let addGroupMembersSelected = {};
let addGroupMembersBusy = false;
let addGroupMembersDebounce = null;

let lastToast = '';
let lastToastType = '';
let lastConfirm = null;
let autoConfirm = true;
let groupOpen = 0;
let peerOpen = 0;
let closed = 0;
let addHide = 0;
let addShow = 0;
let queued = 0;
let lastUnread = null;
let fetchLog = [];
let postBodies = [];
let kickFail = false;
let leaveFail = false;
let addFail = false;
let addHang = false;
let hangResolve = null;
let usersMode = 'ok';
let pageMode = 'default';
let showModalQueued = function (id) { queued += 1; queuedId = id; };
let queuedId = '';

global.window.showToast = function (msg, type) {
    lastToast = msg;
    lastToastType = type || '';
};

function showConfirmDeleteModal(title, text, cb) {
    lastConfirm = { title: title, text: text };
    if (autoConfirm && typeof cb === 'function') cb();
}

function headers() { return { Accept: 'application/json' }; }
function openPeerCard(userId) {
    const id = userId != null && userId !== '' ? Number(userId) : currentPeerId;
    if (!id) return;
    peerOpen += 1;
}
function groupCardModal() {
    return { show() { groupOpen += 1; }, hide() {} };
}
function addGroupMembersModal() {
    return {
        show() { addShow += 1; },
        hide() { addHide += 1; }
    };
}
function closeCurrentThread() { closed += 1; }
function renderThreads() {}
function applyThreadFilter(list) { return list; }
function setUnreadBadge(n) { lastUnread = n; }
function upsertThread() {}

function jsonRes(ok, data) {
    return Promise.resolve({
        ok: ok,
        json: function () { return Promise.resolve(data); }
    });
}

global.fetch = function (url, opts) {
    const u = String(url);
    const method = String((opts && opts.method) || 'GET').toUpperCase();
    fetchLog.push(method + ' ' + u);
    if (method === 'POST' && opts && opts.body) {
        postBodies.push(String(opts.body));
    }
    if (method === 'DELETE') {
        if (kickFail && u.indexOf('/participants/9') !== -1) {
            return jsonRes(false, { message: 'Добавлять и удалять участников может только администратор.' });
        }
        if (leaveFail) {
            return jsonRes(false, { message: 'Не удалось покинуть группу.' });
        }
        if (u.indexOf('/participants/' + me) !== -1) {
            return jsonRes(true, { ok: true, message: 'Вы покинули группу.', left: true });
        }
        return jsonRes(true, { ok: true, message: 'Участник удалён.', left: false });
    }
    if (method === 'POST' && u.indexOf('/participants') !== -1) {
        if (addHang) {
            return new Promise(function (resolve) { hangResolve = resolve; });
        }
        if (addFail) {
            return jsonRes(false, { errors: { user_ids: ['Этот пользователь уже в группе.'] } });
        }
        return jsonRes(true, { ok: true, message: 'Участники добавлены.', members_total: 4 });
    }
    if (u.indexOf('/chat/api/users') !== -1) {
        if (usersMode === 'team') {
            return jsonRes(false, { errors: { team_id: ['Выберите группу из списка.'] } });
        }
        if (usersMode === 'q') {
            return jsonRes(false, { errors: { q: ['Строка поиска слишком длинная (максимум 120 символов).'] } });
        }
        if (usersMode === 'exclude') {
            return jsonRes(false, { errors: { exclude_thread_id: ['Нет доступа к этому диалогу.'] } });
        }
        return jsonRes(true, [
            { id: 11, name: 'Альфа', avatar: '/img/default-avatar.png', role_label: 'Клиент', team_title: 'Штурм' }
        ]);
    }
    if (pageMode === 'after') {
        return jsonRes(true, {
            thread: { id: 88, title: 'Сборная', avatar: '/img/default-avatar.png', members_total: 17 },
            can_manage: true,
            has_more: false,
            members: [{ id: 20, full_name: 'Яяев', avatar: '/a.png', role_label: 'Клиент' }]
        });
    }
    if (u.indexOf('after_user_id=') !== -1) {
        return jsonRes(true, {
            thread: { id: 88, title: 'Сборная', avatar: '/img/default-avatar.png', members_total: 17 },
            can_manage: true,
            has_more: false,
            members: [{ id: 20, full_name: 'Яяев', avatar: '/a.png', role_label: 'Клиент' }]
        });
    }
    if (pageMode === 'after_kick') {
        return jsonRes(true, {
            thread: { id: 88, title: 'Сборная', avatar: '/img/default-avatar.png', members_total: 2 },
            can_manage: true,
            has_more: false,
            members: [{ id: 10, full_name: 'После кика', avatar: '/a.png', role_label: 'Клиент' }]
        });
    }
    if (pageMode === 'after_add') {
        return jsonRes(true, {
            thread: { id: 88, title: 'Сборная', avatar: '/img/default-avatar.png', members_total: 4 },
            can_manage: true,
            has_more: false,
            members: [{ id: 11, full_name: 'Новый', avatar: '/a.png', role_label: 'Клиент' }]
        });
    }
    return jsonRes(true, {
        thread: { id: 88, title: 'Сборная', avatar: '/img/default-avatar.png', members_total: 3, partner_name: 'Школа Альфа' },
        can_manage: groupMembersCanManage,
        has_more: pageMode === 'short',
        members: [
            { id: 9, full_name: 'Клиент К', avatar: '/a.png', role_label: 'Администратор' },
            { id: 10, full_name: 'Второй', avatar: '/a.png', role_label: 'Клиент' }
        ]
    });
};

async function flush() {
    for (let i = 0; i < 12; i++) await Promise.resolve();
}

const timers = [];
global.setTimeout = function (fn) {
    timers.push(fn);
    return timers.length;
};
global.clearTimeout = function () {};
function flushTimers() {
    const queuedTimers = timers.splice(0);
    queuedTimers.forEach(function (fn) { fn(); });
}

eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'fieldError'));
eval(extractFn(chatJs, 'threadUrl'));
eval(extractFn(chatJs, 'membersCountLabel'));
eval(extractFn(chatJs, 'setThreadSubtitle'));
eval(extractFn(chatJs, 'chatToast'));
eval(extractFn(chatJs, 'showGroupCardError'));
eval(extractFn(chatJs, 'showAddGroupMembersError'));
eval(extractFn(chatJs, 'showAddGroupMembersTeamError'));
eval(extractFn(chatJs, 'showAddGroupMembersSearchError'));
eval(extractFn(chatJs, 'setGroupManageVisible'));
eval(extractFn(chatJs, 'lastGroupMemberUserId'));
eval(extractFn(chatJs, 'appendGroupMembers'));
eval(extractFn(chatJs, 'setGroupCardPartner'));
eval(extractFn(chatJs, 'fetchGroupMembers'));
eval(extractFn(chatJs, 'maybeFillGroupMembers'));
eval(extractFn(chatJs, 'maybeLoadMoreMembers'));
eval(extractFn(chatJs, 'openGroupCard'));
eval(extractFn(chatJs, 'headerPeerActivate'));
eval(extractFn(chatJs, 'confirmRemoveGroupMember'));
eval(extractFn(chatJs, 'submitRemoveGroupMember'));
eval(extractFn(chatJs, 'confirmLeaveGroup'));
eval(extractFn(chatJs, 'submitLeaveGroup'));
eval(extractFn(chatJs, 'resetAddGroupMembers'));
eval(extractFn(chatJs, 'addGroupMembersTeamValue'));
eval(extractFn(chatJs, 'toggleAddGroupMember'));
eval(extractFn(chatJs, 'renderAddGroupMembers'));
eval(extractFn(chatJs, 'loadAddGroupMembers'));
eval(extractFn(chatJs, 'openAddGroupMembers'));
eval(extractFn(chatJs, 'submitAddGroupMembers'));
eval(extractFn(chatJs, 'applyInboxBump'));

const onMembersClick = eval('(' + extractListenerByVar(chatJs, 'groupMembersBody', 'click') + ')');
const onPeerHitClick = eval('(' + extractListenerByVar(chatJs, 'peerHit', 'click') + ')');
const onPeerHitKey = eval('(' + extractListenerByVar(chatJs, 'peerHit', 'keydown') + ')');
const onLeaveClick = eval('(' + extractListenerByVar(chatJs, 'leaveGroupBtn', 'click') + ')');
const onAddBtnClick = eval('(' + extractListenerByVar(chatJs, 'addGroupMembersBtn', 'click') + ')');
const onAddSubmit = eval('(' + extractListenerByVar(chatJs, 'addGroupMembersForm', 'submit') + ')');
const onAddSearch = eval('(' + extractListenerByVar(chatJs, 'addGroupMembersSearch', 'input') + ')');
const onAddTeam = eval('(' + extractListenerByVar(chatJs, 'addGroupMembersTeamFilter', 'change') + ')');

(async function main() {
    fetchLog = [];
    groupOpen = 0; peerOpen = 0;
    currentIsGroup = true; currentPeerId = null; currentThreadId = 88;
    wrap.scrollHeight = 400;
    openGroupCard();
    await flush();
    const group = { group_open: groupOpen, peer_open: peerOpen, url: fetchLog[0] || '' };
    const card = {
        title: els.groupCardTitle.textContent,
        partner: els.groupCardPartner.textContent,
        partner_display: els.groupCardPartner.style.display || '',
        count: els.groupCardCount.textContent,
        header_subtitle: els.threadSubtitle.textContent,
        add_visible: !addBtn.classList.contains('is-hidden')
    };

    groupOpen = 0; peerOpen = 0;
    currentIsGroup = false; currentPeerId = null; currentThreadId = null;
    headerPeerActivate();
    const idle = { group_open: groupOpen, peer_open: peerOpen };

    groupOpen = 0; peerOpen = 0;
    currentIsGroup = false; currentPeerId = 5; currentThreadId = 10;
    headerPeerActivate();
    const privateHit = { group_open: groupOpen, peer_open: peerOpen };

    groupOpen = 0; peerOpen = 0;
    currentIsGroup = true; currentPeerId = null; currentThreadId = 88;
    onPeerHitClick();
    await flush();
    const header_click = { group_open: groupOpen };

    groupOpen = 0;
    onPeerHitKey({ key: 'Enter', preventDefault: function () {} });
    await flush();
    const header_enter = { group_open: groupOpen };

    groupOpen = 0;
    onPeerHitKey({ key: ' ', preventDefault: function () {} });
    await flush();
    const header_space = { group_open: groupOpen };

    els.groupMembersBody.children = [];
    els.groupMembersBody.innerHTML = '';
    appendGroupMembers([{ id: 9, full_name: 'Клиент К', avatar: '/a.png', role_label: 'Администратор' }], true);
    const otherHtml = (els.groupMembersBody.children[0] && (els.groupMembersBody.children[0]._html || els.groupMembersBody.children[0].innerHTML)) || '';
    els.groupMembersBody.children = [];
    els.groupMembersBody.innerHTML = '';
    appendGroupMembers([{ id: 7, full_name: 'Я', avatar: '/a.png', role_label: 'Администратор' }], true);
    const selfHtml = els.groupMembersBody.innerHTML;
    els.groupMembersBody.children = [];
    els.groupMembersBody.innerHTML = '';
    appendGroupMembers([{ id: 9, full_name: 'Клиент К', avatar: '/a.png', role_label: 'Клиент' }], false);
    const studentHtml = els.groupMembersBody.innerHTML;
    els.groupMembersBody.children = [];
    els.groupMembersBody.innerHTML = '';
    appendGroupMembers([{ id: 15, full_name: '<b>XSS</b>', avatar: '/a.png', role_label: 'Клиент' }], true);
    const xssHtml = els.groupMembersBody.innerHTML;
    appendGroupMembers([{ id: 15, full_name: '<b>XSS</b>', avatar: '/a.png', role_label: 'Клиент' }], true);
    const dup = { rows: els.groupMembersBody.children.length };

    const btn = makeEl('button');
    btn.className = 'group-member-remove js-remove-group-member';
    btn.setAttribute('data-id', '9');
    const row = makeEl('tr');
    row.setAttribute('data-id', '9');
    row.appendChild(btn);
    peerOpen = 0; lastConfirm = null; autoConfirm = false;
    let stopped = false;
    onMembersClick({
        target: btn,
        preventDefault: function () {},
        stopPropagation: function () { stopped = true; }
    });
    const remove_click = { peer_open: peerOpen, confirm: lastConfirm ? 1 : 0, stopped: stopped };

    peerOpen = 0; lastConfirm = null;
    onMembersClick({
        target: row,
        preventDefault: function () {},
        stopPropagation: function () {}
    });
    const row_click = { peer_open: peerOpen, confirm: lastConfirm ? 1 : 0 };

    autoConfirm = true;
    lastConfirm = null;
    lastToast = '';
    lastToastType = '';
    peerOpen = 0;
    fetchLog = [];
    els.groupCardError.textContent = '';
    pageMode = 'after_kick';
    wrap.scrollHeight = 400;
    currentThreadId = 88;
    currentIsGroup = true;
    confirmRemoveGroupMember(9);
    await flush();
    const kick = {
        confirm_title: lastConfirm ? lastConfirm.title : '',
        confirm_text: lastConfirm ? lastConfirm.text : '',
        toast: lastToast,
        toast_type: lastToastType,
        delete_count: fetchLog.filter(function (u) { return u.indexOf('DELETE') === 0; }).length,
        reload_count: fetchLog.filter(function (u) { return u.indexOf('GET ') === 0 && u.indexOf('/participants') !== -1; }).length,
        peer_open: peerOpen,
        list_html: els.groupMembersBody.innerHTML,
        header_subtitle: els.threadSubtitle.textContent,
        error: els.groupCardError.textContent
    };
    pageMode = 'default';

    autoConfirm = false;
    lastConfirm = null;
    lastToast = '';
    fetchLog = [];
    confirmRemoveGroupMember(9);
    const kick_cancel = { delete_count: fetchLog.length, toast: lastToast };
    autoConfirm = true;

    kickFail = true;
    lastToast = '';
    els.groupCardError.textContent = '';
    fetchLog = [];
    confirmRemoveGroupMember(9);
    await flush();
    const kick_fail = { error: els.groupCardError.textContent, toast: lastToast };
    kickFail = false;

    lastConfirm = null;
    lastToast = '';
    closed = 0;
    threadsCache = [{ id: 88 }, { id: 99 }];
    currentThreadId = 88;
    confirmLeaveGroup();
    await flush();
    const leave = {
        confirm_title: lastConfirm ? lastConfirm.title : '',
        confirm_text: lastConfirm ? lastConfirm.text : '',
        toast: lastToast,
        closed: closed,
        cache_has_thread: threadsCache.some(function (t) { return Number(t.id) === 88; }) ? 1 : 0
    };

    autoConfirm = false;
    lastConfirm = null;
    onLeaveClick();
    const leave_btn = { confirm: lastConfirm ? 1 : 0 };
    autoConfirm = true;

    leaveFail = true;
    closed = 0;
    groupOpen = 0;
    lastToast = '';
    els.groupCardError.textContent = '';
    currentThreadId = 88;
    threadsCache = [{ id: 88 }];
    submitLeaveGroup();
    await flush();
    const leave_fail = { error: els.groupCardError.textContent, card_shown: groupOpen, closed: closed };
    leaveFail = false;

    wrap.clientHeight = 320;
    wrap.scrollHeight = 100;
    wrap.scrollTop = 0;
    pageMode = 'short';
    groupMembersBusy = false;
    groupMembersHasMore = true;
    currentThreadId = 88;
    currentIsGroup = true;
    fetchLog = [];
    els.groupMembersBody.children = [];
    els.groupMembersBody.innerHTML = '';
    fetchGroupMembers(true);
    await flush();
    await flush();
    const afterUrls = fetchLog.filter(function (u) { return u.indexOf('after_user_id=') !== -1; });
    const short_fill = { after_fetches: afterUrls.length, url: afterUrls[0] || '' };

    wrap.scrollHeight = 2000;
    pageMode = 'default';
    groupMembersBusy = false;
    fetchLog = [];
    els.groupMembersBody.children = [];
    els.groupMembersBody.innerHTML = '';
    fetchGroupMembers(true);
    await flush();
    const tall_open = {
        after_fetches: fetchLog.filter(function (u) { return u.indexOf('after_user_id=') !== -1; }).length
    };

    wrap.clientHeight = 0;
    fetchLog = [];
    maybeFillGroupMembers();
    maybeLoadMoreMembers();
    const zero_height = { after_fetches: fetchLog.length };
    wrap.clientHeight = 320;

    wrap.scrollHeight = 400;
    wrap.scrollTop = 350;
    pageMode = 'short';
    groupMembersBusy = false;
    groupMembersHasMore = true;
    els.groupMembersBody.children = [];
    els.groupMembersBody.innerHTML = '';
    appendGroupMembers([{ id: 9, full_name: 'К', avatar: '/a.png', role_label: 'Клиент' }], true);
    fetchLog = [];
    maybeLoadMoreMembers();
    await flush();
    const scroll_bottom = {
        after_fetches: fetchLog.filter(function (u) { return u.indexOf('after_user_id=') !== -1; }).length
    };
    pageMode = 'default';

    groupMembersCanManage = false;
    setGroupManageVisible(false);
    const student_card = { add_visible: !addBtn.classList.contains('is-hidden') };
    groupMembersCanManage = true;
    setGroupManageVisible(true);

    closed = 0;
    lastUnread = null;
    currentThreadId = 88;
    threadsCache = [{ id: 88 }, { id: 99 }];
    applyInboxBump({ thread_id: 88, removed: true, unread_total: 3 });
    const bump_open = {
        closed: closed,
        cache_has_thread: threadsCache.some(function (t) { return Number(t.id) === 88; }) ? 1 : 0,
        unread: lastUnread
    };

    closed = 0;
    lastUnread = 1;
    currentThreadId = 99;
    threadsCache = [{ id: 88 }, { id: 99 }];
    applyInboxBump({ thread_id: 88, removed: true });
    const bump_other = {
        closed: closed,
        cache_has_thread: threadsCache.some(function (t) { return Number(t.id) === 88; }) ? 1 : 0,
        cache_has_other: threadsCache.some(function (t) { return Number(t.id) === 99; }) ? 1 : 0,
        unread_changed: lastUnread === 1 ? 0 : 1
    };

    currentThreadId = 88;
    currentIsGroup = true;
    groupMembersCanManage = true;
    els.addGroupMembersTeamFilter.value = '15';
    els.addGroupMembersSearch.value = 'Петров';
    els.addGroupMembersError.textContent = 'старая';
    els.addGroupMembersTeamError.textContent = 'старая team';
    els.addGroupMembersSearchError.textContent = 'старая q';
    addGroupMembersSelected = { '11': true };
    queued = 0;
    addShow = 0;
    fetchLog = [];
    usersMode = 'ok';
    openAddGroupMembers();
    await flush();
    const add_reopen = {
        team_value: els.addGroupMembersTeamFilter.value,
        search_value: els.addGroupMembersSearch.value,
        error: els.addGroupMembersError.textContent,
        team_error: els.addGroupMembersTeamError.textContent,
        search_error: els.addGroupMembersSearchError.textContent,
        url: (fetchLog.filter(function (u) { return u.indexOf('/chat/api/users') !== -1; })[0] || ''),
        queued: queued,
        modal_show: addShow
    };

    const prevQueued = showModalQueued;
    showModalQueued = null;
    addShow = 0;
    openAddGroupMembers();
    await flush();
    const add_fallback = { modal_show: addShow };
    showModalQueued = prevQueued;

    groupMembersCanManage = false;
    queued = 0;
    fetchLog = [];
    onAddBtnClick();
    const add_denied = { fetch_count: fetchLog.length, queued: queued };
    groupMembersCanManage = true;

    usersMode = 'ok';
    els.addGroupMembersSearch.value = 'Иванов';
    els.addGroupMembersTeamFilter.value = '15';
    fetchLog = [];
    onAddTeam.call(els.addGroupMembersTeamFilter);
    await flush();
    const add_change = { url: fetchLog[fetchLog.length - 1] || '' };

    els.addGroupMembersTeamFilter.value = '15';
    els.addGroupMembersSearch.value = 'Сидоров';
    fetchLog = [];
    onAddSearch.call(els.addGroupMembersSearch);
    const urls_before_flush = fetchLog.slice();
    flushTimers();
    await flush();
    const add_search = { urls_before_flush: urls_before_flush, url: fetchLog[fetchLog.length - 1] || '' };

    usersMode = 'team';
    els.addGroupMembersTeamError.textContent = '';
    els.addGroupMembersSearchError.textContent = '';
    els.addGroupMembersList.innerHTML = '';
    loadAddGroupMembers('');
    await flush();
    const add_team_error = {
        team_error: els.addGroupMembersTeamError.textContent,
        search_error: els.addGroupMembersSearchError.textContent,
        list_html: els.addGroupMembersList.innerHTML
    };

    usersMode = 'q';
    els.addGroupMembersTeamError.textContent = '';
    els.addGroupMembersSearchError.textContent = '';
    loadAddGroupMembers('xxx');
    await flush();
    const add_q_error = {
        team_error: els.addGroupMembersTeamError.textContent,
        search_error: els.addGroupMembersSearchError.textContent
    };

    usersMode = 'exclude';
    els.addGroupMembersError.textContent = '';
    loadAddGroupMembers('');
    await flush();
    const add_exclude_error = { list_error: els.addGroupMembersError.textContent };
    usersMode = 'ok';

    addGroupMembersSelected = {};
    loadAddGroupMembers('');
    await flush();
    toggleAddGroupMember(11);
    const add_select = {
        selected: !!(els.addGroupMembersList.children[0] && String(els.addGroupMembersList.children[0].className).indexOf('is-selected') !== -1),
        online_dot: String(els.addGroupMembersList.innerHTML).indexOf('online-dot') !== -1
    };

    addGroupMembersSelected = {};
    els.addGroupMembersError.textContent = '';
    postBodies = [];
    fetchLog = [];
    submitAddGroupMembers();
    const add_empty = { error: els.addGroupMembersError.textContent, post_count: postBodies.length };

    addGroupMembersSelected = { '11': true };
    addFail = true;
    addHide = 0;
    els.addGroupMembersError.textContent = '';
    submitAddGroupMembers();
    await flush();
    const add_422 = { error: els.addGroupMembersError.textContent, hidden: addHide };
    addFail = false;

    lastToast = '';
    addHide = 0;
    fetchLog = [];
    pageMode = 'after_add';
    wrap.scrollHeight = 400;
    submitAddGroupMembers();
    await flush();
    const add_ok = {
        toast: lastToast,
        hidden: addHide,
        reload: fetchLog.filter(function (u) { return u.indexOf('GET ') === 0 && u.indexOf('/participants') !== -1; }).length,
        header_subtitle: els.threadSubtitle.textContent
    };
    pageMode = 'default';

    addHang = true;
    addGroupMembersBusy = false;
    postBodies = [];
    submitAddGroupMembers();
    submitAddGroupMembers();
    const add_double = { post_count: postBodies.length };
    addHang = false;
    if (hangResolve) {
        hangResolve({
            ok: true,
            json: function () { return Promise.resolve({ ok: true, message: 'Участники добавлены.' }); }
        });
    }
    await flush();

    let prevented = 0;
    onAddSubmit({ preventDefault: function () { prevented += 1; } });
    await flush();
    const add_form = { prevented: prevented };

    process.stdout.write(JSON.stringify({
        group: group,
        idle: idle,
        private: privateHit,
        header_click: header_click,
        header_enter: header_enter,
        header_space: header_space,
        admin_rows: { other_html: otherHtml, self_html: selfHtml },
        student_rows: { other_html: studentHtml },
        xss: { html: xssHtml },
        dup: dup,
        kick: kick,
        kick_cancel: kick_cancel,
        kick_fail: kick_fail,
        remove_click: remove_click,
        row_click: row_click,
        leave: leave,
        leave_btn: leave_btn,
        leave_fail: leave_fail,
        short_fill: short_fill,
        tall_open: tall_open,
        zero_height: zero_height,
        scroll_bottom: scroll_bottom,
        card: card,
        student_card: student_card,
        counts: {
            one: membersCountLabel(1),
            two: membersCountLabel(2),
            five: membersCountLabel(5),
            eleven: membersCountLabel(11),
            twenty_one: membersCountLabel(21)
        },
        bump_open: bump_open,
        bump_other: bump_other,
        add_reopen: add_reopen,
        add_fallback: add_fallback,
        add_denied: add_denied,
        add_change: add_change,
        add_search: add_search,
        add_team_error: add_team_error,
        add_q_error: add_q_error,
        add_exclude_error: add_exclude_error,
        add_select: add_select,
        add_empty: add_empty,
        add_422: add_422,
        add_ok: add_ok,
        add_double: add_double,
        add_form: add_form
    }));
})().catch(function (err) {
    console.error(err && err.stack ? err.stack : err);
    process.exit(1);
});
JS;

        $tmp = sys_get_temp_dir().'/chat-group-members-ux-'.uniqid('', true).'.cjs';
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
