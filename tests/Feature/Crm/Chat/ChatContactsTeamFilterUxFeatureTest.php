<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX-баги фильтра групп в контактах: повторное открытие оставляло прошлую группу,
 * поиск сбрасывал team_id, 422 показывался как пустой список без ошибки под селектом.
 *
 * Серверный JSON 200 недостаточен — прогоняем реальный chat.js
 * (openContacts / loadContacts / change фильтра / input поиска).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatContactsTeamFilterUxFeatureTest extends ChatTestCase
{
    public function test_reopening_contacts_resets_group_filter_and_does_not_keep_previous_team(): void
    {
        $ui = $this->simulateContactsFilterUi();

        $this->assertSame('', $ui['reopen']['team_value']);
        $this->assertSame('', $ui['reopen']['search_value']);
        $this->assertSame('', $ui['reopen']['team_error']);
        $this->assertSame('', $ui['reopen']['search_error']);
        $this->assertSame(1, $ui['reopen']['modal']);
        $this->assertStringNotContainsString('team_id=', (string) $ui['reopen']['url']);
        $this->assertStringNotContainsString('q=', (string) $ui['reopen']['url']);
        $this->assertStringContainsString('/chat/api/users', (string) $ui['reopen']['url']);
    }

    public function test_changing_group_keeps_typed_search_in_the_same_request(): void
    {
        $ui = $this->simulateContactsFilterUi();

        $url = urldecode((string) $ui['change_keeps_search']['url']);
        $this->assertStringContainsString('team_id=15', $url);
        $this->assertStringContainsString('q=Иванов', $url);
    }

    public function test_typing_search_does_not_drop_selected_group(): void
    {
        $ui = $this->simulateContactsFilterUi();

        $this->assertSame(
            [],
            $ui['search_keeps_team']['urls_before_flush'],
            'Пока не прошли 250 мс — запрос поиска ещё нет'
        );
        $url = urldecode((string) $ui['search_keeps_team']['url']);
        $this->assertStringContainsString('team_id=15', $url);
        $this->assertStringContainsString('q=Сидоров', $url);
    }

    public function test_all_groups_request_does_not_send_team_id(): void
    {
        $ui = $this->simulateContactsFilterUi();

        $this->assertStringNotContainsString('team_id=', (string) $ui['all_groups']['url']);
        $this->assertStringContainsString('/chat/api/users', (string) $ui['all_groups']['url']);
    }

    public function test_team_validation_error_is_shown_under_select_not_as_empty_list_only(): void
    {
        $ui = $this->simulateContactsFilterUi();

        $this->assertSame(
            'Выберите группу из списка.',
            $ui['team_error']['team_error']
        );
        $this->assertSame(
            '',
            $ui['team_error']['search_error'],
            'Ошибка team_id не должна дублироваться под поиском'
        );
        $this->assertStringContainsString('Ничего не найдено', (string) $ui['team_error']['list_html']);
    }

    public function test_search_validation_error_stays_under_search_not_under_group_select(): void
    {
        $ui = $this->simulateContactsFilterUi();

        $this->assertSame('', $ui['q_error']['team_error']);
        $this->assertSame(
            'Строка поиска слишком длинная (максимум 120 символов).',
            $ui['q_error']['search_error']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateContactsFilterUi(): array
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

function extractListener(src, elementId, event) {
    const needle = "getElementById('" + elementId + "').addEventListener('" + event + "'";
    const start = src.indexOf(needle);
    if (start < 0) {
        throw new Error('missing listener ' + elementId + ' ' + event);
    }
    const fnPos = src.indexOf('function', start);
    const brace = src.indexOf('{', fnPos);
    let depth = 0;
    for (let j = brace; j < src.length; j++) {
        if (src[j] === '{') depth++;
        else if (src[j] === '}') {
            depth--;
            if (depth === 0) {
                return src.slice(fnPos, j + 1);
            }
        }
    }
    throw new Error('unclosed listener ' + elementId);
}

function makeEl(tag) {
    const el = {
        tagName: String(tag || 'div').toUpperCase(),
        className: '',
        style: {},
        children: [],
        attrs: {},
        value: '',
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
        addEventListener() {},
        appendChild(child) {
            this.children.push(child);
            return child;
        },
        querySelector() {
            const row = makeEl('div');
            row.className = 'contact-row';
            return row;
        }
    };
    return el;
}

const els = {
    contactsList: makeEl('ul'),
    contactsSearch: makeEl('input'),
    contactsTeamFilter: makeEl('select'),
    contactsError: makeEl('div'),
    contactsTeamError: makeEl('div'),
    openContactsBtn: makeEl('button')
};
els.contactsSearch.value = '';
els.contactsTeamFilter.value = '';

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector() { return null; }
};

const urls = { users: '/chat/api/users' };
function headers() { return { Accept: 'application/json' }; }

let pending = [];
let fetchUrls = [];
let nextPayload = { ok: true, body: [] };
global.fetch = function (url) {
    fetchUrls.push(String(url));
    const statusOk = !!nextPayload.ok;
    const body = nextPayload.body;
    const p = Promise.resolve({
        ok: statusOk,
        json() { return Promise.resolve(body); }
    });
    pending.push(p);
    return p;
};

        async function flushFetch() {
            for (let i = 0; i < 8; i++) {
                await Promise.resolve();
                if (pending.length) {
                    await Promise.all(pending.slice());
                    pending = [];
                }
            }
        }

const timers = [];
global.setTimeout = function (fn) {
    timers.push(fn);
    return timers.length;
};
global.clearTimeout = function () {};
function flushTimers() {
    const queued = timers.splice(0);
    queued.forEach(function (fn) { fn(); });
}

let modalShown = 0;
function contactsModal() {
    return {
        show() { modalShown += 1; },
        hide() {}
    };
}

eval(extractFn(chatJs, 'fieldError'));
eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'showContactsError'));
eval(extractFn(chatJs, 'showContactsTeamError'));
eval(extractFn(chatJs, 'contactsTeamValue'));
eval(extractFn(chatJs, 'renderContacts'));
eval(extractFn(chatJs, 'loadContacts'));

