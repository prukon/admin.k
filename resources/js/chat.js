(function () {
    const root = document.getElementById('chatApp');
    if (!root) {
        return;
    }

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta ? csrfMeta.content : '';
    const me = Number(root.getAttribute('data-me') || 0);
    const meAvatar = root.getAttribute('data-me-avatar') || '/img/default-avatar.png';
    const meName = root.getAttribute('data-me-name') || '';
    const urls = {
        threads: root.getAttribute('data-threads-url'),
        storeThread: root.getAttribute('data-store-thread-url'),
        storeGroup: root.getAttribute('data-store-group-url'),
        users: root.getAttribute('data-users-url'),
        unread: root.getAttribute('data-unread-url')
    };

    const svgTick = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>';

    function ticksHtml(isRead) {
        if (isRead) {
            return '<span class="checks checks-read" title="Прочитано" data-read-state="1">'
                + '<span class="check">' + svgTick + '</span>'
                + '<span class="check check-second">' + svgTick + '</span>'
                + '</span>';
        }
        return '<span class="checks checks-sent" title="Отправлено" data-read-state="0">'
            + '<span class="check">' + svgTick + '</span>'
            + '</span>';
    }

    let threadsCache = [];
    let currentThreadId = null;
    let currentPeerId = null;
    let currentIsGroup = false;
    let currentTeamId = null;
    let lastMessageId = null;
    let loadingOlder = false;
    let hasOlder = true;
    let threadChannel = null;
    let inboxBound = false;
    let pollTimer = null;
    let draftCache = Object.create(null);
    let lastPatchedDraft = Object.create(null);
    let draftTimer = null;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function parseEmojiJson(id) {
        const el = document.getElementById(id);
        if (!el) {
            return [];
        }
        try {
            const parsed = JSON.parse(el.textContent || '[]');
            return Array.isArray(parsed) ? parsed.map(function (item) { return String(item); }) : [];
        } catch (e) {
            return [];
        }
    }

    const composerEmojis = parseEmojiJson('composerEmojisJson');
    const reactionEmojis = parseEmojiJson('reactionEmojisJson');
    let reactionPickerMessageId = null;
    let longPressTimer = null;

    function emojiSeqRe() {
        return /(?:\p{RI}\p{RI}|\p{Extended_Pictographic}(?:\uFE0F|\uFE0E)?(?:\u200D\p{Extended_Pictographic}(?:\uFE0F|\uFE0E)?)*|[0-9#*]\uFE0F?\u20E3)/gu;
    }

    function emojiOnlyCount(text) {
        const trimmed = String(text == null ? '' : text).trim();
        if (!trimmed) {
            return 0;
        }
        const compact = trimmed.replace(/\s+/g, '');
        if (!compact) {
            return 0;
        }
        const parts = compact.match(emojiSeqRe());
        if (!parts || parts.join('') !== compact) {
            return 0;
        }
        return parts.length;
    }

    function isBigEmojiMessage(text) {
        const n = emojiOnlyCount(text);
        return n >= 1 && n <= 3;
    }

    function bigEmojiClass(text) {
        const n = emojiOnlyCount(text);
        if (n < 1 || n > 3) {
            return '';
        }
        return ' is-big-emoji is-big-emoji-' + n;
    }

    function insertComposerEmoji(input, emoji) {
        if (!input || !emoji) {
            return;
        }
        const value = String(input.value || '');
        let start = value.length;
        let end = value.length;
        if (typeof input.selectionStart === 'number' && typeof input.selectionEnd === 'number') {
            start = input.selectionStart;
            end = input.selectionEnd;
        }
        input.value = value.slice(0, start) + emoji + value.slice(end);
        const pos = start + String(emoji).length;
        if (typeof input.setSelectionRange === 'function') {
            try {
                input.focus();
                input.setSelectionRange(pos, pos);
            } catch (e) {}
        }
        if (typeof input.dispatchEvent === 'function') {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    function viewerHasReaction(chip, userId) {
        if (chip && chip.mine === true) {
            return true;
        }
        const ids = chip && Array.isArray(chip.user_ids) ? chip.user_ids : [];
        return ids.some(function (id) { return Number(id) === Number(userId); });
    }

    function reactionChipHtml(chip) {
        const emoji = String(chip && chip.emoji ? chip.emoji : '');
        const count = Number(chip && chip.count ? chip.count : 0);
        const mine = viewerHasReaction(chip, me) ? ' is-mine' : '';
        let extra = '';
        if (count >= 4) {
            extra = '<span class="msg-reaction-count">' + escapeHtml(String(count)) + '</span>';
        } else {
            const users = Array.isArray(chip && chip.users) ? chip.users : [];
            extra = '<span class="msg-reaction-avatars">' + users.map(function (u) {
                const src = escapeHtml(u && u.avatar ? u.avatar : '/img/default-avatar.png');
                const name = escapeHtml(u && u.name ? u.name : '');
                return '<img src="' + src + '" alt="' + name + '" title="' + name + '">';
            }).join('') + '</span>';
        }
        return '<button type="button" class="msg-reaction-chip' + mine + '" data-emoji="' + escapeHtml(emoji) + '">'
            + '<span class="msg-reaction-emoji">' + escapeHtml(emoji) + '</span>' + extra + '</button>';
    }

    function reactionsHtml(reactions) {
        const list = Array.isArray(reactions) ? reactions : [];
        if (!list.length) {
            return '<div class="msg-reactions" hidden></div>';
        }
        return '<div class="msg-reactions">' + list.map(reactionChipHtml).join('') + '</div>';
    }

    function applyReactions(messageId, reactions) {
        const row = document.querySelector('#messagesBox [data-mid="' + CSS.escape(String(messageId)) + '"]');
        if (!row) {
            return;
        }
        const html = reactionsHtml(reactions);
        const box = row.querySelector('.msg-reactions');
        if (box) {
            box.outerHTML = html;
            return;
        }
        const inner = row.querySelector('.msg-inner');
        if (inner) {
            inner.insertAdjacentHTML('beforeend', html);
        }
    }

    function ensureReactButton(row, mine) {
        if (!row || row.getAttribute('data-temp') === '1' || row.querySelector('.msg-react-btn')) {
            return;
        }
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'msg-react-btn';
        btn.setAttribute('aria-label', 'Добавить реакцию');
        btn.innerHTML = '<i class="fa-regular fa-face-smile" aria-hidden="true"></i>';
        if (mine) {
            row.insertBefore(btn, row.firstChild);
        } else {
            row.appendChild(btn);
        }
    }

    function fillEmojiGrid(el, list, btnClass) {
        if (!el) {
            return;
        }
        el.innerHTML = (list || []).map(function (emoji) {
            return '<button type="button" class="' + btnClass + '" data-emoji="' + escapeHtml(emoji) + '">'
                + escapeHtml(emoji) + '</button>';
        }).join('');
    }

    function closeEmojiPicker() {
        const picker = document.getElementById('emojiPicker');
        if (picker) {
            picker.hidden = true;
        }
        const btn = document.getElementById('emojiBtn');
        if (btn) {
            btn.setAttribute('aria-expanded', 'false');
        }
    }

    function closeReactionPicker() {
        const picker = document.getElementById('reactionPicker');
        if (picker) {
            picker.hidden = true;
        }
        document.querySelectorAll('#messagesBox .msg-row.is-react-open').forEach(function (row) {
            row.classList.remove('is-react-open');
        });
        reactionPickerMessageId = null;
    }

    function positionFixedPicker(picker, anchor) {
        if (!picker || !anchor || typeof anchor.getBoundingClientRect !== 'function') {
            return;
        }
        const rect = anchor.getBoundingClientRect();
        const width = Math.min(280, window.innerWidth - 16);
        let left = rect.right - width;
        if (left < 8) {
            left = 8;
        }
        if (left + width > window.innerWidth - 8) {
            left = window.innerWidth - width - 8;
        }
        let top = rect.top - 228;
        if (top < 8) {
            top = rect.bottom + 8;
        }
        picker.style.left = left + 'px';
        picker.style.top = top + 'px';
    }

    function openEmojiPicker() {
        const picker = document.getElementById('emojiPicker');
        const btn = document.getElementById('emojiBtn');
        if (!picker || !btn || btn.disabled) {
            return;
        }
        closeReactionPicker();
        if (!picker.childNodes.length) {
            fillEmojiGrid(picker, composerEmojis, 'chat-emoji-pick');
        }
        picker.hidden = false;
        picker.style.position = 'fixed';
        positionFixedPicker(picker, btn);
        btn.setAttribute('aria-expanded', 'true');
    }

    function toggleEmojiPicker() {
        const picker = document.getElementById('emojiPicker');
        if (picker && !picker.hidden) {
            closeEmojiPicker();
            return;
        }
        openEmojiPicker();
    }

    function openReactionPickerForRow(row) {
        if (!row || row.getAttribute('data-temp') === '1' || !row.getAttribute('data-mid')) {
            return;
        }
        const picker = document.getElementById('reactionPicker');
        if (!picker) {
            return;
        }
        closeEmojiPicker();
        document.querySelectorAll('#messagesBox .msg-row.is-react-open').forEach(function (el) {
            el.classList.remove('is-react-open');
        });
        row.classList.add('is-react-open');
        reactionPickerMessageId = row.getAttribute('data-mid');
        if (!picker.childNodes.length) {
            fillEmojiGrid(picker, reactionEmojis, 'chat-reaction-pick');
        }
        picker.hidden = false;
        const anchor = row.querySelector('.msg-react-btn') || row.querySelector('.msg-bubble') || row;
        positionFixedPicker(picker, anchor);
    }

    function showReactionError(text) {
        const el = document.getElementById('msgReactionError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function sendReaction(messageId, emoji, method) {
        const id = Number(currentThreadId);
        if (!id || !messageId) {
            return;
        }
        showReactionError('');
        const opts = {
            method: method,
            headers: headers(method === 'PUT'),
            credentials: 'same-origin'
        };
        if (method === 'PUT') {
            opts.body = JSON.stringify({ emoji: emoji });
        }
        fetch(threadUrl(id, '/messages/' + messageId + '/reaction'), opts)
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showReactionError(fieldError(res.data, 'emoji') || 'Не удалось сохранить реакцию.');
                    return;
                }
                applyReactions(res.data.message_id || messageId, res.data.reactions || []);
                closeReactionPicker();
            })
            .catch(function () {
                showReactionError('Не удалось сохранить реакцию. Проверьте соединение.');
            });
    }

    function mineEmojiOnRow(row) {
        const mine = row ? row.querySelector('.msg-reaction-chip.is-mine') : null;
        return mine ? mine.getAttribute('data-emoji') : '';
    }

    function headers(json) {
        const h = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf
        };
        if (json) {
            h['Content-Type'] = 'application/json';
        }
        if (window.Echo && typeof window.Echo.socketId === 'function') {
            const sid = window.Echo.socketId();
            if (sid) {
                h['X-Socket-Id'] = sid;
            }
        }
        return h;
    }

    function fieldError(xhrJson, field) {
        if (!xhrJson || !xhrJson.errors) {
            return xhrJson && xhrJson.message ? String(xhrJson.message) : '';
        }
        if (xhrJson.errors[field]) {
            const val = xhrJson.errors[field];
            return Array.isArray(val) ? String(val[0] || '') : String(val);
        }
        const prefix = field + '.';
        const keys = Object.keys(xhrJson.errors);
        for (let i = 0; i < keys.length; i++) {
            if (keys[i].indexOf(prefix) === 0) {
                const nested = xhrJson.errors[keys[i]];
                return Array.isArray(nested) ? String(nested[0] || '') : String(nested);
            }
        }
        return xhrJson.message ? String(xhrJson.message) : '';
    }

    function pad(n) {
        return n < 10 ? ('0' + n) : n;
    }

    function isToday(ts) {
        if (!ts) return false;
        const d = new Date(ts.replace(' ', 'T'));
        const n = new Date();
        return d.getFullYear() === n.getFullYear() && d.getMonth() === n.getMonth() && d.getDate() === n.getDate();
    }

    function fmtTime(ts) {
        if (!ts) return '';
        const d = new Date(String(ts).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        return isToday(ts)
            ? (pad(d.getHours()) + ':' + pad(d.getMinutes()))
            : (pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + String(d.getFullYear()).slice(-2));
    }

    function setCountBadge(el, total) {
        if (!el) {
            return;
        }
        let n = Number(total || 0);
        if (isNaN(n) || n < 0) {
            n = 0;
        }
        el.textContent = String(n);
        el.style.display = n > 0 ? '' : 'none';
    }

    function paintSplitNavBadges() {
        let privateUnread = 0;
        let groupUnread = 0;
        threadsCache.forEach(function (t) {
            const n = String(t.id) === String(currentThreadId) ? 0 : Number(t.unread_count || 0);
            if (t.is_group) {
                groupUnread += n;
            } else {
                privateUnread += n;
            }
        });
        setCountBadge(document.getElementById('chatPrivateUnreadBadge'), privateUnread);
        setCountBadge(document.getElementById('chatGroupUnreadBadge'), groupUnread);
    }

    function setUnreadBadge(total) {
        if (typeof window.KidsCrmChatSetUnread === 'function') {
            window.KidsCrmChatSetUnread(total);
        }
        if (typeof paintSplitNavBadges === 'function') {
            paintSplitNavBadges();
        }
    }

    function markThreadRead(threadId) {
        return fetch(threadUrl(threadId, '/read'), {
            method: 'PATCH',
            headers: headers(true),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res && typeof res.unread_total !== 'undefined') {
                setUnreadBadge(res.unread_total);
            }
        }).catch(function () {});
    }

    function threadUrl(id, suffix) {
        return '/chat/api/threads/' + id + (suffix || '');
    }

    function sortThreads(list) {
        return list.sort(function (a, b) {
            const au = a.unread_count || 0;
            const bu = b.unread_count || 0;
            if (au === 0 && bu > 0) return 1;
            if (au > 0 && bu === 0) return -1;
            const at = new Date(a.last_message_time || 0).getTime();
            const bt = new Date(b.last_message_time || 0).getTime();
            return bt - at;
        });
    }

    function threadListTitle(t) {
        const title = t && t.title ? String(t.title).trim() : '';
        if (title) {
            return title;
        }
        return t && t.is_group ? 'Группа' : 'Диалог';
    }

    function renderThreads(list) {
        const mobileSplit = !!(typeof window !== 'undefined' && window.matchMedia
            && window.matchMedia('(max-width: 991.98px)').matches);
        function fill(wrap, rows, emptyText) {
            wrap.innerHTML = '';
            if (!rows.length) {
                wrap.innerHTML = '<div class="chat-empty">' + emptyText + '</div>';
                return;
            }
            rows.forEach(function (t) {
                const active = String(t.id) === String(currentThreadId) ? ' active' : '';
                const unread = String(t.id) === String(currentThreadId) ? 0 : (t.unread_count || 0);
                const badge = unread > 0 ? '<span class="chat-li-unread">' + unread + '</span>' : '';
                const onlineDot = t.peer_is_online
                    ? '<span class="chat-online-dot" title="Онлайн"></span>'
                    : '';
                const ticks = t.last_message_is_mine ? ticksHtml(!!t.last_message_is_read) : '';
                const draft = normalizeDraft(t.draft_body);
                const previewClass = draft ? 'chat-li-preview is-draft' : 'chat-li-preview';
                const previewText = draft ? ('Черновик: ' + draft) : (t.last_message || '');
                const item = document.createElement('div');
                item.className = 'chat-list-item' + active;
                item.setAttribute('data-id', String(t.id));
                item.innerHTML =
                    '<div class="chat-avatar-wrap">' +
                    '<img class="chat-avatar" src="' + escapeHtml(t.avatar || '/img/default-avatar.png') + '" alt="">' +
                    onlineDot +
                    '</div>' +
                    '<div class="chat-li-body">' +
                    '<div class="chat-li-middle">' +
                    '<div class="chat-li-title">' + escapeHtml(threadListTitle(t)) + '</div>' +
                    '<div class="' + previewClass + '">' + escapeHtml(previewText) + '</div>' +
                    '</div>' +
                    '<div class="chat-li-meta">' +
                    '<div class="chat-li-time">' + ticks + escapeHtml(fmtTime(t.last_message_time)) + '</div>' +
                    badge +
                    '</div>' +
                    '</div>';
                item.addEventListener('click', function () {
                    openThread(t.id);
                });
                wrap.appendChild(item);
            });
        }
        const wrap = document.getElementById('threads');
        if (wrap) {
            const rows = mobileSplit ? list.filter(function (t) { return !t.is_group; }) : list;
            fill(wrap, rows, 'Диалогов нет');
        }
        const groupsWrap = document.getElementById('groupThreads');
        if (groupsWrap) {
            const groupRows = threadsCache.filter(function (t) { return !!t.is_group; });
            fill(groupsWrap, groupRows, 'Групп нет');
        }
        if (typeof paintSplitNavBadges === 'function') {
            paintSplitNavBadges();
        }
    }

    function upsertThread(patch) {
        const clean = {};
        Object.keys(patch || {}).forEach(function (key) {
            if (typeof patch[key] !== 'undefined') {
                clean[key] = patch[key];
            }
        });
        patch = clean;
        if (patch.peer_id && !patch.is_group) {
            threadsCache = threadsCache.filter(function (t) {
                return String(t.id) === String(patch.id)
                    || Number(t.peer_id) !== Number(patch.peer_id);
            });
        }
        let found = false;
        threadsCache = threadsCache.map(function (t) {
            if (String(t.id) !== String(patch.id)) return t;
            found = true;
            return Object.assign({}, t, patch);
        });
        if (!found && patch.id) {
            threadsCache.push(Object.assign({
                title: threadListTitle(patch),
                avatar: '/img/default-avatar.png',
                last_message: '',
                unread_count: 0
            }, patch));
        }
        sortThreads(threadsCache);
        renderThreads(applyThreadFilter(threadsCache));
    }

    function applyThreadFilter(list) {
        const q = (document.getElementById('threadSearch').value || '').toLowerCase().trim();
        if (!q) return list;
        return list.filter(function (t) {
            return (t.title && t.title.toLowerCase().indexOf(q) !== -1)
                || (t.last_message && t.last_message.toLowerCase().indexOf(q) !== -1)
                || (t.draft_body && String(t.draft_body).toLowerCase().indexOf(q) !== -1);
        });
    }

    function scrollBottom() {
        const box = document.getElementById('messagesBox');
        box.scrollTop = box.scrollHeight;
    }

    function messageExists(id) {
        return !!document.querySelector('#messagesBox [data-mid="' + CSS.escape(String(id)) + '"]');
    }

    const authorNameColors = ['#e17076', '#faa774', '#a695e7', '#7bc862', '#6ec9cb', '#65aadd', '#ee7aae', '#fa8116'];

    function authorNameColor(userId) {
        const n = Math.abs(Number(userId) || 0);
        return authorNameColors[n % authorNameColors.length];
    }

    function msgAvatarHtml(userId, avatarSrc) {
        const uid = Number(userId);
        if (!uid) {
            return '';
        }
        const src = escapeHtml(avatarSrc || '/img/default-avatar.png');
        return '<button type="button" class="msg-avatar-btn" data-user-id="' + escapeHtml(String(uid)) + '" aria-label="Профиль">'
            + '<img class="msg-avatar" src="' + src + '" alt="">'
            + '</button>';
    }

    function msgAuthorNameHtml(name, userId) {
        if (!name) {
            return '';
        }
        const color = authorNameColor(userId);
        return '<div class="msg-author-name" style="color:' + color + '">' + escapeHtml(name) + '</div>';
    }

    function appendMessage(m, opts) {
        opts = opts || {};
        const box = document.getElementById('messagesBox');
        const empty = box.querySelector('.chat-empty');
        if (empty) empty.remove();

        const mine = Number(m.user_id) === me || opts.mine === true;
        const row = document.createElement('div');
        row.className = mine ? 'msg-row msg-mine' : 'msg-row msg-other';
        if (m.id) row.setAttribute('data-mid', String(m.id));
        if (opts.tempId) {
            row.setAttribute('data-mid', String(opts.tempId));
            row.setAttribute('data-temp', '1');
        }

        if (mine) {
            row.setAttribute('data-read', m.is_read ? '1' : '0');
        }

        const checks = mine ? ticksHtml(!!m.is_read && !opts.pending) : '';
        const reactBtn = opts.tempId
            ? ''
            : '<button type="button" class="msg-react-btn" aria-label="Добавить реакцию"><i class="fa-regular fa-face-smile" aria-hidden="true"></i></button>';
        const avatarSrc = m.author_avatar || (mine ? meAvatar : '/img/default-avatar.png');
        const avatarBtn = msgAvatarHtml(m.user_id, avatarSrc);
        const authorName = currentIsGroup && !mine ? msgAuthorNameHtml(m.author_name, m.user_id) : '';
        const inner =
            '<div class="msg-inner">' + authorName + '<div class="msg-bubble' + bigEmojiClass(m.body) + '">' + escapeHtml(m.body) +
            '<div class="msg-meta"><span class="time">' + escapeHtml(fmtTime(m.created_at)) + '</span>' + checks + '</div>' +
            '</div>' + reactionsHtml(m.reactions) + '</div>';
        row.innerHTML = mine ? (reactBtn + inner + avatarBtn) : (avatarBtn + inner + reactBtn);

        if (opts.prepend) {
            box.insertBefore(row, box.firstChild);
        } else {
            box.appendChild(row);
        }
        return row;
    }

    function markMineRead() {
        document.querySelectorAll('#messagesBox .msg-row.msg-mine').forEach(function (row) {
            row.setAttribute('data-read', '1');
            const el = row.querySelector('.checks');
            if (el) {
                el.outerHTML = ticksHtml(true);
            }
        });
    }

    function markListOutgoingRead(threadId) {
        const row = threadsCache.find(function (t) {
            return String(t.id) === String(threadId);
        });
        if (!row || !row.last_message_is_mine) {
            return;
        }
        upsertThread({ id: threadId, last_message_is_read: true });
    }

    function syncMineReadStatus(messages) {
        if (!Array.isArray(messages)) {
            return;
        }
        messages.forEach(function (m) {
            if (Number(m.user_id) !== me || !m.id || !m.is_read) {
                return;
            }
            const row = document.querySelector('#messagesBox [data-mid="' + CSS.escape(String(m.id)) + '"]');
            if (!row || row.getAttribute('data-read') === '1') {
                return;
            }
            row.setAttribute('data-read', '1');
            const el = row.querySelector('.checks');
            if (el) {
                el.outerHTML = ticksHtml(true);
            }
        });
    }

    function setComposerEnabled(on) {
        const input = document.getElementById('msgInput');
        if (input) {
            input.disabled = !on;
        }
        const submit = document.querySelector('#sendForm button[type="submit"]');
        if (submit) {
            submit.disabled = !on;
        }
        const emojiBtn = document.getElementById('emojiBtn');
        if (emojiBtn) {
            emojiBtn.disabled = !on;
        }
        if (!on) {
            closeEmojiPicker();
            closeReactionPicker();
        }
    }

    function showMsgError(text) {
        const el = document.getElementById('msgBodyError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function showContactsError(text) {
        document.getElementById('contactsError').textContent = text || '';
    }

    function showContactsTeamError(text) {
        const el = document.getElementById('contactsTeamError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function contactsTeamValue() {
        const el = document.getElementById('contactsTeamFilter');
        return el ? String(el.value || '').trim() : '';
    }

    let inboxPollStamp = '';

    function loadThreads() {
        return fetch(urls.threads, { headers: headers(false), credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                const threads = Array.isArray(res.threads) ? res.threads : [];
                const stamp = JSON.stringify(threads) + '\0' + String(res.unread_total);
                if (stamp === inboxPollStamp) {
                    return;
                }
                inboxPollStamp = stamp;
                threadsCache = threads;
                mergeLocalDrafts(threadsCache);
                if (currentThreadId) {
                    threadsCache.forEach(function (t) {
                        if (String(t.id) === String(currentThreadId)) {
                            t.unread_count = 0;
                        }
                    });
                }
                sortThreads(threadsCache);
                renderThreads(applyThreadFilter(threadsCache));
                if (typeof res.unread_total !== 'undefined') {
                    setUnreadBadge(res.unread_total);
                }
            })
            .catch(function () {});
    }

    function normalizeDraft(value) {
        return String(value == null ? '' : value).trim();
    }

    function rememberDraft(threadId, body) {
        if (!threadId) {
            return;
        }
        draftCache[String(threadId)] = normalizeDraft(body);
    }

    function mergeLocalDrafts(threads) {
        threads.forEach(function (t) {
            const key = String(t.id);
            if (Object.prototype.hasOwnProperty.call(draftCache, key)) {
                t.draft_body = draftCache[key];
            } else {
                draftCache[key] = normalizeDraft(t.draft_body);
            }
        });
    }

    function composerDraftFor(thread) {
        const key = String(thread && thread.id ? thread.id : '');
        if (!key) {
            return '';
        }
        if (Object.prototype.hasOwnProperty.call(draftCache, key)) {
            return draftCache[key];
        }
        const fromServer = normalizeDraft(thread && thread.draft_body);
        draftCache[key] = fromServer;
        return fromServer;
    }

    function persistDraft(threadId, text) {
        const id = Number(threadId);
        if (!id) {
            return;
        }
        const body = normalizeDraft(text);
        const key = String(id);
        draftCache[key] = body;
        upsertThread({ id: id, draft_body: body });
        if (lastPatchedDraft[key] === body) {
            return;
        }
        lastPatchedDraft[key] = body;
        fetch(threadUrl(id, '/draft'), {
            method: 'PATCH',
            headers: headers(true),
            credentials: 'same-origin',
            body: JSON.stringify({ body: body })
        }).then(function (r) {
            if (!r.ok) {
                delete lastPatchedDraft[key];
            }
        }).catch(function () {
            delete lastPatchedDraft[key];
        });
    }

    function persistLeavingDraft(nextId) {
        const leavingId = currentThreadId;
        if (!leavingId) {
            return;
        }
        if (String(leavingId) === String(nextId)) {
            return;
        }
        clearTimeout(draftTimer);
        persistDraft(leavingId, document.getElementById('msgInput').value);
    }

    function scheduleDraftSave() {
        const id = currentThreadId;
        if (!id) {
            return;
        }
        rememberDraft(id, document.getElementById('msgInput').value);
        upsertThread({ id: id, draft_body: draftCache[String(id)] });
        clearTimeout(draftTimer);
        draftTimer = setTimeout(function () {
            persistDraft(id, document.getElementById('msgInput').value);
        }, 500);
    }

    function openThread(threadId) {
        persistLeavingDraft(threadId);
        const isMobile = !!(typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(max-width: 991.98px)').matches);
        let openSeq = 0;
        if (isMobile) {
            openThread._seq = (openThread._seq || 0) + 1;
            openSeq = openThread._seq;
            if (String(currentThreadId) !== String(threadId)) {
                const box = document.getElementById('messagesBox');
                if (box) {
                    box.innerHTML = '';
                }
            }
            const app = document.getElementById('chatApp');
            if (app) {
                let tab = 'messages';
                const cache = typeof threadsCache !== 'undefined' && Array.isArray(threadsCache) ? threadsCache : [];
                const row = cache.find(function (t) {
                    return String(t.id) === String(threadId);
                });
                if (row && row.is_group) {
                    tab = 'groups';
                }
                app.setAttribute('data-mobile-tab', tab);
                app.classList.add('is-dialog-open');
                if (typeof setMobileTabButtons === 'function') {
                    setMobileTabButtons(tab);
                }
            }
        }
        fetch(threadUrl(threadId), { headers: headers(false), credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw r;
                return r.json();
            })
            .then(function (res) {
                if (isMobile && openSeq !== openThread._seq) {
                    return;
                }
                if (!res || !res.thread || !res.thread.id) {
                    throw new Error('bad-thread-payload');
                }
                currentThreadId = res.thread.id;
                currentIsGroup = !!res.thread.is_group;
                currentTeamId = res.thread.team_id ? Number(res.thread.team_id) : null;
                currentPeerId = res.thread.peer_id ? Number(res.thread.peer_id) : null;
                if (isMobile) {
                    const app = document.getElementById('chatApp');
                    if (app) {
                        const tab = currentIsGroup ? 'groups' : 'messages';
                        app.setAttribute('data-mobile-tab', tab);
                        app.classList.add('is-dialog-open');
                        if (typeof setMobileTabButtons === 'function') {
                            setMobileTabButtons(tab);
                        }
                    }
                }
                setHeaderPeerClickable(!!currentPeerId || currentIsGroup);
                if (typeof setDeleteThreadVisible === 'function') {
                    setDeleteThreadVisible();
                }
                hasOlder = (res.messages || []).length >= 40;
                document.getElementById('threadTitle').textContent = threadListTitle(res.thread);
                if (typeof setThreadSubtitle === 'function') {
                    setThreadSubtitle(res.thread.header_subtitle);
                }
                const av = document.getElementById('threadAvatar');
                av.src = res.thread.avatar || '/img/default-avatar.png';
                av.style.display = '';
                setComposerEnabled(true);
                document.getElementById('msgInput').value = composerDraftFor(res.thread);
                document.getElementById('msgInput').focus();
                showMsgError('');

                const box = document.getElementById('messagesBox');
                box.innerHTML = '';
                (res.messages || []).forEach(function (m) { appendMessage(m); });
                if (!(res.messages || []).length) {
                    box.innerHTML = '<div class="chat-empty">Напишите первое сообщение</div>';
                }
                lastMessageId = (res.messages || []).length ? res.messages[res.messages.length - 1].id : null;
                scrollBottom();
                maybeLoadOlder();
                upsertThread({
                    id: currentThreadId,
                    unread_count: 0,
                    title: res.thread.title,
                    avatar: res.thread.avatar,
                    is_group: res.thread.is_group,
                    peer_id: res.thread.peer_id
                });
                if (typeof res.unread_total !== 'undefined') {
                    setUnreadBadge(res.unread_total);
                }
                try {
                    subscribeThread(currentThreadId);
                } catch (e) {}
                startPoll();
            })
            .catch(function () {
                if (isMobile && openSeq !== openThread._seq) {
                    return;
                }
                if (String(currentThreadId) === String(threadId)) {
                    return;
                }
                showMsgError('Не удалось открыть диалог.');
            });
    }

    function olderPrefetchThreshold(box) {
        return Math.max(480, Math.floor((box.clientHeight || 0) * 1.5));
    }

    function maybeLoadOlder() {
        const box = document.getElementById('messagesBox');
        if (!box || !box.clientHeight) return;
        if (box.scrollTop <= olderPrefetchThreshold(box)) {
            loadOlder();
        }
    }

    function loadOlder() {
        if (!currentThreadId || loadingOlder || !hasOlder) return;
        const first = document.querySelector('#messagesBox [data-mid]');
        const beforeId = first ? Number(first.getAttribute('data-mid')) : 0;
        if (!beforeId) return;
        loadingOlder = true;
        const box = document.getElementById('messagesBox');
        const prevHeight = box.scrollHeight;
        fetch(threadUrl(currentThreadId, '/messages?before_id=' + beforeId), {
            headers: headers(false),
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (list) {
                const rows = Array.isArray(list) ? list : [];
                hasOlder = rows.length >= 40;
                rows.reverse().forEach(function (m) {
                    if (!messageExists(m.id)) appendMessage(m, { prepend: true });
                });
                box.scrollTop = box.scrollHeight - prevHeight;
                loadingOlder = false;
                maybeLoadOlder();
            })
            .catch(function () {
                loadingOlder = false;
            });
    }

    function subscribeThread(threadId) {
        if (!window.Echo) return;
        try {
            if (threadChannel) {
                try {
                    threadChannel.stopListening('.message.created').stopListening('.thread.read').stopListening('.message.reaction');
                } catch (e) {}
            }
            threadChannel = window.Echo.private('thread.' + threadId);
            threadChannel.listen('.message.created', function (e) {
            const msg = e && e.message ? e.message : e;
            if (!msg || !msg.id) return;
            if (Number(msg.user_id) === me) {
                const opt = document.querySelector('#messagesBox .msg-row.msg-mine[data-temp="1"]');
                if (opt) {
                    opt.setAttribute('data-mid', String(msg.id));
                    opt.removeAttribute('data-temp');
                    const time = opt.querySelector('.time');
                    if (time) time.textContent = fmtTime(msg.created_at);
                    const checks = opt.querySelector('.checks');
                    if (checks) {
                        opt.setAttribute('data-read', msg.is_read ? '1' : '0');
                        checks.outerHTML = ticksHtml(!!msg.is_read);
                    }
                    ensureReactButton(opt, true);
                    applyReactions(msg.id, msg.reactions || []);
                } else if (!messageExists(msg.id)) {
                    appendMessage(msg);
                }
            } else if (!messageExists(msg.id)) {
                appendMessage(msg);
                markThreadRead(threadId);
            }
            lastMessageId = msg.id;
            upsertThread({
                id: threadId,
                unread_count: 0,
                last_message: msg.body,
                last_message_time: msg.created_at,
                last_message_is_mine: Number(msg.user_id) === me,
                last_message_is_read: Number(msg.user_id) === me ? !!msg.is_read : null
            });
            scrollBottom();
        });
        threadChannel.listen('.message.reaction', function (e) {
            if (!e || !e.message_id) {
                return;
            }
            applyReactions(e.message_id, e.reactions || []);
        });
        threadChannel.listen('.thread.read', function (e) {
            if (e && Number(e.user_id) !== me) {
                markMineRead();
                markListOutgoingRead(threadId);
            }
        });
        } catch (e) {}
    }

    function applyInboxBump(e) {
        if (!e || !e.thread_id) return;
        if (e.removed) {
            threadsCache = threadsCache.filter(function (t) {
                return String(t.id) !== String(e.thread_id);
            });
            renderThreads(applyThreadFilter(threadsCache));
            if (String(currentThreadId) === String(e.thread_id) && typeof closeCurrentThread === 'function') {
                closeCurrentThread();
            }
            if (typeof e.unread_total !== 'undefined') {
                setUnreadBadge(e.unread_total);
            }
            return;
        }
        const isActive = String(currentThreadId) === String(e.thread_id);
        upsertThread({
            id: e.thread_id,
            title: e.title,
            avatar: e.avatar,
            peer_id: e.peer_id,
            peer_is_online: e.peer_is_online,
            is_group: e.is_group,
            last_message: e.last_message,
            last_message_time: e.last_message_time,
            last_message_is_mine: e.last_message_is_mine,
            last_message_is_read: e.last_message_is_read,
            unread_count: isActive ? 0 : Number(e.unread_count || 0),
            draft_body: e.draft_body
        });
        if (typeof e.unread_total !== 'undefined') {
            if (isActive) {
                const keep = Number(e.unread_total) - Number(e.unread_count || 0);
                setUnreadBadge(keep < 0 ? 0 : keep);
            } else {
                setUnreadBadge(e.unread_total);
            }
        }
    }

    function subscribeInbox() {
        if (!window.Echo || inboxBound) return;
        try {
            const ch = window.Echo.private('inbox.' + me);
            ch.listen('.inbox.bump', applyInboxBump);
            ch.listen('.thread.read', function (e) {
                if (e && Number(e.user_id) !== me && e.thread_id) {
                    markListOutgoingRead(e.thread_id);
                }
            });
            inboxBound = true;
        } catch (e) {}
    }

    function startPoll() {
        subscribeInbox();
        if (inboxBound) {
            clearInterval(pollTimer);
            pollTimer = null;
            return;
        }
        if (pollTimer) {
            return;
        }
        pollTimer = setInterval(function () {
            subscribeInbox();
            if (inboxBound) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }, 1000);
    }

    document.getElementById('threadSearch').addEventListener('input', function () {
        renderThreads(applyThreadFilter(threadsCache));
    });

    document.getElementById('messagesBox').addEventListener('scroll', maybeLoadOlder, { passive: true });

    document.getElementById('sendForm').addEventListener('submit', function (e) {
        e.preventDefault();
        showMsgError('');
        showReactionError('');
        closeEmojiPicker();
        closeReactionPicker();
        const id = Number(currentThreadId);
        if (!id) {
            showMsgError('Сначала выберите диалог слева.');
            return;
        }
        const input = document.getElementById('msgInput');
        const text = (input.value || '').trim();
        if (!text) {
            showMsgError('Введите текст сообщения.');
            return;
        }

        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        clearTimeout(draftTimer);
        rememberDraft(id, '');
        input.value = '';
        const tempId = 'tmp-' + Date.now();
        const now = new Date();
        const nowSql = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
            + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        appendMessage({
            user_id: me,
            body: text,
            created_at: nowSql,
            is_read: false,
            author_avatar: meAvatar,
            author_name: meName
        }, { mine: true, tempId: tempId, pending: true });
        upsertThread({
            id: id,
            last_message: text,
            last_message_time: nowSql,
            last_message_is_mine: true,
            last_message_is_read: false,
            unread_count: 0,
            draft_body: ''
        });
        scrollBottom();

        fetch(threadUrl(id, '/messages'), {
            method: 'POST',
            headers: headers(true),
            credentials: 'same-origin',
            body: JSON.stringify({ body: text })
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, status: r.status, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showMsgError(fieldError(res.data, 'body') || 'Не удалось отправить сообщение.');
                    input.value = text;
                    rememberDraft(id, text);
                    upsertThread({ id: id, draft_body: text });
                    const tmp = document.querySelector('#messagesBox [data-mid="' + CSS.escape(tempId) + '"]');
                    if (tmp) tmp.remove();
                    return;
                }
                const m = res.data;
                const tmp = document.querySelector('#messagesBox [data-mid="' + CSS.escape(tempId) + '"]');
                if (tmp) {
                    tmp.setAttribute('data-mid', String(m.id));
                    tmp.removeAttribute('data-temp');
                    tmp.setAttribute('data-read', m.is_read ? '1' : '0');
                    const time = tmp.querySelector('.time');
                    if (time) time.textContent = fmtTime(m.created_at);
                    const checks = tmp.querySelector('.checks');
                    if (checks) {
                        checks.outerHTML = ticksHtml(!!m.is_read);
                    }
                    ensureReactButton(tmp, true);
                    applyReactions(m.id, m.reactions || []);
                    const bubble = tmp.querySelector('.msg-bubble');
                    if (bubble) {
                        const big = bigEmojiClass(m.body || text);
                        bubble.className = 'msg-bubble' + big;
                    }
                } else if (!messageExists(m.id)) {
                    appendMessage(m);
                }
                lastMessageId = m.id;
                lastPatchedDraft[String(id)] = '';
                upsertThread({
                    id: id,
                    last_message: m.body,
                    last_message_time: m.created_at,
                    last_message_is_mine: true,
                    last_message_is_read: !!m.is_read,
                    unread_count: 0,
                    draft_body: ''
                });
            })
            .catch(function () {
                showMsgError('Не удалось отправить сообщение. Проверьте соединение.');
                input.value = text;
                rememberDraft(id, text);
                upsertThread({ id: id, draft_body: text });
            })
            .finally(function () {
                btn.disabled = false;
                input.focus();
            });
    });

    document.getElementById('msgInput').addEventListener('input', scheduleDraftSave);

    const emojiBtn = document.getElementById('emojiBtn');
    if (emojiBtn) {
        emojiBtn.addEventListener('click', function (e) {
            e.preventDefault();
            toggleEmojiPicker();
        });
    }

    const emojiPicker = document.getElementById('emojiPicker');
    if (emojiPicker) {
        emojiPicker.addEventListener('click', function (e) {
            const pick = e.target.closest('.chat-emoji-pick');
            if (!pick) {
                return;
            }
            const input = document.getElementById('msgInput');
            insertComposerEmoji(input, pick.getAttribute('data-emoji') || '');
            if (input) {
                input.focus();
            }
        });
    }

    const reactionPicker = document.getElementById('reactionPicker');
    if (reactionPicker) {
        reactionPicker.addEventListener('click', function (e) {
            const pick = e.target.closest('.chat-reaction-pick');
            if (!pick || !reactionPickerMessageId) {
                return;
            }
            const emoji = pick.getAttribute('data-emoji') || '';
            const row = document.querySelector('#messagesBox [data-mid="' + CSS.escape(String(reactionPickerMessageId)) + '"]');
            const current = mineEmojiOnRow(row);
            if (current && current === emoji) {
                sendReaction(reactionPickerMessageId, emoji, 'DELETE');
            } else {
                sendReaction(reactionPickerMessageId, emoji, 'PUT');
            }
        });
    }

    document.getElementById('messagesBox').addEventListener('click', function (e) {
        const avatarBtn = e.target.closest('.msg-avatar-btn');
        if (avatarBtn) {
            e.preventDefault();
            openPeerCard(Number(avatarBtn.getAttribute('data-user-id')));
            return;
        }
        const chip = e.target.closest('.msg-reaction-chip');
        if (chip) {
            e.preventDefault();
            const row = chip.closest('.msg-row');
            const mid = row ? row.getAttribute('data-mid') : '';
            if (!mid || (row && row.getAttribute('data-temp') === '1')) {
                return;
            }
            const emoji = chip.getAttribute('data-emoji') || '';
            if (chip.classList.contains('is-mine')) {
                sendReaction(mid, emoji, 'DELETE');
            } else {
                sendReaction(mid, emoji, 'PUT');
            }
            return;
        }
        const reactBtn = e.target.closest('.msg-react-btn');
        if (reactBtn) {
            e.preventDefault();
            openReactionPickerForRow(reactBtn.closest('.msg-row'));
        }
    });

    document.getElementById('messagesBox').addEventListener('pointerdown', function (e) {
        const bubble = e.target.closest('.msg-bubble');
        if (!bubble) {
            return;
        }
        const row = bubble.closest('.msg-row');
        if (!row || row.getAttribute('data-temp') === '1') {
            return;
        }
        clearTimeout(longPressTimer);
        longPressTimer = setTimeout(function () {
            openReactionPickerForRow(row);
        }, 500);
    });
    ['pointerup', 'pointercancel', 'pointermove', 'scroll'].forEach(function (ev) {
        document.getElementById('messagesBox').addEventListener(ev, function () {
            clearTimeout(longPressTimer);
        }, { passive: true });
    });

    document.addEventListener('click', function (e) {
        const picker = document.getElementById('emojiPicker');
        const btn = document.getElementById('emojiBtn');
        const reaction = document.getElementById('reactionPicker');
        if (picker && !picker.hidden) {
            if (!(picker.contains(e.target) || (btn && btn.contains(e.target)))) {
                closeEmojiPicker();
            }
        }
        if (reaction && !reaction.hidden) {
            if (!(reaction.contains(e.target) || e.target.closest('.msg-react-btn'))) {
                closeReactionPicker();
            }
        }
    });

    window.addEventListener('resize', function () {
        const picker = document.getElementById('emojiPicker');
        const btn = document.getElementById('emojiBtn');
        if (picker && !picker.hidden && btn) {
            positionFixedPicker(picker, btn);
        }
    });

    function contactsModal() {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById('contactsModal'));
    }

    function peerCardModal() {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById('peerCardModal'));
    }

    function showPeerCardError(text) {
        const el = document.getElementById('peerCardError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function setHeaderPeerClickable(on) {
        const hit = document.getElementById('threadPeerHit');
        if (!hit) {
            return;
        }
        if (on) {
            hit.classList.remove('is-idle');
            hit.setAttribute('tabindex', '0');
            hit.setAttribute('role', 'button');
        } else {
            hit.classList.add('is-idle');
            hit.setAttribute('tabindex', '-1');
            hit.removeAttribute('role');
        }
    }

    function dashText(value) {
        const s = String(value == null ? '' : value).trim();
        return s === '' ? '-' : s;
    }

    function telHref(phone) {
        const raw = String(phone == null ? '' : phone).trim();
        if (!raw) {
            return '';
        }
        const cleaned = raw.replace(/[^\d+]/g, '');
        if (!cleaned || cleaned === '+') {
            return '';
        }
        return 'tel:' + cleaned;
    }

    function phoneHtml(phone) {
        const shown = dashText(phone);
        if (shown === '-') {
            return escapeHtml('-');
        }
        const href = telHref(phone);
        if (!href) {
            return escapeHtml(shown);
        }
        return '<a href="' + escapeHtml(href) + '">' + escapeHtml(shown) + '</a>';
    }

    function renderPeerCard(u, targetId) {
        const body = document.getElementById(targetId || 'peerCardBody');
        if (!body) {
            return;
        }
        u = u || {};
        body.innerHTML =
            '<div class="peer-card">' +
            '<img class="peer-card-avatar" src="' + escapeHtml(u.avatar || '/img/default-avatar.png') + '" alt="">' +
            '<div class="peer-card-name">' + escapeHtml(dashText(u.full_name)) + '</div>' +
            '<div class="peer-card-row"><div class="peer-card-label">Телефон</div><div>' + phoneHtml(u.phone) + '</div></div>' +
            '<div class="peer-card-row"><div class="peer-card-label">Родитель</div><div>' + escapeHtml(dashText(u.parent_full_name)) + '</div></div>' +
            '<div class="peer-card-row"><div class="peer-card-label">Телефон родителя</div><div>' + phoneHtml(u.parent_phone) + '</div></div>' +
            '<div class="peer-card-row"><div class="peer-card-label">Последний онлайн</div><div>' + escapeHtml(dashText(u.last_seen_label)) + '</div></div>' +
            '<div class="peer-card-row"><div class="peer-card-label">Группы</div><div>' + escapeHtml(dashText(u.team_title)) + '</div></div>' +
            '</div>';
    }

    function closeCurrentThread() {
        currentThreadId = null;
        currentPeerId = null;
        currentIsGroup = false;
        currentTeamId = null;
        lastMessageId = null;
        hasOlder = false;
        setHeaderPeerClickable(false);
        if (typeof setDeleteThreadVisible === 'function') {
            setDeleteThreadVisible();
        }
        const title = document.getElementById('threadTitle');
        if (title) {
            title.textContent = 'Выберите диалог';
        }
        if (typeof setThreadSubtitle === 'function') {
            setThreadSubtitle('');
        }
        const av = document.getElementById('threadAvatar');
        if (av) {
            av.style.display = 'none';
            av.src = '/img/default-avatar.png';
        }
        setComposerEnabled(false);
        const input = document.getElementById('msgInput');
        if (input) {
            input.value = '';
        }
        const box = document.getElementById('messagesBox');
        if (box) {
            box.innerHTML = '<div class="chat-empty">Сообщения появятся здесь…</div>';
        }
        if (root) {
            root.classList.remove('is-dialog-open');
        }
        const groupEl = document.getElementById('groupCardModal');
        if (groupEl && window.bootstrap) {
            const inst = bootstrap.Modal.getInstance(groupEl);
            if (inst) {
                inst.hide();
            }
        }
    }

    function openPeerCard(userId, queued) {
        const id = userId != null && userId !== '' ? Number(userId) : currentPeerId;
        if (!id) {
            return;
        }
        showPeerCardError('');
        renderPeerCard({});
        if (queued && typeof showModalQueued === 'function') {
            showModalQueued('peerCardModal');
        } else {
            peerCardModal().show();
        }
        fetch(urls.users + '/' + encodeURIComponent(String(id)), {
            headers: headers(false),
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showPeerCardError(fieldError(res.data, 'user') || res.data.message || 'Не удалось загрузить карточку.');
                    return;
                }
                renderPeerCard(res.data);
            })
            .catch(function () {
                showPeerCardError('Не удалось загрузить карточку.');
            });
    }

    function showAccountCardError(text) {
        const el = document.getElementById('accountCardError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function loadAccountCard() {
        showAccountCardError('');
        renderPeerCard({}, 'accountCardBody');
        if (!me) {
            showAccountCardError('Не удалось загрузить карточку.');
            return;
        }
        fetch(urls.users + '/' + encodeURIComponent(String(me)), {
            headers: headers(false),
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showAccountCardError(fieldError(res.data, 'user') || res.data.message || 'Не удалось загрузить карточку.');
                    return;
                }
                renderPeerCard(res.data, 'accountCardBody');
            })
            .catch(function () {
                showAccountCardError('Не удалось загрузить карточку.');
            });
    }

    function isMobileChat() {
        return !!(window.matchMedia && window.matchMedia('(max-width: 991.98px)').matches);
    }

    function placeContactsMount() {
        const mount = document.getElementById('contactsMount');
        const pane = document.getElementById('chatPaneContacts');
        const modalBody = document.getElementById('contactsModalBody');
        if (!mount || !pane || !modalBody) {
            return;
        }
        const home = isMobileChat() ? pane : modalBody;
        if (mount.parentElement !== home) {
            home.appendChild(mount);
        }
    }

    function setMobileTabButtons(tab) {
        document.querySelectorAll('.chat-mobile-nav-btn').forEach(function (btn) {
            const on = btn.getAttribute('data-mobile-tab') === tab;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function setMobileTab(tab, opts) {
        opts = opts || {};
        if (!root) {
            return;
        }
        if (!opts.keepDialog) {
            root.classList.remove('is-dialog-open');
        }
        root.setAttribute('data-mobile-tab', tab);
        setMobileTabButtons(tab);
        placeContactsMount();
        if (tab === 'contacts') {
            const search = document.getElementById('contactsSearch');
            loadContacts(search ? search.value : '');
        }
        if (tab === 'account') {
            loadAccountCard();
        }
    }

    function leaveMobileDialog() {
        if (!root) {
            return;
        }
        root.classList.remove('is-dialog-open');
    }

    function renderContacts(list) {
        const ul = document.getElementById('contactsList');
        ul.innerHTML = '';
        if (!Array.isArray(list) || list.length === 0) {
            ul.innerHTML = '<li class="text-muted text-center py-3">Ничего не найдено</li>';
            return;
        }
        list.forEach(function (u) {
            const li = document.createElement('li');
            li.setAttribute('data-id', String(u.id));
            const role = u.role_label || u.role_name || '';
            const parentFio = String(u.parent_full_name || '').trim();
            const team = String(u.team_title || '').trim();
            const onlineClass = u.is_online ? 'is-online' : 'is-offline';
            li.innerHTML =
                '<div class="contact-row">' +
                '<div class="contact-avatar-wrap">' +
                '<img class="contact-avatar" src="' + escapeHtml(u.avatar) + '" alt="">' +
                '<span class="contact-online-dot ' + onlineClass + '"></span>' +
                '</div>' +
                '<div class="contact-main">' +
                '<div class="contact-name">' + escapeHtml(u.name || '') + '</div>' +
                (parentFio ? '<div class="contact-parent">' + escapeHtml(parentFio) + '</div>' : '') +
                '</div>' +
                '<div class="contact-team contact-sub">' + (team ? escapeHtml(team) : '') + '</div>' +
                '<div class="contact-role contact-sub">' + escapeHtml(role) + '</div>' +
                '</div>';
            li.querySelector('.contact-row').addEventListener('click', function () {
                startDialog(Number(u.id));
            });
            ul.appendChild(li);
        });
    }

    function loadContacts(q) {
        showContactsError('');
        showContactsTeamError('');
        const params = new URLSearchParams();
        const query = String(q || '').trim();
        if (query) {
            params.set('q', query);
        }
        const teamId = contactsTeamValue();
        if (teamId) {
            params.set('team_id', teamId);
        }
        const qs = params.toString();
        const url = urls.users + (qs ? ('?' + qs) : '');
        fetch(url, { headers: headers(false), credentials: 'same-origin' })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    const teamErr = res.data && res.data.errors && res.data.errors.team_id
                        ? fieldError(res.data, 'team_id')
                        : '';
                    const qErr = res.data && res.data.errors && res.data.errors.q
                        ? fieldError(res.data, 'q')
                        : '';
                    showContactsTeamError(teamErr);
                    showContactsError(qErr || (!teamErr ? (res.data.message || 'Не удалось загрузить контакты.') : ''));
                    renderContacts([]);
                    return;
                }
                renderContacts(Array.isArray(res.data) ? res.data : []);
            })
            .catch(function () {
                showContactsError('Не удалось загрузить контакты.');
                renderContacts([]);
            });
    }

    let startDialogBusy = false;

    function startDialog(userId) {
        if (startDialogBusy) {
            return;
        }
        showContactsError('');
        const existing = threadsCache.find(function (t) {
            return !t.is_group && Number(t.peer_id) === Number(userId);
        });
        if (existing) {
            contactsModal().hide();
            openThread(existing.id);
            return;
        }
        startDialogBusy = true;
        fetch(urls.storeThread, {
            method: 'POST',
            headers: headers(true),
            credentials: 'same-origin',
            body: JSON.stringify({ user_id: userId })
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showContactsError(fieldError(res.data, 'user_id') || 'Не удалось открыть диалог.');
                    return;
                }
                contactsModal().hide();
                const id = res.data.thread_id || (res.data.thread && res.data.thread.id);
                if (res.data.thread) {
                    upsertThread(Object.assign({ unread_count: 0 }, res.data.thread));
                }
                if (id) openThread(id);
                else loadThreads();
            })
            .catch(function () {
                showContactsError('Не удалось открыть диалог.');
            })
            .finally(function () {
                startDialogBusy = false;
            });
    }

    let groupWizardTitle = '';
    let groupSelectedIds = {};
    let groupMembersDebounce = null;
    let createGroupBusy = false;

    function createGroupNameModal() {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById('createGroupNameModal'));
    }

    function createGroupMembersModal() {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById('createGroupMembersModal'));
    }

    function showCreateGroupTitleError(text) {
        const el = document.getElementById('createGroupTitleError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function showCreateGroupMembersError(text) {
        const el = document.getElementById('createGroupMembersError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function showCreateGroupMembersTeamError(text) {
        const el = document.getElementById('createGroupMembersTeamError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function showCreateGroupMembersSearchError(text) {
        const el = document.getElementById('createGroupMembersSearchError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function createGroupMembersTeamValue() {
        const el = document.getElementById('createGroupMembersTeamFilter');
        return el ? String(el.value || '').trim() : '';
    }

    function selectedGroupMemberIds() {
        return Object.keys(groupSelectedIds).map(Number).filter(function (id) { return id > 0; });
    }

    function resetCreateGroupWizard() {
        groupWizardTitle = '';
        groupSelectedIds = {};
        createGroupBusy = false;
        showCreateGroupTitleError('');
        showCreateGroupMembersError('');
        showCreateGroupMembersTeamError('');
        showCreateGroupMembersSearchError('');
        const title = document.getElementById('createGroupTitle');
        if (title) {
            title.value = '';
        }
        const search = document.getElementById('createGroupMembersSearch');
        if (search) {
            search.value = '';
        }
        const team = document.getElementById('createGroupMembersTeamFilter');
        if (team) {
            team.value = '';
        }
        const list = document.getElementById('createGroupMembersList');
        if (list) {
            list.innerHTML = '';
        }
    }

    function closeCreateGroupWizard() {
        createGroupNameModal().hide();
        createGroupMembersModal().hide();
        resetCreateGroupWizard();
    }

    function openCreateGroupWizard() {
        resetCreateGroupWizard();
        createGroupMembersModal().hide();
        createGroupNameModal().show();
        const title = document.getElementById('createGroupTitle');
        if (title && typeof title.focus === 'function') {
            title.focus();
        }
    }

    function proceedCreateGroupToMembers() {
        showCreateGroupTitleError('');
        const titleEl = document.getElementById('createGroupTitle');
        const title = titleEl ? String(titleEl.value || '').trim() : '';
        if (!title) {
            showCreateGroupTitleError('Введите название группы.');
            return false;
        }
        groupWizardTitle = title;
        createGroupNameModal().hide();
        createGroupMembersModal().show();
        loadGroupMembers('');
        return true;
    }

    function toggleGroupMember(userId) {
        const id = Number(userId);
        if (!id) {
            return;
        }
        if (groupSelectedIds[id]) {
            delete groupSelectedIds[id];
        } else {
            groupSelectedIds[id] = true;
        }
        const row = document.querySelector('#createGroupMembersList [data-id="' + CSS.escape(String(id)) + '"]');
        if (row) {
            if (groupSelectedIds[id]) {
                row.classList.add('is-selected');
            } else {
                row.classList.remove('is-selected');
            }
        }
        showCreateGroupMembersError('');
    }

    function renderGroupMembers(list) {
        const ul = document.getElementById('createGroupMembersList');
        if (!ul) {
            return;
        }
        ul.innerHTML = '';
        if (!Array.isArray(list) || list.length === 0) {
            ul.innerHTML = '<li class="text-muted text-center py-3">Ничего не найдено</li>';
            return;
        }
        list.forEach(function (u) {
            const li = document.createElement('li');
            const selected = !!groupSelectedIds[Number(u.id)];
            li.setAttribute('data-id', String(u.id));
            li.className = 'group-member-row' + (selected ? ' is-selected' : '');
            const role = u.role_label || u.role_name || '';
            const parentFio = String(u.parent_full_name || '').trim();
            const team = String(u.team_title || '').trim();
            li.innerHTML =
                '<div class="contact-row">' +
                '<div class="contact-avatar-wrap">' +
                '<img class="contact-avatar" src="' + escapeHtml(u.avatar) + '" alt="">' +
                '<span class="group-pick-check" aria-hidden="true">' + svgTick + '</span>' +
                '</div>' +
                '<div class="contact-main">' +
                '<div class="contact-name">' + escapeHtml(u.name || '') + '</div>' +
                (parentFio ? '<div class="contact-parent">' + escapeHtml(parentFio) + '</div>' : '') +
                '</div>' +
                '<div class="contact-team contact-sub">' + (team ? escapeHtml(team) : '') + '</div>' +
                '<div class="contact-role contact-sub">' + escapeHtml(role) + '</div>' +
                '</div>';
            li.addEventListener('click', function () {
                toggleGroupMember(Number(u.id));
            });
            ul.appendChild(li);
        });
    }

    function loadGroupMembers(q) {
        showCreateGroupMembersSearchError('');
        showCreateGroupMembersTeamError('');
        const params = new URLSearchParams();
        const query = String(q || '').trim();
        if (query) {
            params.set('q', query);
        }
        const teamId = createGroupMembersTeamValue();
        if (teamId) {
            params.set('team_id', teamId);
        }
        const qs = params.toString();
        const url = urls.users + (qs ? ('?' + qs) : '');
        fetch(url, { headers: headers(false), credentials: 'same-origin' })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    const teamErr = res.data && res.data.errors && res.data.errors.team_id
                        ? fieldError(res.data, 'team_id')
                        : '';
                    const qErr = res.data && res.data.errors && res.data.errors.q
                        ? fieldError(res.data, 'q')
                        : '';
                    showCreateGroupMembersTeamError(teamErr);
                    showCreateGroupMembersSearchError(qErr || (!teamErr ? (res.data.message || 'Не удалось загрузить контакты.') : ''));
                    renderGroupMembers([]);
                    return;
                }
                renderGroupMembers(Array.isArray(res.data) ? res.data : []);
            })
            .catch(function () {
                showCreateGroupMembersSearchError('Не удалось загрузить контакты.');
                renderGroupMembers([]);
            });
    }

    function submitCreateGroup() {
        if (createGroupBusy) {
            return;
        }
        showCreateGroupMembersError('');
        const ids = selectedGroupMemberIds();
        if (ids.length < 2) {
            showCreateGroupMembersError('Выберите минимум двух участников.');
            return;
        }
        const title = groupWizardTitle || (document.getElementById('createGroupTitle')
            ? String(document.getElementById('createGroupTitle').value || '').trim()
            : '');
        if (!title) {
            showCreateGroupMembersError('Введите название группы.');
            return;
        }
        createGroupBusy = true;
        fetch(urls.storeGroup, {
            method: 'POST',
            headers: headers(true),
            credentials: 'same-origin',
            body: JSON.stringify({ title: title, user_ids: ids })
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    const titleErr = fieldError(res.data, 'title');
                    const membersErr = fieldError(res.data, 'user_ids');
                    if (titleErr && !membersErr) {
                        showCreateGroupMembersError(titleErr);
                    } else {
                        showCreateGroupMembersError(membersErr || 'Не удалось создать группу.');
                    }
                    return;
                }
                createGroupMembersModal().hide();
                createGroupNameModal().hide();
                resetCreateGroupWizard();
                const id = res.data.thread_id || (res.data.thread && res.data.thread.id);
                if (res.data.thread) {
                    upsertThread(Object.assign({ unread_count: 0 }, res.data.thread));
                }
                if (id) {
                    openThread(id);
                } else {
                    loadThreads();
                }
            })
            .catch(function () {
                showCreateGroupMembersError('Не удалось создать группу.');
            })
            .finally(function () {
                createGroupBusy = false;
            });
    }

    let contactsDebounce = null;
    document.getElementById('openContactsBtn').addEventListener('click', function () {
        showContactsError('');
        showContactsTeamError('');
        document.getElementById('contactsSearch').value = '';
        document.getElementById('contactsTeamFilter').value = '';
        loadContacts('');
        contactsModal().show();
    });
    document.getElementById('contactsSearch').addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(contactsDebounce);
        contactsDebounce = setTimeout(function () { loadContacts(q); }, 250);
    });
    document.getElementById('contactsTeamFilter').addEventListener('change', function () {
        const q = document.getElementById('contactsSearch').value.trim();
        loadContacts(q);
    });

    document.querySelectorAll('.js-open-create-group').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openCreateGroupWizard();
        });
    });
    const createGroupNameForm = document.getElementById('createGroupNameForm');
    if (createGroupNameForm) {
        createGroupNameForm.addEventListener('submit', function (e) {
            e.preventDefault();
            proceedCreateGroupToMembers();
        });
    }
    const createGroupMembersForm = document.getElementById('createGroupMembersForm');
    if (createGroupMembersForm) {
        createGroupMembersForm.addEventListener('submit', function (e) {
            e.preventDefault();
            submitCreateGroup();
        });
    }
    const createGroupMembersSearch = document.getElementById('createGroupMembersSearch');
    if (createGroupMembersSearch) {
        createGroupMembersSearch.addEventListener('input', function () {
            const q = this.value.trim();
            clearTimeout(groupMembersDebounce);
            groupMembersDebounce = setTimeout(function () { loadGroupMembers(q); }, 250);
        });
    }
    const createGroupMembersTeamFilter = document.getElementById('createGroupMembersTeamFilter');
    if (createGroupMembersTeamFilter) {
        createGroupMembersTeamFilter.addEventListener('change', function () {
            const q = document.getElementById('createGroupMembersSearch').value.trim();
            loadGroupMembers(q);
        });
    }
    const createGroupNameModalEl = document.getElementById('createGroupNameModal');
    if (createGroupNameModalEl) {
        createGroupNameModalEl.addEventListener('hidden.bs.modal', function () {
            const membersEl = document.getElementById('createGroupMembersModal');
            if (membersEl && membersEl.classList.contains('show')) {
                return;
            }
            if (!groupWizardTitle) {
                resetCreateGroupWizard();
            }
        });
    }
    const createGroupMembersModalEl = document.getElementById('createGroupMembersModal');
    if (createGroupMembersModalEl) {
        createGroupMembersModalEl.addEventListener('hidden.bs.modal', function () {
            const nameEl = document.getElementById('createGroupNameModal');
            if (nameEl && nameEl.classList.contains('show')) {
                return;
            }
            resetCreateGroupWizard();
        });
    }

    let groupMembersBusy = false;
    let groupMembersHasMore = false;
    let groupMembersCanManage = false;
    let addGroupMembersSelected = {};
    let addGroupMembersDebounce = null;
    let addGroupMembersBusy = false;

    function groupCardModal() {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById('groupCardModal'));
    }

    function addGroupMembersModal() {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById('addGroupMembersModal'));
    }

    function showGroupCardError(text) {
        const el = document.getElementById('groupCardError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function showAddGroupMembersError(text) {
        const el = document.getElementById('addGroupMembersError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function showAddGroupMembersTeamError(text) {
        const el = document.getElementById('addGroupMembersTeamError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function showAddGroupMembersSearchError(text) {
        const el = document.getElementById('addGroupMembersSearchError');
        if (el) {
            el.textContent = text || '';
        }
    }

    function membersCountLabel(n) {
        n = Number(n) || 0;
        const n10 = n % 10;
        const n100 = n % 100;
        let word = 'участников';
        if (n10 === 1 && n100 !== 11) {
            word = 'участник';
        } else if (n10 >= 2 && n10 <= 4 && (n100 < 12 || n100 > 14)) {
            word = 'участника';
        }
        return n + ' ' + word;
    }

    function setThreadSubtitle(text) {
        const el = document.getElementById('threadSubtitle');
        if (!el) {
            return;
        }
        const s = String(text == null ? '' : text).trim();
        el.textContent = s;
        el.style.display = s === '' ? 'none' : '';
    }

    function chatToast(message) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, 'success');
        }
    }

    function canDeleteThread() {
        return !!(root && root.getAttribute('data-can-delete-thread') === '1');
    }

    function showThreadDeleteError(text) {
        const el = document.getElementById('threadDeleteError');
        if (!el) {
            return;
        }
        el.textContent = text ? String(text) : '';
    }

    function setDeleteThreadVisible() {
        const btn = document.getElementById('deleteThreadBtn');
        if (!btn) {
            return;
        }
        const allow = canDeleteThread() && currentThreadId && !currentTeamId;
        btn.style.display = allow ? '' : 'none';
        if (!allow) {
            showThreadDeleteError('');
        }
    }

    function confirmDeleteThread() {
        if (!currentThreadId || currentTeamId || !canDeleteThread()) {
            return;
        }
        if (typeof showConfirmDeleteModal !== 'function') {
            return;
        }
        showConfirmDeleteModal(
            'Удалить чат',
            'Вы уверены, что хотите удалить этот чат? Сообщения пропадут у всех участников.',
            function () {
                const confirmEl = document.getElementById('confirmDeleteModal');
                if (window.jQuery) {
                    window.jQuery(confirmEl).off('hidden.bs.modal.return');
                }
                submitDeleteThread();
            }
        );
    }

    function submitDeleteThread() {
        if (!currentThreadId || currentTeamId || !canDeleteThread()) {
            return;
        }
        const threadId = currentThreadId;
        showThreadDeleteError('');
        fetch(threadUrl(threadId), {
            method: 'DELETE',
            headers: headers(true),
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showThreadDeleteError(fieldError(res.data, 'thread') || res.data.message || 'Не удалось удалить чат.');
                    return;
                }
                threadsCache = threadsCache.filter(function (t) {
                    return String(t.id) !== String(threadId);
                });
                renderThreads(applyThreadFilter(threadsCache));
                closeCurrentThread();
                chatToast(res.data.message || 'Чат удалён.');
            })
            .catch(function () {
                showThreadDeleteError('Не удалось удалить чат.');
            });
    }

    function setGroupManageVisible(on) {
        const btn = document.getElementById('addGroupMembersBtn');
        if (btn) {
            if (on) {
                btn.classList.remove('is-hidden');
            } else {
                btn.classList.add('is-hidden');
            }
        }
    }

    function lastGroupMemberUserId() {
        const rows = document.querySelectorAll('#groupMembersBody tr[data-id]');
        if (!rows.length) {
            return 0;
        }
        return Number(rows[rows.length - 1].getAttribute('data-id') || 0);
    }

    function appendGroupMembers(list, canManage) {
        const body = document.getElementById('groupMembersBody');
        if (!body) {
            return;
        }
        (list || []).forEach(function (m) {
            if (!m || !m.id) {
                return;
            }
            if (body.querySelector('tr[data-id="' + CSS.escape(String(m.id)) + '"]')) {
                return;
            }
            const role = m.role_label || m.role_name || '';
            const isMe = Number(m.id) === me;
            const removeBtn = (canManage && !isMe)
                ? '<button type="button" class="group-member-remove js-remove-group-member" data-id="'
                    + escapeHtml(String(m.id)) + '">удалить</button>'
                : '';
            const tr = document.createElement('tr');
            tr.setAttribute('data-id', String(m.id));
            tr.innerHTML =
                '<td><img class="group-member-avatar" src="' + escapeHtml(m.avatar || '/img/default-avatar.png') + '" alt=""></td>' +
                '<td><span class="group-member-name">' + escapeHtml(m.full_name || '') + '</span> ' + removeBtn + '</td>' +
                '<td class="group-member-role">' + escapeHtml(role) + '</td>';
            body.appendChild(tr);
        });
    }

    function fetchGroupMembers(reset) {
        if (!currentThreadId || !currentIsGroup || groupMembersBusy) {
            return;
        }
        const afterId = reset ? 0 : lastGroupMemberUserId();
        if (!reset && (!groupMembersHasMore || !afterId)) {
            return;
        }
        groupMembersBusy = true;
        if (reset) {
            showGroupCardError('');
            const body = document.getElementById('groupMembersBody');
            if (body) {
                body.innerHTML = '';
            }
            groupMembersHasMore = true;
        }
        const qs = afterId ? ('?after_user_id=' + encodeURIComponent(String(afterId))) : '';
        fetch(threadUrl(currentThreadId, '/participants' + qs), {
            headers: headers(false),
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showGroupCardError(fieldError(res.data, 'after_user_id') || res.data.message || 'Не удалось загрузить участников.');
                    return;
                }
                const data = res.data || {};
                const thread = data.thread || {};
                document.getElementById('groupCardTitle').textContent = thread.title || 'Группа';
                const av = document.getElementById('groupCardAvatar');
                if (av) {
                    av.src = thread.avatar || '/img/default-avatar.png';
                }
                document.getElementById('groupCardCount').textContent = membersCountLabel(thread.members_total);
                setThreadSubtitle(membersCountLabel(thread.members_total));
                groupMembersCanManage = !!data.can_manage;
                setGroupManageVisible(groupMembersCanManage);
                groupMembersHasMore = !!data.has_more;
                appendGroupMembers(data.members || [], groupMembersCanManage);
            })
            .catch(function () {
                groupMembersHasMore = false;
                showGroupCardError('Не удалось загрузить участников.');
            })
            .finally(function () {
                groupMembersBusy = false;
                maybeFillGroupMembers();
            });
    }

    function maybeFillGroupMembers() {
        const wrap = document.getElementById('groupMembersWrap');
        if (!wrap || !wrap.clientHeight) {
            return;
        }
        if (wrap.scrollHeight <= wrap.clientHeight + 24) {
            fetchGroupMembers(false);
        }
    }

    function maybeLoadMoreMembers() {
        const wrap = document.getElementById('groupMembersWrap');
        if (!wrap || !wrap.clientHeight) {
            return;
        }
        if (wrap.scrollTop + wrap.clientHeight >= wrap.scrollHeight - 80) {
            fetchGroupMembers(false);
        }
    }

    function openGroupCard() {
        if (!currentThreadId || !currentIsGroup) {
            return;
        }
        showGroupCardError('');
        groupCardModal().show();
        fetchGroupMembers(true);
    }

    function headerPeerActivate() {
        if (currentIsGroup) {
            openGroupCard();
            return;
        }
        openPeerCard();
    }

    function confirmRemoveGroupMember(userId) {
        if (typeof showConfirmDeleteModal !== 'function') {
            return;
        }
        showConfirmDeleteModal(
            'Удаление участника',
            'Удалить этого участника из группы?',
            function () {
                submitRemoveGroupMember(userId);
            }
        );
    }

    function submitRemoveGroupMember(userId) {
        if (!currentThreadId || !userId) {
            return;
        }
        fetch(threadUrl(currentThreadId, '/participants/' + encodeURIComponent(String(userId))), {
            method: 'DELETE',
            headers: headers(true),
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showGroupCardError(fieldError(res.data, 'user') || res.data.message || 'Не удалось удалить участника.');
                    return;
                }
                chatToast(res.data.message || 'Участник удалён.');
                fetchGroupMembers(true);
            })
            .catch(function () {
                showGroupCardError('Не удалось удалить участника.');
            });
    }

    function confirmLeaveGroup() {
        if (typeof showConfirmDeleteModal !== 'function') {
            return;
        }
        showConfirmDeleteModal(
            'Покинуть группу',
            'Вы уверены, что хотите покинуть группу?',
            function () {
                const confirmEl = document.getElementById('confirmDeleteModal');
                if (window.jQuery) {
                    window.jQuery(confirmEl).off('hidden.bs.modal.return');
                }
                submitLeaveGroup();
            }
        );
    }

    function submitLeaveGroup() {
        if (!currentThreadId || !me) {
            return;
        }
        const threadId = currentThreadId;
        fetch(threadUrl(threadId, '/participants/' + encodeURIComponent(String(me))), {
            method: 'DELETE',
            headers: headers(true),
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    groupCardModal().show();
                    showGroupCardError(res.data.message || 'Не удалось покинуть группу.');
                    return;
                }
                threadsCache = threadsCache.filter(function (t) {
                    return String(t.id) !== String(threadId);
                });
                renderThreads(applyThreadFilter(threadsCache));
                closeCurrentThread();
                chatToast(res.data.message || 'Вы покинули группу.');
            })
            .catch(function () {
                groupCardModal().show();
                showGroupCardError('Не удалось покинуть группу.');
            });
    }

    function resetAddGroupMembers() {
        addGroupMembersSelected = {};
        addGroupMembersBusy = false;
        showAddGroupMembersError('');
        showAddGroupMembersTeamError('');
        showAddGroupMembersSearchError('');
        const search = document.getElementById('addGroupMembersSearch');
        if (search) {
            search.value = '';
        }
        const team = document.getElementById('addGroupMembersTeamFilter');
        if (team) {
            team.value = '';
        }
        const list = document.getElementById('addGroupMembersList');
        if (list) {
            list.innerHTML = '';
        }
    }

    function addGroupMembersTeamValue() {
        const el = document.getElementById('addGroupMembersTeamFilter');
        return el ? String(el.value || '').trim() : '';
    }

    function toggleAddGroupMember(id) {
        const key = String(id);
        if (addGroupMembersSelected[key]) {
            delete addGroupMembersSelected[key];
        } else {
            addGroupMembersSelected[key] = true;
        }
        const row = document.querySelector('#addGroupMembersList [data-id="' + CSS.escape(key) + '"]');
        if (row) {
            if (addGroupMembersSelected[key]) {
                row.classList.add('is-selected');
            } else {
                row.classList.remove('is-selected');
            }
        }
    }

    function renderAddGroupMembers(list) {
        const ul = document.getElementById('addGroupMembersList');
        if (!ul) {
            return;
        }
        ul.innerHTML = '';
        if (!list.length) {
            ul.innerHTML = '<li class="chat-empty">Ничего не найдено</li>';
            return;
        }
        list.forEach(function (u) {
            const selected = !!addGroupMembersSelected[String(u.id)];
            const role = u.role_label || u.role_name || '';
            const li = document.createElement('li');
            li.className = 'contact-row group-member-row' + (selected ? ' is-selected' : '');
            li.setAttribute('data-id', String(u.id));
            li.innerHTML =
                '<div class="chat-avatar-wrap">' +
                '<img class="contact-avatar" src="' + escapeHtml(u.avatar || '/img/default-avatar.png') + '" alt="">' +
                '<span class="group-pick-check" aria-hidden="true">' + svgTick + '</span>' +
                '</div>' +
                '<div class="contact-main">' +
                '<div class="contact-name">' + escapeHtml(u.name || '') + '</div>' +
                '</div>' +
                '<div class="contact-team contact-sub">' + escapeHtml(u.team_title || '') + '</div>' +
                '<div class="contact-role contact-sub">' + escapeHtml(role) + '</div>';
            li.addEventListener('click', function () {
                toggleAddGroupMember(u.id);
            });
            ul.appendChild(li);
        });
    }

    function loadAddGroupMembers(q) {
        showAddGroupMembersError('');
        showAddGroupMembersTeamError('');
        showAddGroupMembersSearchError('');
        const params = new URLSearchParams();
        if (q) {
            params.set('q', q);
        }
        const teamId = addGroupMembersTeamValue();
        if (teamId) {
            params.set('team_id', teamId);
        }
        if (currentThreadId) {
            params.set('exclude_thread_id', String(currentThreadId));
        }
        const qs = params.toString();
        fetch(urls.users + (qs ? ('?' + qs) : ''), {
            headers: headers(false),
            credentials: 'same-origin'
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showAddGroupMembersTeamError(fieldError(res.data, 'team_id'));
                    showAddGroupMembersSearchError(fieldError(res.data, 'q'));
                    showAddGroupMembersError(fieldError(res.data, 'exclude_thread_id'));
                    renderAddGroupMembers([]);
                    return;
                }
                renderAddGroupMembers(Array.isArray(res.data) ? res.data : []);
            })
            .catch(function () {
                showAddGroupMembersError('Не удалось загрузить контакты.');
                renderAddGroupMembers([]);
            });
    }

    function openAddGroupMembers() {
        if (!groupMembersCanManage) {
            return;
        }
        resetAddGroupMembers();
        if (typeof showModalQueued === 'function') {
            showModalQueued('addGroupMembersModal');
        } else {
            addGroupMembersModal().show();
        }
        loadAddGroupMembers('');
    }

    function submitAddGroupMembers() {
        if (addGroupMembersBusy) {
            return;
        }
        const ids = Object.keys(addGroupMembersSelected).map(Number).filter(Boolean);
        if (ids.length < 1) {
            showAddGroupMembersError('Выберите хотя бы одного участника.');
            return;
        }
        addGroupMembersBusy = true;
        fetch(threadUrl(currentThreadId, '/participants'), {
            method: 'POST',
            headers: headers(true),
            credentials: 'same-origin',
            body: JSON.stringify({ user_ids: ids })
        })
            .then(function (r) {
                return r.json().then(function (data) { return { ok: r.ok, data: data }; });
            })
            .then(function (res) {
                if (!res.ok) {
                    showAddGroupMembersError(fieldError(res.data, 'user_ids') || res.data.message || 'Не удалось добавить участников.');
                    return;
                }
                addGroupMembersModal().hide();
                chatToast(res.data.message || 'Участники добавлены.');
                fetchGroupMembers(true);
            })
            .catch(function () {
                showAddGroupMembersError('Не удалось добавить участников.');
            })
            .finally(function () {
                addGroupMembersBusy = false;
            });
    }

    const addGroupMembersBtn = document.getElementById('addGroupMembersBtn');
    if (addGroupMembersBtn) {
        addGroupMembersBtn.addEventListener('click', function () {
            openAddGroupMembers();
        });
    }
    const leaveGroupBtn = document.getElementById('leaveGroupBtn');
    if (leaveGroupBtn) {
        leaveGroupBtn.addEventListener('click', function () {
            confirmLeaveGroup();
        });
    }
    const groupMembersWrap = document.getElementById('groupMembersWrap');
    if (groupMembersWrap) {
        groupMembersWrap.addEventListener('scroll', maybeLoadMoreMembers, { passive: true });
    }
    const groupMembersBody = document.getElementById('groupMembersBody');
    if (groupMembersBody) {
        groupMembersBody.addEventListener('click', function (e) {
            const removeBtn = e.target.closest ? e.target.closest('.js-remove-group-member') : null;
            if (removeBtn) {
                e.preventDefault();
                e.stopPropagation();
                confirmRemoveGroupMember(Number(removeBtn.getAttribute('data-id')));
                return;
            }
            const row = e.target.closest ? e.target.closest('tr[data-id]') : null;
            if (!row) {
                return;
            }
            openPeerCard(Number(row.getAttribute('data-id')), true);
        });
    }
    const addGroupMembersForm = document.getElementById('addGroupMembersForm');
    if (addGroupMembersForm) {
        addGroupMembersForm.addEventListener('submit', function (e) {
            e.preventDefault();
            submitAddGroupMembers();
        });
    }
    const addGroupMembersSearch = document.getElementById('addGroupMembersSearch');
    if (addGroupMembersSearch) {
        addGroupMembersSearch.addEventListener('input', function () {
            const q = this.value.trim();
            clearTimeout(addGroupMembersDebounce);
            addGroupMembersDebounce = setTimeout(function () { loadAddGroupMembers(q); }, 250);
        });
    }
    const addGroupMembersTeamFilter = document.getElementById('addGroupMembersTeamFilter');
    if (addGroupMembersTeamFilter) {
        addGroupMembersTeamFilter.addEventListener('change', function () {
            const q = document.getElementById('addGroupMembersSearch').value.trim();
            loadAddGroupMembers(q);
        });
    }
    const addGroupMembersModalEl = document.getElementById('addGroupMembersModal');
    if (addGroupMembersModalEl) {
        addGroupMembersModalEl.addEventListener('hidden.bs.modal', function () {
            resetAddGroupMembers();
        });
    }

    const peerHit = document.getElementById('threadPeerHit');
    if (peerHit) {
        peerHit.addEventListener('click', function () {
            headerPeerActivate();
        });
        peerHit.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                headerPeerActivate();
            }
        });
    }

    const deleteThreadBtn = document.getElementById('deleteThreadBtn');
    if (deleteThreadBtn) {
        deleteThreadBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            confirmDeleteThread();
        });
    }

    const mobileBack = document.getElementById('chatMobileBack');
    if (mobileBack) {
        mobileBack.addEventListener('click', function () {
            leaveMobileDialog();
        });
    }
    document.querySelectorAll('.chat-mobile-nav-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setMobileTab(btn.getAttribute('data-mobile-tab'));
        });
    });
    if (window.matchMedia) {
        const mq = window.matchMedia('(max-width: 991.98px)');
        const onMq = function () {
            placeContactsMount();
            if (!isMobileChat() && root) {
                root.classList.remove('is-dialog-open');
            }
            renderThreads(applyThreadFilter(threadsCache));
        };
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', onMq);
        } else if (typeof mq.addListener === 'function') {
            mq.addListener(onMq);
        }
    }
    placeContactsMount();

    window.KidsCrmChatRefreshInbox = loadThreads;
    window.KidsCrmChatOnInboxBump = applyInboxBump;

    function preventPageZoom(e) {
        e.preventDefault();
    }
    document.addEventListener('gesturestart', preventPageZoom, { passive: false });
    document.addEventListener('gesturechange', preventPageZoom, { passive: false });

    subscribeInbox();
    loadThreads();
    startPoll();
})();
