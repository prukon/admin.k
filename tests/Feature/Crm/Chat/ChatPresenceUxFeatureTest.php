<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX-баги присутствия/карточки: список рисует время last_seen, красную точку и галочки входящего;
 * шапка открывает карточку до выбора диалога; контакты показывают пустого родителя «-».
 *
 * Серверный JSON 200 недостаточен — прогоняем реальный chat.js (renderThreads / renderContacts /
 * renderPeerCard / openPeerCard / markListOutgoingRead / applyInboxBump).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatPresenceUxFeatureTest extends ChatTestCase
{
    public function test_thread_list_shows_last_message_time_not_last_seen_and_green_dot_only_when_online(): void
    {
        $ui = $this->simulatePresenceUi();

        $this->assertStringContainsString('01.08.26', $ui['list_online']['html']);
        $this->assertStringNotContainsString('15:45', $ui['list_online']['html']);
        $this->assertStringContainsString('chat-online-dot', $ui['list_online']['html']);
        $this->assertStringNotContainsString('is-offline', $ui['list_online']['html']);
        $this->assertStringNotContainsString('is-online', $ui['list_online']['html']);

        $this->assertStringContainsString('01.08.26', $ui['list_offline']['html']);
        $this->assertStringNotContainsString('chat-online-dot', $ui['list_offline']['html']);
        $this->assertStringNotContainsString('is-offline', $ui['list_offline']['html']);

        $this->assertStringNotContainsString('chat-online-dot', $ui['list_never']['html']);
        $this->assertStringNotContainsString('is-offline', $ui['list_never']['html']);
        $this->assertStringNotContainsString('chat-li-unread', $ui['list_online']['html']);
    }

    public function test_thread_list_unread_badge_is_under_time_and_hidden_when_zero_or_open(): void
    {
        $ui = $this->simulatePresenceUi();

        $unreadHtml = $ui['list_unread_badge']['html'];
        $this->assertStringContainsString('chat-li-unread', $unreadHtml);
        $this->assertStringContainsString('>7</span>', $unreadHtml);
        $this->assertStringNotContainsString('bg-primary', $unreadHtml);
        $titlePos = strpos($unreadHtml, 'chat-li-title');
        $timePos = strpos($unreadHtml, 'chat-li-time');
        $badgePos = strpos($unreadHtml, 'chat-li-unread');
        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($timePos);
        $this->assertNotFalse($badgePos);
        $this->assertLessThan($timePos, $titlePos, 'Имя слева сверху, не вместе с каунтером');
        $this->assertLessThan($badgePos, $timePos, 'Каунтер должен идти после времени (справа внизу)');

        $this->assertStringNotContainsString('chat-li-unread', $ui['list_unread_open']['html']);
    }

    public function test_thread_list_shows_outgoing_ticks_only_when_last_message_is_mine(): void
    {
        $ui = $this->simulatePresenceUi();

        $this->assertStringContainsString('checks-sent', $ui['ticks_unread']['html']);
        $this->assertStringNotContainsString('checks-read', $ui['ticks_unread']['html']);
        $this->assertStringNotContainsString('check-second', $ui['ticks_unread']['html']);

        $this->assertStringContainsString('checks-read', $ui['ticks_read']['html']);
        $this->assertStringContainsString('check-second', $ui['ticks_read']['html']);

        $this->assertStringNotContainsString('checks-sent', $ui['ticks_incoming']['html']);
        $this->assertStringNotContainsString('checks-read', $ui['ticks_incoming']['html']);
    }

    public function test_contacts_show_red_dot_when_offline_and_hide_empty_parent_line(): void
    {
        $ui = $this->simulatePresenceUi();

        $this->assertStringContainsString('contact-online-dot is-online', $ui['contacts_online']['html']);
        $this->assertStringNotContainsString('is-offline', $ui['contacts_online']['html']);
        $this->assertStringContainsString('contact-parent', $ui['contacts_online']['html']);
        $this->assertStringContainsString('Петров Пётр', $ui['contacts_online']['html']);
        $this->assertStringContainsString('contact-main', $ui['contacts_online']['html']);
        $this->assertStringContainsString('contact-team', $ui['contacts_online']['html']);
        $this->assertStringContainsString('contact-role', $ui['contacts_online']['html']);
        $this->assertStringContainsString('Группа А', $ui['contacts_online']['html']);
        $onlineHtml = $ui['contacts_online']['html'];
        $namePos = strpos($onlineHtml, 'contact-name');
        $parentPos = strpos($onlineHtml, 'contact-parent');
        $teamPos = strpos($onlineHtml, 'contact-team');
        $rolePos = strpos($onlineHtml, 'contact-role');
        $this->assertNotFalse($namePos);
        $this->assertNotFalse($parentPos);
        $this->assertNotFalse($teamPos);
        $this->assertNotFalse($rolePos);
        $this->assertLessThan($parentPos, $namePos, 'ФИО родителя должно идти сразу после имени в левой колонке');
        $this->assertLessThan($teamPos, $parentPos, 'Группа должна быть отдельной колонкой после блока имени/родителя');
        $this->assertLessThan($rolePos, $teamPos, 'Роль должна быть справа после группы');
        $this->assertDoesNotMatchRegularExpression(
            '/contact-team[^>]*>[^<]*Петров/',
            $onlineHtml,
            'ФИО родителя не должно попадать в колонку группы'
        );

        $this->assertStringContainsString('contact-online-dot is-offline', $ui['contacts_offline']['html']);
        $this->assertStringNotContainsString('is-online', $ui['contacts_offline']['html']);
        $this->assertStringNotContainsString('contact-parent', $ui['contacts_offline']['html']);
        $this->assertStringNotContainsString('>-<', $ui['contacts_offline']['html']);

        $this->assertStringNotContainsString('contact-parent', $ui['contacts_dash_parent']['html']);
        $this->assertStringNotContainsString('tel:', $ui['contacts_online']['html']);
    }

    public function test_reopening_contacts_does_not_keep_previous_parent_line(): void
    {
        $ui = $this->simulatePresenceUi();

        $this->assertTrue($ui['contacts_reopen']['first_has_parent']);
        $this->assertFalse($ui['contacts_reopen']['second_has_parent']);
        $this->assertFalse(
            $ui['contacts_reopen']['second_has_old_fio'],
            'Повторное открытие контактов не должно оставлять ФИО родителя предыдущей строки'
        );
    }

    public function test_peer_card_uses_dash_for_empty_fields_and_tel_links_for_phones(): void
    {
        $ui = $this->simulatePresenceUi();

        $this->assertStringContainsString('peer-card-name', $ui['card_empty']['html']);
        $this->assertSame(5, substr_count($ui['card_empty']['html'], 'peer-card-row'));
        $this->assertSame(5, substr_count($ui['card_empty']['html'], '>-<'));
        $this->assertStringNotContainsString('tel:', $ui['card_empty']['html']);
        $this->assertStringNotContainsString('<script>', $ui['card_empty']['html']);
        $this->assertStringContainsString('&lt;script&gt;', $ui['card_empty']['html']);

        $this->assertStringContainsString('href="tel:+79005556677"', $ui['card_full']['html']);
        $this->assertStringContainsString('href="tel:+79001112233"', $ui['card_full']['html']);
        $this->assertStringContainsString('Иванов Иван', $ui['card_full']['html']);
        $this->assertStringContainsString('Сидоров Сидор', $ui['card_full']['html']);
        $this->assertStringContainsString('онлайн', $ui['card_full']['html']);
        $this->assertStringContainsString('КарточкаГруппа', $ui['card_full']['html']);

        $this->assertSame(6, substr_count($ui['card_after_empty']['html'], '>-<'));
        $this->assertStringNotContainsString('tel:+79005556677', $ui['card_after_empty']['html']);
        $this->assertStringNotContainsString('Иванов Иван', $ui['card_after_empty']['html']);
    }

    public function test_header_does_not_open_peer_card_until_dialog_is_chosen(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="threadPeerHit"', $html);
        $this->assertMatchesRegularExpression(
            '/id="threadPeerHit"[^>]*chat-header-peer is-idle/',
            $html
        );

        $ui = $this->simulatePresenceUi();
        $this->assertSame(0, $ui['open_without_peer']['fetch']);
        $this->assertSame(0, $ui['open_without_peer']['modal']);
        $this->assertSame(1, $ui['open_with_peer']['fetch']);
        $this->assertSame(1, $ui['open_with_peer']['modal']);
        $this->assertTrue($ui['header_idle']['before']);
        $this->assertFalse($ui['header_idle']['after_on']);
        $this->assertSame('button', $ui['header_idle']['role']);
    }

    public function test_clicking_thread_or_contact_opens_dialog_not_peer_card(): void
    {
        $ui = $this->simulatePresenceUi();

        $this->assertSame(11, $ui['list_click']['thread']);
        $this->assertSame(0, $ui['list_click']['card']);
        $this->assertSame(22, $ui['contact_click']['dialog']);
        $this->assertSame(0, $ui['contact_click']['card']);
    }

    public function test_list_does_not_mark_incoming_as_read_when_peer_reads(): void
    {
        $ui = $this->simulatePresenceUi();

        $this->assertSame(0, $ui['mark_incoming']['upserts']);
        $this->assertSame(1, $ui['mark_outgoing']['upserts']);
        $this->assertTrue($ui['mark_outgoing']['last_message_is_read']);
    }

    public function test_inbox_bump_keeps_online_and_tick_flags_when_list_is_rebuilt(): void
    {
        $ui = $this->simulatePresenceUi();
        $patch = $ui['bump_patch'];

        $this->assertSame(5, (int) $patch['id']);
        $this->assertSame('2026-08-01 09:30:00', $patch['last_message_time']);
        $this->assertTrue($patch['last_message_is_mine']);
        $this->assertFalse($patch['last_message_is_read']);
        $this->assertTrue($patch['peer_is_online']);
        $this->assertArrayNotHasKey('last_seen_at', $patch);

        $this->assertStringContainsString('01.08.26', $ui['list_after_bump']['html']);
        $this->assertStringNotContainsString('15:45', $ui['list_after_bump']['html']);
        $this->assertStringContainsString('chat-online-dot', $ui['list_after_bump']['html']);
        $this->assertStringContainsString('checks-sent', $ui['list_after_bump']['html']);
    }

    public function test_dashboard_without_chat_permission_still_pings_and_does_not_link_chat(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->grantPermission($denied, 'dashboard.view');
        $this->actingInPartner($denied);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('setInterval(ping, 60000)', $html);
        $this->assertStringContainsString('presenceUrl', $html);
        $this->assertTrue(
            str_contains($html, '/presence/ping') || str_contains($html, 'presence\/ping'),
            'Dashboard без messages.view должен содержать URL ping'
        );
        $this->assertStringNotContainsString(route('chat.index', [], false), $html);
        $this->assertStringNotContainsString('id="chatApp"', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulatePresenceUi(): array
    {
        $chatJs = public_path('js/chat.js');
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

function makeEl(tag) {
    const el = {
        tagName: String(tag || 'div').toUpperCase(),
        className: '',
        style: {},
        children: [],
        attrs: {},
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
        appendChild(child) {
            this.children.push(child);
            return child;
        },
        querySelector() {
            const row = makeEl('div');
            row.className = 'contact-row';
            this._contactRow = row;
            return row;
        },
        classList: {
            add(c) {
                const s = new Set((el.className || '').split(/\s+/).filter(Boolean));
                s.add(c);
                el.className = Array.from(s).join(' ');
            },
            remove(c) {
                const s = new Set((el.className || '').split(/\s+/).filter(Boolean));
                s.delete(c);
                el.className = Array.from(s).join(' ');
            },
            contains(c) {
                return (el.className || '').split(/\s+/).includes(c);
            }
        }
    };
    return el;
}

const els = {
    threads: makeEl('div'),
    contactsList: makeEl('ul'),
    peerCardBody: makeEl('div'),
    peerCardError: makeEl('div'),
    threadPeerHit: makeEl('div'),
    threadSearch: makeEl('input')
};
els.threadPeerHit.className = 'chat-header-peer is-idle';
els.threadSearch.value = '';

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector() { return null; }
};

const svgTick = '<svg></svg>';
let currentThreadId = null;
let currentPeerId = null;
eval(extractFn(chatJs, 'ticksHtml'));
eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'pad'));
eval(extractFn(chatJs, 'isToday'));
eval(extractFn(chatJs, 'fmtTime'));
eval(extractFn(chatJs, 'dashText'));
eval(extractFn(chatJs, 'telHref'));
eval(extractFn(chatJs, 'phoneHtml'));
eval(extractFn(chatJs, 'renderThreads'));
eval(extractFn(chatJs, 'renderContacts'));
eval(extractFn(chatJs, 'renderPeerCard'));
eval(extractFn(chatJs, 'setHeaderPeerClickable'));
eval(extractFn(chatJs, 'openPeerCard'));
eval(extractFn(chatJs, 'markListOutgoingRead'));
eval(extractFn(chatJs, 'applyInboxBump'));

function collected(el) {
    return el.children.map(function (c) { return c.innerHTML; }).join('\n') + el.innerHTML;
}

function renderOneThread(t) {
    els.threads.innerHTML = '';
    renderThreads([t]);
    return { html: collected(els.threads) };
}

function renderOneContact(u) {
    els.contactsList.innerHTML = '';
    renderContacts([u]);
    return { html: collected(els.contactsList) };
}

const lastSeenToday = '2026-08-18 15:45:00';
const messagePast = '2026-08-01 09:30:00';
const baseThread = {
    id: 11,
    title: 'Собеседник',
    avatar: '/img/default-avatar.png',
    last_message: 'текст',
    last_message_time: messagePast,
    last_seen_at: lastSeenToday,
    peer_is_online: true,
    last_message_is_mine: false,
    last_message_is_read: null,
    unread_count: 0
};

const list_online = renderOneThread(baseThread);
const list_offline = renderOneThread(Object.assign({}, baseThread, { peer_is_online: false }));
const list_never = renderOneThread(Object.assign({}, baseThread, {
    peer_is_online: false,
    last_seen_at: null
}));
const list_unread_badge = renderOneThread(Object.assign({}, baseThread, { unread_count: 7 }));
currentThreadId = 11;
const list_unread_open = renderOneThread(Object.assign({}, baseThread, { unread_count: 7 }));
currentThreadId = null;
const ticks_unread = renderOneThread(Object.assign({}, baseThread, {
    last_message_is_mine: true,
    last_message_is_read: false
}));
const ticks_read = renderOneThread(Object.assign({}, baseThread, {
    last_message_is_mine: true,
    last_message_is_read: true
}));
const ticks_incoming = renderOneThread(Object.assign({}, baseThread, {
    last_message_is_mine: false,
    last_message_is_read: true
}));

const contacts_online = renderOneContact({
    id: 22,
    name: 'Ученик',
    avatar: '/img/default-avatar.png',
    role_label: 'Ученик',
    is_online: true,
    parent_full_name: 'Петров Пётр',
    team_title: 'Группа А',
    phone: '+79005556677'
});
const contacts_offline = renderOneContact({
    id: 23,
    name: 'Сотрудник',
    avatar: '/img/default-avatar.png',
    role_label: 'Админ',
    is_online: false,
    parent_full_name: '',
    team_title: ''
});
const contacts_dash_parent = renderOneContact({
    id: 24,
    name: 'Без родителя',
    avatar: '/img/default-avatar.png',
    is_online: false,
    parent_full_name: '   '
});

els.contactsList.innerHTML = '';
renderContacts([{
    id: 1,
    name: 'Первый',
    avatar: '/img/a.png',
    is_online: true,
    parent_full_name: 'Старый Родитель'
}]);
const firstHasParent = collected(els.contactsList).indexOf('contact-parent') !== -1;
renderContacts([{
    id: 2,
    name: 'Второй',
    avatar: '/img/a.png',
    is_online: false,
    parent_full_name: ''
}]);
const secondHtml = collected(els.contactsList);
const contacts_reopen = {
    first_has_parent: firstHasParent,
    second_has_parent: secondHtml.indexOf('contact-parent') !== -1,
    second_has_old_fio: secondHtml.indexOf('Старый Родитель') !== -1
};

renderPeerCard({
    full_name: '<script>alert(1)</script>',
    phone: '',
    parent_full_name: '',
    parent_phone: '',
    last_seen_label: '',
    team_title: ''
});
const card_empty = { html: els.peerCardBody.innerHTML };
renderPeerCard({
    avatar: '/img/a.png',
    full_name: 'Иванов Иван',
    phone: '+7 (900) 555-66-77',
    parent_full_name: 'Сидоров Сидор',
    parent_phone: '+79001112233',
    last_seen_label: 'онлайн',
    team_title: 'КарточкаГруппа'
});
const card_full = { html: els.peerCardBody.innerHTML };
renderPeerCard({});
const card_after_empty = { html: els.peerCardBody.innerHTML };

let fetchCalls = 0;
let modalShows = 0;
global.fetch = function () {
    fetchCalls++;
    return Promise.resolve({ ok: true, json: function () { return Promise.resolve({}); } });
};
function showPeerCardError() {}
function headers() { return {}; }
function peerCardModal() {
    return { show: function () { modalShows++; } };
}
const urls = { users: '/chat/api/users' };
fetchCalls = 0;
modalShows = 0;
openPeerCard();
const open_without_peer = { fetch: fetchCalls, modal: modalShows };
currentPeerId = 5;
fetchCalls = 0;
modalShows = 0;
openPeerCard();
const open_with_peer = { fetch: fetchCalls, modal: modalShows };

const header_idle = { before: els.threadPeerHit.classList.contains('is-idle'), after_on: null, role: null };
setHeaderPeerClickable(true);
header_idle.after_on = els.threadPeerHit.classList.contains('is-idle');
header_idle.role = els.threadPeerHit.getAttribute('role');

let openedThread = null;
let openedCard = 0;
let startedDialog = null;
function openThread(id) { openedThread = id; }
function startDialog(userId) { startedDialog = userId; }
els.threads.innerHTML = '';
renderThreads([baseThread]);
const threadItem = els.threads.children[0];
(threadItem.listeners.click || []).forEach(function (fn) { fn(); });
const list_click = { thread: openedThread, card: openedCard };

const realOpenPeerCard = openPeerCard;
openPeerCard = function () { openedCard++; };
els.contactsList.innerHTML = '';
renderContacts([{
    id: 22,
    name: 'Ученик',
    avatar: '/img/default-avatar.png',
    is_online: true,
    parent_full_name: 'Петров Пётр'
}]);
const contactLi = els.contactsList.children[0];
(contactLi._contactRow.listeners.click || []).forEach(function (fn) { fn(); });
const contact_click = { dialog: startedDialog, card: openedCard };
openPeerCard = realOpenPeerCard;

let upserts = [];
function upsertThread(patch) { upserts.push(patch); }
let threadsCache = [{ id: 1, last_message_is_mine: false }];
markListOutgoingRead(1);
const mark_incoming = { upserts: upserts.length };
upserts = [];
threadsCache = [{ id: 1, last_message_is_mine: true }];
markListOutgoingRead(1);
const mark_outgoing = {
    upserts: upserts.length,
    last_message_is_read: !!(upserts[0] && upserts[0].last_message_is_read)
};

let bumpPatch = null;
upsertThread = function (patch) { bumpPatch = patch; };
function setUnreadBadge() {}
applyInboxBump({
    thread_id: 5,
    title: 'Собеседник',
    avatar: '/img/default-avatar.png',
    peer_id: 9,
    peer_is_online: true,
    last_message: 'текст',
    last_message_time: messagePast,
    last_seen_at: lastSeenToday,
    last_message_is_mine: true,
    last_message_is_read: false,
    unread_count: 0,
    unread_total: 0
});
const list_after_bump = renderOneThread(Object.assign({
    title: bumpPatch.title,
    avatar: bumpPatch.avatar,
    last_message: bumpPatch.last_message,
    last_seen_at: lastSeenToday
}, bumpPatch));

process.stdout.write(JSON.stringify({
    list_online: list_online,
    list_offline: list_offline,
    list_never: list_never,
    list_unread_badge: list_unread_badge,
    list_unread_open: list_unread_open,
    ticks_unread: ticks_unread,
    ticks_read: ticks_read,
    ticks_incoming: ticks_incoming,
    contacts_online: contacts_online,
    contacts_offline: contacts_offline,
    contacts_dash_parent: contacts_dash_parent,
    contacts_reopen: contacts_reopen,
    card_empty: card_empty,
    card_full: card_full,
    card_after_empty: card_after_empty,
    open_without_peer: open_without_peer,
    open_with_peer: open_with_peer,
    header_idle: header_idle,
    list_click: list_click,
    contact_click: contact_click,
    mark_incoming: mark_incoming,
    mark_outgoing: mark_outgoing,
    bump_patch: bumpPatch,
    list_after_bump: list_after_bump
}));
JS;

        $path = sys_get_temp_dir().'/chat-presence-ux-'.uniqid('', true).'.cjs';
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
