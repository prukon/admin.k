@extends('layouts.admin2')

@section('content')
    <style>
        .chat-page { min-height: 70vh; }
        .chat-list-search { padding: .5rem .75rem; border-bottom: 1px solid #e9ecef; }
        .chat-list-item {
            display: flex; gap: .75rem; padding: .6rem .75rem; cursor: pointer;
            border-left: 4px solid transparent;
        }
        .chat-list-item:hover { background: rgba(46, 170, 220, .06); border-left-color: #2eaadc; }
        .chat-list-item.active { background: #eaf6ff; border-left-color: #2eaadc; }
        .chat-avatar { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex: 0 0 42px; }
        .chat-avatar-wrap { position: relative; flex: 0 0 42px; width: 42px; height: 42px; }
        .chat-online-dot {
            position: absolute; right: 0; bottom: 0; width: 10px; height: 10px;
            border-radius: 50%; background: #22c55e; border: 2px solid #fff; box-sizing: content-box;
        }
        .chat-li-middle { flex: 1; min-width: 0; }
        .chat-li-title { font-weight: 600; line-height: 1.2; }
        .chat-li-preview {
            font-size: .9rem; color: #6c757d; overflow: hidden;
            white-space: nowrap; text-overflow: ellipsis; text-align: left;
        }
        .chat-li-time { font-size: .8rem; color: #6c757d; white-space: nowrap; display: flex; align-items: center; gap: .15rem; }
        .chat-li-time .check { width: 12px; height: 12px; }
        .chat-li-time .check svg { width: 12px; height: 12px; }
        .chat-li-time .check-second { margin-left: -6px; }
        .dialog-bg { background: url("/img/background-chat.jpg") repeat; background-size: cover; }
        .msg-row { display: flex; width: 100%; margin: .25rem 0; }
        .msg-inner { display: flex; flex-direction: column; width: 100%; }
        .msg-bubble {
            max-width: 75%; padding: .6rem 3.2rem 1.4rem .9rem; border-radius: 16px;
            background: #fff; position: relative; word-break: break-word;
            box-shadow: 0 1px 0 rgba(0, 0, 0, .03);
        }
        .msg-row.msg-other .msg-bubble { border-bottom-left-radius: 4px; margin-right: auto; }
        .msg-row.msg-mine .msg-bubble { background: #c7f7c9; border-bottom-right-radius: 4px; margin-left: auto; }
        .msg-meta {
            position: absolute; bottom: 4px; right: 8px; font-size: .7rem;
            color: #6c757d; display: flex; align-items: center; gap: .2rem;
        }
        .msg-row.msg-mine .msg-meta { color: #4CAF50; }
        .checks { display: inline-flex; align-items: center; line-height: 1; }
        .check { width: 14px; height: 14px; display: inline-block; }
        .check-second { margin-left: -7px; }
        .check svg { width: 14px; height: 14px; display: block; }
        .checks-sent { color: #6c757d; }
        .checks-read { color: #4CAF50; }
        .contact-list { margin: 0; padding: 0; list-style: none; max-height: min(60vh, 520px); overflow: auto; }
        .contact-row {
            display: flex; align-items: center; gap: .65rem;
            padding: .4rem .25rem; cursor: pointer; border-radius: 8px;
        }
        .contact-row:hover { background: #f5f7f9; }
        .contact-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .contact-avatar-wrap { position: relative; flex: 0 0 36px; width: 36px; height: 36px; }
        .contact-online-dot {
            position: absolute; right: 0; bottom: 0; width: 9px; height: 9px;
            border-radius: 50%; border: 2px solid #fff; box-sizing: content-box;
        }
        .contact-online-dot.is-online { background: #22c55e; }
        .contact-online-dot.is-offline { background: #dc3545; }
        .contact-name { font-weight: 600; }
        .contact-parent { font-size: .8rem; color: #868e96; font-weight: 400; line-height: 1.25; }
        .contact-sub { font-size: .85rem; color: #6c757d; }
        .chat-field-error { min-height: 1.2rem; font-size: .85rem; }
        .chat-empty { color: #6c757d; text-align: center; padding: 2rem 1rem; }
    </style>

    <div class="container py-3 chat-page" id="chatApp"
         data-me="{{ (int) auth()->id() }}"
         data-threads-url="{{ route('chat.api.threads.index') }}"
         data-store-thread-url="{{ route('chat.api.threads.store') }}"
         data-users-url="{{ route('chat.api.users') }}"
         data-unread-url="{{ route('chat.api.unread') }}">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="card h-100">
                    <div class="chat-list-search d-flex gap-2">
                        <input type="text" id="threadSearch" class="form-control form-control-sm" placeholder="Поиск" autocomplete="off">
                        <button type="button" class="btn btn-sm btn-primary text-nowrap" id="openContactsBtn">Контакты</button>
                    </div>
                    <div id="threads" class="list-group list-group-flush" style="overflow:auto; max-height:65vh;"></div>
                </div>
            </div>
            <div class="col-12 col-md-8">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <img id="threadAvatar" src="/img/default-avatar.png" alt="" class="chat-avatar" style="display:none;">
                        <div class="fw-semibold" id="threadTitle">Выберите диалог</div>
                    </div>
                    <div class="card-body dialog-bg p-0 d-flex flex-column" style="height:65vh;">
                        <div id="messagesBox" class="p-3 flex-grow-1 overflow-auto">
                            <div class="chat-empty">Сообщения появятся здесь…</div>
                        </div>
                        <div class="border-top p-2 bg-white">
                            <form id="sendForm">
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control" id="msgInput" name="body" placeholder="Напишите сообщение…" autocomplete="off" disabled>
                                    <button class="btn btn-success" type="submit" disabled>Отправить</button>
                                </div>
                                <div class="text-danger chat-field-error" id="msgBodyError"></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="contactsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Контакты</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="contactsSearch" class="form-control mb-2" placeholder="Поиск по имени или email" autocomplete="off">
                    <div class="text-danger chat-field-error" id="contactsError"></div>
                    <ul id="contactsList" class="contact-list"></ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/chat.js') }}?v={{ @filemtime(public_path('js/chat.js')) ?: time() }}"></script>
@endpush
