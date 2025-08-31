@extends('layouts.admin2')

@section('content')
    <style>
        /* ===== Левый список ===== */
        .chat-list-search { padding:.5rem .75rem; border-bottom:1px solid #e9ecef; }
        .chat-list-item { display:flex; gap:.75rem; padding:.6rem .75rem; cursor:pointer; border-left:4px solid transparent; }
        .chat-list-item:hover { background:#f8f9fa; }
        .chat-list-item.active { background:#eaf6ff; border-left-color:#2eaadc; }
        .chat-avatar { width:42px; height:42px; border-radius:50%; object-fit:cover; }
        .chat-li-middle { flex:1; min-width:0; }
        .chat-li-title { font-weight:600; line-height:1.1; }
        .chat-li-preview { font-size:.9rem; color:#6c757d; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
        .chat-li-time { font-size:.8rem; color:#6c757d; }

        /* ===== Правый блок ===== */
        .dialog-bg { background:#e6ffe8; }

        /* ===== Строка сообщения и пузырь ===== */
        .msg-row {
            display:flex;
            width:100%;            /* ВАЖНО: строка на всю ширину */
            margin:.25rem 0;
        }
        .msg-inner{
            display:flex;
            flex-direction:column; /* пузырь + мета в колонку */
            width:100%;            /* ВАЖНО: внутренняя обёртка на всю ширину */
            max-width:100%;
        }

        .msg-bubble{
            max-width:75%;         /* 3/4 ширины всей строки */
            padding:.6rem .9rem;
            border-radius:16px;
            background:#ffffff;
            position:relative;
            word-break:break-word; /* НЕ break-all, чтобы не ломать «привет» на буквы */
            box-shadow:0 1px 0 rgba(0,0,0,.03);
        }

        .msg-row.msg-other .msg-bubble { background:#fff;   border-bottom-left-radius:4px;  margin-right:auto; }
        .msg-row.msg-mine  .msg-bubble { background:#c7f7c9; border-bottom-right-radius:4px; margin-left:auto;  }

        .msg-meta{
            display:flex; align-items:center; gap:.25rem;
            font-size:.75rem; color:#6c757d; margin-top:1.35rem;
        }
        .msg-row.msg-other .msg-meta{ align-self:flex-start; }
        .msg-row.msg-mine  .msg-meta{ align-self:flex-end; }

        .checks{ display:inline-flex; gap:2px; transform: translateY(1px); }
        .check{ width:14px; height:14px; display:inline-block; }
        .check svg{ width:14px; height:14px; }

        .chat-header-line { display:flex; justify-content:space-between; align-items:center; }
        .chat-title { font-weight:600; }
        .chat-sub { font-size:.9rem; color:#6c757d; }

        /* ===== Модалки (единый стиль) ===== */
        .modal-search-wrap { position:relative; }
        .modal-search-wrap .form-control { padding-left:2rem; }
        .modal-search-icon { position:absolute; top:50%; left:.5rem; transform:translateY(-50%); opacity:.6; }

        .contact-list {
            /*max-height:420px;*/
            overflow:auto; }
        .contact-row, .group-row {
            display:flex; align-items:center; gap:.65rem;
            padding:.5rem .25rem; cursor:pointer; border-radius:8px;
        }
        .contact-row:hover, .group-row:hover { background:#f5f7f9; }
        .contact-avatar, .group-avatar { width:60px; height:60px; border-radius:50%; object-fit:cover; }
        .contact-name, .group-name { font-weight:600; }
        .contact-sub, .group-sub { font-size:.85rem; color:#6c757d; }
        .list-unstyled { margin:0; padding:0; }
        .list-unstyled > li { list-style:none; }

        .chat-actions .btn { margin-left:.4rem; }

        .contact-list li .flex-grow-1{
            text-align: left;
        }
        #contactsModal .modal-content {
            width: 400px;
        }

        /* ==== FIX: чекбоксы в модалке "Создать группу" скроллятся вместе со строками ==== */
        #groupUsers { max-height: 380px; overflow-y: auto; }
        #groupUsers .group-row { display:flex; align-items:center; gap:.65rem; padding:.5rem .25rem; border-radius:8px; cursor:pointer; }
        #groupUsers .group-row:hover { background:#f5f7f9; }

        /* ключевое: отменяем любые позиционирования/float у bootstrap */
        #groupUsers .form-check-input {
            position: static !important;
            float: none !important;
            margin: 0 .5rem 0 0 !important;
            flex: 0 0 auto; /* не растягивать */
        }

        /* чтобы текст не наползал на чекбокс и всё было слева */
        #groupUsers .group-avatar { width:60px; height:60px; border-radius:50%; object-fit:cover; }
        #groupUsers .group-name { font-weight:600; }
        #groupUsers .group-sub  { font-size:.85rem; color:#6c757d; }


        #groupModal .modal-content {
            max-height: 950px;
        }

        /* ===== Правый блок ===== */
        .dialog-bg {
            background: url("/img/background-chat.jpg") repeat; /* путь см. ниже */
            background-size: cover;                     /* или contain / auto — под твой вкус */
        }

        .msg-bubble {
            max-width: 75%;
            padding: .6rem 3.2rem 1.4rem .9rem; /* справа больше отступа под время */
            border-radius: 16px;
            background: #ffffff;
            position: relative;  /* для абсолютного позиционирования времени */
            word-break: break-word;
            box-shadow: 0 1px 0 rgba(0,0,0,.03);
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

        #groupInfoMembers li .flex-grow-1 { text-align:left; }
        #groupInfoAddResults li .flex-grow-1 { text-align:left; }
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
                                    <span id="threadMembersLine" class="text-primary" style="cursor:pointer; display:none;"></span>
                                </div>
                            </div>


                            <div class="chat-actions">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#contactsModal">Контакты</button>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal">Создать группу</button>
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
                                <input type="text" class="form-control" id="msgInput" placeholder="Напишите сообщение…" autocomplete="off">
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
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242 1.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z" />
                        </svg>
                        <input type="text" id="contactsSearch" class="form-control" placeholder="Поиск по имени или email">
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
                        <input type="text" id="groupSubject" class="form-control mb-3" maxlength="120" placeholder="Например: 7Б Футбол">
                    </div>

                    <div class="modal-search-wrap mb-2">
                        <svg class="modal-search-icon" width="16" height="16" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242 1.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z" />
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
                        <img id="groupInfoAvatar" src="/img/default-avatar.png" style="width:56px;height:56px;border-radius:50%;object-fit:cover;" alt="">
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
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85zm-5.242 1.156a5 5 0 1 1 0-10 5 5 0 0 1 0 10z" />
                            </svg>
                            <input type="text" id="groupInfoSearch" class="form-control" placeholder="Добавить участника — начните ввод">
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
    <script>
        let currentThreadMeta = { id:null, is_group:false, member_count:0, title:'' };



        (function(){
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const me   = {{ auth()->id() }};
            let threadsCache = [];
            let currentThreadId = null;
            let lastMessageId   = null;
            let pollingTimer    = null;

            function escapeHtml(t){ return $('<div/>').text(t ?? '').html(); }
            function debounce(fn, ms){ let t; return function(){ const a=arguments,c=this; clearTimeout(t); t=setTimeout(()=>fn.apply(c,a),ms||300); } }
            function isToday(ts){ if(!ts) return false; const d=new Date(ts),n=new Date(); return d.getFullYear()===n.getFullYear()&&d.getMonth()===n.getMonth()&&d.getDate()===n.getDate(); }
            function pad(n){ return n<10?('0'+n):n; }
            function fmtTime(ts){ if(!ts) return ''; const d=new Date(ts); return isToday(ts) ? (pad(d.getHours())+':'+pad(d.getMinutes())) : (pad(d.getDate())+'.'+pad(d.getMonth()+1)+'.'+String(d.getFullYear()).slice(-2)); }
            function scrollBottom(){ const $b=$('#messagesBox'); $b.scrollTop($b[0].scrollHeight); }

            const svgOne = '<svg viewBox="0 0 24 24"><path fill="#6c757d" d="M9 16.2l-3.5-3.5-1.4 1.4L9 19 20.3 7.7l-1.4-1.4z"/></svg>';
            const svgTwo = '<svg viewBox="0 0 24 24"><path fill="#6c757d" d="M9 16.2l-3.5-3.5-1.4 1.4L9 19l6.3-6.3-1.4-1.4z"/><path fill="#6c757d" d="M19 9l-6.3 6.2-1.4-1.4L17.6 7.6z"/></svg>';

            // ===== ЛЕВЫЙ СПИСОК =====
            function renderThreads(list){
                const $wrap = $('#threads').empty();
                if(!list.length){
                    $wrap.append('<div class="list-group-item text-center text-muted py-4">Диалогов нет</div>');
                    return;
                }
                list.forEach(t=>{
                    const active = (t.id===currentThreadId) ? ' active' : '';
                    const $item = $(`
<div class="chat-list-item${active}" data-id="${t.id}">
  <img class="chat-avatar" src="${escapeHtml(t.avatar)}" alt="">
  <div class="chat-li-middle">
    <div class="d-flex justify-content-between">
      <div class="chat-li-title">${escapeHtml(t.title)}</div>
      <div class="chat-li-time">${fmtTime(t.last_message_time || t.updated_at)}</div>
    </div>
    <div class="chat-li-preview">${escapeHtml(t.last_message || '')}</div>
  </div>
</div>
            `).on('click', function(e){ e.preventDefault(); openThread(t.id); });
                    $wrap.append($item);
                });
            }

            function loadThreads(){
                $.ajax({
                    url:'/chat/api/threads', method:'GET',
                    success:function(res){
                        threadsCache = Array.isArray(res?.data) ? res.data : (Array.isArray(res) ? res : []);
                        renderThreads(threadsCache);
                    }
                });
            }

            $('#threadSearch').on('input', function(){
                const q=$(this).val().toLowerCase().trim();
                if(!q) return renderThreads(threadsCache);
                const filtered = threadsCache.filter(t =>
                    (t.title && t.title.toLowerCase().includes(q)) ||
                    (t.last_message && t.last_message.toLowerCase().includes(q))
                );
                renderThreads(filtered);
            });

            // ===== ОТКРЫТЬ ДИАЛОГ =====
            function openThread(threadId){
                $.ajax({
                    url:'/chat/api/threads/'+threadId, method:'GET',
                    success:function(res){
                        // currentThreadId = res.thread.id;
                        // $('#threadTitle').text(res.thread.subject || 'Диалог');
                        // $('#threadSub').text(res.thread.online || '');
                        //

                        currentThreadId = res.thread.id;
                        currentThreadMeta = {
                            id: res.thread.id,
                            is_group: !!res.thread.is_group,
                            member_count: Number(res.thread.member_count || 0),
                            title: res.thread.subject || 'Диалог'
                        };
                        $('#threadTitle').text(currentThreadMeta.title);

// показываем строку-линк "N участников" только для группы
                        const $line = $('#threadMembersLine');
                        if (currentThreadMeta.is_group) {
                            $line.text(currentThreadMeta.member_count + ' участник' + (currentThreadMeta.member_count%10===1 && currentThreadMeta.member_count%100!==11 ? '' : (currentThreadMeta.member_count%10>=2 && currentThreadMeta.member_count%10<=4 && (currentThreadMeta.member_count%100<10 || currentThreadMeta.member_count%100>=20) ? 'а' : 'ов')));
                            $line.show();
                        } else {
                            $line.hide().text('');
                        }



                        $('.chat-list-item').removeClass('active');
                        $(`.chat-list-item[data-id="${currentThreadId}"]`).addClass('active');

                        renderMessages(res.messages);
                        lastMessageId = res.messages.length ? res.messages[res.messages.length-1].id : null;

                        if(pollingTimer) clearInterval(pollingTimer);
                        pollingTimer = setInterval(pollNew, 3000);
                    }
                });
            }

            // ===== СООБЩЕНИЯ =====
            function renderMessages(msgs){
                const $box = $('#messagesBox').empty();
                msgs.forEach(m=>appendMessage(m, $box));
                scrollBottom();
            }

            function appendMessage2(m, $box){
                const mine = m.user_id === me;
                const rowClass = mine ? 'msg-row msg-mine' : 'msg-row msg-other';
                const bubble = $('<div class="msg-bubble"></div>').html(escapeHtml(m.body));
                const meta   = $('<div class="msg-meta"></div>');
                meta.append(`<span>${fmtTime(m.created_at)}</span>`);
                if(mine){
                    const isRead = !!m.is_read;
                    const checks = $('<span class="checks"></span>');
                    checks.append(`<span class="check">${isRead ? svgTwo : svgOne}</span>`);
                    meta.append(checks);
                }
                const row = $(`<div class="${rowClass}"></div>`)
                    .append($('<div class="msg-inner"></div>').append(bubble).append(meta)); /* ВАЖНО: msg-inner */
                $box.append(row);
            }

            function appendMessage(m, $box){
                const mine = m.user_id === me;
                const rowClass = mine ? 'msg-row msg-mine' : 'msg-row msg-other';

                const bubble = $('<div class="msg-bubble"></div>').html(escapeHtml(m.body));

                const meta = $('<div class="msg-meta"></div>');
                meta.append(`<span>${fmtTime(m.created_at)}</span>`);
                if(mine){
                    const isRead = !!m.is_read;
                    const checks = $('<span class="checks"></span>');
                    checks.append(`<span class="check">${isRead ? svgTwo : svgOne}</span>`);
                    meta.append(checks);
                }

                bubble.append(meta); // ВСТАВЛЯЕМ ВНУТРЬ пузыря
                const row = $(`<div class="${rowClass}"></div>`).append($('<div class="msg-inner"></div>').append(bubble));
                $box.append(row);
            }


            function pollNew(){
                if(!currentThreadId || lastMessageId===null) return;
                $.ajax({
                    url:'/chat/api/threads/'+currentThreadId+'/messages?after_id='+lastMessageId,
                    method:'GET',
                    success:function(list){
                        if(!Array.isArray(list) || !list.length) return;
                        const $box = $('#messagesBox');
                        list.forEach(m=>{ appendMessage(m, $box); lastMessageId = m.id; });
                        scrollBottom();
                    }
                });
            }

            // ===== ОТПРАВКА =====
            $('#sendForm').on('submit', function(e){
                e.preventDefault();
                const id = Number(currentThreadId);
                if (!Number.isInteger(id) || id <= 0) { alert('Сначала выберите диалог слева.'); return; }
                const text = $('#msgInput').val().trim();
                if(!text) return;
                $.ajax({
                    url:'/chat/api/threads/'+id+'/messages',
                    method:'POST',
                    headers:{'X-CSRF-TOKEN': csrf},
                    data:{ body:text },
                    success:function(m){
                        $('#msgInput').val('');
                        appendMessage(m, $('#messagesBox'));
                        lastMessageId = m.id;
                        scrollBottom();
                        loadThreads();
                    }
                });
            });

            // ===== КОНТАКТЫ (живой поиск) =====
            function renderContacts(list){
                const $wrap = $('#contactsList').empty();
                if(!list.length){
                    $wrap.append('<li class="text-muted text-center py-3">Ничего не найдено</li>');
                    return;
                }
                list.forEach(u=>{
                    const $el = $(`
<li>
  <div class="contact-row">
    <img class="contact-avatar" src="${escapeHtml(u.avatar)}" alt="">
    <div class="flex-grow-1">
      <div class="contact-name">${escapeHtml(u.name ?? ('ID '+u.id))}</div>
      <div class="contact-sub">был(а) недавно</div>
    </div>
  </div>
</li>
            `).on('click', function(){
                        $.ajax({
                            url:'/chat/api/threads',
                            method:'POST',
                            headers:{'X-CSRF-TOKEN': csrf},
                            data:{ type:'private', members:[u.id] },
                            success:function(res){
                                $('#contactsModal').modal('hide');
                                loadThreads();
                                if(res.thread_id) openThread(res.thread_id);
                            }
                        });
                    });
                    $wrap.append($el);
                });
            }

            function loadContacts(q=''){
                $.ajax({
                    url:'/chat/api/users'+(q?('?q='+encodeURIComponent(q)):''), method:'GET',
                    success:function(list){ renderContacts(list || []); }
                });
            }

            $('#contactsModal').on('shown.bs.modal', function(){
                $('#contactsSearch').val('').trigger('input').focus();
                loadContacts('');
            });

            $('#contactsSearch').on('input', debounce(function(){
                loadContacts($(this).val().trim());
            }, 250));

            // ===== СОЗДАТЬ ГРУППУ (живой поиск) =====
            function renderGroupUsers(list){
                const $wrap = $('#groupUsers').empty();
                if(!list.length){
                    $wrap.append('<li class="text-muted px-2 py-2">Ничего не найдено</li>');
                    return;
                }
                list.forEach(u=>{
                    $wrap.append(`
<li>
  <label class="group-row w-100">
    <input type="checkbox" class="form-check-input me-2 group-pick" value="${u.id}">
    <img class="group-avatar" src="${escapeHtml(u.avatar)}" alt="">
    <div>
      <div class="group-name">${escapeHtml(u.name ?? ('ID '+u.id))}</div>
      <div class="group-sub">${escapeHtml(u.email ?? '')}</div>
    </div>
  </label>
</li>
            `);
                });
            }

            function loadGroupUsers(q=''){
                $.ajax({
                    url:'/chat/api/users'+(q?('?q='+encodeURIComponent(q)):''), method:'GET',
                    success:function(list){ renderGroupUsers(list || []); }
                });
            }

            $('#groupModal').on('shown.bs.modal', function(){
                $('#groupSubject').val('');
                $('#groupSearch').val('').trigger('input').focus();
                loadGroupUsers('');
            });

            $('#groupSearch').on('input', debounce(function(){
                loadGroupUsers($(this).val().trim());
            }, 250));

            $('#createGroupBtn').on('click', function(){
                const subject = $('#groupSubject').val().trim();
                if(!subject){ alert('Введите название группы'); return; }
                const members = $('.group-pick:checked').map(function(){return $(this).val();}).get();
                if(!members.length){ alert('Выберите хотя бы одного участника'); return; }

                $.ajax({
                    url:'/chat/api/threads',
                    method:'POST',
                    headers:{'X-CSRF-TOKEN': csrf},
                    data:{ type:'group', subject:subject, members:members },
                    success:function(res){
                        $('#groupModal').modal('hide');
                        loadThreads();
                        if(res.thread_id) openThread(res.thread_id);
                    }
                });
            });

            $('#threadMembersLine').on('click', function(){
                if (!currentThreadId || !currentThreadMeta.is_group) return;
                loadGroupInfo(currentThreadId);
                const modal = new bootstrap.Modal(document.getElementById('groupInfoModal'));
                modal.show();
            });

            function renderMemberLi(u){
                return $(`
<li>
  <div class="contact-row">
    <img class="contact-avatar" src="${escapeHtml(u.avatar)}" alt="">
    <div class="flex-grow-1">
      <div class="contact-name">${escapeHtml(u.name ?? ('ID '+u.id))}</div>
      <div class="contact-sub">${escapeHtml(u.email ?? '')}</div>
    </div>
  </div>
</li>`);
            }

// грузим список участников
            function loadGroupInfo(threadId){
                // заголовок
                $('#groupInfoTitle').text(currentThreadMeta.title);
                $('#groupInfoCount').text(currentThreadMeta.member_count + ' участник' + (currentThreadMeta.member_count%10===1 && currentThreadMeta.member_count%100!==11 ? '' : (currentThreadMeta.member_count%10>=2 && currentThreadMeta.member_count%10<=4 && (currentThreadMeta.member_count%100<10 || currentThreadMeta.member_count%100>=20) ? 'а' : 'ов')));

                // очистим поиск на добавление
                $('#groupInfoSearch').val('');
                $('#groupInfoAddBox').hide();
                $('#groupInfoAddResults').empty();

                // список участников
                $.ajax({
                    url: '/chat/api/threads/'+threadId+'/members',
                    method: 'GET',
                    success: function(res){
                        const list = Array.isArray(res?.members) ? res.members : [];
                        const $wrap = $('#groupInfoMembers').empty();
                        if (!list.length){
                            $wrap.append('<li class="text-muted text-center py-3">Участников пока нет</li>');
                        } else {
                            list.forEach(u=> $wrap.append(renderMemberLi(u)));
                            // поставим аватар «группы» равным первому участнику
                            $('#groupInfoAvatar').attr('src', list[0]?.avatar || '/img/default-avatar.png');
                        }
                        currentThreadMeta.member_count = Number(res?.member_count || list.length || 0);
                        $('#groupInfoCount').text(currentThreadMeta.member_count + ' участник' + (currentThreadMeta.member_count%10===1 && currentThreadMeta.member_count%100!==11 ? '' : (currentThreadMeta.member_count%10>=2 && currentThreadMeta.member_count%10<=4 && (currentThreadMeta.member_count%100<10 || currentThreadMeta.member_count%100>=20) ? 'а' : 'ов')));
                        // ещё обновим строку под заголовком чата
                        if (currentThreadMeta.is_group) {
                            $('#threadMembersLine').text($('#groupInfoCount').text());
                        }
                    }
                });
            }

// живой поиск по пользователям для ДОБАВЛЕНИЯ в группу
            $('#groupInfoSearch').on('input', debounce(function(){
                const q = $(this).val().trim();
                if (!q) { $('#groupInfoAddBox').hide().find('#groupInfoAddResults').empty(); return; }

                $.ajax({
                    url: '/chat/api/users?q=' + encodeURIComponent(q),
                    method: 'GET',
                    success: function(list){
                        const $box = $('#groupInfoAddBox').show();
                        const $res = $('#groupInfoAddResults').empty();
                        (list||[]).forEach(u=>{
                            const $li = renderMemberLi(u);
                            $li.css('cursor','pointer').on('click', function(){
                                // клик по человеку — добавляем в группу
                                $.ajax({
                                    url: '/chat/api/threads/'+currentThreadId+'/members',
                                    method: 'POST',
                                    headers: {'X-CSRF-TOKEN': csrf},
                                    data: { members: [u.id] },
                                    success: function(){
                                        // перезагрузить текущую инфу/счётчик/левый список тредов
                                        loadGroupInfo(currentThreadId);
                                        loadThreads();
                                    }
                                });
                            });
                            $res.append($li);
                        });
                    }
                });
            }, 250));

            function renderMemberLi(u){
                return $(`
<li>
  <div class="contact-row">
    <img class="contact-avatar" src="${escapeHtml(u.avatar)}" alt="">
    <div class="flex-grow-1">
      <div class="contact-name">${escapeHtml(u.name ?? ('ID '+u.id))}</div>
      <div class="contact-sub">${escapeHtml(u.email ?? '')}</div>
    </div>
  </div>
</li>`);
            }

// грузим список участников
            function loadGroupInfo(threadId){
                // заголовок
                $('#groupInfoTitle').text(currentThreadMeta.title);
                $('#groupInfoCount').text(currentThreadMeta.member_count + ' участник' + (currentThreadMeta.member_count%10===1 && currentThreadMeta.member_count%100!==11 ? '' : (currentThreadMeta.member_count%10>=2 && currentThreadMeta.member_count%10<=4 && (currentThreadMeta.member_count%100<10 || currentThreadMeta.member_count%100>=20) ? 'а' : 'ов')));

                // очистим поиск на добавление
                $('#groupInfoSearch').val('');
                $('#groupInfoAddBox').hide();
                $('#groupInfoAddResults').empty();

                // список участников
                $.ajax({
                    url: '/chat/api/threads/'+threadId+'/members',
                    method: 'GET',
                    success: function(res){
                        const list = Array.isArray(res?.members) ? res.members : [];
                        const $wrap = $('#groupInfoMembers').empty();
                        if (!list.length){
                            $wrap.append('<li class="text-muted text-center py-3">Участников пока нет</li>');
                        } else {
                            list.forEach(u=> $wrap.append(renderMemberLi(u)));
                            // поставим аватар «группы» равным первому участнику
                            $('#groupInfoAvatar').attr('src', list[0]?.avatar || '/img/default-avatar.png');
                        }
                        currentThreadMeta.member_count = Number(res?.member_count || list.length || 0);
                        $('#groupInfoCount').text(currentThreadMeta.member_count + ' участник' + (currentThreadMeta.member_count%10===1 && currentThreadMeta.member_count%100!==11 ? '' : (currentThreadMeta.member_count%10>=2 && currentThreadMeta.member_count%10<=4 && (currentThreadMeta.member_count%100<10 || currentThreadMeta.member_count%100>=20) ? 'а' : 'ов')));
                        // ещё обновим строку под заголовком чата
                        if (currentThreadMeta.is_group) {
                            $('#threadMembersLine').text($('#groupInfoCount').text());
                        }
                    }
                });
            }

// живой поиск по пользователям для ДОБАВЛЕНИЯ в группу
            $('#groupInfoSearch').on('input', debounce(function(){
                const q = $(this).val().trim();
                if (!q) { $('#groupInfoAddBox').hide().find('#groupInfoAddResults').empty(); return; }

                $.ajax({
                    url: '/chat/api/users?q=' + encodeURIComponent(q),
                    method: 'GET',
                    success: function(list){
                        const $box = $('#groupInfoAddBox').show();
                        const $res = $('#groupInfoAddResults').empty();
                        (list||[]).forEach(u=>{
                            const $li = renderMemberLi(u);
                            $li.css('cursor','pointer').on('click', function(){
                                // клик по человеку — добавляем в группу
                                $.ajax({
                                    url: '/chat/api/threads/'+currentThreadId+'/members',
                                    method: 'POST',
                                    headers: {'X-CSRF-TOKEN': csrf},
                                    data: { members: [u.id] },
                                    success: function(){
                                        // перезагрузить текущую инфу/счётчик/левый список тредов
                                        loadGroupInfo(currentThreadId);
                                        loadThreads();
                                    }
                                });
                            });
                            $res.append($li);
                        });
                    }
                });
            }, 250));



            loadThreads();
        })();
    </script>
@endpush
