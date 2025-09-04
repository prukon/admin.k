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
        }

        .chat-list-item:hover {
            background: #f8f9fa;
        }

        .chat-list-item.active {
            background: #eaf6ff;
            border-left-color: #2eaadc;
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
        }

        .chat-li-time {
            font-size: .8rem;
            color: #6c757d;
        }

        /* ===== Правый блок ===== */
        .dialog-bg {
            background: #e6ffe8;
        }

        /* ===== Строка сообщения и пузырь ===== */
        .msg-row {
            display: flex;
            width: 100%; /* ВАЖНО: строка на всю ширину */
            margin: .25rem 0;
        }

        .msg-inner {
            display: flex;
            flex-direction: column; /* пузырь + мета в колонку */
            width: 100%; /* ВАЖНО: внутренняя обёртка на всю ширину */
            max-width: 100%;
        }

        .msg-bubble {
            max-width: 75%; /* 3/4 ширины всей строки */
            padding: .6rem .9rem;
            border-radius: 16px;
            background: #ffffff;
            position: relative;
            word-break: break-word; /* НЕ break-all, чтобы не ломать «привет» на буквы */
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

        .msg-meta {
            display: flex;
            align-items: center;
            gap: .25rem;
            font-size: .75rem;
            color: #6c757d;
            margin-top: 1.35rem;
        }

        .msg-row.msg-other .msg-meta {
            align-self: flex-start;
        }

        .msg-row.msg-mine .msg-meta {
            align-self: flex-end;
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
            /*max-height:420px;*/
            overflow: auto;
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

        .contact-name, .group-name {
            font-weight: 600;
        }

        .contact-sub, .group-sub {
            font-size: .85rem;
            color: #6c757d;
        }

        .list-unstyled {
            margin: 0;
            padding: 0;
        }

        .list-unstyled > li {
            list-style: none;
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

        #groupUsers .group-row {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .5rem .25rem;
            border-radius: 8px;
            cursor: pointer;
        }

        #groupUsers .group-row:hover {
            background: #f5f7f9;
        }

        /* ключевое: отменяем любые позиционирования/float у bootstrap */
        #groupUsers .form-check-input {
            position: static !important;
            float: none !important;
            margin: 0 .5rem 0 0 !important;
            flex: 0 0 auto; /* не растягивать */
        }

        /* чтобы текст не наползал на чекбокс и всё было слева */
        #groupUsers .group-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        #groupUsers .group-name {
            font-weight: 600;
        }

        #groupUsers .group-sub {
            font-size: .85rem;
            color: #6c757d;
        }


        #groupModal .modal-content {
            max-height: 950px;
        }

        /* ===== Правый блок ===== */
        .dialog-bg {
            background: url("/img/background-chat.jpg") repeat; /* путь см. ниже */
            background-size: cover; /* или contain / auto — под твой вкус */
        }

        .msg-bubble {
            max-width: 75%;
            padding: .6rem 3.2rem 1.4rem .9rem; /* справа больше отступа под время */
            border-radius: 16px;
            background: #ffffff;
            position: relative; /* для абсолютного позиционирования времени */
            word-break: break-word;
            box-shadow: 0 1px 0 rgba(0, 0, 0, .03);
        }

        /* Время внутри баббла */
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

        /* для моих сообщений галочки идут рядом со временем */
        .msg-row.msg-mine .msg-meta {
            color: #4CAF50; /* можно другой цвет для своих */
        }

        .chat-li-middle .chat-li-preview {
            text-align: left;
        }

        #groupInfoMembers li .flex-grow-1 {
            text-align: left;
        }

        #groupInfoAddResults li .flex-grow-1 {
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
                            {{--<span class="input-group-text">🔎</span>--}}
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
                            {{--<div>--}}
                            {{--<div class="chat-title" id="threadTitle">Выберите диалог</div>--}}
                            {{--<div class="chat-sub" id="threadSub">&nbsp;</div>--}}
                            {{--</div>--}}

                            <div>
                                <div class="chat-title" id="threadTitle">Выберите диалог</div>
                                <!-- строка под заголовком: тут будет "3 участника", кликабельна -->
                                <div class="chat-sub">
                                    <span id="threadMembersLine" class="text-primary"
                                          style="cursor:pointer; display:none;"></span>
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

                    <ul id="groupUsers" class="list-unstyled" style="max-height:600px; overflow:auto;"></ul>
                    <div class="form-text">Выберите участников (чекбоксы). Вы будете добавлены автоматически.</div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button class="btn btn-primary" id="createGroupBtn">Создать</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Модалка: Информация о группе -->
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
        // pusher-js должен быть доступен глобально
        window.Pusher = window.Pusher || Pusher;
        // включим логи (потом можно выключить)
        if (window.Pusher) window.Pusher.logToConsole = true;

        // ключ берём из конфига reverb (v1.5.1 хранит тут)
        const REVERB_KEY =
        @json(config('reverb.apps.apps.0.key')) ??
        @json(config('broadcasting.connections.reverb.key')); // запасной путь

        console.log('[Echo] key =', REVERB_KEY);

        const WS_HOST = window.location.hostname; // браузер сам даст punycode: test.xn--f1ahbpis.online
        console.log('[WS_HOST]', WS_HOST);

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: REVERB_KEY,
            // wsHost: window.location.hostname,
            // wsHost: 'test.xn--f1ahbpis.online',
            wsHost: WS_HOST,
            wsPort: 443,
            wssPort: 443,
            forceTLS: true,
            enabledTransports: ['wss'],   // ✅ оставляем только wss
            wsPath: '/app',           // <-- ДОБАВЬ ЭТО

            encrypted: true,              // ✅ добавляем это
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            }
        })

        try {
            const p = window.Echo.connector.pusher;
            console.log('[Echo] connector:', window.Echo?.connector);
            console.log('[Pusher] config:', p?.config);
            console.log('[Pusher] ws options:', p?.connection?.options);

            // состояния сокета
            p.connection.bind('state_change', s => console.log('[WS state]', s.previous, '→', s.current));
            p.connection.bind('error', err => console.error('[WS error]', err));
            p.connection.bind('connected', () => console.log('[WS] connected'));
            p.connection.bind('disconnected', () => console.log('[WS] disconnected'));

            // логируем авторизацию приватных каналов 
            const _authorize = p.config.authorizer;
            if (_authorize) {
                console.log('[Auth] authorizer present');
            }
        } catch (e) {
            console.warn('[Echo] diag error:', e);
        }


        // полезные логи состояния сокета
        const p = window.Echo.connector.pusher;
        p.connection.bind('state_change', s => console.log('[WS state]', s.previous, '→', s.current));
        p.connection.bind('error', err => console.error('[WS error]', err));
    </script>


    <script>
        let currentThreadMeta = {id: null, is_group: false, member_count: 0, title: ''};

        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const me = {{ auth()->id() }};
            let threadsCache = [];
            let currentThreadId = null;
            let lastMessageId = null;

            function escapeHtml(t) {
                return $('<div/>').text(t ?? '').html();
            }

            function debounce(fn, ms) {
                let t;
                return function () {
                    const a = arguments, c = this;
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(c, a), ms || 300);
                }
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
                return isToday(ts) ? (pad(d.getHours()) + ':' + pad(d.getMinutes())) : (pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + String(d.getFullYear()).slice(-2));
            }

            function scrollBottom() {
                const $b = $('#messagesBox');
                $b.scrollTop($b[0].scrollHeight);
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
                    const active = (t.id === currentThreadId) ? ' active' : '';
                    const unread = t.unread_count || 0;
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
                    url: '/chat/api/threads', method: 'GET',
                    success: function (res) {
                        threadsCache = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
                        threadsCache.sort((a, b) => new Date(b.last_message_time || b.updated_at) - new Date(a.last_message_time || a.updated_at));
                        renderThreads(threadsCache);
                    }
                });
            }

            $('#threadSearch').on('input', function () {
                const q = $(this).val().toLowerCase().trim();
                if (!q) return renderThreads(threadsCache);
                const filtered = threadsCache.filter(t =>
                    (t.title && t.title.toLowerCase().includes(q)) ||
                    (t.last_message && t.last_message.toLowerCase().includes(q))
                );
                renderThreads(filtered);
            });

            // ===== Персональный канал: обновления списка тредов
            const userChannel = window.Echo.private('user.' + me);
            userChannel.listen('.thread.updated', (e) => {
                const id = e.threadId ?? e.payload?.threadId ?? e.payload?.id ?? null;
                if (!id) {
                    loadThreads();
                    return;
                }

                let exists = false;
                threadsCache = (threadsCache || []).map(t => {
                    if (t.id === id) {
                        exists = true;
                        return Object.assign({}, t, {
                            last_message: e.payload.last_message || t.last_message,
                            last_message_time: e.payload.last_message_time || t.last_message_time,
                            updated_at: e.payload.last_message_time || t.updated_at,
                            member_count: e.payload.member_count ?? t.member_count,
                            is_group: e.payload.is_group ?? t.is_group,
                            title: e.payload.title || t.title,
                        });
                    }
                    return t;
                });
                if (!exists) {
                    loadThreads();
                    return;
                }

                threadsCache.sort((a, b) => new Date(b.last_message_time || b.updated_at) - new Date(a.last_message_time || a.updated_at));
                renderThreads(threadsCache);
            });

            // ===== Подписка на текущий тред
            let threadChannel = null;
            let typingTimer = null;

            function subscribeThread2(threadId) {
                if (threadChannel) {
                    threadChannel.stopListening('.message.created')
                        .stopListening('.typing')
                        .stopListening('.thread.read');
                }
                threadChannel = window.Echo.private('thread.' + threadId);

                threadChannel.subscribed(() => console.log('[thread] subscribed:', threadId))
                    .error(e => console.error('[thread] channel error:', e));

// Ловим вообще все события этого канала
                if (threadChannel.subscription) {
                    // pusher-js >=8
                    threadChannel.subscription.bind_global((eventName, data) => {
                        console.log('[thread GLOBAL]', eventName, data);
                    });
                } else if (threadChannel.pusher) {
                    // старый доступ
                    threadChannel.pusher.channel('private-thread.' + threadId)
                        ?.bind_global((eventName, data) => console.log('[thread GLOBAL]', eventName, data));
                }

                threadChannel.listen('.message.created', (e) => {
                    const mid = e?.message?.id;
                    const body = e?.message?.body ?? '';
                    const uid = e?.message?.user_id;

                    if (currentThreadId === threadId) {
                        appendMessage(e.message, $('#messagesBox'));
                        lastMessageId = mid;
                        scrollBottom();

                        // сразу пометим прочитанное
                        $.ajax({
                            url: '/chat/api/threads/' + currentThreadId + '/read',
                            method: 'PATCH',
                            headers: {'X-CSRF-TOKEN': csrf}
                        });

                        // сбросить badge
                        threadsCache = threadsCache.map(t => t.id === threadId ? Object.assign({}, t, {
                            unread_count: 0,
                            last_message: body,
                            last_message_time: e.message.created_at,
                            updated_at: e.message.created_at
                        }) : t);
                        threadsCache.sort((a, b) => new Date(b.last_message_time || b.updated_at) - new Date(a.last_message_time || a.updated_at));
                        renderThreads(threadsCache);
                        return;
                    }

                    // Если неактивный тред — увеличим непрочитанные
                    threadsCache = threadsCache.map(t => {
                        if (t.id === threadId) {
                            const inc = (uid !== me) ? 1 : 0;
                            return Object.assign({}, t, {
                                last_message: body,
                                last_message_time: e.message.created_at,
                                updated_at: e.message.created_at,
                                unread_count: (t.unread_count || 0) + inc
                            });
                        }
                        return t;
                    });
                    threadsCache.sort((a, b) => new Date(b.last_message_time || b.updated_at) - new Date(a.last_message_time || a.updated_at));
                    renderThreads(threadsCache);
                });

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
                    if (currentThreadId === threadId) {
                        $('#messagesBox .msg-row.msg-mine .checks').each(function () {
                            $(this).html(`<span class="check">${svgTwo}</span>`);
                        });
                        threadsCache = threadsCache.map(t => t.id === threadId ? Object.assign({}, t, {unread_count: 0}) : t);
                        renderThreads(threadsCache);
                    }
                });
            }

            function subscribeThread(threadId) {
                if (threadChannel) {
                    try {
                        threadChannel
                            .stopListening('.message.created')
                            .stopListening('.typing')
                            .stopListening('.thread.read');
                    } catch (e) {
                        console.warn('[thread] stopListening error:', e);
                    }
                }

                console.log('[thread] subscribing to', threadId);
                threadChannel = window.Echo.private('thread.' + threadId);

                if (typeof threadChannel.subscribed === 'function') {
                    threadChannel.subscribed(() => console.log('[thread] subscribed:', threadId));
                } else {
                    console.warn('[thread] no .subscribed() on channel API');
                }
                if (typeof threadChannel.error === 'function') {
                    threadChannel.error(e => console.error('[thread] channel error:', e));
                }

                // Глобально ловим все события этого канала
                try {
                    if (threadChannel.subscription && typeof threadChannel.subscription.bind_global === 'function') {
                        threadChannel.subscription.bind_global((eventName, data) => {
                            console.log('[thread GLOBAL]', eventName, data);
                        });
                    } else if (window.Echo?.connector?.pusher?.channel) {
                        const raw = window.Echo.connector.pusher.channel('private-thread.' + threadId);
                        if (raw?.bind_global) {
                            raw.bind_global((eventName, data) => {
                                console.log('[thread GLOBAL]', eventName, data);
                            });
                        }
                    }
                } catch (e) {
                    console.warn('[thread] bind_global setup error:', e);
                }

                threadChannel.listen('.message.created', (e) => {
                    console.log('[thread] .message.created', e);
                    const mid = e?.message?.id;
                    const body = e?.message?.body ?? '';
                    const uid = e?.message?.user_id;

                    if (currentThreadId === threadId) {
                        appendMessage(e.message, $('#messagesBox'));
                        lastMessageId = mid;
                        scrollBottom();

                        $.ajax({
                            url: '/chat/api/threads/' + currentThreadId + '/read',
                            method: 'PATCH',
                            headers: {'X-CSRF-TOKEN': csrf}
                        });

                        threadsCache = threadsCache.map(t => t.id === threadId ? Object.assign({}, t, {
                            unread_count: 0,
                            last_message: body,
                            last_message_time: e.message.created_at,
                            updated_at: e.message.created_at
                        }) : t);
                        threadsCache.sort((a, b) => new Date(b.last_message_time || b.updated_at) - new Date(a.last_message_time || a.updated_at));
                        renderThreads(threadsCache);
                        return;
                    }

                    threadsCache = threadsCache.map(t => {
                        if (t.id === threadId) {
                            const inc = (uid !== me) ? 1 : 0;
                            return Object.assign({}, t, {
                                last_message: body,
                                last_message_time: e.message.created_at,
                                updated_at: e.message.created_at,
                                unread_count: (t.unread_count || 0) + inc
                            });
                        }
                        return t;
                    });
                    threadsCache.sort((a, b) => new Date(b.last_message_time || b.updated_at) - new Date(a.last_message_time || a.updated_at));
                    renderThreads(threadsCache);
                });

                threadChannel.listen('.typing', (e) => {
                    console.log('[thread] .typing', e);
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
                    console.log('[thread] .thread.read', e);
                    if (currentThreadId === threadId) {
                        $('#messagesBox .msg-row.msg-mine .checks').each(function () {
                            $(this).html(`<span class="check">${svgTwo}</span>`);
                        });
                        threadsCache = threadsCache.map(t => t.id === threadId ? Object.assign({}, t, {unread_count: 0}) : t);
                        renderThreads(threadsCache);
                    }
                });
            }


            function openThread(threadId) {
                $.ajax({
                    url: '/chat/api/threads/' + threadId, method: 'GET',
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
                            const suf = (n % 10 === 1 && n % 100 !== 11) ? '' : (n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 'а' : 'ов');
                            $line.text(n + ' участник' + suf).show();
                        } else {
                            $line.hide().text('');
                        }

                        $('.chat-list-item').removeClass('active');
                        $(`.chat-list-item[data-id="${currentThreadId}"]`).addClass('active');

                        renderMessages(res.messages);
                        lastMessageId = res.messages.length ? res.messages[res.messages.length - 1].id : null;

                        subscribeThread(currentThreadId);

                        $.ajax({
                            url: '/chat/api/threads/' + currentThreadId + '/read',
                            method: 'PATCH',
                            headers: {'X-CSRF-TOKEN': csrf}
                        });

                        threadsCache = threadsCache.map(t => t.id === currentThreadId ? Object.assign({}, t, {unread_count: 0}) : t);
                        renderThreads(threadsCache);
                    }
                });
            }

            // ===== Отрисовка сообщений (без fetch; только AJAX)
            function renderMessages(msgs) {
                const $box = $('#messagesBox').empty();
                msgs.forEach(m => appendMessage(m, $box));
                scrollBottom();
            }

            function appendMessage(m, $box) {
                const mine = m.user_id === me;
                const rowClass = mine ? 'msg-row msg-mine' : 'msg-row msg-other';
                const bubble = $('<div class="msg-bubble"></div>').html(escapeHtml(m.body));
                const meta = $('<div class="msg-meta"></div>');
                meta.append(`<span>${fmtTime(m.created_at)}</span>`);
                if (mine) {
                    const isRead = !!m.is_read;
                    const checks = $('<span class="checks"></span>');
                    checks.append(`<span class="check">${isRead ? svgTwo : svgOne}</span>`);
                    meta.append(checks);
                }
                bubble.append(meta);
                const row = $(`<div class="${rowClass}"></div>`).append($('<div class="msg-inner"></div>').append(bubble));
                $box.append(row);
            }

            // ===== Отправка (AJAX)
            $('#sendForm').on('submit', function (e) {
                e.preventDefault();
                const id = Number(currentThreadId);
                if (!Number.isInteger(id) || id <= 0) {
                    alert('Сначала выберите диалог слева.');
                    return;
                }
                const text = $('#msgInput').val().trim();
                if (!text) return;
                $.ajax({
                    url: '/chat/api/threads/' + id + '/messages',
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': csrf},
                    data: {body: text},
                    success: function (m) {
                        $('#msgInput').val('');
                        appendMessage(m, $('#messagesBox'));
                        lastMessageId = m.id;
                        scrollBottom();

                        threadsCache = threadsCache.map(t => {
                            if (t.id === id) {
                                return Object.assign({}, t, {
                                    last_message: m.body,
                                    last_message_time: m.created_at,
                                    updated_at: m.created_at
                                });
                            }
                            return t;
                        });
                        threadsCache.sort((a, b) => new Date(b.last_message_time || b.updated_at) - new Date(a.last_message_time || a.updated_at));
                        renderThreads(threadsCache);
                    }
                });
            });

            // ===== «Печатает…» (AJAX, как просил)
            let typingSent = false;
            let typingStopTimer = null;

            $('#msgInput').on('input', function () {
                if (!currentThreadId) return;
                if (!typingSent) {
                    typingSent = true;
                    $.ajax({
                        url: '/chat/api/threads/' + currentThreadId + '/typing',
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': csrf},
                        data: {is_typing: 1}
                    });
                }
                clearTimeout(typingStopTimer);
                typingStopTimer = setTimeout(() => {
                    typingSent = false;
                    $.ajax({
                        url: '/chat/api/threads/' + currentThreadId + '/typing',
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': csrf},
                        data: {is_typing: 0}
                    });
                }, 2500);
            });

            // ===== Контакты/Группы — твой существующий код оставляю без изменений (AJAX на /chat/api/*)

            loadThreads();
        })();
    </script>
@endpush

