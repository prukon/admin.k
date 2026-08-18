<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX-баг: набор в диалоге 1 оставался в поле после перехода в диалог 2.
 * Серверный JSON 200 недостаточен — прогоняем реальный chat.js
 * (openThread / persistLeavingDraft / renderThreads / applyInboxBump / loadThreads).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatDraftUxFeatureTest extends ChatTestCase
{
    public function test_switching_dialogs_does_not_leave_previous_composer_text(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertSame(
            'черновик Б',
            $ui['switch_chats']['input'],
            'После перехода в другой диалог в поле должен быть его черновик, а не текст предыдущего'
        );
        $this->assertNotSame('текст А', $ui['switch_chats']['input']);
        $this->assertStringContainsString('Черновик: текст А', $ui['switch_chats']['list_html']);
        $this->assertStringContainsString('Черновик: черновик Б', $ui['switch_chats']['list_html']);
        $this->assertNotEmpty($ui['switch_chats']['draft_patches']);
        $this->assertSame('текст А', $ui['switch_chats']['draft_patches'][0]['body'] ?? null);
        $this->assertStringContainsString(
            '/chat/api/threads/1/draft',
            (string) ($ui['switch_chats']['draft_patches'][0]['url'] ?? '')
        );
    }

    public function test_opening_first_dialog_restores_server_draft_without_patching(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertSame('черновик Б', $ui['first_open']['input']);
        $this->assertSame(
            [],
            $ui['first_open']['draft_patches'],
            'Пока не было набора и не уходили из диалога — PATCH draft не шлём'
        );
    }

    public function test_reopening_same_dialog_does_not_save_empty_over_typed_text(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertSame(
            [],
            $ui['same_thread']['draft_patches'],
            'Повторный клик по тому же диалогу не должен слать PATCH'
        );
        $this->assertSame('текст А', $ui['same_thread']['input']);
    }

    public function test_inbox_poll_does_not_overwrite_composer_or_drop_local_draft_preview(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertSame(
            'печатаю сейчас',
            $ui['poll']['input'],
            'Опрос списка не должен затирать поле ввода открытого диалога'
        );
        $this->assertStringContainsString('Черновик: печатаю сейчас', $ui['poll']['list_html']);
        $this->assertStringNotContainsString('Черновик: старый', $ui['poll']['list_html']);
    }

    public function test_peer_inbox_bump_without_draft_keeps_my_draft_preview(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertStringContainsString('Черновик: мой черновик', $ui['bump_keeps_draft']['list_html']);
        $this->assertStringNotContainsString('>привет от собеседника</div>', $ui['bump_keeps_draft']['list_html']);
        $this->assertArrayNotHasKey('draft_body', $ui['bump_keeps_draft']['patch']);
    }

    public function test_sender_inbox_bump_clears_draft_preview_after_send(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertStringNotContainsString('Черновик:', $ui['bump_clears_draft']['list_html']);
        $this->assertStringContainsString('Отправлено', $ui['bump_clears_draft']['list_html']);
        $this->assertSame('', $ui['bump_clears_draft']['patch']['draft_body'] ?? null);
    }

    public function test_failed_send_puts_draft_preview_back(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertStringContainsString('Черновик: не ушло', $ui['failed_send']['list_html']);
        $this->assertSame('не ушло', $ui['failed_send']['input']);
    }

    public function test_thread_search_matches_own_draft_text(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertStringContainsString('Черновик: уникальныйчерновик', $ui['search_draft']['html']);
        $this->assertStringNotContainsString('Другой', $ui['search_draft']['html']);
    }

    public function test_draft_preview_escapes_html_and_ignores_whitespace_only(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertStringContainsString('&lt;img onerror=alert(1)&gt;', $ui['xss']['html']);
        $this->assertStringNotContainsString('<img onerror', $ui['xss']['html']);
        $this->assertStringNotContainsString('Черновик:', $ui['whitespace']['html']);
        $this->assertStringContainsString('>обычное</div>', $ui['whitespace']['html']);
    }

    public function test_typing_updates_list_preview_before_debounced_patch(): void
    {
        $ui = $this->simulateDraftUi();

        $this->assertSame(
            [],
            $ui['debounce']['patches_before_flush'],
            'Пока не прошли 500 мс — PATCH ещё нет'
        );
        $this->assertStringContainsString('Черновик: набор', $ui['debounce']['list_before_flush']);
        $this->assertCount(1, $ui['debounce']['patches_after_flush']);
        $this->assertSame('набор', $ui['debounce']['patches_after_flush'][0]['body'] ?? null);
        $this->assertSame(
            [],
            $ui['debounce']['patches_without_thread'],
            'Без открытого диалога набор не сохраняем'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateDraftUi(): array
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
        addEventListener() {},
        appendChild(child) {
            this.children.push(child);
            return child;
        },
        querySelector() { return null; },
        querySelectorAll() { return []; }
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
    threadAvatar: makeEl('img'),
    messagesBox: makeEl('div'),
    msgBodyError: makeEl('div'),
    threadPeerHit: makeEl('div')
};
els.threadSearch.value = '';
els.msgInput.value = '';
els.threadAvatar.style = { display: 'none' };

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector() { return null; },
    querySelectorAll() { return []; }
};

const svgTick = '<svg></svg>';
const urls = { threads: '/chat/api/threads' };
let currentThreadId = null;
let currentPeerId = null;
let threadsCache = [];
let draftCache = Object.create(null);
let lastPatchedDraft = Object.create(null);
let draftTimer = null;
let inboxPollStamp = '';
let lastMessageId = null;
let hasOlder = true;
const me = 1;

function headers() { return { Accept: 'application/json' }; }
function threadUrl(id, suffix) { return '/chat/api/threads/' + id + (suffix || ''); }
function setComposerEnabled() {}
function setHeaderPeerClickable() {}
function showMsgError() {}
function appendMessage() {}
function scrollBottom() {}
function subscribeThread() {}
function startPoll() {}
function setUnreadBadge() {}
function markThreadRead() { return Promise.resolve(); }

eval(extractFn(chatJs, 'ticksHtml'));
eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'pad'));
eval(extractFn(chatJs, 'isToday'));
eval(extractFn(chatJs, 'fmtTime'));
eval(extractFn(chatJs, 'sortThreads'));
eval(extractFn(chatJs, 'applyThreadFilter'));
eval(extractFn(chatJs, 'normalizeDraft'));
eval(extractFn(chatJs, 'rememberDraft'));
eval(extractFn(chatJs, 'mergeLocalDrafts'));
eval(extractFn(chatJs, 'composerDraftFor'));
eval(extractFn(chatJs, 'renderThreads'));
eval(extractFn(chatJs, 'upsertThread'));
eval(extractFn(chatJs, 'persistDraft'));
eval(extractFn(chatJs, 'persistLeavingDraft'));
eval(extractFn(chatJs, 'scheduleDraftSave'));
eval(extractFn(chatJs, 'applyInboxBump'));
eval(extractFn(chatJs, 'loadThreads'));
eval(extractFn(chatJs, 'openThread'));

