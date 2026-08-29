<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX визарда «Создать группу»: пустое имя не открывает второй шаг,
 * клик выбирает участника (бордер/галочка), меньше двух — ошибка под списком,
 * «Отмена» закрывает весь визард, поиск и фильтр по группе как в контактах.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatGroupThreadUxFeatureTest extends ChatTestCase
{
    public function test_empty_title_stays_on_name_step_and_shows_error_under_field(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame(0, (int) $ui['empty_title']['members_show']);
        $this->assertSame(0, (int) $ui['empty_title']['fetch_count']);
        $this->assertSame(0, (int) $ui['empty_title']['proceeded']);
        $this->assertSame('Введите название группы.', $ui['empty_title']['title_error']);
    }

    public function test_valid_title_opens_members_modal_and_loads_contacts(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame(1, (int) $ui['to_members']['name_hide']);
        $this->assertSame(1, (int) $ui['to_members']['members_show']);
        $this->assertStringContainsString('/chat/api/users', (string) $ui['to_members']['url']);
        $this->assertStringNotContainsString('team_id=', (string) $ui['to_members']['url']);
        $this->assertStringContainsString('group-member-row', (string) $ui['to_members']['list_html']);
        $this->assertStringContainsString('group-pick-check', (string) $ui['to_members']['list_html']);
        $this->assertStringNotContainsString('contact-online-dot', (string) $ui['to_members']['list_html']);
    }

    public function test_click_toggles_selected_class_and_submit_requires_two(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame(1, (int) $ui['one_selected']['selected_count']);
        $this->assertStringContainsString('is-selected', (string) $ui['one_selected']['list_html']);
        $this->assertSame('Выберите минимум двух участников.', $ui['one_selected']['members_error']);
        $this->assertSame(0, (int) $ui['one_selected']['post_count']);

        $this->assertSame(2, (int) $ui['two_selected']['selected_count']);
        $this->assertSame(1, (int) $ui['two_selected']['post_count']);
        $this->assertStringContainsString('"title":"Сборная"', (string) $ui['two_selected']['post_body']);
        $this->assertStringContainsString('11', (string) $ui['two_selected']['post_body']);
        $this->assertStringContainsString('12', (string) $ui['two_selected']['post_body']);
        $this->assertSame(77, (int) $ui['two_selected']['opened_thread']);
        $this->assertSame(1, (int) $ui['two_selected']['members_hide']);
    }

    public function test_cancel_on_members_closes_whole_wizard(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame(1, (int) $ui['cancel']['name_hide']);
        $this->assertSame(1, (int) $ui['cancel']['members_hide']);
        $this->assertSame('', $ui['cancel']['title_value']);
        $this->assertSame(0, (int) $ui['cancel']['selected_count']);
        $this->assertSame(0, (int) $ui['cancel']['post_count']);
    }

    public function test_members_search_and_team_filter_reuse_contacts_api(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $changeUrl = urldecode((string) $ui['filter']['url']);
        $this->assertStringContainsString('team_id=15', $changeUrl);
        $this->assertStringContainsString('q=Иванов', $changeUrl);

        $this->assertSame(
            [],
            $ui['search']['urls_before_flush'],
            'Пока не прошли 250 мс — запроса поиска ещё нет'
        );
        $searchUrl = urldecode((string) $ui['search']['url']);
        $this->assertStringContainsString('team_id=15', $searchUrl);
        $this->assertStringContainsString('q=Сидоров', $searchUrl);
    }

    public function test_team_validation_error_is_shown_under_group_select(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame('Выберите группу из списка.', $ui['team_error']['team_error']);
        $this->assertSame('', $ui['team_error']['search_error']);
        $this->assertStringContainsString('Ничего не найдено', (string) $ui['team_error']['list_html']);
    }

    public function test_desktop_and_mobile_buttons_open_name_modal(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame(1, (int) $ui['desktop_open']['name_show']);
        $this->assertSame(1, (int) $ui['mobile_open']['name_show']);
        $this->assertSame('', $ui['desktop_open']['title_value']);
    }

    public function test_reopening_wizard_resets_title_filters_and_selection(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame(1, (int) $ui['reopen']['name_show']);
        $this->assertSame(1, (int) $ui['reopen']['members_hide']);
        $this->assertSame('', $ui['reopen']['title_value']);
        $this->assertSame('', $ui['reopen']['search_value']);
        $this->assertSame('', $ui['reopen']['team_value']);
        $this->assertSame('', $ui['reopen']['title_error']);
        $this->assertSame(0, (int) $ui['reopen']['selected_count']);
        $this->assertStringNotContainsString('team_id=', (string) $ui['reopen']['url']);
    }

    public function test_second_click_deselects_member(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame(0, (int) $ui['deselect']['selected_count']);
        $this->assertStringNotContainsString('is-selected', (string) $ui['deselect']['list_html']);
    }

    public function test_selection_survives_team_filter_rerender(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame(1, (int) $ui['persist_selection']['selected_count']);
        $this->assertStringContainsString('is-selected', (string) $ui['persist_selection']['list_html']);
        $this->assertStringContainsString('team_id=15', urldecode((string) $ui['persist_selection']['url']));
    }

    public function test_search_validation_error_stays_under_search_not_under_group_select(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame('', $ui['search_error']['team_error']);
        $this->assertSame(
            'Строка поиска слишком длинная (максимум 120 символов).',
            $ui['search_error']['search_error']
        );
    }

    public function test_server_user_ids_error_is_shown_under_members_list(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame('Выберите минимум двух участников.', $ui['server_error']['members_error']);
        $this->assertSame(0, (int) $ui['server_error']['opened_thread']);
        $this->assertSame(0, (int) $ui['server_error']['members_hide']);
    }

    public function test_double_submit_does_not_send_second_request(): void
    {
        $ui = $this->simulateGroupWizardUi();

        $this->assertSame(1, (int) $ui['busy']['post_count']);
        $this->assertSame(1, (int) $ui['busy']['opened_thread']);
    }

    public function test_group_inbox_bump_does_not_drop_private_dialog_with_same_person(): void
    {
        $ui = $this->simulateGroupInboxBump();

        $this->assertContains(10, $ui['after_group']['ids']);
        $this->assertContains(77, $ui['after_group']['ids']);
        $this->assertSame(11, (int) $ui['after_group']['private_peer']);
        $this->assertTrue((bool) $ui['after_group']['group_is_group']);
        $this->assertTrue((bool) $ui['after_group']['group_peer_null']);

        $this->assertTrue((bool) $ui['after_group_with_peer']['has_10']);
        $this->assertTrue((bool) $ui['after_group_with_peer']['has_88']);
        $this->assertSame('Сборная2', $ui['after_group_with_peer']['title_88']);

        $this->assertFalse((bool) $ui['after_private']['has_10']);
        $this->assertFalse((bool) $ui['after_private']['has_20']);
        $this->assertTrue((bool) $ui['after_private']['has_21']);
        $this->assertTrue((bool) $ui['after_private']['has_group']);
    }

    public function test_thread_list_shows_group_name_not_dialog_fallback(): void
    {
        $ui = $this->simulateGroupListTitle();

        $this->assertStringContainsString('Сборная', $ui['named']['html']);
        $this->assertStringContainsString('chat-li-title', $ui['named']['html']);
        $this->assertStringNotContainsString('Диалог', $ui['named']['html']);

        $this->assertStringContainsString('>Группа</div>', $ui['empty']['html']);
        $this->assertStringNotContainsString('Диалог', $ui['empty']['html']);

        $this->assertStringContainsString('>Группа</div>', $ui['upsert_empty']['html']);
        $this->assertStringNotContainsString('Диалог', $ui['upsert_empty']['html']);
        $this->assertSame('Группа', $ui['upsert_empty']['cache_title']);
    }

    public function test_empty_new_group_stays_below_dialogs_with_messages(): void
    {
        $ui = $this->simulateGroupListTitle();

        $this->assertSame(
            [11, 10, 99],
            array_map('intval', $ui['sort_empty_group']['ids'] ?? []),
            'Непрочитанные сверху, затем по last_message_time; пустая новая группа — внизу'
        );
    }

    public function test_mixed_list_keeps_group_chat_name_and_private_peer_name(): void
    {
        $ui = $this->simulateGroupListTitle();

        $this->assertStringContainsString('Сборная', $ui['mixed']['html']);
        $this->assertStringContainsString('Иванов Иван', $ui['mixed']['html']);
        $this->assertStringNotContainsString('Диалог', $ui['mixed']['html']);
        $this->assertStringNotContainsString('>Группа</div>', $ui['mixed']['html']);
    }

    public function test_empty_private_title_falls_back_to_dialog_not_group(): void
    {
        $ui = $this->simulateGroupListTitle();

        $this->assertStringContainsString('>Диалог</div>', $ui['private_empty']['html']);
        $this->assertStringNotContainsString('>Группа</div>', $ui['private_empty']['html']);
    }

    public function test_opening_group_does_not_rename_it_to_a_person(): void
    {
        $ui = $this->simulateGroupListTitle();

        $this->assertStringContainsString('Сборная', $ui['after_open']['html']);
        $this->assertStringNotContainsString('Сидоров', $ui['after_open']['html']);
        $this->assertStringNotContainsString('Диалог', $ui['after_open']['html']);

        $this->assertSame('Сборная', $ui['header']['named']);
        $this->assertSame('Группа', $ui['header']['empty']);
        $this->assertSame('Диалог', $ui['header']['private_empty']);
        $this->assertSame('Иванов Иван', $ui['header']['private_named']);
        $this->assertNotSame('Группа', $ui['header']['private_empty']);
    }

    public function test_search_finds_group_by_chat_name_not_by_dialog_word(): void
    {
        $ui = $this->simulateGroupListTitle();

        $this->assertStringContainsString('Сборная', $ui['search']['html']);
        $this->assertStringNotContainsString('Иванов Иван', $ui['search']['html']);

        $this->assertStringContainsString('Диалогов нет', $ui['search_dialog_word']['html']);
        $this->assertStringNotContainsString('Сборная', $ui['search_dialog_word']['html']);
    }

    public function test_picking_contact_opens_private_dialog_not_group_with_same_person(): void
    {
        $ui = $this->simulateStartDialogPrefersPrivate();

        $this->assertSame(10, (int) $ui['with_private']['opened']);
        $this->assertSame(1, (int) $ui['with_private']['modal_hidden']);
        $this->assertSame(0, (int) $ui['with_private']['fetch_count']);
    }

    public function test_picking_contact_does_not_open_group_when_there_is_no_private_dialog(): void
    {
        $ui = $this->simulateStartDialogPrefersPrivate();

        $this->assertNull($ui['only_group']['opened']);
        $this->assertSame(0, (int) $ui['only_group']['modal_hidden']);
        $this->assertSame(1, (int) $ui['only_group']['fetch_count']);
    }

    /**
     * @return array<string, mixed>
     */
    private function simulateGroupWizardUi(): array
    {
        $chatJs = resource_path('js/chat.js');
        $this->assertFileExists($chatJs);

        $script = <<<'JS'
const fs = require('fs');
const chatJs = fs.readFileSync(process.argv[2], 'utf8');
global.window = global;
global.CSS = { escape: function (s) { return String(s).replace(/[^a-zA-Z0-9_\-]/g, '\\$&'); } };

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
        _innerHTML: '',
        textContent: '',
        value: '',
        children: [],
        parentElement: null,
        attrs: {},
        style: {},
        listeners: {},
        setAttribute(k, v) { this.attrs[k] = String(v); },
        getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; },
        addEventListener(type, fn) {
            this.listeners[type] = this.listeners[type] || [];
            this.listeners[type].push(fn);
        },
        appendChild(child) {
            child.parentElement = this;
            this.children.push(child);
            return child;
        },
        focus() {},
        classList: null,
        querySelector(sel) {
            const m = String(sel).match(/\[data-id="(\d+)"\]/);
            if (!m) return null;
            return this.children.find(function (c) { return c.getAttribute('data-id') === m[1]; }) || null;
        }
    };
    Object.defineProperty(el, 'innerHTML', {
        get() { return el._innerHTML; },
        set(v) {
            el._innerHTML = String(v);
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

const els = {
    createGroupTitle: makeEl('input'),
    createGroupTitleError: makeEl('div'),
    createGroupMembersError: makeEl('div'),
    createGroupMembersTeamError: makeEl('div'),
    createGroupMembersSearchError: makeEl('div'),
    createGroupMembersTeamFilter: makeEl('select'),
    createGroupMembersSearch: makeEl('input'),
    createGroupMembersList: makeEl('ul'),
    createGroupNameModal: makeEl('div'),
    createGroupMembersModal: makeEl('div'),
    createGroupNameForm: makeEl('form'),
    createGroupMembersForm: makeEl('form'),
    threadSearch: makeEl('input'),
    threads: makeEl('div')
};
els.createGroupTitle.value = '';
els.createGroupMembersSearch.value = '';
els.createGroupMembersTeamFilter.value = '';
els.threadSearch.value = '';

global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); },
    querySelector(sel) {
        if (String(sel).indexOf('#createGroupMembersList') === 0) {
            return els.createGroupMembersList.querySelector(sel.replace('#createGroupMembersList ', ''));
        }
        return null;
    },
    querySelectorAll() { return []; }
};

