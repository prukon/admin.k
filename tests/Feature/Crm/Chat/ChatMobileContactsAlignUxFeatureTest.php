<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX-баги мобильных контактов и модалки «Участники группы»:
 * ФИО клиента/родителя слева; список не вылезает за карточку;
 * смена диалога не показывает прошлую переписку.
 *
 * Серверный 200 недостаточен — прогоняем CSS и реальный chat.js.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMobileContactsAlignUxFeatureTest extends ChatTestCase
{
    public function test_contact_and_parent_names_align_left_in_css_not_only_in_mobile_media(): void
    {
        $css = $this->chatCss();
        $mediaPos = strpos($css, '@media (max-width: 991.98px)');
        $this->assertNotFalse($mediaPos);
        $this->assertLessThan($mediaPos, (int) strpos($css, '.contact-name {'));
        $this->assertLessThan($mediaPos, (int) strpos($css, '.contact-parent {'));
        $this->assertLessThan($mediaPos, (int) strpos($css, '.contact-main {'));
        $this->assertMatchesRegularExpression('/\.contact-name\s*\{[^}]*text-align:\s*left/', $css);
        $this->assertMatchesRegularExpression('/\.contact-parent\s*\{[^}]*text-align:\s*left/', $css);
        $this->assertMatchesRegularExpression('/\.contact-main\s*\{[^}]*text-align:\s*left/', $css);
    }

    public function test_members_modal_form_is_in_flex_chain_so_list_does_not_escape_the_card(): void
    {
        $media = $this->mobileMedia($this->chatCss());

        $this->assertStringContainsString("#createGroupMembersModal {\n        overflow: hidden;", $media);
        $this->assertStringContainsString('#createGroupMembersModal #createGroupMembersForm {', $media);
        $this->assertStringContainsString('max-height: calc(100dvh - 1rem)', $media);
        $this->assertStringContainsString('max-height: calc(100dvh - 16rem)', $media);

        $formPos = strpos($media, '#createGroupMembersModal #createGroupMembersForm {');
        $this->assertNotFalse($formPos);
        $formBlock = substr($media, $formPos, 260);
        $this->assertStringContainsString('display: flex', $formBlock);
        $this->assertStringContainsString('flex-direction: column', $formBlock);
        $this->assertStringContainsString('flex: 1 1 0%', $formBlock);
        $this->assertStringContainsString('min-height: 0', $formBlock);
        $this->assertStringContainsString('overflow: hidden', $formBlock);

        $listPos = strpos($media, '#createGroupMembersModal .contact-list {');
        $this->assertNotFalse($listPos);
        $listBlock = substr($media, $listPos, 260);
        $this->assertStringNotContainsString(
            'max-height: none',
            $listBlock,
            'max-height: none на списке модалки выталкивает учеников за карточку'
        );
        $this->assertStringContainsString('overflow: auto', $listBlock);
        $this->assertStringContainsString('max-height: calc(100dvh - 16rem)', $listBlock);

        $this->assertStringContainsString(
            '#chatPaneContacts .contact-list { max-height: none;',
            $media,
            'Вкладка «Контакты» по-прежнему на всю панель, ограничение только у модалки'
        );
        $this->assertStringNotContainsString('modal-fullscreen', $media);
        $this->assertStringNotContainsString('modal-xl', $media);
    }

    public function test_contacts_tab_and_members_modal_both_render_parent_under_client_name(): void
    {
        $ui = $this->simulateAlignUi();

        $contacts = (string) $ui['contacts'];
        $this->assertStringContainsString('contact-name', $contacts);
        $this->assertStringContainsString('Иванов Иван', $contacts);
        $this->assertStringContainsString('contact-parent', $contacts);
        $this->assertStringContainsString('Петров Пётр', $contacts);
        $namePos = strpos($contacts, 'contact-name');
        $parentPos = strpos($contacts, 'contact-parent');
        $this->assertNotFalse($namePos);
        $this->assertNotFalse($parentPos);
        $this->assertLessThan($parentPos, $namePos, 'ФИО родителя под именем клиента, не наоборот');
        $this->assertStringNotContainsString('contact-parent', (string) $ui['contacts_empty_parent']);

        $members = (string) $ui['members'];
        $this->assertStringContainsString('contact-name', $members);
        $this->assertStringContainsString('Иванов Иван', $members);
        $this->assertStringContainsString('contact-parent', $members);
        $this->assertStringContainsString('Петров Пётр', $members);
        $this->assertStringContainsString('group-member-row', $members);
        $this->assertStringNotContainsString('contact-online-dot', $members);
        $membersNamePos = strpos($members, 'contact-name');
        $membersParentPos = strpos($members, 'contact-parent');
        $this->assertNotFalse($membersNamePos);
        $this->assertNotFalse($membersParentPos);
        $this->assertLessThan($membersParentPos, $membersNamePos);
        $this->assertStringNotContainsString('contact-parent', (string) $ui['members_empty_parent']);
        $this->assertStringContainsString('&lt;img&gt;', (string) $ui['members_xss']);
        $this->assertStringNotContainsString('<img>', (string) $ui['members_xss']);
    }

    public function test_switching_dialog_on_mobile_clears_stale_thread_and_ignores_late_response(): void
    {
        $ui = $this->simulateAlignUi();

        $this->assertSame('', (string) $ui['switch_before']);
        $this->assertSame(
            '',
            (string) $ui['after_stale'],
            'Поздний ответ диалога 1 не должен рисовать его переписку поверх диалога 2'
        );
        $this->assertStringContainsString('MSG:two', (string) $ui['after_second']);
        $this->assertStringNotContainsString('MSG:one', (string) $ui['after_second']);
        $this->assertSame('STALE_DESKTOP', (string) $ui['desktop_keeps']);
        $this->assertSame('KEEP_SAME', (string) $ui['same_thread_keeps']);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateAlignUi(): array
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
        _text: '',
        _html: '',
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
            child.parentElement = this;
            this.children.push(child);
            return child;
        },
        querySelector() {
            return { addEventListener() {}, className: 'contact-row' };
        }
    };
    Object.defineProperty(el, 'textContent', {
        get() { return el._text; },
        set(v) {
            el._text = v == null ? '' : String(v);
            el._html = el._text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    });
    Object.defineProperty(el, 'innerHTML', {
        get() { return el._html; },
        set(v) {
            el._html = v == null ? '' : String(v);
            el.children = [];
        }
    });
    const set = new Set();
    el.classList = {
        add(c) { set.add(c); el.className = Array.from(set).join(' '); },
        remove(c) { set.delete(c); el.className = Array.from(set).join(' '); },
        contains(c) { return set.has(c); }
    };
    return el;
}

