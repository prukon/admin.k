<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX удаления чата: корзина только при праве и без team_id,
 * модалка, тост, ошибка под #threadDeleteError.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatThreadDeleteUxFeatureTest extends ChatTestCase
{
    public function test_delete_button_hidden_without_permission_or_on_team_chat(): void
    {
        $ui = $this->simulateDeleteThreadUi();

        $this->assertSame('none', (string) $ui['idle']['display']);
        $this->assertSame('none', (string) $ui['no_perm']['display']);
        $this->assertSame('none', (string) $ui['team']['display']);
        $this->assertSame('', (string) $ui['open']['display']);
        $this->assertSame('none', (string) $ui['after_team_open']['display']);
        $this->assertSame('', (string) $ui['after_private_again']['display']);
        $this->assertSame('', (string) $ui['manual_group']['display']);
    }

    public function test_confirm_does_not_open_when_idle_on_team_chat_or_without_permission(): void
    {
        $ui = $this->simulateDeleteThreadUi();

        $this->assertSame(0, (int) $ui['confirm_idle']['shown']);
        $this->assertSame(0, (int) $ui['confirm_idle']['delete_count']);
        $this->assertSame(0, (int) $ui['confirm_team']['shown']);
        $this->assertSame(0, (int) $ui['confirm_team']['delete_count']);
        $this->assertSame(0, (int) $ui['confirm_no_perm']['shown']);
        $this->assertSame(0, (int) $ui['confirm_no_perm']['delete_count']);
    }

    public function test_confirm_opens_modal_cancel_does_not_fetch_success_toasts_and_closes(): void
    {
        $ui = $this->simulateDeleteThreadUi();

        $this->assertSame('Удалить чат', (string) $ui['confirm']['title']);
        $this->assertStringContainsString('удалить этот чат', (string) $ui['confirm']['text']);
        $this->assertSame(1, (int) $ui['confirm']['shown']);
        $this->assertSame(0, (int) $ui['cancel']['delete_count']);
        $this->assertSame('', (string) $ui['cancel']['toast']);
        $this->assertSame('Чат удалён.', (string) $ui['ok']['toast']);
        $this->assertSame('success', (string) $ui['ok']['toast_type']);
        $this->assertSame(1, (int) $ui['ok']['closed']);
        $this->assertSame(0, (int) $ui['ok']['cache_has_thread']);
        $this->assertSame('', (string) $ui['ok']['error']);
        $this->assertGreaterThan(0, (int) $ui['ok']['delete_count']);
        $this->assertSame('none', (string) $ui['ok']['display']);
        $this->assertSame('DELETE', (string) $ui['ok']['fetch_method']);
        $this->assertSame(1, (int) $ui['ok']['headers_json']);
        $this->assertSame('same-origin', (string) $ui['ok']['fetch_credentials']);
    }

    public function test_failure_shows_thread_field_error_and_keeps_thread(): void
    {
        $ui = $this->simulateDeleteThreadUi();

        $this->assertSame('Нельзя удалить чат учебной группы.', (string) $ui['fail']['error']);
        $this->assertSame('', (string) $ui['fail']['toast']);
        $this->assertSame(0, (int) $ui['fail']['closed']);
        $this->assertSame(1, (int) $ui['fail']['cache_has_thread']);
        $this->assertSame('', (string) $ui['fail']['display']);
        $this->assertSame('Не удалось удалить чат.', (string) $ui['network']['error']);
    }

    public function test_inbox_bump_removed_closes_open_dialog_without_upsert(): void
    {
        $ui = $this->simulateDeleteThreadUi();

        $this->assertSame(1, (int) $ui['bump_current']['closed']);
        $this->assertSame(0, (int) $ui['bump_current']['cache_has_thread']);
        $this->assertSame('none', (string) $ui['bump_current']['display']);
        $this->assertSame(0, (int) $ui['bump_current']['upsert_count']);
        $this->assertSame(0, (int) $ui['bump_other']['closed']);
        $this->assertSame(1, (int) $ui['bump_other']['cache_has_thread']);
        $this->assertSame('', (string) $ui['bump_other']['display']);
        $this->assertSame(0, (int) $ui['bump_other']['upsert_count']);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateDeleteThreadUi(): array
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

function makeEl(tag) {
    const el = {
        tagName: String(tag || 'div').toUpperCase(),
        className: '',
        style: { display: 'none' },
        attrs: {},
        _text: '',
        innerHTML: '',
        get textContent() { return this._text; },
        set textContent(v) { this._text = v == null ? '' : String(v); },
        setAttribute(k, v) { this.attrs[k] = String(v); },
        getAttribute(k) {
            return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
        },
        classList: {
            add: function () {},
            remove: function () {},
            contains: function () { return false; }
        }
    };
    return el;
}

const els = {
    deleteThreadBtn: makeEl('button'),
    threadDeleteError: makeEl('div'),
    threadTitle: makeEl('div'),
    threadSubtitle: makeEl('div'),
    threadAvatar: Object.assign(makeEl('img'), { src: '' }),
    msgInput: Object.assign(makeEl('input'), { value: '' }),
    messagesBox: makeEl('div'),
    chatApp: makeEl('div'),
    confirmDeleteModal: makeEl('div')
};
els.chatApp.setAttribute('data-can-delete-thread', '1');

global.document = {
    getElementById(id) { return els[id] || null; }
};

const root = els.chatApp;
function threadUrl(id) { return '/chat/api/threads/' + id; }
let lastHeadersJson = 0;
function headers(json) {
    lastHeadersJson = json ? 1 : 0;
    return { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
}
function fieldError(xhrJson, field) {
    if (xhrJson && xhrJson.errors && xhrJson.errors[field]) {
        const val = xhrJson.errors[field];
        return Array.isArray(val) ? String(val[0] || '') : String(val);
    }
    return xhrJson && xhrJson.message ? String(xhrJson.message) : '';
}
function applyThreadFilter(list) { return list; }
function renderThreads() {}
function setHeaderPeerClickable() {}
function setComposerEnabled() {}
function setUnreadBadge() {}
let upsertCount = 0;
function upsertThread() { upsertCount += 1; }

let currentThreadId = 15;
let currentPeerId = 9;
let currentIsGroup = false;
let currentTeamId = null;
let lastMessageId = 1;
let hasOlder = true;
let threadsCache = [{ id: 15 }, { id: 99 }];
let lastToast = '';
let lastToastType = '';
let lastConfirm = null;
let autoConfirm = true;
let closed = 0;
let deleteCount = 0;
let failMode = '';
let lastFetch = { method: '', credentials: '' };

global.showConfirmDeleteModal = function (title, text, cb) {
    lastConfirm = { title: title, text: text };
    if (autoConfirm && typeof cb === 'function') cb();
};
global.showToast = function (message, type) {
    lastToast = message;
    lastToastType = type || '';
};

eval(extractFn(chatJs, 'canDeleteThread'));
eval(extractFn(chatJs, 'showThreadDeleteError'));
eval(extractFn(chatJs, 'setDeleteThreadVisible'));
eval(extractFn(chatJs, 'confirmDeleteThread'));
eval(extractFn(chatJs, 'submitDeleteThread'));
eval(extractFn(chatJs, 'chatToast'));
eval(extractFn(chatJs, 'closeCurrentThread'));
eval(extractFn(chatJs, 'applyInboxBump'));

function snapshot() {
    return {
        display: els.deleteThreadBtn.style.display,
        error: els.threadDeleteError.textContent,
        toast: lastToast,
        toast_type: lastToastType,
        closed: closed,
        cache_has_thread: threadsCache.some(function (t) { return Number(t.id) === 15; }) ? 1 : 0,
        delete_count: deleteCount,
        title: lastConfirm ? lastConfirm.title : '',
        text: lastConfirm ? lastConfirm.text : '',
        shown: lastConfirm ? 1 : 0,
        fetch_method: lastFetch.method,
        fetch_credentials: lastFetch.credentials,
        headers_json: lastHeadersJson,
        upsert_count: upsertCount
    };
}

const out = {};

currentThreadId = null;
setDeleteThreadVisible();
out.idle = snapshot();

currentThreadId = 15;
els.chatApp.setAttribute('data-can-delete-thread', '0');
setDeleteThreadVisible();
out.no_perm = snapshot();

els.chatApp.setAttribute('data-can-delete-thread', '1');
currentTeamId = 4;
setDeleteThreadVisible();
out.team = snapshot();

currentTeamId = null;
setDeleteThreadVisible();
out.open = snapshot();

currentThreadId = 20;
currentIsGroup = true;
currentTeamId = 4;
setDeleteThreadVisible();
out.after_team_open = snapshot();

currentThreadId = 15;
currentIsGroup = false;
currentTeamId = null;
setDeleteThreadVisible();
out.after_private_again = snapshot();

currentIsGroup = true;
currentTeamId = null;
setDeleteThreadVisible();
out.manual_group = snapshot();
currentIsGroup = false;

global.fetch = function (url, opts) {
    deleteCount += 1;
    lastFetch = {
        method: opts && opts.method ? String(opts.method) : '',
        credentials: opts && opts.credentials ? String(opts.credentials) : ''
    };
    if (failMode === 'network') {
        return Promise.reject(new Error('net'));
    }
    if (failMode === '422') {
        return Promise.resolve({
            ok: false,
            json: function () {
                return Promise.resolve({
                    message: 'Нельзя удалить чат учебной группы.',
                    errors: { thread: ['Нельзя удалить чат учебной группы.'] }
                });
            }
        });
    }
    return Promise.resolve({
        ok: true,
        json: function () {
            return Promise.resolve({ ok: true, message: 'Чат удалён.', thread_id: 15 });
        }
    });
};

const origClose = closeCurrentThread;
closeCurrentThread = function () {
    closed += 1;
    origClose();
};

lastConfirm = null;
deleteCount = 0;
currentThreadId = null;
currentTeamId = null;
confirmDeleteThread();
out.confirm_idle = snapshot();

currentThreadId = 15;
currentTeamId = 4;
lastConfirm = null;
deleteCount = 0;
confirmDeleteThread();
out.confirm_team = snapshot();

currentTeamId = null;
els.chatApp.setAttribute('data-can-delete-thread', '0');
lastConfirm = null;
deleteCount = 0;
confirmDeleteThread();
out.confirm_no_perm = snapshot();
els.chatApp.setAttribute('data-can-delete-thread', '1');

upsertCount = 0;
closed = 0;
currentThreadId = 15;
currentTeamId = null;
threadsCache = [{ id: 15 }, { id: 99 }];
setDeleteThreadVisible();
applyInboxBump({ thread_id: 15, removed: true, unread_total: 0 });
out.bump_current = snapshot();

upsertCount = 0;
closed = 0;
currentThreadId = 15;
currentTeamId = null;
threadsCache = [{ id: 15 }, { id: 99 }];
setDeleteThreadVisible();
applyInboxBump({ thread_id: 99, removed: true, unread_total: 0 });
out.bump_other = snapshot();

lastConfirm = null;
autoConfirm = false;
currentThreadId = 15;
currentTeamId = null;
confirmDeleteThread();
out.confirm = snapshot();

lastConfirm = null;
deleteCount = 0;
lastToast = '';
confirmDeleteThread();
out.cancel = snapshot();

function afterFetch(fn) {
    return new Promise(function (resolve) {
        setTimeout(function () { resolve(fn()); }, 0);
    });
}

autoConfirm = true;
failMode = '';
closed = 0;
deleteCount = 0;
lastToast = '';
lastFetch = { method: '', credentials: '' };
lastHeadersJson = 0;
currentThreadId = 15;
currentTeamId = null;
threadsCache = [{ id: 15 }, { id: 99 }];
submitDeleteThread();

afterFetch(function () {
    out.ok = snapshot();
    failMode = '422';
    closed = 0;
    lastToast = '';
    currentThreadId = 15;
    currentTeamId = null;
    threadsCache = [{ id: 15 }];
    setDeleteThreadVisible();
    submitDeleteThread();
    return afterFetch(function () {
        out.fail = snapshot();
        failMode = 'network';
        lastToast = '';
        currentThreadId = 15;
        submitDeleteThread();
        return afterFetch(function () {
            out.network = snapshot();
            process.stdout.write(JSON.stringify(out));
        });
    });
});
JS;

        $tmp = sys_get_temp_dir().'/chat-del-ux-'.uniqid('', true).'.cjs';
        file_put_contents($tmp, $script);
        $cmd = 'node '.escapeshellarg($tmp).' '.escapeshellarg($chatJs).' 2>&1';
        exec($cmd, $lines, $code);
        @unlink($tmp);
        $this->assertSame(0, $code, implode("\n", $lines));
        $json = json_decode(implode("\n", $lines), true);
        $this->assertIsArray($json);

        return $json;
    }
}
