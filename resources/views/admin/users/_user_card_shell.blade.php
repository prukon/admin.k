@once
@push('styles')
    <link rel="stylesheet" href="{{ asset('plugins/flag-icon-css/css/flag-icon.css') }}">
@endpush
                <style>
                    #userCardModal .modal-dialog {
                        max-width: 640px;
                    }
                    #userCardModal .modal-header {
                        align-items: flex-start;
                        gap: 0.5rem;
                        padding-top: 0.6rem;
                        padding-bottom: 0.6rem;
                    }
                    #userCardModal .user-card-head {
                        display: flex;
                        align-items: stretch;
                        justify-content: space-between;
                        gap: 0.5rem;
                        flex: 1 1 auto;
                        min-width: 0;
                    }
                    #userCardModal .user-card-head-block {
                        background: #f8f9fa;
                        border: 1px solid #e9ecef;
                        border-radius: 0.65rem;
                        padding: 0.35rem 0.55rem;
                    }
                    #userCardModal .user-card-identity {
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        flex: 1 1 auto;
                        min-width: 0;
                    }
                    #userCardModal .user-card-avatar {
                        width: 2.25rem;
                        height: 2.25rem;
                        border-radius: 50%;
                        object-fit: cover;
                        flex: 0 0 auto;
                        background: #fff;
                    }
                    #userCardModal .user-card-presence {
                        display: flex;
                        flex-direction: column;
                        align-items: flex-end;
                        justify-content: center;
                        gap: 0.2rem;
                        flex: 0 0 auto;
                        text-align: right;
                    }
                    #userCardModal .modal-title {
                        font-size: 1rem;
                        line-height: 1.2;
                        margin-bottom: 0;
                    }
                    #userCardModal .user-card-meta {
                        font-size: 0.75rem;
                        line-height: 1.2;
                    }
                    #userCardModal .modal-header .btn-close {
                        margin-top: 0.45rem;
                    }
                    #userCardModal .user-card-login-flag {
                        width: 1.25rem;
                        height: 0.95rem;
                        border-radius: 2px;
                        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
                        background-size: cover;
                    }
                    #userCardModal .modal-content {
                        max-height: calc(100vh - 2rem);
                        overflow: visible;
                    }
                    #userCardModal #user-card-form {
                        display: flex;
                        flex-direction: column;
                        flex: 1 1 auto;
                        min-height: 0;
                        overflow: hidden;
                    }
                    #userCardModal #user-card-form > .modal-body {
                        overflow-y: auto;
                        min-height: 0;
                    }
                    #userCardModal #user-card-form > .modal-footer {
                        flex: 0 0 auto;
                        justify-content: flex-start;
                        gap: 0.5rem;
                    }
                    #userCardModal #user-card-new-password {
                        background-color: #fff;
                        padding-right: 2.2rem;
                    }
                    #user-card-pane-info .form-label,
                    #user-card-pane-family .form-label {
                        margin-bottom: 0.15rem;
                        font-size: 0.8125rem;
                        line-height: 1.2;
                    }
                    #user-card-pane-info .form-control,
                    #user-card-pane-info .form-select,
                    #user-card-pane-family .form-control,
                    #user-card-pane-family .form-select {
                        padding-top: 0.2rem;
                        padding-bottom: 0.2rem;
                        min-height: calc(1.5em + 0.4rem + 2px);
                        font-size: 0.875rem;
                    }
                    #user-card-pane-info textarea.form-control,
                    #user-card-pane-family textarea.form-control {
                        min-height: 2.25rem;
                    }
                    #user-card-pane-info .row,
                    #user-card-pane-family .row {
                        --bs-gutter-y: 0.5rem;
                    }
                    #user-card-pane-info .user-card-block,
                    #user-card-pane-family .user-card-block {
                        margin-bottom: 0.75rem;
                        padding: 0.85rem 1rem;
                        border-radius: 0.65rem;
                        background: #f8f9fa;
                        border: 1px solid #e9ecef;
                    }
                    #user-card-pane-info .user-card-block--last,
                    #user-card-pane-family .user-card-block--last {
                        margin-bottom: 0;
                    }
                    #user-card-pane-info .user-card-block__title,
                    #user-card-pane-family .user-card-block__title {
                        display: block;
                        margin-bottom: 0.65rem;
                        font-size: 0.8125rem;
                        font-weight: 600;
                        color: #495057;
                        letter-spacing: 0.01em;
                    }
                    #user-card-pane-info .user-card-block .form-control,
                    #user-card-pane-info .user-card-block .form-select,
                    #user-card-pane-family .user-card-block .form-control,
                    #user-card-pane-family .user-card-block .form-select {
                        background-color: #fff;
                    }
                    #user-card-pane-info .user-card-block .table,
                    #user-card-pane-family .user-card-block .table {
                        --bs-table-bg: #fff;
                        background-color: #fff;
                    }
                    #user-card-pane-logs,
                    #user-card-pane-logs .table,
                    #user-card-pane-logs .dataTables_wrapper,
                    #user-card-pane-emails,
                    #user-card-pane-emails .table,
                    #user-card-pane-emails .dataTables_wrapper {
                        font-size: 0.8125rem;
                    }
                    #user-card-pane-info .user-card-block__subtitle {
                        margin: 0.85rem 0 0.45rem;
                        font-size: 0.8125rem;
                        font-weight: 600;
                        color: #495057;
                    }
                    #user-card-pane-info .user-card-health {
                        display: flex;
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 0.35rem;
                    }
                    #user-card-pane-info .user-card-health__item {
                        min-width: 0;
                    }
                    #user-card-pane-info .user-card-health .form-check-label {
                        font-size: 0.875rem;
                        line-height: 1.3;
                    }
                </style>
                <div class="modal fade" id="userCardModal" tabindex="-1" aria-labelledby="userCardModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable">
                        <div class="modal-content" id="userCardModalContent">
                            <div class="modal-body text-muted">Загрузка...</div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="userCardEmailModal" tabindex="-1" aria-labelledby="userCardEmailModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="userCardEmailModalLabel">Письмо</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                            </div>
                            <div class="modal-body" id="userCardEmailModalBody">
                                <div class="text-center text-muted py-4">Загрузка…</div>
                            </div>
                            <div class="modal-footer">
                                <a href="#" class="btn btn-outline-secondary btn-sm d-none" id="userCardEmailModalOpenPage" target="_blank" rel="noopener">Открыть на отдельной странице</a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                            </div>
                        </div>
                    </div>
                </div>
