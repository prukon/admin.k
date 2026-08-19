@extends('layouts.admin2')

@push('styles')
    @vite(['resources/css/chat.css'])
@endpush

@section('content')
    <div class="container py-3 chat-page" id="chatApp"
         data-me="{{ (int) auth()->id() }}"
         data-mobile-tab="messages"
         data-threads-url="{{ route('chat.api.threads.index') }}"
         data-store-thread-url="{{ route('chat.api.threads.store') }}"
         data-store-group-url="{{ route('chat.api.threads.groups.store') }}"
         data-users-url="{{ route('chat.api.users') }}"
         data-unread-url="{{ route('chat.api.unread') }}"
         data-can-delete-thread="{{ auth()->user()?->can('messages.threads.delete') ? '1' : '0' }}">
        <div class="row g-0 g-lg-3 chat-desktop-row">
            <div class="col-12 col-lg-4 chat-list-col">
                <div class="card h-100">
                    <div class="chat-list-search d-flex gap-2">
                        <input type="text" id="threadSearch" class="form-control form-control-sm" placeholder="Поиск" autocomplete="off">
                        <button type="button" class="btn btn-sm btn-primary text-nowrap d-none d-lg-inline-block" id="openContactsBtn">Контакты</button>
                        <button type="button" class="btn btn-sm btn-primary text-nowrap d-none d-lg-inline-block js-open-create-group" id="openCreateGroupBtn">Создать группу</button>
                    </div>
                    <div id="threads" class="list-group list-group-flush" style="overflow:auto; max-height:65vh;"></div>
                </div>
            </div>
            <div class="col-12 col-lg-8 chat-dialog-col">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <button type="button" class="chat-mobile-back" id="chatMobileBack" aria-label="Назад">
                            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        </button>
                        <div id="threadPeerHit" class="d-flex align-items-center gap-2 chat-header-peer is-idle">
                            <img id="threadAvatar" src="/img/default-avatar.png" alt="" class="chat-avatar" style="display:none;">
                            <div class="chat-header-text">
                                <div class="fw-semibold" id="threadTitle">Выберите диалог</div>
                                <div id="threadSubtitle" class="chat-header-subtitle" style="display:none;"></div>
                            </div>
                        </div>
                        @can('messages.threads.delete')
                            <div class="chat-header-delete-wrap">
                                <button type="button" class="btn btn-sm btn-outline-danger chat-header-delete" id="deleteThreadBtn" title="Удалить чат" aria-label="Удалить чат" style="display:none;">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                </button>
                                <div class="text-danger chat-field-error" id="threadDeleteError" data-error-for="thread"></div>
                            </div>
                        @endcan
                    </div>
                    <div class="card-body dialog-bg p-0 d-flex flex-column" style="height:65vh;">
                        <div id="messagesBox" class="p-3 flex-grow-1 overflow-auto">
                            <div class="chat-empty">Сообщения появятся здесь…</div>
                        </div>
                        <div class="border-top p-2 bg-white chat-composer">
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

        <div id="chatPaneContacts" class="chat-mobile-pane" data-mobile-pane="contacts">
            <div class="chat-mobile-pane-title">Контакты</div>
        </div>
        <div id="chatPaneGroups" class="chat-mobile-pane" data-mobile-pane="groups">
            <div class="chat-mobile-pane-title">Чаты</div>
            <div class="px-3 pb-2">
                <button type="button" class="btn btn-sm btn-primary js-open-create-group" id="openCreateGroupMobileBtn">Создать группу</button>
            </div>
            <div id="groupThreads" class="list-group list-group-flush chat-group-threads"></div>
        </div>
        <div id="chatPaneAccount" class="chat-mobile-pane" data-mobile-pane="account">
            <div class="chat-mobile-pane-title">Аккаунт</div>
            <div class="text-danger chat-field-error" id="accountCardError"></div>
            <div id="accountCardBody"></div>
        </div>

        <nav class="chat-mobile-nav" id="chatMobileNav" aria-label="Разделы чата">
            <button type="button" class="chat-mobile-nav-btn" data-mobile-tab="contacts" aria-selected="false">
                <i class="fa-solid fa-address-book" aria-hidden="true"></i>
                <span>Контакты</span>
            </button>
            <button type="button" class="chat-mobile-nav-btn is-active" data-mobile-tab="messages" aria-selected="true">
                <i class="fa-solid fa-comment" aria-hidden="true"></i>
                <span>Личные сообщения</span>
                <span id="chatPrivateUnreadBadge" class="badge badge-info chat-mobile-nav-badge js-chat-private-unread-count"@if(($chatPrivateUnreadCount ?? 0) <= 0) style="display:none"@endif>{{ (int) ($chatPrivateUnreadCount ?? 0) }}</span>
            </button>
            <button type="button" class="chat-mobile-nav-btn" data-mobile-tab="groups" aria-selected="false">
                <i class="fa-solid fa-comments" aria-hidden="true"></i>
                <span>Чаты</span>
                <span id="chatGroupUnreadBadge" class="badge badge-info chat-mobile-nav-badge js-chat-group-unread-count"@if(($chatGroupUnreadCount ?? 0) <= 0) style="display:none"@endif>{{ (int) ($chatGroupUnreadCount ?? 0) }}</span>
            </button>
            <button type="button" class="chat-mobile-nav-btn" data-mobile-tab="account" aria-selected="false">
                <i class="fa-solid fa-user" aria-hidden="true"></i>
                <span>Аккаунт</span>
            </button>
        </nav>
    </div>

    <div class="modal fade" id="contactsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Контакты</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body" id="contactsModalBody">
                    <div id="contactsMount">
                    <select id="contactsTeamFilter" class="form-select mb-2" aria-label="Фильтр по группе">
                        <option value="">Все группы</option>
                        <option value="none">Без группы</option>
                        @foreach ($contactTeams ?? [] as $team)
                            <option value="{{ (int) $team->id }}">{{ $team->title }}</option>
                        @endforeach
                    </select>
                    <div class="text-danger chat-field-error" id="contactsTeamError" data-error-for="team_id"></div>
                    <input type="text" id="contactsSearch" class="form-control mb-2" placeholder="Поиск по имени или email" autocomplete="off">
                    <div class="text-danger chat-field-error" id="contactsError" data-error-for="q"></div>
                    <ul id="contactsList" class="contact-list"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="peerCardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Контакт</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="text-danger chat-field-error" id="peerCardError"></div>
                    <div id="peerCardBody"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="createGroupNameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Создать группу</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form id="createGroupNameForm">
                    <div class="modal-body">
                        <label for="createGroupTitle" class="form-label">Название группы</label>
                        <input type="text" id="createGroupTitle" name="title" class="form-control" maxlength="100" autocomplete="off">
                        <div class="text-danger chat-field-error" id="createGroupTitleError" data-error-for="title"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary" id="createGroupNameSubmit">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createGroupMembersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Участники группы</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form id="createGroupMembersForm">
                    <div class="modal-body">
                        <select id="createGroupMembersTeamFilter" class="form-select mb-2" aria-label="Фильтр по группе">
                            <option value="">Все группы</option>
                            <option value="none">Без группы</option>
                            @foreach ($contactTeams ?? [] as $team)
                                <option value="{{ (int) $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>
                        <div class="text-danger chat-field-error" id="createGroupMembersTeamError" data-error-for="team_id"></div>
                        <input type="text" id="createGroupMembersSearch" class="form-control mb-2" placeholder="Поиск по имени или email" autocomplete="off">
                        <div class="text-danger chat-field-error" id="createGroupMembersSearchError" data-error-for="q"></div>
                        <ul id="createGroupMembersList" class="contact-list"></ul>
                        <div class="text-danger chat-field-error" id="createGroupMembersError" data-error-for="user_ids"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary" id="createGroupMembersSubmit">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="groupCardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Группа</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="text-danger chat-field-error" id="groupCardError"></div>
                    <div class="group-card-head">
                        <img id="groupCardAvatar" class="group-card-avatar" src="/img/default-avatar.png" alt="">
                        <div class="group-card-title" id="groupCardTitle"></div>
                        <div class="group-card-count" id="groupCardCount"></div>
                    </div>
                    <div class="group-card-actions">
                        <button type="button" class="group-card-action" id="addGroupMembersBtn" title="Добавить участников" aria-label="Добавить участников">
                            <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="group-card-action" id="leaveGroupBtn" title="Покинуть группу" aria-label="Покинуть группу">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="group-members-wrap" id="groupMembersWrap">
                        <table class="table table-sm group-members-table">
                            <thead>
                                <tr>
                                    <th>Аватар</th>
                                    <th>ФИО клиента</th>
                                    <th>Роль</th>
                                </tr>
                            </thead>
                            <tbody id="groupMembersBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addGroupMembersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить участников</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form id="addGroupMembersForm">
                    <div class="modal-body">
                        <select id="addGroupMembersTeamFilter" class="form-select mb-2" aria-label="Фильтр по группе">
                            <option value="">Все группы</option>
                            <option value="none">Без группы</option>
                            @foreach ($contactTeams ?? [] as $team)
                                <option value="{{ (int) $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>
                        <div class="text-danger chat-field-error" id="addGroupMembersTeamError" data-error-for="team_id"></div>
                        <input type="text" id="addGroupMembersSearch" class="form-control mb-2" placeholder="Поиск по имени или email" autocomplete="off">
                        <div class="text-danger chat-field-error" id="addGroupMembersSearchError" data-error-for="q"></div>
                        <ul id="addGroupMembersList" class="contact-list"></ul>
                        <div class="text-danger chat-field-error" id="addGroupMembersError" data-error-for="user_ids"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary" id="addGroupMembersSubmit">Добавить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/chat.js'])
@endpush