const svgTick = '<svg></svg>';
function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function fieldError(json, field) {
    if (json && json.errors && json.errors[field]) {
        const v = json.errors[field];
        return Array.isArray(v) ? String(v[0] || '') : String(v);
    }
    return json && json.message ? String(json.message) : '';
}
function headers() { return { Accept: 'application/json' }; }
function upsertThread() {}
function loadThreads() {}
function openThread(id) { openedThread = Number(id); }

const urls = { users: '/chat/api/users', storeGroup: '/chat/api/threads/groups' };
let groupWizardTitle = '';
let groupSelectedIds = {};
let groupMembersDebounce = null;
let createGroupBusy = false;
let nameShow = 0;
let nameHide = 0;
let membersShow = 0;
let membersHide = 0;
let openedThread = 0;
let fetchLog = [];
let postBodies = [];
let teamFail = false;
let searchFail = false;
let postFail = false;
let postHang = false;
let hangResolve = null;

function createGroupNameModal() {
    return { show: function () { nameShow += 1; }, hide: function () { nameHide += 1; } };
}
function createGroupMembersModal() {
    return { show: function () { membersShow += 1; }, hide: function () { membersHide += 1; } };
}

eval(extractFn(chatJs, 'showCreateGroupTitleError'));
eval(extractFn(chatJs, 'showCreateGroupMembersError'));
eval(extractFn(chatJs, 'showCreateGroupMembersTeamError'));
eval(extractFn(chatJs, 'showCreateGroupMembersSearchError'));
eval(extractFn(chatJs, 'createGroupMembersTeamValue'));
eval(extractFn(chatJs, 'selectedGroupMemberIds'));
eval(extractFn(chatJs, 'resetCreateGroupWizard'));
eval(extractFn(chatJs, 'openCreateGroupWizard'));
eval(extractFn(chatJs, 'proceedCreateGroupToMembers'));
eval(extractFn(chatJs, 'toggleGroupMember'));
eval(extractFn(chatJs, 'renderGroupMembers'));
eval(extractFn(chatJs, 'loadGroupMembers'));
eval(extractFn(chatJs, 'submitCreateGroup'));
eval(extractFn(chatJs, 'closeCreateGroupWizard'));

