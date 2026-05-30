@extends('layouts.admin2')

@php
    $usersHasActiveFilters = false;
    $canViewContracts = $canViewContracts ?? (auth()->user() && auth()->user()->can('contracts.view'));
@endphp

@section('content')
    @vite(['resources/css/admin-list-toolbar.css'])

    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Пользователи</h4>
        <div class="">
            @include('admin.users._users_section_tabs', ['activeTab' => $activeTab ?? 'users'])

            <div class="tab-content">
                <div class="card payments-report-surface border-0 shadow-sm mb-2 mb-md-3 mt-2">
                    <div class="card-body px-3 py-3">
                        <div class="payments-report-toolbar d-flex flex-nowrap align-items-center justify-content-between gap-2 gap-md-3 min-w-0">
                            <h1 class="h5 mb-0 fw-semibold text-body payments-report-title text-truncate min-w-0 flex-shrink-1">Все пользователи</h1>
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
                    <table id="users-table" class="table table-striped table-bordered align-middle w-100">
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
                            <th>Email</th>
                            <th>Телефон</th>
                            <th>Статус</th>
                            <th>Действия</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                @include('includes.modal.createUser')
                @include('includes.modal.editUser')
                @include('includes.modal.fieldModal')
            </div>
        </div>

        @include('includes.logModal')
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {

            const canViewContracts = @json((bool) $canViewContracts);
            const defaultFilterStatus = 'active';

            const defaultColumnsVisibility = {
                avatar: true,
                name: true,
                parent: true,
                ...(canViewContracts ? {contract: true} : {}),
                teams: true,
                birthday: true,
                email: true,
                phone: true,
                status_label: true,
                actions: true
            };

            let currentColumnsConfig = {...defaultColumnsVisibility};

            const columnsMap = {
                avatar: 1,
                name: 2,
                parent: 3,
                ...(canViewContracts ? {contract: 4} : {}),
                teams: canViewContracts ? 5 : 4,
                birthday: canViewContracts ? 6 : 5,
                email: canViewContracts ? 7 : 6,
                phone: canViewContracts ? 8 : 7,
                status_label: canViewContracts ? 9 : 8,
                actions: canViewContracts ? 10 : 9
            };

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function renderContractCell(row) {
                if (!row.latest_contract || !row.latest_contract.url) {
                    return '';
                }

                const contract = row.latest_contract;
                const isSigned = contract.status === 'signed';
                const iconColor = isSigned ? '#0d6efd' : '#6c757d';
                const statusLabel = escapeHtml(contract.status_label || '');
                const tooltipText = 'Статус: ' + statusLabel;

                return '<a href="' + escapeHtml(contract.url) + '" '
                    + 'class="users-contract-icon-link" '
                    + 'data-bs-toggle="tooltip" '
                    + 'data-bs-placement="top" '
                    + 'data-bs-custom-class="ulp-assignment-paid-tooltip" '
                    + 'title="' + tooltipText + '" '
                    + 'aria-label="' + tooltipText + '">'
                    + '<i class="fa-solid fa-file-pdf" style="color:' + iconColor + ';"></i>'
                    + '</a>';
            }

            function initUsersContractTooltips() {
                if (!canViewContracts || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                    return;
                }

                document.querySelectorAll('#users-table .users-contract-icon-link[data-bs-toggle="tooltip"]').forEach(function (el) {
                    const existing = bootstrap.Tooltip.getInstance(el);
                    if (existing) {
                        existing.dispose();
                    }

                    new bootstrap.Tooltip(el, {
                        customClass: 'ulp-assignment-paid-tooltip',
                    });
                });
            }

            function toBool(val, fallback = true) {
                if (val === undefined || val === null) return fallback;

                if (typeof val === 'boolean') return val;

                if (typeof val === 'number') return val === 1;

                if (typeof val === 'string') {
                    const v = val.toLowerCase().trim();
                    if (v === 'true' || v === '1') return true;
                    if (v === 'false' || v === '0') return false;
                }

                return fallback;
            }

            function applyVisibleColumns(config) {
                Object.keys(columnsMap).forEach(function (key) {
                    const colIndex = columnsMap[key];
                    const column = table.column(colIndex);

                    const isVisible = toBool(config[key], defaultColumnsVisibility[key]);

                    column.visible(isVisible);

                    $('.column-toggle[data-column-key="' + key + '"]')
                        .prop('checked', isVisible);
                });
            }

            function loadColumnsConfigFromServer() {
                $.ajax({
                    url: '/admin/users/columns-settings',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        const merged = {};

                        Object.keys(defaultColumnsVisibility).forEach(function (key) {
                            merged[key] = toBool(
                                response.hasOwnProperty(key) ? response[key] : defaultColumnsVisibility[key],
                                defaultColumnsVisibility[key]
                            );
                        });

                        currentColumnsConfig = merged;
                        applyVisibleColumns(currentColumnsConfig);
                    },
                    error: function () {
                        currentColumnsConfig = {...defaultColumnsVisibility};
                        applyVisibleColumns(currentColumnsConfig);
                    }
                });
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

            const table = $('#users-table').DataTable({
                processing: true,
                serverSide: true,
                pageLength: 10,
                lengthMenu: [10, 20, 50, 100],
                ajax: {
                    url: '/admin/users/data',
                    type: 'GET',
                    data: function (d) {
                        const params = usersFilterParams();
                        d.name = params.name;
                        d.team_id = params.team_id;
                        d.status = params.status;
                        if (canViewContracts) {
                            d.contract = params.contract;
                        }
                    }
                },

                columns: [
                    {
                        data: null,
                        name: 'rownum',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function (data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    {
                        data: 'avatar',
                        name: 'avatar',
                        orderable: true,
                        searchable: false,
                        className: 'text-center',
                        render: function (data, type, row) {
                            const url = data || '/img/default-avatar.png';
                            return '<img src="' + url + '" alt="" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">';
                        }
                    },
                    {
                        data: 'name',
                        name: 'name',
                        render: function (data, type, row) {
                            return '<a href="javascript:void(0);" ' +
                                'class="edit-user-link" ' +
                                'data-id="' + row.id + '" ' +
                                'data-bs-toggle="modal" ' +
                                'data-bs-target="#editUserModal">' +
                                data +
                                '</a>';
                        }
                    },
                    {data: 'parent', name: 'parent', defaultContent: ''},
                    ...(canViewContracts ? [{
                        data: 'latest_contract',
                        name: 'contract',
                        orderable: true,
                        searchable: false,
                        className: 'text-center',
                        defaultContent: '',
                        render: function (data, type, row) {
                            return renderContractCell(row);
                        }
                    }] : []),
                    {data: 'teams', name: 'teams', defaultContent: ''},
                    {data: 'birthday', name: 'birthday', defaultContent: ''},
                    {data: 'email', name: 'email', defaultContent: ''},
                    {data: 'phone', name: 'phone', defaultContent: ''},
                    {
                        data: 'status_label',
                        name: 'status_label',
                        render: function (data, type, row) {
                            const badgeClass = row.is_enabled ? 'bg-success' : 'bg-secondary';
                            return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                        }
                    },
                    {
                        data: null,
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-end',
                        render: function (data, type, row) {
                            return '<button type="button" ' +
                                'class="btn btn-sm btn-outline-primary edit-user-link" ' +
                                'data-id="' + row.id + '" ' +
                                'data-bs-toggle="modal" ' +
                                'data-bs-target="#editUserModal">' +
                                'Редактировать' +
                                '</button>';
                        }
                    }
                ],

                order: [[2, 'asc']],
                scrollX: true,
                language: {
                    "processing": "Обработка...",
                    "search": "",
                    "searchPlaceholder": "Поиск...",
                    "lengthMenu": "Показать _MENU_",
                    "info": "С _START_ до _END_ из _TOTAL_ записей",
                    "infoEmpty": "С 0 до 0 из 0 записей",
                    "infoFiltered": "(отфильтровано из _MAX_ записей)",
                    "loadingRecords": "Загрузка записей...",
                    "zeroRecords": "Записи отсутствуют.",
                    "emptyTable": "В таблице отсутствуют данные",
                    "paginate": {
                        "first": "",
                        "previous": "",
                        "next": "",
                        "last": ""
                    },
                    "aria": {
                        "sortAscending": ": активировать для сортировки столбца по возрастанию",
                        "sortDescending": ": активировать для сортировки столбца по убыванию"
                    }
                }
            });

            loadColumnsConfigFromServer();
            table.columns.adjust();

            table.on('draw.dt', function () {
                initUsersContractTooltips();
            });

            function reloadUsersTable() {
                table.ajax.reload();
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

            $('.column-toggle').on('change', function () {
                const key = $(this).data('column-key');
                const isChecked = $(this).is(':checked');

                currentColumnsConfig[key] = isChecked ? 1 : 0;

                applyVisibleColumns(currentColumnsConfig);

                $.ajax({
                    url: '/admin/users/columns-settings',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        columns: currentColumnsConfig
                    },
                    error: function () {
                        console.error('Не удалось сохранить настройки колонок');
                    }
                });
            });

            showLogModal("{{ route('logs.data.user') }}");
        });
    </script>
@endpush
