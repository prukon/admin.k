<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX удаления своего сообщения: корзина у пузыря, модалка, тост, сокет.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMessageDeleteUxFeatureTest extends ChatTestCase
{
    public function test_javascript_wires_own_message_delete(): void
    {
        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertStringContainsString('data-can-delete-own-message', $js);
        $this->assertStringContainsString('function canDeleteOwnMessage(', $js);
        $this->assertStringContainsString('function ownMessageDeleteBtnHtml(', $js);
        $this->assertStringContainsString('function confirmDeleteOwnMessage(', $js);
        $this->assertStringContainsString('function submitDeleteOwnMessage(', $js);
        $this->assertStringContainsString('function removeMessageRow(', $js);
        $this->assertStringContainsString('function applyMessageDeleted(', $js);
        $this->assertStringContainsString("listen('.message.deleted'", $js);
        $this->assertStringContainsString("stopListening('.message.deleted')", $js);
        $this->assertStringContainsString("'/messages/' + messageId", $js);
        $this->assertStringContainsString('msg-delete-btn', $js);
        $this->assertStringContainsString('msgDeleteError', $js);
        $this->assertStringContainsString('Удалить сообщение', $js);
        $this->assertStringContainsString('пропадёт у всех участников', $js);
        $this->assertStringContainsString('ensureDeleteButton(', $js);
    }

    public function test_delete_button_only_on_own_saved_messages_with_permission(): void
    {
        $ui = $this->simulateOwnMessageDeleteUi();

        $this->assertSame('', (string) $ui['btn_no_perm']);
        $this->assertSame('', (string) $ui['btn_other']);
        $this->assertSame('', (string) $ui['btn_temp']);
        $this->assertStringContainsString('msg-delete-btn', (string) $ui['btn_mine']);
        $this->assertStringContainsString('Удалить сообщение', (string) $ui['btn_mine']);
    }

    public function test_confirm_cancel_success_error_and_socket(): void
    {
        $ui = $this->simulateOwnMessageDeleteUi();

        $this->assertSame(0, (int) $ui['confirm_no_perm']['shown']);
        $this->assertSame(0, (int) $ui['confirm_no_perm']['delete_count']);

        $this->assertSame('Удалить сообщение', (string) $ui['confirm']['title']);
        $this->assertStringContainsString('пропадёт у всех участников', (string) $ui['confirm']['text']);
        $this->assertSame(1, (int) $ui['confirm']['shown']);
        $this->assertSame(0, (int) $ui['cancel']['delete_count']);
        $this->assertSame('', (string) $ui['cancel']['toast']);

        $this->assertSame('Сообщение удалено.', (string) $ui['ok']['toast']);
        $this->assertSame('success', (string) $ui['ok']['toast_type']);
        $this->assertSame(0, (int) $ui['ok']['row_exists']);
        $this->assertStringContainsString('chat-empty', (string) $ui['ok']['empty']);
        $this->assertGreaterThan(0, (int) $ui['ok']['delete_count']);
        $this->assertSame('DELETE', (string) $ui['ok']['fetch_method']);
        $this->assertSame(1, (int) $ui['ok']['headers_json']);
        $this->assertSame('same-origin', (string) $ui['ok']['fetch_credentials']);
        $this->assertSame('', (string) $ui['ok']['error']);

        $this->assertSame('Не удалось удалить сообщение.', (string) $ui['fail']['error']);
        $this->assertSame('', (string) $ui['fail']['toast']);
        $this->assertSame(1, (int) $ui['fail']['row_exists']);

        $this->assertSame(0, (int) $ui['socket']['row_exists']);
        $this->assertStringContainsString('chat-empty', (string) $ui['socket']['empty']);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateOwnMessageDeleteUi(): array
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
        children: [],
        get textContent() { return this._text; },
        set textContent(v) { this._text = v == null ? '' : String(v); },
        setAttribute(k, v) { this.attrs[k] = String(v); },
        getAttribute(k) {
            return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null;
        },
        querySelector(sel) {
            if (sel.indexOf('data-mid=') !== -1) {
                const m = String(sel).match(/data-mid="([^"]+)"/);
                const want = m ? m[1] : '';
                return this.children.find(function (c) { return c.attrs && c.attrs['data-mid'] === want; }) || null;
            }
            if (sel === '.msg-row') {
                return this.children.find(function (c) { return String(c.className || '').indexOf('msg-row') !== -1; }) || null;
            }
            if (sel === '.msg-row[data-mid]') {
                return this.children.find(function (c) { return c.attrs && c.attrs['data-mid']; }) || null;
            }
            return null;
        },
        querySelectorAll(sel) {
            if (sel === '.msg-row[data-mid]') {
                return this.children.filter(function (c) { return c.attrs && c.attrs['data-mid']; });
            }
            return [];
        },
        remove() {
            const box = els.messagesBox;
            box.children = box.children.filter(function (c) { return c !== el; });
        }
    };
    return el;
}