function jsonOk(data) {
    return Promise.resolve({
        ok: true,
        json: function () { return Promise.resolve(data); }
    });
}

global.fetch = function (url, opts) {
    fetchLog.push(String(url));
    const method = opts && opts.method ? String(opts.method) : 'GET';
    if (method === 'POST') {
        postBodies.push(String(opts && opts.body || ''));
        if (postHang) {
            return new Promise(function (resolve) {
                hangResolve = function () {
                    resolve(jsonOk({
                        ok: true,
                        created: true,
                        thread_id: 1,
                        thread: { id: 1, title: 'Сборная', is_group: true, peer_id: null, avatar: '/img/default-avatar.png' }
                    }));
                };
            });
        }
        if (postFail) {
            return Promise.resolve({
                ok: false,
                json: function () {
                    return Promise.resolve({ errors: { user_ids: ['Выберите минимум двух участников.'] } });
                }
            });
        }
        return jsonOk({
            ok: true,
            created: true,
            thread_id: 77,
            thread: { id: 77, title: 'Сборная', is_group: true, peer_id: null, avatar: '/img/default-avatar.png' }
        });
    }
    if (teamFail) {
        return Promise.resolve({
            ok: false,
            json: function () {
                return Promise.resolve({ errors: { team_id: ['Выберите группу из списка.'] } });
            }
        });
    }
    if (searchFail) {
        return Promise.resolve({
            ok: false,
            json: function () {
                return Promise.resolve({ errors: { q: ['Строка поиска слишком длинная (максимум 120 символов).'] } });
            }
        });
    }
    return jsonOk([
        { id: 11, name: 'Альфа', avatar: '/img/default-avatar.png', role_label: 'Клиент', parent_full_name: '', team_title: 'Штурм' },
        { id: 12, name: 'Бета', avatar: '/img/default-avatar.png', role_label: 'Клиент', parent_full_name: '', team_title: '' }
    ]);
};

