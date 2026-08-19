<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * Подгрузка истории в /chat: порог ≈ 1.5 экрана (не scrollTop < 40),
 * цепочка после успешной порции, без цикла при ошибке fetch,
 * короткая первая страница после openThread.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMobileHistoryUxFeatureTest extends ChatTestCase
{
    public function test_prefetch_starts_before_user_reaches_oldest_visible_message(): void
    {
        $ui = $this->simulateHistoryUi();

        $this->assertSame(480, (int) $ui['threshold_short_screen']);
        $this->assertSame(600, (int) $ui['threshold_tall_screen']);
        $this->assertGreaterThan(0, (int) $ui['mid_scroll']['older_fetches']);
        $this->assertStringContainsString('before_id=41', (string) $ui['mid_scroll']['url']);
    }

    public function test_history_does_not_prefetch_when_user_is_at_latest_messages(): void
    {
        $ui = $this->simulateHistoryUi();

        $this->assertSame(0, (int) $ui['at_bottom']['older_fetches']);
    }

    public function test_history_does_not_prefetch_when_message_pane_has_no_height(): void
    {
        $ui = $this->simulateHistoryUi();

        $this->assertSame(0, (int) $ui['zero_height']['older_fetches']);
    }

    public function test_opening_a_short_full_page_thread_loads_older_history_without_scrolling_to_top(): void
    {
        $ui = $this->simulateHistoryUi();

        $this->assertGreaterThan(0, (int) $ui['short_open']['older_fetches']);
        $this->assertStringContainsString('before_id=', (string) $ui['short_open']['url']);
        $this->assertSame(0, (int) $ui['tall_open']['older_fetches']);
    }

    public function test_failed_older_fetch_does_not_retry_in_a_loop(): void
    {
        $ui = $this->simulateHistoryUi();

        $this->assertSame(1, (int) $ui['failed_older']['older_fetches']);
    }

    public function test_history_js_uses_screen_threshold_and_does_not_retry_after_error(): void
    {
        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertStringNotContainsString('scrollTop < 40', $js);
        $this->assertStringContainsString('function olderPrefetchThreshold(', $js);
        $this->assertStringContainsString('Math.max(480, Math.floor((box.clientHeight || 0) * 1.5))', $js);
        $this->assertStringContainsString('if (!box || !box.clientHeight) return', $js);
        $this->assertStringContainsString('scrollBottom();', $js);
        $this->assertStringContainsString('maybeLoadOlder();', $js);

        $loadOlder = $this->extractFunction($js, 'loadOlder');
        $catchPos = strrpos($loadOlder, '.catch(');
        $this->assertNotFalse($catchPos);
        $success = substr($loadOlder, 0, $catchPos);
        $fail = substr($loadOlder, $catchPos);
        $this->assertStringContainsString('maybeLoadOlder()', $success);
        $this->assertStringNotContainsString('maybeLoadOlder()', $fail);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateHistoryUi(): array
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
        scrollTop: 0,
        clientHeight: 300,
        scrollHeight: 0,
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
        },
        insertBefore(child, ref) {
            if (!ref) return this.appendChild(child);
            if (child.parentElement && Array.isArray(child.parentElement.children)) {
                child.parentElement.children = child.parentElement.children.filter(function (c) { return c !== child; });
            }
            child.parentElement = this;
            const i = this.children.indexOf(ref);
            this.children.splice(i < 0 ? this.children.length : i, 0, child);
            return child;
        },
        querySelector(sel) {
            if (sel === '.chat-empty') {
                return this.children.find(function (c) { return String(c.className).indexOf('chat-empty') !== -1; }) || null;
            }
            return null;
        },
        remove() {
            if (!this.parentElement || !Array.isArray(this.parentElement.children)) return;
            this.parentElement.children = this.parentElement.children.filter((c) => c !== this);
            this.parentElement = null;
        }
    };
    Object.defineProperty(el, 'firstChild', {
        get() { return this.children[0] || null; }
    });
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

const els = {
    chatApp: makeEl('div'),
    threadTitle: makeEl('div'),
    threadAvatar: makeEl('img'),
    messagesBox: makeEl('div'),
    msgInput: makeEl('input'),
    threadSearch: makeEl('input')
};
els.chatApp.setAttribute('data-mobile-tab', 'messages');
els.threadAvatar.style = { display: 'none' };
els.msgInput.focus = function () {};

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector(sel) {
        if (sel === '#messagesBox [data-mid]') {
            return els.messagesBox.children.find(function (c) { return c.getAttribute('data-mid'); }) || null;
        }
        return null;
    },
    querySelectorAll() { return []; }
};

function headers() { return { Accept: 'application/json' }; }
function threadUrl(id, suffix) { return '/chat/api/threads/' + id + (suffix || ''); }
function persistLeavingDraft() {}
function setComposerEnabled() {}
function setHeaderPeerClickable() {}
function showMsgError() {}
function composerDraftFor() { return ''; }
function upsertThread() {}
function subscribeThread() {}
function startPoll() {}
function setUnreadBadge() {}
function setMobileTabButtons() {}
function messageExists(id) {
    return els.messagesBox.children.some(function (c) {
        return String(c.getAttribute('data-mid')) === String(id);
    });
}

let rowPx = 8;
function appendMessage(m, opts) {
    opts = opts || {};
    const row = makeEl('div');
    if (m.id) row.setAttribute('data-mid', String(m.id));
    if (opts.prepend) {
        els.messagesBox.insertBefore(row, els.messagesBox.firstChild);
    } else {
        els.messagesBox.appendChild(row);
    }
    els.messagesBox.scrollHeight = els.messagesBox.children.length * rowPx;
}

