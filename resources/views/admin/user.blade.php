@extends('layouts.admin2')

@php
    $usersHasActiveFilters = false;
    $canViewContracts = $canViewContracts ?? (auth()->user() && auth()->user()->can('contracts.view'));
    $canViewUserSex = $canViewUserSex ?? (auth()->user() && auth()->user()->can('users.sex'));
    $canViewUserComment = $canViewUserComment ?? (auth()->user() && auth()->user()->can('users.comment'));
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css', 'resources/css/user.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Пользователи</h4>
        <div class="">
            @include('admin.users._users_section_tabs')

            <div class="tab-content">
                <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
                    <div class="card-body px-3 py-3">
                        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Пользователи</h1>
                            <div class="d-flex align-items-center gap-2 payments-report-toolbar-actions payments-report-toolbar-actions--many flex-shrink-0">
                                <button id="new-user"
                                        type="button"
                                        class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#createUserModal">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-user-plus payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Добавить</span>
                                </button>

                                @can('users.import')
                                <button type="button"
                                        class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#usersImportModal"
                                        title="Импорт учеников из Excel">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-file-import payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Импорт</span>
                                </button>
                                @endcan

                                <button type="button"
                                        class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#fieldModal"
                                        title="Настройка пользовательских полей">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-gear payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Настройки</span>
                                </button>

                                <button type="button"
                                        class="payments-report-toolbar-action d-inline-flex align-items-center gap-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#historyModal"
                                        title="История изменений">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-clock-rotate-left payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">История</span>
                                </button>

                                <button class="payments-report-toolbar-action payments-report-filters-toggle d-inline-flex align-items-center gap-2"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#usersReportFiltersCollapse"
                                        aria-expanded="{{ $usersHasActiveFilters ? 'true' : 'false' }}"
                                        aria-controls="usersReportFiltersCollapse"
                                        id="usersReportFiltersToggle">
                                    <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                        <i class="fas fa-sliders-h payments-report-toolbar-icon"></i>
                                    </span>
                                    <span class="payments-report-toolbar-label d-none d-sm-inline">Фильтры</span>
                                    <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                                </button>

                                <div class="dropdown payments-report-toolbar-dropdown">
                                    <button class="payments-report-toolbar-action payments-report-columns-toggle d-inline-flex align-items-center gap-2"
                                            type="button"
                                            id="columnsDropdown"
                                            data-bs-toggle="dropdown"
                                            data-bs-auto-close="outside"
                                            aria-expanded="false"
                                            aria-haspopup="true"
                                            title="Какие колонки показывать в таблице">
                                        <span class="payments-report-toolbar-icon-wrap" aria-hidden="true">
                                            <i class="fas fa-table-columns payments-report-toolbar-icon"></i>
                                        </span>
                                        <span class="payments-report-toolbar-label d-none d-sm-inline">Колонки</span>
                                        <i class="fas fa-chevron-down payments-report-toolbar-chevron" aria-hidden="true"></i>
                                    </button>

                                    <div class="dropdown-menu dropdown-menu-end payments-report-toolbar-dropdown-panel payments-report-columns-menu"
                                         aria-labelledby="columnsDropdown">
                                        <div class="small text-muted text-uppercase mb-2 px-1 payments-report-columns-menu-label">Вид таблицы</div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="avatar"
                                                   id="colAvatar"
                                                   checked>
                                            <label class="form-check-label" for="colAvatar">Аватар</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="name"
                                                   id="colName"
                                                   checked>
                                            <label class="form-check-label" for="colName">Имя</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="parent"
                                                   id="colParent"
                                                   checked>
                                            <label class="form-check-label" for="colParent">Родитель</label>
                                        </div>

                                        @if($canViewContracts)
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="contract"
                                                   id="colContract"
                                                   checked>
                                            <label class="form-check-label" for="colContract">Договор</label>
                                        </div>
                                        @endif

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="teams"
                                                   id="colTeams"
                                                   checked>
                                            <label class="form-check-label" for="colTeams">Группа</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="birthday"
                                                   id="colBirthday"
                                                   checked>
                                            <label class="form-check-label" for="colBirthday">Дата рождения</label>
                                        </div>

                                        @if($canViewUserSex)
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="sex"
                                                   id="colSex"
                                                   checked>
                                            <label class="form-check-label" for="colSex">Пол</label>
                                        </div>
                                        @endif

                                        @if($canViewUserComment)
                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="comment"
                                                   id="colComment"
                                                   checked>
                                            <label class="form-check-label" for="colComment">Комментарий</label>
                                        </div>
                                        @endif

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="email"
                                                   id="colEmail"
                                                   checked>
                                            <label class="form-check-label" for="colEmail">Email</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="phone"
                                                   id="colPhone"
                                                   checked>
                                            <label class="form-check-label" for="colPhone">Телефон</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="status_label"
                                                   id="colStatus"
                                                   checked>
                                            <label class="form-check-label" for="colStatus">Статус</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input column-toggle"
                                                   type="checkbox"
                                                   data-column-key="actions"
                                                   id="colActions"
                                                   checked>
                                            <label class="form-check-label" for="colActions">Действия</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="collapse {{ $usersHasActiveFilters ? 'show' : '' }} mb-2 mb-md-3" id="usersReportFiltersCollapse">
                    <form id="users-report-filters" class="border rounded p-2 p-md-3 bg-light">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label" for="filter-name">Имя</label>
                                <input id="filter-name"
                                       class="form-control"
                                       type="text"
                                       placeholder="Поиск по имени, email, телефону">
                            </div>

                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label" for="filter-team">Группа</label>
                                <select id="filter-team" class="form-select">
                                    <option value="">Все группы</option>
                                    <option value="none">Без группы</option>
                                    @foreach($allTeams as $team)
                                        <option value="{{ $team->id }}">{{ $team->title }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label" for="filter-status">Статус</label>
                                <select id="filter-status" class="form-select">
                                    <option value="">Все пользователи</option>
                                    <option value="active" selected>Только активные</option>
                                    <option value="inactive">Только неактивные</option>
                                </select>
                            </div>

                            @if($canViewContracts)
                            <div class="col-12 col-md-6 col-lg-3">
                                <label class="form-label" for="filter-contract">Договор</label>
                                <select id="filter-contract" class="form-select">
                                    <option value="">Все</option>
                                    <option value="with">С договором</option>
                                    <option value="without">Без договора</option>
                                    <option value="signed">Подписан</option>
                                    <option value="unsigned">Не подписан</option>
                                </select>
                            </div>
                            @endif

                            <div class="col-12 col-md-auto d-flex flex-wrap align-items-stretch gap-2 ms-lg-auto payments-report-filters-actions">
                                <button id="filter-apply" class="btn btn-primary payments-report-filters-submit" type="button">Применить</button>
                                <button id="filter-reset" class="btn btn-outline-secondary payments-report-filters-reset" type="button">Сброс</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table id="users-table" class="table table-striped table-bordered align-middle w-100 dt-columns-managed">
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Аватар</th>
                            <th>Имя</th>
                            <th>Родитель</th>
                            @if($canViewContracts)
                            <th>Договор</th>
                            @endif
                            <th>Группа</th>
                            <th>Дата рождения</th>
                            @if($canViewUserSex)
                            <th>Пол</th>
                            @endif
                            @if($canViewUserComment)
                            <th>Комментарий</th>
                            @endif
                            <th>Email</th>
                            <th>Телефон</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                @include('includes.modal.createUser', [
                    'lockStudentRole' => true,
                    'studentRoleId' => $studentRoleId ?? null,
                    'canViewUserSex' => $canViewUserSex,
                    'canViewUserComment' => $canViewUserComment,
                ])
                @include('includes.modal.editUser', [
                    'canViewUserSex' => $canViewUserSex,
                    'canViewUserComment' => $canViewUserComment,
                ])
                @include('includes.modal.fieldModal')
                @include('admin.users._import_modal')
            </div>
        </div>

        @include('includes.logModal')
    </div>
@endsection

@push('scripts')
@include('partials.select2.generic-multiselect')
    <script>
        $(document).ready(function () {

            const canViewContracts = @json((bool) $canViewContracts);
            const canViewUserSex = @json((bool) $canViewUserSex);
            const canViewUserComment = @json((bool) $canViewUserComment);
            const defaultFilterStatus = 'active';

            function renderContractCell(row) {
                if (!row.latest_contract || !row.latest_contract.url) {
                    return '';
                }

                const contract = row.latest_contract;
                const isSigned = contract.status === 'signed';
                const iconColor = isSigned ? '#0d6efd' : '#6c757d';
                const statusLabel = KidsCrmTooltip.escapeHtml(contract.status_label || '');
                const tooltipText = 'Статус: ' + statusLabel;
                const url = KidsCrmTooltip.escapeHtml(contract.url);

                return '<a href="' + url + '" '
                    + 'class="users-contract-icon-link js-dt-cell-ellipsis-tooltip" '
                    + 'data-bs-toggle="tooltip" '
                    + 'data-bs-placement="top" '
                    + 'data-bs-custom-class="ulp-assignment-paid-tooltip" '
                    + 'title="' + tooltipText + '" '
                    + 'aria-label="' + tooltipText + '">'
                    + '<i class="fa-solid fa-file-pdf" style="color:' + iconColor + ';"></i>'
                    + '</a>';
            }

            function usersFilterParams() {
                const params = {
                    name: $('#filter-name').val() || '',
                    team_id: $('#filter-team').val() || '',
                    status: $('#filter-status').val() || ''
                };

                if (canViewContracts) {
                    params.contract = $('#filter-contract').val() || '';
                }

                return params;
            }

            function usersHasNonDefaultFilters() {
                const params = usersFilterParams();
                return params.name !== ''
                    || params.team_id !== ''
                    || params.status !== defaultFilterStatus
                    || (canViewContracts && params.contract !== '');
            }

            function syncUsersFiltersCollapseState() {
                const hasActive = usersHasNonDefaultFilters();
                const collapseEl = document.getElementById('usersReportFiltersCollapse');
                const $toggle = $('#usersReportFiltersToggle');

                if (collapseEl && hasActive && !collapseEl.classList.contains('show')) {
                    bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false }).show();
                }

                if ($toggle.length && collapseEl) {
                    $toggle.attr('aria-expanded', collapseEl.classList.contains('show') ? 'true' : 'false');
                }
            }

            const dtApi = KidsCrmDataTable.create('#users-table', {
                columnsSettings: {
                    defaults: {
                        avatar: true,
                        name: true,
                        parent: true,
                        ...(canViewContracts ? { contract: true } : {}),
                        teams: true,
                        birthday: true,
                        ...(canViewUserSex ? { sex: true } : {}),
                        ...(canViewUserComment ? { comment: true } : {}),
                        email: true,
                        phone: true,
                        status_label: true,
                        actions: true,
                    },
                    urls: {
                        get: @json(route('admin.users.table-settings.get')),
                        save: @json(route('admin.users.table-settings.save')),
                    },
                    csrfToken: '{{ csrf_token() }}',
                },
                dataTable: {
                    ajax: {
                        url: @json(route('admin.users.data')),
                        type: 'GET',
                        data: function (d) {
                            const params = usersFilterParams();
                            d.name = params.name;
                            d.team_id = params.team_id;
                            d.status = params.status;
                            if (canViewContracts) {
                                d.contract = params.contract;
                            }
                        },
                    },
                    order: [[2, 'asc']],
                    language: @include('partials.datatables.ru'),
                },
                columns: [
                    { type: 'rownum' },
                    {
                        key: 'avatar',
                        type: 'image',
                        data: 'avatar',
                        name: 'avatar',
                        orderable: true,
                        searchable: false,
                        fallbackUrl: '/img/default-avatar.png',
                    },
                    {
                        key: 'name',
                        type: 'link',
                        data: 'name',
                        name: 'name',
                        className: 'dt-col-text',
                        linkClass: 'edit-user-link',
                        linkAttrs: function (row) {
                            return 'data-id="' + row.id + '" data-bs-toggle="modal" data-bs-target="#editUserModal"';
                        },
                    },
                    { key: 'parent', type: 'text', data: 'parent' },
                    ...(canViewContracts ? [{
                        key: 'contract',
                        type: 'icon',
                        data: 'latest_contract',
                        name: 'contract',
                        orderable: true,
                        searchable: false,
                        className: 'dt-col-icon text-center',
                        defaultContent: '',
                        sortKey: 'latest_contract',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }

                            return renderContractCell(row);
                        },
                    }] : []),
                    { key: 'teams', type: 'text', data: 'teams' },
                    { key: 'birthday', type: 'text', data: 'birthday', className: 'dt-col-text text-nowrap' },
                    ...(canViewUserSex ? [{
                        key: 'sex',
                        type: 'text',
                        data: 'sex',
                        className: 'dt-col-text text-nowrap',
                    }] : []),
                    ...(canViewUserComment ? [{
                        key: 'comment',
                        type: 'text',
                        data: 'comment',
                        className: 'dt-col-text',
                        render: function (data, type) {
                            if (type !== 'display') {
                                return data || '';
                            }

                            if (!data) {
                                return '';
                            }

                            return window.KidsCrmTooltip.renderText(data);
                        },
                    }] : []),
                    { key: 'email', type: 'text', data: 'email' },
                    { key: 'phone', type: 'text', data: 'phone', className: 'dt-col-text text-nowrap' },
                    {
                        key: 'status_label',
                        type: 'badge',
                        data: 'status_label',
                        name: 'status_label',
                        className: 'dt-col-badge text-center',
                        render: function (data, type, row) {
                            if (type !== 'display') {
                                return data || '';
                            }

                            const badgeClass = row.is_enabled ? 'bg-success' : 'bg-secondary';
                            return '<span class="badge ' + badgeClass + '">' + KidsCrmTooltip.escapeHtml(data || '') + '</span>';
                        },
                    },
                    {
                        key: 'actions',
                        type: 'actions',
                        render: function (data, type, row) {
                            return '<button type="button" '
                                + 'class="btn btn-sm btn-outline-primary edit-user-link" '
                                + 'data-id="' + row.id + '" '
                                + 'data-bs-toggle="modal" '
                                + 'data-bs-target="#editUserModal">'
                                + 'Редактировать'
                                + '</button>';
                        },
                    },
                ],
            });

            const table = dtApi.table;

            function reloadUsersTable() {
                dtApi.reload({ keepPage: true });
                syncUsersFiltersCollapseState();
            }

            $('#filter-apply').on('click', function () {
                reloadUsersTable();
            });

            $('#users-report-filters').on('submit', function (e) {
                e.preventDefault();
                reloadUsersTable();
            });

            $('#filter-reset').on('click', function () {
                $('#filter-name').val('');
                $('#filter-team').val('');
                $('#filter-status').val(defaultFilterStatus);
                if (canViewContracts) {
                    $('#filter-contract').val('');
                }
                reloadUsersTable();
            });

            $('#filter-name').on('keyup', function (e) {
                if (e.key === 'Enter') {
                    reloadUsersTable();
                }
            });

            $('#usersReportFiltersCollapse').on('shown.bs.collapse hidden.bs.collapse', function () {
                $('#usersReportFiltersToggle').attr(
                    'aria-expanded',
                    $('#usersReportFiltersCollapse').hasClass('show') ? 'true' : 'false'
                );
            });

            showLogModal("{{ route('logs.data.user') }}");

            if (typeof KidsCrmTooltip !== 'undefined' && typeof KidsCrmTooltip.bindDataTable === 'function') {
                KidsCrmTooltip.bindDataTable(table, '.js-dt-cell-ellipsis-tooltip');
            }

            if (window.KidsCrmGenericMultiselectSelect2) {
                KidsCrmGenericMultiselectSelect2.init($('#createStudentTeamIds'));
                KidsCrmGenericMultiselectSelect2.init($('#editStudentTeamIds'));
            }

            @can('users.import')
            (function initUsersImportModal() {
                const $modal = $('#usersImportModal');
                if (!$modal.length) {
                    return;
                }

                const previewUrl = @json(route('admin.users.import.preview'));
                const commitUrl = @json(route('admin.users.import.commit'));
                const csrfToken = @json(csrf_token());

                let importToken = '';

                const $fileInput = $('#users-import-file');
                const $fileError = $('#users-import-file-error');
                const $stepUpload = $('#users-import-step-upload');
                const $stepPreview = $('#users-import-step-preview');
                const $stepErrors = $('#users-import-step-errors');
                const $stepSuccess = $('#users-import-step-success');
                const $memoAccordion = $('#usersImportMemoAccordion');
                const $checkBtn = $('#users-import-check-btn');
                const $commitBtn = $('#users-import-commit-btn');
                const $resetBtn = $('#users-import-reset-btn');
                const $checkSpinner = $('#users-import-check-spinner');
                const $commitSpinner = $('#users-import-commit-spinner');

                function setLoading($btn, $spinner, isLoading) {
                    $btn.prop('disabled', isLoading);
                    $spinner.toggleClass('d-none', !isLoading);
                }

                function resetImportModal() {
                    importToken = '';
                    $fileInput.val('');
                    $fileInput.removeClass('is-invalid');
                    $fileError.text('');
                    $stepUpload.removeClass('d-none');
                    $stepPreview.addClass('d-none');
                    $stepErrors.addClass('d-none');
                    $stepSuccess.addClass('d-none');
                    $memoAccordion.removeClass('d-none');
                    $checkBtn.removeClass('d-none');
                    $commitBtn.addClass('d-none');
                    $resetBtn.addClass('d-none');
                }

                function showSuccess(response) {
                    const created = response.created || 0;
                    const updated = response.updated || 0;

                    $stepUpload.addClass('d-none');
                    $stepPreview.addClass('d-none');
                    $stepErrors.addClass('d-none');
                    $stepSuccess.removeClass('d-none');
                    $memoAccordion.addClass('d-none');

                    $('#users-import-success-message').text(
                        response.message || ('Импорт завершён: создано ' + created + ', обновлено ' + updated + '.')
                    );
                    $('#users-import-success-created').text(created);
                    $('#users-import-success-updated').text(updated);

                    $checkBtn.addClass('d-none');
                    $commitBtn.addClass('d-none');
                    $resetBtn.addClass('d-none');

                    if (typeof reloadUsersTable === 'function') {
                        reloadUsersTable();
                    }
                }

                function showErrors(message, errors) {
                    $stepUpload.addClass('d-none');
                    $stepPreview.addClass('d-none');
                    $stepErrors.removeClass('d-none');
                    $stepSuccess.addClass('d-none');
                    $memoAccordion.addClass('d-none');
                    $('#users-import-errors-summary').text(message || 'Найдены ошибки в файле.');
                    const $body = $('#users-import-errors-body');
                    $body.empty();
                    (errors || []).forEach(function (item) {
                        $body.append(
                            '<tr>'
                            + '<td>' + (item.row || '—') + '</td>'
                            + '<td>' + $('<div>').text(item.field || '').html() + '</td>'
                            + '<td>' + $('<div>').text(item.message || '').html() + '</td>'
                            + '</tr>'
                        );
                    });
                    $checkBtn.removeClass('d-none');
                    $commitBtn.addClass('d-none');
                    $resetBtn.removeClass('d-none');
                }

                function showPreview(response) {
                    importToken = response.import_token || '';
                    const summary = response.summary || {};
                    $stepUpload.addClass('d-none');
                    $stepErrors.addClass('d-none');
                    $stepSuccess.addClass('d-none');
                    $stepPreview.removeClass('d-none');
                    $memoAccordion.addClass('d-none');
                    $('#users-import-preview-success').text(
                        'Файл проверен: ' + (summary.total_rows || 0) + ' строк, '
                        + 'создать ' + (summary.create_count || 0) + ', '
                        + 'обновить ' + (summary.update_count || 0) + '.'
                    );
                    const $body = $('#users-import-preview-body');
                    $body.empty();
                    (response.preview || []).forEach(function (row) {
                        const modeLabel = row.mode === 'update' ? 'Обновление' : 'Создание';
                        $body.append(
                            '<tr>'
                            + '<td>' + row.row + '</td>'
                            + '<td>' + $('<div>').text(row.student || '').html() + '</td>'
                            + '<td>' + $('<div>').text(row.team || '').html() + '</td>'
                            + '<td>' + modeLabel + '</td>'
                            + '</tr>'
                        );
                    });
                    $checkBtn.addClass('d-none');
                    $commitBtn.removeClass('d-none');
                    $resetBtn.removeClass('d-none');
                }

                $modal.on('hidden.bs.modal', resetImportModal);
                $resetBtn.on('click', resetImportModal);

                $checkBtn.on('click', function () {
                    const file = $fileInput[0].files[0];
                    $fileInput.removeClass('is-invalid');
                    $fileError.text('');

                    if (!file) {
                        $fileInput.addClass('is-invalid');
                        $fileError.text('Выберите файл Excel для импорта.');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('_token', csrfToken);

                    setLoading($checkBtn, $checkSpinner, true);

                    $.ajax({
                        url: previewUrl,
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                    }).done(function (response) {
                        showPreview(response);
                    }).fail(function (xhr) {
                        const payload = xhr.responseJSON || {};
                        showErrors(payload.message, payload.errors);
                    }).always(function () {
                        setLoading($checkBtn, $checkSpinner, false);
                    });
                });

                $commitBtn.on('click', function () {
                    if (!importToken) {
                        showErrors('Сессия импорта не найдена. Загрузите файл повторно.', []);
                        return;
                    }

                    setLoading($commitBtn, $commitSpinner, true);

                    $.ajax({
                        url: commitUrl,
                        method: 'POST',
                        data: {
                            _token: csrfToken,
                            import_token: importToken,
                        },
                    }).done(function (response) {
                        showSuccess(response);
                    }).fail(function (xhr) {
                        const payload = xhr.responseJSON || {};
                        showErrors(payload.message || 'Не удалось выполнить импорт.', payload.errors || []);
                    }).always(function () {
                        setLoading($commitBtn, $commitSpinner, false);
                    });
                });
            })();
            @endcan
        });
    </script>
@endpush