function wait() {
    return new Promise(function (resolve) { setImmediate(resolve); });
}

(async function () {
    fetchLog = [];
    nameShow = 0;
    membersShow = 0;
    openCreateGroupWizard();
    els.createGroupTitle.value = '   ';
    const emptyOk = proceedCreateGroupToMembers();
    const empty_title = {
        members_show: membersShow,
        fetch_count: fetchLog.length,
        title_error: els.createGroupTitleError.textContent,
        proceeded: emptyOk ? 1 : 0
    };

    fetchLog = [];
    nameHide = 0;
    membersShow = 0;
    els.createGroupTitle.value = 'Сборная';
    proceedCreateGroupToMembers();
    await wait();
    const to_members = {
        name_hide: nameHide,
        members_show: membersShow,
        url: fetchLog[0] || '',
        list_html: els.createGroupMembersList.children.map(function (c) {
            return c.className + ' ' + c.innerHTML;
        }).join('')
    };

    postBodies = [];
    openedThread = 0;
    membersHide = 0;
    els.createGroupMembersError.textContent = '';
    toggleGroupMember(11);
    submitCreateGroup();
    await wait();
    const one_selected = {
        selected_count: selectedGroupMemberIds().length,
        list_html: els.createGroupMembersList.children.map(function (c) { return c.className; }).join(' '),
        members_error: els.createGroupMembersError.textContent,
        post_count: postBodies.length
    };

    toggleGroupMember(11);
    const deselect = {
        selected_count: selectedGroupMemberIds().length,
        list_html: els.createGroupMembersList.children.map(function (c) { return c.className; }).join(' ')
    };
    toggleGroupMember(11);

    toggleGroupMember(12);
    submitCreateGroup();
    await wait();
    const two_selected = {
        selected_count: 2,
        post_count: postBodies.length,
        post_body: postBodies[0] || '',
        opened_thread: openedThread,
        members_hide: membersHide
    };

    nameHide = 0;
    membersHide = 0;
    els.createGroupTitle.value = 'Сборная';
    groupWizardTitle = 'Сборная';
    groupSelectedIds = { 11: true };
    closeCreateGroupWizard();
    const cancel = {
        name_hide: nameHide,
        members_hide: membersHide,
        title_value: els.createGroupTitle.value,
        selected_count: selectedGroupMemberIds().length,
        post_count: 0
    };

    resetCreateGroupWizard();
    groupWizardTitle = 'Сборная';
    els.createGroupMembersSearch.value = 'Иванов';
    els.createGroupMembersTeamFilter.value = '15';
    fetchLog = [];
    loadGroupMembers(els.createGroupMembersSearch.value);
    await wait();
    const filter = { url: fetchLog[fetchLog.length - 1] || '' };

    fetchLog = [];
    const urls_before_flush = fetchLog.slice();
    els.createGroupMembersSearch.value = 'Сидоров';
    clearTimeout(groupMembersDebounce);
    groupMembersDebounce = setTimeout(function () {
        loadGroupMembers(els.createGroupMembersSearch.value.trim());
    }, 250);
    const beforeFlush = fetchLog.slice();
    await new Promise(function (resolve) { setTimeout(resolve, 260); });
    const search = {
        urls_before_flush: beforeFlush,
        url: fetchLog[fetchLog.length - 1] || ''
    };

    teamFail = true;
    fetchLog = [];
    loadGroupMembers('');
    await wait();
    const team_error = {
        team_error: els.createGroupMembersTeamError.textContent,
        search_error: els.createGroupMembersSearchError.textContent,
        list_html: els.createGroupMembersList.innerHTML || 'Ничего не найдено'
    };
    teamFail = false;

    resetCreateGroupWizard();
    els.createGroupTitle.value = 'Сборная';
    proceedCreateGroupToMembers();
    await wait();
    toggleGroupMember(11);
    els.createGroupMembersTeamFilter.value = '15';
    fetchLog = [];
    loadGroupMembers('Иванов');
    await wait();
    const persist_selection = {
        selected_count: selectedGroupMemberIds().length,
        list_html: els.createGroupMembersList.children.map(function (c) { return c.className; }).join(' '),
        url: fetchLog[fetchLog.length - 1] || ''
    };

    searchFail = true;
    fetchLog = [];
    loadGroupMembers('xxx');
    await wait();
    const search_error = {
        team_error: els.createGroupMembersTeamError.textContent,
        search_error: els.createGroupMembersSearchError.textContent
    };
    searchFail = false;

    postFail = true;
    postBodies = [];
    openedThread = 0;
    membersHide = 0;
    els.createGroupMembersError.textContent = '';
    groupWizardTitle = 'Сборная';
    toggleGroupMember(12);
    submitCreateGroup();
    await wait();
    const server_error = {
        members_error: els.createGroupMembersError.textContent,
        opened_thread: openedThread,
        members_hide: membersHide
    };
    postFail = false;

    postHang = true;
    postBodies = [];
    openedThread = 0;
    createGroupBusy = false;
    groupWizardTitle = 'Сборная';
    groupSelectedIds = { 11: true, 12: true };
    submitCreateGroup();
    submitCreateGroup();
    const busyBefore = postBodies.length;
    if (typeof hangResolve === 'function') {
        hangResolve();
    }
    await wait();
    const busy = { post_count: busyBefore, opened_thread: openedThread };
    postHang = false;

    els.createGroupTitle.value = 'Старое';
    els.createGroupMembersSearch.value = 'Иванов';
    els.createGroupMembersTeamFilter.value = '15';
    groupSelectedIds = { 11: true };
    groupWizardTitle = 'Старое';
    els.createGroupTitleError.textContent = 'ошибка';
    nameShow = 0;
    membersHide = 0;
    fetchLog = [];
    openCreateGroupWizard();
    const reopen = {
        name_show: nameShow,
        members_hide: membersHide,
        title_value: els.createGroupTitle.value,
        search_value: els.createGroupMembersSearch.value,
        team_value: els.createGroupMembersTeamFilter.value,
        title_error: els.createGroupTitleError.textContent,
        selected_count: selectedGroupMemberIds().length,
        url: fetchLog.join(' ')
    };

    nameShow = 0;
    openCreateGroupWizard();
    const desktop_open = { name_show: nameShow, title_value: els.createGroupTitle.value };
    nameShow = 0;
    openCreateGroupWizard();
    const mobile_open = { name_show: nameShow };

    process.stdout.write(JSON.stringify({
        empty_title: empty_title,
        to_members: to_members,
        one_selected: one_selected,
        two_selected: two_selected,
        deselect: deselect,
        cancel: cancel,
        filter: filter,
        search: search,
        team_error: team_error,
        persist_selection: persist_selection,
        search_error: search_error,
        server_error: server_error,
        busy: busy,
        reopen: reopen,
        desktop_open: desktop_open,
        mobile_open: mobile_open
    }));
})().catch(function (err) {
    console.error(err && err.stack ? err.stack : err);
    process.exit(1);
});
JS;

        $tmp = sys_get_temp_dir().'/chat-group-ux-'.uniqid('', true).'.cjs';
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

    /**
     * @return array<string, mixed>
     */
    private function simulateGroupInboxBump(): array
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