function collected(el) {
    return el.children.map(function (c) { return c.innerHTML; }).join('\n') + el.innerHTML;
}

function jsonOk(data) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: function () { return Promise.resolve(data); }
    });
}

let fetchLog = [];
let inboxPayload = { threads: [], unread_total: 0 };
let showPayloads = {};

global.fetch = function (url, opts) {
    const method = String((opts && opts.method) || 'GET').toUpperCase();
    const parsed = opts && opts.body ? JSON.parse(opts.body) : null;
    fetchLog.push({
        url: String(url),
        method: method,
        body: parsed && Object.prototype.hasOwnProperty.call(parsed, 'body') ? parsed.body : parsed
    });
    const u = String(url);
    if (method === 'PATCH' && u.indexOf('/draft') !== -1) {
        return jsonOk({ ok: true, draft_body: parsed && parsed.body ? parsed.body : '' });
    }
    if (method === 'GET' && u === '/chat/api/threads') {
        return jsonOk(inboxPayload);
    }
    const m = u.match(/\/chat\/api\/threads\/(\d+)$/);
    if (method === 'GET' && m) {
        const id = Number(m[1]);
        return jsonOk(showPayloads[id] || {
            thread: { id: id, title: 'Диалог', avatar: '/img/default-avatar.png', draft_body: '' },
            messages: [],
            unread_total: 0
        });
    }
    return jsonOk({});
};

