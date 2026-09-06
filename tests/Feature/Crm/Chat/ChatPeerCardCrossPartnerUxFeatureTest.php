<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX-баг: клик по имени в шапке открытого диалога / по участнику группы / по аватарке
 * сообщения открывает #peerCardModal, а в #peerCardError оказывалось
 * «This action is unauthorized.» вместо карточки или русской ошибки поля user.
 *
 * Серверный JSON недостаточен — прогоняем реальный chat.js (openPeerCard,
 * headerPeerActivate, fieldError(..., 'user')).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatPeerCardCrossPartnerUxFeatureTest extends ChatTestCase
{
    public function test_idle_header_does_not_fetch_peer_card(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/id="threadPeerHit"[^>]*chat-header-peer is-idle/',
            $html
        );

        $ui = $this->simulatePeerCardUi();
        $this->assertSame(0, (int) $ui['idle']['fetch']);
        $this->assertSame(0, (int) $ui['idle']['modal']);
        $this->assertSame('', (string) $ui['idle']['error']);
    }

    public function test_header_click_on_private_dialog_shows_russian_user_error_not_english_unauthorized(): void
    {
        $ui = $this->simulatePeerCardUi();

        $this->assertSame(1, (int) $ui['header_forbidden']['fetch']);
        $this->assertSame(1, (int) $ui['header_forbidden']['modal']);
        $this->assertSame(
            'Нет доступа к карточке этого пользователя.',
            (string) $ui['header_forbidden']['error']
        );
        $this->assertStringNotContainsString(
            'This action is unauthorized.',
            (string) $ui['header_forbidden']['error']
        );
        $this->assertStringContainsString('/chat/api/users/42', (string) $ui['header_forbidden']['url']);
        $this->assertSame('application/json', (string) $ui['header_forbidden']['accept']);
        $this->assertSame('XMLHttpRequest', (string) $ui['header_forbidden']['xhr']);
        $this->assertStringContainsString('>-<', (string) $ui['header_forbidden']['body']);
    }

    public function test_header_click_on_group_opens_group_card_not_person_card(): void
    {
        $ui = $this->simulatePeerCardUi();

        $this->assertSame(0, (int) $ui['group_header']['user_fetch']);
        $this->assertSame(0, (int) $ui['group_header']['peer_modal']);
        $this->assertSame(1, (int) $ui['group_header']['group_modal']);
        $this->assertSame('', (string) $ui['group_header']['error']);
    }

    public function test_group_member_row_and_message_avatar_use_same_russian_error_slot(): void
    {
        $ui = $this->simulatePeerCardUi();

        $this->assertSame(1, (int) $ui['group_row']['fetch']);
        $this->assertSame(1, (int) $ui['group_row']['queued']);
        $this->assertSame(0, (int) $ui['group_row']['direct_modal']);
        $this->assertSame(
            'Нет доступа к карточке этого пользователя.',
            (string) $ui['group_row']['error']
        );
        $this->assertStringContainsString('/chat/api/users/77', (string) $ui['group_row']['url']);

        $this->assertSame(1, (int) $ui['avatar']['fetch']);
        $this->assertSame(1, (int) $ui['avatar']['direct_modal']);
        $this->assertSame(0, (int) $ui['avatar']['queued']);
        $this->assertSame(
            'Нет доступа к карточке этого пользователя.',
            (string) $ui['avatar']['error']
        );
        $this->assertStringContainsString('/chat/api/users/88', (string) $ui['avatar']['url']);
        $this->assertStringNotContainsString('This action is unauthorized.', (string) $ui['avatar']['error']);
    }

    public function test_errors_user_wins_over_english_generic_message(): void
    {
        $ui = $this->simulatePeerCardUi();

        $this->assertSame(
            'Нет доступа к карточке этого пользователя.',
            (string) $ui['mixed_payload']['error']
        );
        $this->assertStringNotContainsString(
            'This action is unauthorized.',
            (string) $ui['mixed_payload']['error']
        );
    }

    public function test_successful_card_clears_error_and_renders_name(): void
    {
        $ui = $this->simulatePeerCardUi();

        $this->assertSame('', (string) $ui['success']['error']);
        $this->assertStringContainsString('Иванов Иван', (string) $ui['success']['body']);
        $this->assertStringNotContainsString('This action is unauthorized.', (string) $ui['success']['body']);
    }

    public function test_reopening_after_forbidden_does_not_keep_english_error(): void
    {
        $ui = $this->simulatePeerCardUi();

        $this->assertSame(
            'Нет доступа к карточке этого пользователя.',
            (string) $ui['reopen']['after_forbidden']
        );
        $this->assertSame('', (string) $ui['reopen']['during_reload']);
        $this->assertSame('', (string) $ui['reopen']['after_success']);
        $this->assertStringContainsString('Петров Пётр', (string) $ui['reopen']['body']);
    }

    public function test_network_failure_shows_fallback_not_english_unauthorized(): void
    {
        $ui = $this->simulatePeerCardUi();

        $this->assertSame('Не удалось загрузить карточку.', (string) $ui['network']['error']);
        $this->assertStringNotContainsString('This action is unauthorized.', (string) $ui['network']['error']);
    }

    public function test_peer_card_modal_markup_is_standard_width_with_error_slot(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $start = strpos($html, 'id="peerCardModal"');
        $this->assertNotFalse($start);
        $modal = substr($html, $start, 900);
        $this->assertStringContainsString('class="modal-dialog"', $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
        $this->assertStringContainsString('id="peerCardError"', $modal);
        $this->assertStringContainsString('chat-field-error', $modal);
        $this->assertStringContainsString('Контакт', $modal);
        $this->assertStringContainsString('id="peerCardBody"', $modal);
        $this->assertStringContainsString('id="threadPeerHit"', $html);
        $this->assertStringContainsString('data-users-url="'.route('chat.api.users').'"', $html);
    }

    public function test_open_peer_card_js_prefers_errors_user_on_both_entry_paths(): void
    {
        $js = (string) file_get_contents(resource_path('js/chat.js'));

        $openPos = strpos($js, 'function openPeerCard(');
        $this->assertNotFalse($openPos);
        $openChunk = substr($js, $openPos, strpos($js, 'function showAccountCardError(') - $openPos);
        $fieldPos = strpos($openChunk, "fieldError(res.data, 'user')");
        $msgPos = strpos($openChunk, 'res.data.message');
        $this->assertNotFalse($fieldPos);
        $this->assertNotFalse($msgPos);
        $this->assertLessThan($msgPos, $fieldPos, 'errors.user должен читаться раньше generic message');

        $headerPos = strpos($js, 'function headerPeerActivate(');
        $this->assertNotFalse($headerPos);
        $headerChunk = substr($js, $headerPos, 220);
        $this->assertStringContainsString('if (currentIsGroup)', $headerChunk);
        $this->assertStringContainsString('openGroupCard()', $headerChunk);
        $this->assertStringContainsString('openPeerCard()', $headerChunk);

        $this->assertStringContainsString("openPeerCard(Number(row.getAttribute('data-id')), true)", $js);
        $this->assertStringContainsString("openPeerCard(Number(avatarBtn.getAttribute('data-user-id')))", $js);
        $this->assertStringNotContainsString('This action is unauthorized.', $js);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulatePeerCardUi(): array
    {
        $chatJs = resource_path('js/chat.js');
        $this->assertFileExists($chatJs);

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');

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
        get innerHTML() { return this._html; },
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
        }
    };
    const set = new Set();
    el.classList = {
        add(c) { set.add(c); el.className = Array.from(set).join(' '); },
        remove(c) { set.delete(c); el.className = Array.from(set).join(' '); },
        contains(c) { return set.has(c); }
    };
    el.closest = function (sel) {
        if (sel === '.msg-avatar-btn' && String(this.className).indexOf('msg-avatar-btn') !== -1) {
            return this;
        }
        if (String(sel).indexOf('tr[data-id]') === 0 && this.tagName === 'TR' && this.getAttribute('data-id')) {
            return this;
        }
        if (sel === '.js-remove-group-member' && String(this.className).indexOf('js-remove-group-member') !== -1) {
            return this;
        }
        return this.parentElement && this.parentElement.closest ? this.parentElement.closest(sel) : null;
    };
    return el;
}