let threadsCache = [
    { id: 10, title: 'Личка', peer_id: 11, is_group: false, unread_count: 0, last_message: 'hi' }
];
let currentThreadId = null;
function sortThreads(list) { return list; }
function renderThreads() {}
function applyThreadFilter(list) { return list; }
function setUnreadBadge() {}

eval(extractFn(chatJs, 'threadListTitle'));
eval(extractFn(chatJs, 'upsertThread'));
eval(extractFn(chatJs, 'applyInboxBump'));

applyInboxBump({
    thread_id: 77,
    title: 'Сборная',
    avatar: '/img/default-avatar.png',
    peer_id: null,
    is_group: true,
    last_message: '',
    unread_count: 0,
    unread_total: 0
});
const after_group = {
    ids: threadsCache.map(function (t) { return t.id; }),
    private_peer: (threadsCache.find(function (t) { return t.id === 10; }) || {}).peer_id,
    group_is_group: !!(threadsCache.find(function (t) { return t.id === 77; }) || {}).is_group,
    group_peer_null: (threadsCache.find(function (t) { return t.id === 77; }) || {}).peer_id == null
};

applyInboxBump({
    thread_id: 88,
    title: 'Сборная2',
    peer_id: 11,
    is_group: true,
    unread_count: 0
});
const after_group_with_peer = {
    has_10: threadsCache.some(function (t) { return t.id === 10; }),
    has_88: threadsCache.some(function (t) { return t.id === 88; }),
    title_88: (threadsCache.find(function (t) { return t.id === 88; }) || {}).title
};

