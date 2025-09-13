@extends('layouts.admin2')

@section('content')
    <style>
        /* ===== Левый список ===== */
        .chat-list-search {
            padding: .5rem .75rem;
            border-bottom: 1px solid #e9ecef;
        }

        .chat-list-item {
            display: flex;
            gap: .75rem;
            padding: .6rem .75rem;
            cursor: pointer;
            border-left: 4px solid transparent;
            transition: background-color .15s ease, box-shadow .15s ease, border-left-color .15s ease, transform .05s ease;
        }

        .chat-list-item:hover {
            background: rgba(243, 161, 43, 0.06);
            border-left-color: #2eaadc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        }

        .chat-list-item:active {
            transform: translateY(1px);
        }

        .chat-list-item.active {
            background: #eaf6ff;
            border-left-color: #2eaadc;
        }

        .chat-list-item.active:hover {
            background: #e2f1ff;
            border-left-color: #2eaadc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
        }

        .chat-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
        }

        .chat-li-middle {
            flex: 1;
            min-width: 0;
        }

        .chat-li-title {
            font-weight: 600;
            line-height: 1.1;
        }

        .chat-li-preview {
            font-size: .9rem;
            color: #6c757d;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            text-align: left;
        }

        .chat-li-time {
            font-size: .8rem;
            color: #6c757d;
        }

        /* ===== Правый блок ===== */
        .dialog-bg {
            background: url("/img/background-chat.jpg") repeat;
            background-size: cover;
        }

        /* ===== Строка сообщения и пузырь ===== */
        .msg-row {
            display: flex;
            width: 100%;
            margin: .25rem 0;
        }

        .msg-inner {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 100%;
        }

        .msg-bubble {
            max-width: 75%;
            padding: .6rem 3.2rem 1.4rem .9rem; /* справа место под время */
            border-radius: 16px;
            background: #ffffff;
            position: relative;
            word-break: break-word;
            box-shadow: 0 1px 0 rgba(0, 0, 0, .03);
        }

        .msg-row.msg-other .msg-bubble {
            background: #fff;
            border-bottom-left-radius: 4px;
            margin-right: auto;
        }

        .msg-row.msg-mine .msg-bubble {
            background: #c7f7c9;
            border-bottom-right-radius: 4px;
            margin-left: auto;
        }

        /* Время/чеки внутри баббла */
        .msg-meta {
            position: absolute;
            bottom: 4px;
            right: 8px;
            font-size: .7rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: .2rem;
        }

        .msg-row.msg-mine .msg-meta {
            color: #4CAF50;
        }

        .checks {
            display: inline-flex;
            gap: 2px;
            transform: translateY(1px);
        }

        .check {
            width: 14px;
            height: 14px;
            display: inline-block;
        }

        .check svg {
            width: 14px;
            height: 14px;
        }

        .chat-header-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-title {
            font-weight: 600;
        }

        .chat-sub {
            font-size: .9rem;
            color: #6c757d;
            min-height: 1.1rem;
            line-height: 1.1rem;
        }

        .chat-title {
            margin-bottom: 0.15rem;
        }

        /* ===== Модалки (единый стиль) ===== */
        .modal-search-wrap {
            position: relative;
        }

        .modal-search-wrap .form-control {
            padding-left: 2rem;
        }

        .modal-search-icon {
            position: absolute;
            top: 50%;
            left: .5rem;
            transform: translateY(-50%);
            opacity: .6;
        }

        .contact-list {
            overflow: auto;
        }

        .list-unstyled {
            margin: 0;
            padding: 0;
        }

        .list-unstyled > li {
            list-style: none;
        }

        .contact-row, .group-row {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .5rem .25rem;
            cursor: pointer;
            border-radius: 8px;
        }

        .contact-row:hover, .group-row:hover {
            background: #f5f7f9;
        }

        .contact-avatar, .group-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Заголовок строки с ролью справа */
        .row-topline {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: .5rem;
        }

        .row-role {
            font-size: .8rem;
            color: #6c757d;
            white-space: nowrap;
            flex: 0 0 auto;
        }

        .contact-name, .group-name {
            font-weight: 600;
        }

        .contact-sub, .group-sub {
            font-size: .85rem;
            color: #6c757d;
        }

        .chat-actions .btn {
            margin-left: .4rem;
        }

        .contact-list li .flex-grow-1 {
            text-align: left;
        }

        #contactsModal .modal-content {
            width: 400px;
        }

        /* ==== FIX: чекбоксы в модалке "Создать группу" скроллятся вместе со строками ==== */
        #groupUsers {
            max-height: 380px;
            overflow-y: auto;
        }

        #groupUsers .form-check-input {
            position: static !important;
            float: none !important;
            margin: 0 .5rem 0 0 !important;
            flex: 0 0 auto;
        }

        #groupInfoMembers li .flex-grow-1, #groupInfoAddResults li .flex-grow-1 {
            text-align: left;
        }

        /* Сделать модалку "Создать группу" уже */
        #groupModal .modal-dialog {
            max-width: 440px;
        }

        #groupModal .modal-content {
            max-height: 950px;
        }

        /* Левый список — поисковая выдача */
        .chat-li-middle .chat-li-preview {
            text-align: left;
        }
    </style>

    <div class="container py-3">
        <div class="row g-3">
            <!-- Лево -->
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="chat-list-search">
                        <div class="input-group input-group-sm">
                            <input type="text" id="threadSearch" class="form-control" placeholder="Поиск">
                        </div>
                    </div>
                    <div id="threads" class="list-group list-group-flush" style="overflow:auto; max-height:65vh;"></div>
                </div>
            </div>

            <!-- Право -->
            <div class="col-12 col-md-8">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="chat-header-line">
                            <div>
                                <div class="chat-title" id="threadTitle">Выберите диалог</div>
                                <div class="chat-sub">
                                    <span id="threadMembersLine" class="text-primary invisible"
                                          style="cursor:pointer;"></span>
                                </div>
                            </div>
                            <div class="chat-actions">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#contactsModal">Контакты
                                </button>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#groupModal">Создать группу
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body dialog-bg p-0 d-flex flex-column" style="height:65vh;">
                        <div id="messagesBox" class="p-3 flex-grow-1 overflow-auto">
                            <div class="text-center text-muted pt-5">Сообщения появятся здесь…</div>
                        </div>
                        <div class="border-top p-2 bg-white">
                            <form id="sendForm" class="d-flex gap-2">
                                @csrf
                                <input type="text" class="form-control" id="msgInput" placeholder="Напишите сообщение…"
                                       autocomplete="off">
                                <button class="btn btn-success" type="submit">Отправить</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модалка: Контакты -->
    <div class="modal fade" id="contactsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Контакты</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-search-wrap mb-2">
                        <svg class="modal-search-icon" width="16" height="16" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242 1.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/>
                        </svg>
                        <input type="text" id="contactsSearch" class="form-control"
                               placeholder="Поиск по имени или email">
                    </div>
                    <ul id="contactsList" class="list-unstyled contact-list"></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Модалка: Создать группу -->
    <div class="modal fade" id="groupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Создать группу</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Название группы <span class="text-danger">*</span></label>
                        <input type="text" id="groupSubject" class="form-control mb-3" maxlength="120"
                               placeholder="Например: 7Б Футбол">
                    </div>

                    <div class="modal-search-wrap mb-2">
                        <svg class="modal-search-icon" width="16" height="16" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242 1.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/>
                        </svg>
                        <input type="text" id="groupSearch" class="form-control" placeholder="Кого добавить в группу">
                    </div>

                    <ul id="groupUsers" class="list-unstyled"></ul>
                    <div class="form-text">Выберите участников (чекбоксы). Вы будете добавлены автоматически.</div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button class="btn btn-primary" id="createGroupBtn">Создать</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модалка: Информация о группе (без изменений в этой задаче) -->
    <div class="modal fade" id="groupInfoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <img id="groupInfoAvatar" src="/img/default-avatar.png"
                             style="width:56px;height:56px;border-radius:50%;object-fit:cover;" alt="">
                        <div>
                            <div class="fw-semibold" id="groupInfoTitle">Группа</div>
                            <div class="text-muted" id="groupInfoCount">0 участников</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-2">
                        <div class="modal-search-wrap">
                            <svg class="modal-search-icon" width="16" height="16" viewBox="0 0 16 16">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242 1.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/>
                            </svg>
                            <input type="text" id="groupInfoSearch" class="form-control"
                                   placeholder="Добавить участника — начните ввод">
                        </div>
                    </div>

                    <ul id="groupInfoMembers" class="list-unstyled contact-list"></ul>

                    <div id="groupInfoAddBox" class="mt-3" style="display:none;">
                        <div class="small text-muted mb-1">Нажмите на пользователя, чтобы добавить в группу</div>
                        <ul id="groupInfoAddResults" class="list-unstyled contact-list"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@push('scripts')
    <!-- 1) pusher-js -->
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <!-- 2) laravel-echo (>=1.16) -->
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

    <!-- 3) Инициализация Echo под Reverb -->
    <script>
        window.Pusher = window.Pusher || Pusher;
        if (window.Pusher) window.Pusher.logToConsole = true;

        const REVERB_KEY =
                @json(config('reverb.apps.0.key') ??
                      config('broadcasting.connections.reverb.key') ??
                      env('REVERB_APP_KEY'));

        const WS_HOST = window.location.hostname;

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: REVERB_KEY,
            wsHost: WS_HOST,
            wsPort: 443,
            wssPort: 443,
            forceTLS: true,
            enabledTransports: ['wss'],
            wsPath: '/app',
            encrypted: true,
            authEndpoint: '/broadcasting/auth',
            auth: {headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}}
        });

        try {
            const p = window.Echo.connector.pusher;
            p.connection.bind('state_change', s => console.log('[WS state]', s.previous, '→', s.current));
            p.connection.bind('error', err => console.error('[WS error]', err));
            p.connection.bind('connected', () => console.log('[WS] connected'));
            p.connection.bind('disconnected', () => console.log('[WS] disconnected'));
        } catch (e) {
            console.warn('[Echo] diag error:', e);
        }
    </script>

    <!-- 4) Логика чата (AJAX) -->
    <script>
        let currentThreadMeta = {id: null, is_group: false, member_count: 0, title: ''};

        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const me = {{ auth()->id() }};

            let threadsCache = [];
            let currentThreadId = null;
            let lastMessageId = null;


            // «оптимистичные» сообщения: tempId -> true
            const optimisticMap = new Map();

            let lastReadAtByThreadId = Object.create(null);


            // Каналы
            let threadChannel = null; // активный тред
            let inboxChannel = null; // инбокс пользователя

            // Пулы
            let safetyPoll = null;
            let threadsListPoll = null;

            function escapeHtml(t) {
                return $('<div/>').text(t ?? '').html();
            }

            function isToday(ts) {
                if (!ts) return false;
                const d = new Date(ts), n = new Date();
                return d.getFullYear() === n.getFullYear() && d.getMonth() === n.getMonth() && d.getDate() === n.getDate();
            }

            function pad(n) {
                return n < 10 ? ('0' + n) : n;
            }

            function fmtTime(ts) {
                if (!ts) return '';
                const d = new Date(ts);
                return isToday(ts) ? `${pad(d.getHours())}:${pad(d.getMinutes())}`
                    : `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${String(d.getFullYear()).slice(-2)}`;
            }

            function scrollBottom() {
                const $b = $('#messagesBox');
                $b.scrollTop($b[0].scrollHeight);
            }

            // антидубликаты по DOM
            function messageExists(id) {
                if (!id) return false;
                return $(`#messagesBox [data-mid="${CSS.escape(String(id))}"]`).length > 0;
            }

            // ===== сортировка: 1) непрочитанные сверху 2) по времени DESC
            function sortThreads(list) {
                return list.sort((a, b) => {
                    const au = a.unread_count || 0, bu = b.unread_count || 0;
                    if (au > 0 || bu > 0) {
                        if (au === 0 && bu > 0) return 1;
                        if (au > 0 && bu === 0) return -1;
                    }
                    const at = new Date(a.last_message_time || a.updated_at || 0).getTime();
                    const bt = new Date(b.last_message_time || b.updated_at || 0).getTime();
                    return bt - at;
                });
            }

            const svgOne = '<svg viewBox="0 0 24 24"><path fill="#6c757d" d="M9 16.2l-3.5-3.5-1.4 1.4L9 19 20.3 7.7l-1.4-1.4z"/></svg>';
            const svgTwo = '<svg viewBox="0 0 24 24"><path fill="#6c757d" d="M9 16.2l-3.5-3.5-1.4 1.4L9 19l6.3-6.3-1.4-1.4z"/><path fill="#6c757d" d="M19 9l-6.3 6.2-1.4-1.4L17.6 7.6z"/></svg>';

            // ===== ЛЕВЫЙ СПИСОК =====
            function renderThreads(list) {
                const $wrap = $('#threads').empty();
                if (!list.length) {
                    $wrap.append('<div class="list-group-item text-center text-muted py-4">Диалогов нет</div>');
                    return;
                }
                list.forEach(t => {
                    const active = (String(t.id) === String(currentThreadId)) ? ' active' : '';
                    const unreadRaw = t.unread_count || 0;
                    const unread = (String(t.id) === String(currentThreadId)) ? 0 : unreadRaw; // НЕ показываем в активном чате

                    const badge = unread > 0 ? `<span class="badge rounded-pill bg-primary ms-2">${unread}</span>` : '';
                    const titleHtml = `${escapeHtml(t.title)} ${badge}`;
                    const $item = $(`
<div class="chat-list-item${active}" data-id="${t.id}">
  <img class="chat-avatar" src="${escapeHtml(t.avatar)}" alt="">
  <div class="chat-li-middle">
    <div class="d-flex justify-content-between">
      <div class="chat-li-title">${titleHtml}</div>
      <div class="chat-li-time">${fmtTime(t.last_message_time || t.updated_at)}</div>
    </div>
    <div class="chat-li-preview">${escapeHtml(t.last_message || '')}</div>
  </div>
</div>
                    `).on('click', function (e) {
                        e.preventDefault();
                        openThread(t.id);
                    });
                    $wrap.append($item);
                });
            }

            function loadThreads() {
                $.ajax({
                    url: '/chat/api/threads',
                    method: 'GET',
                    success: function (res) {
                        threadsCache = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
                        sortThreads(threadsCache);
                        renderThreads(threadsCache);
                        ensureInboxSubscription();
                    }
                });
            }

            $('#threadSearch').on('input', function () {
                const q = $(this).val().toLowerCase().trim();
                if (!q) return renderThreads(sortThreads([...threadsCache]));
                const filtered = threadsCache.filter(t =>
                    (t.title && t.title.toLowerCase().includes(q)) ||
                    (t.last_message && t.last_message.toLowerCase().includes(q))
                );
                renderThreads(sortThreads(filtered));
            });

            // ===== helper: точечное обновление треда
            function updateThreadById(id, patch) {
                let updated = false;
                threadsCache = threadsCache.map(t => {
                    if (String(t.id) === String(id)) {
                        updated = true;
                        const cleaned = Object.fromEntries(Object.entries(patch).filter(([, v]) => v !== undefined));
                        return Object.assign({}, t, cleaned);
                    }
                    return t;
                });
                if (!updated) return false;
                sortThreads(threadsCache);
                renderThreads(threadsCache);
                return true;
            }


            // ===== USER канал (если бэкенд кидает thread.updated)
            const userChannel = window.Echo.private('user.' + me);
            userChannel.listen('.thread.updated', (e) => {
                const id = e.threadId ?? e.payload?.threadId ?? e.payload?.id ?? null;
                if (!id) {
                    loadThreads();
                    return;
                }
                const patch = {
                    last_message: e.payload?.last_message,
                    last_message_time: e.payload?.last_message_time,
                    updated_at: e.payload?.last_message_time || e.payload?.updated_at,
                    member_count: e.payload?.member_count,
                    is_group: e.payload?.is_group,
                    title: e.payload?.title,
                    avatar: e.payload?.avatar
                };
                if (typeof e.payload?.unread_count !== 'undefined') patch.unread_count = e.payload.unread_count;
                if (!updateThreadById(id, patch)) loadThreads();
            });

            // ===== ИНБОКС канал (для левого списка) =====
            function ensureInboxSubscription() {
                if (inboxChannel) return;
                inboxChannel = window.Echo.private('inbox.' + me);

                // Пришёл бамп инбокса (новое сообщение / обновление превью)
                inboxChannel.listen('.inbox.bump', (e) => {
                    const id     = e.thread_id ?? e.threadId;
                    const body   = e.last_message ?? e.message?.body ?? '';
                    const ts     = e.last_message_time ?? e.message?.created_at ?? e.updated_at;
                    const title  = e.title;
                    const avatar = e.avatar;

                    const unreadFromServer = typeof e.unread_count !== 'undefined' ? Number(e.unread_count) : null;
                    const incUnread        = e.increment_unread ? 1 : 0;

                    const isActive      = String(currentThreadId) === String(id);
                    const readTs        = lastReadAtByThreadId[id];
                    const isAlreadyRead = readTs && ts && (new Date(ts).getTime() <= new Date(readTs).getTime());

                    let found = false;
                    threadsCache = threadsCache.map(t => {
                        if (String(t.id) === String(id)) {
                            found = true;

                            const nextUnread = (unreadFromServer !== null)
                                ? (isActive || isAlreadyRead ? 0 : unreadFromServer)
                                : (isActive || isAlreadyRead ? 0 : (t.unread_count || 0) + incUnread);

                            return Object.assign({}, t, {
                                last_message:       body || t.last_message,
                                last_message_time:  ts   || t.last_message_time,
                                updated_at:         ts   || t.updated_at,
                                unread_count:       nextUnread,
                                title:              title  || t.title,
                                avatar:             avatar || t.avatar,
                                member_count:       typeof e.member_count !== 'undefined' ? e.member_count : t.member_count,
                                is_group:           typeof e.is_group   !== 'undefined' ? e.is_group   : t.is_group,
                                recipients:         e.recipients || t.recipients
                            });
                        }
                        return t;
                    });

                    if (!found) {
                        const nextUnread = (unreadFromServer !== null)
                            ? (isActive || isAlreadyRead ? 0 : unreadFromServer)
                            : (isActive || isAlreadyRead ? 0 : incUnread);

                        threadsCache.push({
                            id,
                            title:             title || 'Диалог',
                            avatar:            avatar || '/img/default-avatar.png',
                            last_message:      body,
                            last_message_time: ts,
                            updated_at:        ts,
                            unread_count:      nextUnread,
                            member_count:      e.member_count,
                            is_group:          e.is_group,
                            recipients:        e.recipients || []
                        });
                    }

                    sortThreads(threadsCache);
                    renderThreads(threadsCache);
                });

                // Массовая синхронизация списка тредов
                inboxChannel.listen('.inbox.sync', (e) => {
                    if (!Array.isArray(e.threads)) return;

                    const byId = Object.create(null);
                    e.threads.forEach(t => { byId[String(t.id)] = t; });

                    // Обновляем существующие
                    threadsCache = threadsCache.map(t => {
                        const fresh = byId[String(t.id)];
                        if (!fresh) return t;

                        const isActive      = String(currentThreadId) === String(t.id);
                        const ts            = fresh.last_message_time || fresh.updated_at;
                        const readTs        = lastReadAtByThreadId[t.id];
                        const isAlreadyRead = readTs && ts && (new Date(ts).getTime() <= new Date(readTs).getTime());
                        const unread        = typeof fresh.unread_count !== 'undefined' ? Number(fresh.unread_count) : (t.unread_count || 0);

                        return Object.assign({}, t, {
                            last_message:      fresh.last_message ?? t.last_message,
                            last_message_time: ts ?? t.last_message_time,
                            updated_at:        ts ?? t.updated_at,
                            unread_count:      (isActive || isAlreadyRead) ? 0 : unread,
                            title:             fresh.title  || t.title,
                            avatar:            fresh.avatar || t.avatar,
                            member_count:      typeof fresh.member_count !== 'undefined' ? fresh.member_count : t.member_count,
                            is_group:          typeof fresh.is_group   !== 'undefined' ? fresh.is_group   : t.is_group,
                            recipients:        fresh.recipients || t.recipients
                        });
                    });

                    // Добавляем новые
                    e.threads.forEach(f => {
                        if (threadsCache.find(x => String(x.id) === String(f.id))) return;

                        const isActive      = String(currentThreadId) === String(f.id);
                        const ts            = f.last_message_time || f.updated_at;
                        const readTs        = lastReadAtByThreadId[f.id];
                        const isAlreadyRead = readTs && ts && (new Date(ts).getTime() <= new Date(readTs).getTime());

                        threadsCache.push({
                            id:               f.id,
                            title:            f.title  || 'Диалог',
                            avatar:           f.avatar || '/img/default-avatar.png',
                            last_message:     f.last_message,
                            last_message_time:ts,
                            updated_at:       ts,
                            unread_count:     (isActive || isAlreadyRead) ? 0 : Number(f.unread_count || 0),
                            member_count:     f.member_count,
                            is_group:         f.is_group,
                            recipients:       f.recipients || []
                        });
                    });

                    sortThreads(threadsCache);
                    renderThreads(threadsCache);
                });
            }

            // ===== Подписка на текущий тред (активный чат) =====
            let typingTimer = null;

            function onMessageCreatedActive(e, threadId) {
                // нормализуем payload: либо e.message, либо плоский e
                const msg  = (e && e.message) ? e.message : e;
                const mid  = msg?.id;
                const body = msg?.body ?? '';
                const uid  = msg?.user_id;
                const ts   = msg?.created_at;

                if (!mid && !body) return; // защитно, если пришёл совсем пустой event

                if (uid === me) {
                    // прилетел ответ на «оптимистичное» — заменим временный узел
                    const $opt = $('#messagesBox .msg-row.msg-mine[data-temp="1"]').last();
                    if ($opt.length) {
                        $opt.attr('data-mid', String(mid)).removeAttr('data-temp').removeAttr('data-oid');
                        const $meta = $opt.find('.msg-meta');
                        $meta.find('span.time').text(fmtTime(ts));
                        $meta.find('.checks').html(`<span class="check">${svgTwo}</span>`);
                    } else {
                        if (!messageExists(mid)) appendMessage(msg, $('#messagesBox'));
                    }
                } else {
                    if (!messageExists(mid)) appendMessage(msg, $('#messagesBox'));
                }

                lastMessageId = mid;

                // синхронизируем левую колонку сразу
                updateThreadById(threadId, {
                    unread_count: 0, // активный тред — счётчик скрываем
                    last_message: body,
                    last_message_time: ts,
                    updated_at: ts
                });

                // прокрутка
                const box = $('#messagesBox')[0];
                if (box) void box.offsetHeight;
                scrollBottom();

                // пометить прочитанным, если не моё и чат активный
                if (uid !== me && String(currentThreadId) === String(threadId)) {
                    $.ajax({
                        url: '/chat/api/threads/' + currentThreadId + '/read',
                        method: 'PATCH',
                        headers: {'X-CSRF-TOKEN': csrf, 'X-Socket-Id': window.Echo.socketId()}
                    });
                    lastReadAtByThreadId[currentThreadId] = ts || new Date().toISOString();
                }
            }

            function subscribeThread(threadId) {
                if (threadChannel) {
                    try {
                        threadChannel
                            .stopListening('.message.created')
                            .stopListening('message.created')
                            .stopListening('.typing')
                            .stopListening('.thread.read');
                    } catch (e) {
                        console.warn('[thread] stopListening error:', e);
                    }
                }

                threadChannel = window.Echo.private('thread.' + threadId);

                if (typeof threadChannel.subscribed === 'function') {
                    threadChannel.subscribed(() => console.log('[thread] subscribed:', threadId));
                }
                if (typeof threadChannel.error === 'function') {
                    threadChannel.error(e => console.error('[thread] channel error:', e));
                }


                threadChannel
                // если в событии задан broadcastAs('message.created')
                    .listen('.message.created', (e) => onMessageCreatedActive(e, threadId))
                    .listen('message.created',  (e) => onMessageCreatedActive(e, threadId))
                    // дефолтный кейс Laravel без broadcastAs:
                    .listen('.MessageCreated', (e) => onMessageCreatedActive(e, threadId))
                    .listen('.App\\Events\\MessageCreated', (e) => onMessageCreatedActive(e, threadId));



                threadChannel.listen('.typing', (e) => {
                    if (e.userId === me) return;
                    const $sub = $('#threadSub');
                    if (e.isTyping) {
                        $sub.text('печатает…').show();
                        clearTimeout(typingTimer);
                        typingTimer = setTimeout(() => $sub.text('').hide(), 4000);
                    } else {
                        $sub.text('').hide();
                    }
                });

                threadChannel.listen('.thread.read', (e) => {
                    if (String(currentThreadId) === String(threadId)) {
                        $('#messagesBox .msg-row.msg-mine .checks').each(function () {
                            $(this).html(`<span class="check">${svgTwo}</span>`);
                        });
                        updateThreadById(threadId, {unread_count: 0});
                    }
                });
            }

            try {
                const p = window.Echo.connector.pusher;
                p.connection.bind('connected', () => {
                    if (currentThreadId) subscribeThread(currentThreadId);
                    ensureInboxSubscription();
                });
            } catch (e) {
                console.warn('[WS] reconnect hook error', e);
            }

            // ===== Открытие треда =====
            function openThread(threadId) {
                $.ajax({
                    url: '/chat/api/threads/' + threadId,
                    method: 'GET',
                    success: function (res) {
                        currentThreadId = res.thread.id;
                        currentThreadMeta = {
                            id: res.thread.id,
                            is_group: !!res.thread.is_group,
                            member_count: Number(res.thread.member_count || 0),
                            title: res.thread.subject || 'Диалог'
                        };
                        $('#threadTitle').text(currentThreadMeta.title);

                        const $line = $('#threadMembersLine');
                        if (currentThreadMeta.is_group) {
                            const n = currentThreadMeta.member_count;
                            const suf = (n % 10 === 1 && n % 100 !== 11) ? '' :
                                (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 'а' : 'ов');
                            $line.text(n + ' участник' + suf).removeClass('invisible');
                        } else {
                            $line.text('').addClass('invisible');
                        }

                        $('.chat-list-item').removeClass('active');
                        $(`.chat-list-item[data-id="${currentThreadId}"]`).addClass('active');

                        renderMessages(res.messages);
                        lastMessageId = res.messages.length ? res.messages[res.messages.length - 1].id : null;

                        subscribeThread(currentThreadId);

                        $.ajax({
                            url: '/chat/api/threads/' + currentThreadId + '/read',
                            method: 'PATCH',
                            headers: {'X-CSRF-TOKEN': csrf, 'X-Socket-Id': window.Echo.socketId()}
                        });
                        lastReadAtByThreadId[currentThreadId] = new Date().toISOString();


                        updateThreadById(currentThreadId, {unread_count: 0});

                        startSafetyPoll();
                        startThreadsListPoll();
                        ensureInboxSubscription();
                    }
                });
            }


            $('#threadMembersLine').off('click').on('click', function () {
                if (!currentThreadId || !currentThreadMeta.is_group) return;

                // заголовок и счетчик
                $('#groupInfoTitle').text(currentThreadMeta.title);
                const n = Number(currentThreadMeta.member_count || 0);
                const suf = (n % 10 === 1 && n % 100 !== 11) ? '' :
                    (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 'а' : 'ов');
                $('#groupInfoCount').text(n + ' участник' + suf);

                // первичная отрисовка
                $('#groupInfoMembers').empty().append('<li class="text-muted py-2 ps-2">Загрузка…</li>');
                $('#groupInfoModal').modal('show');

                // загрузить состав группы
                $.ajax({
                    url: '/chat/api/threads/' + currentThreadId + '/members',
                    method: 'GET',
                    success: function (res) {
                        const $ul = $('#groupInfoMembers').empty();
                        (res.members || []).forEach(m => {
                            const $li = $(`
<li>
  <div class="contact-row">
    <img class="contact-avatar" src="${escapeHtml(m.avatar)}" alt="">
    <div class="flex-grow-1">
      <div class="contact-name">${escapeHtml(m.name || '')}</div>
    </div>
  </div>
</li>`);
                            $ul.append($li);
                        });
                        const n2 = Number(res.member_count || (res.members || []).length || 0);
                        const suf2 = (n2 % 10 === 1 && n2 % 100 !== 11) ? '' :
                            (n2 % 10 >= 2 && n2 % 10 <= 4 && (n2 % 100 < 10 || n2 % 100 >= 20) ? 'а' : 'ов');
                        $('#groupInfoCount').text(n2 + ' участник' + suf2);
                        currentThreadMeta.member_count = n2; // обновим локально
                    }
                });
            });


            // ===== Отрисовка сообщений =====
            function renderMessages(msgs) {
                const $box = $('#messagesBox').empty();
                msgs.forEach(m => appendMessage(m, $box));
                const box = $('#messagesBox')[0];
                if (box) void box.offsetHeight;
                scrollBottom();
            }

            function appendMessage(m, $box, opts = {}) {
                const mine = m.user_id === me || opts.mine === true;
                const rowClass = mine ? 'msg-row msg-mine' : 'msg-row msg-other';
                const bubble = $('<div class="msg-bubble"></div>').html(escapeHtml(m.body));
                const meta = $('<div class="msg-meta"></div>');

                // время/чеки
                const tStr = opts.ts ? fmtTime(opts.ts) : fmtTime(m.created_at);
                meta.append(`<span class="time">${tStr}</span>`);
                if (mine) {
                    const isRead = !!m.is_read;
                    const checks = $('<span class="checks"></span>');
                    checks.append(`<span class="check">${(opts.pending ? svgOne : (isRead ? svgTwo : svgOne))}</span>`);
                    meta.append(checks);
                }
                bubble.append(meta);

                const row = $(`<div class="${rowClass}"></div>`).append($('<div class="msg-inner"></div>').append(bubble));

                if (m.id) {
                    row.attr('data-mid', String(m.id));
                } else if (opts.tempId) {
                    row.attr('data-mid', String(opts.tempId)).attr('data-temp', '1').attr('data-oid', String(opts.tempId));
                }

                $box.append(row);
                return row;
            }

            // ===== Отправка: «оптимистичное» добавление =====
            $('#sendForm').on('submit', function (e) {
                e.preventDefault();
                const id = Number(currentThreadId);
                if (!Number.isInteger(id) || id <= 0) {
                    alert('Сначала выберите диалог слева.');
                    return;
                }
                const $input = $('#msgInput');
                const text = $input.val().trim();
                if (!text) return;

                // очистить инпут и заблокировать кнопку
                $input.val('');
                const $btn = $(this).find('button[type="submit"]');
                $btn.prop('disabled', true);

                // создаем временный id и «одновременную» отрисовку справа + обновление слева
                const tempId = 'tmp-' + Date.now();
                optimisticMap.set(tempId, true);
                const nowIso = new Date().toISOString();


                updateThreadById(id, {last_message: text, last_message_time: nowIso, updated_at: nowIso});

                appendMessage({user_id: me, body: text, created_at: nowIso, is_read: false}, $('#messagesBox'), {
                    mine: true, tempId, pending: true, ts: nowIso
                });
                const box = $('#messagesBox')[0];
                if (box) void box.offsetHeight;
                scrollBottom();


                // реальный POST
                $.ajax({
                    url: '/chat/api/threads/' + id + '/messages',
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': csrf, 'X-Socket-Id': window.Echo.socketId()},
                    data: {body: text},
                    success: function (m) {
                        // если temp-строка ещё существует — заменим её данными от сервера (будет дублирующая защита из .listen)
                        const $temp = $(`#messagesBox .msg-row.msg-mine[data-oid="${CSS.escape(String(tempId))}"]`);
                        if ($temp.length) {
                            $temp.attr('data-mid', String(m.id)).removeAttr('data-temp').removeAttr('data-oid');
                            const $meta = $temp.find('.msg-meta');
                            $meta.find('span.time').text(fmtTime(m.created_at));
                            $meta.find('.checks').html(`<span class="check">${svgTwo}</span>`);
                        } else {
                            // если вдруг не нашли (например, перерисовка) — просто дорисуем
                            if (!messageExists(m.id)) appendMessage(m, $('#messagesBox'));
                        }
                        lastMessageId = m.id;
                        updateThreadById(id, {
                            last_message: m.body,
                            last_message_time: m.created_at,
                            updated_at: m.created_at
                        });
                    },
                    error: function () {
                        // восстановим текст в инпут и сообщим об ошибке
                        $('#msgInput').val(text).focus();
                        // удалим оптимистичное
                        $(`#messagesBox .msg-row[data-oid="${CSS.escape(String(tempId))}"]`).remove();
                        alert('Не удалось отправить сообщение. Проверьте соединение и попробуйте ещё раз.');
                    },
                    complete: function () {
                        optimisticMap.delete(tempId);
                        $btn.prop('disabled', false);
                    }
                });
            });

            // ===== «Печатает…» =====
            let typingSent = false;
            let typingStopTimer = null;

            $('#msgInput').on('input', function () {
                if (!currentThreadId) return;
                if (!typingSent) {
                    typingSent = true;
                    $.ajax({
                        url: '/chat/api/threads/' + currentThreadId + '/typing',
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': csrf, 'X-Socket-Id': window.Echo.socketId()},
                        data: {is_typing: 1}
                    });
                }
                clearTimeout(typingStopTimer);
                typingStopTimer = setTimeout(() => {
                    typingSent = false;
                    $.ajax({
                        url: '/chat/api/threads/' + currentThreadId + '/typing',
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': csrf, 'X-Socket-Id': window.Echo.socketId()},
                        data: {is_typing: 0}
                    });
                }, 2500);
            });

            // ===== Safety-поллер активного треда =====
            function startSafetyPoll() {
                clearInterval(safetyPoll);
                safetyPoll = setInterval(() => {
                    if (!currentThreadId || !lastMessageId) return;
                    $.ajax({
                        url: '/chat/api/threads/' + currentThreadId + '/messages',
                        method: 'GET',
                        data: {after_id: lastMessageId},
                        success: function (list) {
                            (list || []).forEach(m => {
                                if (!messageExists(m.id)) {
                                    appendMessage(m, $('#messagesBox'));
                                    lastMessageId = m.id;
                                }
                            });
                            if (list?.length) {
                                const box = $('#messagesBox')[0];
                                if (box) void box.offsetHeight;
                                scrollBottom();
                            }
                        }
                    });
                }, 7000);
            }

            // ===== Лёгкий пулл списка тредов =====
            function startThreadsListPoll() {
                clearInterval(threadsListPoll);
                threadsListPoll = setInterval(() => {
                    $.ajax({
                        url: '/chat/api/threads',
                        method: 'GET',
                        success: function (res) {
                            const fresh = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
                            const map = Object.create(null);
                            fresh.forEach(t => {
                                map[String(t.id)] = t;
                            });
                            threadsCache = threadsCache.map(t => {
                                const f = map[String(t.id)];
                                if (!f) return t;
                                return Object.assign({}, t, {
                                    last_message: f.last_message,
                                    last_message_time: f.last_message_time,
                                    updated_at: f.last_message_time || f.updated_at,
                                    unread_count: f.unread_count,
                                    title: f.title || t.title,
                                    avatar: f.avatar || t.avatar
                                });
                            });
                            fresh.forEach(f => {
                                if (!threadsCache.find(x => String(x.id) === String(f.id))) threadsCache.push(f);
                            });
                            sortThreads(threadsCache);
                            renderThreads(threadsCache);
                        }
                    });
                }, 6000);
            }

            // ====== ДЕБАУНС ======
            function makeDebounced(fn, delay) {
                let t;
                return function () {
                    const args = arguments, ctx = this;
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(ctx, args), delay);
                };
            }

            /* ---------- Контакты ---------- */

            function fmtLastSeen(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                if (isNaN(d.getTime())) return '';
                return 'был(а) в сети ' + (isToday(iso) ? `${pad(d.getHours())}:${pad(d.getMinutes())}` :
                    `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${String(d.getFullYear()).slice(-2)}`);
            }

            function renderContactsList(list) {
                const $ul = $('#contactsList').empty();
                if (!Array.isArray(list) || list.length === 0) {
                    $ul.append('<li class="text-muted text-center py-3">Ничего не найдено</li>');
                    return;
                }
                list.forEach(u => {
                    const roleText = u.role_label || u.role_name || '';
                    const lastSeen = fmtLastSeen(u.last_seen_at);
                    const $li = $(`
<li data-id="${u.id}">
  <div class="contact-row">
    <img class="contact-avatar" src="${escapeHtml(u.avatar)}" alt="">
    <div class="flex-grow-1">
      <div class="row-topline">
        <div class="contact-name">${escapeHtml(u.name || '')}</div>
        <div class="row-role">${escapeHtml(roleText)}</div>
      </div>
<div class="contact-sub">${escapeHtml(u.team_title || '')}</div>
<div class="contact-sub">${escapeHtml(lastSeen)}</div>

    </div>
  </div>
</li>`);
                    $ul.append($li);
                });
            }

            function loadContacts(q = '') {
                $.ajax({
                    url: '/chat/api/users',
                    method: 'GET',
                    data: {q: q},
                    success: function (list) {
                        renderContactsList(list);
                    }
                });
            }

            // клик по контакту — открыть/создать приватный диалог