const els = {
    peerCardError: makeEl('div'),
    peerCardBody: makeEl('div'),
    threadPeerHit: makeEl('div'),
    groupMembersBody: makeEl('tbody'),
    messagesBox: makeEl('div'),
    accountCardError: makeEl('div'),
    accountCardBody: makeEl('div')
};
els.threadPeerHit.className = 'chat-header-peer is-idle';

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector() { return null; }
};

const csrf = 'test-csrf';
const urls = { users: '/chat/api/users' };
let currentPeerId = null;
let currentIsGroup = false;
let currentThreadId = null;
const me = 1;
let modalShows = 0;
let groupShows = 0;
let queuedShows = 0;
let fetchLog = [];
let fetchMode = 'forbidden';

function jsonRes(ok, data) {
    return Promise.resolve({
        ok: ok,
        json: function () { return Promise.resolve(data); }
    });
}

global.fetch = function (url, opts) {
    const headers = (opts && opts.headers) || {};
    fetchLog.push({
        url: String(url),
        accept: headers.Accept || headers.accept || '',
        xhr: headers['X-Requested-With'] || ''
    });
    if (fetchMode === 'network') {
        return Promise.reject(new Error('offline'));
    }
    if (fetchMode === 'ok') {
        return jsonRes(true, {
            id: 9,
            full_name: 'Иванов Иван',
            partner_name: 'Школа Б',
            phone: '',
            parent_full_name: '',
            parent_phone: '',
            last_seen_label: '-',
            team_title: ''
        });
    }
    if (fetchMode === 'ok2') {
        return jsonRes(true, {
            id: 10,
            full_name: 'Петров Пётр',
            partner_name: '',
            phone: '',
            parent_full_name: '',
            parent_phone: '',
            last_seen_label: '-',
            team_title: ''
        });
    }
    if (fetchMode === 'mixed') {
        return jsonRes(false, {
            message: 'This action is unauthorized.',
            errors: { user: ['Нет доступа к карточке этого пользователя.'] }
        });
    }
    return jsonRes(false, {
        message: 'Нет доступа к карточке этого пользователя.',
        errors: { user: ['Нет доступа к карточке этого пользователя.'] }
    });
};

