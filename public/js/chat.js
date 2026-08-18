(function () {
    const root = document.getElementById('chatApp');
    if (!root) {
        return;
    }

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrf = csrfMeta ? csrfMeta.content : '';
    const me = Number(root.getAttribute('data-me') || 0);
    const urls = {
        threads: root.getAttribute('data-threads-url'),
        storeThread: root.getAttribute('data-store-thread-url'),
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
    let lastMessageId = null;
    let loadingOlder = false;
    let hasOlder = true;
    let threadChannel = null;
    let inboxBound = false;
    let pollTimer = null;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
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
        if (!xhrJson || !xhrJson.errors || !xhrJson.errors[field]) {
            return xhrJson && xhrJson.message ? String(xhrJson.message) : '';
        }
        const val = xhrJson.errors[field];
        return Array.isArray(val) ? String(val[0] || '') : String(val);
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

    function setUnreadBadge(total) {
        if (typeof window.KidsCrmChatSetUnread === 'function') {
            window.KidsCrmChatSetUnread(total);
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

    function renderThreads(list) {
        const wrap = document.getElementById('threads');
        wrap.innerHTML = '';
        if (!list.length) {
            wrap.innerHTML = '<div class="chat-empty">Диалогов нет</div>';
            return;
        }
        list.forEach(function (t) {
            const active = String(t.id) === String(currentThreadId) ? ' active' : '';
            const unread = String(t.id) === String(currentThreadId) ? 0 : (t.unread_count || 0);
            const badge = unread > 0 ? '<span class="badge rounded-pill bg-primary ms-2">' + unread + '</span>' : '';
            const onlineDot = t.peer_is_online
                ? '<span class="chat-online-dot" title="Онлайн"></span>'
                : '';
            const ticks = t.last_message_is_mine ? ticksHtml(!!t.last_message_is_read) : '';
            const item = document.createElement('div');
            item.className = 'chat-list-item' + active;
            item.setAttribute('data-id', String(t.id));
            item.innerHTML =
                '<div class="chat-avatar-wrap">' +
                '<img class="chat-avatar" src="' + escapeHtml(t.avatar || '/img/default-avatar.png') + '" alt="">' +
                onlineDot +
                '</div>' +
                '<div class="chat-li-middle">' +
                '<div class="d-flex justify-content-between">' +
                '<div class="chat-li-title">' + escapeHtml(t.title || 'Диалог') + badge + '</div>' +
                '<div class="chat-li-time">' + ticks + escapeHtml(fmtTime(t.last_message_time)) + '</div>' +
                '</div>' +
                '<div class="chat-li-preview">' + escapeHtml(t.last_message || '') + '</div>' +
                '</div>';
            item.addEventListener('click', function () {
                openThread(t.id);
            });
            wrap.appendChild(item);
        });
    }

    function upsertThread(patch) {
        const clean = {};
        Object.keys(patch || {}).forEach(function (key) {
            if (typeof patch[key] !== 'undefined') {
                clean[key] = patch[key];
            }
        });
        patch = clean;
        if (patch.peer_id) {
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
                title: 'Диалог',
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
                || (t.last_message && t.last_message.toLowerCase().indexOf(q) !== -1);
        });
    }

    function scrollBottom() {
        const box = document.getElementById('messagesBox');
        box.scrollTop = box.scrollHeight;
    }

    function messageExists(id) {
        return !!document.querySelector('#messagesBox [data-mid="' + CSS.escape(String(id)) + '"]');
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

        row.innerHTML =
            '<div class="msg-inner"><div class="msg-bubble">' + escapeHtml(m.body) +
            '<div class="msg-meta"><span class="time">' + escapeHtml(fmtTime(m.created_at)) + '</span>' + checks + '</div>' +
            '</div></div>';

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
        document.getElementById('msgInput').disabled = !on;
        document.querySelector('#sendForm button[type="submit"]').disabled = !on;
    }

    function showMsgError(text) {
        document.getElementById('msgBodyError').textContent = text || '';
    }

    function showContactsError(text) {
        document.getElementById('contactsError').textContent = text || '';
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

    function openThread(threadId) {
        fetch(threadUrl(threadId), { headers: headers(false), credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw r;
                return r.json();
            })
            .then(function (res) {
                if (!res || !res.thread || !res.thread.id) {
                    throw new Error('bad-thread-payload');
                }
                currentThreadId = res.thread.id;
                hasOlder = (res.messages || []).length >= 40;
                document.getElementById('threadTitle').textContent = res.thread.title || 'Диалог';
                const av = document.getElementById('threadAvatar');
                av.src = res.thread.avatar || '/img/default-avatar.png';
                av.style.display = '';
                setComposerEnabled(true);
                showMsgError('');

                const box = document.getElementById('messagesBox');
                box.innerHTML = '';
                (res.messages || []).forEach(function (m) { appendMessage(m); });
                if (!(res.messages || []).length) {
                    box.innerHTML = '<div class="chat-empty">Напишите первое сообщение</div>';
                }
                lastMessageId = (res.messages || []).length ? res.messages[res.messages.length - 1].id : null;
                scrollBottom();
                upsertThread({ id: currentThreadId, unread_count: 0, title: res.thread.title, avatar: res.thread.avatar });
                if (typeof res.unread_total !== 'undefined') {
                    setUnreadBadge(res.unread_total);
                }
                try {
                    subscribeThread(currentThreadId);
                } catch (e) {}
                startPoll();
            })
            .catch(function () {
                if (String(currentThreadId) === String(threadId)) {
                    return;
                }
                showMsgError('Не удалось открыть диалог.');
            });
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
            })
            .finally(function () { loadingOlder = false; });
    }

    function subscribeThread(threadId) {
        if (!window.Echo) return;
        try {
            if (threadChannel) {
                try {
                    threadChannel.stopListening('.message.created').stopListening('.thread.read');
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
        const isActive = String(currentThreadId) === String(e.thread_id);
        upsertThread({
            id: e.thread_id,
            title: e.title,
            avatar: e.avatar,
            peer_id: e.peer_id,
            peer_is_online: e.peer_is_online,
            last_message: e.last_message,
            last_message_time: e.last_message_time,
            last_message_is_mine: e.last_message_is_mine,
            last_message_is_read: e.last_message_is_read,
            unread_count: isActive ? 0 : Number(e.unread_count || 0)
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

    function socketState() {
        const pusher = window.Echo && window.Echo.connector && window.Echo.connector.pusher;
        return pusher && pusher.connection ? String(pusher.connection.state || '') : '';
    }

    function startPoll() {
        clearInterval(pollTimer);
        pollTimer = setInterval(function () {
            subscribeInbox();
            if (socketState() === 'connected') {
                return;
            }
            if (!currentThreadId) return;
            if (lastMessageId) {
                fetch(threadUrl(currentThreadId, '/messages?after_id=' + lastMessageId), {
                    headers: headers(false),
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(function (list) {
                        const rows = Array.isArray(list) ? list : [];
                        let sawPeer = false;
                        rows.forEach(function (m) {
                            if (!messageExists(m.id)) {
                                appendMessage(m);
                                lastMessageId = m.id;
                                upsertThread({
                                    id: currentThreadId,
                                    unread_count: 0,
                                    last_message: m.body,
                                    last_message_time: m.created_at,
                                    last_message_is_mine: Number(m.user_id) === me,
                                    last_message_is_read: Number(m.user_id) === me ? !!m.is_read : null
                                });
                                if (Number(m.user_id) !== me) {
                                    sawPeer = true;
                                }
                            }
                        });
                        if (rows.length) scrollBottom();
                        syncMineReadStatus(rows);
                        if (sawPeer) {
                            markThreadRead(currentThreadId);
                        }
                    })
                    .catch(function () {});
            }
            if (document.querySelector('#messagesBox .msg-row.msg-mine[data-read="0"]')) {
                fetch(threadUrl(currentThreadId, '/messages'), {
                    headers: headers(false),
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json(); })
                    .then(syncMineReadStatus)
                    .catch(function () {});
            }
        }, 1000);
    }

    document.getElementById('threadSearch').addEventListener('input', function () {
        renderThreads(applyThreadFilter(threadsCache));
    });

    document.getElementById('messagesBox').addEventListener('scroll', function () {
        if (this.scrollTop < 40) loadOlder();
    });

    document.getElementById('sendForm').addEventListener('submit', function (e) {
        e.preventDefault();
        showMsgError('');
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
        input.value = '';
        const tempId = 'tmp-' + Date.now();
        const now = new Date();
        const nowSql = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
            + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        appendMessage({ user_id: me, body: text, created_at: nowSql, is_read: false }, { mine: true, tempId: tempId, pending: true });
        upsertThread({
            id: id,
            last_message: text,
            last_message_time: nowSql,
            last_message_is_mine: true,
            last_message_is_read: false,
            unread_count: 0
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
                } else if (!messageExists(m.id)) {
                    appendMessage(m);
                }
                lastMessageId = m.id;
                upsertThread({
                    id: id,
                    last_message: m.body,
                    last_message_time: m.created_at,
                    last_message_is_mine: true,
                    last_message_is_read: !!m.is_read,
                    unread_count: 0
                });
            })
            .catch(function () {
                showMsgError('Не удалось отправить сообщение. Проверьте соединение.');
                input.value = text;
            })
            .finally(function () {
                btn.disabled = false;
                input.focus();
            });
    });

    function contactsModal() {
        return bootstrap.Modal.getOrCreateInstance(document.getElementById('contactsModal'));
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
                '<div class="flex-grow-1">' +
                '<div class="d-flex justify-content-between gap-2">' +
                '<div class="contact-name">' + escapeHtml(u.name || '') + '</div>' +
                '<div class="contact-sub">' + escapeHtml(role) + '</div>' +
                '</div>' +
                (parentFio ? '<div class="contact-parent">' + escapeHtml(parentFio) + '</div>' : '') +
                (team ? '<div class="contact-sub">' + escapeHtml(team) + '</div>' : '') +
                '</div></div>';
            li.querySelector('.contact-row').addEventListener('click', function () {
                startDialog(Number(u.id));
            });
            ul.appendChild(li);
        });
    }

    function loadContacts(q) {
        const url = urls.users + (q ? ('?q=' + encodeURIComponent(q)) : '');
        fetch(url, { headers: headers(false), credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (list) { renderContacts(Array.isArray(list) ? list : []); })
            .catch(function () { renderContacts([]); });
    }

    let startDialogBusy = false;

    function startDialog(userId) {
        if (startDialogBusy) {
            return;
        }
        showContactsError('');
        const existing = threadsCache.find(function (t) {
            return Number(t.peer_id) === Number(userId);
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

    let contactsDebounce = null;
    document.getElementById('openContactsBtn').addEventListener('click', function () {
        showContactsError('');
        document.getElementById('contactsSearch').value = '';
        loadContacts('');
        contactsModal().show();
    });
    document.getElementById('contactsSearch').addEventListener('input', function () {
        const q = this.value.trim();
        clearTimeout(contactsDebounce);
        contactsDebounce = setTimeout(function () { loadContacts(q); }, 250);
    });

    window.KidsCrmChatRefreshInbox = loadThreads;
    window.KidsCrmChatOnInboxBump = applyInboxBump;

    subscribeInbox();
    loadThreads();
    startPoll();
})();