threadsCache.push({ id: 20, title: 'Другая личка', peer_id: 11, is_group: false, unread_count: 0 });
applyInboxBump({
    thread_id: 21,
    title: 'Новая личка',
    peer_id: 11,
    is_group: false,
    unread_count: 1
});
const after_private = {
    has_10: threadsCache.some(function (t) { return t.id === 10; }),
    has_20: threadsCache.some(function (t) { return t.id === 20; }),
    has_21: threadsCache.some(function (t) { return t.id === 21; }),
    has_group: threadsCache.some(function (t) { return t.id === 77; })
};

process.stdout.write(JSON.stringify({
    after_group: after_group,
    after_group_with_peer: after_group_with_peer,
    after_private: after_private
}));
JS;

        $tmp = sys_get_temp_dir().'/chat-group-bump-'.uniqid('', true).'.cjs';
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

    /**
     * @return array<string, mixed>
     */
    private function simulateGroupListTitle(): array
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
        addEventListener() {},
        appendChild(child) {
            this.children.push(child);
            return child;
        }
    };
    return el;
}

const els = { threads: makeEl('div'), threadSearch: makeEl('input') };
els.threadSearch.value = '';
global.document = {
    getElementById(id) { return els[id] || null; },
    createElement(tag) { return makeEl(tag); }
};