let me = 7;
let currentThreadId = null;
let currentPeerId = null;
let currentIsGroup = false;
let lastMessageId = null;
let hasOlder = false;
let loadingOlder = false;
let firstPageCount = 40;
let failOlder = false;
let fetchLog = [];

window.matchMedia = function (q) {
    return { matches: String(q).indexOf('991.98px') !== -1 };
};

function pageMessages(count, startId) {
    const rows = [];
    for (let i = 0; i < count; i++) {
        rows.push({ id: startId + i, user_id: 9, body: 'm' + (startId + i), created_at: '2026-08-01 12:00:00' });
    }
    return rows;
}

function jsonOk(data) {
    return Promise.resolve({
        ok: true,
        status: 200,
        json: function () { return Promise.resolve(data); }
    });
}

global.fetch = function (url) {
    const u = String(url);
    fetchLog.push(u);
    if (u.indexOf('/messages?before_id=') !== -1) {
        if (failOlder) {
            return Promise.reject(new Error('older-fail'));
        }
        return jsonOk(pageMessages(5, 1));
    }
    return jsonOk({
        thread: { id: 3, title: 'Диалог', avatar: '/img/default-avatar.png', peer_id: 9, draft_body: '' },
        messages: pageMessages(firstPageCount, 100),
        unread_total: 0
    });
};

eval(extractFn(chatJs, 'olderPrefetchThreshold'));
eval(extractFn(chatJs, 'maybeLoadOlder'));
eval(extractFn(chatJs, 'loadOlder'));
eval(extractFn(chatJs, 'scrollBottom'));
eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'openThread'));

function wait() {
    return new Promise(function (resolve) { setImmediate(resolve); });
}
async function flush() {
    for (let i = 0; i < 10; i++) await wait();
}

function seedFirstVisible(id) {
    const row = makeEl('div');
    row.setAttribute('data-mid', String(id));
    els.messagesBox.children = [row];
}

function olderFetches() {
    return fetchLog.filter(function (u) { return String(u).indexOf('/messages?before_id=') !== -1; });
}

(async function () {
    const threshold_short_screen = olderPrefetchThreshold({ clientHeight: 300 });
    const threshold_tall_screen = olderPrefetchThreshold({ clientHeight: 400 });

    currentThreadId = 3;
    hasOlder = true;
    loadingOlder = false;
    failOlder = false;
    fetchLog = [];
    els.messagesBox.clientHeight = 300;
    els.messagesBox.scrollHeight = 2000;
    els.messagesBox.scrollTop = 400;
    seedFirstVisible(41);
    maybeLoadOlder();
    await flush();
    const mid_scroll = {
        older_fetches: olderFetches().length,
        url: olderFetches()[0] || ''
    };

    fetchLog = [];
    loadingOlder = false;
    hasOlder = true;
    els.messagesBox.scrollTop = 1800;
    seedFirstVisible(41);
    maybeLoadOlder();
    await flush();
    const at_bottom = { older_fetches: olderFetches().length };

    fetchLog = [];
    loadingOlder = false;
    hasOlder = true;
    els.messagesBox.clientHeight = 0;
    els.messagesBox.scrollTop = 0;
    seedFirstVisible(41);
    maybeLoadOlder();
    await flush();
    const zero_height = { older_fetches: olderFetches().length };

    els.messagesBox.clientHeight = 300;
    els.messagesBox.children = [];
    els.messagesBox.scrollHeight = 0;
    els.messagesBox.scrollTop = 0;
    rowPx = 8;
    firstPageCount = 40;
    failOlder = false;
    fetchLog = [];
    currentThreadId = null;
    hasOlder = false;
    loadingOlder = false;
    await openThread(3);
    await flush();
    const short_open = {
        older_fetches: olderFetches().length,
        url: olderFetches()[0] || ''
    };

    els.messagesBox.children = [];
    els.messagesBox.scrollHeight = 0;
    els.messagesBox.scrollTop = 0;
    rowPx = 200;
    fetchLog = [];
    currentThreadId = null;
    hasOlder = false;
    loadingOlder = false;
    await openThread(3);
    await flush();
    const tall_open = { older_fetches: olderFetches().length };

    els.messagesBox.children = [];
    els.messagesBox.clientHeight = 300;
    els.messagesBox.scrollHeight = 2000;
    els.messagesBox.scrollTop = 10;
    rowPx = 8;
    seedFirstVisible(41);
    currentThreadId = 3;
    hasOlder = true;
    loadingOlder = false;
    failOlder = true;
    fetchLog = [];
    maybeLoadOlder();
    await flush();
    const failed_older = { older_fetches: olderFetches().length };

    process.stdout.write(JSON.stringify({
        threshold_short_screen: threshold_short_screen,
        threshold_tall_screen: threshold_tall_screen,
        mid_scroll: mid_scroll,
        at_bottom: at_bottom,
        zero_height: zero_height,
        short_open: short_open,
        tall_open: tall_open,
        failed_older: failed_older
    }));
})().catch(function (err) {
    console.error(err && err.stack ? err.stack : err);
    process.exit(1);
});
JS;

        $tmp = sys_get_temp_dir().'/chat-mobile-history-ux-'.uniqid('', true).'.cjs';
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

    private function extractFunction(string $src, string $name): string
    {
        $needle = 'function '.$name.'(';
        $start = strpos($src, $needle);
        $this->assertNotFalse($start, 'нет функции '.$name);
        $brace = strpos($src, '{', $start);
        $this->assertNotFalse($brace);
        $depth = 0;
        $len = strlen($src);
        for ($j = $brace; $j < $len; $j++) {
            $ch = $src[$j];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $start, $j - $start + 1);
                }
            }
        }

        $this->fail('незакрытая функция '.$name);
    }
}