function draftPatches() {
    return fetchLog.filter(function (c) {
        return c.method === 'PATCH' && String(c.url).indexOf('/draft') !== -1;
    });
}

function resetCaches() {
    threadsCache = [];
    draftCache = Object.create(null);
    lastPatchedDraft = Object.create(null);
    currentThreadId = null;
    currentPeerId = null;
    inboxPollStamp = '';
    draftTimer = null;
    fetchLog = [];
    els.threads.innerHTML = '';
    els.threads.children = [];
    els.msgInput.value = '';
    els.threadSearch.value = '';
}

function baseThread(id, title, last, draft) {
    return {
        id: id,
        title: title,
        avatar: '/img/default-avatar.png',
        last_message: last,
        last_message_time: '2026-08-01 09:30:00',
        last_message_is_mine: false,
        last_message_is_read: null,
        unread_count: 0,
        draft_body: draft || '',
        peer_id: id * 10,
        peer_is_online: false
    };
}

const realSetTimeout = setTimeout;
const realClearTimeout = clearTimeout;
let pendingDraftFn = null;
global.setTimeout = function (fn, ms) {
    if (ms === 500) {
        pendingDraftFn = fn;
        return 1;
    }
    return realSetTimeout(fn, ms);
};
global.clearTimeout = function (id) {
    if (id === 1) {
        pendingDraftFn = null;
        return;
    }
    return realClearTimeout(id);
};
function flushDraftDebounce() {
    if (typeof pendingDraftFn === 'function') {
        const fn = pendingDraftFn;
        pendingDraftFn = null;
        fn();
    }
}

function sleep(ms) {
    return new Promise(function (resolve) { realSetTimeout(resolve, ms); });
}