const svgTick = '<svg></svg>';
let currentThreadId = null;
let threadsCache = [];
function collected(el) {
    return el.children.map(function (c) { return c.innerHTML; }).join('\n') + el.innerHTML;
}

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

function renderOne(t) {
    els.threads.innerHTML = '';
    renderThreads([t]);
    return { html: collected(els.threads) };
}

const named = renderOne({
    id: 77,
    title: 'Сборная',
    is_group: true,
    avatar: '/img/default-avatar.png',
    last_message: 'привет',
    unread_count: 0
});
const empty = renderOne({
    id: 78,
    title: '',
    is_group: true,
    avatar: '/img/default-avatar.png',
    last_message: '',
    unread_count: 0
});

threadsCache = [];
upsertThread({ id: 79, is_group: true, avatar: '/img/default-avatar.png' });
const upsert_empty = {
    html: collected(els.threads),
    cache_title: (threadsCache.find(function (t) { return t.id === 79; }) || {}).title
};

const private_empty = renderOne({
    id: 5,
    title: '',
    is_group: false,
    peer_id: 9,
    last_message: 'hi',
    unread_count: 0
});

els.threads.innerHTML = '';
renderThreads([
    { id: 1, title: 'Сборная', is_group: true, last_message: 'g', unread_count: 0 },
    { id: 2, title: 'Иванов Иван', is_group: false, peer_id: 9, last_message: 'p', unread_count: 0 }
]);
const mixed = { html: collected(els.threads) };

