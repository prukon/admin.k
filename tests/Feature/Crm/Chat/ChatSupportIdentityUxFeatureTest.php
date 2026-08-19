<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\Team;
use App\Services\Chat\ChatSupportIdentity;
use App\Services\TeamUserSyncService;

/**
 * UX «Служба поддержки»: колонка роли и ФИО в пикерах/списке/карточке
 * берутся из живого JSON, не из role_name и не из учётки.
 *
 * Серверный 200 недостаточен: JS `role_label || role_name` без role_label
 * показал бы «superadmin» / «Суперадмин». Три пикера (контакты, создать группу,
 * добавить участников) — отдельные render*, регрессия часто в дубликате.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatSupportIdentityUxFeatureTest extends ChatTestCase
{
    use InteractsWithChatSupportIdentity;

    public function test_chat_page_first_paint_does_not_dump_superadmin_fio_into_html(): void
    {
        $canonical = $this->makeSupport('UxHtmlКанон_', 'Секрет');
        $extra = $this->makeSupport('UxHtmlЛишний_', 'ТожеСекрет');

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="contactsList"', $html);
        $this->assertStringContainsString('id="openContactsBtn"', $html);
        $this->assertStringContainsString('id="openCreateGroupBtn"', $html);
        $this->assertStringContainsString('id="openCreateGroupMobileBtn"', $html);
        $this->assertStringContainsString('id="addGroupMembersList"', $html);
        $this->assertStringNotContainsString($canonical->lastname, $html);
        $this->assertStringNotContainsString($extra->lastname, $html);
        $this->assertStringNotContainsString($canonical->email, $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="contactsList"[^>]*>\s*<li[^>]*contact-name/',
            $html,
            'Список контактов при первом открытии страницы должен быть пустым — ФИО подтянет JS'
        );
    }

    public function test_contacts_and_member_pickers_show_support_alias_not_real_fio_or_role_name(): void
    {
        $ui = $this->simulateSupportUi();

        foreach (['contacts', 'create_group', 'add_members'] as $path) {
            $html = (string) $ui[$path]['html'];
            $this->assertStringContainsString(
                ChatSupportIdentity::DISPLAY_NAME,
                $html,
                $path.': должна быть подпись «Служба поддержки»'
            );
            $this->assertStringContainsString(
                'data-id='.(string) $ui['canonical_id'],
                $html,
                $path.': клик должен идти на канонический id'
            );
            $this->assertStringNotContainsString(
                'data-id='.(string) $ui['extra_id'],
                $html,
                $path.': лишний superadmin не должен рисоваться второй строкой'
            );
            $this->assertStringNotContainsString((string) $ui['canonical_lastname'], $html);
            $this->assertStringNotContainsString((string) $ui['canonical_email'], $html);
            $this->assertStringNotContainsString('superadmin', $html);
            $this->assertStringNotContainsString('Суперадмин', $html);
            $this->assertStringContainsString((string) $ui['peer_name'], $html);
        }

        $this->assertSame(
            1,
            substr_count((string) $ui['contacts']['html'], 'data-id='.(string) $ui['canonical_id'])
        );
    }

    public function test_desktop_mobile_create_group_and_add_members_all_load_support_on_default_filter(): void
    {
        $ui = $this->simulateSupportUi();

        foreach (['desktop_open', 'mobile_tab', 'create_group_step', 'add_members_open'] as $trigger) {
            $url = urldecode((string) $ui[$trigger]['url']);
            $this->assertStringContainsString('/chat/api/users', $url, $trigger);
            $this->assertStringNotContainsString('team_id=', $url, $trigger.': дефолт «Все группы» не шлёт team_id');
            $this->assertStringContainsString(
                ChatSupportIdentity::DISPLAY_NAME,
                (string) $ui[$trigger]['html'],
                $trigger.': после загрузки должна быть служба поддержки'
            );
            $this->assertStringNotContainsString((string) $ui['canonical_lastname'], (string) $ui[$trigger]['html']);
        }
    }

    public function test_specific_team_filter_does_not_force_support_into_the_list(): void
    {
        $ui = $this->simulateSupportUi();

        $url = urldecode((string) $ui['team_filter']['url']);
        $this->assertStringContainsString('team_id=', $url);
        $this->assertStringNotContainsString(
            ChatSupportIdentity::DISPLAY_NAME,
            (string) $ui['team_filter']['html'],
            'В конкретной учебной группе поддержку не навязываем'
        );
        $this->assertStringContainsString((string) $ui['kid_name'], (string) $ui['team_filter']['html']);
    }

    public function test_contact_click_starts_dialog_with_canonical_id_not_extra(): void
    {
        $ui = $this->simulateSupportUi();

        $this->assertSame((int) $ui['canonical_id'], (int) $ui['click']['start_id']);
        $this->assertNotSame((int) $ui['extra_id'], (int) $ui['click']['start_id']);
    }

    public function test_thread_list_and_peer_card_use_alias_and_hide_phone(): void
    {
        $ui = $this->simulateSupportUi();

        $this->assertStringContainsString(ChatSupportIdentity::DISPLAY_NAME, (string) $ui['threads']['html']);
        $this->assertStringNotContainsString((string) $ui['canonical_lastname'], (string) $ui['threads']['html']);
        $this->assertStringNotContainsString('superadmin', (string) $ui['threads']['html']);

        $card = (string) $ui['card']['html'];
        $this->assertStringContainsString(ChatSupportIdentity::DISPLAY_NAME, $card);
        $this->assertStringNotContainsString((string) $ui['canonical_lastname'], $card);
        $this->assertStringNotContainsString('+79001112233', $card);
        $this->assertStringContainsString('peer-card-name', $card);
        $this->assertStringContainsString('>-<', $card);

        $members = (string) $ui['members']['html'];
        $this->assertStringContainsString(ChatSupportIdentity::DISPLAY_NAME, $members);
        $this->assertStringContainsString('group-member-role', $members);
        $this->assertStringNotContainsString((string) $ui['canonical_lastname'], $members);
        $this->assertStringNotContainsString('Суперадмин', $members);
        $this->assertStringNotContainsString('data-id="'.(string) $ui['extra_id'].'"', $members);
    }

    public function test_js_falls_back_to_role_name_so_api_must_send_role_label(): void
    {
        $ui = $this->simulateSupportUi();

        $this->assertStringContainsString('superadmin', (string) $ui['fallback']['html']);
        $this->assertStringNotContainsString(
            'superadmin',
            (string) $ui['contacts']['html'],
            'Живой JSON с role_label не должен показывать role_name в колонке роли'
        );
    }

    public function test_rebuilding_contacts_does_not_keep_previous_real_fio(): void
    {
        $ui = $this->simulateSupportUi();

        $this->assertStringContainsString((string) $ui['canonical_lastname'], (string) $ui['stale']['before']);
        $this->assertStringNotContainsString((string) $ui['canonical_lastname'], (string) $ui['stale']['after']);
        $this->assertStringContainsString(ChatSupportIdentity::DISPLAY_NAME, (string) $ui['stale']['after']);
    }

    public function test_xss_in_support_name_is_escaped_in_every_picker(): void
    {
        $ui = $this->simulateSupportUi();

        $this->assertStringContainsString('&lt;img&gt;', (string) $ui['xss']['contacts']);
        $this->assertStringNotContainsString('<img>', (string) $ui['xss']['contacts']);
        $this->assertStringContainsString('&lt;img&gt;', (string) $ui['xss']['create_group']);
        $this->assertStringContainsString('&lt;img&gt;', (string) $ui['xss']['add_members']);
        $this->assertStringContainsString('&lt;img&gt;', (string) $ui['xss']['card']);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateSupportUi(): array
    {
        $canonical = $this->makeSupport('UxСаКанон_', 'СекретИмя');
        $extra = $this->makeSupport('UxСаЛишний_', 'ЛишнийИмя');
        $peer = $this->makePeer('UxСаКлиент_', [
            'lastname' => 'КлиентовUxСа',
            'name' => 'Иван',
        ]);
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $kid = $this->makePeer('UxСаВГруппе_', [
            'lastname' => 'ГрупповUxСа',
            'name' => 'Пётр',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($kid, [(int) $team->id]);

        $this->grantPermission($this->user, 'messages.view');

        $contacts = $this->getJson(route('chat.api.users'))->assertOk()->json();
        $teamContacts = $this->getJson(route('chat.api.users', ['team_id' => $team->id]))->assertOk()->json();

        $this->postJson(route('chat.api.threads.store'), ['user_id' => $canonical->id], $this->chatAjaxHeaders())
            ->assertCreated();
        $threads = $this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads');
        $card = $this->getJson(route('chat.api.users.show', $canonical))->assertOk()->json();

        $admin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'АдминовUxСа',
            'name' => 'Андрей',
        ]);
        $this->grantPermission($admin, 'messages.view');
        $group = $this->createGroupThreadForUsers(
            [$admin->id, $canonical->id, $extra->id, $peer->id],
            'UxСаСостав'
        );
        $this->actingInPartner($admin);
        $membersPayload = $this->getJson(route('chat.api.threads.participants.index', $group))->assertOk()->json();
        $this->actingInPartner($this->user);

        $payload = [
            'contacts' => $contacts,
            'team_contacts' => $teamContacts,
            'threads' => $threads,
            'card' => $card,
            'members' => $membersPayload['members'] ?? [],
            'canonical_id' => (int) $canonical->id,
            'extra_id' => (int) $extra->id,
            'canonical_lastname' => (string) $canonical->lastname,
            'canonical_email' => (string) $canonical->email,
            'peer_name' => (string) $peer->full_name,
            'kid_name' => (string) $kid->full_name,
            'team_id' => (int) $team->id,
            'me' => (int) $admin->id,
        ];

        $chatJs = resource_path('js/chat.js');
        $this->assertFileExists($chatJs);
        $payloadPath = tempnam(sys_get_temp_dir(), 'chat_support_ux_json_');
        $this->assertNotFalse($payloadPath);
        file_put_contents($payloadPath, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');
const data = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));

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
        focus() {},
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
        addEventListener(type, fn) {
            this.listeners[type] = this.listeners[type] || [];
            this.listeners[type].push(fn);
        },
        appendChild(child) {
            this.children.push(child);
            return child;
        },
        querySelector(sel) {
            const m = String(sel || '').match(/data-id="([^"]+)"/);
            if (m) {
                return this.children.find(function (c) {
                    return c.getAttribute('data-id') === m[1];
                }) || null;
            }
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
            },
            toggle(c, on) {
                if (on === false) {
                    this.remove(c);
                } else if (on === true) {
                    this.add(c);
                } else if (this.contains(c)) {
                    this.remove(c);
                } else {
                    this.add(c);
                }
            }
        }
    };
    return el;
}

function collected(el) {
    return el.children.map(function (c) {
        return 'data-id=' + (c.getAttribute('data-id') || '') + '\n' + c.innerHTML;
    }).join('\n') + '\n' + el.innerHTML;
}

global.CSS = { escape(s) { return String(s); } };
const svgTick = '<svg></svg>';
let me = Number(data.me || 0);
let currentThreadId = 9;
let groupMembersCanManage = true;
let addGroupMembersSelected = {};
let addGroupMembersBusy = false;
let groupSelectedIds = {};
let groupWizardTitle = '';
let createGroupBusy = false;
let startDialogId = null;
function startDialog(id) { startDialogId = Number(id); }

const els = {
    contactsList: makeEl('ul'),
    contactsSearch: makeEl('input'),
    contactsTeamFilter: makeEl('select'),
    contactsError: makeEl('div'),
    contactsTeamError: makeEl('div'),
    openContactsBtn: makeEl('button'),
    createGroupMembersList: makeEl('ul'),
    createGroupMembersSearch: makeEl('input'),
    createGroupMembersTeamFilter: makeEl('select'),
    createGroupTitle: makeEl('input'),
    createGroupTitleError: makeEl('div'),
    createGroupMembersError: makeEl('div'),
    createGroupMembersTeamError: makeEl('div'),
    createGroupMembersSearchError: makeEl('div'),
    addGroupMembersList: makeEl('ul'),
    addGroupMembersSearch: makeEl('input'),
    addGroupMembersTeamFilter: makeEl('select'),
    addGroupMembersError: makeEl('div'),
    addGroupMembersTeamError: makeEl('div'),
    addGroupMembersSearchError: makeEl('div'),
    groupMembersBody: makeEl('tbody'),
    threads: makeEl('div'),
    peerCardBody: makeEl('div')
};
els.contactsSearch.value = '';
els.contactsTeamFilter.value = '';
els.createGroupTitle.value = 'Сборная';
const root = makeEl('div');

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector() { return null; },
    querySelectorAll() { return []; }
};

const urls = { users: '/chat/api/users' };
function headers() { return { Accept: 'application/json' }; }
function fieldError() { return ''; }
function showContactsError(t) { els.contactsError.textContent = t || ''; }
function showContactsTeamError(t) { els.contactsTeamError.textContent = t || ''; }
function showCreateGroupTitleError(t) { els.createGroupTitleError.textContent = t || ''; }
function showCreateGroupMembersError(t) { els.createGroupMembersError.textContent = t || ''; }
function showCreateGroupMembersTeamError(t) { els.createGroupMembersTeamError.textContent = t || ''; }
function showCreateGroupMembersSearchError(t) { els.createGroupMembersSearchError.textContent = t || ''; }
function showAddGroupMembersError(t) { els.addGroupMembersError.textContent = t || ''; }
function showAddGroupMembersTeamError(t) { els.addGroupMembersTeamError.textContent = t || ''; }
function showAddGroupMembersSearchError(t) { els.addGroupMembersSearchError.textContent = t || ''; }
function contactsModal() { return { show() {}, hide() {} }; }
function createGroupNameModal() { return { show() {}, hide() {} }; }
function createGroupMembersModal() { return { show() {}, hide() {} }; }
function addGroupMembersModal() { return { show() {}, hide() {} }; }
function placeContactsMount() {}
function setMobileTabButtons() {}
function loadAccountCard() {}
function ticksHtml() { return ''; }
function normalizeDraft(v) { return String(v || '').trim(); }
function fmtTime() { return ''; }

let pending = [];
let fetchUrls = [];
let nextPayload = { ok: true, body: [] };
global.fetch = function (url) {
    fetchUrls.push(String(url));
    const p = Promise.resolve({
        ok: !!nextPayload.ok,
        json() { return Promise.resolve(nextPayload.body); }
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

eval(extractFn(chatJs, 'escapeHtml'));
eval(extractFn(chatJs, 'dashText'));
eval(extractFn(chatJs, 'telHref'));
eval(extractFn(chatJs, 'phoneHtml'));
eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'contactsTeamValue'));
eval(extractFn(chatJs, 'createGroupMembersTeamValue'));
eval(extractFn(chatJs, 'addGroupMembersTeamValue'));
eval(extractFn(chatJs, 'renderContacts'));
eval(extractFn(chatJs, 'loadContacts'));
eval(extractFn(chatJs, 'renderGroupMembers'));
eval(extractFn(chatJs, 'loadGroupMembers'));
eval(extractFn(chatJs, 'renderAddGroupMembers'));
eval(extractFn(chatJs, 'loadAddGroupMembers'));
eval(extractFn(chatJs, 'resetAddGroupMembers'));
eval(extractFn(chatJs, 'openAddGroupMembers'));
eval(extractFn(chatJs, 'resetCreateGroupWizard'));
eval(extractFn(chatJs, 'proceedCreateGroupToMembers'));
eval(extractFn(chatJs, 'setMobileTab'));
eval(extractFn(chatJs, 'renderThreads'));
eval(extractFn(chatJs, 'renderPeerCard'));
eval(extractFn(chatJs, 'appendGroupMembers'));
const openContactsClick = eval('(' + extractListener(chatJs, 'openContactsBtn', 'click') + ')');

(async function main() {
    nextPayload = { ok: true, body: data.contacts };
    els.contactsList.innerHTML = '';
    renderContacts(data.contacts);
    const contactsHtml = collected(els.contactsList);
    const firstLi = els.contactsList.children[0];
    startDialogId = null;
    if (firstLi && firstLi._contactRow && firstLi._contactRow.listeners.click) {
        firstLi._contactRow.listeners.click[0]();
    }

    els.createGroupMembersList.innerHTML = '';
    renderGroupMembers(data.contacts);
    const createGroupHtml = collected(els.createGroupMembersList);

    els.addGroupMembersList.innerHTML = '';
    renderAddGroupMembers(data.contacts);
    const addMembersHtml = collected(els.addGroupMembersList);

    els.threads.innerHTML = '';
    renderThreads(data.threads || []);
    const threadsHtml = collected(els.threads);

    renderPeerCard(data.card || {});
    const cardHtml = els.peerCardBody.innerHTML;

    els.groupMembersBody.innerHTML = '';
    els.groupMembersBody.children = [];
    appendGroupMembers(data.members || [], true);
    const membersHtml = collected(els.groupMembersBody);

    els.contactsList.innerHTML = '';
    renderContacts([{
        id: 1,
        name: data.canonical_lastname,
        avatar: '/img/a.png',
        role_label: 'Суперадмин',
        role_name: 'superadmin'
    }]);
    const staleBefore = collected(els.contactsList);
    renderContacts(data.contacts);
    const staleAfter = collected(els.contactsList);

    els.contactsList.innerHTML = '';
    renderContacts([{
        id: data.canonical_id,
        name: 'Служба поддержки',
        avatar: '/img/a.png',
        role_name: 'superadmin'
    }]);
    const fallbackHtml = collected(els.contactsList);

    const xssUser = {
        id: data.canonical_id,
        name: '<img>',
        full_name: '<img>',
        avatar: '/img/a.png',
        role_label: 'Служба поддержки'
    };
    els.contactsList.innerHTML = '';
    renderContacts([xssUser]);
    const xssContacts = collected(els.contactsList);
    els.createGroupMembersList.innerHTML = '';
    renderGroupMembers([xssUser]);
    const xssCreate = collected(els.createGroupMembersList);
    els.addGroupMembersList.innerHTML = '';
    renderAddGroupMembers([xssUser]);
    const xssAdd = collected(els.addGroupMembersList);
    renderPeerCard(xssUser);
    const xssCard = els.peerCardBody.innerHTML;

    nextPayload = { ok: true, body: data.contacts };
    els.contactsTeamFilter.value = String(data.team_id);
    els.contactsSearch.value = 'старый';
    fetchUrls = [];
    openContactsClick.call(els.openContactsBtn);
    await flushFetch();
    const desktopOpen = { url: fetchUrls[0] || '', html: collected(els.contactsList) };

    els.contactsSearch.value = '';
    els.contactsTeamFilter.value = '';
    fetchUrls = [];
    setMobileTab('contacts');
    await flushFetch();
    const mobileTab = { url: fetchUrls[0] || '', html: collected(els.contactsList) };

    els.createGroupMembersTeamFilter.value = String(data.team_id);
    els.createGroupTitle.value = 'Сборная';
    fetchUrls = [];
    resetCreateGroupWizard();
    els.createGroupTitle.value = 'Сборная';
    proceedCreateGroupToMembers();
    await flushFetch();
    const createGroupStep = { url: fetchUrls[0] || '', html: collected(els.createGroupMembersList) };

    els.addGroupMembersTeamFilter.value = String(data.team_id);
    fetchUrls = [];
    openAddGroupMembers();
    await flushFetch();
    const addMembersOpen = { url: fetchUrls[0] || '', html: collected(els.addGroupMembersList) };

    nextPayload = { ok: true, body: data.team_contacts };
    els.contactsTeamFilter.value = String(data.team_id);
    fetchUrls = [];
    loadContacts('');
    await flushFetch();
    const teamFilter = { url: fetchUrls[0] || '', html: collected(els.contactsList) };

    console.log(JSON.stringify({
        canonical_id: data.canonical_id,
        extra_id: data.extra_id,
        canonical_lastname: data.canonical_lastname,
        canonical_email: data.canonical_email,
        peer_name: data.peer_name,
        kid_name: data.kid_name,
        contacts: { html: contactsHtml },
        create_group: { html: createGroupHtml },
        add_members: { html: addMembersHtml },
        threads: { html: threadsHtml },
        card: { html: cardHtml },
        members: { html: membersHtml },
        click: { start_id: startDialogId },
        fallback: { html: fallbackHtml },
        stale: { before: staleBefore, after: staleAfter },
        xss: { contacts: xssContacts, create_group: xssCreate, add_members: xssAdd, card: xssCard },
        desktop_open: desktopOpen,
        mobile_tab: mobileTab,
        create_group_step: createGroupStep,
        add_members_open: addMembersOpen,
        team_filter: teamFilter
    }));
})().catch(function (e) {
    console.error(e && e.stack ? e.stack : e);
    process.exit(1);
});
JS;

        $path = sys_get_temp_dir().'/chat-support-ux-'.uniqid('', true).'.cjs';
        file_put_contents($path, $script);

        try {
            $output = [];
            $exitCode = 0;
            exec(
                'node '.escapeshellarg($path).' '.escapeshellarg($chatJs).' '.escapeshellarg($payloadPath).' 2>&1',
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
            @unlink($payloadPath);
        }
    }
}