function headers(json) {
    const h = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf
    };
    if (json) h['Content-Type'] = 'application/json';
    return h;
}
function peerCardModal() {
    return { show() { modalShows += 1; } };
}
function showModalQueued(id) {
    if (id === 'peerCardModal') queuedShows += 1;
}
function openGroupCard() {
    groupShows += 1;
}
function wait() {
    return new Promise(function (resolve) { setImmediate(resolve); });
}

eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'fieldError'));
eval(extractFn(chatJs, 'dashText'));
eval(extractFn(chatJs, 'telHref'));
eval(extractFn(chatJs, 'phoneHtml'));
eval(extractFn(chatJs, 'showPeerCardError'));
eval(extractFn(chatJs, 'renderPeerCard'));
eval(extractFn(chatJs, 'openPeerCard'));
eval(extractFn(chatJs, 'headerPeerActivate'));
eval(extractFn(chatJs, 'showAccountCardError'));
eval(extractFn(chatJs, 'loadAccountCard'));

const onGroupClick = eval('(' + extractListenerByVar(chatJs, 'groupMembersBody', 'click') + ')');
const onPeerHitClick = eval('(' + extractListenerByVar(chatJs, 'peerHit', 'click') + ')');
const onMessagesClick = eval('(' + extractListenerByVar(chatJs, "document.getElementById('messagesBox')", 'click') + ')');