function collected(el) {
    return el.children.map(function (c) { return c.innerHTML + ' ' + c.className; }).join('\n') + el.innerHTML;
}

let mobile = true;
window.matchMedia = function (q) {
    return { matches: mobile && String(q).indexOf('991.98px') !== -1 };
};

const els = {
    chatApp: makeEl('div'),
    contactsList: makeEl('ul'),
    createGroupMembersList: makeEl('ul'),
    threadTitle: makeEl('div'),
    threadAvatar: makeEl('img'),
    messagesBox: makeEl('div'),
    msgInput: makeEl('input'),
    msgBodyError: makeEl('div'),
    threadPeerHit: makeEl('div')
};
els.chatApp.setAttribute('data-mobile-tab', 'messages');
els.threadAvatar.style = { display: 'none' };
els.msgInput.focus = function () {};

const root = els.chatApp;
const me = 7;
let threadsCache = [];
let currentThreadId = null;
let currentPeerId = null;
let currentIsGroup = false;
let lastMessageId = null;
let hasOlder = false;
let groupSelectedIds = {};
const svgTick = '';

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector() { return null; },
    querySelectorAll() { return []; }
};

function headers() { return { Accept: 'application/json' }; }
function threadUrl(id, suffix) { return '/chat/api/threads/' + id + (suffix || ''); }
function persistLeavingDraft() {}
function setComposerEnabled() {}
function setHeaderPeerClickable() {}
function showMsgError() {}
function composerDraftFor() { return ''; }
function scrollBottom() {}
function maybeLoadOlder() {}
function subscribeThread() {}
function startPoll() {}
function setUnreadBadge() {}
function upsertThread() {}
function setMobileTabButtons() {}
function startDialog() {}
function appendMessage(m) {
    els.messagesBox.innerHTML += 'MSG:' + String(m && m.body ? m.body : '') + ';';
}

eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'renderContacts'));
eval(extractFn(chatJs, 'renderGroupMembers'));
eval(extractFn(chatJs, 'openThread'));

const people = [
    {
        id: 11,
        name: 'Иванов Иван',
        avatar: '/img/default-avatar.png',
        role_label: 'Клиент',
        parent_full_name: 'Петров Пётр',
        team_title: 'Штурм'
    },
    {
        id: 12,
        name: 'Без Родителя',
        avatar: '/img/default-avatar.png',
        role_label: 'Клиент',
        parent_full_name: '   ',
        team_title: ''
    }
];