@push('scripts')
<script>
$(function () {
            const userCardLanguageBase = @include('partials.datatables.ru');
            const userCardModalEl = document.getElementById('userCardModal');
            let userCardHideIsQueued = false;
            let userCardParentReady = false;
            let userCardPaymentsTable = null;
            let userCardContractsTable = null;
            let userCardLogsTable = null;
            let userCardEmailsTable = null;
            let userCardPaymentsUrl = '';
            let userCardContractsUrl = '';
            let userCardLogsUrl = '';
            let userCardEmailsUrl = '';

            function userCardLanguage(emptyText) {
                return Object.assign({}, userCardLanguageBase, {
                    emptyTable: emptyText,
                    zeroRecords: emptyText
                });
            }

            function destroyUserCardTeamsSelect() {
                const $teams = $('#user-card-teams');
                if ($teams.length && $teams.hasClass('select2-hidden-accessible')) {
                    $teams.select2('destroy');
                }
            }

            function userCardHideIsTemporary() {
                if (!userCardModalEl || !window.jQuery) {
                    return false;
                }
                const events = window.jQuery._data(userCardModalEl, 'events');
                const hidden = events && events.hidden;
                if (!hidden) {
                    return false;
                }
                return hidden.some(function (handler) {
                    return String(handler.namespace || '').split('.').indexOf('openNext') !== -1;
                });
            }

            function closeUserCardTeamsSelect() {
                const $teams = $('#user-card-teams');
                if ($teams.length && $teams.hasClass('select2-hidden-accessible')) {
                    $teams.select2('close');
                }
            }

            function restoreUserCardTeamsSelect() {
                const $teams = $('#user-card-teams');
                if (!$teams.length || !window.KidsCrmGenericMultiselectSelect2) {
                    return;
                }
                if (!$teams.hasClass('select2-hidden-accessible')) {
                    initUserCardTeamsSelect();
                    return;
                }
                $teams.next('.select2-container').css('width', '100%');
                if (window.KidsCrmMultiselectChipStyles) {
                    window.KidsCrmMultiselectChipStyles.apply($teams);
                }
            }

            function initUserCardTeamsSelect() {
                const $teams = $('#user-card-teams');
                if (!$teams.length || !window.KidsCrmGenericMultiselectSelect2 || $teams.hasClass('select2-hidden-accessible')) {
                    return;
                }
                KidsCrmGenericMultiselectSelect2.init($teams, {
                    dropdownParent: $('#userCardModal')
                });
            }

            function destroyUserCardTables() {
                if (userCardPaymentsTable) {
                    userCardPaymentsTable.destroy();
                    userCardPaymentsTable = null;
                }
                if (userCardContractsTable) {
                    userCardContractsTable.destroy();
                    userCardContractsTable = null;
                }
                if (userCardLogsTable) {
                    userCardLogsTable.destroy();
                    userCardLogsTable = null;
                }
                if (userCardEmailsTable) {
                    userCardEmailsTable.destroy();
                    userCardEmailsTable = null;
                }
            }

            function showUserCardFieldErrors(form, errors) {
                form.querySelectorAll('.is-invalid').forEach(function (el) {
                    el.classList.remove('is-invalid');
                });
                form.querySelectorAll('[data-error-for]').forEach(function (el) {
                    el.textContent = '';
                    el.classList.remove('d-block');
                });
                const choice = document.getElementById('user-card-discount-choice');
                if (choice) {
                    choice.classList.add('d-none');
                }
                if (window.KidsCrmGenericMultiselectSelect2) {
                    KidsCrmGenericMultiselectSelect2.clearInvalid($('#user-card-teams'));
                }

                let firstPane = '';
                Object.keys(errors || {}).forEach(function (field) {
                    const message = (errors[field] && errors[field][0]) ? String(errors[field][0]) : '';
                    let key = field;
                    if (field.indexOf('team_ids') === 0) {
                        key = 'team_ids';
                    }
                    const box = form.querySelector('[data-error-for="' + key + '"]');
                    if (box) {
                        box.textContent = message;
                        box.classList.add('d-block');
                        let input = null;
                        if (key === 'team_ids') {
                            input = form.querySelector('[name="team_ids[]"]');
                        } else if (key.indexOf('custom.') === 0) {
                            input = form.querySelector('[name="custom[' + key.slice(7) + ']"]');
                        } else {
                            input = form.querySelector('input[type="checkbox"][name="' + key + '"]')
                                || form.querySelector('[name="' + key + '"]');
                        }
                        if (input) {
                            input.classList.add('is-invalid');
                            if (key === 'team_ids' && window.KidsCrmGenericMultiselectSelect2) {
                                KidsCrmGenericMultiselectSelect2.markInvalid($(input));
                            }
                        }
                        const pane = box.closest('.tab-pane');
                        if (!firstPane && pane) {
                            firstPane = pane.id;
                        }
                    }
                    if (field === 'recalculate_unpaid_prices' && choice) {
                        choice.classList.remove('d-none');
                        const note = choice.querySelector('[data-error-for="recalculate_unpaid_prices"]');
                        if (note) {
                            note.textContent = message;
                        }
                        firstPane = 'user-card-pane-info';
                    }
                });

                if (firstPane && window.bootstrap) {
                    const tab = document.querySelector('#userCardTabs [data-bs-target="#' + firstPane + '"]');
                    if (tab) {
                        window.bootstrap.Tab.getOrCreateInstance(tab).show();
                    }
                }
            }

            function initUserCardPayments() {
                if (userCardPaymentsTable || !userCardPaymentsUrl || !$.fn.DataTable || !$('#user-card-payments-table').length) {
                    return;
                }
                userCardPaymentsTable = $('#user-card-payments-table').DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    ajax: userCardPaymentsUrl,
                    order: [[0, 'desc']],
                    language: userCardLanguage('Платежей нет'),
                    columns: [
                        { data: 'paid_at', name: 'paid_at' },
                        { data: 'summ', name: 'summ' },
                        { data: 'status_label', name: 'status_label' },
                        { data: 'team_title', name: 'team_title' },
                        { data: 'type_label', name: 'type_label' },
                        { data: 'method_label', name: 'method_label' }
                    ]
                });
            }

            function initUserCardContracts() {
                if (userCardContractsTable || !userCardContractsUrl || !$.fn.DataTable || !$('#user-card-contracts-table').length) {
                    return;
                }
                userCardContractsTable = $('#user-card-contracts-table').DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    ajax: userCardContractsUrl,
                    order: [[0, 'desc']],
                    language: userCardLanguage('Договоров нет'),
                    columns: [
                        { data: 'number', name: 'number' },
                        { data: 'status_label', name: 'status_label' },
                        { data: 'created_label', name: 'created_label' },
                        { data: 'signed_label', name: 'signed_label' },
                        {
                            data: 'url',
                            name: 'url',
                            orderable: false,
                            searchable: false,
                            render: function (data, type) {
                                if (type !== 'display' || !data) {
                                    return '';
                                }
                                return '<a href="' + $('<div>').text(data).html() + '">Открыть</a>';
                            }
                        }
                    ]
                });
            }

            function initUserCardLogs() {
                if (userCardLogsTable || !userCardLogsUrl || !$.fn.DataTable) {
                    return;
                }
                userCardLogsTable = $('#user-card-logs-table').DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    ajax: {
                        url: userCardLogsUrl,
                        data: function (payload) {
                            payload.hide_authorizations = $('#user-card-hide-logins').is(':checked') ? 1 : 0;
                        },
                        error: function (xhr) {
                            const box = document.getElementById('user-card-hide-logins-error');
                            if (!box) {
                                return;
                            }
                            let message = '';
                            if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.hide_authorizations) {
                                message = xhr.responseJSON.errors.hide_authorizations[0] || '';
                            }
                            box.textContent = message;
                            box.classList.toggle('d-none', !message);
                        }
                    },
                    order: [[0, 'desc']],
                    language: userCardLanguage('Записей нет'),
                    columns: [
                        { data: 'created_at', name: 'created_at' },
                        { data: 'author', name: 'author', orderable: false },
                        { data: 'action', name: 'action', orderable: false },
                        { data: 'description', name: 'description', orderable: false }
                    ]
                });
            }

            function userCardEscapeHtml(value) {
                return $('<div>').text(value == null ? '' : String(value)).html();
            }

            function userCardEmailStatusBadge(status) {
                if (status === 'sent') {
                    return '<span class="badge bg-success">отправлено</span>';
                }
                if (status === 'sending') {
                    return '<span class="badge bg-warning text-dark">в процессе</span>';
                }
                if (status === 'failed') {
                    return '<span class="badge bg-danger">ошибка</span>';
                }
                return status ? '<span class="badge bg-secondary">' + userCardEscapeHtml(status) + '</span>' : '';
            }

            function initUserCardEmails() {
                if (userCardEmailsTable || !userCardEmailsUrl || !$.fn.DataTable || !$('#user-card-emails-table').length) {
                    return;
                }
                userCardEmailsTable = $('#user-card-emails-table').DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    ajax: userCardEmailsUrl,
                    order: [[0, 'desc']],
                    language: userCardLanguage('Писем не было'),
                    columns: [
                        { data: 'sent_label', name: 'sent_label' },
                        {
                            data: 'status',
                            name: 'status',
                            render: function (data, type) {
                                if (type !== 'display') {
                                    return data || '';
                                }
                                return userCardEmailStatusBadge(data);
                            }
                        },
                        { data: 'to_summary', name: 'to_summary' },
                        { data: 'subject', name: 'subject' },
                        {
                            data: 'error_excerpt',
                            name: 'error_excerpt',
                            orderable: false,
                            render: function (data, type) {
                                if (type !== 'display') {
                                    return data || '';
                                }
                                return data ? '<span class="text-danger">' + userCardEscapeHtml(data) + '</span>' : '';
                            }
                        },
                        { data: 'send_attempts', name: 'send_attempts' },
                        {
                            data: 'show_url',
                            name: 'show_url',
                            orderable: false,
                            searchable: false,
                            render: function (data) {
                                const url = data || '';
                                return '<button type="button" class="btn btn-sm btn-outline-primary js-user-card-email-show" data-show-url="'
                                    + userCardEscapeHtml(url)
                                    + '">Открыть</button>';
                            }
                        }
                    ]
                });
            }

            function openUserCardEmail(showUrl) {
                showUrl = String(showUrl || '').trim();
                if (showUrl === '' || typeof showModalQueued !== 'function') {
                    return;
                }
                const title = document.getElementById('userCardEmailModalLabel');
                const body = document.getElementById('userCardEmailModalBody');
                const page = document.getElementById('userCardEmailModalOpenPage');
                const match = showUrl.match(/\/(\d+)\/?$/);
                if (title) {
                    title.textContent = match ? ('Письмо #' + match[1]) : 'Письмо';
                }
                if (body) {
                    body.innerHTML = '<div class="text-center text-muted py-4">Загрузка…</div>';
                }
                if (page) {
                    page.setAttribute('href', showUrl);
                }
                showModalQueued('userCardEmailModal', { backdrop: 'static', keyboard: false });
                $.ajax({
                    url: showUrl,
                    method: 'GET',
                    data: { modal: 1 },
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    }
                }).done(function (html) {
                    if (body) {
                        body.innerHTML = html;
                    }
                }).fail(function () {
                    if (body) {
                        body.innerHTML = '<div class="alert alert-danger mb-0">Не удалось загрузить письмо.</div>';
                    }
                });
            }

            function destroyUserCardParentSelect() {
                const $parent = $('#card-parent-id');
                if ($parent.length && $parent.hasClass('select2-hidden-accessible')) {
                    $parent.select2('destroy');
                }
            }

            function closeUserCardParentSelect() {
                const $parent = $('#card-parent-id');
                if ($parent.length && $parent.hasClass('select2-hidden-accessible')) {
                    $parent.select2('close');
                }
            }

            function userCardParentPayload(wrap) {
                return {
                    parent_id: wrap.getAttribute('data-parent-id') || null,
                    parent_lastname: wrap.getAttribute('data-parent-lastname') || '',
                    parent_firstname: wrap.getAttribute('data-parent-firstname') || '',
                    parent_middlename: wrap.getAttribute('data-parent-middlename') || '',
                    parent_full_name_genitive: wrap.getAttribute('data-parent-full-name-genitive') || '',
                    parent_passport: wrap.getAttribute('data-parent-passport') || '',
                    parent_passport_issued: wrap.getAttribute('data-parent-passport-issued') || '',
                    parent_address: wrap.getAttribute('data-parent-address') || '',
                    parent_phone: wrap.getAttribute('data-parent-phone') || '',
                    parent_email: wrap.getAttribute('data-parent-email') || ''
                };
            }

            function initUserCardParentSelect() {
                const wrap = document.querySelector('#user-card-pane-family .js-student-parent-fields');
                if (!wrap) {
                    return;
                }
                const $parent = $('#card-parent-id');
                if (userCardParentReady) {
                    if ($parent.length && $parent.hasClass('select2-hidden-accessible')) {
                        $parent.next('.select2-container').css('width', '100%');
                    }
                    return;
                }
                if ($parent.length && typeof window.initStudentParentSelects === 'function') {
                    window.initStudentParentSelects($(wrap));
                }
                if (typeof window.setStudentParentForm === 'function') {
                    window.setStudentParentForm('card', userCardParentPayload(wrap));
                }
                userCardParentReady = true;
            }

            function openUserCard(url) {
                const content = document.getElementById('userCardModalContent');
                if (!content || !userCardModalEl || !window.bootstrap) {
                    window.location.href = url;
                    return;
                }
                destroyUserCardTeamsSelect();
                destroyUserCardParentSelect();
                userCardParentReady = false;
                destroyUserCardTables();
                userCardPaymentsUrl = '';
                userCardContractsUrl = '';
                userCardLogsUrl = '';
                userCardEmailsUrl = '';
                content.innerHTML = '<div class="modal-body text-muted">Загрузка...</div>';
                window.bootstrap.Modal.getOrCreateInstance(userCardModalEl).show();
                $.ajax({
                    url: url,
                    method: 'GET',
                    cache: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    }
                }).done(function (html) {
                    content.innerHTML = html;
                    const form = content.querySelector('#user-card-form');
                    if (!form) {
                        return;
                    }
                    userCardPaymentsUrl = form.getAttribute('data-payments-url') || '';
                    userCardContractsUrl = form.getAttribute('data-contracts-url') || '';
                    userCardLogsUrl = form.getAttribute('data-logs-url') || '';
                    userCardEmailsUrl = form.getAttribute('data-emails-url') || '';
                    initUserCardTeamsSelect();
                    if (window.PhoneInputMask) {
                        window.PhoneInputMask.initIn(content);
                    }
                }).fail(function (xhr) {
                    const message = xhr.status === 404
                        ? 'Карточка ученика не найдена.'
                        : 'Не удалось открыть карточку.';
                    content.innerHTML = '<div class="modal-header"><h5 class="modal-title">Карточка ученика</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div><div class="modal-body text-danger"></div>';
                    content.querySelector('.modal-body').textContent = message;
                });
            }

            function reloadUserCardListTable() {
                ['#users-table', '#trainers-table', '#role-staff-table'].forEach(function (selector) {
                    if ($.fn.DataTable.isDataTable(selector)) {
                        $(selector).DataTable().ajax.reload(null, false);
                    }
                });
            }

            $(document).on('click', '#users-table a.js-open-user-card, #trainers-table a.js-open-user-card, #role-staff-table a.js-open-user-card', function (event) {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.which === 2) {
                    return;
                }
                event.preventDefault();
                openUserCard(this.getAttribute('href'));
            });

            $(document).on('click', '#userCardModal a.js-open-user-card', function (event) {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.which === 2) {
                    return;
                }
                event.preventDefault();
                openUserCard(this.getAttribute('href'));
            });

            $(document).on('shown.bs.tab', '#userCardTabs button', function (event) {
                const target = event.target.getAttribute('data-bs-target');
                if (target === '#user-card-pane-family') {
                    const pane = document.querySelector(target);
                    if (pane && window.PhoneInputMask) {
                        pane.querySelectorAll('.js-phone-mask, .js-parent-phone').forEach(function (input) {
                            window.PhoneInputMask.init(input, {force: true});
                        });
                    }
                    initUserCardParentSelect();
                }
                if (target === '#user-card-pane-payments') {
                    initUserCardPayments();
                }
                if (target === '#user-card-pane-contracts') {
                    initUserCardContracts();
                }
                if (target === '#user-card-pane-logs') {
                    initUserCardLogs();
                }
                if (target === '#user-card-pane-emails') {
                    initUserCardEmails();
                }
            });

            $(document).on('change', '#user-card-hide-logins', function () {
                const box = document.getElementById('user-card-hide-logins-error');
                if (box) {
                    box.textContent = '';
                    box.classList.add('d-none');
                }
                if (userCardLogsTable) {
                    userCardLogsTable.ajax.reload();
                }
            });

            $(document).on('submit', '#user-card-form', function (event) {
                event.preventDefault();
                const form = this;
                if (window.KidsCrmGenericMultiselectSelect2) {
                    KidsCrmGenericMultiselectSelect2.clearInvalid($('#user-card-teams'));
                }
                form.querySelectorAll('input[data-user-card-empty-teams]').forEach(function (el) {
                    el.remove();
                });
                const teams = form.querySelector('#user-card-teams');
                if (teams && teams.selectedOptions.length === 0) {
                    const marker = document.createElement('input');
                    marker.type = 'hidden';
                    marker.name = 'team_ids';
                    marker.value = '';
                    marker.setAttribute('data-user-card-empty-teams', '1');
                    form.appendChild(marker);
                }

                const saveBtn = document.getElementById('user-card-save');
                if (saveBtn) {
                    saveBtn.disabled = true;
                }

                $.ajax({
                    url: form.getAttribute('action'),
                    method: 'POST',
                    data: $(form).serialize(),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).done(function (response) {
                    const instance = window.bootstrap.Modal.getInstance(userCardModalEl);
                    if (instance) {
                        instance.hide();
                    }
                    reloadUserCardListTable();
                    if (typeof window.showToast === 'function') {
                        window.showToast((response && response.message) ? response.message : 'Клиент успешно обновлён.', 'success');
                    }
                }).fail(function (xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        showUserCardFieldErrors(form, xhr.responseJSON.errors);
                        return;
                    }
                    if (typeof window.showToast === 'function') {
                        window.showToast('Не удалось сохранить карточку.', 'error');
                    }
                }).always(function () {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                    }
                });
            });

            $(document).on('click', '[data-user-card-recalc]', function () {
                const form = document.getElementById('user-card-form');
                if (!form) {
                    return;
                }
                let input = form.querySelector('input[name="recalculate_unpaid_prices"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'recalculate_unpaid_prices';
                    form.appendChild(input);
                }
                input.value = this.getAttribute('data-user-card-recalc') || '0';
                $(form).trigger('submit');
            });

            const userCardLastPassword = {};
            const userCardSamePasswordMessage = 'Новый пароль совпадает с текущим.';

            function userCardForm() {
                return document.getElementById('user-card-form');
            }

            function resetUserCardPasswordUi() {
                const changeBtn = document.getElementById('user-card-change-password');
                const wrap = document.getElementById('user-card-change-pass');
                const input = document.getElementById('user-card-new-password');
                const error = document.getElementById('user-card-password-error');
                const eye = document.getElementById('user-card-toggle-password');
                if (changeBtn) {
                    changeBtn.classList.remove('d-none');
                }
                if (wrap) {
                    wrap.classList.add('d-none');
                }
                if (input) {
                    input.value = '';
                    input.setAttribute('type', 'password');
                }
                if (error) {
                    error.classList.add('d-none');
                }
                if (eye) {
                    eye.classList.add('fa-eye');
                    eye.classList.remove('fa-eye-slash');
                }
            }

            function userCardEmail() {
                const input = document.getElementById('user-card-email');
                if (input && input.matches('input')) {
                    return String(input.value || '').trim();
                }
                const form = userCardForm();
                return String(form ? (form.getAttribute('data-email') || '') : '').trim();
            }

            function syncUserCardSendPassword() {
                const button = document.getElementById('user-card-send-password');
                if (!button) {
                    return;
                }
                button.classList.toggle('d-none', userCardEmail() === '');
            }

            function notifyUserCard(message, type) {
                if (typeof window.showToast === 'function') {
                    window.showToast(message, type);
                    return;
                }
                window.alert(message);
            }

            $(document).on('click', '#user-card-change-password', function () {
                if (this.getAttribute('aria-disabled') === 'true') {
                    return;
                }
                this.classList.add('d-none');
                const wrap = document.getElementById('user-card-change-pass');
                if (wrap) {
                    wrap.classList.remove('d-none');
                }
                const input = document.getElementById('user-card-new-password');
                if (input) {
                    input.focus();
                }
            });

            $(document).on('click', '#user-card-toggle-password', function () {
                const input = document.getElementById('user-card-new-password');
                if (!input) {
                    return;
                }
                const shown = input.getAttribute('type') === 'text';
                input.setAttribute('type', shown ? 'password' : 'text');
                this.classList.toggle('fa-eye', shown);
                this.classList.toggle('fa-eye-slash', !shown);
            });

            $(document).on('keydown', '#user-card-new-password', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    $('#user-card-apply-password').trigger('click');
                }
            });

            $(document).on('click', '#user-card-cancel-password', function () {
                resetUserCardPasswordUi();
            });

            $(document).on('click', '#user-card-apply-password', function () {
                const form = userCardForm();
                const input = document.getElementById('user-card-new-password');
                const error = document.getElementById('user-card-password-error');
                if (!form || !input) {
                    return;
                }
                const newPassword = input.value;
                const userId = (form.getAttribute('action') || '').split('/').pop();
                if (newPassword.length < 8) {
                    if (error) {
                        error.classList.remove('d-none');
                    }
                    return;
                }
                if (error) {
                    error.classList.add('d-none');
                }
                if (userCardLastPassword[userId] === newPassword) {
                    notifyUserCard(userCardSamePasswordMessage, 'warning');
                    return;
                }
                const button = this;
                button.disabled = true;
                $.ajax({
                    url: form.getAttribute('data-password-url'),
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                        'Accept': 'application/json'
                    },
                    data: { password: newPassword }
                }).done(function (response) {
                    if (response && response.success) {
                        userCardLastPassword[userId] = newPassword;
                        resetUserCardPasswordUi();
                        notifyUserCard('Пароль успешно изменен', 'success');
                    }
                }).fail(function (xhr) {
                    let message = 'Произошла ошибка при сохранении данных.';
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        message = Object.values(xhr.responseJSON.errors).flat().join('\n');
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
                    notifyUserCard(message, message === userCardSamePasswordMessage ? 'warning' : 'error');
                }).always(function () {
                    button.disabled = false;
                });
            });

            $(document).on('input', '#user-card-email', syncUserCardSendPassword);

            $(document).on('click', '.js-user-card-email-show', function (event) {
                event.preventDefault();
                openUserCardEmail(this.getAttribute('data-show-url') || '');
            });

            $(document).on('click', '#user-card-send-password', function () {
                const form = userCardForm();
                const email = userCardEmail();
                const button = this;
                if (!form || email === '') {
                    return;
                }
                const confirmMessage = 'Будет сгенерирован новый пароль и отправлен на ' + email + '.\n'
                    + 'Старый пароль перестанет работать. Продолжить?';
                const send = function () {
                    button.disabled = true;
                    $.ajax({
                        url: form.getAttribute('data-welcome-url'),
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                            'Accept': 'application/json'
                        }
                    }).done(function (response) {
                        notifyUserCard((response && response.message) ? response.message : 'Пароль отправлен.', 'success');
                    }).fail(function (xhr) {
                        const message = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : 'Не удалось отправить письмо.';
                        if (typeof showErrorModal === 'function') {
                            showErrorModal('Отправка пароля', message);
                        } else {
                            notifyUserCard(message, 'error');
                        }
                    }).always(function () {
                        button.disabled = false;
                        syncUserCardSendPassword();
                    });
                };
                if (typeof showConfirmDeleteModal === 'function') {
                    showConfirmDeleteModal('Отправка пароля по почте', confirmMessage, send);
                    return;
                }
                if (window.confirm(confirmMessage)) {
                    send();
                }
            });

            $(document).on('click', '#user-card-delete', function () {
                const form = userCardForm();
                if (!form) {
                    return;
                }
                const remove = function () {
                    $.ajax({
                        url: form.getAttribute('data-delete-url'),
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                            'Accept': 'application/json'
                        }
                    }).done(function (response) {
                        if (response && response.success) {
                            const instance = window.bootstrap.Modal.getInstance(userCardModalEl);
                            if (instance) {
                                instance.hide();
                            }
                            reloadUserCardListTable();
                            notifyUserCard('Клиент успешно удален.', 'success');
                        } else {
                            notifyUserCard('Произошла ошибка при удалении клиента.', 'error');
                        }
                    }).fail(function () {
                        notifyUserCard('Произошла ошибка при удалении клиента.', 'error');
                    });
                };
                if (typeof showConfirmDeleteModal === 'function') {
                    showConfirmDeleteModal('Удаление клиента', 'Вы уверены, что хотите удалить клиента?', remove);
                    return;
                }
                if (window.confirm('Вы уверены, что хотите удалить клиента?')) {
                    remove();
                }
            });

            if (userCardModalEl) {
                userCardModalEl.addEventListener('hide.bs.modal', function () {
                    userCardHideIsQueued = userCardHideIsTemporary();
                    closeUserCardTeamsSelect();
                    closeUserCardParentSelect();
                });
                userCardModalEl.addEventListener('hidden.bs.modal', function () {
                    if (userCardHideIsQueued) {
                        userCardHideIsQueued = false;
                        return;
                    }
                    destroyUserCardTeamsSelect();
                    destroyUserCardParentSelect();
                    userCardParentReady = false;
                    destroyUserCardTables();
                    resetUserCardPasswordUi();
                });
                userCardModalEl.addEventListener('shown.bs.modal', function () {
                    userCardHideIsQueued = false;
                    restoreUserCardTeamsSelect();
                    if (document.getElementById('user-card-pane-family') && document.getElementById('user-card-pane-family').classList.contains('active')) {
                        initUserCardParentSelect();
                    }
                });
            }

            const userCardId = new URLSearchParams(window.location.search).get('card');
            if (userCardId && /^\d+$/.test(userCardId)) {
                openUserCard(@json(url('/admin/users')) + '/' + userCardId);
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('card');
                window.history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search);
            }
});
</script>
@endpush
@endonce