let contactsDebounce = null;
const openContactsClick = eval('(' + extractListener(chatJs, 'openContactsBtn', 'click') + ')');
const onSearchInput = eval('(' + extractListener(chatJs, 'contactsSearch', 'input') + ')');
const onTeamChange = eval('(' + extractListener(chatJs, 'contactsTeamFilter', 'change') + ')');

(async function main() {
    nextPayload = { ok: true, body: [] };
    els.contactsTeamFilter.value = '15';
    els.contactsSearch.value = 'Петров';
    els.contactsTeamError.textContent = 'старая ошибка';
    els.contactsError.textContent = 'старый поиск';
    fetchUrls = [];
    modalShown = 0;
    openContactsClick.call(els.openContactsBtn);
    await flushFetch();
    const reopen = {
        team_value: els.contactsTeamFilter.value,
        search_value: els.contactsSearch.value,
        team_error: els.contactsTeamError.textContent,
        search_error: els.contactsError.textContent,
        url: fetchUrls[fetchUrls.length - 1] || '',
        modal: modalShown
    };

    nextPayload = { ok: true, body: [] };
    els.contactsSearch.value = 'Иванов';
    els.contactsTeamFilter.value = '15';
    fetchUrls = [];
    onTeamChange.call(els.contactsTeamFilter);
    await flushFetch();
    const change_keeps_search = { url: fetchUrls[fetchUrls.length - 1] || '' };

    nextPayload = { ok: true, body: [] };
    els.contactsTeamFilter.value = '15';
    els.contactsSearch.value = 'Сидоров';
    fetchUrls = [];
    onSearchInput.call(els.contactsSearch);
    const urls_before_flush = fetchUrls.slice();
    flushTimers();
    await flushFetch();
    const search_keeps_team = {
        urls_before_flush: urls_before_flush,
        url: fetchUrls[fetchUrls.length - 1] || ''
    };

    nextPayload = { ok: true, body: [] };
    els.contactsTeamFilter.value = '';
    els.contactsSearch.value = '';
    fetchUrls = [];
    loadContacts('');
    await flushFetch();
    const all_groups = { url: fetchUrls[fetchUrls.length - 1] || '' };

    nextPayload = {
        ok: false,
        body: {
            message: 'The given data was invalid.',
            errors: { team_id: ['Выберите группу из списка.'] }
        }
    };
    els.contactsTeamFilter.value = 'abc';
    els.contactsList.innerHTML = '';
    els.contactsTeamError.textContent = '';
    els.contactsError.textContent = '';
    loadContacts('');
    await flushFetch();
    const team_error = {
        team_error: els.contactsTeamError.textContent,
        search_error: els.contactsError.textContent,
        list_html: els.contactsList.innerHTML
    };

    nextPayload = {
        ok: false,
        body: {
            message: 'The given data was invalid.',
            errors: { q: ['Строка поиска слишком длинная (максимум 120 символов).'] }
        }
    };
    els.contactsTeamFilter.value = '';
    els.contactsTeamError.textContent = '';
    els.contactsError.textContent = '';
    loadContacts('x');
    await flushFetch();
    const q_error = {
        team_error: els.contactsTeamError.textContent,
        search_error: els.contactsError.textContent
    };

    process.stdout.write(JSON.stringify({
        reopen: reopen,
        change_keeps_search: change_keeps_search,
        search_keeps_team: search_keeps_team,
        all_groups: all_groups,
        team_error: team_error,
        q_error: q_error
    }));
})().catch(function (e) {
    process.stderr.write(String(e && e.stack ? e.stack : e));
    process.exit(1);
});
JS;

        $path = sys_get_temp_dir().'/chat-contacts-team-filter-ux-'.uniqid('', true).'.cjs';
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