// клик по контакту — открыть/создать приватный диалог
            $('#contactsList').on('click', '.contact-row', function () {
                const uid = Number($(this).closest('li').data('id'));
                if (!uid) return;

                // ищем, есть ли уже приватный тред с этим пользователем
                const existing = threadsCache.find(t =>
                    !t.is_group &&
                    t.recipients &&
                    t.recipients.length === 2 &&
                    t.recipients.includes(uid)
                );

                if (existing) {
                    // просто открываем существующий чат
                    $('#contactsModal').modal('hide');
                    openThread(existing.id);
                    return;
                }

                // если не нашли — создаём новый
                $.ajax({
                    url: '/chat/api/threads',
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': csrf},
                    data: {type: 'private', members: [uid]},
                    success: function (res) {
                        $('#contactsModal').modal('hide');
                        loadThreads();
                        if (res?.thread_id) openThread(res.thread_id);
                    },
                    error: function (xhr) {
                        alert('Не удалось открыть диалог: ' + (xhr.responseJSON?.message || xhr.statusText));
                    }
                });
            });

            $('#contactsModal').on('shown.bs.modal', function () {
                $('#contactsSearch').val('');
                loadContacts('');
            });
            $('#contactsSearch').on('input', makeDebounced(function () {
                loadContacts($(this).val().trim());
            }, 300));

            /* ---------- Создать группу ---------- */

            let groupSelected = new Set();

            function renderGroupUsers(list) {
                const $ul = $('#groupUsers').empty();
                if (!Array.isArray(list) || list.length === 0) {
                    $ul.append('<li class="text-muted text-center py-3">Пользователи не найдены</li>');
                    return;
                }
                list.forEach(u => {
                    const checked = groupSelected.has(u.id) ? 'checked' : '';
                    const roleText = u.role_label || u.role_name || '';
                    const lastSeen = fmtLastSeen(u.last_seen_at);
                    const $li = $(`
<li data-id="${u.id}">
  <div class="group-row">
    <input class="form-check-input" type="checkbox" value="${u.id}" ${checked}>
    <img class="group-avatar" src="${escapeHtml(u.avatar)}" alt="">
    <div class="flex-grow-1">
      <div class="row-topline">
        <div class="group-name">${escapeHtml(u.name || '')}</div>
        <div class="row-role">${escapeHtml(roleText)}</div>
      </div>
  <div class="group-sub">${escapeHtml(u.team_title || '')}</div>
<div class="group-sub">${escapeHtml(lastSeen)}</div>

    </div>
  </div>
</li>`);
                    $li.find('input[type="checkbox"]').on('change', function () {
                        const id = Number(u.id);
                        if (this.checked) groupSelected.add(id); else groupSelected.delete(id);
                    });
                    // клик по строке — переключаем чекбокс
                    $li.on('click', function (e) {
                        if ($(e.target).is('input')) return;
                        const $cb = $(this).find('input[type="checkbox"]');
                        $cb.prop('checked', !$cb.prop('checked')).trigger('change');
                    });
                    $ul.append($li);
                });
            }

            function loadGroupUsers(q = '') {
                $.ajax({
                    url: '/chat/api/users',
                    method: 'GET',
                    data: {q: q},
                    success: function (list) {
                        renderGroupUsers(list);
                    }
                });
            }

            $('#groupModal').on('shown.bs.modal', function () {
                $('#groupSubject').val('');
                $('#groupSearch').val('');
                groupSelected = new Set();
                loadGroupUsers('');
            });

            $('#groupSearch').on('input', makeDebounced(function () {
                loadGroupUsers($(this).val().trim());
            }, 300));

            $('#createGroupBtn').on('click', function (e) {
                e.preventDefault();
                const subject = ($('#groupSubject').val() || '').trim();
                if (!subject) {
                    alert('Введите название группы');
                    return;
                }
                const members = Array.from(groupSelected);
                if (members.length < 1) {
                    alert('Выберите хотя бы одного участника');
                    return;
                }

                const $btn = $(this).prop('disabled', true);
                $.ajax({
                    url: '/chat/api/threads',
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': csrf},
                    data: {type: 'group', subject: subject, members: members},
                    success: function (res) {
                        $('#groupModal').modal('hide');
                        loadThreads();
                        if (res?.thread_id) openThread(res.thread_id);
                    },
                    error: function (xhr) {
                        alert('Не удалось создать группу: ' + (xhr.responseJSON?.message || xhr.statusText));
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // ===== Инициализация =====
            try {
                const p = window.Echo.connector.pusher;
                p.connection.bind('state_change', s => console.log('[WS state]', s.previous, '→', s.current));
                p.connection.bind('error', err => console.error('[WS error]', err));
            } catch (e) {
                console.warn('[WS] bind error', e);
            }

            loadThreads();
            startThreadsListPoll();
        })();
    </script>
@endpush