const els = {
    msgDeleteError: makeEl('div'),
    messagesBox: makeEl('div'),
    chatApp: makeEl('div')
};
els.chatApp.setAttribute('data-can-delete-own-message', '1');

global.document = {
    getElementById(id) { return els[id] || null; }
};
global.CSS = { escape: function (v) { return String(v); } };

const root = els.chatApp;
function threadUrl(id, suffix) { return '/chat/api/threads/' + id + (suffix || ''); }
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

let currentThreadId = 15;
let lastMessageId = 41;
let lastToast = '';
let lastToastType = '';
let lastConfirm = null;
let autoConfirm = true;
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

eval(extractFn(chatJs, 'canDeleteOwnMessage'));
eval(extractFn(chatJs, 'ownMessageDeleteBtnHtml'));
eval(extractFn(chatJs, 'showMsgDeleteError'));
eval(extractFn(chatJs, 'removeMessageRow'));
eval(extractFn(chatJs, 'applyMessageDeleted'));
eval(extractFn(chatJs, 'confirmDeleteOwnMessage'));
eval(extractFn(chatJs, 'submitDeleteOwnMessage'));
eval(extractFn(chatJs, 'chatToast'));

function seedRow(id) {
    const row = makeEl('div');
    row.className = 'msg-row msg-mine';
    row.setAttribute('data-mid', String(id));
    els.messagesBox.children = [row];
    els.messagesBox.innerHTML = '';
    lastMessageId = id;
}

function snapshot() {
    const row = els.messagesBox.querySelector('[data-mid="41"]');
    return {
        error: els.msgDeleteError.textContent,
        toast: lastToast,
        toast_type: lastToastType,
        delete_count: deleteCount,
        title: lastConfirm ? lastConfirm.title : '',
        text: lastConfirm ? lastConfirm.text : '',
        shown: lastConfirm ? 1 : 0,
        fetch_method: lastFetch.method,
        fetch_credentials: lastFetch.credentials,
        headers_json: lastHeadersJson,
        row_exists: row ? 1 : 0,
        empty: els.messagesBox.innerHTML
    };
}

const out = {};
out.btn_no_perm = (function () {
    els.chatApp.setAttribute('data-can-delete-own-message', '0');
    return ownMessageDeleteBtnHtml(true, false);
})();
els.chatApp.setAttribute('data-can-delete-own-message', '1');
out.btn_other = ownMessageDeleteBtnHtml(false, false);
out.btn_temp = ownMessageDeleteBtnHtml(true, true);
out.btn_mine = ownMessageDeleteBtnHtml(true, false);

lastConfirm = null;
deleteCount = 0;
els.chatApp.setAttribute('data-can-delete-own-message', '0');
confirmDeleteOwnMessage(41);
out.confirm_no_perm = snapshot();
els.chatApp.setAttribute('data-can-delete-own-message', '1');

global.fetch = function (url, opts) {
    deleteCount += 1;
    lastFetch = {
        method: opts && opts.method ? String(opts.method) : '',
        credentials: opts && opts.credentials ? String(opts.credentials) : ''
    };
    if (failMode === '403') {
        return Promise.resolve({
            ok: false,
            json: function () {
                return Promise.resolve({});
            }
        });
    }
    return Promise.resolve({
        ok: true,
        json: function () {
            return Promise.resolve({ ok: true, message: 'Сообщение удалено.', thread_id: 15, message_id: 41 });
        }
    });
};

lastConfirm = null;
autoConfirm = false;
confirmDeleteOwnMessage(41);
out.confirm = snapshot();

lastConfirm = null;
deleteCount = 0;
lastToast = '';
confirmDeleteOwnMessage(41);
out.cancel = snapshot();

function afterFetch(fn) {
    return new Promise(function (resolve) {
        setTimeout(function () { resolve(fn()); }, 0);
    });
}

autoConfirm = true;
failMode = '';
deleteCount = 0;
lastToast = '';
lastFetch = { method: '', credentials: '' };
lastHeadersJson = 0;
seedRow(41);
submitDeleteOwnMessage(41);

afterFetch(function () {
    out.ok = snapshot();
    failMode = '403';
    lastToast = '';
    seedRow(41);
    submitDeleteOwnMessage(41);
    return afterFetch(function () {
        out.fail = snapshot();
        seedRow(41);
        applyMessageDeleted({ thread_id: 15, message_id: 41 });
        out.socket = snapshot();
        process.stdout.write(JSON.stringify(out));
    });
});
JS;

        $tmp = sys_get_temp_dir().'/chat-msg-del-ux-'.uniqid('', true).'.cjs';
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