(async function main() {
    fetchMode = 'forbidden';
    fetchLog = [];
    modalShows = 0;
    currentPeerId = null;
    currentIsGroup = false;
    headerPeerActivate();
    await wait();
    const idle = { fetch: fetchLog.length, modal: modalShows, error: els.peerCardError.textContent };

    fetchLog = [];
    modalShows = 0;
    queuedShows = 0;
    els.peerCardError.textContent = '';
    els.peerCardBody.innerHTML = '';
    currentPeerId = 42;
    currentIsGroup = false;
    onPeerHitClick();
    await wait();
    await wait();
    const header_forbidden = {
        fetch: fetchLog.length,
        modal: modalShows,
        queued: queuedShows,
        error: els.peerCardError.textContent,
        body: els.peerCardBody.innerHTML,
        url: (fetchLog[0] && fetchLog[0].url) || '',
        accept: (fetchLog[0] && fetchLog[0].accept) || '',
        xhr: (fetchLog[0] && fetchLog[0].xhr) || ''
    };

    fetchLog = [];
    modalShows = 0;
    groupShows = 0;
    els.peerCardError.textContent = 'leftover';
    currentIsGroup = true;
    currentThreadId = 88;
    currentPeerId = null;
    headerPeerActivate();
    await wait();
    const group_header = {
        user_fetch: fetchLog.length,
        peer_modal: modalShows,
        group_modal: groupShows,
        error: els.peerCardError.textContent === 'leftover' ? '' : els.peerCardError.textContent
    };

    fetchMode = 'forbidden';
    fetchLog = [];
    modalShows = 0;
    queuedShows = 0;
    els.peerCardError.textContent = '';
    const memberRow = makeEl('tr');
    memberRow.setAttribute('data-id', '77');
    onGroupClick({ target: memberRow, preventDefault() {}, stopPropagation() {} });
    await wait();
    await wait();
    const group_row = {
        fetch: fetchLog.length,
        queued: queuedShows,
        direct_modal: modalShows,
        error: els.peerCardError.textContent,
        url: (fetchLog[0] && fetchLog[0].url) || ''
    };

    fetchLog = [];
    modalShows = 0;
    queuedShows = 0;
    els.peerCardError.textContent = '';
    const avatarBtn = makeEl('button');
    avatarBtn.className = 'msg-avatar-btn';
    avatarBtn.setAttribute('data-user-id', '88');
    onMessagesClick({ target: avatarBtn, preventDefault() {} });
    await wait();
    await wait();
    const avatar = {
        fetch: fetchLog.length,
        queued: queuedShows,
        direct_modal: modalShows,
        error: els.peerCardError.textContent,
        url: (fetchLog[0] && fetchLog[0].url) || ''
    };

    fetchMode = 'mixed';
    fetchLog = [];
    els.peerCardError.textContent = '';
    currentPeerId = 42;
    currentIsGroup = false;
    openPeerCard();
    await wait();
    await wait();
    const mixed_payload = { error: els.peerCardError.textContent };

    fetchMode = 'ok';
    fetchLog = [];
    els.peerCardError.textContent = 'This action is unauthorized.';
    openPeerCard(9);
    await wait();
    await wait();
    const success = { error: els.peerCardError.textContent, body: els.peerCardBody.innerHTML };

    fetchMode = 'forbidden';
    els.peerCardError.textContent = '';
    openPeerCard(9);
    await wait();
    await wait();
    const afterForbidden = els.peerCardError.textContent;
    fetchMode = 'ok2';
    const duringStart = (function () {
        openPeerCard(10);
        return els.peerCardError.textContent;
    }());
    await wait();
    await wait();
    const reopen = {
        after_forbidden: afterForbidden,
        during_reload: duringStart,
        after_success: els.peerCardError.textContent,
        body: els.peerCardBody.innerHTML
    };

    fetchMode = 'network';
    els.peerCardError.textContent = '';
    openPeerCard(11);
    await wait();
    await wait();
    const network = { error: els.peerCardError.textContent };

    process.stdout.write(JSON.stringify({
        idle: idle,
        header_forbidden: header_forbidden,
        group_header: group_header,
        group_row: group_row,
        avatar: avatar,
        mixed_payload: mixed_payload,
        success: success,
        reopen: reopen,
        network: network
    }));
})().catch(function (err) {
    console.error(err && err.stack ? err.stack : err);
    process.exit(1);
});
JS;

        $path = sys_get_temp_dir().'/chat-peer-card-ux-'.uniqid('', true).'.cjs';
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