renderContacts(people);
const contacts = collected(els.contactsList);
renderContacts([people[1]]);
const contacts_empty_parent = collected(els.contactsList);

renderGroupMembers(people);
const members = collected(els.createGroupMembersList);
renderGroupMembers([people[1]]);
const members_empty_parent = collected(els.createGroupMembersList);
renderGroupMembers([{
    id: 13,
    name: '<img>',
    avatar: '/img/default-avatar.png',
    role_label: 'Клиент',
    parent_full_name: '<img>',
    team_title: ''
}]);
const members_xss = collected(els.createGroupMembersList);

function jsonOk(data) {
    return {
        ok: true,
        status: 200,
        json: function () { return Promise.resolve(data); }
    };
}

function wait() {
    return new Promise(function (resolve) { setImmediate(resolve); });
}

(async function () {
    const queue = [];
    mobile = true;
    currentThreadId = null;
    els.messagesBox.innerHTML = 'STALE_THREAD_1';
    global.fetch = function () {
        return new Promise(function (resolve) { queue.push(resolve); });
    };
    openThread(1);
    openThread(2);
    const switch_before = els.messagesBox.innerHTML;
    queue[0](jsonOk({
        thread: { id: 1, title: 'Один', avatar: '/img/default-avatar.png', peer_id: 9, is_group: false, draft_body: '' },
        messages: [{ id: 10, user_id: 9, body: 'one', created_at: '2026-08-01 12:00:00' }],
        unread_total: 0
    }));
    await wait();
    const after_stale = els.messagesBox.innerHTML;
    queue[1](jsonOk({
        thread: { id: 2, title: 'Два', avatar: '/img/default-avatar.png', peer_id: 10, is_group: false, draft_body: '' },
        messages: [{ id: 20, user_id: 10, body: 'two', created_at: '2026-08-01 12:01:00' }],
        unread_total: 0
    }));
    await wait();
    const after_second = els.messagesBox.innerHTML;

    mobile = false;
    currentThreadId = 3;
    els.messagesBox.innerHTML = 'STALE_DESKTOP';
    let resolveDesk;
    global.fetch = function () {
        return new Promise(function (resolve) { resolveDesk = resolve; });
    };
    openThread(8);
    const desktop_keeps = els.messagesBox.innerHTML;
    resolveDesk(jsonOk({
        thread: { id: 8, title: 'Другой', avatar: '/img/default-avatar.png', peer_id: 10, is_group: false, draft_body: '' },
        messages: [],
        unread_total: 0
    }));
    await wait();

    mobile = true;
    currentThreadId = 3;
    els.messagesBox.innerHTML = 'KEEP_SAME';
    let resolveSame;
    global.fetch = function () {
        return new Promise(function (resolve) { resolveSame = resolve; });
    };
    openThread(3);
    const same_thread_keeps = els.messagesBox.innerHTML;
    resolveSame(jsonOk({
        thread: { id: 3, title: 'Тот же', avatar: '/img/default-avatar.png', peer_id: 9, is_group: false, draft_body: '' },
        messages: [],
        unread_total: 0
    }));
    await wait();

    process.stdout.write(JSON.stringify({
        contacts: contacts,
        contacts_empty_parent: contacts_empty_parent,
        members: members,
        members_empty_parent: members_empty_parent,
        members_xss: members_xss,
        switch_before: switch_before,
        after_stale: after_stale,
        after_second: after_second,
        desktop_keeps: desktop_keeps,
        same_thread_keeps: same_thread_keeps
    }));
})().catch(function (err) {
    console.error(err && err.stack ? err.stack : err);
    process.exit(1);
});
JS;

        $tmp = sys_get_temp_dir().'/chat-mobile-contacts-align-ux-'.uniqid('', true).'.cjs';
        file_put_contents($tmp, $script);

        try {
            $output = [];
            $exit = 0;
            exec('node '.escapeshellarg($tmp).' '.escapeshellarg($chatJs).' 2>&1', $output, $exit);
            $this->assertSame(0, $exit, implode("\n", $output));
            $decoded = json_decode(implode("\n", $output), true);
            $this->assertIsArray($decoded);

            return $decoded;
        } finally {
            @unlink($tmp);
        }
    }

    private function chatCss(): string
    {
        $path = resource_path('css/chat.css');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function mobileMedia(string $css): string
    {
        $pos = strpos($css, '@media (max-width: 991.98px)');
        $this->assertNotFalse($pos);

        return substr($css, $pos);
    }
}