(async function main() {
    showPayloads[2] = {
        thread: {
            id: 2,
            title: 'Б',
            avatar: '/img/default-avatar.png',
            peer_id: 22,
            draft_body: 'черновик Б'
        },
        messages: [],
        unread_total: 0
    };
    showPayloads[1] = {
        thread: {
            id: 1,
            title: 'А',
            avatar: '/img/default-avatar.png',
            peer_id: 11,
            draft_body: ''
        },
        messages: [],
        unread_total: 0
    };

    resetCaches();
    currentThreadId = null;
    els.msgInput.value = '';
    fetchLog = [];
    openThread(2);
    await sleep(30);
    const first_open = { input: els.msgInput.value, draft_patches: draftPatches() };

    resetCaches();
    threadsCache = [
        baseThread(1, 'А', 'hello', ''),
        baseThread(2, 'Б', 'hi', 'черновик Б')
    ];
    mergeLocalDrafts(threadsCache);
    currentThreadId = 1;
    els.msgInput.value = 'текст А';
    fetchLog = [];
    openThread(2);
    await sleep(30);
    const switch_chats = {
        input: els.msgInput.value,
        list_html: collected(els.threads),
        draft_patches: draftPatches()
    };

    resetCaches();
    threadsCache = [baseThread(1, 'А', 'hello', '')];
    mergeLocalDrafts(threadsCache);
    currentThreadId = 1;
    els.msgInput.value = 'текст А';
    rememberDraft(1, 'текст А');
    fetchLog = [];
    persistLeavingDraft(1);
    openThread(1);
    await sleep(30);
    const same_thread = { input: els.msgInput.value, draft_patches: draftPatches() };

    resetCaches();
    threadsCache = [baseThread(1, 'А', 'hello', 'старый')];
    currentThreadId = 1;
    els.msgInput.value = 'печатаю сейчас';
    rememberDraft(1, 'печатаю сейчас');
    inboxPayload = {
        threads: [baseThread(1, 'А', 'hello', 'старый')],
        unread_total: 0
    };
    fetchLog = [];
    loadThreads();
    await sleep(30);
    const poll = { input: els.msgInput.value, list_html: collected(els.threads) };

    resetCaches();
    threadsCache = [baseThread(5, 'Собеседник', 'старое', 'мой черновик')];
    mergeLocalDrafts(threadsCache);
    currentThreadId = 9;
    let bumpPatch = null;
    const realUpsert = upsertThread;
    upsertThread = function (patch) {
        bumpPatch = Object.assign({}, patch);
        realUpsert(patch);
    };
    applyInboxBump({
        thread_id: 5,
        title: 'Собеседник',
        avatar: '/img/default-avatar.png',
        last_message: 'привет от собеседника',
        last_message_time: '2026-08-01 09:30:00',
        last_message_is_mine: false,
        last_message_is_read: null,
        unread_count: 1,
        unread_total: 1
    });
    const bump_keeps_draft = { list_html: collected(els.threads), patch: bumpPatch };
    upsertThread = realUpsert;

    resetCaches();
    threadsCache = [baseThread(5, 'Собеседник', 'старое', 'мой черновик')];
    mergeLocalDrafts(threadsCache);
    bumpPatch = null;
    upsertThread = function (patch) {
        bumpPatch = Object.assign({}, patch);
        realUpsert(patch);
    };
    applyInboxBump({
        thread_id: 5,
        title: 'Собеседник',
        avatar: '/img/default-avatar.png',
        last_message: 'Отправлено',
        last_message_time: '2026-08-01 09:30:00',
        last_message_is_mine: true,
        last_message_is_read: false,
        unread_count: 0,
        unread_total: 0,
        draft_body: ''
    });
    const bump_clears_draft = { list_html: collected(els.threads), patch: bumpPatch };
    upsertThread = realUpsert;

    resetCaches();
    threadsCache = [baseThread(1, 'А', 'hello', 'не ушло')];
    mergeLocalDrafts(threadsCache);
    currentThreadId = 1;
    rememberDraft(1, '');
    upsertThread({ id: 1, draft_body: '' });
    rememberDraft(1, 'не ушло');
    upsertThread({ id: 1, draft_body: 'не ушло' });
    els.msgInput.value = 'не ушло';
    const failed_send = { list_html: collected(els.threads), input: els.msgInput.value };

    resetCaches();
    threadsCache = [
        baseThread(1, 'Другой', 'нет', ''),
        baseThread(2, 'Искомый', 'нет', 'уникальныйчерновик')
    ];
    els.threadSearch.value = 'уникальныйчерновик';
    renderThreads(applyThreadFilter(threadsCache));
    const search_draft = { html: collected(els.threads) };

    resetCaches();
    renderThreads([baseThread(1, 'А', 'обычное', '<img onerror=alert(1)>')]);
    const xss = { html: collected(els.threads) };
    renderThreads([baseThread(1, 'А', 'обычное', '   ')]);
    const whitespace = { html: collected(els.threads) };

    resetCaches();
    threadsCache = [baseThread(1, 'А', 'hello', '')];
    currentThreadId = 1;
    els.msgInput.value = 'набор';
    fetchLog = [];
    pendingDraftFn = null;
    scheduleDraftSave();
    const debounceListBefore = collected(els.threads);
    const patches_before_flush = draftPatches().slice();
    flushDraftDebounce();
    const patches_after_flush = draftPatches().slice();
    currentThreadId = null;
    fetchLog = [];
    scheduleDraftSave();
    flushDraftDebounce();
    const patches_without_thread = draftPatches().slice();
    const debounce = {
        list_before_flush: debounceListBefore,
        patches_before_flush: patches_before_flush,
        patches_after_flush: patches_after_flush,
        patches_without_thread: patches_without_thread
    };

    process.stdout.write(JSON.stringify({
        switch_chats: switch_chats,
        first_open: first_open,
        same_thread: same_thread,
        poll: poll,
        bump_keeps_draft: bump_keeps_draft,
        bump_clears_draft: bump_clears_draft,
        failed_send: failed_send,
        search_draft: search_draft,
        xss: xss,
        whitespace: whitespace,
        debounce: debounce
    }));
})().catch(function (e) {
    process.stderr.write(String(e && e.stack ? e.stack : e));
    process.exit(1);
});
JS;

        $path = sys_get_temp_dir().'/chat-draft-ux-'.uniqid('', true).'.cjs';
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