threadsCache = [
    { id: 77, title: 'Сборная', is_group: true, peer_id: null, avatar: '/img/default-avatar.png' }
];
els.threads.innerHTML = '';
upsertThread({
    id: 77,
    unread_count: 0,
    title: 'Сборная',
    avatar: '/img/default-avatar.png',
    is_group: true,
    peer_id: null
});
const after_open = { html: collected(els.threads) };

threadsCache = [
    { id: 1, title: 'Сборная', is_group: true, last_message: 'x' },
    { id: 2, title: 'Иванов Иван', is_group: false, last_message: 'y' }
];
els.threadSearch.value = 'сборн';
els.threads.innerHTML = '';
renderThreads(applyThreadFilter(threadsCache));
const search = { html: collected(els.threads) };

els.threadSearch.value = 'диалог';
els.threads.innerHTML = '';
renderThreads(applyThreadFilter(threadsCache));
const search_dialog_word = { html: collected(els.threads) };

const header = {
    named: threadListTitle({ title: 'Сборная', is_group: true }),
    empty: threadListTitle({ title: '', is_group: true }),
    private_empty: threadListTitle({ title: '', is_group: false }),
    private_named: threadListTitle({ title: 'Иванов Иван', is_group: false })
};

threadsCache = [
    {
        id: 10,
        title: 'Старый диалог',
        is_group: false,
        last_message: 'привет',
        last_message_time: '2026-08-01 09:30:00',
        unread_count: 0
    },
    {
        id: 11,
        title: 'Непрочитанный',
        is_group: false,
        last_message: 'входящее',
        last_message_time: '2026-07-01 09:30:00',
        unread_count: 2
    }
];
upsertThread({
    id: 99,
    title: 'Новая группа',
    is_group: true,
    last_message: null,
    last_message_time: null,
    unread_count: 0
});
const sort_empty_group = { ids: threadsCache.map(function (t) { return t.id; }) };

process.stdout.write(JSON.stringify({
    named: named,
    empty: empty,
    upsert_empty: upsert_empty,
    private_empty: private_empty,
    mixed: mixed,
    after_open: after_open,
    search: search,
    search_dialog_word: search_dialog_word,
    header: header,
    sort_empty_group: sort_empty_group
}));
JS;

        $tmp = sys_get_temp_dir().'/chat-group-list-title-'.uniqid('', true).'.cjs';
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

    /**
     * @return array<string, mixed>
     */
    private function simulateStartDialogPrefersPrivate(): array
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

let threadsCache = [
    { id: 77, title: 'Сборная', is_group: true, peer_id: 11 },
    { id: 10, title: 'Личка', is_group: false, peer_id: 11 }
];
let startDialogBusy = false;
let opened = null;
let modalHidden = 0;
let fetchCount = 0;
const urls = { storeThread: '/chat/api/threads' };
function headers() { return {}; }
function showContactsError() {}
function fieldError() { return ''; }
function loadThreads() {}
function upsertThread() {}
function contactsModal() {
    return { hide: function () { modalHidden += 1; } };
}
function openThread(id) { opened = id; }
global.fetch = function () { fetchCount += 1; return Promise.resolve({ ok: true, json: function () { return Promise.resolve({}); } }); };

eval(extractFn(chatJs, 'startDialog'));
startDialog(11);
const with_private = { opened: opened, modal_hidden: modalHidden, fetch_count: fetchCount };

threadsCache = [{ id: 77, title: 'Сборная', is_group: true, peer_id: 11 }];
startDialogBusy = false;
opened = null;
modalHidden = 0;
fetchCount = 0;
startDialog(11);
const only_group = { opened: opened, modal_hidden: modalHidden, fetch_count: fetchCount };

process.stdout.write(JSON.stringify({
    with_private: with_private,
    only_group: only_group
}));
JS;

        $tmp = sys_get_temp_dir().'/chat-group-start-dialog-'.uniqid('', true).'.cjs';
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
