<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX: sortThreads / inbox.bump — непрочитанные сверху, пустая группа не всплывает
 * над перепиской (last_message_time null, не updated_at).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatInboxSortUxFeatureTest extends ChatTestCase
{
    public function test_sort_threads_puts_unread_first_then_last_message_then_empty(): void
    {
        $ui = $this->simulateInboxSort();

        $this->assertSame([11, 10, 99], array_map('intval', $ui['sort']['ids'] ?? []));
    }

    public function test_empty_group_bump_stays_below_dialog_with_message(): void
    {
        $ui = $this->simulateInboxSort();

        $html = (string) ($ui['empty_bump']['html'] ?? '');
        $this->assertSame(
            [10, 99],
            array_map('intval', $ui['empty_bump']['ids'] ?? []),
            'inbox.bump пустой группы с last_message_time null не должен всплывать над перепиской'
        );
        $this->assertStringContainsString('Старый диалог', $html);
        $this->assertStringContainsString('Новая группа', $html);
        $posDialog = strpos($html, 'Старый диалог');
        $posGroup = strpos($html, 'Новая группа');
        $this->assertNotFalse($posDialog);
        $this->assertNotFalse($posGroup);
        $this->assertLessThan($posGroup, $posDialog);
        $this->assertStringNotContainsString(
            now()->format('d.m.y'),
            $html,
            'пустое last_message_time не должно рисовать сегодняшнюю дату как время строки'
        );
    }

    public function test_stale_updated_at_time_on_empty_group_is_not_used_when_time_is_null(): void
    {
        $ui = $this->simulateInboxSort();

        $this->assertSame(
            [10, 77],
            array_map('intval', $ui['null_time_not_now']['ids'] ?? [])
        );
    }

    public function test_message_bump_promotes_empty_group_above_other_empty(): void
    {
        $ui = $this->simulateInboxSort();

        $this->assertSame(
            [20, 10, 99],
            array_map('intval', $ui['message_bump']['ids'] ?? [])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateInboxSort(): array
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
        children: [],
        attrs: {},
        _text: '',
        _html: '',
        get textContent() { return this._text; },
        set textContent(v) {
            this._text = v == null ? '' : String(v);
            this._html = this._text;
        },
        get innerHTML() { return this._html; },
        set innerHTML(v) {
            this._html = v == null ? '' : String(v);
            if (this._html === '') {
                this.children = [];
            }
        },
        setAttribute(k, v) { this.attrs[k] = String(v); },
        addEventListener() {},
        appendChild(child) {
            this.children.push(child);
            return child;
        }
    };
    return el;
}

const els = { threads: makeEl('div'), threadSearch: makeEl('input'), groupThreads: makeEl('div') };
els.threadSearch.value = '';
global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); }
};
global.window = { matchMedia() { return { matches: false }; } };

const svgTick = '<svg></svg>';
let currentThreadId = null;
let threadsCache = [];
function collected(el) {
    return el.children.map(function (c) { return c.innerHTML; }).join('\n') + el.innerHTML;
}
function setUnreadBadge() {}

eval(extractFn(chatJs, 'ticksHtml'));
eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'pad'));
eval(extractFn(chatJs, 'isToday'));
eval(extractFn(chatJs, 'fmtTime'));
eval(extractFn(chatJs, 'normalizeDraft'));
eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'sortThreads'));
eval(extractFn(chatJs, 'applyThreadFilter'));
eval(extractFn(chatJs, 'renderThreads'));
eval(extractFn(chatJs, 'upsertThread'));
eval(extractFn(chatJs, 'applyInboxBump'));

threadsCache = [
    {
        id: 10,
        title: 'Старый диалог',
        is_group: false,
        last_message: 'привет',
        last_message_time: '2026-08-01 09:30:00',
        unread_count: 0,
        avatar: '/img/default-avatar.png'
    },
    {
        id: 11,
        title: 'Непрочитанный',
        is_group: false,
        last_message: 'входящее',
        last_message_time: '2026-07-01 09:30:00',
        unread_count: 2,
        avatar: '/img/default-avatar.png'
    },
    {
        id: 99,
        title: 'Новая группа',
        is_group: true,
        last_message: null,
        last_message_time: null,
        unread_count: 0,
        avatar: '/img/default-avatar.png'
    }
];
sortThreads(threadsCache);
const sort = { ids: threadsCache.map(function (t) { return t.id; }) };

threadsCache = [
    {
        id: 10,
        title: 'Старый диалог',
        is_group: false,
        last_message: 'привет',
        last_message_time: '2026-08-01 09:30:00',
        unread_count: 0,
        avatar: '/img/default-avatar.png'
    }
];
els.threads.innerHTML = '';
applyInboxBump({
    thread_id: 99,
    title: 'Новая группа',
    is_group: true,
    last_message: null,
    last_message_time: null,
    unread_count: 0,
    avatar: '/img/default-avatar.png'
});
const empty_bump = {
    ids: threadsCache.map(function (t) { return t.id; }),
    html: collected(els.threads)
};

threadsCache = [
    {
        id: 10,
        title: 'Старый диалог',
        is_group: false,
        last_message: 'привет',
        last_message_time: '2026-08-01 09:30:00',
        unread_count: 0,
        avatar: '/img/default-avatar.png'
    }
];
applyInboxBump({
    thread_id: 77,
    title: 'Пустая с null',
    is_group: true,
    last_message: null,
    last_message_time: null,
    unread_count: 0,
    avatar: '/img/default-avatar.png'
});
const null_time_not_now = { ids: threadsCache.map(function (t) { return t.id; }) };

threadsCache = [
    {
        id: 10,
        title: 'Старый диалог',
        is_group: false,
        last_message: 'привет',
        last_message_time: '2026-08-01 09:30:00',
        unread_count: 0,
        avatar: '/img/default-avatar.png'
    },
    {
        id: 99,
        title: 'Ещё пустая',
        is_group: true,
        last_message: null,
        last_message_time: null,
        unread_count: 0,
        avatar: '/img/default-avatar.png'
    }
];
applyInboxBump({
    thread_id: 20,
    title: 'Живая группа',
    is_group: true,
    last_message: 'Первое',
    last_message_time: '2026-08-20 12:00:00',
    unread_count: 0,
    avatar: '/img/default-avatar.png'
});
const message_bump = { ids: threadsCache.map(function (t) { return t.id; }) };

process.stdout.write(JSON.stringify({
    sort: sort,
    empty_bump: empty_bump,
    null_time_not_now: null_time_not_now,
    message_bump: message_bump
}));
JS;

        $tmp = sys_get_temp_dir().'/chat-inbox-sort-'.uniqid('', true).'.cjs';
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
